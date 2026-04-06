<?php
declare(strict_types=1);
require '../mainconfig.php';
require '../lib/check_session.php';
require_once __DIR__ . '/../lib/flash_message.php';

/* ── DATA USER ── */
$user_query = $model->db_query($db,
    "id, username, phone, status, created_at, ip_address",
    "users", "id = '" . $db->real_escape_string($login['id']) . "'");
$user = $user_query['rows'];

$username   = $user['username']   ?? '';
$phone      = $user['phone']      ?? '';
$status     = $user['status']     ?? 'Active';
$created_at = $user['created_at'] ?? '';
$ip_address = $user['ip_address'] ?? '-';
$uid        = $user['id']         ?? '';

$cfg           = $model->db_query($db, "*", "settings", "id=1")['rows'];
$app_name      = $cfg['title']         ?? 'Platform';
$link_telegram = $cfg['link_telegram'] ?? '#';

$error      = '';
$success    = '';
$open_modal = '';

/* ── ACTIONS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'change_password') {
        $old_p = trim($_POST['old_password']      ?? '');
        $new_p = trim($_POST['new_password']      ?? '');
        $con_p = trim($_POST['confirm_password']  ?? '');
        if (!$old_p || !$new_p || !$con_p)   $error = 'Semua kolom wajib diisi.';
        elseif (strlen($new_p) < 6)           $error = 'Sandi baru minimal 6 karakter.';
        elseif ($new_p !== $con_p)            $error = 'Konfirmasi sandi tidak cocok.';
        else {
            $cur = ($model->db_query($db, "password", "users", "id='{$uid}'"))['rows']['password'] ?? '';
            if (!password_verify($old_p, $cur)) $error = 'Kata sandi lama salah.';
            else {
                mysqli_query($db, "UPDATE users SET password='" . password_hash($new_p, PASSWORD_BCRYPT) . "' WHERE id='{$uid}'");
                $success = 'Kata sandi berhasil diubah.';
            }
        }
        $open_modal = 'password';

    } else {
        /* action === 'profile' */
        $new_u = trim($_POST['username'] ?? '');
        if (!$new_u)                                                   $error = 'Username tidak boleh kosong.';
        elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $new_u))        $error = 'Username hanya boleh abjad & angka.';
        else {
            $chk = mysqli_query($db, "SELECT id FROM users WHERE username='" . $db->real_escape_string($new_u) . "' AND id!='{$uid}'");
            if (mysqli_num_rows($chk) > 0) $error = 'Username sudah terpakai.';
        }
        if (!$error) {
            mysqli_query($db, "UPDATE users SET username='" . $db->real_escape_string($new_u) . "' WHERE id='{$uid}'");
            $success  = 'Profil berhasil diperbarui.';
            $username = $new_u;
        }
        $open_modal = 'profile';
    }
}


require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Pengaturan</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --pri:      #237fad;
  --priD:     #1a5f8a;
  --bg:       #f8fafc;
  --card:     #ffffff;
  --border:   #e2e8f0;
  --txt:      #0f172a;
  --txt2:     #475569;
  --muted:    #94a3b8;
  --grn:      #10b981;
  --red:      #f43f5e;
  --font:     'Poppins', sans-serif;
}
html, body { background: var(--bg); color: var(--txt); font-family: var(--font); min-height: 100vh; }
body { padding-bottom: 80px; }
.app { max-width: 480px; margin: 0 auto; position: relative; }

/* ── HEADER OVERLAP ── */
.set-head {
  background: linear-gradient(135deg, var(--pri) 0%, var(--priD) 100%);
  border-radius: 0 0 24px 24px;
  padding: 24px 20px 80px;
  color: #fff; position: relative; overflow: hidden;
  box-shadow: 0 8px 24px rgba(35,127,173,0.15);
}
.set-head::before {
  content: ''; position: absolute; width: 140px; height: 140px;
  background: rgba(255,255,255,0.06); border-radius: 50%;
  top: -40px; right: -40px; pointer-events: none;
}
.sh-top { display: flex; align-items: center; justify-content: center; position: relative; z-index: 2; margin-bottom: 24px; }
.sh-back { position: absolute; left: 0; color: #fff; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; }
.sh-back svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }
.sh-title { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; }

/* ── PROFILE CARD ── */
.user-card {
  margin: -50px 20px 20px;
  background: var(--card); border-radius: 16px; padding: 20px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.06);
  position: relative; z-index: 10;
  display: flex; align-items: center; gap: 16px;
}
.uc-img {
  width: 60px; height: 60px; border-radius: 50%;
  background: var(--priL, #e0f2fe); border: 2.5px solid var(--pri);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 800; color: var(--pri);
  overflow: hidden; flex-shrink: 0;
}
.uc-info { flex: 1; min-width: 0; }
.uc-name { font-size: 16px; font-weight: 800; color: var(--txt); margin-bottom: 2px; }
.uc-phone { font-size: 12px; font-weight: 500; color: var(--muted); }

/* ── FORM PROFIL ── */
.panel {
  margin: 0 20px 20px; background: var(--card); border-radius: 16px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.02); overflow: hidden;
  border: 1px solid var(--border);
}
.panel-title { font-size: 13px; font-weight: 800; color: var(--txt); padding: 16px 16px 10px; border-bottom: 1px solid var(--border); }

.f-group { padding: 16px; border-bottom: 1px solid var(--border); }
.f-group:last-child { border-bottom: none; }
.f-lbl { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; margin-bottom: 8px; }
.f-inp {
  width: 100%; border: 1.5px solid var(--border); background: #f8fafc;
  border-radius: 12px; padding: 14px; font-size: 14px; font-weight: 600; font-family: var(--font);
  color: var(--txt); outline: none; transition: border 0.2s;
}
.f-inp:focus { border-color: var(--pri); background: #fff; }
.f-inp[readonly] { opacity: 0.6; cursor: not-allowed; }
.f-btn {
  width: calc(100% - 32px); margin: 0 16px 16px; padding: 14px;
  background: var(--pri); color: #fff; font-size: 14px; font-weight: 700; font-family: var(--font);
  border: none; border-radius: 12px; cursor: pointer; display: flex; justify-content: center; gap: 8px;
}
.f-btn svg { width: 18px; stroke: #fff; fill: none; stroke-width: 2.5; }

/* ── MENUS ── */
.m-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px; border-bottom: 1px solid var(--border); cursor: pointer; text-decoration: none; color: inherit;
}
.m-item:last-child { border-bottom: none; }
.m-item-left { display: flex; align-items: center; gap: 12px; }
.m-ic { width: 36px; height: 36px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: var(--txt2); }
.m-ic svg { width: 18px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; }
.m-tit { font-size: 13.5px; font-weight: 700; color: var(--txt); }
.m-arr svg { width: 18px; stroke: var(--muted); fill: none; stroke-width: 2; stroke-linecap: round; }

/* ── MODAL ── */
.modal-bg {
  position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px);
  z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px;
}
.modal-bg.show { display: flex; }
.mod {
  background: var(--card); width: 100%; max-width: 400px;
  border-radius: 20px; overflow: hidden; animation: modIn 0.3s cubic-bezier(0.2,1,0.3,1);
}
@keyframes modIn { from{transform:scale(0.9);opacity:0;} to{transform:scale(1);opacity:1;} }
.mod-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border); }
.mod-title { font-size: 15px; font-weight: 800; color: var(--txt); }
.mod-close { background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.mod-close svg { width: 16px; stroke: var(--muted); fill: none; stroke-width: 2; }
.mod-body { padding: 20px; }

.alert { margin: 0 20px 16px; padding: 12px 14px; border-radius: 12px; font-size: 12px; font-weight: 600; display: flex; gap: 8px; }
.alert-ok { background: #dcfce7; color: #16a34a; border: 1px dashed #86efac; }
.alert-err { background: #fee2e2; color: #dc2626; border: 1px dashed #fca5a5; }

</style>
</head>
<body>
<div class="app">

  <!-- HEADER OVERLAP -->
  <div class="set-head">
    <div class="sh-top">
      <a href="<?= base_url('pages/profile') ?>" class="sh-back">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <div class="sh-title">Pengaturan Sub-Akun</div>
    </div>
  </div>

  <!-- PROFILE INFO -->
  <div class="user-card">
    <div class="uc-img">
      <?php $ini = strtoupper(substr($username ?: $phone, 0, 1) ?: 'U'); ?>
      <?= $ini ?>
    </div>
    <div class="uc-info">
      <div class="uc-name"><?= htmlspecialchars($username ?: 'Member') ?></div>
      <div class="uc-phone"><?= htmlspecialchars($phone) ?></div>
    </div>
  </div>

  <?php if ($open_modal === 'profile' && ($success || $error)): ?>
  <div class="alert <?= $success ? 'alert-ok' : 'alert-err' ?>">
    <?= htmlspecialchars($success ?: $error) ?>
  </div>
  <?php endif; ?>

  <!-- FORM PROFIL -->
  <div class="panel">
    <div class="panel-title">Edit Profil</div>
    <form method="POST">
      <input type="hidden" name="action" value="profile">
      <div class="f-group">
        <label class="f-lbl">Username Alias</label>
        <input type="text" name="username" class="f-inp" value="<?= htmlspecialchars($username) ?>" placeholder="Ubah namamu">
      </div>
      <div class="f-group">
        <label class="f-lbl">Nomor HP</label>
        <input type="text" class="f-inp" value="<?= htmlspecialchars($phone) ?>" readonly>
      </div>
      <button type="submit" class="f-btn">
        Simpan Profil
      </button>
    </form>
  </div>

  <!-- KEAMANAN & BANTUAN -->
  <div class="panel">
    <div class="m-item" onclick="openMod('modPass')">
      <div class="m-item-left">
        <div class="m-ic"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></div>
        <div class="m-tit">Ubah Kata Sandi</div>
      </div>
      <div class="m-arr"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </div>
    <a href="<?= htmlspecialchars($link_telegram) ?>" target="_blank" class="m-item">
      <div class="m-item-left">
        <div class="m-ic"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
        <div class="m-tit">Hubungi Support</div>
      </div>
      <div class="m-arr"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </a>
  </div>
</div>

<!-- MODAL PASSWORD -->
<div class="modal-bg <?= ($open_modal==='password')?'show':'' ?>" id="modPass" onclick="bgClose(event)">
  <div class="mod">
    <div class="mod-head">
      <div class="mod-title">Perbarui Sandi</div>
      <button class="mod-close" onclick="closeMod('modPass')"><svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="mod-body">
      <?php if ($open_modal === 'password' && ($success || $error)): ?>
      <div class="alert <?= $success ? 'alert-ok' : 'alert-err' ?>" style="margin: 0 0 16px; width: 100%;">
        <?= htmlspecialchars($success ?: $error) ?>
      </div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="f-group" style="padding:0 0 16px; border:none;">
          <label class="f-lbl">Sandi Lama</label>
          <input type="password" name="old_password" class="f-inp" placeholder="Ketikan sandi lama">
        </div>
        <div class="f-group" style="padding:0 0 16px; border:none;">
          <label class="f-lbl">Sandi Baru</label>
          <input type="password" name="new_password" class="f-inp" placeholder="Minimal 6 karakter">
        </div>
        <div class="f-group" style="padding:0 0 16px; border:none;">
          <label class="f-lbl">Ulangi Sandi Baru</label>
          <input type="password" name="confirm_password" class="f-inp" placeholder="Konfirmasi sandi baru">
        </div>
        <button type="submit" class="f-btn" style="margin:0; width:100%;">Ubah Sekarang</button>
      </form>
    </div>
  </div>
</div>

<script>
function openMod(id){ document.getElementById(id).classList.add('show'); }
function closeMod(id){ document.getElementById(id).classList.remove('show'); }
function bgClose(e){ if(e.target.classList.contains('modal-bg')) e.target.classList.remove('show'); }
</script>
</body>
</html>