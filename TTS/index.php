<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sleep Cycle Calculator</title>
  <style>
    :root {
      --bg: #f5f7fb;
      --card: #ffffff;
      --text: #172033;
      --muted: #667085;
      --primary: #5b5ce2;
      --primary-dark: #4444c4;
      --accent: #8b5cf6;
      --success: #0f9f6e;
      --warning: #f59e0b;
      --danger: #ef4444;
      --border: #e5e7eb;
      --shadow: 0 18px 50px rgba(17, 24, 39, 0.08);
      --radius: 22px;
    }

    body.dark {
      --bg: #0f172a;
      --card: #151f35;
      --text: #f8fafc;
      --muted: #a5b4fc;
      --primary: #818cf8;
      --primary-dark: #6366f1;
      --accent: #c084fc;
      --success: #34d399;
      --warning: #fbbf24;
      --danger: #fb7185;
      --border: #29344d;
      --shadow: 0 18px 50px rgba(0, 0, 0, 0.28);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:
        radial-gradient(circle at top left, rgba(139, 92, 246, 0.18), transparent 34%),
        radial-gradient(circle at top right, rgba(91, 92, 226, 0.14), transparent 30%),
        var(--bg);
      color: var(--text);
      transition: background 0.25s ease, color 0.25s ease;
    }

    .container {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
      padding: 28px 0 56px;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 40px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 800;
      font-size: 20px;
    }

    .logo-mark {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      display: grid;
      place-items: center;
      color: #fff;
      box-shadow: var(--shadow);
    }

    .theme-toggle {
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      padding: 10px 14px;
      border-radius: 999px;
      cursor: pointer;
      font-weight: 700;
      box-shadow: 0 8px 22px rgba(17, 24, 39, 0.06);
    }

    .hero {
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      gap: 28px;
      align-items: center;
      margin-bottom: 28px;
    }

    .hero-card, .card {
      background: rgba(255, 255, 255, 0.78);
      backdrop-filter: blur(14px);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 28px;
      box-shadow: var(--shadow);
    }

    body.dark .hero-card,
    body.dark .card {
      background: rgba(21, 31, 53, 0.86);
    }

    h1 {
      font-size: clamp(36px, 5vw, 64px);
      line-height: 1.02;
      margin: 0 0 18px;
      letter-spacing: -0.045em;
    }

    h2 {
      margin: 0 0 10px;
      font-size: 26px;
      letter-spacing: -0.02em;
    }

    h3 {
      margin: 0 0 8px;
      font-size: 18px;
    }

    p {
      color: var(--muted);
      line-height: 1.65;
      margin: 0 0 16px;
    }

    .badge-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 22px;
    }

    .badge {
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--muted);
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
    }

    .moon-visual {
      min-height: 340px;
      display: grid;
      place-items: center;
      position: relative;
      overflow: hidden;
    }

    .moon {
      width: 170px;
      height: 170px;
      border-radius: 50%;
      background: linear-gradient(135deg, #fff8d6, #f7d774);
      box-shadow: 0 0 80px rgba(247, 215, 116, 0.5);
      position: relative;
    }

    .moon::after {
      content: "";
      position: absolute;
      width: 130px;
      height: 130px;
      right: -20px;
      top: -6px;
      border-radius: 50%;
      background: var(--card);
      opacity: 0.9;
    }

    .star {
      position: absolute;
      width: 7px;
      height: 7px;
      background: var(--primary);
      border-radius: 50%;
      opacity: 0.65;
      animation: pulse 2s infinite ease-in-out;
    }

    .star:nth-child(1) { top: 18%; left: 18%; }
    .star:nth-child(2) { top: 26%; right: 17%; animation-delay: 0.4s; }
    .star:nth-child(3) { bottom: 20%; left: 28%; animation-delay: 0.8s; }
    .star:nth-child(4) { bottom: 28%; right: 25%; animation-delay: 1.1s; }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.45; }
      50% { transform: scale(1.55); opacity: 1; }
    }

    .tabs {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }

    .tab {
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      padding: 12px 16px;
      border-radius: 999px;
      cursor: pointer;
      font-weight: 800;
    }

    .tab.active {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: #fff;
      border-color: transparent;
    }

    .panel {
      display: none;
    }

    .panel.active {
      display: block;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
      margin: 18px 0;
    }

    label {
      display: block;
      font-weight: 800;
      margin-bottom: 8px;
    }

    input, select {
      width: 100%;
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      padding: 14px 15px;
      border-radius: 14px;
      font-size: 16px;
      outline: none;
    }

    input:focus, select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(91, 92, 226, 0.14);
    }

    .button-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 12px;
    }

    button.primary, button.secondary {
      border: 0;
      padding: 14px 18px;
      border-radius: 14px;
      cursor: pointer;
      font-weight: 900;
      font-size: 15px;
    }

    button.primary {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      color: #fff;
      box-shadow: 0 12px 30px rgba(91, 92, 226, 0.25);
    }

    button.secondary {
      background: var(--card);
      color: var(--text);
      border: 1px solid var(--border);
    }

    .results {
      display: grid;
      gap: 12px;
      margin-top: 18px;
    }

    .result-item {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: center;
      padding: 16px;
      border: 1px solid var(--border);
      background: var(--card);
      border-radius: 18px;
    }

    .result-time {
      font-size: 28px;
      font-weight: 950;
      letter-spacing: -0.03em;
    }

    .result-meta {
      color: var(--muted);
      font-size: 14px;
      margin-top: 4px;
    }

    .quality {
      font-size: 13px;
      font-weight: 900;
      padding: 8px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .quality.best { background: rgba(15, 159, 110, 0.12); color: var(--success); }
    .quality.good { background: rgba(91, 92, 226, 0.12); color: var(--primary); }
    .quality.low { background: rgba(245, 158, 11, 0.14); color: var(--warning); }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
      margin-top: 28px;
    }

    .mini-card {
      border: 1px solid var(--border);
      background: var(--card);
      border-radius: 20px;
      padding: 20px;
      box-shadow: 0 10px 28px rgba(17, 24, 39, 0.05);
    }

    .mini-card .icon {
      font-size: 28px;
      margin-bottom: 12px;
    }

    .timeline {
      display: grid;
      gap: 10px;
      margin-top: 16px;
    }

    .timeline-item {
      display: grid;
      grid-template-columns: 82px 1fr;
      gap: 12px;
      padding: 12px;
      border-radius: 15px;
      border: 1px solid var(--border);
      background: var(--card);
    }

    .timeline-time {
      font-weight: 950;
      color: var(--primary);
    }

    .toast {
      position: fixed;
      left: 50%;
      bottom: 24px;
      transform: translateX(-50%) translateY(100px);
      background: var(--text);
      color: var(--card);
      padding: 12px 16px;
      border-radius: 999px;
      opacity: 0;
      transition: 0.25s ease;
      font-weight: 800;
      z-index: 20;
    }

    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    footer {
      margin-top: 32px;
      color: var(--muted);
      text-align: center;
      font-size: 14px;
    }

    @media (max-width: 860px) {
      .hero, .grid, .info-grid, .form-row {
        grid-template-columns: 1fr;
      }

      header {
        align-items: flex-start;
      }

      .moon-visual {
        min-height: 250px;
      }

      .result-item {
        align-items: flex-start;
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="logo">
        <div class="logo-mark">☾</div>
        <span>Sleep Cycle Calculator</span>
      </div>
      <button class="theme-toggle" id="themeToggle">🌙 Темна тема</button>
    </header>

    <section class="hero">
      <div class="hero-card">
        <h1>Прокидайся у правильний момент</h1>
        <p>
          Сервіс допомагає порахувати, коли краще лягати спати або прокидатися,
          орієнтуючись на цикли сну приблизно по 90 хвилин.
        </p>
        <p>
          Це не медичний інструмент, а простий практичний калькулятор для планування режиму сну.
        </p>
        <div class="badge-row">
          <span class="badge">90 хвилин = 1 цикл</span>
          <span class="badge">Урахування часу на засинання</span>
          <span class="badge">Денний сон</span>
          <span class="badge">План вечора</span>
        </div>
      </div>

      <div class="hero-card moon-visual">
        <span class="star"></span>
        <span class="star"></span>
        <span class="star"></span>
        <span class="star"></span>
        <div class="moon"></div>
      </div>
    </section>

    <section class="card">
      <div class="tabs">
        <button class="tab active" data-tab="wake">Коли лягати?</button>
        <button class="tab" data-tab="sleep">Коли прокидатися?</button>
        <button class="tab" data-tab="duration">Аналіз сну</button>
        <button class="tab" data-tab="nap">Денний сон</button>
        <button class="tab" data-tab="routine">План вечора</button>
      </div>

      <div class="panel active" id="wake">
        <h2>Розрахунок часу, коли краще лягати</h2>
        <p>Вкажіть, коли потрібно прокинутися, і сайт покаже оптимальні варіанти часу для сну.</p>
        <div class="form-row">
          <div>
            <label for="wakeTime">Мені потрібно прокинутися о</label>
            <input type="time" id="wakeTime" value="07:00" />
          </div>
          <div>
            <label for="fallAsleepWake">Час на засинання</label>
            <select id="fallAsleepWake">
              <option value="5">5 хвилин</option>
              <option value="10">10 хвилин</option>
              <option value="15" selected>15 хвилин</option>
              <option value="20">20 хвилин</option>
              <option value="30">30 хвилин</option>
              <option value="45">45 хвилин</option>
            </select>
          </div>
        </div>
        <div class="button-row">
          <button class="primary" onclick="calculateBedtimes()">Розрахувати</button>
          <button class="secondary" onclick="copyResults('wakeResults')">Скопіювати результат</button>
        </div>
        <div class="results" id="wakeResults"></div>
      </div>

      <div class="panel" id="sleep">
        <h2>Розрахунок часу пробудження</h2>
        <p>Вкажіть, коли плануєте лягти, і сайт підкаже, коли краще прокинутися.</p>
        <div class="form-row">
          <div>
            <label for="bedTime">Я лягаю спати о</label>
            <input type="time" id="bedTime" value="23:00" />
          </div>
          <div>
            <label for="fallAsleepSleep">Час на засинання</label>
            <select id="fallAsleepSleep">
              <option value="5">5 хвилин</option>
              <option value="10">10 хвилин</option>
              <option value="15" selected>15 хвилин</option>
              <option value="20">20 хвилин</option>
              <option value="30">30 хвилин</option>
              <option value="45">45 хвилин</option>
            </select>
          </div>
        </div>
        <div class="button-row">
          <button class="primary" onclick="calculateWakeups()">Розрахувати</button>
          <button class="secondary" onclick="copyResults('sleepResults')">Скопіювати результат</button>
        </div>
        <div class="results" id="sleepResults"></div>
      </div>

      <div class="panel" id="duration">
        <h2>Аналіз тривалості сну</h2>
        <p>Перевірте, скільки вийде сну, скільки це циклів і наскільки цей варіант зручний.</p>
        <div class="form-row">
          <div>
            <label for="durationStart">Засинання приблизно о</label>
            <input type="time" id="durationStart" value="23:30" />
          </div>
          <div>
            <label for="durationEnd">Пробудження о</label>
            <input type="time" id="durationEnd" value="07:00" />
          </div>
        </div>
        <div class="button-row">
          <button class="primary" onclick="analyzeDuration()">Проаналізувати</button>
          <button class="secondary" onclick="copyResults('durationResults')">Скопіювати результат</button>
        </div>
        <div class="results" id="durationResults"></div>
      </div>

      <div class="panel" id="nap">
        <h2>Калькулятор денного сну</h2>
        <p>Короткий денний сон може допомогти відновити енергію, але важливо не прокинутися посеред глибокого сну.</p>
        <div class="form-row">
          <div>
            <label for="napStart">Почати денний сон о</label>
            <input type="time" id="napStart" value="14:00" />
          </div>
          <div>
            <label for="napType">Тип денного сну</label>
            <select id="napType">
              <option value="20">Power nap — 20 хвилин</option>
              <option value="30">Короткий відпочинок — 30 хвилин</option>
              <option value="90" selected>Повний цикл — 90 хвилин</option>
              <option value="120">Довгий сон — 120 хвилин</option>
            </select>
          </div>
        </div>
        <div class="button-row">
          <button class="primary" onclick="calculateNap()">Розрахувати</button>
          <button class="secondary" onclick="copyResults('napResults')">Скопіювати результат</button>
        </div>
        <div class="results" id="napResults"></div>
      </div>

      <div class="panel" id="routine">
        <h2>План підготовки до сну</h2>
        <p>Сайт може сформувати простий вечірній план: коли вимкнути екрани, коли заспокоїти активність і коли лягати.</p>
        <div class="form-row">
          <div>
            <label for="targetSleepTime">Планую лягти о</label>
            <input type="time" id="targetSleepTime" value="23:00" />
          </div>
          <div>
            <label for="routineMode">Режим підготовки</label>
            <select id="routineMode">
              <option value="light">Легкий — 45 хвилин</option>
              <option value="normal" selected>Звичайний — 90 хвилин</option>
              <option value="deep">Спокійний вечір — 120 хвилин</option>
            </select>
          </div>
        </div>
        <div class="button-row">
          <button class="primary" onclick="buildRoutine()">Створити план</button>
          <button class="secondary" onclick="copyResults('routineResults')">Скопіювати план</button>
        </div>
        <div class="timeline" id="routineResults"></div>
      </div>
    </section>

    <section class="info-grid">
      <div class="mini-card">
        <div class="icon">🛌</div>
        <h3>Чому 90 хвилин?</h3>
        <p>Один цикл сну часто умовно рахують як 90 хвилин. Прокидатися наприкінці циклу зазвичай легше.</p>
      </div>
      <div class="mini-card">
        <div class="icon">⏱️</div>
        <h3>Час на засинання</h3>
        <p>За замовчуванням використовується 15 хвилин, але користувач може змінити це значення.</p>
      </div>
      <div class="mini-card">
        <div class="icon">☕</div>
        <h3>Додаткові поради</h3>
        <p>За кілька годин до сну краще зменшити кофеїн, яскраві екрани і важку активність.</p>
      </div>
    </section>

    <footer>
      Sleep Cycle Calculator — простий інструмент для планування сну. Не замінює консультацію лікаря.
    </footer>
  </div>

  <div class="toast" id="toast">Скопійовано</div>

  <script>
    const CYCLE_MINUTES = 90;
    const recommendedCycles = [6, 5, 4, 3];

    const pad = (n) => String(n).padStart(2, '0');

    function timeToMinutes(time) {
      const [h, m] = time.split(':').map(Number);
      return h * 60 + m;
    }

    function minutesToTime(total) {
      total = ((total % 1440) + 1440) % 1440;
      const h = Math.floor(total / 60);
      const m = total % 60;
      return `${pad(h)}:${pad(m)}`;
    }

    function formatHours(minutes) {
      const h = Math.floor(minutes / 60);
      const m = minutes % 60;
      if (m === 0) return `${h} год`;
      return `${h} год ${m} хв`;
    }

    function qualityForCycles(cycles) {
      if (cycles >= 5) return ['best', 'Оптимально'];
      if (cycles === 4) return ['good', 'Нормально'];
      return ['low', 'Мінімум'];
    }

    function renderResults(containerId, items) {
      const container = document.getElementById(containerId);
      container.innerHTML = items.map(item => `
        <div class="result-item">
          <div>
            <div class="result-time">${item.time}</div>
            <div class="result-meta">${item.meta}</div>
          </div>
          <span class="quality ${item.qualityClass}">${item.quality}</span>
        </div>
      `).join('');
    }

    function calculateBedtimes() {
      const wake = timeToMinutes(document.getElementById('wakeTime').value);
      const fallAsleep = Number(document.getElementById('fallAsleepWake').value);
      const items = recommendedCycles.map(cycles => {
        const sleepMinutes = cycles * CYCLE_MINUTES;
        const bedtime = wake - sleepMinutes - fallAsleep;
        const [qualityClass, quality] = qualityForCycles(cycles);
        return {
          time: minutesToTime(bedtime),
          meta: `${cycles} циклів · ${formatHours(sleepMinutes)} сну · + ${fallAsleep} хв на засинання`,
          qualityClass,
          quality
        };
      });
      renderResults('wakeResults', items);
      saveSettings();
    }

    function calculateWakeups() {
      const bed = timeToMinutes(document.getElementById('bedTime').value);
      const fallAsleep = Number(document.getElementById('fallAsleepSleep').value);
      const items = recommendedCycles.slice().reverse().map(cycles => {
        const sleepMinutes = cycles * CYCLE_MINUTES;
        const wakeup = bed + fallAsleep + sleepMinutes;
        const [qualityClass, quality] = qualityForCycles(cycles);
        return {
          time: minutesToTime(wakeup),
          meta: `${cycles} циклів · ${formatHours(sleepMinutes)} сну · засинання приблизно через ${fallAsleep} хв`,
          qualityClass,
          quality
        };
      });
      renderResults('sleepResults', items);
      saveSettings();
    }

    function analyzeDuration() {
      const start = timeToMinutes(document.getElementById('durationStart').value);
      let end = timeToMinutes(document.getElementById('durationEnd').value);
      if (end <= start) end += 1440;
      const total = end - start;
      const cycles = total / CYCLE_MINUTES;
      const fullCycles = Math.floor(cycles);
      const remainder = total % CYCLE_MINUTES;
      let qualityClass = 'low';
      let quality = 'Неідеально';
      let advice = 'Спробуйте трохи змістити час сну або пробудження, щоб наблизитися до повного циклу.';

      if (remainder <= 10 || remainder >= 80) {
        qualityClass = fullCycles >= 5 ? 'best' : 'good';
        quality = 'Добре';
        advice = 'Час близький до завершення повного циклу сну.';
      }

      if (total >= 450 && total <= 570 && (remainder <= 10 || remainder >= 80)) {
        qualityClass = 'best';
        quality = 'Оптимально';
      }

      renderResults('durationResults', [{
        time: formatHours(total),
        meta: `${fullCycles} повних циклів · залишок ${remainder} хв. ${advice}`,
        qualityClass,
        quality
      }]);
      saveSettings();
    }

    function calculateNap() {
      const start = timeToMinutes(document.getElementById('napStart').value);
      const duration = Number(document.getElementById('napType').value);
      let qualityClass = 'good';
      let quality = 'Добре';
      let note = 'Це може допомогти швидко відновити енергію.';

      if (duration === 90) {
        qualityClass = 'best';
        quality = 'Повний цикл';
        note = '90 хвилин — варіант повного циклу денного сну.';
      } else if (duration > 90) {
        qualityClass = 'low';
        quality = 'Обережно';
        note = 'Довгий денний сон може ускладнити засинання ввечері.';
      }

      renderResults('napResults', [{
        time: minutesToTime(start + duration),
        meta: `${duration} хвилин денного сну. ${note}`,
        qualityClass,
        quality
      }]);
      saveSettings();
    }

    function buildRoutine() {
      const sleep = timeToMinutes(document.getElementById('targetSleepTime').value);
      const mode = document.getElementById('routineMode').value;
      const presets = {
        light: [
          [-45, 'Зменшити яскравість екранів і світла'],
          [-25, 'Закінчити активні справи'],
          [-10, 'Спокійна підготовка до сну'],
          [0, 'Лягати спати']
        ],
        normal: [
          [-90, 'Завершити робочі задачі'],
          [-60, 'Приглушити світло, менше екранів'],
          [-30, 'Душ, читання або спокійна рутина'],
          [-10, 'Без телефону в ліжку'],
          [0, 'Лягати спати']
        ],
        deep: [
          [-120, 'Без кави, важкої їжі та інтенсивних справ'],
          [-90, 'Закрити робочі питання'],
          [-60, 'Приглушити світло та екрани'],
          [-30, 'Спокійна рутина: душ, книга, тиша'],
          [-10, 'Підготувати кімнату: темно, прохолодно, тихо'],
          [0, 'Лягати спати']
        ]
      };

      const container = document.getElementById('routineResults');
      container.innerHTML = presets[mode].map(([offset, text]) => `
        <div class="timeline-item">
          <div class="timeline-time">${minutesToTime(sleep + offset)}</div>
          <div>${text}</div>
        </div>
      `).join('');
      saveSettings();
    }

    function copyResults(id) {
      const el = document.getElementById(id);
      const text = el.innerText.trim();
      if (!text) {
        showToast('Спочатку зробіть розрахунок');
        return;
      }
      navigator.clipboard.writeText(text).then(() => showToast('Скопійовано'));
    }

    function showToast(text) {
      const toast = document.getElementById('toast');
      toast.textContent = text;
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 1800);
    }

    function saveSettings() {
      const data = {
        wakeTime: document.getElementById('wakeTime').value,
        fallAsleepWake: document.getElementById('fallAsleepWake').value,
        bedTime: document.getElementById('bedTime').value,
        fallAsleepSleep: document.getElementById('fallAsleepSleep').value,
        durationStart: document.getElementById('durationStart').value,
        durationEnd: document.getElementById('durationEnd').value,
        napStart: document.getElementById('napStart').value,
        napType: document.getElementById('napType').value,
        targetSleepTime: document.getElementById('targetSleepTime').value,
        routineMode: document.getElementById('routineMode').value,
        dark: document.body.classList.contains('dark')
      };
      localStorage.setItem('sleepCalculatorSettings', JSON.stringify(data));
    }

    function loadSettings() {
      const raw = localStorage.getItem('sleepCalculatorSettings');
      if (!raw) return;
      try {
        const data = JSON.parse(raw);
        Object.keys(data).forEach(key => {
          const el = document.getElementById(key);
          if (el) el.value = data[key];
        });
        if (data.dark) document.body.classList.add('dark');
        updateThemeButton();
      } catch (_) {}
    }

    function updateThemeButton() {
      const btn = document.getElementById('themeToggle');
      btn.textContent = document.body.classList.contains('dark') ? '☀️ Світла тема' : '🌙 Темна тема';
    }

    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.add('active');
      });
    });

    document.getElementById('themeToggle').addEventListener('click', () => {
      document.body.classList.toggle('dark');
      updateThemeButton();
      saveSettings();
    });

    document.querySelectorAll('input, select').forEach(el => {
      el.addEventListener('change', saveSettings);
    });

    loadSettings();
    calculateBedtimes();
    calculateWakeups();
    analyzeDuration();
    calculateNap();
    buildRoutine();
  </script>
</body>
</html>