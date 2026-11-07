<?php
require __DIR__ . '/../lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method'], 405);
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (!empty($in['website'])) json_out(['ok' => true, 'id' => 0]); // ловушка для спам-ботов
$phone = S()['contacts']['phone'];

$svc = (string)($in['service'] ?? ''); $day = (string)($in['day'] ?? ''); $t = (string)($in['time'] ?? '');
$qty = (int)($in['qty'] ?? 0);
$name = trim(mb_substr((string)($in['name'] ?? ''), 0, 60)); $contact = trim(mb_substr((string)($in['contact'] ?? ''), 0, 60));
$comment = trim(mb_substr((string)($in['comment'] ?? ''), 0, 300));

if (!service($svc) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) json_out(['error' => 'Выберите услугу и день'], 400);
if (!in_array($day, window_days(), true)) json_out(['error' => 'Эта дата недоступна для записи'], 400);
if (!is_work_day($day)) json_out(['error' => 'В этот день мы не работаем. Сеансы — четверг–воскресенье.'], 400);
if (!in_array($t, slot_times($svc, $day), true)) json_out(['error' => 'Выберите сеанс'], 400);

$mode = is_quest($svc) ? 'quest' : cinema_mode($day);
if ($mode === 'quest') { [$mn, $mx] = ppl_bounds($svc); if ($qty < $mn || $qty > $mx) json_out(['error' => "Количество игроков: от $mn до $mx"], 400); }
elseif ($mode === 'group') { if ($qty < 1 || $qty > seats_total()) json_out(['error' => 'Укажите количество мест'], 400); }
else $qty = 0; // приватный сеанс — бронь всего зала

if (empty($in['consent'])) json_out(['error' => 'Нужно согласие на обработку персональных данных'], 400);
if (mb_strlen($name) < 2) json_out(['error' => 'Укажите имя'], 400);
if (strlen(preg_replace('/\D/', '', $contact)) < 10) json_out(['error' => 'Укажите телефон'], 400);

$price = price_of($svc, $day, $qty);
$state = array_column(slot_state($svc, $day), null, 't')[$t] ?? null;
$check = fn($st) => $st && $st['free'] && ($mode !== 'group' || $qty <= $st['left']);

if (is_demo()) { // ДЕМО: ничего не сохраняем и никуда не отправляем
  if (!$check($state)) json_out(['error' => 'Этот сеанс недоступен. Выберите другое время.', 'taken' => true], 409);
  json_out(['ok' => true, 'demo' => true, 'id' => 0, 'price' => $price, 'label' => day_label($day, $t)]);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? ''; $db = db();
$st = $db->prepare('SELECT COUNT(*) FROM bookings WHERE ip=? AND created > ?'); $st->execute([$ip, date('c', time() - 3600)]);
if ((int)$st->fetchColumn() >= 5) json_out(['error' => "Слишком много заявок. Позвоните нам: $phone"], 429);
$db->exec('BEGIN IMMEDIATE');
$state = array_column(slot_state($svc, $day), null, 't')[$t] ?? null; // повторная проверка внутри транзакции
if (!$check($state)) { $db->exec('ROLLBACK'); json_out(['error' => $mode === 'group' && $state && $state['left'] > 0 ? "Осталось мест: {$state['left']}" : 'Этот сеанс только что заняли. Выберите другое время.', 'taken' => true], 409); }
$db->prepare('INSERT INTO bookings(service,day,time,mode,qty,price,name,contact,comment,status,source,ip,consent,created,updated) VALUES(?,?,?,?,?,?,?,?,?,"new","site",?,?,?,?)')
   ->execute([$svc, $day, $t, $mode, $qty, $price, $name, $contact, $comment, $ip, date('c') . ' ' . $ip, date('c'), date('c')]);
$id = (int)$db->lastInsertId(); $db->exec('COMMIT');
try { notify_new(get_booking($id)); } catch (Throwable $e) { error_log('tg: ' . $e->getMessage()); }
json_out(['ok' => true, 'id' => $id, 'price' => $price, 'label' => day_label($day, $t)]);
