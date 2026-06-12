<?php
session_name('ai_studio_session');
session_start();
if (!isset($_SESSION['studio_user_id'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Building — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 40px; width: 100%; max-width: 720px; }
.prog-bg   { background: rgba(255,255,255,0.1); border-radius: 999px; height: 8px; overflow: hidden; }
.prog-fill { height: 100%; border-radius: 999px; background: #fff; transition: width 0.4s ease; }
.stat-box  { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 16px; text-align: center; }
.day-log { height: 320px; overflow-y: auto; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; font-family: 'Courier New', monospace; font-size: 12px; }
.day-log::-webkit-scrollbar { width: 4px; }
.day-log::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
.log-ok   { color: #4ade80; }
.log-wait { color: rgba(255,255,255,0.25); }
.log-spin { color: #93c5fd; }
.log-err  { color: #f87171; }
.log-warn { color: #fbbf24; }
.btn-retry { background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.4); color: #fca5a5; border-radius: 8px; padding: 9px 18px; font-size: 13px; cursor: pointer; }
</style>
</head>
<body>
<div class="card">
  <div style="text-align:center;margin-bottom:32px">
    <div id="topIcon" style="font-size:48px;margin-bottom:12px">⚡</div>
    <h1 id="mainTitle" style="font-size:22px;font-weight:800;margin-bottom:6px">Course Generate Ho Raha Hai...</h1>
    <p id="mainSub" style="color:rgba(255,255,255,0.4);font-size:14px">Page band mat karo — content generate ho raha hai</p>
  </div>
  <div style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
      <span style="color:rgba(255,255,255,0.5);font-size:13px" id="progLabel">Initializing...</span>
      <span style="color:#fff;font-size:13px;font-weight:700" id="progPct">0%</span>
    </div>
    <div class="prog-bg"><div class="prog-fill" id="progBar" style="width:0%"></div></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:24px">
    <div class="stat-box"><div id="sDone" style="color:#4ade80;font-size:22px;font-weight:800">0</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Done</div></div>
    <div class="stat-box"><div id="sTotal" style="font-size:22px;font-weight:800">—</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Total Days</div></div>
    <div class="stat-box"><div id="sBatch" style="color:#93c5fd;font-size:22px;font-weight:800">—</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Batch</div></div>
    <div class="stat-box"><div id="sETA" style="color:#fbbf24;font-size:22px;font-weight:800">—</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">ETA</div></div>
  </div>
  <div class="day-log" id="dayLog"><div class="log-wait">System initializing...</div></div>
  <div id="errBox" style="display:none;margin-top:16px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:14px">
    <p style="color:#fca5a5;font-size:13px;margin-bottom:10px" id="errMsg"></p>
    <button class="btn-retry" onclick="retryFailed()">🔄 Retry Failed Days</button>
  </div>
</div>

<script>
// ─── STATE ───────────────────────────────────────────────────────────────────
var syllabus = [], meta = {}, allContent = {}, failedDays = [];
var AI_SETTINGS = null;

// Surface any silent JS error into the on-screen log so the engine's progress is
// never a mystery (and we know it didn't just die quietly).
window.addEventListener('error', function(e){
  try { var el = document.getElementById('dayLog'); if(el){ var d=document.createElement('div'); d.className='log-err'; d.textContent='🟥 JS error: ' + (e.message || (e.error && e.error.message) || 'unknown'); el.appendChild(d); el.scrollTop=el.scrollHeight; } } catch(_){}
});
window.addEventListener('unhandledrejection', function(e){
  try { var el = document.getElementById('dayLog'); if(el){ var d=document.createElement('div'); d.className='log-err'; d.textContent='🟥 Promise error: ' + (e.reason && e.reason.message ? e.reason.message : e.reason); el.appendChild(d); el.scrollTop=el.scrollHeight; } } catch(_){}
});

// ─── PROVIDER CONFIG ──────────────────────────────────────────────────────────
// Default model lists per provider (fallback if no saved model)
var PROVIDER_MODELS = {
  gemini: ['gemini-2.5-flash','gemini-2.0-flash','gemini-1.5-flash','gemini-2.5-pro','gemini-1.5-pro'],
  groq:   ['llama-3.3-70b-versatile','llama-3.1-8b-instant','qwen/qwen3-32b','openai/gpt-oss-20b','mixtral-8x7b-32768','gemma2-9b-it'],
  openai: ['gpt-4o-mini','gpt-4o','gpt-3.5-turbo'],
  grok:   ['grok-3-fast','grok-3','grok-2']
};

// ─── UTILS ────────────────────────────────────────────────────────────────────
function sleep(ms){ return new Promise(function(r){ setTimeout(r, ms); }); }

// Parse a duration string like "12", "7.66s", "1m30s", "2h" -> milliseconds
function parseDur(s){
  s = ('' + s).trim();
  if(/^\d+(\.\d+)?$/.test(s)) return parseFloat(s) * 1000;
  var m, total = 0, matched = false;
  var re = /(\d+(?:\.\d+)?)\s*(ms|s|m|h)/g;
  while((m = re.exec(s))){
    matched = true;
    var n = parseFloat(m[1]);
    if(m[2] === 'ms')      total += n;
    else if(m[2] === 's')  total += n * 1000;
    else if(m[2] === 'm')  total += n * 60000;
    else if(m[2] === 'h')  total += n * 3600000;
  }
  return matched ? total : 0;
}

// Read the real cooldown the API asks for (Retry-After / x-ratelimit-reset-*),
// so we wait the CORRECT amount instead of guessing. Returns ms (capped 5 min).
function readRetryAfter(res){
  try {
    var ra = res.headers.get('retry-after');
    if(ra){ var n = parseFloat(ra); if(!isNaN(n) && n > 0) return Math.min(n * 1000, 300000); }
    var hdrs = ['x-ratelimit-reset-tokens','x-ratelimit-reset-requests','x-ratelimit-reset'];
    for(var i = 0; i < hdrs.length; i++){
      var v = res.headers.get(hdrs[i]);
      if(v){ var ms = parseDur(v); if(ms) return Math.min(ms, 300000); }
    }
  } catch(e){}
  return 0;
}

function log(msg, type){
  type = type || 'wait';
  var el  = document.getElementById('dayLog');
  var div = document.createElement('div');
  div.className   = 'log-' + type;
  div.textContent = msg || '\u00a0';
  el.appendChild(div);
  el.scrollTop = el.scrollHeight;
}

function setProgress(pct, label){
  document.getElementById('progBar').style.width   = pct + '%';
  document.getElementById('progPct').textContent   = pct + '%';
  document.getElementById('progLabel').textContent = label;
}

// ─── CODE BLOCK FIX + SYNTAX HIGHLIGHT ───────────────────────────────────────
// Forces a known-good light theme on every <pre> block and lightly highlights
// the code so text is BLACK (base) + RED (strings/numbers) + BLUE (keywords)
// on a #d7dbdb background. Inline styles travel with the saved HTML so it looks
// the same in the preview AND the LMS editor.
var PRE_STYLE  = 'background:#d7dbdb;color:#1a1a1a;padding:16px;border-radius:8px;overflow-x:auto;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-word;margin:14px 0;border:1px solid #b9bfbf';
var CODE_STYLE = 'background:transparent;color:#1a1a1a;font-family:inherit';

// Token colours
var CLR_KEYWORD = '#0033b3'; // blue
var CLR_LITERAL = '#c41a16'; // red (strings + numbers)
var CLR_COMMENT = '#5c6370'; // muted grey

var CODE_KEYWORDS = 'def|class|return|if|elif|else|for|while|do|switch|case|import|from|as|in|is|not|and|or|None|True|False|null|nil|true|false|try|except|catch|finally|throw|throws|with|lambda|yield|pass|break|continue|self|this|function|fn|func|var|let|const|new|delete|public|private|protected|static|final|void|int|float|double|string|str|bool|boolean|char|long|short|byte|print|println|echo|require|include|async|await|extends|implements|interface|struct|type|enum|package|namespace|using|val|fun|abstract|override';

function highlightCode(s){
  if(s == null) return '';
  s = String(s);
  if(s.length > 20000) return s; // very large -> skip token pass (avoid slow regex)
  try {
    var tokens = [];
    // Placeholder is letter-wrapped (z<idx>z) so later number/keyword passes
    // can NOT match the index digits and corrupt the placeholder.
    function stash(html){ tokens.push(html); return '\u0001z' + (tokens.length - 1) + 'z\u0001'; }
    // 1. Comments (# , // , -- to end of line) — muted
    s = s.replace(/((?:#|\/\/|--)[^\n\r]*)/g, function(m){ return stash('<span style="color:' + CLR_COMMENT + '">' + m + '</span>'); });
    // 2. Strings (entity-quoted or literal) — red
    s = s.replace(/&quot;[\s\S]*?&quot;|&#0*39;[\s\S]*?&#0*39;|"[^"\n]*"|'[^'\n]*'|`[^`\n]*`/g, function(m){ return stash('<span style="color:' + CLR_LITERAL + '">' + m + '</span>'); });
    // 3. Protect remaining HTML entities so their digits aren't coloured as numbers
    s = s.replace(/&[a-zA-Z#0-9]+;/g, function(m){ return stash(m); });
    // 4. Numbers — red
    s = s.replace(/\b\d+(?:\.\d+)?\b/g, function(m){ return stash('<span style="color:' + CLR_LITERAL + '">' + m + '</span>'); });
    // 5. Keywords — blue
    s = s.replace(new RegExp('\\b(?:' + CODE_KEYWORDS + ')\\b', 'g'), function(m){ return stash('<span style="color:' + CLR_KEYWORD + ';font-weight:600">' + m + '</span>'); });
    // 6. Restore stashed tokens
    s = s.replace(/\u0001z(\d+)z\u0001/g, function(_, i){ return tokens[+i]; });
    return s;
  } catch(e){ return s; }
}

function fixCodeBlocks(html){
  try {
    if(html == null) return '';
    html = String(html);
    if(!html) return '';
    // Rewrite every <pre>...</pre> block: light theme + syntax highlight.
    return html.replace(/<pre[^>]*>([\s\S]*?)<\/pre>/gi, function(m, inner){
      var code = inner.replace(/<\/?code[^>]*>/gi, ''); // drop existing code wrappers
      code = highlightCode(code);
      return '<pre style="' + PRE_STYLE + '"><code style="' + CODE_STYLE + '">' + code + '</code></pre>';
    });
    // Inline <code> (inside sentences) left untouched.
  } catch(e){ return (html == null ? '' : String(html)); }
}

// ─── BUILD PROVIDER CHAIN ─────────────────────────────────────────────────────
// Returns array of {provider, keys[], models[]} in priority order
// Primary provider first, then others as fallback
function buildProviderChain(settings){
  var primary = settings.active_ai_provider || 'gemini';

  // All 4 providers in order: primary first
  var order = [primary];
  ['gemini','groq','openai','grok'].forEach(function(p){
    if(p !== primary) order.push(p);
  });

  var chain = [];
  order.forEach(function(p){
    var keys = [
      (settings[p+'_api_key']   || '').trim(),
      (settings[p+'_api_key_2'] || '').trim(),
      (settings[p+'_api_key_3'] || '').trim()
    ].filter(function(k){ return k.length > 0; });

    if(keys.length === 0) return; // skip if no keys configured

    var savedModel = (settings[p+'_model'] || '').trim();
    var models = [];
    if(savedModel) models.push(savedModel);
    // Add default models as fallback (skip duplicates)
    (PROVIDER_MODELS[p] || []).forEach(function(m){
      if(models.indexOf(m) === -1) models.push(m);
    });

    chain.push({ provider: p, keys: keys, models: models, deadKeys: [] });
  });

  return chain;
}

// ─── API CALLERS ──────────────────────────────────────────────────────────────
function callGemini(apiKey, model, prompt){
  var ctrl  = new AbortController();
  var timer = setTimeout(function(){ ctrl.abort(); }, 50000);
  return fetch(
    'https://generativelanguage.googleapis.com/v1beta/models/' + model + ':generateContent?key=' + apiKey,
    { method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ contents:[{parts:[{text:prompt}]}], generationConfig:{temperature:0.5, maxOutputTokens:8192} }),
      signal: ctrl.signal }
  ).then(function(res){
    clearTimeout(timer);
    if(res.status === 429) return {ok:false, code:429, msg:'RATE_LIMIT', retryAfter:readRetryAfter(res)};
    if(res.status === 503) return {ok:false, code:503, msg:'OVERLOADED', retryAfter:readRetryAfter(res)};
    if(!res.ok)            return {ok:false, code:res.status, msg:'HTTP_'+res.status};
    return res.json().then(function(j){
      var text = '';
      try { text = j.candidates[0].content.parts[0].text; } catch(e){}
      return text.trim() ? {ok:true, text:text} : {ok:false, code:0, msg:'EMPTY'};
    });
  }).catch(function(e){
    clearTimeout(timer);
    return {ok:false, code:408, msg: e.name==='AbortError'?'TIMEOUT':e.message};
  });
}

function callOpenAIStyle(endpoint, apiKey, model, prompt){
  var ctrl  = new AbortController();
  var timer = setTimeout(function(){ ctrl.abort(); }, 50000);
  return fetch(endpoint, {
    method:'POST',
    headers:{'Content-Type':'application/json', 'Authorization':'Bearer '+apiKey},
    body: JSON.stringify({ model:model, messages:[{role:'user',content:prompt}], max_tokens:4096, temperature:0.5 }),
    signal: ctrl.signal
  }).then(function(res){
    clearTimeout(timer);
    if(res.status === 429) return {ok:false, code:429, msg:'RATE_LIMIT', retryAfter:readRetryAfter(res)};
    if(res.status === 401) return {ok:false, code:401, msg:'INVALID_KEY'};
    if(res.status === 404) return {ok:false, code:404, msg:'MODEL_NOT_FOUND'};
    if(res.status === 503) return {ok:false, code:503, msg:'OVERLOADED', retryAfter:readRetryAfter(res)};
    if(!res.ok)            return {ok:false, code:res.status, msg:'HTTP_'+res.status};
    return res.json().then(function(j){
      var text = '';
      try { text = j.choices[0].message.content; } catch(e){}
      return text.trim() ? {ok:true, text:text} : {ok:false, code:0, msg:'EMPTY'};
    });
  }).catch(function(e){
    clearTimeout(timer);
    return {ok:false, code:408, msg: e.name==='AbortError'?'TIMEOUT':e.message};
  });
}

function callAPI(provider, apiKey, model, prompt){
  if(provider === 'gemini') return callGemini(apiKey, model, prompt);
  if(provider === 'groq')   return callOpenAIStyle('https://api.groq.com/openai/v1/chat/completions', apiKey, model, prompt);
  if(provider === 'openai') return callOpenAIStyle('https://api.openai.com/v1/chat/completions', apiKey, model, prompt);
  if(provider === 'grok')   return callOpenAIStyle('https://api.x.ai/v1/chat/completions', apiKey, model, prompt);
  return Promise.resolve({ok:false, code:0, msg:'UNKNOWN_PROVIDER'});
}

// ─── PROMPT BUILDER ───────────────────────────────────────────────────────────
function buildPrompt(topic, level, language, dayObj, includeQuiz, type){
  var isIntern = (type === 'internship');
  var quizInstr = (includeQuiz && dayObj.has_quiz)
    ? 'Include "quiz" array with exactly 10 MCQ objects: {"question":"...","options":["A","B","C","D"],"correct_index":0,"explanation":"..."}'
    : '"quiz":[]';
  return (isIntern
      ? 'You are an expert ' + topic + ' internship mentor.\nGenerate ONE day of a hands-on internship as a practical task (Task Objective, Concepts You Need, Step-by-Step Instructions, Deliverable, Tips).\n'
      : 'You are an expert ' + topic + ' instructor.\nGenerate complete lesson content for ONE day of a course (Introduction, concept sections, worked examples, Summary).\n')
    + 'Level: ' + level + ' | Language: ' + language + '\n\n'
    + 'Day: ' + dayObj.day + '\n'
    + 'Title: ' + dayObj.title + '\n'
    + 'Topics: ' + (dayObj.topics || []).join(', ') + '\n\n'
    + 'Instructions:\n'
    + '- Write detailed HTML using <h3>,<p>,<ul>,<li>,<strong>\n'
    + '- Minimum 500 words of content\n'
    + '- Cover ALL topics listed above\n'
    + '- For EVERY code example (any programming language) wrap it in <pre><code>...</code></pre>\n'
    + '- Inside code blocks, HTML-escape special characters: write &lt; for <, &gt; for >, &amp; for & so the markup never breaks\n'
    + '- Do NOT add your own colour/style to code — styling and syntax highlighting are applied automatically\n'
    + '- For short inline code inside a sentence use plain <code>...</code>\n'
    + '- ' + quizInstr + '\n\n'
    + 'Return ONLY valid JSON, no markdown, no backticks:\n'
    + '{"day":' + dayObj.day + ',"content":"[HTML HERE]","image_query":"short search query","quiz":[]}';
}

// ─── JSON PARSER ─────────────────────────────────────────────────────────────
function parseOneDayJSON(raw){
  var text = raw.replace(/```json\s*/gi,'').replace(/```\s*/gi,'').trim();
  // Try direct parse
  try { var p = JSON.parse(text); if(p && p.day) return p; } catch(e){}
  // Try extract from first { to last }
  var s = text.indexOf('{'), en = text.lastIndexOf('}');
  if(s !== -1 && en > s){
    try { var p2 = JSON.parse(text.substring(s, en+1)); if(p2 && p2.day) return p2; } catch(e){}
  }
  // Regex fallback
  try {
    var dm  = text.match(/"day"\s*:\s*(\d+)/);
    var iqm = text.match(/"image_query"\s*:\s*"([^"]+)"/);
    var day = dm ? parseInt(dm[1]) : 0;
    var iq  = iqm ? iqm[1] : '';
    var cm  = text.match(/"content"\s*:\s*"([\s\S]+?)(?=",\s*"(?:image_query|quiz|day))/);
    var content = cm ? cm[1].replace(/\\n/g,'\n').replace(/\\"/g,'"') : '';
    if(day && content) return {day:day, content:content, image_query:iq, quiz:[]};
  } catch(e){}
  return null;
}

// ─── QUIZ HELPERS (dedicated, reliable quiz generation) ───────────────────────
function isValidQuizItem(q){
  return q && typeof q.question === 'string' && q.question.trim().length > 0
    && Array.isArray(q.options) && q.options.length >= 2;
}

function buildQuizPrompt(topic, level, language, dayObj){
  return 'You are an expert ' + topic + ' instructor.\n'
    + 'Create EXACTLY 10 multiple-choice quiz questions that test this lesson.\n'
    + 'Level: ' + level + ' | Language: ' + language + '\n'
    + 'Lesson: Day ' + dayObj.day + ' — ' + dayObj.title + '\n'
    + 'Topics: ' + (dayObj.topics || []).join(', ') + '\n\n'
    + 'Rules:\n- Each question MUST have exactly 4 options\n- "correct_index" is 0-3\n- Add a short "explanation"\n'
    + 'Return ONLY a valid JSON array (no markdown, no backticks, no extra text):\n'
    + '[{"question":"...","options":["A","B","C","D"],"correct_index":0,"explanation":"..."}]';
}

function parseQuizArray(raw){
  if(!raw) return [];
  var text = String(raw).replace(/```json\s*/gi,'').replace(/```\s*/gi,'').trim();
  var arr = null;
  try { var p = JSON.parse(text); if(Array.isArray(p)) arr = p; } catch(e){}
  if(!arr){
    var s = text.indexOf('['), e2 = text.lastIndexOf(']');
    if(s !== -1 && e2 > s){ try { var p2 = JSON.parse(text.substring(s, e2+1)); if(Array.isArray(p2)) arr = p2; } catch(e){} }
  }
  if(!Array.isArray(arr)) return [];
  return arr.filter(isValidQuizItem).map(function(q){
    return {
      question:      q.question,
      options:       q.options.slice(0, 4),
      correct_index: (typeof q.correct_index === 'number') ? q.correct_index : (parseInt(q.correct_index, 10) || 0),
      explanation:   q.explanation || ''
    };
  });
}

// Dedicated quiz generation with full provider/key/model failover. Used when the
// main content response did not include a valid quiz — so quiz days NEVER end up
// without a quiz just because one model struggled with the combined response.
async function fetchQuiz(chain, dayObj, topic, level, language){
  var prompt = buildQuizPrompt(topic, level, language, dayObj);
  var rateLimitUntil = {};
  var calls = 0, MAX = 30;
  function liveKeysOf(c){ return c.keys.filter(function(k){ return c.deadKeys.indexOf(k) === -1; }); }

  while(calls < MAX){
    if(!chain.some(function(c){ return liveKeysOf(c).length > 0; })) return [];

    var selected = null;
    for(var ci = 0; ci < chain.length; ci++){
      var c = chain[ci];
      if(liveKeysOf(c).length === 0) continue;
      if(Date.now() < (rateLimitUntil[c.provider] || 0)) continue;
      selected = c; break;
    }
    if(!selected){
      var earliest = Infinity;
      chain.forEach(function(c){ if(liveKeysOf(c).length > 0 && rateLimitUntil[c.provider]) earliest = Math.min(earliest, rateLimitUntil[c.provider]); });
      await sleep(earliest === Infinity ? 4000 : Math.max(1000, earliest - Date.now() + 500));
      continue;
    }

    if(selected.curKey   === undefined) selected.curKey   = 0;
    if(selected.curModel === undefined) selected.curModel = 0;
    var lk = liveKeysOf(selected);
    if(selected.curKey   >= lk.length)              selected.curKey   = 0;
    if(selected.curModel >= selected.models.length) selected.curModel = 0;
    var apiKey = lk[selected.curKey], model = selected.models[selected.curModel];

    calls++;
    var res = await callAPI(selected.provider, apiKey, model, prompt);

    if(res.ok){
      var quiz = parseQuizArray(res.text);
      if(quiz.length >= 5) return quiz.slice(0, 10);
      await sleep(700); continue; // got a response but quiz weak -> try another model/key
    }
    if(res.code === 401){ selected.deadKeys.push(apiKey); selected.curKey = 0; continue; }
    if(res.code === 429){
      var lkN = liveKeysOf(selected);
      if(selected.curKey + 1 < lkN.length){ selected.curKey++; await sleep(400); }
      else if(selected.curModel + 1 < selected.models.length){ selected.curKey = 0; selected.curModel++; await sleep(400); }
      else { selected.curKey = 0; selected.curModel = 0; var b = (res.retryAfter && res.retryAfter > 0) ? res.retryAfter : 60000; rateLimitUntil[selected.provider] = Date.now() + Math.min(b, 180000); await sleep(300); }
      continue;
    }
    if(res.code === 404 || res.code === 400){
      selected.curModel = (selected.curModel + 1) % selected.models.length;
      if(selected.curModel === 0) rateLimitUntil[selected.provider] = Date.now() + 10000;
      await sleep(400); continue;
    }
    selected.curModel = (selected.curModel + 1) % selected.models.length;
    rateLimitUntil[selected.provider] = Date.now() + 6000;
    await sleep(900);
  }
  return [];
}

// ─── CORE: GENERATE ONE DAY — UNSTOPPABLE SMART FAILOVER ──────────────────────
// Strategy:
//  • Stick with the currently-working provider/key until it actually fails.
//  • On 429 (rate limit): use the NEXT key of the SAME provider; only when all
//    its keys are tired, cooldown the provider and move to the NEXT provider.
//  • On 401 (bad key): kill that key permanently and move on.
//  • On 404 (bad model): try the next model, then next provider.
//  • On 5xx / timeout / empty: brief cooldown + rotate.
//  • Cooldown WAITS never count against the budget — the engine WAITS instead of
//    giving up, so it never stops while any usable key exists.
//  • Only fails a day if EVERY key is permanently dead (401) or a very high real
//    call budget is hit — and even then the day is auto-retried later.
async function generateOneDay(chain, dayObj, topic, level, language, includeQuiz, type){
  var prompt = buildPrompt(topic, level, language, dayObj, includeQuiz, type);
  var rateLimitUntil = {};   // provider -> timestamp usable again (per-day)
  var quizRetryCount = 0;
  var realCalls = 0;         // counts ONLY actual API calls (not waits)
  var MAX_REAL_CALLS = 80;   // generous safety cap per day

  function liveKeysOf(c){ return c.keys.filter(function(k){ return c.deadKeys.indexOf(k) === -1; }); }
  function anyLiveKeyExists(){ return chain.some(function(c){ return liveKeysOf(c).length > 0; }); }

  while(true){
    // Genuinely nothing usable left
    if(!anyLiveKeyExists()){
      log('  ❌ Day ' + dayObj.day + ' — saari API keys invalid (401). Settings mein valid key daalo.', 'err');
      return {success:false, fatal:true};
    }
    if(realCalls >= MAX_REAL_CALLS){
      log('  ⚠️ Day ' + dayObj.day + ' — bahut tries ho gaye, baad mein auto-retry hoga', 'warn');
      return {success:false};
    }

    // Pick first provider (chain order = primary first) with live keys & no cooldown
    var selected = null;
    for(var ci = 0; ci < chain.length; ci++){
      var c = chain[ci];
      if(liveKeysOf(c).length === 0) continue;
      if(Date.now() < (rateLimitUntil[c.provider] || 0)) continue;
      selected = c; break;
    }

    // Everything cooling down -> WAIT for the soonest (NEVER give up)
    if(!selected){
      var earliest = Infinity;
      chain.forEach(function(c){
        if(liveKeysOf(c).length > 0 && rateLimitUntil[c.provider]) earliest = Math.min(earliest, rateLimitUntil[c.provider]);
      });
      var waitMs = (earliest === Infinity) ? 5000 : Math.max(1000, earliest - Date.now() + 1000);
      log('  ⏳ Sab providers cooldown pe — ' + Math.ceil(waitMs/1000) + 's wait (engine rukega nahi)...', 'warn');
      await sleep(waitMs);
      continue;
    }

    // Sticky cursors persist on the chain object across days
    if(selected.curKey   === undefined) selected.curKey   = 0;
    if(selected.curModel === undefined) selected.curModel = 0;
    var lk = liveKeysOf(selected);
    if(selected.curKey   >= lk.length)              selected.curKey   = 0;
    if(selected.curModel >= selected.models.length) selected.curModel = 0;
    var apiKey = lk[selected.curKey];
    var model  = selected.models[selected.curModel];

    realCalls++;
    log('  Day ' + dayObj.day + ' | [' + selected.provider.toUpperCase() + '] ' + model + ' (Key ' + (selected.curKey+1) + '/' + lk.length + ') #' + realCalls, 'spin');

    var res = await callAPI(selected.provider, apiKey, model, prompt);

    if(res.ok){
      var parsed = parseOneDayJSON(res.text);
      if(!parsed || !parsed.day){
        log('  Day ' + dayObj.day + ' — JSON parse fail, retry...', 'warn');
        await sleep(800);
        continue;
      }
      if(includeQuiz && dayObj.has_quiz){
        var quiz = Array.isArray(parsed.quiz) ? parsed.quiz.filter(isValidQuizItem) : [];
        if(quiz.length < 5){
          log('  📝 Day ' + dayObj.day + ' — quiz alag se generate kar raha hoon...', 'spin');
          var fq = await fetchQuiz(chain, dayObj, topic, level, language);
          if(fq.length >= 5){
            parsed.quiz = fq;
            log('  📝 Quiz ready: ' + fq.length + ' questions', 'ok');
          } else {
            parsed.quiz = quiz;
            log('  ⚠️ Day ' + dayObj.day + ' — quiz nahi bana, content save kar raha hoon', 'warn');
          }
        } else {
          parsed.quiz = quiz;
          log('  📝 Quiz: ' + quiz.length + ' questions', 'ok');
        }
      }
      selected.rlHits = 0; // provider worked -> reset backoff
      return {success:true, data:parsed};
    }

    // ─── Errors -> smart rotation ─────────────────────────────────────────────
    if(res.code === 401){
      log('  ❌ ' + selected.provider.toUpperCase() + ' Key ' + (selected.curKey+1) + ' invalid (401) — skip', 'err');
      selected.deadKeys.push(apiKey);
      selected.curKey = 0;
      await sleep(300);
      continue;
    }

    if(res.code === 429){
      var lkNow = liveKeysOf(selected);
      if(selected.curKey + 1 < lkNow.length){
        // Same provider still has another key on this model — stick with it
        selected.curKey++;
        log('  ⚠️ ' + selected.provider.toUpperCase() + ' key rate-limited — next key (' + (selected.curKey+1) + '/' + lkNow.length + ')...', 'warn');
        await sleep(400);
      } else if(selected.curModel + 1 < selected.models.length){
        // All keys busy on this model -> drop to the NEXT model (usually smaller
        // with much higher free limits, e.g. llama-3.1-8b-instant). This is the
        // key fix that breaks the rate-limit storm.
        selected.curKey = 0;
        selected.curModel++;
        log('  🔻 ' + selected.provider.toUpperCase() + ' saari keys busy — high-limit model pe switch: ' + selected.models[selected.curModel], 'warn');
        await sleep(400);
      } else {
        // Every key + model of this provider is rate-limited -> cooldown.
        // Respect the API's Retry-After and back off more each time.
        selected.curKey = 0;
        selected.curModel = 0;
        selected.rlHits = (selected.rlHits || 0) + 1;
        var base429 = (res.retryAfter && res.retryAfter > 0) ? res.retryAfter : 60000;
        var cd429 = Math.min(base429 * Math.pow(1.6, selected.rlHits - 1), 300000); // cap 5 min
        rateLimitUntil[selected.provider] = Date.now() + cd429;
        log('  ⚠️ ' + selected.provider.toUpperCase() + ' fully rate-limited — ' + Math.ceil(cd429/1000) + 's cooldown (x' + selected.rlHits + '), agle provider pe...', 'warn');
        await sleep(300);
      }
      continue;
    }

    if(res.code === 404){
      selected.curModel++;
      if(selected.curModel >= selected.models.length){
        selected.curModel = 0;
        rateLimitUntil[selected.provider] = Date.now() + 15000;
        log('  ⚠️ ' + selected.provider.toUpperCase() + ' models khatam — short cooldown, agle provider pe...', 'warn');
      } else {
        log('  ⚠️ ' + model + ' not found — agla model...', 'warn');
      }
      await sleep(500);
      continue;
    }

    if(res.code === 503 || res.code === 408 || res.code === 500 || res.code === 502){
      // A model/server is overloaded — another model may be fine, so rotate model first
      if(selected.curModel + 1 < selected.models.length){
        selected.curModel++;
        log('  ⚠️ ' + selected.provider.toUpperCase() + ' ' + res.msg + ' — agla model: ' + selected.models[selected.curModel], 'warn');
        await sleep(1200);
      } else {
        selected.curModel = 0;
        var cd5 = (res.retryAfter && res.retryAfter > 0) ? res.retryAfter : 10000;
        rateLimitUntil[selected.provider] = Date.now() + cd5;
        log('  ⚠️ ' + selected.provider.toUpperCase() + ' ' + res.msg + ' — ' + Math.ceil(cd5/1000) + 's cooldown, rotate...', 'warn');
        await sleep(1200);
      }
      continue;
    }

    // Unknown / empty response -> brief cooldown + rotate model
    log('  ❌ ' + selected.provider.toUpperCase() + ' error ' + res.code + ' (' + res.msg + ') — rotate...', 'warn');
    selected.curModel = (selected.curModel + 1) % selected.models.length;
    rateLimitUntil[selected.provider] = Date.now() + 6000;
    await sleep(1200);
  }
}

// ─── MAIN FLOW ────────────────────────────────────────────────────────────────
async function loadSettings(){
  try {
    var r = await fetch('../api/ai/get-settings.php');
    var d = await r.json();
    if(d.success){ AI_SETTINGS = d.settings; return true; }
  } catch(e){ console.warn('Settings load error:', e); }
  return false;
}

async function generateAll(chain, meta){
  failedDays = [];
  var doneDays = 0, t0 = Date.now();
  var BS = parseInt(meta.batch_size) || 7;
  var totalBatches = Math.ceil(syllabus.length / BS);

  for(var i = 0; i < syllabus.length; i++){
    var dayObj = syllabus[i];

    if(i % BS === 0){
      var bNum  = Math.floor(i / BS) + 1;
      var bEnd  = Math.min(i + BS - 1, syllabus.length - 1);
      document.getElementById('sBatch').textContent = bNum + '/' + totalBatches;
      log('── Batch ' + bNum + '/' + totalBatches + ': Day ' + syllabus[i].day + '–' + syllabus[bEnd].day + ' ──', 'spin');
    }

    var res = await generateOneDay(chain, dayObj, meta.topic, meta.level, meta.language, meta.include_quiz, meta.type);

    if(res.success){
      var item = res.data;
      allContent[item.day] = {
        day:         item.day,
        title:       dayObj.title || 'Day ' + item.day,
        topics:      dayObj.topics || [],
        has_quiz:    dayObj.has_quiz || false,
        content:     (function(){ try { return fixCodeBlocks(item.content || ''); } catch(ce){ return String(item.content || ''); } })(),
        image_query: item.image_query || (dayObj.image_query || meta.topic),
        quiz:        item.quiz || []
      };
      doneDays++;
      var qInfo = (item.quiz && item.quiz.length > 0) ? ' [Quiz: ' + item.quiz.length + 'Q]' : '';
      log('✅ Day ' + item.day + ': ' + (dayObj.title || '') + qInfo, 'ok');
    } else {
      log('❌ Day ' + dayObj.day + ' failed — will retry later', 'err');
      failedDays.push(i);
    }

    // Update stats
    var pct = Math.round((doneDays / syllabus.length) * 100);
    setProgress(pct, 'Day ' + doneDays + '/' + syllabus.length + ' complete');
    document.getElementById('sDone').textContent  = doneDays;

    if(doneDays > 0){
      var el  = (Date.now() - t0) / 1000;
      var rem = ((syllabus.length - doneDays) * el) / doneDays;
      document.getElementById('sETA').textContent = rem > 60 ? Math.ceil(rem/60) + 'm' : Math.ceil(rem) + 's';
    }

    // Batch boundary pause (every BS days) — short breather; real rate limits are
    // now handled per-call by the smart failover, so no need for a long pause.
    if((i + 1) % BS === 0 && i < syllabus.length - 1){
      log('⏸️ Batch complete — 8s breather...', 'wait');
      await sleep(8000);
      log('▶️ Next batch shuru...', 'spin');
    } else {
      await sleep(600); // small delay between days
    }
  }

  // ─── AUTO-RETRY: engine never stops until every day is generated ────────────
  var round = 0;
  while(failedDays.length > 0 && round < 10){
    var anyLive = chain.some(function(c){ return c.keys.some(function(k){ return c.deadKeys.indexOf(k) === -1; }); });
    if(!anyLive){
      log('🔴 Koi live API key nahi bachi — auto-retry rok raha hoon', 'err');
      break;
    }
    round++;
    log('🔁 Auto-retry round ' + round + ' — ' + failedDays.length + ' day(s) bache hain (engine khud retry kar raha hai)...', 'spin');
    var toRetry = failedDays.slice();
    failedDays  = [];
    for(var ri = 0; ri < toRetry.length; ri++){
      var idx2 = toRetry[ri];
      var d2   = syllabus[idx2];
      log('── Retry Day ' + d2.day + ' (round ' + round + ') ──', 'spin');
      var r2 = await generateOneDay(chain, d2, meta.topic, meta.level, meta.language, meta.include_quiz, meta.type);
      if(r2.success){
        var it2 = r2.data;
        allContent[it2.day] = {
          day:         it2.day,
          title:       d2.title || 'Day ' + it2.day,
          topics:      d2.topics || [],
          has_quiz:    d2.has_quiz || false,
          content:     fixCodeBlocks(it2.content || ''),
          image_query: it2.image_query || meta.topic,
          quiz:        it2.quiz || []
        };
        doneDays++;
        var pct2 = Math.round((doneDays / syllabus.length) * 100);
        setProgress(pct2, 'Day ' + doneDays + '/' + syllabus.length + ' complete');
        document.getElementById('sDone').textContent = doneDays;
        log('✅ Day ' + it2.day + ' recovered (round ' + round + ')', 'ok');
      } else {
        failedDays.push(idx2);
        if(r2.fatal){ failedDays = failedDays.concat(toRetry.slice(ri + 1)); break; }
      }
      await sleep(1200);
    }
    if(failedDays.length > 0) await sleep(5000); // breather before next round
  }

  if(failedDays.length > 0){
    document.getElementById('errBox').style.display = 'block';
    document.getElementById('errMsg').textContent   = failedDays.length + ' day(s) abhi bhi baaki — manual retry karo (ya Settings mein valid/extra API key add karo).';
  } else {
    finalize();
  }
}

async function retryFailed(){
  document.getElementById('errBox').style.display = 'none';
  var toRetry = failedDays.slice();
  failedDays  = [];
  var chain   = window._chain;
  // Reset dead keys and rate limits for retry
  chain.forEach(function(c){ c.deadKeys = []; });

  log('🔄 Retrying ' + toRetry.length + ' failed day(s)...', 'spin');

  for(var ri = 0; ri < toRetry.length; ri++){
    var idx    = toRetry[ri];
    var dayObj = syllabus[idx];
    log('── Retry: Day ' + dayObj.day + ' ──', 'spin');
    var res = await generateOneDay(chain, dayObj, meta.topic, meta.level, meta.language, meta.include_quiz, meta.type);
    if(res.success){
      var item = res.data;
      allContent[item.day] = {
        day:         item.day,
        title:       dayObj.title || 'Day ' + item.day,
        topics:      dayObj.topics || [],
        has_quiz:    dayObj.has_quiz || false,
        content:     fixCodeBlocks(item.content || ''),
        image_query: item.image_query || meta.topic,
        quiz:        item.quiz || []
      };
      log('✅ Day ' + item.day + ' retry OK', 'ok');
      document.getElementById('sDone').textContent = parseInt(document.getElementById('sDone').textContent) + 1;
    } else {
      failedDays.push(idx);
      log('❌ Day ' + dayObj.day + ' still failing', 'err');
    }
    await sleep(1500);
  }

  if(failedDays.length > 0){
    document.getElementById('errBox').style.display = 'block';
    document.getElementById('errMsg').textContent   = failedDays.length + ' day(s) abhi bhi fail. Dobara retry karo.';
  } else {
    finalize();
  }
}

function finalize(){
  setProgress(100, 'Complete!');
  document.getElementById('sETA').textContent   = 'Done';
  document.getElementById('sBatch').textContent = 'Done';
  document.getElementById('topIcon').textContent = '🎉';
  document.getElementById('mainTitle').textContent = 'Course Ready!';
  document.getElementById('mainSub').innerHTML = '<span style="color:#4ade80;font-weight:600">Sab content ready! Preview pe ja raha hoon...</span>';
  log('🎉 All done! Saving...', 'ok');
  var arr = Object.values(allContent).sort(function(a,b){ return a.day - b.day; });

  // Large (e.g. 6-month / 180-day) courses can exceed sessionStorage quota —
  // handle it gracefully instead of failing silently.
  try {
    sessionStorage.setItem('ai_course_data', JSON.stringify({ course:arr, total_days:arr.length }));
  } catch(e) {
    log('❌ Course data save fail (browser storage full): ' + e.message, 'err');
    document.getElementById('topIcon').textContent   = '⚠️';
    document.getElementById('mainTitle').textContent = 'Storage Full';
    document.getElementById('mainSub').innerHTML =
      '<span style="color:#fbbf24;font-weight:600">' + arr.length +
      ' din ka content bahut bada hai — browser storage full. Chhota duration try karo ya batch size badhao.</span>';
    return;
  }
  setTimeout(function(){ window.location.href = 'preview.php'; }, 1500);
}

// ─── INIT ─────────────────────────────────────────────────────────────────────
async function init(){
  var rawS = sessionStorage.getItem('ai_syllabus');
  var rawM = sessionStorage.getItem('ai_meta');
  if(!rawS || !rawM){ window.location.href = 'generate.php'; return; }

  syllabus = JSON.parse(rawS);
  meta     = JSON.parse(rawM);

  document.getElementById('sTotal').textContent    = syllabus.length;
  document.getElementById('mainTitle').textContent = '"' + meta.topic + '" — ' + meta.days + ' Days';
  log('📋 Syllabus: ' + syllabus.length + ' days | Level: ' + meta.level, 'ok');

  log('⏳ Settings load ho rahi hai...', 'wait');
  var ok = await loadSettings();
  if(!ok || !AI_SETTINGS){
    log('❌ Settings load fail — Settings page pe jao aur save karo', 'err');
    return;
  }

  var settingCount = Object.keys(AI_SETTINGS).length;
  log('✅ Settings loaded (' + settingCount + ' keys)', 'ok');

  // Build provider chain — THIS is what fixes the infinite loop
  var chain = buildProviderChain(AI_SETTINGS);
  if(chain.length === 0){
    log('❌ Koi bhi provider configured nahi! Settings mein API key daalo.', 'err');
    return;
  }

  window._chain = chain; // save for retry

  var primary = chain[0];
  log('🤖 Primary Provider: ' + primary.provider.toUpperCase() + ' (' + primary.keys.length + ' keys)', 'ok');
  log('🔗 Provider Chain: ' + chain.map(function(c){ return c.provider.toUpperCase()+'('+c.keys.length+'k)'; }).join(' → '), 'ok');
  log('📝 Quiz: ' + (meta.include_quiz ? '10 questions per quiz day' : 'Disabled') + ' (' + meta.level + ', ' + meta.days + ' days)', 'ok');
  log('🔗 Models: ' + primary.models.slice(0,3).join(' → ') + '...', 'ok');

  await generateAll(chain, meta);
}

init();
</script>
</body>
</html>