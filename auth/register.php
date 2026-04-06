<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$_ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if (!preg_match('/android|iphone|ipad|ipod|mobile/i', $_ua)) { ?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Khusus Mobile</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#012b26;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;color:#fff;}
.box{background:#023e35;border:1px solid #facc15;border-radius:24px;padding:40px 24px;max-width:360px;width:100%;text-align:center;box-shadow:0 12px 32px rgba(0,0,0,0.5);}
.icon{width:64px;height:64px;background:rgba(250, 204, 21, 0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#facc15;}
.icon i{font-size:32px;}
h2{font-size:1.2rem;font-weight:800;margin-bottom:8px;color:#facc15;}
p{font-size:0.85rem;color:rgba(255,255,255,0.7);margin-bottom:24px;line-height:1.6;}
.steps{display:flex;flex-direction:column;gap:12px;text-align:left;background:rgba(255,255,255,0.05);padding:16px;border-radius:16px;border:1px solid rgba(255,255,255,0.05);}
.step{display:flex;align-items:flex-start;gap:12px;font-size:0.8rem;color:rgba(255,255,255,0.8);line-height:1.5;}
.sn{width:20px;height:20px;border-radius:50%;background:#facc15;color:#012b26;font-weight:800;font-size:0.7rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
</style></head><body>
<div class="box">
<div class="icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
<h2>Hanya Lewat HP</h2>
<p>Platform investasi ini bersifat mobile-first dan hanya bisa diakses via <strong>smartphone</strong>.</p>
<div class="steps">
<div class="step"><div class="sn">1</div><div>Buka browser di HP/Smartphone kamu</div></div>
<div class="step"><div class="sn">2</div><div>Ketik ulang link halaman web ini</div></div>
<div class="step"><div class="sn">3</div><div>Silakan daftar / login dari sana</div></div>
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
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: #f1f5f9;
  font-family: 'Poppins', sans-serif;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow-x: hidden;
  align-items: center;
}

/* TOP BG (Dark Green) */
.top-bg {
  position: absolute; top: 0; left: 0; right: 0; height: 35vh;
  background: linear-gradient(135deg, #023e35 0%, #012b26 100%);
  border-bottom-left-radius: 40px; border-bottom-right-radius: 40px;
  z-index: 0;
}

.content-wrap {
  position: relative; z-index: 1; max-width: 480px; width: 100%;
  display: flex; flex-direction: column; align-items: center;
  padding: 40px 20px 20px;
}

/* LOGO SECTION */
.logo-box {
  width: 54px; height: 54px; background: #fff; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1); padding: 8px; margin-bottom: 12px;
}
.logo-box img { width: 100%; height: 100%; object-fit: contain; border-radius:8px;}
.app-title { font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 2px;}
.app-sub { font-size: 11px; font-weight: 500; color: rgba(255,255,255,0.8); margin-bottom: 30px;}

/* MAIN WHITE CARD */
.main-card {
  background: #fff; border-radius: 24px; width: 100%;
  padding: 24px 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

.mc-title { font-size: 16px; font-weight: 800; color: #012b26; margin-bottom: 4px; }
.mc-sub { font-size: 11px; font-weight: 500; color: #64748b; margin-bottom: 20px; }

/* INPUT GROUPS */
.fg { margin-bottom: 16px; }
.fg-lbl { 
  font-size: 10px; font-weight: 700; color: #64748b; 
  text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;
  display: flex; justify-content: space-between; align-items: center;
}
.fg-lbl.optional {
  text-transform: none; font-size: 10px; font-weight: 500; color: #94a3b8;
  background: #f1f5f9; padding: 2px 8px; border-radius: 12px; margin-left: auto;
}

.inp-wrap {
  display: flex; align-items: center; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 12px; transition: 0.2s; overflow: hidden;
}
.inp-wrap:focus-within { border-color: #012b26; box-shadow: 0 0 0 3px rgba(1,43,38,0.1); }
.inp-wrap.readonly { background: #f1f5f9; cursor: not-allowed; }
.inp-wrap.readonly input { color: #94a3b8; }

.inp-prefix {
  padding: 12px 10px 12px 14px; font-size: 13px; font-weight: 600; color: #475569;
  display: flex; align-items: center; gap: 8px; border-right: 1px solid #e2e8f0;
}
.inp-prefix i { font-size: 14px; color: #94a3b8; }

.inp-main {
  flex: 1; padding: 12px 14px; border: none; background: transparent;
  font-size: 13px; font-weight: 600; color: #012b26; outline: none;
}
.inp-main::placeholder { color: #94a3b8; font-weight: 500; }

.inp-right {
  padding: 12px 14px; display: flex; align-items: center; justify-content: center;
  color: #94a3b8; cursor: pointer;
}

/* BUTTON */
.btn-login {
  width: 100%; background: #012b26; color: #facc15; border: none;
  padding: 14px; border-radius: 12px; font-size: 13px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 10px; cursor: pointer; box-shadow: 0 6px 15px rgba(1,43,38,0.2);
  transition: 0.2s; font-family: 'Poppins', sans-serif;
}
.btn-login:active { transform: scale(0.97); }

.bottom-link { text-align: center; margin-top: 24px; font-size: 11px; font-weight: 500; color: #64748b; }
.bottom-link a { color: #012b26; font-weight: 700; text-decoration: none; }
.copy { font-size: 10px; color: #64748b; text-align: center; margin-top: 16px; }

/* Loader */
.loader-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
.loader-overlay.show { display: flex; }
.spinner { width: 40px; height: 40px; border: 4px solid #facc15; border-top-color: transparent; border-radius: 50%; animation: s-spin 0.8s linear infinite; }
@keyframes s-spin { to { transform: rotate(360deg); } }

/* SweetAlert2 Theme */
.swal2-container { font-family: 'Poppins', sans-serif !important; z-index: 10000 !important; }
.swal2-popup { border-radius: 20px !important; padding: 0 !important; overflow: hidden !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 20px 60px rgba(1, 43, 38, 0.15) !important; max-width: 380px !important; width: calc(100vw - 32px) !important; }
.swal2-popup::before { content: ''; display: block; width: 100%; height: 4px; background: linear-gradient(90deg, #012b26, #023e35, #facc15, #012b26); background-size: 200% 100%; animation: swal-stripe 3s linear infinite; }
@keyframes swal-stripe { 0% { background-position: 0% 0%; } 100% { background-position: 200% 0%; } }
.swal2-icon { margin: 24px auto 8px !important; }
.swal2-title { font-size: 18px !important; font-weight: 800 !important; color: #012b26 !important; padding: 4px 22px !important; margin: 0 !important; }
.swal2-html-container { font-size: 13.5px !important; font-weight: 500 !important; color: #64748b !important; padding: 6px 22px 20px !important; margin: 0 !important; }
.swal2-actions { padding: 0 20px 24px !important; gap: 10px !important; flex-wrap: nowrap !important; }
.swal2-confirm.swal2-styled { font-size: 14px !important; font-weight: 700 !important; padding: 13px 24px !important; border-radius: 12px !important; background: #facc15 !important; color: #012b26 !important; box-shadow: 0 6px 20px rgba(250, 204, 21, 0.3) !important; }
</style>
</head>
<body>

<div class="top-bg"></div>

<div class="content-wrap">
  
  <div class="logo-box">
    <?php if(!empty($config_web['web_logo'])): ?>
      <img src="<?= base_url('assets/images/' . $config_web['web_logo']) ?>" alt="<?= $nama_web ?>">
    <?php else: ?>
      <i class="fa-solid fa-bolt"></i>
    <?php endif; ?>
  </div>
  <div class="app-title"><?= htmlspecialchars($nama_web) ?></div>
  <div class="app-sub">Buat akun dan mulai berinvestasi</div>

  <div class="main-card">
    <div class="mc-title">Daftar akun</div>
    <div class="mc-sub">Lengkapi data untuk bergabung</div>

    <form id="registerForm">
      
      <!-- Username -->
      <div class="fg">
        <div class="fg-lbl">USERNAME</div>
        <div class="inp-wrap">
          <div class="inp-prefix">
            <i class="fa-solid fa-user"></i>
          </div>
          <input type="text" id="username" class="inp-main" placeholder="Buat nama pengguna" autocomplete="username" required>
        </div>
      </div>

      <!-- Nomor Ponsel -->
      <div class="fg">
        <div class="fg-lbl">NOMOR TELEPON</div>
        <div class="inp-wrap">
          <div class="inp-prefix">
            <i class="fa-solid fa-mobile-screen"></i> +62
          </div>
          <input type="tel" id="phone" class="inp-main" placeholder="81234567890" autocomplete="tel" required>
        </div>
      </div>

      <!-- Kata Sandi -->
      <div class="fg">
        <div class="fg-lbl">KATA SANDI</div>
        <div class="inp-wrap">
          <div class="inp-prefix" style="border-right: none; padding-right: 0;">
            <i class="fa-solid fa-lock"></i>
          </div>
          <input type="password" id="password" class="inp-main" placeholder="Minimal 6 karakter" required>
          <div class="inp-right" id="togglePassword">
            <i class="fa-solid fa-eye-slash"></i>
          </div>
        </div>
        <div class="pw-strength" id="pwIndicator" style="display:none;"></div>
      </div>

      <!-- Konfirmasi Kata Sandi -->
      <div class="fg" style="margin-top:-6px;">
        <div class="inp-wrap">
          <div class="inp-prefix" style="border-right: none; padding-right: 0;">
            <i class="fa-solid fa-lock" style="visibility:hidden;"></i>
          </div>
          <input type="password" id="password_confirm" class="inp-main" placeholder="Ulangi kata sandi" required>
          <div class="inp-right" id="togglePasswordConfirm">
            <i class="fa-solid fa-eye-slash"></i>
          </div>
        </div>
      </div>

      <!-- Kode Referral -->
      <div class="fg">
        <div class="fg-lbl">
           KODE REFERRAL
           <span class="optional">Dari link</span>
        </div>
        <div class="inp-wrap <?= $reff_locked ? 'readonly' : '' ?>">
          <div class="inp-prefix" style="border-right: none; padding-right: 0;">
            <i class="fa-solid fa-tag"></i>
          </div>
          <input type="text" id="refferal" class="inp-main" value="<?= htmlspecialchars($reff) ?>" <?= $reff_locked ? 'readonly' : '' ?> placeholder="Opsional">
        </div>
      </div>

      <!-- Verifikasi -->
      <div class="fg" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
           <div style="display:flex; align-items:center; gap:8px;">
              <div style="width:28px; height:28px; background:#e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#012b26;">
                 <i class="fa-solid fa-shield-halved"></i>
              </div>
              <div>
                 <div style="font-size:12px; font-weight:800; color:#012b26;">Verifikasi</div>
                 <div style="font-size:10px; font-weight:500; color:#64748b;">Samakan hasil hitung di bawah</div>
              </div>
           </div>
           <button type="button" onclick="generateCaptcha()" style="background:rgba(1,43,38,0.05); color:#012b26; border:1px solid rgba(1,43,38,0.1); border-radius:8px; padding:6px 12px; font-size:11px; font-weight:700; cursor:pointer;">
              <i class="fa-solid fa-rotate-right"></i> Baru
           </button>
        </div>
        <div style="text-align:center; margin-bottom:12px;">
           <div class="captcha-question" id="captchaQuestion" style="font-size:18px; font-weight:800; color:#012b26; letter-spacing:3px;">? + ? = ?</div>
        </div>
        <div class="inp-wrap">
           <input type="number" inputmode="numeric" id="captchaInput" class="inp-main" placeholder="Masukkan angka hasil..." style="text-align:center; font-weight:700; letter-spacing:2px;" required>
        </div>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        Daftar <i class="fa-solid fa-user-plus"></i>
      </button>

    </form>
  </div>

  <div class="bottom-link">
    Sudah punya akun? <a href="login.php<?= !empty($reff) ? '?reff='.htmlspecialchars($reff) : '' ?>">Masuk</a>
  </div>
  
  <div class="copy">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($nama_web) ?>
  </div>

</div>

<div class="loader-overlay" id="loadingOv">
  <div class="spinner"></div>
</div>

<script>
/* Toggle Password Visibility */
$('#togglePassword').on('click', function() {
  const input = $('#password');
  const type = input.attr('type') === 'password' ? 'text' : 'password';
  input.attr('type', type);
  if(type === 'text') $(this).html('<i class="fa-solid fa-eye" style="color: #012b26;"></i>');
  else $(this).html('<i class="fa-solid fa-eye-slash"></i>');
});

$('#togglePasswordConfirm').on('click', function() {
  const input = $('#password_confirm');
  const type = input.attr('type') === 'password' ? 'text' : 'password';
  input.attr('type', type);
  if(type === 'text') $(this).html('<i class="fa-solid fa-eye" style="color: #012b26;"></i>');
  else $(this).html('<i class="fa-solid fa-eye-slash"></i>');
});

/* Math Captcha Verification */
let currentCaptcha = 0;

function generateCaptcha() {
  const num1 = Math.floor(Math.random() * 10) + 1;
  const num2 = Math.floor(Math.random() * 10) + 1;
  currentCaptcha = num1 + num2;
  
  $('#captchaQuestion').html(`${num1} <span style="color:#64748b;font-weight:500;">+</span> ${num2} <span style="color:#64748b;font-weight:500;">=</span> <span style="color:#facc15;">?</span>`);
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
      confirmButtonColor: '#012b26'
    });
    return;
  }
  
  if (password !== confirmP) {
    Swal.fire({
      icon: 'warning',
      title: 'Password Berbeda',
      text: 'Ulangi password tidak cocok.',
      confirmButtonColor: '#012b26'
    });
    return;
  }

  if (username.length < 3 || username.length > 50) {
    Swal.fire({
      icon: 'warning',
      title: 'Username Tidak Valid',
      text: 'Username harus antara 3 hingga 50 karakter.',
      confirmButtonColor: '#012b26'
    });
    return;
  }
  
  if (password.length < 6) {
    Swal.fire({
      icon: 'warning',
      title: 'Password Terlalu Pendek',
      text: 'Minimal 6 karakter.',
      confirmButtonColor: '#012b26'
    });
    return;
  }
  
  if (captchaInput !== currentCaptcha) {
    Swal.fire({
      icon: 'error',
      title: 'Perhitungan Salah',
      text: 'Hasil verifikasi keamanan tidak tepat. Silakan coba lagi.',
      confirmButtonColor: '#012b26'
    });
    generateCaptcha();
    return;
  }

  $('#loadingOv').addClass('show');
  $('#submitBtn').prop('disabled', true).html('Proses... <i class="fa-solid fa-spinner fa-spin"></i>');

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
          confirmButtonColor:'#012b26',
          timer: 2000, showConfirmButton: false
        }).then(function() {
          window.location.href = res.redirect || '/';
        });
      } else {
        $('#submitBtn').prop('disabled', false).html('Daftar <i class="fa-solid fa-user-plus"></i>');
        Swal.fire({ icon:'error', title:'Gagal Daftar',
          text: res.message || 'Terjadi kesalahan, coba lagi.',
          confirmButtonColor:'#012b26' });
        generateCaptcha();
      }
    },
    error: function() {
      $('#loadingOv').removeClass('show');
      $('#submitBtn').prop('disabled', false).html('Daftar <i class="fa-solid fa-user-plus"></i>');
      Swal.fire({ icon:'error', title:'Koneksi Error',
        text:'Gagal menghubungi server. Periksa koneksi internet.',
        confirmButtonColor:'#012b26' });
      generateCaptcha();
    }
  });
});
</script>

</body>
</html>