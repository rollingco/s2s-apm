const CYCLE_MINUTES = 90;
const recommendedCycles = [6, 5, 4, 3];
let t = {};
let currentLang = document.body.dataset.lang || 'uk';

const $ = (id) => document.getElementById(id);
const pad = (n) => String(n).padStart(2, '0');

function timeToMinutes(time) {
  const [h, m] = time.split(':').map(Number);
  return h * 60 + m;
}

function minutesToTime(total) {
  total = ((total % 1440) + 1440) % 1440;
  return `${pad(Math.floor(total / 60))}:${pad(total % 60)}`;
}

function formatHours(minutes) {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (m === 0) return `${h} ${t.hoursShort}`;
  return `${h} ${t.hoursShort} ${m} ${t.minutesShort}`;
}

function cycleWord(cycles) {
  return cycles === 1 ? t.cycle : t.cycles;
}

function qualityForCycles(cycles) {
  if (cycles >= 5) return ['best', t.optimal];
  if (cycles === 4) return ['good', t.normal];
  return ['low', t.minimum];
}

async function loadTranslations(lang) {
  const response = await fetch(`lang/${lang}.json?v=1.0.0`);
  t = await response.json();
  currentLang = lang;
  document.documentElement.lang = lang;
  document.body.dataset.lang = lang;
  localStorage.setItem('sleepLang', lang);
  document.cookie = `sleep_lang=${lang}; path=/; max-age=31536000`;
  applyTranslations();
  fillSelects();
  calculateAll();
}

function applyTranslations() {
  document.querySelectorAll('[data-i18n]').forEach((el) => {
    const key = el.dataset.i18n;
    if (t[key]) el.textContent = t[key];
  });
  document.querySelectorAll('[data-set-lang]').forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.setLang === currentLang);
  });
  updateThemeButton();
  renderRandomTip();
  renderAllTips();
}

function fillSelect(selectId, options, selectedValue) {
  const select = $(selectId);
  select.innerHTML = options.map((option) => {
    const value = typeof option === 'object' ? option.value : option;
    const label = typeof option === 'object' ? option.label : `${option} ${t.minutes}`;
    return `<option value="${value}">${label}</option>`;
  }).join('');
  if (selectedValue) select.value = selectedValue;
}

function fillSelects() {
  fillSelect('fallAsleepWake', t.fallAsleepOptions, getSetting('fallAsleepWake', '15'));
  fillSelect('fallAsleepSleep', t.fallAsleepOptions, getSetting('fallAsleepSleep', '15'));
  fillSelect('napType', t.napOptions, getSetting('napType', '90'));
  fillSelect('routineMode', t.routineOptions, getSetting('routineMode', 'normal'));
}

function renderResults(containerId, items) {
  $(containerId).innerHTML = items.map(item => `
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
  const wake = timeToMinutes($('wakeTime').value);
  const fallAsleep = Number($('fallAsleepWake').value);
  const items = recommendedCycles.map(cycles => {
    const sleepMinutes = cycles * CYCLE_MINUTES;
    const [qualityClass, quality] = qualityForCycles(cycles);
    return {
      time: minutesToTime(wake - sleepMinutes - fallAsleep),
      meta: `${cycles} ${cycleWord(cycles)} · ${formatHours(sleepMinutes)} ${t.sleep} · ${t.fallAsleepPrefix} ${fallAsleep} ${t.fallAsleepSuffix}`,
      qualityClass,
      quality
    };
  });
  renderResults('wakeResults', items);
  saveSettings();
}

function calculateWakeups() {
  const bed = timeToMinutes($('bedTime').value);
  const fallAsleep = Number($('fallAsleepSleep').value);
  const items = recommendedCycles.slice().reverse().map(cycles => {
    const sleepMinutes = cycles * CYCLE_MINUTES;
    const [qualityClass, quality] = qualityForCycles(cycles);
    return {
      time: minutesToTime(bed + fallAsleep + sleepMinutes),
      meta: `${cycles} ${cycleWord(cycles)} · ${formatHours(sleepMinutes)} ${t.sleep} · ${t.fallAsleepPrefix} ${fallAsleep} ${t.fallAsleepSuffix}`,
      qualityClass,
      quality
    };
  });
  renderResults('sleepResults', items);
  saveSettings();
}

function analyzeDuration() {
  const start = timeToMinutes($('durationStart').value);
  let end = timeToMinutes($('durationEnd').value);
  if (end <= start) end += 1440;
  const total = end - start;
  const fullCycles = Math.floor(total / CYCLE_MINUTES);
  const remainder = total % CYCLE_MINUTES;
  let qualityClass = 'low';
  let quality = t.notIdeal;
  let advice = t.durationAdviceBad;

  if (remainder <= 10 || remainder >= 80) {
    qualityClass = fullCycles >= 5 ? 'best' : 'good';
    quality = t.good;
    advice = t.durationAdviceGood;
  }
  if (total >= 450 && total <= 570 && (remainder <= 10 || remainder >= 80)) {
    qualityClass = 'best';
    quality = t.optimal;
  }
  renderResults('durationResults', [{
    time: formatHours(total),
    meta: `${fullCycles} ${cycleWord(fullCycles)} · ${remainder} ${t.minutesShort}. ${advice}`,
    qualityClass,
    quality
  }]);
  saveSettings();
}

function calculateNap() {
  const start = timeToMinutes($('napStart').value);
  const duration = Number($('napType').value);
  let qualityClass = 'good';
  let quality = t.good;
  let note = t.napGoodNote;
  if (duration === 90) { qualityClass = 'best'; quality = t.fullCycle; note = t.napFullNote; }
  if (duration > 90) { qualityClass = 'low'; quality = t.careful; note = t.napLongNote; }
  renderResults('napResults', [{
    time: minutesToTime(start + duration),
    meta: `${duration} ${t.minutes}. ${note}`,
    qualityClass,
    quality
  }]);
  saveSettings();
}

function buildRoutine() {
  const sleep = timeToMinutes($('targetSleepTime').value);
  const mode = $('routineMode').value;
  $('routineResults').innerHTML = t.routineSteps[mode].map(([offset, text]) => `
    <div class="timeline-item">
      <div class="timeline-time">${minutesToTime(sleep + offset)}</div>
      <div>${text}</div>
    </div>
  `).join('');
  saveSettings();
}

function renderRandomTip() {
  if (!t.tips) return;
  const index = Math.floor(Math.random() * t.tips.length);
  $('randomTipText').textContent = t.tips[index];
}

function renderAllTips() {
  if (!t.tips) return;
  $('allTipsList').innerHTML = t.tips.map((tip, index) => `
    <div class="tip-item"><div class="tip-number">${index + 1}</div><p>${tip}</p></div>
  `).join('');
}

function copyResults(id) {
  const text = $(id).innerText.trim();
  if (!text) return showToast(t.calculateFirst);
  navigator.clipboard.writeText(text).then(() => showToast(t.copied));
}

function showToast(text) {
  const toast = $('toast');
  toast.textContent = text;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 1800);
}

function saveSettings() {
  const data = {
    wakeTime: $('wakeTime').value,
    fallAsleepWake: $('fallAsleepWake').value,
    bedTime: $('bedTime').value,
    fallAsleepSleep: $('fallAsleepSleep').value,
    durationStart: $('durationStart').value,
    durationEnd: $('durationEnd').value,
    napStart: $('napStart').value,
    napType: $('napType').value,
    targetSleepTime: $('targetSleepTime').value,
    routineMode: $('routineMode').value,
    dark: document.body.classList.contains('dark')
  };
  localStorage.setItem('sleepCalculatorSettings', JSON.stringify(data));
}

function getSetting(key, fallback) {
  try {
    const data = JSON.parse(localStorage.getItem('sleepCalculatorSettings') || '{}');
    return data[key] || fallback;
  } catch (_) { return fallback; }
}

function loadSettings() {
  try {
    const data = JSON.parse(localStorage.getItem('sleepCalculatorSettings') || '{}');
    Object.keys(data).forEach(key => {
      const el = $(key);
      if (el) el.value = data[key];
    });
    if (data.dark) document.body.classList.add('dark');
  } catch (_) {}
}

function updateThemeButton() {
  $('themeToggle').textContent = document.body.classList.contains('dark') ? t.lightTheme : t.darkTheme;
}

function calculateAll() {
  calculateBedtimes();
  calculateWakeups();
  analyzeDuration();
  calculateNap();
  buildRoutine();
}

document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    $(tab.dataset.tab).classList.add('active');
  });
});

document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', () => copyResults(btn.dataset.copy)));
document.querySelectorAll('[data-set-lang]').forEach(btn => btn.addEventListener('click', () => loadTranslations(btn.dataset.setLang)));
$('themeToggle').addEventListener('click', () => { document.body.classList.toggle('dark'); updateThemeButton(); saveSettings(); });
$('nextTipBtn').addEventListener('click', renderRandomTip);
$('calcBedtimesBtn').addEventListener('click', calculateBedtimes);
$('calcWakeupsBtn').addEventListener('click', calculateWakeups);
$('analyzeDurationBtn').addEventListener('click', analyzeDuration);
$('calcNapBtn').addEventListener('click', calculateNap);
$('buildRoutineBtn').addEventListener('click', buildRoutine);
document.querySelectorAll('input, select').forEach(el => el.addEventListener('change', calculateAll));

loadSettings();
loadTranslations(localStorage.getItem('sleepLang') || currentLang);
