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
<title>Dashboard — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; }
.sidebar { background: rgba(0,0,0,0.25); border-right: 1px solid rgba(255,255,255,0.08); width: 240px; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 50; display: flex; flex-direction: column; padding: 24px 16px; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: rgba(255,255,255,0.5); font-size: 14px; text-decoration: none; transition: all 0.2s; }
.nav-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
.nav-link.active { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.15); }
.main { margin-left: 240px; padding: 40px; }
.stat-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 24px; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-saved      { background: rgba(34,197,94,0.15);  color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
.badge-generating { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
.badge-failed     { background: rgba(239,68,68,0.12);  color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
.badge-course     { background: rgba(99,102,241,0.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
.badge-internship { background: rgba(168,85,247,0.15); color: #d8b4fe; border: 1px solid rgba(168,85,247,0.3); }
table { width: 100%; border-collapse: collapse; }
th { color: rgba(255,255,255,0.35); font-size: 11px; font-weight: 700; letter-spacing: 1px; padding: 12px 16px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.75); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(255,255,255,0.03); }
.btn-sm { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 6px; padding: 5px 12px; font-size: 12px; cursor: pointer; text-decoration: none; transition: all 0.2s; display: inline-block; }
.btn-sm:hover { background: rgba(255,255,255,0.15); }
.btn-view { background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.4); color: #a5b4fc; border-radius: 6px; padding: 5px 12px; font-size: 12px; cursor: pointer; transition: all 0.2s; display: inline-block; }
.btn-view:hover { background: rgba(99,102,241,0.35); color: #fff; }
.btn-del { background: rgba(239,68,68,0.18); border: 1px solid rgba(239,68,68,0.4); color: #fca5a5; border-radius: 6px; padding: 5px 12px; font-size: 12px; cursor: pointer; transition: all 0.2s; display: inline-block; margin-left: 4px; }
.btn-del:hover { background: rgba(239,68,68,0.35); color: #fff; }
.btn-del:disabled { opacity: 0.5; cursor: not-allowed; }

/* Modal */
.modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; }
.modal-bg.open { display:flex; }
.modal-box { background:#1a2540; border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:32px; max-width:400px; width:100%; text-align:center; }
</style>
</head>
<body>

<!-- Loading Modal -->
<div class="modal-bg" id="loadingModal">
  <div class="modal-box">
    <div style="font-size:44px;margin-bottom:16px" id="modalIcon">⏳</div>
    <h2 style="font-size:18px;font-weight:800;margin-bottom:8px" id="modalTitle">Course Load Ho Raha Hai...</h2>
    <p style="color:rgba(255,255,255,0.4);font-size:13px" id="modalMsg">Please wait...</p>
    <div id="modalActions" style="display:none;margin-top:20px;display:flex;flex-direction:column;gap:10px">
      <a id="modalLmsBtn" href="#" target="_blank"
        style="background:#fff;color:#1F2A44;border-radius:8px;padding:10px;font-weight:700;font-size:14px;text-decoration:none;display:block">
        ✏️ LMS mein Edit Karo
      </a>
      <button onclick="closeModal()"
        style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;border-radius:8px;padding:10px;font-size:14px;cursor:pointer">
        ✕ Band Karo
      </button>
    </div>
    <button onclick="closeModal()" id="modalCloseBtn"
      style="display:none;margin-top:16px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;border-radius:8px;padding:9px 20px;font-size:13px;cursor:pointer">
      ✕ Close
    </button>
  </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
  <div style="margin-bottom:32px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
      <span style="font-size:22px">🤖</span>
      <span style="color:#fff;font-weight:800;font-size:17px">AI Studio</span>
    </div>
    <p style="color:rgba(255,255,255,0.3);font-size:11px;padding-left:32px">InternshipADDA</p>
  </div>
  <nav style="flex:1;display:flex;flex-direction:column;gap:4px">
    <a href="dashboard.php" class="nav-link active">📊 Dashboard</a>
    <a href="generate.php"  class="nav-link">✨ Generate</a>
    <?php if($userRole==='admin'): ?>
    <a href="settings.php"  class="nav-link">⚙️ Settings</a>
    <?php endif; ?>
  </nav>
  <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:16px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px">
        <?= strtoupper(substr($userName,0,1)) ?>
      </div>
      <div>
        <p style="color:#fff;font-size:13px;font-weight:600"><?= htmlspecialchars($userName) ?></p>
        <p style="color:rgba(255,255,255,0.35);font-size:11px"><?= ucfirst($userRole) ?></p>
      </div>
    </div>
    <a href="logout.php"
      style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;color:rgba(255,100,100,0.8);font-size:13px;text-decoration:none"
      onmouseover="this.style.background='rgba(239,68,68,0.1)'"
      onmouseout="this.style.background='transparent'">🚪 Logout</a>
  </div>
</div>

<!-- Main -->
<div class="main">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px">
    <div>
      <h1 style="font-size:24px;font-weight:800;margin-bottom:4px">📊 Dashboard</h1>
      <p style="color:rgba(255,255,255,0.4);font-size:14px">AI se generate kiye gaye courses ka overview</p>
    </div>
    <a href="generate.php"
      style="background:#fff;color:#1F2A44;border:none;border-radius:10px;padding:12px 20px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
      ✨ New Course
    </a>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:28px">
    <div class="stat-card" style="text-align:center">
      <div style="font-size:28px;font-weight:800;margin-bottom:4px" id="st-total">—</div>
      <div style="color:rgba(255,255,255,0.4);font-size:12px">Total Generated</div>
    </div>
    <div class="stat-card" style="text-align:center">
      <div style="font-size:28px;font-weight:800;margin-bottom:4px;color:#4ade80" id="st-saved">—</div>
      <div style="color:rgba(255,255,255,0.4);font-size:12px">Saved to LMS</div>
    </div>
    <div class="stat-card" style="text-align:center">
      <div style="font-size:28px;font-weight:800;margin-bottom:4px;color:#a5b4fc" id="st-courses">—</div>
      <div style="color:rgba(255,255,255,0.4);font-size:12px">Courses</div>
    </div>
    <div class="stat-card" style="text-align:center">
      <div style="font-size:28px;font-weight:800;margin-bottom:4px;color:#d8b4fe" id="st-intern">—</div>
      <div style="color:rgba(255,255,255,0.4);font-size:12px">Internships</div>
    </div>
    <div class="stat-card" style="text-align:center">
      <div style="font-size:28px;font-weight:800;margin-bottom:4px;color:#fbbf24" id="st-month">—</div>
      <div style="color:rgba(255,255,255,0.4);font-size:12px">This Month</div>
    </div>
  </div>

  <!-- History Table -->
  <div class="card">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:space-between">
      <h2 style="font-size:16px;font-weight:700">📋 Recent History</h2>
      <span style="color:rgba(255,255,255,0.35);font-size:13px" id="histCount"></span>
    </div>

    <div id="historyLoading" style="padding:40px;text-align:center;color:rgba(255,255,255,0.3);font-size:14px">
      ⏳ Loading...
    </div>

    <div id="historyTable" style="display:none;overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>TOPIC</th>
            <th>DAYS</th>
            <th>LEVEL</th>
            <th>LANGUAGE</th>
            <th>TYPE</th>
            <th>AI MODEL</th>
            <th>STATUS</th>
            <th>DATE</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody id="historyBody"></tbody>
      </table>
    </div>

    <div id="historyEmpty" style="display:none;padding:60px;text-align:center">
      <div style="font-size:40px;margin-bottom:12px">✨</div>
      <p style="color:rgba(255,255,255,0.4);font-size:14px">Koi course generate nahi hua abhi</p>
      <a href="generate.php" style="color:#fff;font-size:13px;margin-top:10px;display:inline-block;text-decoration:underline">Pehla course generate karo →</a>
    </div>
  </div>

</div>

<script>
async function loadStats() {
  try {
    const r    = await fetch('../api/ai/history.php?action=stats');
    const data = await r.json();
    if (!data.success) return;
    document.getElementById('st-total').textContent   = data.total;
    document.getElementById('st-saved').textContent   = data.saved;
    document.getElementById('st-courses').textContent = data.courses;
    document.getElementById('st-intern').textContent  = data.internships;
    document.getElementById('st-month').textContent   = data.this_month;
  } catch(e) {}
}

async function loadHistory() {
  try {
    const r    = await fetch('../api/ai/history.php?action=list');
    const data = await r.json();

    document.getElementById('historyLoading').style.display = 'none';

    if (!data.success || !data.data.length) {
      document.getElementById('historyEmpty').style.display = 'block';
      return;
    }

    document.getElementById('histCount').textContent      = data.data.length + ' records';
    document.getElementById('historyTable').style.display = 'block';

    const tbody = document.getElementById('historyBody');
    tbody.innerHTML = data.data.map((h, i) => {
      const date    = new Date(h.created_at).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'2-digit' });
      const statusB = h.status === 'saved' ? 'badge-saved' : h.status === 'generating' ? 'badge-generating' : 'badge-failed';
      const typeB   = h.generate_for === 'course' ? 'badge-course' : 'badge-internship';

      const viewBtn = `<button class="btn-view" onclick="viewCourse(${h.id}, '${h.generate_for}', ${h.lms_id || 0}, '${(h.topic||'').replace(/'/g,"\\'")}')">👁 View</button>`;

      const lmsLink = h.lms_id
        ? `<a href="https://internshipadda.com/app/views/admin/${h.generate_for === 'course' ? 'courses/builder' : 'internships/edit'}.php?id=${h.lms_id}" target="_blank" class="btn-sm" style="margin-left:4px">✏️ LMS</a>`
        : '';

      const delBtn = `<button class="btn-del" onclick="deleteCourse(${h.id}, '${(h.topic||'').replace(/'/g,"\\'")}', this)">🗑 Delete</button>`;

      return `<tr>
        <td style="color:rgba(255,255,255,0.3)">${i+1}</td>
        <td style="color:#fff;font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${h.topic}">${h.topic}</td>
        <td>${h.duration_days}d</td>
        <td>${h.level || '—'}</td>
        <td>${h.language || '—'}</td>
        <td><span class="badge ${typeB}">${h.generate_for}</span></td>
        <td style="color:rgba(255,255,255,0.45);font-size:11px;font-family:monospace">${(h.ai_provider||'—').replace('gemini/','')}</td>
        <td><span class="badge ${statusB}">${h.status}</span></td>
        <td style="color:rgba(255,255,255,0.45)">${date}</td>
        <td style="white-space:nowrap">${viewBtn}${lmsLink}${delBtn}</td>
      </tr>`;
    }).join('');

  } catch(e) {
    document.getElementById('historyLoading').textContent = 'Error loading history.';
  }
}

// ── View Course — FIXED ────────────────────────────
function viewCourse(historyId, type, lmsId, topic) {

  // Check karo — kya current session mein ye course hai?
  const rawC = sessionStorage.getItem('ai_course_data');
  const rawM = sessionStorage.getItem('ai_meta');
  if (rawC && rawM) {
    try {
      const meta = JSON.parse(rawM);
      if (meta.history_id == historyId) {
        // Session mein hai — seedha preview pe jao
        window.location.href = 'preview.php';
        return;
      }
    } catch(e) {}
  }

  // LMS ID nahi hai
  if (!lmsId) {
    showModal('⚠️', 'LMS mein Save Nahi',
      'Ye course LMS mein save nahi tha. Dobara generate karo.', false, null);
    document.getElementById('modalCloseBtn').style.display = 'block';
    return;
  }

  // ✅ FIXED — seedha LMS view page pe bhejo — no API call
  if (type === 'course') {
    window.open(`https://internshipadda.com/app/views/admin/courses/view.php?id=${lmsId}`, '_blank');
  } else {
    window.open(`https://internshipadda.com/app/views/admin/internships/view.php?id=${lmsId}`, '_blank');
  }
}

function showModal(icon, title, msg, showActions, lmsUrl) {
  document.getElementById('modalIcon').textContent  = icon;
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalMsg').textContent   = msg;
  document.getElementById('loadingModal').classList.add('open');
  document.getElementById('modalCloseBtn').style.display = 'none';

  const actBox = document.getElementById('modalActions');
  if (showActions && lmsUrl) {
    actBox.style.display = 'flex';
    document.getElementById('modalLmsBtn').href = lmsUrl;
  } else {
    actBox.style.display = 'none';
  }
}

function closeModal() {
  document.getElementById('loadingModal').classList.remove('open');
}

// ── Delete a generated course/history record ───────
async function deleteCourse(id, topic, btn) {
  if (!confirm('"' + topic + '" ko delete karna hai?\nYe action wapas nahi hoga.')) return;
  if (btn) { btn.disabled = true; btn.textContent = '⏳'; }
  try {
    const r = await fetch('delete-history.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: id })
    });
    const txt = await r.text();
    let data;
    try { data = JSON.parse(txt); } catch(e) { data = { success: false, message: txt.substring(0, 150) }; }

    if (data.success) {
      const row = btn ? btn.closest('tr') : null;
      if (row) row.remove();
      loadStats();
      // Agar table khali ho gaya to empty state dikhao
      const left = document.querySelectorAll('#historyBody tr').length;
      document.getElementById('histCount').textContent = left + ' records';
      if (left === 0) {
        document.getElementById('historyTable').style.display = 'none';
        document.getElementById('historyEmpty').style.display = 'block';
      }
    } else {
      alert('Delete fail: ' + (data.message || 'Server ne delete support nahi kiya'));
      if (btn) { btn.disabled = false; btn.textContent = '🗑 Delete'; }
    }
  } catch(e) {
    alert('Delete error: ' + e.message);
    if (btn) { btn.disabled = false; btn.textContent = '🗑 Delete'; }
  }
}

loadStats();
loadHistory();
</script>
</body>
</html>