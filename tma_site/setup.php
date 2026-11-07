<?php
// Открыть один раз после того, как в config.php вписаны токен и пароль: https://ДОМЕН/setup.php?p=ПАРОЛЬ
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=utf-8');
$pass = (string)cfg('setup_password');
if ($pass === '' || is_placeholder($pass) || !hash_equals($pass, (string)($_GET['p'] ?? ''))) { http_response_code(403); exit("Нет доступа\n"); }
if (!bot_ready()) exit("Сначала впишите токен бота в config.php\n");
db(); echo "База: ок\n";
$secret = substr(hash('sha256', (string)cfg('bot_token') . 'tma-hook'), 0, 32);
$r = tg('setWebhook', ['url' => rtrim((string)cfg('site_url'), '/') . '/bot.php', 'secret_token' => $secret, 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
echo 'Вебхук: ' . (!empty($r['ok']) ? "ок\n" : 'ОШИБКА ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n");
$me = tg('getMe', []); if (!empty($me['ok'])) echo "Бот: @{$me['result']['username']}\nАдминистратор пишет боту /id, получает chat_id и вписывает его в config.php → admin_chat_ids.\n";
echo admin_ids() ? 'Администраторов в config.php: ' . count(admin_ids()) . "\n" : "ВНИМАНИЕ: admin_chat_ids не заполнен — заявки некому отправлять.\n";
if (is_demo()) echo "ВНИМАНИЕ: в schedule.json включён demo_mode — заявки не отправляются. Поставьте \"demo_mode\": false.\n";
tg('setMyCommands', ['commands' => [['command' => 'today', 'description' => 'Брони на сегодня'], ['command' => 'tomorrow', 'description' => 'Брони на завтра'], ['command' => 'new', 'description' => 'Неподтверждённые заявки']]]);
