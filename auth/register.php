<?php
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
$page_name = 'Register';
require '../lib/flash_message.php';

$query_settings = $model->db_query($db, "*", "settings", "id = 1");
$config_web = $query_settings['rows'];

if (isset($_COOKIE['X_SESSION'])) {
    try {
        $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], ['HS256']);
        $check_user = $model->db_query($db, "*", "users", "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_SESSION']) . "'");
        if ($check_user['count'] === 1) exit(header("Location: " . base_url()));
    } catch (Exception $e) {}
}

$reff = '';
$reff_locked = false;
if (isset($_GET['reff'])) {
    $check_reff = $model->db_query($db, "*", "users", "id = '" . protect($_GET['reff']) . "'");
    if ($check_reff['count'] == 1) {
        $reff = $check_reff['rows']['id'];
        $_SESSION['reff'] = $reff;
        $reff_locked = true;
    }
}
if (empty($reff) && isset($_SESSION['reff'])) {
    $reff = $_SESSION['reff'];
    $reff_locked = true;
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']); return trim($ipList[0]); }
    else return $_SERVER['REMOTE_ADDR'];
}

function autoLogin($db, $model, $config, $user_id) {
    $check_user = $model->db_query($db, "*", "users", "id = '" . $user_id . "'");
    if ($check_user['count'] == 1) {
        $uniqueid = time();
        $payload = [
            "id"   => $check_user['rows']['id'],
            "sign" => hash_hmac('sha256', $check_user['rows']['id'] . $uniqueid, $config['hmac']['key']),
            "exp"  => time() + \Firebase\JWT\JWT::$leeway + 86400
        ];
        $createJwt = \Firebase\JWT\JWT::encode($payload, $config['jwt']['secret']);
        setcookie('X_SESSION', $createJwt, ['expires' => time() + 86400, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
        $model->db_update($db, "users", ['x_session' => $createJwt, 'x_uniqueid' => $uniqueid], "id = '" . $check_user['rows']['id'] . "'");
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    if ($_POST['ajax_action'] === 'do_register') {
        $username = protect(trim($_POST['username'] ?? ''));
        $phone    = protect(trim($_POST['phone'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        if (empty($username)) { echo json_encode(['success'=>false,'message'=>'Nama pengguna tidak boleh kosong']); exit; }
        if (strlen($username) < 3 || strlen($username) > 50) { echo json_encode(['success'=>false,'message'=>'Nama pengguna harus 3–50 karakter']); exit; }
        if (!preg_match('/^62[0-9]{8,13}$/', $phone)) { echo json_encode(['success'=>false,'message'=>'Nomor harus diawali 62 dan valid']); exit; }
        if (strlen($password) < 6) { echo json_encode(['success'=>false,'message'=>'Kata sandi minimal 6 karakter']); exit; }

        $raw_refferal = isset($_POST['refferal']) ? protect(trim($_POST['refferal'])) : '';
        $refferal = '';
        if (!empty($raw_refferal)) {
            $check_ref = $model->db_query($db, "id", "users", "x_uniqueid = '" . $raw_refferal . "'");
            if ($check_ref['count'] == 1) { $refferal = $check_ref['rows']['id']; }
            else {
                $check_ref2 = $model->db_query($db, "id", "users", "id = '" . $raw_refferal . "'");
                if ($check_ref2['count'] == 1) $refferal = $check_ref2['rows']['id'];
            }
        }

        $user_ip = getUserIP();
        $check_ip = $model->db_query($db, "id", "users", "ip_address = '" . $user_ip . "'");
        if ($check_ip['count'] >= 10) { echo json_encode(['success'=>false,'message'=>'Maksimal 10 akun per perangkat']); exit; }

        $check_phone = $model->db_query($db, "id", "users", "phone = '" . $phone . "'");
        if ($check_phone['count'] > 0) { echo json_encode(['success'=>false,'message'=>'Nomor telepon sudah terdaftar']); exit; }

        $check_uname = $model->db_query($db, "id", "users", "username = '" . $username . "'");
        if ($check_uname['count'] > 0) { echo json_encode(['success'=>false,'message'=>'Nama pengguna sudah digunakan, coba yang lain']); exit; }

        $input_datauser = [
            'username'   => $username,
            'phone'      => $phone,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'saldo'      => '0',
            'point'      => '0',
            'profit'     => '0',
            'status'     => 'Active',
            'created_at' => date('Y-m-d H:i:s'),
            'ip_address' => $user_ip,
            'wd_product_id' => '0',
        ];
        if (!empty($refferal)) $input_datauser['uplink'] = $refferal;

        if ($model->db_insert($db, "users", $input_datauser)) {
            $new_user_id = mysqli_insert_id($db);
            if (autoLogin($db, $model, $config, $new_user_id)) {
                echo json_encode(['success' => true, 'redirect' => base_url()]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Login otomatis gagal, silakan login manual']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Pendaftaran gagal, coba lagi']);
        }
        exit;
    }
}

$nama_web = htmlspecialchars($config_web['title'] ?? 'Website');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>Register &bullet; <?= $nama_web ?></title>
<link rel="apple-touch-icon" href="<?= base_url('assets/images/' . ($config_web['web_logo'] ?? '')) ?>">
<link rel="shortcut icon" type="image/x-icon" href="<?= base_url('assets/images/' . ($config_web['web_logo'] ?? '')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700;1,800&display=swap" rel="stylesheet">
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
  color: #FACC15;
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
  width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

.glass-badge .icon .dot {
  position: absolute;
  top: -3px; right: -3px;
  width: 8px; height: 8px;
  background: #FACC15;
  border-radius: 50%;
  border: 2px solid #5C4811;
}

.glass-badge .texts {
  display: flex; flex-direction: column; color: #ffffff;
}

.glass-badge .texts strong {
  font-size: 10px; font-weight: 700; line-height: 1.2;
}

.glass-badge .texts span {
  font-size: 9px; font-weight: 500; opacity: 0.7;
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
  font-size: 22px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;
}

.greeting p {
  font-size: 13px; color: var(--text-gray); line-height: 1.5;
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

.form-label span.optional {
  font-size: 11px;
  font-weight: 500;
  color: #94A3B8;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrapper.readonly input {
  background: #F1F5F9;
  color: var(--text-gray);
  cursor: not-allowed;
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

.pw-strength {
  display: flex;
  gap: 6px;
  margin-top: 10px;
  margin-bottom: -4px;
}

.pw-strength span {
  flex: 1;
  height: 3px;
  background: #E2E8F0;
  border-radius: 2px;
  transition: background 0.3s;
}

.pw-strength span.active {
  background: var(--pri);
}

/* Captcha Module */
.captcha-box-wrapper {
  border: 1.5px dashed var(--border-color);
  background: #FAFAFA;
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.captcha-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 700;
  color: #94A3B8;
  letter-spacing: 1px;
  margin-bottom: 12px;
}

.captcha-question {
  font-size: 22px;
  font-weight: 800;
  color: var(--text-dark);
  margin-bottom: 16px;
  letter-spacing: 2px;
}

.captcha-question span {
  color: var(--pri-dark);
}

.captcha-input-field {
  width: 100%;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 12px;
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  color: var(--text-dark);
  background: #ffffff;
  outline: none;
  letter-spacing: 4px;
}

.captcha-input-field:focus {
  border-color: var(--pri);
  box-shadow: 0 0 0 3px rgba(197, 154, 37, 0.1);
}

.captcha-input-field::placeholder {
  color: #CBD5E1;
  letter-spacing: 4px;
}

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
  margin-top: 8px;
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
  width: 14px; height: 14px; stroke: #ffffff; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

.disclaimer-text {
  text-align: center;
  margin-top: 24px;
  font-size: 11px;
  color: #94A3B8;
  line-height: 1.6;
}

.disclaimer-text a {
  color: var(--pri-dark);
  font-weight: 700;
  text-decoration: none;
}

/* Footer Information */
.footer-info {
  margin-top: auto;
  padding-top: 24px;
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
    <h1>Buat Akun Baru</h1>
    <p>Isi data berikut untuk memulai perjalanan finansial Anda.</p>
  </div>

  <div class="tabs">
    <a href="login.php<?= !empty($reff) ? '?reff='.htmlspecialchars($reff) : '' ?>" class="tab">Masuk</a>
    <a href="javascript:void(0)" class="tab active">Daftar</a>
  </div>

  <form id="registerForm">
    
    <!-- Username -->
    <div class="form-group">
      <div class="form-label">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:2px"><rect x="3" y="5" width="18" height="14" rx="3" fill="#60A5FA"/><circle cx="12" cy="12" r="4" fill="#DBEAFE"/></svg>
        Username 
      </div>
      <div class="input-wrapper">
        <input type="text" id="username" name="username" placeholder="Buat nama pengguna" autocomplete="username" required>
      </div>
    </div>

    <!-- Nomor Ponsel -->
    <div class="form-group">
      <div class="form-label">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:2px"><rect x="3" y="5" width="18" height="14" rx="3" fill="#60A5FA"/><circle cx="8" cy="12" r="3" fill="#DBEAFE"/><path d="M14 10H19M14 14H17" stroke="#DBEAFE" stroke-width="2" stroke-linecap="round"/></svg>
        Nomor Ponsel
      </div>
      <div class="input-wrapper">
        <input type="tel" id="phone" name="phone" placeholder="0812 3456 7890" autocomplete="tel" required>
      </div>
    </div>

    <!-- Kata Sandi -->
    <div class="form-group">
      <div class="form-label">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:2px"><rect x="5" y="10" width="14" height="12" rx="3" fill="#FBBF24"/><path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="#FBBF24" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="16" r="2" fill="#92400E"/></svg>
        Kata Sandi
      </div>
      <div class="input-wrapper">
        <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
        <div class="input-right-icon" onclick="toggleVis('password', this)">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8v0z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
      </div>
      <div class="pw-strength" id="pwIndicator">
        <span></span><span></span><span></span><span></span>
      </div>
    </div>

    <!-- Konfirmasi Kata Sandi -->
    <div class="form-group">
      <div class="form-label">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:2px"><rect x="5" y="10" width="14" height="12" rx="3" fill="#FBBF24"/><path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="#FBBF24" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="16" r="2" fill="#92400E"/></svg>
        Konfirmasi Kata Sandi
      </div>
      <div class="input-wrapper">
        <input type="password" id="password_confirm" name="password_confirm" placeholder="Masukkan ulang kata sandi" required>
        <div class="input-right-icon" onclick="toggleVis('password_confirm', this)">
           <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8v0z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
      </div>
    </div>

    <!-- Kode Undangan -->
    <div class="form-group">
      <div class="form-label">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:2px"><rect x="4" y="9" width="16" height="11" rx="2" fill="#EF4444"/><rect x="10" y="5" width="4" height="4" rx="1" fill="#FCA5A5"/><path d="M4 14.5L12 17L20 14.5" stroke="#FCA5A5" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="20" stroke="#FCA5A5" stroke-width="2"/></svg>
        Kode Undangan <span class="optional">(Opsional)</span>
      </div>
      <div class="input-wrapper <?= $reff_locked ? 'readonly' : '' ?>">
        <input type="text" id="refferal" name="refferal" value="<?= htmlspecialchars($reff) ?>" <?= $reff_locked ? 'readonly' : '' ?> placeholder="Masukkan kode undangan">
      </div>
    </div>

    <!-- Security Verif box -->
    <div class="captcha-box-wrapper">
      <div class="captcha-label">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="2" fill="#FCA5A5"/><path d="M7 8h10M7 12h10M7 16h5" stroke="#991B1B" stroke-width="2" stroke-linecap="round"/></svg> 
        VERIFIKASI KEAMANAN
      </div>
      <div class="captcha-question" id="captchaQuestion">? <span>+</span> ? <span>=</span> ?</div>
      <input type="number" inputmode="numeric" id="captchaInput" class="captcha-input-field" placeholder="• •" required>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      Daftar Sekarang
      <div class="arrow-icon">
        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </div>
    </button>

  </form>

  <div class="disclaimer-text">
    Dengan mendaftar, Anda menyetujui<br>
    <a href="#">Syarat & Ketentuan</a> serta <a href="#">Kebijakan Privasi</a>
  </div>

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
/* Toggle Password Visibility */
function toggleVis(inputId, el) {
  const input = $('#' + inputId);
  const type = input.attr('type') === 'password' ? 'text' : 'password';
  input.attr('type', type);
  if(type === 'text') {
    $(el).html('<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>');
  } else {
    $(el).html('<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8v0z"/><circle cx="12" cy="12" r="3"/></svg>');
  }
}

/* Password Strength Indicator */
$('#password').on('input', function() {
  const val = $(this).val();
  const spans = $('#pwIndicator span');
  spans.removeClass('active');
  if(val.length > 0) spans.eq(0).addClass('active');
  if(val.length > 5) spans.eq(1).addClass('active');
  if(val.length > 5 && /\d/.test(val)) spans.eq(2).addClass('active');
  if(val.length > 7 && /\d/.test(val) && /[a-zA-Z]/.test(val)) spans.eq(3).addClass('active');
});

/* Math Captcha Verification */
let currentCaptcha = 0;

function generateCaptcha() {
  const num1 = Math.floor(Math.random() * 10) + 1;
  const num2 = Math.floor(Math.random() * 10) + 1;
  currentCaptcha = num1 + num2;
  
  $('#captchaQuestion').html(`${num1} <span>+</span> ${num2} <span>=</span> ?`);
  $('#captchaInput').val('');
}

document.addEventListener('DOMContentLoaded', () => {
    generateCaptcha();
});

/* AJAX REGISTER */
$('#registerForm').on('submit', function(e) {
  e.preventDefault();

  const username = $.trim($('#username').val());
  const phone    = $.trim($('#phone').val());
  const password = $.trim($('#password').val());
  const confirmP = $.trim($('#password_confirm').val());
  const refferal = $.trim($('#refferal').val());
  const captchaInput = parseInt($.trim($('#captchaInput').val()), 10);

  if (!username || !phone || !password || !confirmP || isNaN(captchaInput)) {
    Swal.fire({
      icon: 'warning',
      title: 'Perhatian',
      text: 'Silakan lengkapi semua baris yang wajib diisi.',
      confirmButtonColor: '#C59A25'
    });
    return;
  }
  
  if (password !== confirmP) {
    Swal.fire({
      icon: 'warning',
      title: 'Password Berbeda',
      text: 'Ulangi password tidak cocok.',
      confirmButtonColor: '#C59A25'
    });
    return;
  }

  if (username.length < 3 || username.length > 50) {
    Swal.fire({
      icon: 'warning',
      title: 'Username Tidak Valid',
      text: 'Username harus antara 3 hingga 50 karakter.',
      confirmButtonColor: '#C59A25'
    });
    return;
  }
  
  if (password.length < 6) {
    Swal.fire({
      icon: 'warning',
      title: 'Password Terlalu Pendek',
      text: 'Minimal 6 karakter.',
      confirmButtonColor: '#C59A25'
    });
    return;
  }
  
  if (captchaInput !== currentCaptcha) {
    Swal.fire({
      icon: 'error',
      title: 'Perhitungan Salah',
      text: 'Hasil verifikasi keamanan tidak tepat. Silakan coba lagi.',
      confirmButtonColor: '#C59A25'
    });
    generateCaptcha();
    return;
  }

  $('#loadingOv').addClass('show');
  $('#submitBtn').prop('disabled', true).html('Memproses... <div class="arrow-icon"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>');

  // Format phone: strip leading 0, add 62
  let phoneNum = phone.replace(/\D/g, '');
  if (phoneNum.startsWith('0')) phoneNum = phoneNum.substring(1);
  if (!phoneNum.startsWith('62')) phoneNum = '62' + phoneNum;

  $.ajax({
    url: window.location.pathname,
    type: 'POST',
    data: {
      ajax_action: 'do_register',
      phone: phoneNum,
      password: password,
      username: username,
      refferal: refferal
    },
    dataType: 'json',
    success: function(res) {
      $('#loadingOv').removeClass('show');
      if (res.success) {
        Swal.fire({
          icon: 'success', title: 'Pendaftaran Berhasil!',
          text: 'Selamat datang! Akun kamu sudah dibuat 🎉',
          confirmButtonColor:'#C59A25',
          timer: 2000, showConfirmButton: false
        }).then(function() {
          window.location.href = res.redirect || '/';
        });
      } else {
        $('#submitBtn').prop('disabled', false).html('Daftar Sekarang <div class="arrow-icon"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>');
        Swal.fire({ icon:'error', title:'Gagal Daftar',
          text: res.message || 'Terjadi kesalahan, coba lagi.',
          confirmButtonColor:'#C59A25' });
        generateCaptcha();
      }
    },
    error: function() {
      $('#loadingOv').removeClass('show');
      $('#submitBtn').prop('disabled', false).html('Daftar Sekarang <div class="arrow-icon"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>');
      Swal.fire({ icon:'error', title:'Koneksi Error',
        text:'Gagal menghubungi server. Periksa koneksi internet.',
        confirmButtonColor:'#C59A25' });
      generateCaptcha();
    }
  });
});
</script>

</body>
</html>