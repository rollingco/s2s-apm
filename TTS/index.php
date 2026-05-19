<?php
$allowedLangs = ['uk', 'en'];
$lang = $_GET['lang'] ?? ($_COOKIE['sleep_lang'] ?? 'uk');
if (!in_array($lang, $allowedLangs, true)) {
    $lang = 'uk';
}
setcookie('sleep_lang', $lang, time() + 60 * 60 * 24 * 365, '/');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Sleep cycle calculator: bedtime, wake-up time, nap calculator and sleep tips." />
  <title>Sleep Cycle Calculator</title>
  <link rel="stylesheet" href="assets/styles.css?v=1.0.0" />
</head>
<body data-lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
  <div class="container">
    <header class="site-header">
      <a class="logo" href="?lang=<?= $lang ?>" aria-label="Sleep Cycle Calculator">
        <span class="logo-mark">☾</span>
        <span data-i18n="appName">Sleep Cycle Calculator</span>
      </a>

      <div class="header-actions">
        <div class="language-switch" aria-label="Language switcher">
          <button class="language-btn" data-set-lang="uk">UA</button>
          <button class="language-btn" data-set-lang="en">EN</button>
        </div>
        <button class="theme-toggle" id="themeToggle" type="button" data-i18n="darkTheme">🌙 Темна тема</button>
      </div>
    </header>

    <main>
      <section class="hero">
        <div class="hero-card">
          <p class="eyebrow" data-i18n="eyebrow">Розумний калькулятор сну</p>
          <h1 data-i18n="heroTitle">Прокидайся у правильний момент</h1>
          <p data-i18n="heroText1">Сервіс допомагає порахувати, коли краще лягати спати або прокидатися, орієнтуючись на цикли сну приблизно по 90 хвилин.</p>
          <p data-i18n="heroText2">Це не медичний інструмент, а простий практичний калькулятор для планування режиму сну.</p>
          <div class="badge-row">
            <span class="badge" data-i18n="badgeCycle">90 хвилин = 1 цикл</span>
            <span class="badge" data-i18n="badgeFallAsleep">Урахування часу на засинання</span>
            <span class="badge" data-i18n="badgeNap">Денний сон</span>
            <span class="badge" data-i18n="badgeRoutine">План вечора</span>
          </div>
        </div>

        <div class="hero-card moon-visual" aria-hidden="true">
          <span class="star"></span><span class="star"></span><span class="star"></span><span class="star"></span>
          <div class="moon"></div>
        </div>
      </section>

      <section class="card tips-card">
        <div>
          <h2 data-i18n="randomTipTitle">Порада для кращого сну</h2>
          <p id="randomTipText"></p>
        </div>
        <button class="secondary" type="button" id="nextTipBtn" data-i18n="nextTip">Інша порада</button>
      </section>

      <section class="card calculator-card">
        <nav class="tabs" aria-label="Sleep calculators">
          <button class="tab active" type="button" data-tab="wake" data-i18n="tabWake">Коли лягати?</button>
          <button class="tab" type="button" data-tab="sleep" data-i18n="tabSleep">Коли прокидатися?</button>
          <button class="tab" type="button" data-tab="duration" data-i18n="tabDuration">Аналіз сну</button>
          <button class="tab" type="button" data-tab="nap" data-i18n="tabNap">Денний сон</button>
          <button class="tab" type="button" data-tab="routine" data-i18n="tabRoutine">План вечора</button>
          <button class="tab" type="button" data-tab="alltips" data-i18n="tabTips">Усі поради</button>
        </nav>

        <div class="panel active" id="wake">
          <h2 data-i18n="wakeTitle">Розрахунок часу, коли краще лягати</h2>
          <p data-i18n="wakeText">Вкажіть, коли потрібно прокинутися, і сайт покаже оптимальні варіанти часу для сну.</p>
          <div class="form-row">
            <div><label for="wakeTime" data-i18n="wakeTimeLabel">Мені потрібно прокинутися о</label><input type="time" id="wakeTime" value="07:00" /></div>
            <div><label for="fallAsleepWake" data-i18n="fallAsleepLabel">Час на засинання</label><select id="fallAsleepWake"></select></div>
          </div>
          <div class="button-row"><button class="primary" type="button" id="calcBedtimesBtn" data-i18n="calculate">Розрахувати</button><button class="secondary" type="button" data-copy="wakeResults" data-i18n="copyResult">Скопіювати результат</button></div>
          <div class="results" id="wakeResults"></div>
        </div>

        <div class="panel" id="sleep">
          <h2 data-i18n="sleepTitle">Розрахунок часу пробудження</h2>
          <p data-i18n="sleepText">Вкажіть, коли плануєте лягти, і сайт підкаже, коли краще прокинутися.</p>
          <div class="form-row">
            <div><label for="bedTime" data-i18n="bedTimeLabel">Я лягаю спати о</label><input type="time" id="bedTime" value="23:00" /></div>
            <div><label for="fallAsleepSleep" data-i18n="fallAsleepLabel">Час на засинання</label><select id="fallAsleepSleep"></select></div>
          </div>
          <div class="button-row"><button class="primary" type="button" id="calcWakeupsBtn" data-i18n="calculate">Розрахувати</button><button class="secondary" type="button" data-copy="sleepResults" data-i18n="copyResult">Скопіювати результат</button></div>
          <div class="results" id="sleepResults"></div>
        </div>

        <div class="panel" id="duration">
          <h2 data-i18n="durationTitle">Аналіз тривалості сну</h2>
          <p data-i18n="durationText">Перевірте, скільки вийде сну, скільки це циклів і наскільки цей варіант зручний.</p>
          <div class="form-row">
            <div><label for="durationStart" data-i18n="durationStartLabel">Засинання приблизно о</label><input type="time" id="durationStart" value="23:30" /></div>
            <div><label for="durationEnd" data-i18n="durationEndLabel">Пробудження о</label><input type="time" id="durationEnd" value="07:00" /></div>
          </div>
          <div class="button-row"><button class="primary" type="button" id="analyzeDurationBtn" data-i18n="analyze">Проаналізувати</button><button class="secondary" type="button" data-copy="durationResults" data-i18n="copyResult">Скопіювати результат</button></div>
          <div class="results" id="durationResults"></div>
        </div>

        <div class="panel" id="nap">
          <h2 data-i18n="napTitle">Калькулятор денного сну</h2>
          <p data-i18n="napText">Короткий денний сон може допомогти відновити енергію, але важливо не прокинутися посеред глибокого сну.</p>
          <div class="form-row">
            <div><label for="napStart" data-i18n="napStartLabel">Почати денний сон о</label><input type="time" id="napStart" value="14:00" /></div>
            <div><label for="napType" data-i18n="napTypeLabel">Тип денного сну</label><select id="napType"></select></div>
          </div>
          <div class="button-row"><button class="primary" type="button" id="calcNapBtn" data-i18n="calculate">Розрахувати</button><button class="secondary" type="button" data-copy="napResults" data-i18n="copyResult">Скопіювати результат</button></div>
          <div class="results" id="napResults"></div>
        </div>

        <div class="panel" id="routine">
          <h2 data-i18n="routineTitle">План підготовки до сну</h2>
          <p data-i18n="routineText">Сайт може сформувати простий вечірній план: коли вимкнути екрани, коли заспокоїти активність і коли лягати.</p>
          <div class="form-row">
            <div><label for="targetSleepTime" data-i18n="targetSleepLabel">Планую лягти о</label><input type="time" id="targetSleepTime" value="23:00" /></div>
            <div><label for="routineMode" data-i18n="routineModeLabel">Режим підготовки</label><select id="routineMode"></select></div>
          </div>
          <div class="button-row"><button class="primary" type="button" id="buildRoutineBtn" data-i18n="buildPlan">Створити план</button><button class="secondary" type="button" data-copy="routineResults" data-i18n="copyPlan">Скопіювати план</button></div>
          <div class="timeline" id="routineResults"></div>
        </div>

        <div class="panel" id="alltips">
          <h2 data-i18n="allTipsTitle">15 порад для покращення сну</h2>
          <p data-i18n="allTipsText">Поради показуються рандомно на головній сторінці, але тут можна переглянути весь список.</p>
          <div class="tips-grid" id="allTipsList"></div>
        </div>
      </section>
    </main>

    <footer><span data-i18n="footerText">Sleep Cycle Calculator — простий інструмент для планування сну. Не замінює консультацію лікаря.</span></footer>
  </div>
  <div class="toast" id="toast">Copied</div>
  <script src="assets/app.js?v=1.0.0"></script>
</body>
</html>
