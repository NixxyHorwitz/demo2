<?php

// === KONFIGURASI DAN DATA AWAL ===
$time = microtime(true);
$start_pageload = $time;

if (isset($model) && isset($db)) {
  $query_settings = $model->db_query($db, "*", "settings", "id = 1");
  $config_web = $query_settings['rows'];

  $user_id = $_SESSION['user_id'] ?? null;
  $user_data_query = $model->db_query($db, "*", "users", "id = '$user_id'");
  $user_data = $user_data_query['rows'] ?? null;
} else {
  $config_web = ['title' => 'Nama Website'];
  $user_data = ['user_name' => 'User', 'point' => 0, 'avatar' => 'avatar.svg'];
}

if (!function_exists('base_url')) {
  function base_url($path = '')
  {
    return $path;
  }
}

$user_name = htmlspecialchars($user_data['rows']['phone'] ?? 'Pengguna');
$user_balance = number_format($user_data['point'] ?? 0, 0, ',', '.');
$avatar = base_url('uploads/avatar/' . ($user_data['avatar'] ?? 'avatar.svg'));
$nama_web = htmlspecialchars($config_web['title'] ?? 'Website');
?>

<!DOCTYPE html>
<html class="loading" lang="en" data-textdirection="ltr">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-pr©¡sident-compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta name="description" content="<?= htmlspecialchars($config['meta']['description'] ?? ''); ?>">
  <meta name="keywords" content="<?= htmlspecialchars($config['meta']['keyword'] ?? ''); ?>">

  <title><?= $nama_web; ?> - <?= htmlspecialchars($page_name ?? 'Halaman'); ?></title>

  <link rel="apple-touch-icon" href="<?= base_url('assets/images/'.$config_web['web_logo']); ?>">
  <link rel="shortcut icon" type="image/x-icon" href="<?= base_url('assets/images/'.$config_web['web_logo']); ?>">
  <link href="https://fonts.googleapis.com/css?family=Muli:300,300i,400,400i,600,600i,700,700i%7CComfortaa:300,400,700" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <style>
/* ============================================================
   SWEETALERT2 THEME — Jet Black & Metallic Gold
============================================================ */

/* ── BACKDROP ── */
.swal2-container {
  font-family: 'Poppins', sans-serif !important;
}
.swal2-backdrop-show,
.swal2-container.swal2-backdrop-show {
  background: rgba(0, 0, 0, 0.75) !important;
  backdrop-filter: blur(6px) !important;
  -webkit-backdrop-filter: blur(6px) !important;
}

/* ── POPUP BASE ── */
.swal2-popup {
  font-family: 'Poppins', sans-serif !important;
  border-radius: 16px !important;
  padding: 0 !important;
  overflow: hidden !important;
  border: 1px solid rgba(197, 147, 39, 0.3) !important;
  box-shadow: 0 20px 50px rgba(0,0,0,0.6), 0 0 0 1px rgba(197,147,39,0.1) !important;
  background: #111111 !important;
  max-width: 320px !important;
  width: calc(100vw - 40px) !important;
}

/* top accent bar — gold gradient */
.swal2-popup::before {
  content: ''; display: block; width: 100%; height: 3px;
  background: linear-gradient(90deg, #C59327, #F5D061, #C59327);
  background-size: 200% 100%; animation: swal-stripe 2.5s linear infinite;
  flex-shrink: 0;
}
@keyframes swal-stripe {
  0%   { background-position: 0% 0%; }
  100% { background-position: 200% 0%; }
}

/* ── ANIMATIONS ── */
.swal2-show { animation: swal-pop-in .2s cubic-bezier(.22, 1, .36, 1) !important; }
.swal2-hide { animation: swal-pop-out .12s ease-in forwards !important; }
@keyframes swal-pop-in {
  from { opacity: 0; transform: scale(.92) translateY(8px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes swal-pop-out {
  from { opacity: 1; transform: scale(1); }
  to   { opacity: 0; transform: scale(.96) translateY(4px); }
}

/* ── ICON ── */
.swal2-icon { margin: 18px auto 4px !important; transform: scale(0.8) !important; }

/* Success */
.swal2-icon.swal2-success { border-color: rgba(16, 185, 129, 0.4) !important; color: #10b981 !important; }
.swal2-icon.swal2-success .swal2-success-ring { border-color: rgba(16, 185, 129, 0.2) !important; }
.swal2-icon.swal2-success [class^='swal2-success-line'] { background-color: #10b981 !important; }

/* Error */
.swal2-icon.swal2-error { border-color: rgba(239, 68, 68, 0.4) !important; color: #ef4444 !important; }
.swal2-icon.swal2-error [class^='swal2-x-mark-line'] { background-color: #ef4444 !important; }

/* Warning */
.swal2-icon.swal2-warning { border-color: rgba(245, 158, 11, 0.4) !important; color: #f59e0b !important; }

/* Info / Question — gold */
.swal2-icon.swal2-info { border-color: rgba(197, 147, 39, 0.4) !important; color: #F5D061 !important; }
.swal2-icon.swal2-question { border-color: rgba(197, 147, 39, 0.4) !important; color: #F5D061 !important; }

/* ── LOADER ── */
.swal2-loader { border-color: #F5D061 transparent #F5D061 transparent !important; border-width: 2px !important; }

/* ── TITLE ── */
.swal2-title {
  font-family: 'Poppins', sans-serif !important; font-size: 15px !important; font-weight: 800 !important;
  color: #fff !important; padding: 2px 18px 2px !important; margin: 0 !important; line-height: 1.3 !important;
}

/* ── HTML CONTENT ── */
.swal2-html-container {
  font-family: 'Poppins', sans-serif !important; font-size: 12px !important; font-weight: 500 !important;
  color: rgba(255,255,255,0.6) !important; line-height: 1.6 !important; padding: 4px 18px 16px !important; margin: 0 !important;
}
.swal2-html-container b, .swal2-html-container strong { color: #F5D061 !important; font-weight: 700 !important; }

/* ── ACTIONS ── */
.swal2-actions { padding: 0 16px 18px !important; gap: 8px !important; flex-wrap: nowrap !important; }

/* Confirm — Gold */
.swal2-confirm.swal2-styled {
  font-family: 'Poppins', sans-serif !important; font-size: 12.5px !important; font-weight: 700 !important;
  padding: 10px 20px !important; border-radius: 10px !important; border: none !important;
  background: linear-gradient(135deg, #C59327, #F5D061) !important; color: #111 !important;
  box-shadow: 0 4px 12px rgba(197, 147, 39, 0.3) !important; transition: transform .15s !important;
  outline: none !important;
}
.swal2-confirm.swal2-styled:active { transform: scale(.96) !important; }
.swal2-confirm.swal2-styled[style*="background"] {
  background: linear-gradient(135deg, #C59327, #F5D061) !important; color: #111 !important;
  box-shadow: 0 4px 12px rgba(197, 147, 39, 0.3) !important;
}

/* Cancel — Dark */
.swal2-cancel.swal2-styled {
  font-family: 'Poppins', sans-serif !important; font-size: 12.5px !important; font-weight: 700 !important;
  padding: 10px 18px !important; border-radius: 10px !important;
  background: rgba(255,255,255,0.06) !important; color: rgba(255,255,255,0.6) !important;
  border: 1px solid rgba(255,255,255,0.1) !important; box-shadow: none !important; outline: none !important;
  transition: background .15s !important;
}
.swal2-cancel.swal2-styled:hover { background: rgba(255,255,255,0.1) !important; color: #fff !important; }

/* Deny — Red */
.swal2-deny.swal2-styled {
  font-family: 'Poppins', sans-serif !important; font-size: 12.5px !important; font-weight: 700 !important;
  padding: 10px 18px !important; border-radius: 10px !important;
  background: rgba(239,68,68,0.1) !important; color: #f87171 !important;
  border: 1px solid rgba(239,68,68,0.2) !important; box-shadow: none !important; outline: none !important;
}
.swal2-deny.swal2-styled:hover { background: rgba(239,68,68,0.2) !important; }

/* ── CLOSE BUTTON ── */
.swal2-close {
  color: rgba(255,255,255,0.4) !important; font-size: 20px !important; top: 10px !important; right: 10px !important;
  width: 28px !important; height: 28px !important; border-radius: 50% !important; background: transparent !important;
  transition: background .15s, color .15s !important;
}
.swal2-close:hover { background: rgba(255,255,255,0.1) !important; color: #fff !important; }
.swal2-close:focus { box-shadow: none !important; }

/* ── INPUT / TEXTAREA ── */
.swal2-input, .swal2-textarea, .swal2-select {
  font-family: 'Poppins', sans-serif !important; font-size: 13px !important; font-weight: 500 !important;
  border: 1px solid rgba(255,255,255,0.1) !important; border-radius: 10px !important; padding: 10px 12px !important;
  color: #fff !important; background: rgba(255,255,255,0.05) !important; box-shadow: none !important; outline: none !important;
  margin: 8px 0 !important; transition: border-color .2s !important; width: calc(100% - 36px) !important;
}
.swal2-input:focus, .swal2-textarea:focus { border-color: #F5D061 !important; }
.swal2-input::placeholder, .swal2-textarea::placeholder { color: rgba(255,255,255,0.3) !important; }

/* ── VALIDATION MESSAGE ── */
.swal2-validation-message {
  font-family: 'Poppins', sans-serif !important; font-size: 11.5px !important; font-weight: 600 !important;
  background: rgba(239,68,68,0.1) !important; color: #f87171 !important; border-radius: 8px !important;
  border: 1px dashed rgba(239,68,68,0.3) !important; padding: 8px 12px !important; margin: 4px 0 0 !important;
}
.swal2-validation-message::before { background: #ef4444 !important; }

/* ── TIMER BAR ── */
.swal2-timer-progress-bar { background: linear-gradient(90deg, #C59327, #F5D061) !important; height: 2px !important; }
.swal2-timer-progress-bar-container { border-radius: 0 0 16px 16px !important; overflow: hidden !important; }

/* ── FOOTER ── */
.swal2-footer {
  font-family: 'Poppins', sans-serif !important; font-size: 11px !important; color: rgba(255,255,255,0.4) !important;
  border-top-color: rgba(255,255,255,0.08) !important; margin: 10px 0 0 !important; padding-top: 12px !important;
}

/* ── MOBILE ── */
@media (max-width: 480px) {
  .swal2-title { font-size: 14px !important; padding: 2px 16px !important; }
  .swal2-html-container { font-size: 11.5px !important; padding: 4px 16px 14px !important; }
  .swal2-actions { flex-direction: row !important; padding: 0 16px 16px !important; gap: 8px !important; }
  .swal2-confirm.swal2-styled, .swal2-cancel.swal2-styled, .swal2-deny.swal2-styled { flex: 1 !important; justify-content: center !important; }
}
    </style>