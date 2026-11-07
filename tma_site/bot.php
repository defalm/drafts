<?php
// Вебхук Telegram-бота. Telegram присылает сюда сообщения и нажатия кнопок.
require __DIR__ . '/lib.php';
$secret = substr(hash('sha256', (string)cfg('bot_token') . 'tma-hook'), 0, 32);
if (($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '') !== $secret) { http_response_code(403); exit; }
$u = json_decode((string)file_get_contents('php://input'), true) ?: [];
$db = db();
function say($chat, string $text, array $extra = []) { return tg('sendMessage', ['chat_id' => $chat, 'text' => $text] + $extra); }
function edit($chat, $mid, string $text, array $kb = []) { tg('editMessageText', ['chat_id' => $chat, 'message_id' => $mid, 'text' => $text, 'reply_markup' => ['inline_keyboard' => $kb]]); }
function short_name(string $svc): string { return is_quest($svc) ? '«' . service($svc)['name'] . '»' : 'Кино'; }

function list_day(string $day): string {
  $s = '📅 ' . day_label($day) . "\n";
  if (!is_work_day($day)) return $s . 'Выходной день.';
  $st = db()->prepare('SELECT * FROM bookings WHERE day=? AND status IN ' . ACTIVE . ' ORDER BY time, id'); $st->execute([$day]); $rows = $st->fetchAll();
  if (!$rows) return $s . 'Броней нет, все сеансы свободны.';
  foreach ($rows as $b) $s .= "\n" . ($b['status'] === 'confirmed' ? '✅' : ($b['status'] === 'blocked' ? '⛔' : '🆕')) . " {$b['time']} · " . short_name((string)$b['service']) . ' · '
     . ($b['status'] === 'blocked' ? 'закрыт' : ($b['mode'] === 'quest' ? "{$b['qty']} чел" : ($b['mode'] === 'group' ? "{$b['qty']} мест" : 'весь зал')) . " · {$b['name']}, {$b['contact']}");
  return $s;
}
// --- закрытие / открытие сеансов: день → услуга → время ---
function days_kb(): array {
  $kb = []; $row = [];
  for ($i = 0; $i < 21 && count($kb) * 2 + count($row) < 12; $i++) { $d = date('Y-m-d', strtotime("today +$i day")); if (!is_work_day($d)) continue;
    $row[] = ['text' => ($i === 0 ? 'Сегодня, ' : ($i === 1 ? 'Завтра, ' : '')) . day_label($d), 'callback_data' => "bd:$d"];
    if (count($row) === 2) { $kb[] = $row; $row = []; } }
  if ($row) $kb[] = $row;
  return $kb;
}
function services_kb(string $day): array {
  $kb = []; foreach (services() as $id => $s) $kb[] = [['text' => ($s['kind'] === 'quest' ? '🕯 ' : '🎬 ') . $s['name'] . ($s['kind'] === 'cinema' ? (cinema_mode($day) === 'group' ? ' (сборный)' : ' (приватный)') : ''), 'callback_data' => "bs:$day:$id"]];
  $kb[] = [['text' => '← Другой день', 'callback_data' => 'bl']];
  return $kb;
}
function times_kb(string $svc, string $day): array {
  $kb = []; $row = []; $rows = day_rows($day); $group = !is_quest($svc) && cinema_mode($day) === 'group';
  foreach (slot_times($svc, $day) as $t) {
    $c = conflicting($svc, $t, $rows); $blk = array_values(array_filter($c, fn($r) => $r['status'] === 'blocked')); $hm = str_replace(':', '', $t);
    if (slot_closed_by_time($day, $t, 0)) $btn = ['text' => '· ' . $t, 'callback_data' => 'x'];
    elseif ($blk) $btn = ['text' => '⛔ ' . $t, 'callback_data' => "ub:{$blk[0]['id']}"];
    elseif ($c) $btn = ['text' => '👥 ' . $t . ($group ? ' · ' . array_sum(array_column($c, 'qty')) . '/' . seats_total() : ''), 'callback_data' => "bk:$svc:$day:$hm"];
    else $btn = ['text' => '🟢 ' . $t, 'callback_data' => "bt:$svc:$day:$hm"];
    $row[] = $btn; if (count($row) === 3) { $kb[] = $row; $row = []; } }
  if ($row) $kb[] = $row;
  $kb[] = [['text' => '← Другая услуга', 'callback_data' => "bd:$day"]];
  return $kb;
}
function times_text(string $svc, string $day): string {
  $shared = !empty(S()['quests_shared_sessions']) && is_quest($svc);
  return '📅 ' . day_label($day) . ' · ' . short_name($svc) . (!is_quest($svc) ? (cinema_mode($day) === 'group' ? ' (сборный, мест: ' . seats_total() . ')' : ' (приватный)') : '')
    . "\n\n🟢 свободно — нажмите, чтобы закрыть\n⛔ закрыто — нажмите, чтобы открыть\n👥 бронь с сайта — нажмите, чтобы посмотреть"
    . ($shared ? "\n\nКвесты идут общими сеансами: закрытие действует на все квесты." : '');
}
function blocked_kb(): array {
  $rows = db()->query("SELECT * FROM bookings WHERE status='blocked' AND day>=date('now','localtime','-1 day') ORDER BY day, time LIMIT 40")->fetchAll();
  $kb = [];
  foreach ($rows as $b) { if (slot_closed_by_time($b['day'], $b['time'], 0)) continue; $kb[] = [['text' => '🔓 ' . short_name((string)$b['service']) . ' · ' . day_label($b['day'], $b['time']), 'callback_data' => "ub:{$b['id']}"]]; }
  return $kb;
}
function hm($s): string { return substr((string)$s, 0, 2) . ':' . substr((string)$s, 2, 2); }
$menu = ['reply_markup' => ['keyboard' => [[['text' => '📋 Сегодня'], ['text' => '📋 Завтра']], [['text' => '🔒 Закрыть сеанс'], ['text' => '🔓 Открыть сеанс']], [['text' => '🆕 Неподтверждённые']]], 'resize_keyboard' => true]];

if (isset($u['callback_query'])) {
  $cq = $u['callback_query']; $chat = $cq['message']['chat']['id'] ?? 0; $mid = $cq['message']['message_id'] ?? 0;
  $ans = fn($t) => tg('answerCallbackQuery', ['callback_query_id' => $cq['id'], 'text' => $t]);
  if (!is_admin($chat)) { $ans('Нет доступа'); exit; }
  $p = explode(':', (string)$cq['data']); $act = $p[0];
  $who = trim(($cq['from']['first_name'] ?? '') . ' ' . (isset($cq['from']['username']) ? '@' . $cq['from']['username'] : ''));
  if ($act === 'x') { $ans('Этот сеанс уже прошёл'); exit; }
  if ($act === 'bl') { edit($chat, $mid, "🔒 Какой день?\n\nИли напишите сообщением, например: закрыть 15.11 19:00 валак", days_kb()); $ans(''); exit; }
  if ($act === 'bd') { $day = $p[1] ?? ''; edit($chat, $mid, '📅 ' . day_label($day) . "\nКакую услугу?", services_kb($day)); $ans(''); exit; }
  if ($act === 'bs') { [$day, $svc] = [$p[1] ?? '', $p[2] ?? '']; if (service($svc)) edit($chat, $mid, times_text($svc, $day), times_kb($svc, $day)); $ans(''); exit; }
  if ($act === 'bt') { [$svc, $day, $t] = [$p[1] ?? '', $p[2] ?? '', hm($p[3] ?? '')];
    $r = block_slot($svc, $day, $t, 'Закрыл ' . $who); edit($chat, $mid, times_text($svc, $day), times_kb($svc, $day)); $ans($r['ok'] ? '⛔ ' . $t . ' закрыто' : $r['msg']); exit; }
  if ($act === 'ub') { $b = get_booking((int)($p[1] ?? 0));
    if ($b && $b['status'] === 'blocked') { $db->prepare("UPDATE bookings SET status='cancelled', updated=? WHERE id=?")->execute([date('c'), $b['id']]); $msg = '🟢 ' . $b['time'] . ' снова свободно на сайте'; } else $msg = 'Уже открыто';
    if (str_starts_with((string)($cq['message']['text'] ?? ''), '🔓')) { $kb = blocked_kb(); edit($chat, $mid, $kb ? '🔓 Закрытые сеансы — нажмите, чтобы открыть:' : 'Закрытых сеансов нет 👍', $kb); }
    elseif ($b) edit($chat, $mid, times_text((string)$b['service'], $b['day']), times_kb((string)$b['service'], $b['day']));
    $ans($msg); exit; }
  if ($act === 'bk') { [$svc, $day, $t] = [$p[1] ?? '', $p[2] ?? '', hm($p[3] ?? '')];
    foreach (conflicting($svc, $t, day_rows($day)) as $b) save_msg((int)$b['id'], $chat, say($chat, booking_text($b), ['reply_markup' => booking_kb($b)]));
    $ans('Брони на это время — отменить можно кнопкой под заявкой'); exit; }
  // подтверждение / отмена заявки
  $b = get_booking((int)($p[1] ?? 0));
  if (!$b) { $ans('Заявка не найдена'); exit; }
  if ($act === 'ok' && $b['status'] === 'new') { set_status((int)$b['id'], 'confirmed'); $msg = 'Подтверждено'; }
  elseif ($act === 'no' && in_array($b['status'], ['new', 'confirmed'], true)) { set_status((int)$b['id'], 'cancelled'); $msg = 'Отменено, сеанс снова свободен на сайте'; }
  else { refresh_msgs($b); $msg = 'Статус уже изменён'; }
  $ans($msg); exit;
}

$m = $u['message'] ?? null; if (!$m || !isset($m['text'])) exit;
$chat = $m['chat']['id']; $text = trim((string)$m['text']);
$who = trim(($m['from']['first_name'] ?? '') . ' ' . (isset($m['from']['username']) ? '@' . $m['from']['username'] : ''));
$site = (string)cfg('site_url'); $c = S()['contacts'];
if (!is_admin($chat)) {
  // chat_id нужен, чтобы вписать его в config.php → admin_chat_ids
  if (str_starts_with($text, '/id')) say($chat, 'Ваш chat_id: ' . $chat);
  else say($chat, "Здравствуйте! Записаться в «ТЬМУ» можно на сайте: $site\nТелефон: {$c['phone']}\nTelegram: @{$c['telegram_booking']}");
  exit;
}
$tl = mb_strtolower($text);
if ($text === '/start') say($chat, "Готово! Сюда приходят заявки с сайта.\n\nПод каждой заявкой кнопки «Подтвердить» и «Отменить». Отменённый сеанс сразу снова свободен на сайте.\n\n🔒 «Закрыть сеанс» — если записались по телефону.\n🔓 «Открыть сеанс» — вернуть закрытый сеанс.", $menu);
elseif ($text === '📋 Сегодня' || $text === '/today') say($chat, list_day(date('Y-m-d')), $menu);
elseif ($text === '📋 Завтра' || $text === '/tomorrow') say($chat, list_day(date('Y-m-d', strtotime('tomorrow'))), $menu);
elseif ($text === '🔒 Закрыть сеанс' || $text === '/close') say($chat, "🔒 Какой день?\n\nИли напишите сообщением, например: закрыть 15.11 19:00 валак", ['reply_markup' => ['inline_keyboard' => days_kb()]]);
elseif ($text === '🔓 Открыть сеанс' || $text === '/open') { $kb = blocked_kb(); say($chat, $kb ? '🔓 Закрытые сеансы — нажмите, чтобы открыть:' : "Закрытых сеансов нет 👍\n\nЧтобы отменить бронь с сайта, нажмите «Отменить» под самой заявкой.", $kb ? ['reply_markup' => ['inline_keyboard' => $kb]] : $menu); }
elseif (preg_match('/^(закр\S*|откр\S*)\s+(\d{1,2})[.\/](\d{1,2})\s+(\d{1,2})[:.](\d{2})(?:\s+(.+))?$/u', $tl, $mm)) {
  $y = (int)date('Y'); $day = sprintf('%04d-%02d-%02d', $y, $mm[3], $mm[2]); if ($day < date('Y-m-d', strtotime('-1 day'))) $day = sprintf('%04d-%02d-%02d', $y + 1, $mm[3], $mm[2]);
  $t = sprintf('%02d:%02d', $mm[4], $mm[5]); $want = trim($mm[6] ?? '');
  $svcs = array_keys(array_filter(services(), fn($s) => $want === '' || str_starts_with(mb_strtolower($s['name']), $want) || ($want === 'кино' && $s['kind'] === 'cinema')));
  $svcs = array_values(array_filter($svcs, fn($s) => in_array($t, slot_times($s, $day), true)));
  if (!checkdate((int)$mm[3], (int)$mm[2], $y) || !$svcs) say($chat, "Не нашёл такого сеанса. Пример: закрыть 15.11 19:00 валак\n(без названия — все услуги на это время)", $menu);
  elseif (str_starts_with($mm[1], 'закр')) { $lines = []; foreach ($svcs as $s) { $r = block_slot($s, $day, $t, 'Закрыл ' . $who); $lines[] = ($r['ok'] ? '⛔ ' : '⚠️ ' . short_name($s) . ': ') . $r['msg']; } say($chat, implode("\n", $lines), $menu); }
  else { $lines = []; $rows = day_rows($day);
    foreach ($svcs as $s) { $blk = array_values(array_filter(conflicting($s, $t, $rows), fn($r) => $r['status'] === 'blocked' && $r['service'] === $s));
      if ($blk) { $db->prepare("UPDATE bookings SET status='cancelled', updated=? WHERE id=?")->execute([date('c'), $blk[0]['id']]); $lines[] = '🟢 ' . short_name($s) . ', ' . day_label($day, $t) . ' снова свободно'; }
      else $lines[] = '· ' . short_name($s) . ': не был закрыт вручную'; }
    say($chat, implode("\n", $lines), $menu); }
}
elseif ($text === '🆕 Неподтверждённые' || $text === '/new') {
  $rows = $db->query("SELECT * FROM bookings WHERE status='new' ORDER BY id")->fetchAll();
  if (!$rows) say($chat, 'Все заявки обработаны 👍', $menu);
  foreach ($rows as $b) save_msg((int)$b['id'], $chat, say($chat, booking_text($b), ['reply_markup' => booking_kb($b)]));
}
else say($chat, "Что умеет бот:\n📋 Сегодня / Завтра — список броней\n🔒 Закрыть сеанс — день → услуга → время (или «закрыть 15.11 19:00 валак»)\n🔓 Открыть сеанс — вернуть закрытый (или «открыть 15.11 19:00 валак»)\n🆕 Неподтверждённые — заявки без ответа", $menu);
