<?php
require __DIR__ . '/../lib.php';
// GET ?s=valak → дни на days_ahead вперёд с сеансами и их доступностью
$svc = (string)($_GET['s'] ?? ''); if (!service($svc)) json_out(['error' => 'service'], 400);
$out = [];
foreach (window_days() as $d) {
  $work = is_work_day($d);
  $out[] = ['date' => $d, 'dow' => DN[dow($d)], 'day' => (int)date('j', strtotime($d)), 'mon' => MN[(int)date('n', strtotime($d)) - 1],
    'label' => day_label($d), 'work' => $work, 'mode' => is_quest($svc) ? 'quest' : cinema_mode($d),
    'slots' => $work ? slot_state($svc, $d) : []];
}
json_out(['days' => $out, 'seats_total' => is_quest($svc) ? null : seats_total(), 'demo' => is_demo()]);
