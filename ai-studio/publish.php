<?php
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', 'internshipadda.com');
session_name('ai_studio_session');
session_start();
if (!isset($_SESSION['studio_user_id'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Publish — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 40px; width: 100%; max-width: 600px; }
.input-f { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 10px; padding: 12px 16px; width: 100%; font-size: 14px; outline: none; transition: border-color 0.2s; }
.input-f:focus { border-color: rgba(255,255,255,0.5); }
.input-f::placeholder { color: rgba(255,255,255,0.25); }
textarea.input-f { resize: vertical; min-height: 90px; }
label { color: rgba(255,255,255,0.55); font-size: 13px; display: block; margin-bottom: 7px; }
.btn { border: none; border-radius: 10px; padding: 13px 24px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; width: 100%; }
.btn-primary { background: #fff; color: #1F2A44; }
.btn-primary:hover { background: #f0f0f0; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #fff; }
.btn-secondary:hover { background: rgba(255,255,255,0.14); }
</style>
</head>
<body>
<div class="card">

  <div style="text-align:center;margin-bottom:32px">
    <div style="font-size:44px;margin-bottom:10px">📤</div>
    <h1 style="font-size:22px;font-weight:800;margin-bottom:6px">Save to LMS</h1>
    <p style="color:rgba(255,255,255,0.4);font-size:14px" id="subtitle">Course details fill karo</p>
  </div>

  <!-- Success State -->
  <div id="successBox" style="display:none;text-align:center;padding:20px 0">
    <div style="font-size:54px;margin-bottom:16px">🎉</div>
    <h2 style="font-size:20px;font-weight:800;margin-bottom:8px" id="successMsg">Saved!</h2>
    <p style="color:rgba(255,255,255,0.5);font-size:14px;margin-bottom:24px">Draft LMS mein add ho gaya</p>
    <div style="display:flex;flex-direction:column;gap:10px">
      <a id="editLmsBtn" href="#" target="_blank" class="btn btn-primary" style="text-decoration:none;display:block;text-align:center">
        ✏️ LMS mein Edit Karo →
      </a>
      <button class="btn btn-secondary" onclick="window.location.href='generate.php'">✨ Naya Course Generate Karo</button>
      <button class="btn btn-secondary" onclick="window.location.href='dashboard.php'">📊 Dashboard</button>
    </div>
  </div>

  <!-- Form -->
  <div id="formBox">
    <div style="display:flex;flex-direction:column;gap:16px">

      <div>
        <label>Course/Internship Title *</label>
        <input type="text" id="title" class="input-f" placeholder="e.g. Complete Python Programming Course" />
      </div>

      <div>
        <label>Description</label>
        <textarea id="desc" class="input-f" placeholder="Course ke baare mein likho..."></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label>Price (₹) — 0 for Free</label>
          <input type="number" id="price" class="input-f" placeholder="0" value="0" min="0" />
        </div>
        <div>
          <label>Type</label>
          <select id="type" class="input-f" style="background:rgba(255,255,255,0.07)">
            <option value="course">📚 Course</option>
            <option value="internship">💼 Internship</option>
          </select>
        </div>
      </div>

      <div>
        <label>Thumbnail URL (optional — manually upload baad mein)</label>
        <input type="url" id="thumb" class="input-f" placeholder="https://..." />
      </div>

      <div id="errBox" style="display:none;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:12px;color:#fca5a5;font-size:13px"></div>

      <button class="btn btn-primary" id="saveBtn">
        💾 Save as Draft in LMS
      </button>
      <button class="btn btn-secondary" onclick="window.location.href='preview.php'">
        ← Back to Preview
      </button>

    </div>
  </div>

</div>

<script>
let courseData = null;
let meta = null;

// ─── CODE BLOCK FIX (safety net before saving to LMS) ────────────────────────
// Bakes a light #d7dbdb theme + black/red/blue syntax highlighting straight into
// the saved HTML as INLINE styles, so enrolled students see the same readable
// code as admins — regardless of the LMS student-theme CSS.
var PRE_STYLE_PUB = 'background:#d7dbdb;color:#1a1a1a;padding:16px;border-radius:8px;overflow-x:auto;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-word;margin:14px 0;border:1px solid #b9bfbf';
var CODE_KEYWORDS_PUB = 'def|class|return|if|elif|else|for|while|do|switch|case|import|from|as|in|is|not|and|or|None|True|False|null|nil|true|false|try|except|catch|finally|throw|throws|with|lambda|yield|pass|break|continue|self|this|function|fn|func|var|let|const|new|delete|public|private|protected|static|final|void|int|float|double|string|str|bool|boolean|char|long|short|byte|print|println|echo|require|include|async|await|extends|implements|interface|struct|type|enum|package|namespace|using|val|fun|abstract|override';
function highlightCodePub(s){
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
    s = s.replace(new RegExp('\\b(?:' + CODE_KEYWORDS_PUB + ')\\b', 'g'), function(m){ return stash('<span style="color:#0033b3;font-weight:600">' + m + '</span>'); });
    s = s.replace(/\u0001z(\d+)z\u0001/g, function(_, i){ return tokens[+i]; });
    return s;
  } catch(e){ return s; }
}
function fixCodeBlocks(html){
  try {
    if(html == null) return '';
    html = String(html);
    if(!html) return '';
    // Skip if already processed (avoid double-highlighting)
    if(html.indexOf('background:#d7dbdb') !== -1) return html;
    return html.replace(/<pre[^>]*>([\s\S]*?)<\/pre>/gi, function(m, inner){
      var code = inner.replace(/<\/?code[^>]*>/gi, '');
      code = highlightCodePub(code);
      return '<pre style="' + PRE_STYLE_PUB + '"><code style="background:transparent;color:#1a1a1a;font-family:inherit">' + code + '</code></pre>';
    });
  } catch(e){ return (html == null ? '' : String(html)); }
}

function init() {
  const rawC = sessionStorage.getItem('ai_course_data');
  const rawM = sessionStorage.getItem('ai_meta');
  if (!rawC || !rawM) { window.location.href = 'generate.php'; return; }
  const data = JSON.parse(rawC);
  meta = JSON.parse(rawM);
  courseData = data.course;
  document.getElementById('title').value = meta.topic + ' — Complete ' + meta.days + ' Day Course';
  document.getElementById('subtitle').textContent = meta.topic + ' • ' + data.total_days + ' days • ' + meta.level;
  document.getElementById('type').value = meta.type || 'course';
  document.getElementById('desc').value = `A complete ${meta.days}-day ${meta.level} level ${meta.topic} course generated by AI. Covers all fundamentals to advanced topics in ${meta.language} language.`;
}

async function saveToLMS() {
  const rawC = sessionStorage.getItem('ai_course_data');
  const rawM = sessionStorage.getItem('ai_meta');
  if (!rawC || !rawM) { showErr('Course data nahi mila!'); return; }
  const parsedC = JSON.parse(rawC);
  const parsedM = JSON.parse(rawM);
  courseData = parsedC.course;
  meta = parsedM;

  const title = document.getElementById('title').value.trim();
  const desc  = document.getElementById('desc').value.trim();
  const price = parseFloat(document.getElementById('price').value) || 0;
  const type  = document.getElementById('type').value;
  const thumb = document.getElementById('thumb').value.trim();

  if (!title) { showErr('Title required!'); return; }
  if (!courseData || courseData.length === 0) { showErr('Course data nahi mila!'); return; }

  // Safety net: ensure every day's code blocks carry the inline light theme +
  // colour highlighting so enrolled students see readable, colourful code.
  courseData = courseData.map(function(d){
    d.content = fixCodeBlocks(d.content || '');
    return d;
  });

  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.textContent = '⏳ Saving...';
  hideErr();

  const res = await fetch('/api/ai/lms-publish.php', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      title, description: desc, price, cover_image: thumb,
      type, course_data: courseData,
      history_id: meta.history_id || 0,
      _token: 'AISTUDIO_LMS_2026'
    })
  });

  const txt = await res.text();
  
  if (!res.ok) {
    showErr('Error ' + res.status + ': ' + txt.substring(0, 200));
    btn.disabled = false;
    btn.textContent = '💾 Save as Draft in LMS';
    return;
  }

  let data;
  try { data = JSON.parse(txt); } 
  catch(e) { showErr('Parse error: ' + txt.substring(0,200)); btn.disabled=false; btn.textContent='💾 Save as Draft in LMS'; return; }

  if (data.success) {
    document.getElementById('formBox').style.display = 'none';
    document.getElementById('successBox').style.display = 'block';
    document.getElementById('successMsg').textContent = data.message;
    document.getElementById('editLmsBtn').href = data.edit_url;
    sessionStorage.removeItem('ai_course_data');
    sessionStorage.removeItem('ai_syllabus');
  } else {
    showErr(data.message || 'Failed');
    btn.disabled = false;
    btn.textContent = '💾 Save as Draft in LMS';
  }
}

function showErr(msg) {
  const el = document.getElementById('errBox');
  el.textContent = '❌ ' + msg;
  el.style.display = 'block';
}
function hideErr() {
  document.getElementById('errBox').style.display = 'none';
}

document.getElementById('saveBtn').addEventListener('click', saveToLMS);
init();
</script>
</body>
</html>