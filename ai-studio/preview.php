<?php
session_name('ai_studio_session');
session_start();
if (!isset($_SESSION['studio_user_id'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preview — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; }

/* Left Nav */
.day-nav {
  width: 260px; min-height: 100vh;
  position: fixed; top: 0; left: 0; z-index: 40;
  background: rgba(0,0,0,0.3);
  border-right: 1px solid rgba(255,255,255,0.08);
  display: flex; flex-direction: column;
  overflow: hidden;
}
.day-nav-header {
  padding: 20px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  flex-shrink: 0;
}
.day-list {
  overflow-y: auto; flex: 1; padding: 8px;
}
.day-list::-webkit-scrollbar { width: 4px; }
.day-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }

.day-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 8px;
  cursor: pointer; transition: all 0.15s;
  font-size: 13px; color: rgba(255,255,255,0.5);
  margin-bottom: 2px;
}
.day-item:hover { background: rgba(255,255,255,0.07); color: #fff; }
.day-item.active { background: rgba(255,255,255,0.12); color: #fff; font-weight: 600; }
.day-item.quiz-day { border-left: 3px solid #fbbf24; padding-left: 9px; }

.week-label {
  color: rgba(255,255,255,0.25); font-size: 10px;
  font-weight: 700; letter-spacing: 1.5px;
  padding: 12px 12px 4px;
}

/* Content area */
.content-area { margin-left: 260px; padding: 32px 40px; min-height: 100vh; }
.content-card {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 16px; padding: 36px;
  max-width: 860px; margin: 0 auto;
}

/* Day content styling */
.day-content h3 { color: #fff; font-size: 18px; font-weight: 700; margin: 20px 0 10px; }
.day-content h3:first-child { margin-top: 0; }
.day-content p  { color: rgba(255,255,255,0.75); font-size: 15px; line-height: 1.75; margin-bottom: 14px; }
.day-content ul, .day-content ol { color: rgba(255,255,255,0.75); padding-left: 20px; margin-bottom: 14px; }
.day-content li { margin-bottom: 6px; font-size: 15px; line-height: 1.65; }
.day-content strong { color: #fff; }
.day-content em    { color: rgba(255,255,255,0.65); }
.day-content blockquote {
  background: rgba(255,255,255,0.05);
  border-left: 3px solid rgba(255,255,255,0.4);
  border-radius: 0 10px 10px 0;
  padding: 14px 18px; margin: 16px 0;
  color: rgba(255,255,255,0.7); font-size: 14px;
}
.day-content pre {
  background: #d7dbdb;
  border: 1px solid #b9bfbf;
  border-radius: 10px; padding: 18px;
  overflow-x: auto; margin: 16px 0;
  color: #1a1a1a;
  white-space: pre-wrap; word-break: break-word;
}
.day-content pre code {
  background: transparent;
}
.day-content code {
  font-family: 'Courier New', monospace;
  color: #86efac; font-size: 13px;
}
.day-content p code {
  background: rgba(255,255,255,0.08);
  padding: 2px 7px; border-radius: 5px;
  color: #fbbf24; font-size: 13px;
}

/* Topic badges */
.topic-badge {
  display: inline-block;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  color: rgba(255,255,255,0.7);
  border-radius: 20px; padding: 4px 12px;
  font-size: 12px; margin: 3px;
}

/* Quiz section */
.quiz-section {
  background: rgba(251,191,36,0.08);
  border: 1px solid rgba(251,191,36,0.25);
  border-radius: 14px; padding: 24px;
  margin-top: 32px;
}
.quiz-question {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px; padding: 16px;
  margin-bottom: 16px;
}
.quiz-option {
  padding: 10px 14px; border-radius: 8px; margin-top: 8px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  cursor: pointer; font-size: 14px;
  color: rgba(255,255,255,0.7); transition: all 0.2s;
}
.quiz-option:hover  { background: rgba(255,255,255,0.1); color: #fff; }
.quiz-option.correct { background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.4); color: #4ade80; }
.quiz-option.wrong   { background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.3); color: #f87171; }

/* Image search links */
.img-search-box {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px; padding: 16px 20px;
  margin: 20px 0;
}
.img-link {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.7);
  padding: 7px 14px; border-radius: 8px;
  font-size: 13px; text-decoration: none;
  transition: all 0.2s; margin: 4px;
}
.img-link:hover { background: rgba(255,255,255,0.14); color: #fff; }

/* Nav buttons */
.nav-btn {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  color: #fff; border-radius: 10px;
  padding: 11px 22px; font-size: 14px;
  font-weight: 600; cursor: pointer;
  transition: all 0.2s; text-decoration: none;
  display: inline-flex; align-items: center; gap: 8px;
}
.nav-btn:hover { background: rgba(255,255,255,0.15); }
.nav-btn.primary {
  background: #fff; color: #1F2A44;
}
.nav-btn.primary:hover { background: #f0f0f0; }

/* ✅ Slider styling */
.day-slider-wrap {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  flex-shrink: 0;
}
#daySlider {
  -webkit-appearance: none;
  width: 100%; height: 4px;
  border-radius: 4px;
  background: rgba(255,255,255,0.15);
  outline: none; cursor: pointer;
}
#daySlider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 14px; height: 14px;
  border-radius: 50%;
  background: #a78bfa;
  cursor: pointer;
  box-shadow: 0 0 6px rgba(167,139,250,0.6);
}
#daySlider::-moz-range-thumb {
  width: 14px; height: 14px;
  border-radius: 50%;
  background: #a78bfa;
  cursor: pointer;
  border: none;
}
</style>
</head>
<body>

<!-- Day Navigation Sidebar -->
<div class="day-nav">
  <div class="day-nav-header">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
      <span style="font-size:18px">🤖</span>
      <span style="font-weight:800;font-size:15px">AI Studio</span>
    </div>
    <p style="color:rgba(255,255,255,0.35);font-size:12px" id="navTopic">Loading...</p>
  </div>

  <!-- ✅ DAY SLIDER -->
  <div class="day-slider-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <span style="color:rgba(255,255,255,0.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase">Jump to Day</span>
      <span id="sliderLabel" style="color:#a78bfa;font-size:12px;font-weight:800">Day 1</span>
    </div>
    <input type="range" id="daySlider" min="1" max="30" value="1">
    <div style="display:flex;justify-content:space-between;margin-top:5px">
      <span style="color:rgba(255,255,255,0.2);font-size:10px">Day 1</span>
      <span id="sliderMax" style="color:rgba(255,255,255,0.2);font-size:10px">Day 30</span>
    </div>
  </div>
  <!-- ✅ SLIDER END -->

  <div class="day-list" id="dayList">
    <div style="color:rgba(255,255,255,0.3);padding:20px;font-size:13px">Loading...</div>
  </div>
  <div style="padding:12px;border-top:1px solid rgba(255,255,255,0.08);flex-shrink:0">
    <a href="generate.php" class="nav-btn" style="width:100%;justify-content:center;font-size:13px">
      ✨ New Course
    </a>
  </div>
</div>

<!-- Content Area -->
<div class="content-area">
  <div style="max-width:860px;margin:0 auto">

    <!-- Top bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px">
      <div>
        <h1 id="pageTitle" style="font-size:20px;font-weight:800;margin-bottom:4px">Preview</h1>
        <p id="pageSubtitle" style="color:rgba(255,255,255,0.4);font-size:13px"></p>
      </div>
      <div style="display:flex;gap:10px">
        <button class="nav-btn" onclick="goPublish()">📤 Save to LMS</button>
      </div>
    </div>

    <!-- Day Content Card -->
    <div class="content-card" id="contentCard">
      <div style="text-align:center;padding:60px 20px;color:rgba(255,255,255,0.3)">
        <div style="font-size:40px;margin-bottom:12px">👈</div>
        <p>Baaye se koi din select karo</p>
      </div>
    </div>

    <!-- Prev / Next -->
    <div style="display:flex;justify-content:space-between;margin-top:20px" id="dayNav">
    </div>

  </div>
</div>

<script>
let course     = [];
let meta       = {};
let currentDay = 1;

// Defensive code-block fix for legacy content already in sessionStorage so old
// courses also render with a light theme + black/red/blue syntax highlighting.
var PRE_STYLE_PV  = 'background:#d7dbdb;color:#1a1a1a;padding:16px;border-radius:8px;overflow-x:auto;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-word;margin:14px 0;border:1px solid #b9bfbf';
var CODE_KEYWORDS_PV = 'def|class|return|if|elif|else|for|while|do|switch|case|import|from|as|in|is|not|and|or|None|True|False|null|nil|true|false|try|except|catch|finally|throw|throws|with|lambda|yield|pass|break|continue|self|this|function|fn|func|var|let|const|new|delete|public|private|protected|static|final|void|int|float|double|string|str|bool|boolean|char|long|short|byte|print|println|echo|require|include|async|await|extends|implements|interface|struct|type|enum|package|namespace|using|val|fun|abstract|override';
function highlightCodePV(s){
  if(s == null) return '';
  s = String(s);
  if(s.length > 20000) return s;
  try {
    var tokens = [];
    function stash(html){ tokens.push(html); return '\u0001z' + (tokens.length - 1) + 'z\u0001'; }
    s = s.replace(/((?:#|\/\/|--)[^\n\r]*)/g, function(m){ return stash('<span style="color:#5c6370">' + m + '</span>'); });
    s = s.replace(/&quot;[\s\S]*?&quot;|&#0*39;[\s\S]*?&#0*39;|"[^"\n]*"|'[^'\n]*'|`[^`\n]*`/g, function(m){ return stash('<span style="color:#c41a16">' + m + '</span>'); });
    s = s.replace(/&[a-zA-Z#0-9]+;/g, function(m){ return stash(m); });
    s = s.replace(/\b\d+(?:\.\d+)?\b/g, function(m){ return stash('<span style="color:#c41a16">' + m + '</span>'); });
    s = s.replace(new RegExp('\\b(?:' + CODE_KEYWORDS_PV + ')\\b', 'g'), function(m){ return stash('<span style="color:#0033b3;font-weight:600">' + m + '</span>'); });
    s = s.replace(/\u0001z(\d+)z\u0001/g, function(_, i){ return tokens[+i]; });
    return s;
  } catch(e){ return s; }
}
function fixCodeBlocks(html){
  try {
    if(html == null) return '';
    html = String(html);
    if(!html) return '';
    return html.replace(/<pre[^>]*>([\s\S]*?)<\/pre>/gi, function(m, inner){
      var code = inner.replace(/<\/?code[^>]*>/gi, '');
      code = highlightCodePV(code);
      return '<pre style="' + PRE_STYLE_PV + '"><code style="background:transparent;color:#1a1a1a;font-family:inherit">' + code + '</code></pre>';
    });
  } catch(e){ return (html == null ? '' : String(html)); }
}

function init() {
  const rawC = sessionStorage.getItem('ai_course_data');
  const rawM = sessionStorage.getItem('ai_meta');
  if (!rawC || !rawM) { window.location.href = 'generate.php'; return; }

  const data = JSON.parse(rawC);
  course     = data.course || [];
  meta       = JSON.parse(rawM);

  document.getElementById('navTopic').textContent    = meta.topic + ' — ' + meta.days + ' Days';
  document.getElementById('pageTitle').textContent   = meta.topic + ' Course Preview';
  document.getElementById('pageSubtitle').textContent = meta.days + ' days • ' + meta.level + ' • ' + meta.language;

  buildSidebar();
  showDay(1);
}

function buildSidebar() {
  const list = document.getElementById('dayList');
  list.innerHTML = '';

  let weekNum = 0;
  course.forEach((day, idx) => {
    if (idx % 7 === 0) {
      weekNum++;
      const wl = document.createElement('div');
      wl.className   = 'week-label';
      wl.textContent = `WEEK ${weekNum}`;
      list.appendChild(wl);
    }

    const item = document.createElement('div');
    item.className = 'day-item' + (day.has_quiz ? ' quiz-day' : '');
    item.id        = `nav-day-${day.day}`;
    item.innerHTML = `
      <span style="color:rgba(255,255,255,0.25);font-size:11px;min-width:26px">D${day.day}</span>
      <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${day.title}</span>
      ${day.has_quiz ? '<span style="font-size:10px">📝</span>' : ''}
    `;
    item.onclick = () => showDay(day.day);
    list.appendChild(item);
  });

  // ✅ Slider initialize
  const slider   = document.getElementById('daySlider');
  const label    = document.getElementById('sliderLabel');
  const maxLabel = document.getElementById('sliderMax');

  slider.max           = course.length;
  slider.value         = 1;
  maxLabel.textContent = 'Day ' + course.length;
  label.textContent    = 'Day 1';

  slider.addEventListener('input', function () {
    const d = parseInt(this.value);
    label.textContent = 'Day ' + d;
    showDay(d);
  });
}

function showDay(dayNum) {
  currentDay = dayNum;

  // ✅ Slider sync
  const slider = document.getElementById('daySlider');
  const label  = document.getElementById('sliderLabel');
  if (slider) { slider.value = dayNum; label.textContent = 'Day ' + dayNum; }

  // Sidebar active
  document.querySelectorAll('.day-item').forEach(el => el.classList.remove('active'));
  const navEl = document.getElementById(`nav-day-${dayNum}`);
  if (navEl) {
    navEl.classList.add('active');
    navEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  const day = course.find(d => d.day === dayNum);
  if (!day) return;

  // Image search links
  const q         = encodeURIComponent(day.image_query || day.title + ' ' + meta.topic);
  const imgSearch = `
    <div class="img-search-box">
      <p style="color:rgba(255,255,255,0.45);font-size:12px;margin-bottom:10px;font-weight:600">🖼️ IMAGE SEARCH — Download karke manually upload karo LMS mein</p>
      <p style="color:rgba(255,255,255,0.3);font-size:11px;margin-bottom:10px">Search query: "${day.image_query || ''}"</p>
      <div>
        <a href="https://www.google.com/search?tbm=isch&q=${q}" target="_blank" class="img-link">🔍 Google Images</a>
        <a href="https://unsplash.com/s/photos/${q}" target="_blank" class="img-link">🌄 Unsplash</a>
        <a href="https://www.pexels.com/search/${q}/" target="_blank" class="img-link">📸 Pexels</a>
        <a href="https://pixabay.com/images/search/${q}/" target="_blank" class="img-link">🎨 Pixabay</a>
        <a href="https://www.freepik.com/search?query=${q}" target="_blank" class="img-link">🖌️ Freepik</a>
      </div>
    </div>
  `;

  // Quiz HTML
  let quizHtml = '';
  if (day.has_quiz && day.quiz && day.quiz.length > 0) {
    const qItems = day.quiz.map((q, qi) => {
      const opts = q.options.map((opt, oi) =>
        `<div class="quiz-option" onclick="checkAns(this, ${qi}, ${oi}, ${q.correct})" data-qi="${qi}" data-oi="${oi}">
          <span style="font-weight:700;margin-right:8px">${['A','B','C','D'][oi]}.</span>${opt}
        </div>`
      ).join('');
      return `
        <div class="quiz-question">
          <p style="color:#fff;font-size:14px;font-weight:600;margin-bottom:10px">
            <span style="color:rgba(255,255,255,0.4);margin-right:8px">Q${qi+1}.</span>${q.question}
          </p>
          ${opts}
          <div id="exp-${qi}" style="display:none;margin-top:10px;padding:10px 14px;background:rgba(34,197,94,0.08);border-radius:8px;color:#86efac;font-size:13px">
            💡 ${q.explanation || ''}
          </div>
        </div>
      `;
    }).join('');

    quizHtml = `
      <div class="quiz-section" style="margin-top:32px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
          <span style="font-size:24px">📝</span>
          <div>
            <h3 style="color:#fbbf24;margin:0;font-size:18px">Day ${day.day} Quiz</h3>
            <p style="color:rgba(255,255,255,0.4);font-size:13px;margin:0">${day.quiz.length} questions — test your knowledge</p>
          </div>
        </div>
        ${qItems}
      </div>
    `;
  }

  // Topics badges
  const topicsHtml = (day.topics || []).map(t =>
    `<span class="topic-badge">${t}</span>`
  ).join('');

  // Full content
  document.getElementById('contentCard').innerHTML = `
    <div style="margin-bottom:24px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <span style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;color:rgba(255,255,255,0.7)">DAY ${day.day}</span>
        ${day.has_quiz ? '<span style="background:rgba(251,191,36,0.15);border:1px solid rgba(251,191,36,0.3);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;color:#fbbf24">📝 QUIZ DAY</span>' : ''}
      </div>
      <h2 style="font-size:24px;font-weight:800;margin-bottom:12px">${day.title}</h2>
      <div>${topicsHtml}</div>
    </div>

    <hr style="border:none;border-top:1px solid rgba(255,255,255,0.08);margin-bottom:28px">

    ${imgSearch}

    <div class="day-content">${fixCodeBlocks(day.content) || '<p style="color:rgba(255,255,255,0.4)">Content not available.</p>'}</div>

    ${quizHtml}
  `;

  // Prev/Next
  const idx  = course.findIndex(d => d.day === dayNum);
  const prev = idx > 0 ? course[idx-1] : null;
  const next = idx < course.length-1 ? course[idx+1] : null;

  document.getElementById('dayNav').innerHTML = `
    <div>
      ${prev ? `<button class="nav-btn" onclick="showDay(${prev.day})">← Day ${prev.day}</button>` : '<div></div>'}
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <span style="color:rgba(255,255,255,0.3);font-size:13px">${dayNum} / ${course.length}</span>
      ${next ? `<button class="nav-btn" onclick="showDay(${next.day})">Day ${next.day} →</button>` : `<button class="nav-btn primary" onclick="goPublish()">📤 Save to LMS →</button>`}
    </div>
  `;
}

function checkAns(el, qi, oi, correct) {
  const parent = el.parentElement;
  parent.querySelectorAll('.quiz-option').forEach(opt => {
    opt.onclick = null;
    opt.style.cursor = 'default';
  });

  const selected = parseInt(el.dataset.oi);
  parent.querySelectorAll('.quiz-option').forEach(opt => {
    if (parseInt(opt.dataset.oi) === correct) opt.classList.add('correct');
    else if (parseInt(opt.dataset.oi) === selected) opt.classList.add('wrong');
  });

  const expEl = document.getElementById(`exp-${qi}`);
  if (expEl) expEl.style.display = 'block';
}

function goPublish() {
  window.location.href = 'publish.php';
}

init();
</script>
</body>
</html>