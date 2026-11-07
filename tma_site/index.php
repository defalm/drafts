<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
// значение из настроек: плейсхолдер подсвечивается, число форматируется
function v($val, bool $money = false): string { return is_placeholder($val) ? '<span class="ph-tag">' . h($val) . '</span>' : h(show($val, $money)); }
$S = S(); $c = $S['contacts']; $k = $S['cinema']; $qs = $S['quest_schedule'];
$days = fn(array $d) => implode(', ', array_map(fn($x) => DN[$x], $d));
$privDays = array_values(array_diff($S['work_days'], $k['group_days']));
$kinoTimes = times_range($k['first'], $k['last'], (int)$k['step_min']);
$tape = 'Только по предварительной записи · ' . $c['hours'] . ' · ' . $c['address'] . ' · ' . $c['rating'] . ' на Яндекс Картах · ';
page_head('«ТЬМА» — хоррор-квесты с актёрами в Ялте', 'Хоррор-квесты с живыми актёрами в Ялте, ул. Дзержинского, 21Б. Онлайн-бронирование. Чт–Вс, 12:00–00:00.');
?>
<link rel="stylesheet" href="assets/home.css?v=3">
<canvas id="grain" aria-hidden="true"></canvas>
<main class="home">

<!-- 1. Первый экран: фонарик -->
<section class="hero" id="hero">
  <div class="scrawl" aria-hidden="true">
    <span style="left:6%;top:16%;--r:-8deg">не оборачивайся</span>
    <span style="left:62%;top:12%;--r:6deg">ты не один</span>
    <span style="left:10%;top:70%;--r:4deg">выпусти нас</span>
    <span style="left:58%;top:78%;--r:-5deg">60 минут</span>
    <span style="left:76%;top:44%;--r:90deg" class="sm">оно видит тебя</span>
    <svg class="hand" style="left:30%;top:58%" viewBox="0 0 60 80"><path d="M30 78c-12 0-20-8-22-20L4 36c-1-4 5-6 7-2l5 12V10c0-5 7-5 7 0v26-30c0-5 7-5 7 0v30-26c0-5 7-5 7 0v28-20c0-5 7-5 7 0v34c0 18-8 26-18 26z"/></svg>
    <svg class="hand" style="left:80%;top:22%;--r:20deg" viewBox="0 0 60 80"><path d="M30 78c-12 0-20-8-22-20L4 36c-1-4 5-6 7-2l5 12V10c0-5 7-5 7 0v26-30c0-5 7-5 7 0v30-26c0-5 7-5 7 0v28-20c0-5 7-5 7 0v34c0 18-8 26-18 26z"/></svg>
    <i class="scratch" style="left:14%;top:34%"></i><i class="scratch" style="left:84%;top:66%;--r:30deg"></i>
  </div>
  <div class="dark" id="dark" aria-hidden="true"></div>
  <div class="eyes" aria-hidden="true">
    <b style="left:12%;top:44%"></b><b style="left:86%;top:30%"></b><b style="left:70%;top:62%" class="s"></b><b style="left:24%;top:84%" class="s"></b><b style="left:46%;top:8%" class="s"></b>
  </div>
  <div class="hero-in">
    <h1 class="glitch" data-text="ТЬМА">ТЬМА</h1>
    <p class="sub">Хоррор-квесты <span>с актёрами</span> в Ялте</p>
    <a class="rating" href="<?= h($c['map_link']) ?>" target="_blank" rel="noopener"><span class="st">★★★★★</span><b><?= h($c['rating']) ?></b> <?= h($c['rating_count']) ?> на Яндекс Картах</a>
    <div class="cta"><a class="btn" href="booking.php">Забронировать</a><a class="btn ghost" href="#quests">Выбрать дверь</a></div>
  </div>
  <p class="torch-hint" id="hint" aria-hidden="true"><span class="mouse">посветите курсором</span><span class="touch">проведите пальцем — посветите</span></p>
</section>

<!-- лента ограждения -->
<div class="tape-wrap" aria-hidden="true"><div class="tape"><div><?php for ($i = 0; $i < 4; $i++) echo '<span>' . h($tape) . '</span>'; ?></div></div></div>

<!-- 2. Квесты-двери -->
<section id="quests"><div class="wrap">
  <div class="eyebrow">Квесты</div>
  <h2>Три двери. <span class="red">Выберите одну</span></h2>
  <p class="lead">Сеанс — <?= (int)$qs['duration_min'] ?> минут. Начало каждый час с <?= h($qs['first']) ?> до <?= h($qs['last']) ?>, <?= h($days($S['work_days'])) ?>. Нажмите на дверь, чтобы заглянуть.</p>
  <div class="doors">
  <?php foreach ($S['quests'] as $i => $q): ?>
    <article class="door">
      <div class="room">
        <div class="photo"><span>фото квеста</span></div>
        <div class="in">
          <h3><?= h($q['name']) ?></h3>
          <p><?= h($q['short']) ?></p>
          <div class="chips"><span><?= (int)$qs['duration_min'] ?> мин</span><span>игроков: <?= v($q['players']) ?></span><span>возраст: <?= v($q['age']) ?></span><span>от <?= v($q['price'], true) ?></span></div>
          <a class="btn" href="booking.php?s=<?= h($q['id']) ?>">Забронировать</a>
        </div>
      </div>
      <button class="leaf" type="button" aria-expanded="false" aria-label="Открыть дверь: квест «<?= h($q['name']) ?>»">
        <span class="plate">№ <?= $i + 1 ?></span>
        <span class="pn p1"></span><span class="pn p2"></span>
        <span class="nm"><?= h($q['name']) ?></span>
        <span class="knob"></span>
        <span class="push">открыть</span>
      </button>
      <i class="leak" aria-hidden="true"></i>
    </article>
  <?php endforeach; ?>
  <?php if (!empty($S['coming_soon']['show'])): ?>
    <article class="door locked" tabindex="0" aria-label="<?= h($S['coming_soon']['title']) ?>">
      <div class="leaf static"><span class="plate">№ <?= count($S['quests']) + 1 ?></span><span class="pn p1"></span><span class="pn p2"></span><span class="knob"></span></div>
      <i class="plank a"></i><i class="plank b"></i>
      <span class="soon"><?= h($S['coming_soon']['title']) ?></span>
    </article>
  <?php endif; ?>
  </div>
</div></section>

<!-- 3. Кинопросмотр: плёнка -->
<section id="kino" class="kino-s">
  <div class="beam" aria-hidden="true"></div>
  <div class="wrap">
    <div class="eyebrow">Отдельная услуга</div>
    <h2><?= h($k['name']) ?></h2>
    <p class="lead"><?= v($k['about']) ?></p>
  </div>
  <div class="film">
    <div class="frame">
      <div class="day"><?= h($days($k['group_days'])) ?></div><h3>Сборные сеансы</h3>
      <ul><li>Продажа отдельных мест</li><li>Место: <?= v($k['seat_price'], true) ?></li><li>Мест в зале: <?= v($k['seats_total']) ?></li></ul>
      <div class="tix"><?php foreach ($kinoTimes as $t) echo '<a href="booking.php?s=' . h($k['id']) . '">' . h($t) . '</a>'; ?></div>
    </div>
    <div class="frame">
      <div class="day"><?= h($days($privDays)) ?></div><h3>Приватный зал</h3>
      <ul><li>Весь зал только для вашей компании</li><li>Зал: <?= v($k['private_price'], true) ?></li><li>До <?= v($k['private_max_people']) ?> человек</li></ul>
      <div class="tix"><?php foreach ($kinoTimes as $t) echo '<a href="booking.php?s=' . h($k['id']) . '">' . h($t) . '</a>'; ?></div>
    </div>
  </div>
  <div class="wrap"><div class="btns"><a class="btn" href="booking.php?s=<?= h($k['id']) ?>">Забронировать кинопросмотр</a></div></div>
</section>

<!-- 4. Как проходит: таймер и бикфордов шнур -->
<section id="how" class="how"><div class="wrap">
  <div class="how-grid">
    <div class="how-head">
      <div class="eyebrow">Как проходит игра</div>
      <h2>Часы уже тикают</h2>
      <div class="timer" id="timer" aria-label="<?= (int)$qs['duration_min'] ?> минут"><?= sprintf('%02d', (int)$qs['duration_min']) ?>:00</div>
      <p class="lead" id="timerNote">Время пошло, когда закрылась дверь.</p>
    </div>
    <ol class="fuse" id="fuse">
      <li><b>Бронь онлайн</b><p>Выберите квест, день и свободный сеанс прямо на сайте. Работаем только по предварительной записи.</p></li>
      <li><b>Подтверждение</b><p>Администратор свяжется с вами и подтвердит бронь.</p></li>
      <li><b>Приходите</b><p><?= h($c['address']) ?>. Приходите к началу сеанса.</p></li>
      <li><b><?= (int)$qs['duration_min'] ?> минут во тьме</b><p>Живые актёры, ваша команда и задача — выбраться.</p></li>
    </ol>
  </div>
</div></section>

<!-- 5. Контакты -->
<section id="contacts" style="padding-top:0"><div class="wrap">
  <div class="eyebrow">Контакты</div>
  <h2 class="flick">Если осмелитесь</h2>
  <div class="ct">
    <div>
      <div class="li"><small>Адрес</small><b><?= h($c['address']) ?></b></div>
      <div class="li"><small>Режим работы</small><b><?= h($c['hours']) ?></b></div>
      <div class="li"><small>Телефон</small><b><a href="tel:<?= h($c['phone_href']) ?>"><?= h($c['phone']) ?></a></b></div>
      <div class="li"><small>Бронь в Telegram</small><b><a href="https://t.me/<?= h($c['telegram_booking']) ?>" target="_blank" rel="noopener">@<?= h($c['telegram_booking']) ?></a></b></div>
      <div class="li"><small>Канал и ВКонтакте</small><b><a href="<?= h($c['channel']) ?>" target="_blank" rel="noopener">Telegram-канал</a> · <a href="<?= h($c['vk']) ?>" target="_blank" rel="noopener">Написать во ВКонтакте</a></b></div>
      <div class="btns"><a class="btn" href="booking.php">Забронировать</a><a class="btn ghost" href="<?= h($c['map_link']) ?>" target="_blank" rel="noopener">Маршрут</a></div>
    </div>
    <a class="mapbox radar" href="<?= h($c['map_link']) ?>" target="_blank" rel="noopener"><i class="ring"></i><i class="ring r2"></i><i class="ring r3"></i><i class="pin"></i><b><?= h($c['address']) ?></b><span>Открыть на Яндекс Картах</span></a>
  </div>
</div></section>
</main>
<script src="assets/home.js?v=2" defer></script>
<?php page_foot(true);
