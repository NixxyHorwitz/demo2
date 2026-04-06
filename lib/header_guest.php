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
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    :root {
      --dana-blue: #2b70ff;
      --dana-grad1: #2b70ff;
      --dana-grad2: #6b9bff;
      --bg: #f2f6ff;
      --card: #ffffff;
      --muted: #75809a;
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      background: var(--bg);
      font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
      color: #071029
    }

    .wrap {
      max-width: 420px;
      margin: 0 auto;
      padding: 18px
    }

    .appbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .logo .badge {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--dana-grad1), var(--dana-grad2));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 800;
      font-size: 18px
    }

    .app-actions {
      display: flex;
      gap: 12px
    }

    .circle {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: white;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 6px 18px rgba(11, 26, 60, 0.06)
    }

    .balance-card {
      background: linear-gradient(90deg, var(--dana-grad1), var(--dana-grad2));
      border-radius: 16px;
      padding: 18px;
      color: white;
      position: relative;
      overflow: hidden;
      box-shadow: 0 12px 30px rgba(43, 112, 255, 0.14)
    }

    .balance-top {
      display: flex;
      align-items: center;
      justify-content: space-between
    }

    .balance-top .left {
      display: flex;
      gap: 12px;
      align-items: center
    }

    .avatar {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700
    }

    .balance-value {
      margin-top: 14px;
      font-size: 28px;
      font-weight: 800;
      letter-spacing: 0.4px
    }

    .balance-sub {
      font-size: 13px;
      opacity: 0.95
    }

    .action-row {
      display: flex;
      gap: 10px;
      margin-top: 14px
    }

    .action {
      flex: 1;
      background: rgba(255, 255, 255, 0.12);
      padding: 10px;
      border-radius: 12px;
      text-align: center;
      color: white;
      font-weight: 700;
      cursor: pointer
    }

    .action i {
      display: block;
      font-size: 18px;
      margin-bottom: 6px
    }

    .features {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-top: 14px
    }

    .feature {
      background: var(--card);
      border-radius: 12px;
      padding: 10px;
      text-align: center;
      box-shadow: 0 8px 22px rgba(11, 26, 60, 0.04)
    }

    .feature i {
      display: block;
      font-size: 18px;
      margin-bottom: 6px;
      color: var(--dana-grad1)
    }

    .feature p {
      margin: 0;
      font-size: 12px;
      color: var(--muted)
    }

    .card {
      background: var(--card);
      border-radius: 12px;
      padding: 12px;
      margin-top: 12px;
      box-shadow: 0 8px 20px rgba(11, 26, 60, 0.04)
    }

    .card h3 {
      margin: 0 0 6px 0
    }

    .card p {
      margin: 0;
      color: var(--muted);
      font-size: 14px
    }

    .modal {
      position: fixed;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(4, 10, 35, 0.45);
      visibility: hidden;
      opacity: 0;
      transition: opacity .18s, visibility .18s;
      z-index: 999
    }

    .modal.show {
      visibility: visible;
      opacity: 1
    }

    .panel {
      background: white;
      padding: 16px;
      border-radius: 12px;
      width: 92%;
      max-width: 420px
    }

    .input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #e6ecff;
      margin-top: 8px
    }

    .primary {
      background: linear-gradient(90deg, var(--dana-grad1), var(--dana-grad2));
      color: white;
      padding: 12px;
      border: none;
      border-radius: 10px;
      width: 100%;
      font-weight: 700
    }

    small.muted {
      color: var(--muted)
    }

    @media(max-width:460px) {
      .wrap {
        padding: 12px
      }
    }
  </style>