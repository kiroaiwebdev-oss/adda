<?php
session_name('ai_studio_session');
session_start();
if (!isset($_SESSION['studio_user_id'])) { header('Location: index.php'); exit; }
if ($_SESSION['studio_role'] !== 'admin') { header('Location: dashboard.php'); exit; }
$userName = $_SESSION['studio_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; }
.sidebar { background: rgba(0,0,0,0.25); border-right: 1px solid rgba(255,255,255,0.08); width: 240px; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 50; display: flex; flex-direction: column; padding: 24px 16px; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: rgba(255,255,255,0.5); font-size: 14px; text-decoration: none; transition: all 0.2s; }
.nav-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
.nav-link.active { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.15); }
.main { margin-left: 240px; padding: 40px; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 28px; margin-bottom: 20px; }
.section-title { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
.section-sub { color: rgba(255,255,255,0.4); font-size: 13px; margin-bottom: 22px; }
.input-f { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 10px; padding: 11px 15px; width: 100%; font-size: 14px; outline: none; transition: border-color 0.2s; }
.input-f:focus { border-color: rgba(255,255,255,0.5); }
.input-f::placeholder { color: rgba(255,255,255,0.2); }
select.input-f option { background: #1F2A44; }
label { color: rgba(255,255,255,0.55); font-size: 13px; display: block; margin-bottom: 7px; }
.provider-btn { background: rgba(255,255,255,0.05); border: 2px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 16px 12px; cursor: pointer; transition: all 0.2s; text-align: center; }
.provider-btn:hover { border-color: rgba(255,255,255,0.3); }
.provider-btn.active { border-color: #fff; background: rgba(255,255,255,0.1); }
.badge-free { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; margin-top: 6px; }
.badge-paid { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.3); padding: 3px 9px; border-radius: 20px; font-size: 11px; display: inline-block; margin-top: 6px; }
.badge-opt { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; display: inline-block; margin-left: 6px; vertical-align: middle; }
.btn-save { background: #fff; color: #1F2A44; border: none; border-radius: 10px; padding: 13px 32px; font-size: 15px; font-weight: 700; cursor: pointer; }
.btn-save:hover { background: #f0f0f0; }
.btn-test { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: #fff; border-radius: 8px; padding: 10px 18px; font-size: 13px; cursor: pointer; width: 100%; margin-top: 12px; font-weight: 600; }
.btn-test:hover { background: rgba(255,255,255,0.14); }
.btn-test:disabled { opacity: 0.5; cursor: not-allowed; }
.key-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.key-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 12px 14px; }
.model-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); border-radius: 6px; }
.model-row:last-child { border-bottom: none; }
#toast { display: none; position: fixed; top: 24px; right: 24px; padding: 13px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; z-index: 9999; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
</style>
</head>
<body>
<div id="toast"></div>

<div class="sidebar">
  <div style="margin-bottom:32px">
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-size:22px">🤖</span>
      <span style="font-weight:800;font-size:17px">AI Studio</span>
    </div>
    <p style="color:rgba(255,255,255,0.3);font-size:11px;padding-left:32px;margin-top:2px">InternshipADDA</p>
  </div>
  <nav style="flex:1;display:flex;flex-direction:column;gap:4px">
    <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
    <a href="generate.php"  class="nav-link">✨ Generate</a>
    <a href="settings.php"  class="nav-link active">⚙️ Settings</a>
  </nav>
  <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:16px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-weight:700"><?= strtoupper(substr($userName,0,1)) ?></div>
      <div>
        <p style="font-size:13px;font-weight:600"><?= htmlspecialchars($userName) ?></p>
        <p style="color:rgba(255,255,255,0.35);font-size:11px">Admin</p>
      </div>
    </div>
    <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;color:rgba(255,100,100,0.8);font-size:13px;text-decoration:none" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">🚪 Logout</a>
  </div>
</div>

<div class="main">
  <div style="max-width:720px">
    <div style="margin-bottom:32px">
      <h1 style="font-size:24px;font-weight:800;margin-bottom:6px">⚙️ Settings</h1>
      <p style="color:rgba(255,255,255,0.4);font-size:14px">AI provider aur system configure karo</p>
    </div>

    <!-- PROVIDER SELECTION -->
    <div class="card">
      <div class="section-title">🤖 AI Provider</div>
      <div class="section-sub">Kaunse AI se course content generate hoga</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px">
        <div class="provider-btn active" id="btnGemini" onclick="selectAI('gemini')">
          <div style="font-size:26px;margin-bottom:4px">✨</div>
          <div style="font-weight:700;font-size:14px">Google Gemini</div>
          <div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">2.5 Flash, 2.0 Flash...</div>
          <span class="badge-free">FREE</span>
        </div>
        <div class="provider-btn" id="btnGroq" onclick="selectAI('groq')">
          <div style="font-size:26px;margin-bottom:4px">🚀</div>
          <div style="font-weight:700;font-size:14px">Groq</div>
          <div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Llama, Qwen, Scout...</div>
          <span class="badge-free">FREE</span>
        </div>
        <div class="provider-btn" id="btnOpenai" onclick="selectAI('openai')">
          <div style="font-size:26px;margin-bottom:4px">🤖</div>
          <div style="font-weight:700;font-size:14px">OpenAI</div>
          <div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">GPT-4o, GPT-4-turbo...</div>
          <span class="badge-paid">PAID</span>
        </div>
        <div class="provider-btn" id="btnGrok" onclick="selectAI('grok')">
          <div style="font-size:26px;margin-bottom:4px">⚡</div>
          <div style="font-weight:700;font-size:14px">xAI Grok</div>
          <div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Grok-3, Grok-4...</div>
          <span class="badge-paid">PAID</span>
        </div>
      </div>

      <!-- GEMINI -->
      <div id="geminiSection">
        <div class="key-group">
          <div class="key-item">
            <label style="color:#fff;font-weight:600">🔑 API Key 1 <a href="https://makersuite.google.com/app/apikey" target="_blank" style="color:rgba(255,255,255,0.35);font-size:11px;font-weight:400;margin-left:8px">Free key lao →</a></label>
            <div style="position:relative"><input type="password" id="geminiKey" class="input-f" placeholder="AIzaSy..."><button type="button" onclick="tv('geminiKey')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 2 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="geminiKey2" class="input-f" placeholder="AIzaSy..."><button type="button" onclick="tv('geminiKey2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 3 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="geminiKey3" class="input-f" placeholder="AIzaSy..."><button type="button" onclick="tv('geminiKey3')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
        </div>
        <button class="btn-test" id="testGeminiBtn" onclick="testModels('gemini')">🔍 Test Keys & Auto-Select Best Model</button>
        <div id="geminiTestResults" style="display:none;margin-top:14px;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px">
          <p style="color:rgba(255,255,255,0.4);font-size:11px;font-weight:700;letter-spacing:1px;margin-bottom:12px">MODEL TEST RESULTS — GEMINI</p>
          <div id="geminiModelList"></div>
          <div id="geminiBestBox" style="display:none;margin-top:14px;padding:14px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px">
            <p style="color:#4ade80;font-size:12px;font-weight:700;margin-bottom:10px">✅ Working Models — Select karo:</p>
            <select id="geminiSelectedModel" class="input-f" style="font-family:monospace;font-weight:700"></select>
          </div>
        </div>
      </div>

      <!-- GROQ -->
      <div id="groqSection" style="display:none">
        <div class="key-group">
          <div class="key-item">
            <label style="color:#fff;font-weight:600">🔑 API Key 1 <a href="https://console.groq.com" target="_blank" style="color:rgba(255,255,255,0.35);font-size:11px;font-weight:400;margin-left:8px">Free key lao →</a></label>
            <div style="position:relative"><input type="password" id="groqKey" class="input-f" placeholder="gsk_..."><button type="button" onclick="tv('groqKey')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 2 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="groqKey2" class="input-f" placeholder="gsk_..."><button type="button" onclick="tv('groqKey2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 3 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="groqKey3" class="input-f" placeholder="gsk_..."><button type="button" onclick="tv('groqKey3')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
        </div>
        <button class="btn-test" id="testGroqBtn" onclick="testModels('groq')">🔍 Test Keys & Auto-Select Best Model</button>
        <div id="groqTestResults" style="display:none;margin-top:14px;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px">
          <p style="color:rgba(255,255,255,0.4);font-size:11px;font-weight:700;letter-spacing:1px;margin-bottom:12px">MODEL TEST RESULTS — GROQ</p>
          <div id="groqModelList"></div>
          <div id="groqBestBox" style="display:none;margin-top:14px;padding:14px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px">
            <p style="color:#4ade80;font-size:12px;font-weight:700;margin-bottom:10px">✅ Working Models — Select karo:</p>
            <select id="groqSelectedModel" class="input-f" style="font-family:monospace;font-weight:700"></select>
          </div>
        </div>
      </div>

      <!-- OPENAI -->
      <div id="openaiSection" style="display:none">
        <div class="key-group">
          <div class="key-item">
            <label style="color:#fff;font-weight:600">🔑 API Key 1 <a href="https://platform.openai.com/api-keys" target="_blank" style="color:rgba(255,255,255,0.35);font-size:11px;font-weight:400;margin-left:8px">Key lao →</a></label>
            <div style="position:relative"><input type="password" id="openaiKey" class="input-f" placeholder="sk-..."><button type="button" onclick="tv('openaiKey')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 2 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="openaiKey2" class="input-f" placeholder="sk-..."><button type="button" onclick="tv('openaiKey2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 3 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="openaiKey3" class="input-f" placeholder="sk-..."><button type="button" onclick="tv('openaiKey3')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
        </div>
        <button class="btn-test" id="testOpenaiBtn" onclick="testModels('openai')">🔍 Test Keys & Auto-Select Best Model</button>
        <div id="openaiTestResults" style="display:none;margin-top:14px;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px">
          <p style="color:rgba(255,255,255,0.4);font-size:11px;font-weight:700;letter-spacing:1px;margin-bottom:12px">MODEL TEST RESULTS — OPENAI</p>
          <div id="openaiModelList"></div>
          <div id="openaiBestBox" style="display:none;margin-top:14px;padding:14px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px">
            <p style="color:#4ade80;font-size:12px;font-weight:700;margin-bottom:10px">✅ Working Models — Select karo:</p>
            <select id="openaiSelectedModel" class="input-f" style="font-family:monospace;font-weight:700"></select>
          </div>
        </div>
      </div>

      <!-- GROK -->
      <div id="grokSection" style="display:none">
        <div class="key-group">
          <div class="key-item">
            <label style="color:#fff;font-weight:600">🔑 API Key 1 <a href="https://console.x.ai" target="_blank" style="color:rgba(255,255,255,0.35);font-size:11px;font-weight:400;margin-left:8px">Key lao →</a></label>
            <div style="position:relative"><input type="password" id="grokKey" class="input-f" placeholder="xai-..."><button type="button" onclick="tv('grokKey')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 2 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="grokKey2" class="input-f" placeholder="xai-..."><button type="button" onclick="tv('grokKey2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
          <div class="key-item">
            <label>🔑 API Key 3 <span class="badge-opt">OPTIONAL</span></label>
            <div style="position:relative"><input type="password" id="grokKey3" class="input-f" placeholder="xai-..."><button type="button" onclick="tv('grokKey3')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer">👁</button></div>
          </div>
        </div>
        <button class="btn-test" id="testGrokBtn" onclick="testModels('grok')">🔍 Test Keys & Auto-Select Best Model</button>
        <div id="grokTestResults" style="display:none;margin-top:14px;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px">
          <p style="color:rgba(255,255,255,0.4);font-size:11px;font-weight:700;letter-spacing:1px;margin-bottom:12px">MODEL TEST RESULTS — XAI GROK</p>
          <div id="grokModelList"></div>
          <div id="grokBestBox" style="display:none;margin-top:14px;padding:14px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px">
            <p style="color:#4ade80;font-size:12px;font-weight:700;margin-bottom:10px">✅ Working Models — Select karo:</p>
            <select id="grokSelectedModel" class="input-f" style="font-family:monospace;font-weight:700"></select>
          </div>
        </div>
      </div>
    </div>

    <!-- CONTENT SETTINGS -->
    <div class="card">
      <div class="section-title">📝 Content Settings</div>
      <div class="section-sub">Default generation preferences</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label>Default Language</label>
          <select id="defaultLang" class="input-f">
            <option value="English">English</option>
            <option value="Hindi">Hindi</option>
            <option value="Hinglish">Hinglish</option>
          </select>
        </div>
        <div>
          <label>Batch Size</label>
          <select id="batchSize" class="input-f">
            <option value="5">5 Days per batch</option>
            <option value="7" selected>7 Days per batch</option>
            <option value="10">10 Days per batch</option>
          </select>
        </div>
      </div>
      <div>
        <label>Image Search</label>
        <select id="imgEngine" class="input-f">
          <option value="google">Google Images</option>
          <option value="unsplash">Unsplash First</option>
          <option value="pexels">Pexels First</option>
        </select>
      </div>
    </div>

    <button class="btn-save" onclick="saveSettings()">💾 Settings Save Karo</button>
    <p id="saveStatus" style="color:rgba(255,255,255,0.3);font-size:12px;margin-top:10px"></p>
  </div>
</div>

<script>
var activeAI = 'gemini';
var PROVIDERS = ['gemini','groq','openai','grok'];

// ── Load Settings ────────────────────────────────
async function loadSettings() {
    try {
        var r       = await fetch('../api/ai/settings.php?action=get');
        var rawText = await r.text(); // ✅ pehle text lo
        console.log('[LOAD] raw:', rawText.substring(0, 200));

        if (rawText.indexOf('<?php') !== -1 || rawText.indexOf('Fatal') !== -1) {
            console.error('[LOAD] PHP not executing:', rawText);
            return;
        }

        var data;
        try { data = JSON.parse(rawText); }
        catch(e) { console.error('[LOAD] JSON parse fail:', rawText.substring(0,300)); return; }

        if (!data.success || !data.settings) return;
        var s = data.settings;

        activeAI = s['active_ai_provider'] || 'gemini';
        selectAI(activeAI, false);

        PROVIDERS.forEach(function(p) {
            var k1 = document.getElementById(p+'Key');
            var k2 = document.getElementById(p+'Key2');
            var k3 = document.getElementById(p+'Key3');
            if (k1 && s[p+'_api_key'])   k1.value = s[p+'_api_key'];
            if (k2 && s[p+'_api_key_2']) k2.value = s[p+'_api_key_2'];
            if (k3 && s[p+'_api_key_3']) k3.value = s[p+'_api_key_3'];

            var savedModel = s[p+'_model'];
            if (savedModel && savedModel.trim()) {
                var rt = document.getElementById(p+'TestResults');
                var bb = document.getElementById(p+'BestBox');
                var sl = document.getElementById(p+'SelectedModel');
                var ml = document.getElementById(p+'ModelList');
                if (rt) rt.style.display = 'block';
                if (bb) bb.style.display = 'block';
                if (sl) sl.innerHTML = '<option value="'+savedModel+'" selected>'+savedModel+' ⭐ (Saved)</option>';
                if (ml) ml.innerHTML = '<p style="color:rgba(255,255,255,0.4);font-size:12px">Saved: <strong style="color:#fff">'+savedModel+'</strong></p>';
            }
        });

        if (s['default_language'])    document.getElementById('defaultLang').value = s['default_language'];
        if (s['batch_size'])          document.getElementById('batchSize').value   = s['batch_size'];
        if (s['image_search_engine']) document.getElementById('imgEngine').value   = s['image_search_engine'];

        console.log('[LOAD] Settings loaded OK. Active: '+activeAI);
    } catch(e) {
        console.error('[LOAD] Error:', e);
    }
}

// ── Select AI Provider ───────────────────────────
function selectAI(type, upd) {
    activeAI = type;
    PROVIDERS.forEach(function(p) {
        var cap = p.charAt(0).toUpperCase() + p.slice(1);
        var btn = document.getElementById('btn' + cap);
        var sec = document.getElementById(p + 'Section');
        if (btn) btn.className = 'provider-btn' + (p === type ? ' active' : '');
        if (sec) sec.style.display = p === type ? 'block' : 'none';
    });
}

// ── Test Models ──────────────────────────────────
async function testModels(provider) {
    var keyEl = document.getElementById(provider + 'Key');
    if (!keyEl || !keyEl.value.trim()) { toast('❌ Pehle API Key 1 daalo!', true); return; }

    var cap     = provider.charAt(0).toUpperCase() + provider.slice(1);
    var btn     = document.getElementById('test' + cap + 'Btn');
    var results = document.getElementById(provider + 'TestResults');
    var list    = document.getElementById(provider + 'ModelList');
    var bestBox = document.getElementById(provider + 'BestBox');
    var selEl   = document.getElementById(provider + 'SelectedModel');

    btn.disabled    = true;
    btn.textContent = '⏳ Testing...';
    results.style.display = 'block';
    bestBox.style.display = 'none';
    list.innerHTML = '<p style="color:rgba(255,255,255,0.4);font-size:13px;padding:8px 0">🔄 Testing all models...</p>';

    try {
        var r       = await fetch('../api/ai/test-models.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ api_key: keyEl.value.trim(), provider: provider })
        });
        var rawText = await r.text(); // ✅ pehle text
        console.log('[TEST] raw:', rawText.substring(0,300));

        var data;
        try { data = JSON.parse(rawText); }
        catch(e) { toast('❌ Test response parse error!', true); console.error(rawText); btn.disabled=false; btn.textContent='🔍 Test Keys & Auto-Select Best Model'; return; }

        list.innerHTML = data.results.map(function(r) {
            var color = r.working ? '#4ade80' : r.status===429 ? '#fbbf24' : 'rgba(255,255,255,0.3)';
            var bg    = r.working ? 'rgba(34,197,94,0.05)' : r.status===429 ? 'rgba(251,191,36,0.05)' : 'transparent';
            return '<div class="model-row" style="background:'+bg+'"><span style="color:#fff;font-size:13px;font-family:monospace">'+r.model+'</span><span style="color:'+color+';font-size:12px;font-weight:600">'+r.message+'</span></div>';
        }).join('');

        var working = data.results.filter(function(r) { return r.working; });
        if (working.length > 0) {
            bestBox.style.display = 'block';
            selEl.innerHTML = working.map(function(r) {
                return '<option value="'+r.model+'"'+(r.model===data.best_model?' selected':'')+'>'+r.model+(r.model===data.best_model?' ⭐ (Best)':'')+' ('+r.latency_ms+'ms)</option>';
            }).join('');
            toast('✅ '+working.length+' working model(s) mile! Ek select karo phir Save karo.');
        } else {
            toast('⚠️ Koi working model nahi mila. API key check karo.', true);
        }
    } catch(e) {
        toast('❌ Test error: '+e.message, true);
        console.error(e);
    }

    btn.disabled    = false;
    btn.textContent = '🔍 Test Keys & Auto-Select Best Model';
}

// ── Save Settings ────────────────────────────────
async function saveSettings() {
    var payload = { active_ai_provider: activeAI };

    PROVIDERS.forEach(function(p) {
        var k1 = document.getElementById(p+'Key');
        var k2 = document.getElementById(p+'Key2');
        var k3 = document.getElementById(p+'Key3');
        if (k1) payload[p+'_api_key']   = k1.value.trim();
        if (k2) payload[p+'_api_key_2'] = k2.value.trim();
        if (k3) payload[p+'_api_key_3'] = k3.value.trim();

        // ✅ Selected model dropdown se lo
        var sel = document.getElementById(p+'SelectedModel');
        if (sel && sel.value && sel.value.trim()) {
            payload[p+'_model'] = sel.value.trim();
            console.log('[SAVE] '+p+'_model = '+sel.value.trim());
        }
    });

    payload['default_language']    = document.getElementById('defaultLang').value;
    payload['batch_size']          = document.getElementById('batchSize').value;
    payload['image_search_engine'] = document.getElementById('imgEngine').value;

    console.log('[SAVE] Full payload:', JSON.stringify(payload, null, 2));
    document.getElementById('saveStatus').textContent = 'Saving...';

    try {
        var r = await fetch('../api/ai/settings.php?action=save', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload)
        });

        // ✅ FIXED — pehle raw text, phir JSON parse
        var rawText = await r.text();
        console.log('[SAVE] Raw server response:', rawText);

        // PHP execute nahi hua?
        if (rawText.indexOf('<?php') !== -1 || rawText.indexOf('Fatal error') !== -1 || rawText.indexOf('Parse error') !== -1) {
            toast('❌ PHP server error! Console dekho.', true);
            document.getElementById('saveStatus').textContent = 'PHP error!';
            console.error('[SAVE] PHP not executing:', rawText);
            return;
        }

        // JSON parse
        var data;
        try {
            data = JSON.parse(rawText);
        } catch(parseErr) {
            toast('❌ Response JSON nahi hai: '+rawText.substring(0,80), true);
            document.getElementById('saveStatus').textContent = 'Parse error!';
            console.error('[SAVE] JSON parse failed. Raw:', rawText);
            return;
        }

        if (data.success) {
            // DB se verified values confirm karo
            var prov  = activeAI;
            var model = 'not set';
            if (data.verified) {
                prov  = data.verified['active_ai_provider'] || activeAI;
                model = data.verified[prov+'_model'] || '⚠️ not saved';
            }
            toast('✅ Saved! Provider: '+prov.toUpperCase()+' | Model: '+model);
            document.getElementById('saveStatus').textContent = 'Last saved: '+new Date().toLocaleTimeString();
            console.log('[SAVE] Verified DB:', data.verified);
        } else {
            toast('❌ Save failed: '+(data.message||'unknown'), true);
            document.getElementById('saveStatus').textContent = 'Save failed!';
        }
    } catch(e) {
        toast('❌ Network error: '+e.message, true);
        document.getElementById('saveStatus').textContent = 'Network error!';
        console.error('[SAVE] Fetch error:', e);
    }
}

// ── Helpers ──────────────────────────────────────
function tv(id) {
    var el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function toast(msg, err) {
    var t = document.getElementById('toast');
    t.textContent      = msg;
    t.style.background = err ? '#ef4444' : '#22c55e';
    t.style.color      = '#fff';
    t.style.display    = 'block';
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(function() { t.style.display = 'none'; }, 5000);
}

// ── Init ─────────────────────────────────────────
loadSettings();
</script>
</body>
</html>