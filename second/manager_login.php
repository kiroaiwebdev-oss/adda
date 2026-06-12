<?php
require_once __DIR__ . '/db.php';
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = getDB()->prepare(
            "SELECT id, name, password, role FROM users 
             WHERE email = ? AND role IN ('admin','manager') AND status = 'active'"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            unset($_SESSION['manager_permissions']); // fresh load
            header('Location: manager_dashboard.php'); exit;
        }
    }
    $error = 'Invalid email / password ya access nahi hai.';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manager Login — InternshipAdda</title>
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{--bg:#f7f6f2;--surface:#fff;--border:#d4d1ca;--text:#28251d;--muted:#7a7974;--primary:#16a34a;--primary-h:#15803d;--error:#a12c7b;--radius:0.75rem;--shadow:0 4px 24px oklch(0.2 0.01 80/0.10)}
[data-theme="dark"]{--bg:#171614;--surface:#1c1b19;--border:#393836;--text:#cdccca;--muted:#797876;--primary:#4ade80;--primary-h:#22c55e;--error:#d163a7}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh;display:flex;align-items:center;justify-content:center}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:2.5rem;width:100%;max-width:420px;box-shadow:var(--shadow)}
.logo{display:flex;align-items:center;gap:0.75rem;margin-bottom:2rem}
.logo svg{width:36px;height:36px}
.logo-text{font-weight:700;font-size:1.1rem;color:var(--text)}
.logo-text span{color:var(--primary)}
h1{font-size:1.4rem;font-weight:700;margin-bottom:0.4rem}
.subtitle{color:var(--muted);font-size:0.9rem;margin-bottom:1.8rem}
label{display:block;font-size:0.85rem;font-weight:500;margin-bottom:0.4rem;color:var(--text)}
input[type=email],input[type=password]{width:100%;padding:0.7rem 1rem;border:1.5px solid var(--border);border-radius:0.5rem;background:var(--bg);color:var(--text);font-family:inherit;font-size:0.95rem;transition:border-color 180ms;margin-bottom:1.1rem}
input:focus{outline:none;border-color:var(--primary)}
.btn{width:100%;padding:0.8rem;background:var(--primary);color:#fff;border:none;border-radius:0.5rem;font-family:inherit;font-size:0.95rem;font-weight:600;cursor:pointer;transition:background 180ms}
.btn:hover{background:var(--primary-h)}
.error-box{background:oklch(from var(--error) l c h/0.1);border:1px solid oklch(from var(--error) l c h/0.3);color:var(--error);padding:0.75rem 1rem;border-radius:0.5rem;font-size:0.875rem;margin-bottom:1.2rem}
.badge{display:inline-flex;align-items:center;gap:0.4rem;background:oklch(from var(--primary) l c h/0.12);color:var(--primary);padding:0.3rem 0.75rem;border-radius:9999px;font-size:0.78rem;font-weight:600;margin-bottom:1.5rem}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <svg viewBox="0 0 36 36" fill="none">
      <rect width="36" height="36" rx="8" fill="var(--primary)"/>
      <path d="M10 26L18 10L26 26" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M13 21h10" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <div class="logo-text">Internship<span>Adda</span></div>
  </div>
  <div class="badge">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>
    Manager Portal
  </div>
  <h1>Welcome back</h1>
  <p class="subtitle">Platform manage karne ke liye login karein</p>
  <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['error']) && $_GET['error']==='unauthorized'): ?>
    <div class="error-box">Access denied. Manager/Admin account required.</div>
  <?php endif; ?>
  <form method="POST" novalidate>
    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" placeholder="manager@example.com" required
           value="<?= htmlspecialchars($_POST['email']??'') ?>">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" placeholder="••••••••" required>
    <button type="submit" class="btn">Login to Manager Panel</button>
  </form>
</div>
</body>
</html>