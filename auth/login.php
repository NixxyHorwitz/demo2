<?php
// auth/login.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$_ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if (!preg_match('/android|iphone|ipad|ipod|mobile/i', $_ua)) { ?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Khusus Mobile</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg, #a34e94 0%, #733066 100%);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;color:#1e293b;}
.box{background:#ffffff;border-radius:24px;padding:40px 24px;max-width:360px;width:100%;text-align:center;box-shadow:0 12px 32px rgba(163,78,148,0.2);}
.icon{width:64px;height:64px;background:#faebf6;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#a34e94;}
.icon svg{width:32px;height:32px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
h2{font-size:1.2rem;font-weight:800;margin-bottom:8px;}
p{font-size:0.85rem;color:#64748b;margin-bottom:24px;line-height:1.6;}
.steps{display:flex;flex-direction:column;gap:12px;text-align:left;background:#f8fafc;padding:16px;border-radius:16px;}
.step{display:flex;align-items:flex-start;gap:12px;font-size:0.8rem;color:#475569;line-height:1.5;}
.sn{width:20px;height:20px;border-radius:50%;background:#a34e94;color:#fff;font-weight:700;font-size:0.7rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
</style></head><body>
<div class="box">
<div class="icon"><svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><path d="M12 18h.01"/></svg></div>
<h2>Khusus Mobile</h2>
<p>Halaman ini hanya dapat diakses melalui browser <strong>smartphone</strong> Anda.</p>
<div class="steps">
<div class="step"><div class="sn">1</div><div>Buka browser di HP/Smartphone kamu</div></div>
<div class="step"><div class="sn">2</div><div>Ketik ulang link halaman web ini</div></div>
<div class="step"><div class="sn">3</div><div>Gunakan Chrome atau Safari untuk hasil terbaik</div></div>
</div></div></body></html>
<?php exit; }

require '../mainconfig.php';
$page_type = 'auth';
$page_name = 'Login';

$query_settings = $model->db_query($db, "*", "settings", "id = 1");
$config_web = $query_settings['rows'] ?? [];

function getUserIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

if (isset($_COOKIE['X_SESSION'])) {
    try {
        $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], ['HS256']);
        $check_user = $model->db_query($db, "*", "users", "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_SESSION']) . "'");
        if ($check_user['count'] !== 1) { logout(); exit(header("Location: " . $_SERVER['PHP_SELF'])); }
        elseif (!hash_equals(hash_hmac('sha256', $check_user['rows']['id'] . $check_user['rows']['x_uniqueid'], $config['hmac']['key']), $jwt->sign)) { logout(); exit(header("Location: " . $_SERVER['PHP_SELF'])); }
        else {
            if (($jwt->exp - time()) < 43200) {
                $uniqueid = time();
                $payload = ["id" => $check_user['rows']['id'], "sign" => hash_hmac('sha256', $check_user['rows']['id'] . $uniqueid, $config['hmac']['key']), "exp" => time() + (86400 * 30)];
                $newJwt = \Firebase\JWT\JWT::encode($payload, $config['jwt']['secret'], 'HS256');
                setcookie('X_SESSION', $newJwt, ['expires' => time() + (86400 * 30), 'path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
                $model->db_update($db, "users", ['x_session' => $newJwt, 'x_uniqueid' => $uniqueid], "id = '".$check_user['rows']['id']."'");
            }
            exit(header("Location: " . base_url()));
        }
    } catch (Exception $e) { logout(); exit(header("Location: " . $_SERVER['PHP_SELF'])); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    if ($_POST['ajax_action'] === 'do_login') {
        $phone = protect(trim($_POST['phone']));
        $phone = preg_replace('/^(?:\+62|62|0)?/', '62', $phone);
        $password = trim($_POST['password']);
        $check_user = $model->db_query($db, "*", "users", "phone = '" . $phone . "'");
        
        if ($check_user['count'] == 1 && password_verify($password, $check_user['rows']['password'])) {
            $uniqueid = time();
            $payload  = ["id" => $check_user['rows']['id'], "sign" => hash_hmac('sha256', $check_user['rows']['id'] . $uniqueid, $config['hmac']['key']), "exp" => time() + (86400 * 30)];
            $createJwt = \Firebase\JWT\JWT::encode($payload, $config['jwt']['secret']);
            setcookie('X_SESSION', $createJwt, ['expires' => time() + (86400 * 30), 'path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
            $model->db_update($db, "users", ['x_session' => $createJwt, 'x_uniqueid' => $uniqueid], "id = '" . $check_user['rows']['id'] . "'");
            $model->db_insert($db, "login_logs", ['user_id' => $check_user['rows']['id'], 'ip_address' => getUserIP(), 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 'created_at' => date('Y-m-d H:i:s')]);
            echo json_encode(['success' => true, 'redirect' => base_url()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nomor HP atau Password salah']);
        }
    }
    exit;
}

$nama_web = htmlspecialchars($config_web['title'] ?? 'P-Force');
$reff     = isset($_GET['reff']) ? htmlspecialchars($_GET['reff']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>Login &bullet; <?= $nama_web ?></title>
<link rel="apple-touch-icon" href="<?= base_url('assets/images/' . ($config_web['web_logo'] ?? '')) ?>">
<link rel="shortcut icon" type="image/x-icon" href="<?= base_url('assets/images/' . ($config_web['web_logo'] ?? '')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* CSS Reset & Variables */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --pri: #C59A25; /* Primary Gold */
  --pri-dark: #9F7A18; /* Dark Gold */
  --bg-top: #6A5311; /* Top area backdrop */
  --bg-bottom: #ffffff;
  --text-dark: #1E293B;
  --text-gray: #64748B;
  --border-color: #E2E8F0;
  --input-bg: #F8FAFC;
}

body {
  background: linear-gradient(135deg, #7D6114 0%, #382B09 100%);
  font-family: 'Poppins', sans-serif;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow-x: hidden;
}

.bg-overlay {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: 
    linear-gradient(115deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 30%),
    linear-gradient(295deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0) 40%);
  pointer-events: none;
  z-index: 0;
}

.top-section {
  padding: 50px 24px 30px;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  z-index: 1;
}

/* Logo Setup */
.logo-wrapper {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 28px;
}

.logo-box {
  width: 64px;
  height: 64px;
  background: #ffffff;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 16px rgba(0,0,0,0.2);
  padding: 8px;
}

.logo-box svg { width: 100%; height: 100%; }

.logo-text {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.logo-text .title {
  font-size: 24px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.1;
  letter-spacing: 0.5px;
}

.logo-text .title span {
  color: #FACC15; /* Bright Gold text */
}

.logo-text .subtitle {
  font-size: 11px;
  font-weight: 600;
  color: rgba(255,255,255,0.8);
  letter-spacing: 1.5px;
  margin-top: 4px;
}

/* Glass UI Badges */
.glass-badges {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 16px;
  padding: 12px 14px;
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.glass-badge {
  display: flex;
  align-items: center;
  gap: 8px;
}

.glass-badge .icon {
  position: relative;
  width: 28px;
  height: 28px;
  border: 1px solid rgba(255,255,255,0.3);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
}

.glass-badge .icon svg {
  width: 14px;
  height: 14px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.glass-badge .icon .dot {
  position: absolute;
  top: -3px;
  right: -3px;
  width: 8px;
  height: 8px;
  background: #FACC15;
  border-radius: 50%;
  border: 2px solid #5C4811;
}

.glass-badge .texts {
  display: flex;
  flex-direction: column;
  color: #ffffff;
}

.glass-badge .texts strong {
  font-size: 10px;
  font-weight: 700;
  line-height: 1.2;
}

.glass-badge .texts span {
  font-size: 9px;
  font-weight: 500;
  opacity: 0.7;
}

/* Base Card UI */
.main-card {
  background: var(--bg-bottom);
  border-top-left-radius: 28px;
  border-top-right-radius: 28px;
  flex: 1;
  padding: 32px 24px 24px;
  box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
  display: flex;
  flex-direction: column;
  z-index: 10;
  position: relative;
}

.greeting {
  margin-bottom: 24px;
}

.greeting h1 {
  font-size: 22px;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 6px;
}

.greeting p {
  font-size: 13px;
  color: var(--text-gray);
  line-height: 1.5;
}

/* Tabs */
.tabs {
  display: flex;
  background: var(--input-bg);
  border-radius: 12px;
  padding: 4px;
  margin-bottom: 24px;
}

.tab {
  flex: 1;
  text-align: center;
  padding: 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: #94A3B8;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.3s;
}

.tab.active {
  background: #ffffff;
  color: var(--text-dark);
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* Form Styling */
.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 10px;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrapper input {
  width: 100%;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 14px 16px;
  font-size: 14px;
  color: var(--text-dark);
  background: #ffffff;
  outline: none;
  transition: all 0.2s;
}

.input-wrapper input:focus {
  border-color: var(--pri);
  box-shadow: 0 0 0 3px rgba(197, 154, 37, 0.1);
}

.input-wrapper input::placeholder {
  color: #94A3B8;
  font-weight: 500;
}

.input-right-icon {
  position: absolute;
  right: 16px;
  cursor: pointer;
  color: #94A3B8;
  display: flex;
  align-items: center;
}

.input-right-icon svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;}

/* Login Button */
.btn-submit {
  width: 100%;
  background: var(--pri);
  color: #ffffff;
  border: none;
  border-radius: 12px;
  padding: 16px;
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  margin-top: 32px;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(197, 154, 37, 0.3);
  transition: transform 0.1s, opacity 0.2s;
  font-family: 'Poppins', sans-serif;
}

.btn-submit:active {
  transform: scale(0.98);
}

.btn-submit .arrow-icon {
  position: absolute;
  right: 16px;
  width: 28px;
  height: 28px;
  background: rgba(255,255,255,0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-submit .arrow-icon svg {
  width: 14px; height: 14px;
  stroke: #ffffff;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.forgot-text {
  display: block;
  text-align: center;
  margin-top: 20px;
  color: var(--text-dark);
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
}

/* Footer Information */
.footer-info {
  margin-top: auto;
  padding-top: 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.footer-line {
  width: 32px;
  height: 3px;
  background: #CBD5E1;
  border-radius: 2px;
  margin-bottom: 20px;
}

.copyright-text {
  font-size: 11px;
  color: #94A3B8;
  line-height: 1.6;
}

.bottom-badges-container {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 16px;
  flex-wrap: wrap;
}

.pill-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #F8FAFC;
  border: 1px solid var(--border-color);
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 600;
  color: var(--text-dark);
}

/* LOADER */
.loader-overlay {
  display: none;
  position: fixed;
  inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
  z-index: 9999; align-items: center; justify-content: center;
}
.loader-overlay.show { display: flex; }
.spinner {
  width: 40px; height: 40px;
  border: 4px solid #fff; border-top-color: transparent;
  border-radius: 50%;
  animation: s-spin 0.8s linear infinite;
}
@keyframes s-spin { to { transform: rotate(360deg); } }

/* SWEETALERT2 THEME — GOLD */
.swal2-container { font-family: 'Poppins', sans-serif !important; z-index: 10000 !important; }
.swal2-popup { border-radius: 20px !important; padding: 0 !important; overflow: hidden !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 20px 60px rgba(197, 154, 37, 0.15) !important; max-width: 380px !important; width: calc(100vw - 32px) !important; }
.swal2-popup::before { content: ''; display: block; width: 100%; height: 4px; background: linear-gradient(90deg, #FACC15, #C59A25, #9F7A18, #FACC15); background-size: 200% 100%; animation: swal-stripe 3s linear infinite; }
@keyframes swal-stripe { 0% { background-position: 0% 0%; } 100% { background-position: 200% 0%; } }
.swal2-icon { margin: 24px auto 8px !important; }
.swal2-icon.swal2-success { border-color: rgba(16, 185, 129, 0.3) !important; color: #10b981 !important; }
.swal2-icon.swal2-success [class^='swal2-success-line'] { background-color: #10b981 !important; }
.swal2-icon.swal2-error { border-color: rgba(239, 68, 68, 0.3) !important; color: #ef4444 !important; }
.swal2-icon.swal2-error [class^='swal2-x-mark-line'] { background-color: #ef4444 !important; }
.swal2-title { font-size: 18px !important; font-weight: 800 !important; color: #0f172a !important; padding: 4px 22px !important; margin: 0 !important; }
.swal2-html-container { font-size: 13.5px !important; font-weight: 500 !important; color: #64748b !important; padding: 6px 22px 20px !important; margin: 0 !important; }
.swal2-actions { padding: 0 20px 24px !important; gap: 10px !important; flex-wrap: nowrap !important; }
.swal2-confirm.swal2-styled { font-size: 14px !important; font-weight: 700 !important; padding: 13px 24px !important; border-radius: 12px !important; background: linear-gradient(135deg, #C59A25, #9F7A18) !important; box-shadow: 0 6px 20px rgba(197, 154, 37, 0.3) !important; }
</style>
</head>
<body>

<div class="bg-overlay"></div>

<div class="top-section">
  
  <div class="logo-wrapper">
    <div class="logo-box">
      <?php if(!empty($config_web['web_logo'])): ?>
        <img src="<?= base_url('assets/images/' . $config_web['web_logo']) ?>" alt="<?= $nama_web ?>" style="width: 100%; height: 100%; object-fit: contain; border-radius: 10px;">
      <?php else: ?>
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
          <rect x="18" y="2" width="22" height="22" transform="rotate(45 18 2)" fill="#FEF3C7" rx="3"/>
          <path d="M10 24L18 10L26 24" stroke="#B45309" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M14 24V16L18 20L22 16V24" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="18" cy="18" r="1.5" fill="#B45309"/>
        </svg>
      <?php endif; ?>
    </div>
    <div class="logo-text">
      <?php
        $site_words = explode(' ', $nama_web);
        $w1 = htmlspecialchars($site_words[0] ?? 'FMB');
        $w2 = htmlspecialchars($site_words[1] ?? 'Finance');
      ?>
      <div class="title"><?= $w1 ?> <span><?= $w2 ?></span></div>
      <div class="subtitle">BANKING & FINANCE</div>
    </div>
  </div>

  <div class="glass-badges">
    <div class="glass-badge">
      <div class="icon">
        <div class="dot"></div>
        <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
      </div>
      <div class="texts">
        <strong>256-bit SSL</strong>
        <span>Encrypted</span>
      </div>
    </div>
    <div class="glass-badge">
      <div class="icon">
        <div class="dot"></div>
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
      </div>
      <div class="texts">
        <strong>OJK Licensed</strong>
        <span>Regulated</span>
      </div>
    </div>
    <div class="glass-badge">
      <div class="icon">
        <div class="dot"></div>
        <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/></svg>
      </div>
      <div class="texts">
        <strong>LPS Insured</strong>
        <span>Guaranteed</span>
      </div>
    </div>
  </div>

</div>

<div class="main-card">
  
  <div class="greeting">
    <h1>Selamat Datang</h1>
    <p>Masuk ke akun <?= $nama_web ?> Anda untuk melanjutkan.</p>
  </div>

  <div class="tabs">
    <a href="javascript:void(0)" class="tab active">Masuk</a>
    <a href="register.php<?= !empty($reff) ? '?reff='.$reff : '' ?>" class="tab">Daftar</a>
  </div>

  <form id="loginForm">
    
    <div class="form-group">
      <div class="form-label">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:2px"><rect x="3" y="5" width="18" height="14" rx="3" fill="#60A5FA"/><circle cx="8" cy="12" r="3" fill="#DBEAFE"/><path d="M14 10H19M14 14H17" stroke="#DBEAFE" stroke-width="2" stroke-linecap="round"/></svg>
        Nomor Ponsel
      </div>
      <div class="input-wrapper">
        <input type="tel" id="phone" placeholder="0812 3456 7890" autocomplete="tel" required>
      </div>
    </div>

    <div class="form-group">
      <div class="form-label">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:2px"><rect x="5" y="10" width="14" height="12" rx="3" fill="#FBBF24"/><path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="#FBBF24" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="16" r="2" fill="#92400E"/></svg>
        Kata Sandi
      </div>
      <div class="input-wrapper">
        <input type="password" id="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
        <div class="input-right-icon" id="togglePassword">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8v0z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      Masuk ke Akun
      <div class="arrow-icon">
        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </div>
    </button>

  </form>

  <div class="footer-info">
    <div class="footer-line"></div>
    <div class="copyright-text">
      &copy; <?= date('Y') ?> <?= $nama_web ?>. All rights reserved.<br>Terlisensi dan diatur oleh OJK &bull; Terjamin oleh LPS
    </div>
    
    <div class="bottom-badges-container">
      <div class="pill-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="12" rx="3" fill="#F59E0B"/><path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/></svg>
        256-bit SSL
      </div>
      <div class="pill-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="#3B82F6"/></svg>
        OJK
      </div>
      <div class="pill-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 21h18v-2H3v2zm9-18L3 8v2h18V8l-9-5zm-6 9h2v7H6v-7zm4 0h2v7h-2v-7zm4 0h2v7h-2v-7z" fill="#64748B"/></svg>
        LPS
      </div>
    </div>
  </div>

</div>

<div class="loader-overlay" id="loadingOv">
  <div class="spinner"></div>
</div>

<script>
// Toggle Password Visibility
$('#togglePassword').on('click', function() {
  const input = $('#password');
  const type = input.attr('type') === 'password' ? 'text' : 'password';
  input.attr('type', type);
  if(type === 'text') {
    $(this).html('<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>');
  } else {
    $(this).html('<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8v0z"/><circle cx="12" cy="12" r="3"/></svg>');
  }
});

/* AJAX LOGIN SUBMISSION */
$('#loginForm').on('submit', function(e) {
  e.preventDefault();
  
  const phone = $.trim($('#phone').val());
  const password = $.trim($('#password').val());
  
  if (!phone || !password) {
    Swal.fire({
      icon: 'warning',
      title: 'Perhatian',
      text: 'Silakan isi nomor telepon dan password.',
      confirmButtonColor: '#C59A25'
    });
    return;
  }
  
  $('#loadingOv').addClass('show');
  
  // Submit btn state
  $('#submitBtn').prop('disabled', true).html('Memproses... <div class="arrow-icon"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>');
  
  $.ajax({
    url: window.location.pathname,
    type: 'POST',
    data: {
      ajax_action: 'do_login',
      phone: phone,
      password: password
    },
    dataType: 'json',
    success: function(res) {
      if (res.success) {
        window.location.href = res.redirect || '/';
      } else {
        $('#loadingOv').removeClass('show');
        $('#submitBtn').prop('disabled', false).html('Masuk ke Akun <div class="arrow-icon"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>');
        
        Swal.fire({
          icon: 'error',
          title: 'Gagal Masuk',
          text: res.message || 'Nomor telepon atau Password salah',
          confirmButtonColor: '#C59A25'
        });
      }
    },
    error: function() {
      $('#loadingOv').removeClass('show');
      $('#submitBtn').prop('disabled', false).html('Masuk ke Akun <div class="arrow-icon"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>');
      Swal.fire({
        icon: 'error',
        title: 'Error Jaringan',
        text: 'Gagal terhubung ke server. Silakan coba lagi.',
        confirmButtonColor: '#C59A25'
      });
    }
  });
});
</script>

</body>
</html>