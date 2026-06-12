<?php
/**
 * Shared CSS tokens + sidebar/topbar/table styles for manager panel pages.
 * Include inside <head>.
 */
?>
<link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
<link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,600,700&display=swap" rel="stylesheet">
<style>
:root,[data-theme="light"]{
  --bg:#f7f6f2;--surface:#fff;--surface-2:#fbfbf9;
  --border:oklch(0.2 0.01 80/0.12);--divider:#dcd9d5;
  --text:#28251d;--muted:#7a7974;--faint:#bab9b4;
  --primary:#01696f;--primary-h:#0c4e54;--primary-hl:#cedcd8;
  --success:#437a22;--warning:#964219;--error:#a12c7b;--orange:#da7101;
  --r-sm:.375rem;--r-md:.5rem;--r-lg:.75rem;--r-xl:1rem;
  --shadow-sm:0 1px 2px oklch(0.2 0.01 80/0.06);
  --shadow-md:0 4px 12px oklch(0.2 0.01 80/0.08);
  --t:180ms cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#171614;--surface:#1c1b19;--surface-2:#201f1d;
  --border:oklch(1 0 0/0.08);--divider:#262523;
  --text:#cdccca;--muted:#797876;--faint:#5a5957;
  --primary:#4f98a3;--primary-h:#227f8b;--primary-hl:#313b3b;
  --success:#6daa45;--warning:#bb653b;--error:#d163a7;--orange:#fdab43;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Satoshi',sans-serif;background:var(--bg);color:var(--text);min-height:100dvh;display:flex}
a{color:inherit;text-decoration:none}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit}
input,select,textarea{font:inherit;color:inherit}

/* SIDEBAR */
.sidebar{width:240px;min-height:100dvh;background:var(--surface);border-right:1px solid var(--divider);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100}
.sidebar-logo{padding:1.25rem 1.25rem 1rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;gap:.75rem}
.sidebar-logo svg{width:32px;height:32px;flex-shrink:0}
.logo-text{font-weight:700;font-size:.95rem}.logo-text span{color:var(--primary)}
.sidebar-user{padding:.9rem 1.25rem;border-bottom:1px solid var(--divider)}
.user-badge{font-size:.72rem;font-weight:600;background:oklch(from var(--primary) l c h/0.12);color:var(--primary);padding:.2rem .6rem;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em;display:inline-block;margin-bottom:.35rem}
.user-name{font-weight:600;font-size:.9rem}
.user-email-sm{font-size:.75rem;color:var(--muted)}
nav{flex:1;padding:.75rem 0;overflow-y:auto}
.nav-section{padding:.75rem 1.25rem .25rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint)}
.nav-item{display:flex;align-items:center;gap:.7rem;padding:.55rem 1.25rem;font-size:.875rem;color:var(--muted);transition:color var(--t),background var(--t);position:relative}
.nav-item:hover{background:var(--bg);color:var(--text)}
.nav-item.active{background:oklch(from var(--primary) l c h/0.1);color:var(--primary);font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--primary);border-radius:0 4px 4px 0}
.nav-item svg{width:16px;height:16px;flex-shrink:0;opacity:.7}
.nav-item.active svg,.nav-item:hover svg{opacity:1}
.nav-badge{margin-left:auto;font-size:.7rem;background:var(--orange);color:#fff;padding:.1rem .45rem;border-radius:9999px;font-weight:700}
.sidebar-footer{padding:1rem 1.25rem;border-top:1px solid var(--divider);display:flex;gap:.5rem;align-items:center}
.btn-sm{padding:.45rem .9rem;font-size:.8rem;border-radius:var(--r-md);font-weight:500;transition:background var(--t),color var(--t);display:inline-flex;align-items:center;gap:.3rem}
.btn-ghost{border:1px solid var(--border);color:var(--muted)}.btn-ghost:hover{background:var(--bg);color:var(--text)}
.btn-danger{background:oklch(from var(--error) l c h/0.1);color:var(--error)}.btn-danger:hover{background:oklch(from var(--error) l c h/0.18)}

/* MAIN */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100dvh}
.topbar{height:56px;background:var(--surface);border-bottom:1px solid var(--divider);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;position:sticky;top:0;z-index:50}
.breadcrumb{font-size:.78rem;color:var(--muted);flex:1}
.breadcrumb span{color:var(--text);font-weight:500}
.content{padding:1.5rem;flex:1}

/* PAGE HEADER */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
.page-header h1{font-size:1.4rem;font-weight:700}
.page-header p{font-size:.85rem;color:var(--muted);margin-top:.25rem}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem}
.kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:1.1rem 1.25rem;box-shadow:var(--shadow-sm)}
.kpi-label{font-size:.78rem;color:var(--muted);font-weight:500;margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem}
.kpi-label svg{width:14px;height:14px}
.kpi-value{font-size:1.65rem;font-weight:700;line-height:1;font-variant-numeric:tabular-nums}
.kpi-sub{font-size:.75rem;color:var(--muted);margin-top:.3rem}
.kpi-card.accent{background:var(--primary);border-color:var(--primary)}
.kpi-card.accent .kpi-label,.kpi-card.accent .kpi-value,.kpi-card.accent .kpi-sub{color:#fff}

/* GRID */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:900px){.grid-2{grid-template-columns:1fr}}

/* CARD / SECTION */
.section-card,.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm)}
.section-head{padding:.9rem 1.25rem;border-bottom:1px solid var(--divider);display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap}
.section-head h2{font-size:.9rem;font-weight:700}
.section-head a{font-size:.8rem;color:var(--primary);font-weight:500}
.section-head a:hover{text-decoration:underline}

/* TABLE */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{padding:.65rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--divider);white-space:nowrap}
td{padding:.75rem 1rem;font-size:.85rem;border-bottom:1px solid var(--divider);vertical-align:top}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--bg)}

/* BADGES */
.badge,.status-badge{display:inline-flex;align-items:center;padding:.18rem .6rem;border-radius:9999px;font-size:.72rem;font-weight:600;white-space:nowrap}
.badge-success,.status-published,.status-active,.status-approved,.status-completed{background:oklch(from var(--success) l c h/0.12);color:var(--success)}
.badge-muted,.status-draft,.status-inactive{background:oklch(from var(--muted) l c h/0.15);color:var(--muted)}
.badge-warning,.status-pending,.status-applied{background:oklch(from var(--orange) l c h/0.15);color:var(--orange)}
.badge-error,.status-rejected,.status-failed{background:oklch(from var(--error) l c h/0.12);color:var(--error)}
.badge-info,.status-contacted{background:oklch(from var(--primary) l c h/0.12);color:var(--primary)}

/* FORM */
.input,input[type=text],input[type=email],input[type=number],input[type=date],input[type=password],select,textarea{
  width:100%;padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);
  background:var(--surface);font-size:.875rem;transition:border-color var(--t)
}
.input:focus,input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary)}
.label{display:block;font-size:.78rem;font-weight:600;color:var(--text);margin-bottom:.35rem}
.field{margin-bottom:1rem}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:720px){.field-row{grid-template-columns:1fr}}

/* BUTTONS */
.btn{padding:.55rem 1rem;border-radius:var(--r-md);font:inherit;font-size:.85rem;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:.4rem;transition:all var(--t);text-decoration:none}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-h)}
.btn-outline{background:transparent;border:1.5px solid var(--border);color:var(--text)}
.btn-outline:hover{background:var(--bg);border-color:var(--primary);color:var(--primary)}
.btn-error{background:oklch(from var(--error) l c h/0.1);color:var(--error)}
.btn-error:hover{background:oklch(from var(--error) l c h/0.18)}

/* ALERTS */
.alert{padding:.7rem 1rem;border-radius:var(--r-md);font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-success{background:oklch(from var(--success) l c h/0.1);border:1px solid oklch(from var(--success) l c h/0.3);color:var(--success)}
.alert-error{background:oklch(from var(--error) l c h/0.1);border:1px solid oklch(from var(--error) l c h/0.3);color:var(--error)}
.alert-warn{background:oklch(from var(--orange) l c h/0.08);border:1px solid oklch(from var(--orange) l c h/0.3);color:var(--orange)}
.alert-info{background:oklch(from var(--primary) l c h/0.07);border:1px solid oklch(from var(--primary) l c h/0.2);color:var(--primary)}

/* FILTER BAR */
.filter-bar{display:flex;gap:.6rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center}
.filter-bar select,.filter-bar input{padding:.5rem .9rem;border:1.5px solid var(--border);border-radius:var(--r-md);background:var(--surface);font:inherit;font-size:.85rem;color:var(--text);width:auto}

/* EMPTY STATE */
.empty-state{padding:3rem 2rem;text-align:center;color:var(--muted)}
.empty-state-icon{font-size:2.5rem;opacity:.5;margin-bottom:.75rem}

/* MOBILE */
.mobile-menu-btn{display:none;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--r-md);color:var(--muted);flex-shrink:0}
.mobile-menu-btn:hover{background:var(--bg)}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);transition:transform .3s ease}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .kpi-grid{grid-template-columns:1fr 1fr}
  .mobile-menu-btn{display:flex}
}
@media(max-width:480px){
  .kpi-grid{grid-template-columns:1fr}
}
</style>
