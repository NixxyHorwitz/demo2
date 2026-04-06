<?php
declare(strict_types=1);
require '../mainconfig.php';
require '../lib/check_session.php';
require_once __DIR__ . '/../lib/flash_message.php';

$page_type = 'profile';
$page_name = 'Akun Saya';

$user_query = $model->db_query($db, "id, phone, point, profit, saldo, status, rekening, no_rek, pemilik, bank_code, created_at, uplink_level, x_uniqueid, username", "users", "id = '".$db->real_escape_string($login['id'])."'");
$user = $user_query['rows'];

$saldo        = (float)($user['saldo']  ?? 0);
$point        = (float)($user['point']  ?? 0);
$profit       = (float)($user['profit'] ?? 0);
$phone        = $user['phone']          ?? '-';
$display_name = $user['username']       ?? substr($phone, 0, 8).'...';
$no_rek       = $user['no_rek']         ?? null;
$pemilik      = $user['pemilik']        ?? null;
$bank_code    = $user['bank_code']      ?? null;

$settings      = $model->db_query($db, "*", "settings", "id='1'");
$app_name      = $settings['rows']['title'] ?? 'InvestApp';
$link_telegram = $settings['rows']['link_telegram'] ?? '#';

$error      = '';
$success    = '';
$open_modal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $uid = $login['id'];
        $old_p = trim($_POST['old_password'] ?? '');
        $new_p = trim($_POST['new_password'] ?? '');
        $con_p = trim($_POST['confirm_password'] ?? '');
        if (!$old_p || !$new_p || !$con_p) {
            $error = 'Semua kolom wajib diisi.';
        } elseif (strlen($new_p) < 6) {
            $error = 'Sandi baru minimal 6 karakter.';
        } elseif ($new_p !== $con_p) {
            $error = 'Konfirmasi sandi tidak cocok.';
        } else {
            $cur = ($model->db_query($db, "password", "users", "id='{$uid}'"))['rows']['password'] ?? '';
            if (!password_verify($old_p, $cur)) {
                $error = 'Kata sandi lama salah.';
            } else {
                mysqli_query($db, "UPDATE users SET password='" . password_hash($new_p, PASSWORD_BCRYPT) . "' WHERE id='{$uid}'");
                $success = 'Kata sandi berhasil diubah.';
            }
        }
        $open_modal = 'password';
    }
}

$app_images = [];
$imgQ = mysqli_query($db, "SELECT * FROM app_images");
if($imgQ) { while($r = mysqli_fetch_assoc($imgQ)) $app_images[$r['image_key']] = $r['image_url']; }

require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Profil &bullet; <?= htmlspecialchars($app_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Poppins', sans-serif; background: #0A0A0A; color: #fff; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
    .app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; background: #0A0A0A; }

    /* ====== HEADER ====== */
    .p-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px 5px; }
    .p-brand { font-size: 15px; font-weight: 800; color: #F5D061; letter-spacing: 0px; }
    .p-actions { display: flex; gap: 10px; }
    .p-btn { width: 32px; height: 32px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6); text-decoration: none; position: relative; }
    .p-btn svg { width: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
    .p-btn .badge { position: absolute; top: 6px; right: 6px; width: 6px; height: 6px; background: #ef4444; border-radius: 50%; }

    /* ====== USER INFO ====== */
    .p-user { padding: 10px 20px; display: flex; align-items: center; gap: 12px; margin-bottom: 5px; }
    .pu-ava {
        width: 60px; height: 60px; border-radius: 50%; padding: 3px;
        border: 2px solid rgba(197,147,39,0.5);
        background: linear-gradient(135deg,#C59327,#F5D061);
        position: relative; display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 22px; font-weight: 800; color: #111;
    }
    .pu-info { flex: 1; }
    .pu-name { font-size: 15px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px; margin-bottom: 2px; }
    .pu-badge { font-size: 9px; font-weight: 700; background: linear-gradient(135deg,#C59327,#F5D061); color: #111; padding: 2px 7px; border-radius: 20px; display:flex; align-items:center; gap:3px; text-transform: uppercase; }
    .pu-badge svg { width: 9px; fill: #111; }
    .pu-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 11px; color: rgba(255,255,255,0.4); font-weight: 500; }
    .pu-meta div { display: flex; align-items: center; gap: 4px; }
    .pu-meta svg { width: 12px; stroke: rgba(255,255,255,0.35); fill: none; stroke-width: 2; }

    /* ====== STATS BOX ====== */
    .p-stats { margin: 10px 20px 20px; display: flex; padding: 12px 0; border-top: 1px dashed rgba(255,255,255,0.07); border-bottom: 1px dashed rgba(255,255,255,0.07); }
    .ps-col { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
    .ps-col:not(:last-child)::after { content: ''; position: absolute; right: 0; top: 15%; height: 70%; width: 1px; background: rgba(255,255,255,0.07); }
    .ps-icon { width: 26px; height: 26px; background: rgba(197,147,39,0.12); border-radius: 7px; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
    .ps-icon svg { width: 12px; stroke: #F5D061; fill: none; stroke-width: 2.5; }
    .ps-lbl { font-size: 8.5px; font-weight: 700; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; text-align: center; }
    .ps-val { font-size: 12.5px; font-weight: 800; color: #F5D061; }

    /* ====== MENU LIST ====== */
    .p-wrap { margin-bottom: 15px; padding: 0 16px; }
    .pm-title { font-size: 9.5px; font-weight: 800; color: rgba(255,255,255,0.35); border-left: 2px solid rgba(197,147,39,0.5); padding-left: 8px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
    .pm-list { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; overflow: hidden; }
    .pm-item { display: flex; align-items: center; padding: 11px 14px; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.04); transition: 0.2s; }
    .pm-item:active { background: rgba(255,255,255,0.04); }
    .pm-item:last-child { border-bottom: none; }
    .pmi-icon { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; }
    .pmi-icon svg { width: 14px; stroke: currentColor; fill: none; stroke-width: 2; }
    .pmi-text { flex: 1; }
    .pmi-title { font-size: 12px; font-weight: 700; color: #fff; margin-bottom: 1px; }
    .pmi-desc { font-size: 9.5px; font-weight: 500; color: rgba(255,255,255,0.4); }
    .pmi-arrow svg { width: 14px; stroke: rgba(255,255,255,0.2); fill: none; stroke-width: 2; }

    /* LOGOUT BTN */
    .btn-logout {
        margin: 5px 16px 30px; display: flex; align-items: center; justify-content: center; gap: 8px;
        background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.2);
        padding: 12px; border-radius: 12px; font-size: 12px; font-weight: 700; color: #f87171;
        text-decoration: none; transition: 0.2s; cursor: pointer;
    }
    .btn-logout:active { background: rgba(239,68,68,0.12); }
    .btn-logout svg { width: 16px; stroke: currentColor; fill: none; stroke-width: 2; }

    /* ====== MODAL & FORM ====== */
    .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
    .modal-bg.show { display: flex; }
    .mod { background: #111; border: 1px solid #333; width: 100%; max-width: 400px; border-radius: 16px; overflow: hidden; animation: modIn 0.3s cubic-bezier(0.2,1,0.3,1); }
    @keyframes modIn { from{transform:scale(0.9);opacity:0;} to{transform:scale(1);opacity:1;} }
    .mod-head { display: flex; justify-content: space-between; align-items: center; padding: 16px; border-bottom: 1px solid #222; }
    .mod-title { font-size: 13px; font-weight: 800; color: #F5D061; }
    .mod-close { background: rgba(255,255,255,0.05); border: none; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .mod-close svg { width: 14px; stroke: #999; fill: none; stroke-width: 2; }
    .mod-body { padding: 16px; }
    .f-group { margin-bottom: 12px; }
    .f-lbl { display: block; font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; margin-bottom: 6px; }
    .f-inp { width: 100%; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 600; font-family: 'Poppins', sans-serif; color: #fff; outline: none; transition: border 0.2s; }
    .f-inp:focus { border-color: #F5D061; }
    .f-btn { width: 100%; padding: 12px; border: none; background: linear-gradient(135deg, #C59327 0%, #F5D061 100%); color: #111; font-size: 13px; font-weight: 800; font-family: 'Poppins', sans-serif; border-radius: 10px; cursor: pointer; }

    .alert { margin: 0 0 12px; padding: 10px 12px; border-radius: 10px; font-size: 11px; font-weight: 600; text-align: center; }
    .alert-ok  { background: rgba(16,185,129,0.1); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
    .alert-err { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }
</style>
</head>
<body>
<div class="app">

    <!-- HEADER -->
    <div class="p-header">
        <div class="p-brand"><?= htmlspecialchars($app_name) ?></div>
        <div class="p-actions">
            <a href="#" class="p-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><span class="badge"></span></a>
            <a href="#" class="p-btn"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></a>
        </div>
    </div>

    <!-- USER SECTION -->
    <div class="p-user">
        <div class="pu-ava">
            <?= strtoupper(substr($display_name, 0, 1)) ?>
        </div>
        <div class="pu-info">
            <div class="pu-name">
                <?= htmlspecialchars($display_name) ?>
                <div class="pu-badge"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> VIP</div>
            </div>
            <div class="pu-meta">
                <div><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> ID: <?= str_pad($user['id'], 6, '0', STR_PAD_LEFT) ?></div>
                <div><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> <?= htmlspecialchars($phone) ?></div>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="p-stats">
        <div class="ps-col">
            <div class="ps-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg></div>
            <div class="ps-lbl">Saldo</div>
            <div class="ps-val">Rp<?= number_format($saldo + $point, 0, ',', '.') ?></div>
        </div>
        <div class="ps-col">
            <div class="ps-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
            <div class="ps-lbl">Penghasilan</div>
            <div class="ps-val">Rp<?= number_format($profit, 0, ',', '.') ?></div>
        </div>
        <div class="ps-col">
            <div class="ps-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg></div>
            <div class="ps-lbl">Profit Rate</div>
            <div class="ps-val">0%</div>
        </div>
    </div>

    <!-- AKUN GROUP -->
    <div class="p-wrap">
        <div class="pm-title">Akun</div>
        <div class="pm-list">
            <a href="<?= base_url('pages/agent') ?>" class="pm-item">
                <div class="pmi-icon" style="background: rgba(59,130,246,0.15); color: #2563EB;"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Tautan Undangan</div>
                    <div class="pmi-desc">Bagikan link referral Anda</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
            <a href="<?= base_url('pages/bank') ?>" class="pm-item">
                <div class="pmi-icon" style="background: rgba(16,185,129,0.15); color: #059669;"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Rekening Bank</div>
                    <div class="pmi-desc">Kelola rekening penarikan</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
            <a href="javascript:void(0)" onclick="openMod('modPass')" class="pm-item">
                <div class="pmi-icon" style="background: rgba(139,92,246,0.15); color: #7C3AED;"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Ubah Kata Sandi</div>
                    <div class="pmi-desc">Perbarui keamanan akun</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
            <a href="<?= base_url('pages/checkin') ?>" class="pm-item">
                <div class="pmi-icon" style="background: rgba(245,158,11,0.15); color: #D97706;"><svg viewBox="0 0 24 24"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Bonus Harian</div>
                    <div class="pmi-desc">Klaim bonus tambahan Anda</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
        </div>
    </div>

    <!-- RIWAYAT GROUP -->
    <div class="p-wrap">
        <div class="pm-title">Riwayat</div>
        <div class="pm-list">
            <a href="<?= base_url('pages/history') ?>" class="pm-item">
                <div class="pmi-icon" style="background: rgba(236,72,153,0.15); color: #DB2777;"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Riwayat Transaksi</div>
                    <div class="pmi-desc">Semua catatan transaksi</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
        </div>
    </div>

    <!-- BANTUAN GROUP -->
    <div class="p-wrap">
        <div class="pm-title">Bantuan & Layanan</div>
        <div class="pm-list">
            <a href="#" class="pm-item">
                <div class="pmi-icon" style="background: rgba(99,102,241,0.15); color: #4F46E5;"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Download Aplikasi</div>
                    <div class="pmi-desc">Unduh APK aplikasi kami</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
            <a href="<?= $link_telegram ?>" target="_blank" class="pm-item">
                <div class="pmi-icon" style="background: rgba(14,165,233,0.15); color: #0284C7;"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Customer Service</div>
                    <div class="pmi-desc">Hubungi kami via Telegram</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
            <?php if (isset($user['uplink_level']) && ($user['uplink_level'] === 'demo' || $user['uplink_level'] === 'promotor')): ?>
            <a href="<?= base_url('pages/proof.php') ?>" class="pm-item">
                <div class="pmi-icon" style="background: rgba(220,38,38,0.15); color: #DC2626;"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Proof Generator</div>
                    <div class="pmi-desc">Buat bukti transaksi</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
            <a href="<?= base_url('pages/faker.php') ?>" class="pm-item">
                <div class="pmi-icon" style="background: rgba(16,185,129,0.15); color: #059669;"><svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Data Faker</div>
                    <div class="pmi-desc">Generate virtual data</div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></div>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- LOGOUT -->
    <a href="#" class="btn-logout" onclick="return confirmLogout(event)">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        Keluar dari Akun
    </a>

    <!-- MODAL PASSWORD -->
    <div class="modal-bg <?= ($open_modal==='password')?'show':'' ?>" id="modPass" onclick="bgClose(event)">
      <div class="mod">
        <div class="mod-head">
          <div class="mod-title">Ubah Kata Sandi</div>
          <button class="mod-close" onclick="closeMod('modPass')" type="button"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
        </div>
        <div class="mod-body">
          <?php if ($open_modal === 'password' && ($success || $error)): ?>
          <div class="alert <?= $success ? 'alert-ok' : 'alert-err' ?>">
            <?= htmlspecialchars($success ?: $error) ?>
          </div>
          <?php endif; ?>
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="f-group">
              <label class="f-lbl">Sandi Lama</label>
              <input type="password" name="old_password" class="f-inp" placeholder="Ketikan sandi lama" required>
            </div>
            <div class="f-group">
              <label class="f-lbl">Sandi Baru</label>
              <input type="password" name="new_password" class="f-inp" placeholder="Minimal 6 karakter" required>
            </div>
            <div class="f-group">
              <label class="f-lbl">Ulangi Sandi Baru</label>
              <input type="password" name="confirm_password" class="f-inp" placeholder="Konfirmasi sandi baru" required>
            </div>
            <button type="submit" class="f-btn">Perbarui Sandi</button>
          </form>
        </div>
      </div>
    </div>

</div>

<?php require '../lib/footer_user.php'; ?>

<script>
function confirmLogout(e) {
  e.preventDefault();
  var href = '<?= base_url("pages/logout") ?>';
  Swal.fire({
    icon: 'question',
    title: 'Keluar dari Akun?',
    text: 'Apakah Anda yakin ingin keluar?',
    showCancelButton: true,
    confirmButtonText: 'Ya, Keluar',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#18181b',
    background: '#111111',
    color: '#ffffff',
    reverseButtons: true,
    customClass: {
        title: 'swal2-title-custom',
        cancelButton: 'swal2-cancel-custom'
    }
  }).then(function(r) {
    if (r.isConfirmed) window.location.href = href;
  });
  return false;
}

function openMod(id){ document.getElementById(id).classList.add('show'); }
function closeMod(id){ document.getElementById(id).classList.remove('show'); }
function bgClose(e){ if(e.target.classList.contains('modal-bg')) e.target.classList.remove('show'); }
</script>
<style>
.swal2-title-custom { font-family: 'Poppins', sans-serif; font-size: 18px; }
.swal2-cancel-custom { color: #9ca3af !important; border: 1px solid #333 !important; }
</style>
</body>
</html>
