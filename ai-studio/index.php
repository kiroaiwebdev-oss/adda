<?php
session_name('ai_studio_session');
session_start();
if (isset($_SESSION['studio_user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Studio — Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #1F2A44;
    font-family: 'Segoe UI', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .login-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 20px;
    padding: 48px 40px;
    width: 100%;
    max-width: 420px;
    backdrop-filter: blur(10px);
  }
  .input-f {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    border-radius: 10px;
    padding: 13px 16px;
    width: 100%;
    font-size: 15px;
    outline: none;
    transition: border-color 0.2s;
  }
  .input-f:focus { border-color: rgba(255,255,255,0.5); }
  .input-f::placeholder { color: rgba(255,255,255,0.3); }
  .btn-login {
    background: #fff;
    color: #1F2A44;
    border: none;
    border-radius: 10px;
    padding: 14px;
    width: 100%;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
  }
  .btn-login:hover { background: #f0f0f0; transform: translateY(-1px); }
  .btn-login:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
  label { color: rgba(255,255,255,0.6); font-size: 13px; display: block; margin-bottom: 7px; }
  .error-box {
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.4);
    color: #fca5a5;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    display: none;
    margin-bottom: 16px;
  }
  /* Glow dots */
  .dot {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
    pointer-events: none;
  }
</style>
</head>
<body>

<!-- Background glow -->
<div class="dot" style="width:400px;height:400px;background:#4f6af5;top:-100px;left:-100px"></div>
<div class="dot" style="width:300px;height:300px;background:#a855f7;bottom:-50px;right:-50px"></div>

<div class="login-card">

  <!-- Logo -->
  <div style="text-align:center;margin-bottom:36px">
    <div style="font-size:44px;margin-bottom:12px">🤖</div>
    <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-0.5px">AI Studio</h1>
    <p style="color:rgba(255,255,255,0.4);font-size:14px;margin-top:5px">InternshipADDA Course Generator</p>
  </div>

  <div class="error-box" id="errBox"></div>

  <div style="display:flex;flex-direction:column;gap:18px">
    <div>
      <label>Email Address</label>
      <input type="email" id="email" class="input-f"
        placeholder="admin@internshipadda.com"
        value="admin@internshipadda.com" />
    </div>
    <div>
      <label>Password</label>
      <div style="position:relative">
        <input type="password" id="password" class="input-f"
          placeholder="••••••••"
          onkeydown="if(event.key==='Enter')doLogin()" />
        <button onclick="togglePass()"
          style="position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer;font-size:18px">
          👁
        </button>
      </div>
    </div>

    <button class="btn-login" id="loginBtn" onclick="doLogin()">
      🔐 Login to AI Studio
    </button>
  </div>

 
</div>

<script>
function togglePass() {
  const p = document.getElementById('password');
  p.type  = p.type === 'password' ? 'text' : 'password';
}

async function doLogin() {
  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const btn      = document.getElementById('loginBtn');
  const err      = document.getElementById('errBox');

  if (!email || !password) { showErr('Email aur password dono required hain'); return; }

  btn.disabled    = true;
  btn.textContent = '⏳ Logging in...';
  err.style.display = 'none';

  try {
    const res  = await fetch('../api/ai/auth.php?action=login', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email, password })
    });
    const data = await res.json();

    if (data.success) {
      btn.textContent = '✅ Redirecting...';
      window.location.href = 'dashboard.php';
    } else {
      showErr(data.message || 'Login failed');
      btn.disabled    = false;
      btn.textContent = '🔐 Login to AI Studio';
    }
  } catch(e) {
    showErr('Server error: ' + e.message);
    btn.disabled    = false;
    btn.textContent = '🔐 Login to AI Studio';
  }
}

function showErr(msg) {
  const el      = document.getElementById('errBox');
  el.textContent = '❌ ' + msg;
  el.style.display = 'block';
}
</script>
</body>
</html>