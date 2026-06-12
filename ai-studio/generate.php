<?php
session_name('ai_studio_session');
session_start();
if (!isset($_SESSION['studio_user_id'])) { header('Location: index.php'); exit; }
$userName = $_SESSION['studio_name'] ?? 'User';
$userRole = $_SESSION['studio_role'] ?? 'generator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; }
.sidebar { background: rgba(0,0,0,0.25); border-right: 1px solid rgba(255,255,255,0.08); width: 240px; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 50; display: flex; flex-direction: column; padding: 24px 16px; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: rgba(255,255,255,0.5); font-size: 14px; text-decoration: none; transition: all 0.2s; }
.nav-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
.nav-link.active { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.15); }
.main { margin-left: 240px; padding: 40px; min-height: 100vh; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; }
.input-f { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 10px; padding: 12px 16px; width: 100%; font-size: 14px; outline: none; transition: border-color 0.2s; }
.input-f:focus { border-color: rgba(255,255,255,0.5); }
.input-f::placeholder { color: rgba(255,255,255,0.25); }
select.input-f option { background: #1F2A44; }
label { color: rgba(255,255,255,0.6); font-size: 13px; display: block; margin-bottom: 7px; }
.type-card { background: rgba(255,255,255,0.05); border: 2px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 20px; cursor: pointer; transition: all 0.2s; text-align: center; }
.type-card:hover { border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.08); }
.type-card.selected { border-color: #fff; background: rgba(255,255,255,0.12); }
.toggle { position: relative; width: 48px; height: 26px; background: rgba(255,255,255,0.15); border-radius: 999px; cursor: pointer; transition: background 0.3s; }
.toggle.on { background: #22c55e; }
.toggle-dot { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: left 0.3s; }
.toggle.on .toggle-dot { left: 25px; }
.btn-primary { background: #fff; color: #1F2A44; border: none; border-radius: 10px; padding: 14px 24px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; width: 100%; }
.btn-primary:hover { background: #f0f0f0; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.step-label { color: rgba(255,255,255,0.4); font-size: 11px; font-weight: 700; letter-spacing: 1.5px; margin-bottom: 14px; }
#progressBox { display: none; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 24px; margin-bottom: 20px; }
.prog-bar-wrap { background: rgba(255,255,255,0.08); border-radius: 999px; height: 8px; margin: 14px 0 10px; overflow: hidden; }
.prog-bar { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e, #4ade80); transition: width 0.5s ease; }
#logBox { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 12px 14px; margin-top: 14px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; color: rgba(255,255,255,0.6); }
#logBox p { margin-bottom: 3px; line-height: 1.5; }
.log-ok   { color: #4ade80 !important; }
.log-warn { color: #fbbf24 !important; }
.log-err  { color: #f87171 !important; }
.log-info { color: rgba(255,255,255,0.5) !important; }
</style>
</head>
<body>

<div class="sidebar">
  <div style="margin-bottom:32px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
      <span style="font-size:22px">🤖</span>
      <span style="color:#fff;font-weight:800;font-size:17px">AI Studio</span>
    </div>
    <p style="color:rgba(255,255,255,0.3);font-size:11px;padding-left:32px">InternshipADDA</p>
  </div>
  <nav style="flex:1;display:flex;flex-direction:column;gap:4px">
    <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
    <a href="generate.php"  class="nav-link active">✨ Generate</a>
    <?php if($userRole==='admin'): ?>
    <a href="settings.php"  class="nav-link">⚙️ Settings</a>
    <?php endif; ?>
  </nav>
  <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:16px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px"><?= strtoupper(substr($userName,0,1)) ?></div>
      <div>
        <p style="color:#fff;font-size:13px;font-weight:600"><?= htmlspecialchars($userName) ?></p>
        <p style="color:rgba(255,255,255,0.35);font-size:11px"><?= ucfirst($userRole) ?></p>
      </div>
    </div>
    <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;color:rgba(255,100,100,0.8);font-size:13px;text-decoration:none" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">🚪 Logout</a>
  </div>
</div>

<div class="main">
  <div style="max-width:680px;margin:0 auto">
    <div style="margin-bottom:36px">
      <h1 style="font-size:26px;font-weight:800;margin-bottom:6px">✨ Generate New Course</h1>
      <p style="color:rgba(255,255,255,0.4);font-size:14px">AI se complete day-wise course ya internship banao</p>
    </div>

    <div id="errBox" style="display:none;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);border-radius:12px;padding:14px 16px;color:#fca5a5;font-size:14px;margin-bottom:20px"></div>

    <div id="progressBox">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
        <span style="color:#fff;font-weight:700;font-size:15px">🔄 Generating...</span>
        <span id="progPercent" style="color:#4ade80;font-weight:800;font-size:15px">0%</span>
      </div>
      <div id="progMsg" style="color:rgba(255,255,255,0.7);font-size:13px">Starting...</div>
      <div class="prog-bar-wrap"><div class="prog-bar" id="progBar" style="width:0%"></div></div>
      <div id="progCount" style="color:#4ade80;font-size:12px;font-weight:700;margin-top:4px"></div>
      <div id="logBox"></div>
    </div>

    <!-- Step 1 -->
    <div class="card" style="padding:24px;margin-bottom:16px">
      <p class="step-label">STEP 1 — KISKE LIYE GENERATE KARNA HAI?</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="type-card selected" id="typeCourse" onclick="selectType('course')">
          <div style="font-size:30px;margin-bottom:8px">📚</div>
          <div style="font-weight:700;font-size:15px">Course</div>
          <div style="color:rgba(255,255,255,0.4);font-size:12px;margin-top:4px">LMS Courses mein save hoga</div>
        </div>
        <div class="type-card" id="typeInternship" onclick="selectType('internship')">
          <div style="font-size:30px;margin-bottom:8px">💼</div>
          <div style="font-weight:700;font-size:15px">Internship</div>
          <div style="color:rgba(255,255,255,0.4);font-size:12px;margin-top:4px">LMS Internships mein save hoga</div>
        </div>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="card" style="padding:24px;margin-bottom:16px">
      <p class="step-label">STEP 2 — COURSE DETAILS</p>
      <div style="display:flex;flex-direction:column;gap:16px">
        <div>
          <label>Topic / Subject *</label>
          <input type="text" id="topic" class="input-f" placeholder="e.g. Python, Java, Digital Marketing, React JS...">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div>
            <label>Duration *</label>
            <select id="days" class="input-f">
              <option value="7">7 Days (1 Week)</option>
              <option value="14">14 Days (2 Weeks)</option>
              <option value="21">21 Days (3 Weeks)</option>
              <option value="30" selected>30 Days (1 Month)</option>
              <option value="45">45 Days (1.5 Months)</option>
              <option value="60">60 Days (2 Months)</option>
              <option value="90">90 Days (3 Months)</option>
              <option value="120">120 Days (4 Months)</option>
              <option value="150">150 Days (5 Months)</option>
              <option value="180">180 Days (6 Months)</option>
            </select>
          </div>
          <div>
            <label>Level *</label>
            <select id="level" class="input-f">
              <option value="Beginner">Beginner</option>
              <option value="Intermediate">Intermediate</option>
              <option value="Advanced">Advanced</option>
            </select>
          </div>
          <div>
            <label>Language</label>
            <select id="language" class="input-f">
              <option value="English">English</option>
              <option value="Hindi">Hindi</option>
              <option value="Hinglish">Hinglish</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="card" style="padding:24px;margin-bottom:24px">
      <p class="step-label">STEP 3 — OPTIONS</p>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px">
        <div>
          <p style="font-weight:600;font-size:14px;margin-bottom:3px">📝 Quiz Include Karo</p>
          <p style="color:rgba(255,255,255,0.4);font-size:12px">Har 7 days ke baad auto quiz generate hoga</p>
        </div>
        <div class="toggle on" id="quizToggle" onclick="toggleQuiz()">
          <div class="toggle-dot"></div>
        </div>
      </div>
    </div>

    <button class="btn-primary" id="genBtn" onclick="startGenerate()">🚀 Generate Course with AI</button>
  </div>
</div>

<script>
let selectedType = 'course';
let includeQuiz  = true;
let AI_SETTINGS  = null;

// ── Gemini fallback chain ──────────────────────────
const GEMINI_FALLBACKS = [
    'gemini-2.5-flash','gemini-2.0-flash','gemini-1.5-flash',
    'gemini-1.5-flash-8b','gemini-1.5-pro','gemini-2.0-flash-lite'
];

// ── Groq fallback chain (removed deprecated models) ──
const GROQ_FALLBACKS = [
    'llama-3.3-70b-versatile',
    'llama-3.1-8b-instant',
    'qwen/qwen3-32b',
    'meta-llama/llama-4-scout',
    'gemma2-9b-it'
];

const OPENAI_FALLBACKS = ['gpt-4o-mini','gpt-4o','gpt-3.5-turbo'];
const GROK_FALLBACKS   = ['grok-3-fast','grok-3','grok-2-1212'];

// ── Load settings on page load ─────────────────────
async function loadSettings() {
    try {
        var r       = await fetch('../api/ai/get-settings.php');
        var rawText = await r.text();
        var data    = JSON.parse(rawText);
        if (data.success && data.settings) {
            AI_SETTINGS = data.settings;
            console.log('[INIT] Settings loaded. Provider:', AI_SETTINGS.active_ai_provider);
        } else {
            console.warn('[INIT] Settings load failed:', data.message);
        }
    } catch(e) {
        console.warn('[INIT] get-settings.php error:', e.message);
    }
}
loadSettings();

function selectType(t) {
    selectedType = t;
    document.getElementById('typeCourse').className     = 'type-card' + (t==='course'     ? ' selected' : '');
    document.getElementById('typeInternship').className = 'type-card' + (t==='internship' ? ' selected' : '');
}
function toggleQuiz() {
    includeQuiz = !includeQuiz;
    document.getElementById('quizToggle').className = 'toggle' + (includeQuiz ? ' on' : '');
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
// Parse "12", "7.66s", "1m30s" -> ms
function parseDur(s) {
    s = ('' + s).trim();
    if (/^\d+(\.\d+)?$/.test(s)) return parseFloat(s) * 1000;
    var m, total = 0, matched = false, re = /(\d+(?:\.\d+)?)\s*(ms|s|m|h)/g;
    while ((m = re.exec(s))) {
        matched = true; var n = parseFloat(m[1]);
        if (m[2]==='ms') total += n; else if (m[2]==='s') total += n*1000;
        else if (m[2]==='m') total += n*60000; else if (m[2]==='h') total += n*3600000;
    }
    return matched ? total : 0;
}
// Read API-requested cooldown (Retry-After / x-ratelimit-reset-*) -> ms, cap 3 min
function readRetryAfter(res) {
    try {
        var ra = res.headers.get('retry-after');
        if (ra) { var n = parseFloat(ra); if (!isNaN(n) && n>0) return Math.min(n*1000, 180000); }
        var hdrs = ['x-ratelimit-reset-tokens','x-ratelimit-reset-requests','x-ratelimit-reset'];
        for (var i=0;i<hdrs.length;i++) { var v = res.headers.get(hdrs[i]); if (v) { var ms = parseDur(v); if (ms) return Math.min(ms,180000); } }
    } catch(e) {}
    return 0;
}
function log(msg, cls) {
    cls = cls || 'log-info';
    var box = document.getElementById('logBox');
    var p   = document.createElement('p');
    p.textContent = msg; p.className = cls;
    box.appendChild(p); box.scrollTop = box.scrollHeight;
}
function updateProgress(msg, done, total) {
    var pct = total > 0 ? Math.round((done/total)*100) : 0;
    document.getElementById('progMsg').textContent     = msg;
    document.getElementById('progBar').style.width     = pct + '%';
    document.getElementById('progPercent').textContent = pct + '%';
    document.getElementById('progCount').textContent   = done + ' / ' + total + ' days complete';
}
function showErr(msg) {
    var el = document.getElementById('errBox');
    el.innerHTML = '❌ ' + msg; el.style.display = 'block';
    document.getElementById('progressBox').style.display = 'none';
}
function hideErr() { document.getElementById('errBox').style.display = 'none'; }
function resetBtn() {
    var btn = document.getElementById('genBtn');
    btn.disabled = false; btn.textContent = '🚀 Generate Course with AI';
}

// ── Build model list (saved first, then fallbacks) ──
function buildModels(provider, savedModel) {
    var fallbacks = {
        gemini: GEMINI_FALLBACKS,
        groq:   GROQ_FALLBACKS,
        openai: OPENAI_FALLBACKS,
        grok:   GROK_FALLBACKS
    }[provider] || GEMINI_FALLBACKS;

    var list = [];
    if (savedModel && savedModel.trim()) list.push(savedModel.trim());
    fallbacks.forEach(function(m) { if (list.indexOf(m) === -1) list.push(m); });
    return list;
}

// ── Build API keys list ────────────────────────────
function buildKeys(provider, s) {
    var keyMap = {
        gemini: [s.gemini_api_key||'', s.gemini_api_key_2||'', s.gemini_api_key_3||''],
        groq:   [s.groq_api_key||'',   s.groq_api_key_2||'',   s.groq_api_key_3||''],
        openai: [s.openai_api_key||'',  s.openai_api_key_2||'', s.openai_api_key_3||''],
        grok:   [s.grok_api_key||'',   s.grok_api_key_2||'',   s.grok_api_key_3||'']
    };
    return (keyMap[provider] || []).filter(function(k) { return k.trim(); });
}

// ── Gemini API call ────────────────────────────────
async function callGemini(apiKey, model, prompt, maxTokens) {
    maxTokens = maxTokens || 3000;
    var controller = new AbortController();
    var timer = setTimeout(function() { controller.abort(); }, 50000);
    try {
        var res = await fetch(
            'https://generativelanguage.googleapis.com/v1beta/models/'+model+':generateContent?key='+apiKey,
            { method:'POST', headers:{'Content-Type':'application/json'},
              body: JSON.stringify({contents:[{parts:[{text:prompt}]}],generationConfig:{temperature:0.7,maxOutputTokens:maxTokens}}),
              signal: controller.signal }
        );
        clearTimeout(timer);
        if (res.status===429) return {ok:false,code:429,msg:'RATE_LIMIT',retryAfter:readRetryAfter(res)};
        if (res.status===503) return {ok:false,code:503,msg:'MODEL_OVERLOADED',retryAfter:readRetryAfter(res)};
        if (!res.ok) return {ok:false,code:res.status,msg:'HTTP_'+res.status};
        var json = await res.json();
        var text = (json&&json.candidates&&json.candidates[0]&&json.candidates[0].content&&json.candidates[0].content.parts&&json.candidates[0].content.parts[0]&&json.candidates[0].content.parts[0].text) || '';
        if (!text.trim()) return {ok:false,code:0,msg:'EMPTY_RESPONSE'};
        return {ok:true,text:text};
    } catch(e) {
        clearTimeout(timer);
        if (e.name==='AbortError') return {ok:false,code:408,msg:'TIMEOUT'};
        return {ok:false,code:0,msg:e.message};
    }
}

// ── Groq / OpenAI / Grok API call ─────────────────
async function callOpenAIFormat(provider, apiKey, model, prompt, maxTokens) {
    maxTokens = maxTokens || 3000;
    var urls = {
        groq:   'https://api.groq.com/openai/v1/chat/completions',
        openai: 'https://api.openai.com/v1/chat/completions',
        grok:   'https://api.x.ai/v1/chat/completions'
    };
    var url = urls[provider];
    if (!url) return {ok:false,code:0,msg:'Unknown provider: '+provider};

    var controller = new AbortController();
    var timer = setTimeout(function() { controller.abort(); }, 50000);
    try {
        var res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type':'application/json','Authorization':'Bearer '+apiKey},
            body: JSON.stringify({model:model,messages:[{role:'user',content:prompt}],max_tokens:maxTokens,temperature:0.7}),
            signal: controller.signal
        });
        clearTimeout(timer);
        if (res.status===429) return {ok:false,code:429,msg:'RATE_LIMIT',retryAfter:readRetryAfter(res)};
        if (res.status===404) return {ok:false,code:404,msg:'MODEL_NOT_FOUND'};
        if (res.status===503) return {ok:false,code:503,msg:'MODEL_OVERLOADED',retryAfter:readRetryAfter(res)};
        if (!res.ok) return {ok:false,code:res.status,msg:'HTTP_'+res.status};
        var json = await res.json();
        var text = (json&&json.choices&&json.choices[0]&&json.choices[0].message&&json.choices[0].message.content) || '';
        if (!text.trim()) return {ok:false,code:0,msg:'EMPTY_RESPONSE'};
        return {ok:true,text:text};
    } catch(e) {
        clearTimeout(timer);
        if (e.name==='AbortError') return {ok:false,code:408,msg:'TIMEOUT'};
        return {ok:false,code:0,msg:e.message};
    }
}

// ── Parse JSON array from AI response ─────────────
function parseJSON(raw) {
    var text = raw.replace(/```json\s*/gi,'').replace(/```\s*/gi,'').trim();
    try { var p = JSON.parse(text); if (Array.isArray(p)) return p; } catch(e) {}
    var m = text.match(/\[[\s\S]+\]/m);
    if (m) try { var p2 = JSON.parse(m[0]); if (Array.isArray(p2)) return p2; } catch(e) {}
    return null;
}

// ── Blueprint prompt: whole-course module map (so the topic completes in time) ──
function buildBlueprintPrompt(topic, days, level, language, type) {
    var kind = (type === 'internship') ? 'internship' : 'course';
    var arc  = (type === 'internship')
        ? 'orientation & setup -> progressively harder hands-on tasks -> a real final capstone project/deliverable'
        : 'fundamentals -> core concepts -> intermediate -> advanced -> real projects -> final review & capstone';
    return 'You are an expert '+kind+' curriculum architect.\n'
        + 'Design a COMPLETE '+level+' "'+topic+'" '+kind+' that is fully covered in EXACTLY '+days+' days.\n'
        + 'Progression: '+arc+'.\n'
        + 'Divide the '+days+' days into sequential modules that TOGETHER cover the ENTIRE subject with NO gaps and NO overlaps, '
        + 'so a learner completely finishes "'+topic+'" by day '+days+' (last days = projects/capstone, not new theory).\n'
        + 'Return ONLY a valid JSON array of modules, no markdown:\n'
        + '[{"module":"Module name","start_day":1,"end_day":5,"goals":"what is mastered here"}]\n'
        + 'Modules MUST continuously span day 1 to day '+days+'.';
}

// ── Build syllabus (day-batch) prompt ──────────────
function buildPrompt(topic, totalDays, startDay, endDay, level, language, type, quiz, blueprint, prevTitles) {
    var n    = endDay - startDay + 1;
    var kind = (type === 'internship') ? 'internship' : 'course';
    var q    = quiz ? 'Set "has_quiz": true ONLY on days that are exact multiples of 7 (day 7, 14, 21, ...); all other days MUST be false.' : 'has_quiz always false.';
    var role = (type === 'internship')
        ? 'internship program designer building a hands-on, project-based '+totalDays+'-day "'+topic+'" internship where EACH day is a concrete practical task with a deliverable'
        : 'course curriculum designer building a complete, progressive '+totalDays+'-day "'+topic+'" course that takes a '+level+' learner from fundamentals to advanced';
    var bp   = (blueprint && blueprint.length)
        ? '\nFULL CURRICULUM BLUEPRINT (these modules span all '+totalDays+' days — follow them):\n'+blueprint+'\n' : '';
    var prev = (prevTitles && prevTitles.length)
        ? '\nAlready created days (DO NOT repeat these — continue forward logically):\n'+prevTitles.join(' | ')+'\n' : '';
    return 'You are an expert '+role+'.\n'
        + 'Level: '+level+' | Language: '+language+'\n'
        + bp + prev
        + '\nNow generate ONLY days '+startDay+' to '+endDay+' (exactly '+n+' days) of this '+kind+'.\n'
        + 'Rules:\n'
        + '- Follow the blueprint module that covers these specific days; progress logically with ZERO repetition of earlier topics\n'
        + '- The WHOLE "'+topic+'" subject must be fully covered and COMPLETED by day '+totalDays+' (final days = wrap-up/project, not new basics)\n'
        + (type === 'internship'
            ? '- Each day = a specific practical task/assignment with a clear deliverable\n'
            : '- Each day = a focused lesson covering specific, non-overlapping concepts\n')
        + '- '+q+'\n'
        + 'Return ONLY a valid JSON array, no markdown:\n'
        + '[{"day":'+startDay+',"title":"...","topics":["t1","t2","t3"],"image_query":"...","has_quiz":false}]';
}

// ── Master call: smart, unstoppable fallback (mirrors building.php engine) ──
async function callWithFallback(provider, apiKeys, models, prompt, batchNum) {
    var deadKeys = [];          // permanently dead (401) keys
    var rateLimitUntil = 0;     // provider cooldown timestamp
    var curKey = 0, curModel = 0, rlHits = 0;
    var realCalls = 0, MAX = 70;
    var maxTokens = 3000;       // safe for free-tier TPM; halved on 413

    function liveKeys(){ return apiKeys.filter(function(k){ return deadKeys.indexOf(k) === -1; }); }

    while (realCalls < MAX) {
        if (liveKeys().length === 0) {
            return {success:false, error:'Saari '+provider.toUpperCase()+' keys invalid (401). Settings mein valid key daalo.'};
        }
        // Respect cooldown — wait, don't give up
        if (Date.now() < rateLimitUntil) {
            var w = rateLimitUntil - Date.now() + 500;
            log('⏳ '+provider.toUpperCase()+' cooldown — '+Math.ceil(w/1000)+'s wait...', 'log-warn');
            await sleep(w);
        }

        var lk = liveKeys();
        if (curKey   >= lk.length)       curKey   = 0;
        if (curModel >= models.length)   curModel = 0;
        var apiKey = lk[curKey];
        var model  = models[curModel];

        realCalls++;
        log('🔁 Try '+realCalls+': Key'+(curKey+1)+'/'+lk.length+' + '+model, 'log-info');

        var res = provider === 'gemini'
            ? await callGemini(apiKey, model, prompt, maxTokens)
            : await callOpenAIFormat(provider, apiKey, model, prompt, maxTokens);

        if (res.ok) {
            var parsed = parseJSON(res.text);
            if (parsed && parsed.length > 0) {
                log('✅ Batch '+batchNum+' done — '+model, 'log-ok');
                return {success:true, data:parsed};
            }
            log('⚠️ Parse fail — next model...', 'log-warn');
            curModel++; await sleep(800); continue;
        }

        // 401 — dead key, skip permanently
        if (res.code === 401) {
            log('❌ Key'+(curKey+1)+' invalid (401) — permanently skip', 'log-err');
            deadKeys.push(apiKey); curKey = 0; await sleep(300); continue;
        }

        // 413 — request too large for free-tier TPM: reduce tokens, then smaller model
        if (res.code === 413) {
            if (maxTokens > 1024) {
                maxTokens = Math.max(1024, Math.floor(maxTokens / 2));
                log('⚠️ 413 too large — max_tokens kam karke '+maxTokens+', retry...', 'log-warn');
                await sleep(400); continue;
            }
            curModel++; log('⚠️ 413 — agla model...', 'log-warn'); await sleep(400); continue;
        }

        // 429 — rate limit: next key -> next (higher-limit) model -> cooldown w/ backoff
        if (res.code === 429) {
            var lkNow = liveKeys();
            if (curKey + 1 < lkNow.length) {
                curKey++;
                log('⏳ Key rate-limited — next key ('+(curKey+1)+'/'+lkNow.length+')', 'log-warn');
                await sleep(400);
            } else if (curModel + 1 < models.length) {
                curKey = 0; curModel++;
                log('🔻 Saari keys busy — high-limit model: '+models[curModel], 'log-warn');
                await sleep(400);
            } else {
                curKey = 0; curModel = 0; rlHits++;
                var base = (res.retryAfter && res.retryAfter > 0) ? res.retryAfter : 30000;
                var cd = Math.min(base * Math.pow(1.6, rlHits - 1), 180000);
                rateLimitUntil = Date.now() + cd;
                log('⏳ Sab keys+models rate-limited — '+Math.ceil(cd/1000)+'s cooldown (x'+rlHits+')', 'log-warn');
                await sleep(300);
            }
            continue;
        }

        // 404 / 400 — unsupported model: skip it
        if (res.code === 404 || res.code === 400) {
            log('⚠️ '+model+' unsupported ('+res.code+') — agla model', 'log-warn');
            curModel++;
            if (curModel >= models.length) { curModel = 0; rateLimitUntil = Date.now() + 10000; }
            await sleep(400); continue;
        }

        // 5xx / timeout — try another model, then short cooldown
        if (res.code === 503 || res.code === 500 || res.code === 502 || res.code === 408) {
            if (curModel + 1 < models.length) {
                curModel++; log('⚠️ '+res.msg+' — agla model: '+models[curModel], 'log-warn'); await sleep(1200);
            } else {
                curModel = 0;
                var cd5 = (res.retryAfter && res.retryAfter > 0) ? res.retryAfter : 10000;
                rateLimitUntil = Date.now() + cd5;
                log('⚠️ '+res.msg+' — '+Math.ceil(cd5/1000)+'s cooldown', 'log-warn'); await sleep(1200);
            }
            continue;
        }

        // Unknown — rotate model + brief cooldown
        log('❌ Error '+res.code+': '+res.msg+' — rotate', 'log-err');
        curModel = (curModel + 1) % models.length;
        rateLimitUntil = Date.now() + 6000;
        await sleep(1200);
    }
    return {success:false, error:'Batch '+batchNum+' — bahut tries ho gaye. Extra/valid API key add karo ya thoda baad try karo.'};
}

// ── MAIN ──────────────────────────────────────────
async function startGenerate() {
    var topic    = document.getElementById('topic').value.trim();
    var days     = parseInt(document.getElementById('days').value);
    var level    = document.getElementById('level').value;
    var language = document.getElementById('language').value;

    if (!topic) { showErr('Topic enter karo!'); return; }

    // Settings reload karo agar nahi hai
    if (!AI_SETTINGS) {
        try {
            var r       = await fetch('../api/ai/get-settings.php');
            var rawText = await r.text();
            var d       = JSON.parse(rawText);
            if (!d.success || !d.settings) { showErr('Settings load nahi hui — page refresh karo.'); return; }
            AI_SETTINGS = d.settings;
        } catch(e) { showErr('Settings fetch error: '+e.message); return; }
    }

    var provider = (AI_SETTINGS.active_ai_provider || 'gemini').toLowerCase().trim();
    console.log('[GEN] Provider:', provider);
    console.log('[GEN] All settings keys:', Object.keys(AI_SETTINGS));

    // ✅ FIXED — sabhi providers support
    var apiKeys = buildKeys(provider, AI_SETTINGS);
    console.log('[GEN] API Keys found:', apiKeys.length, 'for provider:', provider);

    if (!apiKeys.length) {
        showErr('❌ '+provider.toUpperCase()+' API key set nahi hai! <a href="settings.php" style="color:#fff;text-decoration:underline">Settings mein jao →</a>');
        return;
    }

    var savedModel = AI_SETTINGS[provider+'_model'] || '';
    var models     = buildModels(provider, savedModel);
    console.log('[GEN] Models:', models);

    var btn = document.getElementById('genBtn');
    btn.disabled = true; btn.textContent = '⏳ Generating...';
    hideErr();
    document.getElementById('progressBox').style.display = 'block';
    document.getElementById('logBox').innerHTML = '';

    var batchSize  = parseInt(AI_SETTINGS.batch_size || '7');
    var totalBatch = Math.ceil(days / batchSize);
    var allSyllabus = [];

    log('🚀 Provider: '+provider.toUpperCase()+' | Days: '+days+' | Batches: '+totalBatch, 'log-ok');
    log('🔑 Keys: '+apiKeys.length+' | Model: '+models[0], 'log-info');

    try {
        // ── Step 1: full curriculum blueprint (so the whole topic finishes in time) ──
        updateProgress('Blueprint bana raha hoon...', 0, days);
        log('🗺️ Blueprint generate ho raha hai ('+days+'-day '+selectedType+')...', 'log-info');
        var blueprintText = '';
        try {
            var bpRes = await callWithFallback(provider, apiKeys, models, buildBlueprintPrompt(topic, days, level, language, selectedType), 0);
            if (bpRes.success && Array.isArray(bpRes.data)) {
                blueprintText = bpRes.data.map(function(m){ return 'Days '+(m.start_day||'?')+'-'+(m.end_day||'?')+': '+(m.module||'')+(m.goals ? (' - '+m.goals) : ''); }).join('\n');
                log('🗺️ Blueprint ready - '+bpRes.data.length+' modules (poora topic '+days+' din me cover hoga)', 'log-ok');
            } else { log('⚠️ Blueprint skip - per-batch continuity se chalega', 'log-warn'); }
        } catch(e) { log('⚠️ Blueprint error - skip', 'log-warn'); }

        var prevTitles = [];
        for (var b = 0; b < totalBatch; b++) {
            var startDay = b * batchSize + 1;
            var endDay   = Math.min(startDay + batchSize - 1, days);

            updateProgress('Batch '+(b+1)+'/'+totalBatch+' — Day '+startDay+'–'+endDay+'...', allSyllabus.length, days);
            if (b > 0) await sleep(1500);

            var prompt = buildPrompt(topic, days, startDay, endDay, level, language, selectedType, includeQuiz, blueprintText, prevTitles.slice(-30));
            var result = await callWithFallback(provider, apiKeys, models, prompt, b+1);

            if (!result.success) {
                showErr('Batch '+(b+1)+' fail: '+result.error);
                resetBtn(); return;
            }

            allSyllabus = allSyllabus.concat(result.data);
            result.data.forEach(function(d){ if (d && d.title) prevTitles.push('Day '+(d.day||'')+': '+d.title); });
            updateProgress('✅ Batch '+(b+1)+'/'+totalBatch+' done!', allSyllabus.length, days);
        }

        // ── Enforce day numbering + quiz placement (don't trust the AI) ──────────
        // Quiz ONLY on every 7th day (end of each week): day 7, 14, 21, ...
        if (allSyllabus.length > days) allSyllabus = allSyllabus.slice(0, days);
        allSyllabus.forEach(function(d, idx){
            d.day      = idx + 1;                                   // sequential 1..N
            d.has_quiz = includeQuiz && ((idx + 1) % 7 === 0);      // only multiples of 7
        });
        log('📝 Quiz days: ' + (includeQuiz ? allSyllabus.filter(function(d){return d.has_quiz;}).map(function(d){return d.day;}).join(', ') || 'none' : 'disabled'), 'log-info');

        // History save
        updateProgress('💾 Saving...', days, days);
        var historyId = 0;
        try {
            var hRes  = await fetch('../api/ai/save-history.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({topic:topic,total_days:days,level:level,language:language,type:selectedType,provider:provider,model:models[0]})
            });
            var hRaw  = await hRes.text();
            var hData = JSON.parse(hRaw);
            if (hData.success) historyId = hData.history_id;
        } catch(e) { console.warn('History save failed:', e); }

        // sessionStorage mein save karo
        try {
            sessionStorage.setItem('ai_syllabus', JSON.stringify(allSyllabus));
            sessionStorage.setItem('ai_meta', JSON.stringify({
                topic:topic, days:days, level:level, language:language,
                type:selectedType, include_quiz:includeQuiz,
                history_id:historyId, provider:provider,
                model:models[0], batch_size:batchSize
            }));
        } catch(e) {
            showErr('Browser storage full — itne bade course ka syllabus save nahi ho paya. Thoda chhota duration try karo. (' + e.message + ')');
            resetBtn(); return;
        }

        updateProgress('🎉 Done! Redirecting...', days, days);
        log('🎉 Syllabus ready! Building page pe ja raha hoon...', 'log-ok');
        await sleep(800);
        window.location.href = 'building.php';

    } catch(e) {
        showErr('Unexpected error: '+e.message);
        console.error(e); resetBtn();
    }
}
</script>
</body>
</html>