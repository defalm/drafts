<?php
// Общая шапка и подвал для index.php и booking.php
function page_head(string $title, string $desc): void { $c = S()['contacts']; ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h($title) ?></title><meta name="description" content="<?= h($desc) ?>"><meta name="theme-color" content="#070606">
<?php if (is_demo()): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%23070606'/%3E%3Ctext x='16' y='24' font-size='22' text-anchor='middle' fill='%23ff2d3a' font-family='Impact,sans-serif'%3ET%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/style.css?v=2"></head><body>
<header class="hd"><div class="wrap"><a class="logo" href="./">Т<b>Ь</b>МА</a>
<nav class="nav"><a href="./#quests">Квесты</a><a href="./#kino">Кинопросмотр</a><a href="./#how">Как проходит</a><a href="./#contacts">Контакты</a></nav>
<a class="btn" href="booking.php">Забронировать</a></div></header>
<?php }

function page_foot(bool $mbar = false): void { $c = S()['contacts']; ?>
<footer><div class="wrap"><span>© <?= date('Y') ?> «ТЬМА» · хоррор-квесты · <?= h($c['address']) ?></span>
<div class="flinks"><a href="privacy.html">Политика обработки персональных данных</a><a href="consent.html">Согласие на обработку ПДн</a><a href="terms.html">Пользовательское соглашение</a><a href="cookies.html">Использование cookie</a><a href="privacy.html#op">Контакты оператора ПДн</a></div></div></footer>
<?php if (is_demo()): ?><div class="demo">Демо-набросок сайта · сделал Андрей</div><?php endif; ?>
<?php if ($mbar): ?><div class="mbar"><a class="btn" href="booking.php">Забронировать</a></div><?php endif; ?>
<div id="ck" role="dialog" aria-label="Уведомление о cookie"><span>Мы используем cookie, чтобы сайт работал корректно. <a href="cookies.html">Подробнее</a></span><button class="btn" id="ckok" type="button">Хорошо</button></div>
<script>(function(){var k="tma_ck";try{if(localStorage.getItem(k))return}catch(e){}var c=document.getElementById("ck");c.classList.add("on");document.getElementById("ckok").onclick=function(){c.classList.remove("on");try{localStorage.setItem(k,"1")}catch(e){}}})();</script>
</body></html>
<?php }
