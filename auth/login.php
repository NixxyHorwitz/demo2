<?php
// auth/login.php
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
.fg-lbl { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }

.inp-wrap {
  display: flex; align-items: center; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 12px; transition: 0.2s; overflow: hidden;
}
.inp-wrap:focus-within { border-color: #012b26; box-shadow: 0 0 0 3px rgba(1,43,38,0.1); }

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
  margin-top: 20px; cursor: pointer; box-shadow: 0 6px 15px rgba(1,43,38,0.2);
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
  <div class="app-sub">Masuk untuk kelola aset Anda</div>

  <div class="main-card">
    <div class="mc-title">Selamat datang</div>
    <div class="mc-sub">Isi data di bawah untuk melanjutkan</div>

    <form id="loginForm">
      
      <div class="fg">
        <div class="fg-lbl">NOMOR TELEPON</div>
        <div class="inp-wrap">
          <div class="inp-prefix">
            <i class="fa-solid fa-mobile-screen"></i> +62
          </div>
          <input type="tel" id="phone" class="inp-main" placeholder="81234567890" autocomplete="tel" required>
        </div>
      </div>

      <div class="fg">
        <div class="fg-lbl">KATA SANDI</div>
        <div class="inp-wrap">
          <div class="inp-prefix" style="border-right: none; padding-right: 0;">
            <i class="fa-solid fa-lock"></i>
          </div>
          <input type="password" id="password" class="inp-main" placeholder="••••••••" autocomplete="current-password" required>
          <div class="inp-right" id="togglePassword">
            <i class="fa-solid fa-eye-slash"></i>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-login" id="submitBtn">
        Masuk <i class="fa-solid fa-arrow-right"></i>
      </button>

    </form>
  </div>

  <div class="bottom-link">
    Belum punya akun? <a href="register.php<?= !empty($reff) ? '?reff='.$reff : '' ?>">Daftar sekarang</a>
  </div>
  
  <div class="copy">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($nama_web) ?>
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
    $(this).html('<i class="fa-solid fa-eye" style="color: #012b26;"></i>');
  } else {
    $(this).html('<i class="fa-solid fa-eye-slash"></i>');
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
  $('#submitBtn').prop('disabled', true).html('Proses... <i class="fa-solid fa-spinner fa-spin"></i>');
  
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
        $('#submitBtn').prop('disabled', false).html('Masuk <i class="fa-solid fa-arrow-right"></i>');
        
        Swal.fire({
          icon: 'error',
          title: 'Gagal Masuk',
          text: res.message || 'Nomor telepon atau Password salah',
          confirmButtonColor: '#012b26'
        });
      }
    },
    error: function() {
      $('#loadingOv').removeClass('show');
      $('#submitBtn').prop('disabled', false).html('Masuk <i class="fa-solid fa-arrow-right"></i>');
      Swal.fire({
        icon: 'error',
        title: 'Error Jaringan',
        text: 'Gagal terhubung ke server. Silakan coba lagi.',
        confirmButtonColor: '#012b26'
      });
    }
  });
});
</script>

</body>
</html>