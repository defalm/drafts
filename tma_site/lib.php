<?php
declare(strict_types=1);

// ---------- Настройки из schedule.json ----------
function S(): array {
  static $s = null;
  if ($s === null) {
    $s = json_decode((string)file_get_contents(__DIR__ . '/schedule.json'), true, 64, JSON_THROW_ON_ERROR);
    date_default_timezone_set($s['timezone'] ?? 'Europe/Simferopol');
  }
  return $s;
}
S();
function cfg(string $k) { static $c = null; if ($c === null) $c = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : []; return $c[$k] ?? null; }
function is_demo(): bool { return !empty(S()['demo_mode']); }
function is_placeholder($v): bool { return is_string($v) && str_starts_with(trim($v), '['); }

// Число из настроек или null, если там плейсхолдер вида [ЦЕНА]
function num($v): ?int { if (is_int($v)) return $v; if (is_string($v) && preg_match('/^\d+$/', trim($v))) return (int)$v; return null; }
// Как показать значение на сайте: число → «1 500 ₽» (если $money), плейсхолдер → как есть
function show($v, bool $money = false): string { $n = num($v); if ($n === null) return (string)$v; return $money ? rub($n) : (string)$n; }

const DN = ['вс','пн','вт','ср','чт','пт','сб']; const MN = ['янв','фев','мар','апр','мая','июн','июл','авг','сен','окт','ноя','дек'];
const DN_FULL = ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'];

// ---------- Услуги ----------
function services(): array {
  static $out = null; if ($out !== null) return $out;
  $out = [];
  foreach (S()['quests'] as $q) $out[$q['id']] = $q + ['kind' => 'quest'];
  $c = S()['cinema']; $out[$c['id']] = $c + ['kind' => 'cinema'];
  return $out;
}
function service(string $id): ?array { return services()[$id] ?? null; }
function is_quest(string $id): bool { return (service($id)['kind'] ?? '') === 'quest'; }

function times_range(string $first, string $last, int $step): array {
  $out = []; $a = (int)substr($first, 0, 2) * 60 + (int)substr($first, 3, 2); $b = (int)substr($last, 0, 2) * 60 + (int)substr($last, 3, 2);
  for ($m = $a; $m <= $b; $m += max(15, $step)) $out[] = sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
  return $out;
}
function dow(string $day): int { return (int)date('w', strtotime($day)); }
function is_work_day(string $day): bool { return in_array(dow($day), S()['work_days'], true); }
function cinema_mode(string $day): string { return in_array(dow($day), S()['cinema']['group_days'], true) ? 'group' : 'private'; }

// Все сеансы услуги в этот день по расписанию (пустой массив — день нерабочий)
function slot_times(string $svc, string $day): array {
  if (!service($svc) || !is_work_day($day)) return [];
  if (is_quest($svc)) { $q = S()['quest_schedule']; return times_range($q['first'], $q['last'], (int)$q['step_min']); }
  $c = S()['cinema']; return times_range($c['first'], $c['last'], (int)$c['step_min']);
}
function seats_total(): int { return num(S()['cinema']['seats_total']) ?? (int)S()['demo_fallback']['seats_total']; }
function ppl_bounds(string $svc): array {
  $q = service($svc); $f = S()['demo_fallback'];
  $min = num($q['players_min'] ?? null) ?? (int)$f['players_min']; $max = num($q['players_max'] ?? null) ?? (int)$f['players_max'];
  return [$min, max($min, $max)];
}
function slot_ts(string $day, string $t): int { return (int)strtotime("$day $t"); }
function slot_closed_by_time(string $day, string $t, int $extraMin = -1): bool {
  $m = $extraMin >= 0 ? $extraMin : (int)S()['close_before_min'];
  return slot_ts($day, $t) <= time() + $m * 60;
}
function window_days(): array {
  $out = []; for ($i = 0; $i < (int)S()['days_ahead']; $i++) $out[] = date('Y-m-d', strtotime("today +$i day")); return $out;
}

// Цена или null, если цены ещё не заданы
function price_of(string $svc, string $day, int $qty): ?int {
  $s = service($svc); if (!$s) return null;
  if ($s['kind'] === 'quest') {
    $p = num($s['price']); if ($p === null) return null;
    $base = num($s['players_base']); $add = num($s['extra_player_price']);
    if ($base !== null && $add !== null) $p += max(0, $qty - $base) * $add;
    elseif ($qty > 0 && ($base === null) !== ($add === null)) return null;
    return $p;
  }
  if (cinema_mode($day) === 'group') { $p = num($s['seat_price']); return $p === null ? null : $p * $qty; }
  return num($s['private_price']);
}

// ---------- База ----------
function db(): PDO {
  static $db = null; if ($db) return $db;
  $db = new PDO('sqlite:' . __DIR__ . '/data/tma.sqlite');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $db->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
  $db->exec('CREATE TABLE IF NOT EXISTS bookings(id INTEGER PRIMARY KEY, service TEXT, day TEXT, time TEXT, mode TEXT, qty INT, price INT,
    name TEXT, contact TEXT, comment TEXT, status TEXT DEFAULT "new", source TEXT DEFAULT "site", ip TEXT, consent TEXT, created TEXT, updated TEXT)');
  $db->exec('CREATE INDEX IF NOT EXISTS ix_slot ON bookings(day, time, status)');
  $db->exec('CREATE TABLE IF NOT EXISTS tg_msgs(booking_id INT, chat_id TEXT, msg_id INT)');
  return $db;
}
const ACTIVE = "('new','confirmed','blocked')";
function day_rows(string $day): array {
  if (is_demo()) return []; // в демо ничего не сохраняется, все сеансы по расписанию свободны
  $st = db()->prepare('SELECT * FROM bookings WHERE day=? AND status IN ' . ACTIVE . ' ORDER BY id'); $st->execute([$day]); return $st->fetchAll();
}
// Брони, которые мешают сеансу $svc в $t. Квесты независимы, если quests_shared_sessions=false.
function conflicting(string $svc, string $t, array $rows): array {
  $shared = !empty(S()['quests_shared_sessions']) && is_quest($svc);
  return array_values(array_filter($rows, fn($r) => $r['time'] === $t && ($r['service'] === $svc || ($shared && is_quest((string)$r['service'])))));
}
// Состояние всех сеансов услуги в день: [['t'=>'12:00','free'=>true,'left'=>?int,'past'=>bool], ...]
function slot_state(string $svc, string $day, ?array $rows = null): array {
  $times = slot_times($svc, $day); if (!$times) return [];
  $rows ??= day_rows($day); $out = [];
  $group = !is_quest($svc) && cinema_mode($day) === 'group';
  foreach ($times as $t) {
    $past = slot_closed_by_time($day, $t); $c = conflicting($svc, $t, $rows);
    if ($group) {
      $blocked = (bool)array_filter($c, fn($r) => $r['status'] === 'blocked');
      $left = $blocked ? 0 : max(0, seats_total() - array_sum(array_map(fn($r) => (int)$r['qty'], $c)));
      $out[] = ['t' => $t, 'free' => !$past && $left > 0, 'left' => $left, 'past' => $past];
    } else $out[] = ['t' => $t, 'free' => !$past && !$c, 'left' => null, 'past' => $past];
  }
  return $out;
}
function block_slot(string $svc, string $day, string $t, string $note, string $src = 'bot'): array {
  if (!in_array($t, slot_times($svc, $day), true)) return ['ok' => false, 'msg' => 'В этот день у услуги нет такого сеанса'];
  if (slot_closed_by_time($day, $t, 0)) return ['ok' => false, 'msg' => 'Этот сеанс уже прошёл'];
  $c = conflicting($svc, $t, day_rows($day));
  if ($c) return ['ok' => false, 'msg' => $c[0]['status'] === 'blocked' ? 'Этот сеанс уже закрыт' : 'На это время есть бронь №' . $c[0]['id'] . ' (' . $c[0]['name'] . '). Сначала отмените её кнопкой под заявкой.'];
  db()->prepare('INSERT INTO bookings(service,day,time,mode,qty,price,name,contact,comment,status,source,created,updated) VALUES(?,?,?,?,0,NULL,?,"","","blocked",?,?,?)')
      ->execute([$svc, $day, $t, is_quest($svc) ? 'quest' : cinema_mode($day), $note ?: 'Закрыто вручную', $src, date('c'), date('c')]);
  return ['ok' => true, 'id' => (int)db()->lastInsertId(), 'msg' => service($svc)['name'] . ', ' . day_label($day, $t) . ' — закрыто'];
}

// ---------- Форматирование ----------
function day_label(string $day, string $t = ''): string {
  $ts = strtotime($day); $s = DN[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . ' ' . MN[(int)date('n', $ts) - 1];
  return $t !== '' ? "$s, $t" : $s;
}
function rub(int $n): string { return number_format($n, 0, '', ' ') . ' ₽'; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function json_out($d, int $code = 200): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function qty_label(array $b): string {
  if ($b['mode'] === 'quest') return "👥 Игроков: {$b['qty']}";
  if ($b['mode'] === 'group') return "🎟 Сборный сеанс, мест: {$b['qty']}";
  return '🎬 Приватный сеанс — весь зал';
}

// ---------- Telegram ----------
function bot_ready(): bool { $t = (string)cfg('bot_token'); return $t !== '' && !is_placeholder($t); }
function admin_ids(): array { return array_values(array_filter(array_map('strval', (array)cfg('admin_chat_ids')), fn($x) => $x !== '' && !is_placeholder($x))); }
function is_admin($chat): bool { return in_array((string)$chat, admin_ids(), true); }
function tg(string $method, array $data) {
  if (getenv('TMA_MOCK')) { // локальная проверка без интернета: запросы пишутся в data/mock.log
    file_put_contents(__DIR__ . '/data/mock.log', $method . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    static $mid = 100; return ['ok' => true, 'result' => ['message_id' => ++$mid, 'username' => 'mock_bot']];
  }
  if (!bot_ready()) return null;
  $ch = curl_init('https://api.telegram.org/bot' . cfg('bot_token') . '/' . $method);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)]);
  $r = curl_exec($ch); curl_close($ch);
  return $r ? json_decode((string)$r, true) : null;
}
const STATUS = ['new' => '🆕 Новая', 'confirmed' => '✅ Подтверждена', 'cancelled' => '❌ Отменена', 'blocked' => '⛔ Сеанс закрыт вручную'];
function booking_text(array $b): string {
  $s = service((string)$b['service']);
  $t = STATUS[$b['status']] . " заявка №{$b['id']}\n\n"
     . ($s['kind'] === 'quest' ? '🕯 Квест «' . $s['name'] . '»' : '🎬 ' . $s['name']) . "\n"
     . '📅 ' . day_label($b['day'], $b['time']) . "\n"
     . qty_label($b) . "\n"
     . '💰 ' . ($b['price'] !== null && $b['price'] !== '' ? rub((int)$b['price']) : 'цена не задана на сайте') . "\n\n"
     . '👤 ' . $b['name'] . "\n📞 " . $b['contact'];
  if (!empty($b['comment'])) $t .= "\n💬 " . $b['comment'];
  return $t;
}
function booking_kb(array $b): array {
  if ($b['status'] === 'new') return ['inline_keyboard' => [[['text' => '✅ Подтвердить', 'callback_data' => "ok:{$b['id']}"], ['text' => '❌ Отменить', 'callback_data' => "no:{$b['id']}"]]]];
  if ($b['status'] === 'confirmed') return ['inline_keyboard' => [[['text' => '❌ Отменить бронь', 'callback_data' => "no:{$b['id']}"]]]];
  return ['inline_keyboard' => []];
}
function save_msg(int $id, $chat, $r): void { if (!empty($r['ok'])) db()->prepare('INSERT INTO tg_msgs VALUES(?,?,?)')->execute([$id, (string)$chat, (int)$r['result']['message_id']]); }
function notify_new(array $b): void {
  foreach (admin_ids() as $chat) save_msg((int)$b['id'], $chat, tg('sendMessage', ['chat_id' => $chat, 'text' => booking_text($b), 'reply_markup' => booking_kb($b)]));
}
function refresh_msgs(array $b): void {
  $st = db()->prepare('SELECT chat_id, msg_id FROM tg_msgs WHERE booking_id=?'); $st->execute([$b['id']]);
  foreach ($st->fetchAll() as $m) tg('editMessageText', ['chat_id' => $m['chat_id'], 'message_id' => (int)$m['msg_id'], 'text' => booking_text($b), 'reply_markup' => booking_kb($b)]);
}
function get_booking(int $id): ?array { $st = db()->prepare('SELECT * FROM bookings WHERE id=?'); $st->execute([$id]); return $st->fetch() ?: null; }
function set_status(int $id, string $s): ?array {
  db()->prepare('UPDATE bookings SET status=?, updated=? WHERE id=?')->execute([$s, date('c'), $id]);
  $b = get_booking($id); if ($b) refresh_msgs($b); return $b;
}
