<?php
declare(strict_types=1);
require '../mainconfig.php';
require '../lib/check_session.php';
require_once __DIR__ . '/../lib/flash_message.php';

$page_type = 'profile';
$page_name = 'Akun Saya';

$uid = $db->real_escape_string($login['id']);
$user_query = $model->db_query($db, "id, phone, point, profit, saldo, status, rekening, no_rek, pemilik, bank_code, created_at, uplink_level, x_uniqueid, username", "users", "id = '".$uid."'");
$user = $user_query['rows'];

$saldo        = (float)($user['saldo'] ?? 0);
$profit       = profitDisplay($db, $login['id']) ? (float)($user['profit'] ?? 0) : 0;
$phone        = $user['phone'] ?? '-';
$display_name = $user['username'] ?? substr($phone, 0, 8).'...';

// Tambahan Query
$q_dep = mysqli_query($db, "SELECT SUM(amount) as t FROM topups WHERE user_id='$uid' AND status='Success'");
$t_deposit = (float)($q_dep ? mysqli_fetch_assoc($q_dep)['t'] : 0);

$q_wd = mysqli_query($db, "SELECT SUM(amount) as t FROM withdraws WHERE user_id='$uid' AND status='Success'");
$t_wd = (float)($q_wd ? mysqli_fetch_assoc($q_wd)['t'] : 0);

$q_inv = mysqli_query($db, "SELECT SUM(harga) as t FROM orders WHERE user_id='$uid' AND status='Active'");
$t_invest = (float)($q_inv ? mysqli_fetch_assoc($q_inv)['t'] : 0);

$settings      = $model->db_query($db, "*", "settings", "id='1'")['rows'];
$app_name      = $settings['title'] ?? 'Platform';

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
    body { font-family: 'Poppins', sans-serif; background: #012b26; color: #fff; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
    .app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; background: #012b26; }

    /* HEADER */
    .r-header { background: linear-gradient(135deg, #023e35 0%, #01312b 100%); padding: 20px 20px 100px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; position: relative; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1; }
    .rh-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
    .rht-left { display: flex; align-items: center; gap: 12px; }
    .rht-ava { width: 44px; height: 44px; border-radius: 12px; background: #facc15; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; color: #012b26; border: 1px solid rgba(250,204,21,0.5); }
    .rht-info { display: flex; flex-direction: column; }
    .rhti-greet { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.5px; }
    .rhti-name { font-size: 14px; font-weight: 800; color: #fff; margin-bottom: 1px; }
    .rhti-phone { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 4px; }
    .rht-btn { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; transition: 0.2s; cursor: pointer; }
    .rht-btn:active { background: rgba(255,255,255,0.1); }

    /* SALDO CARD */
    .saldo-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(250,204,21,0.2); border-radius: 16px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
    .sc-info { display: flex; flex-direction: column; gap: 4px; }
    .sc-lbl { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px;}
    .sc-val { font-size: 22px; font-weight: 800; color: #fff; line-height: 1; }
    .sc-icon { width: 44px; height: 44px; border-radius: 12px; background: #fff; color: #023e35; display: flex; align-items: center; justify-content: center; font-size: 18px; }

    /* STATS 2x2 */
    .r-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 0 20px; margin-top: -75px; position: relative; z-index: 2; margin-bottom: 16px; }
    .rs-card { background: #023e35; border-radius: 16px; padding: 14px 16px; border: 1px solid #035246; box-shadow: 0 6px 15px rgba(0,0,0,0.15); display: flex; flex-direction: column; justify-content: center; }
    .rs-icon { width: 34px; height: 34px; border-radius: 10px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; font-size: 16px; color: #facc15; margin-bottom: 12px; }
    .rs-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;}
    .rs-val { font-size: 14px; font-weight: 800; color: #fff; line-height: 1; }
    .rs-val.y { color: #facc15; }

    /* QUICK MENU */
    .qm-card { background: #023e35; border-radius: 16px; padding: 20px 20px 10px; margin: 0 20px 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .c-m-title { font-size: 13px; font-weight: 800; color: #fff; margin-bottom: 16px; }
    .qm-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .qm-btn { display: flex; flex-direction: column; align-items: center; text-decoration: none; padding-bottom: 10px; transition: 0.2s;}
    .qm-btn:active { transform: scale(0.95); }
    .qmb-icon { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; margin-bottom: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .qmb-lbl { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.8); }

    /* HISTORY LIST */
    .hl-card { background: #023e35; border-radius: 16px; margin: 0 20px 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden; }
    .hl-head { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px dashed rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); }
    .hlh-icon { width: 28px; height: 28px; border-radius: 8px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; font-size: 12px; color: #facc15; }
    .hlh-title { font-size: 12px; font-weight: 800; color: #fff; }
    
    .hl-list { display: flex; flex-direction: column; }
    .hl-item { display: flex; align-items: center; gap: 14px; padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.04); text-decoration: none; transition: 0.2s; }
    .hl-item:active { background: rgba(255,255,255,0.02); }
    .hl-item:last-child { border-bottom: none; }
    .hli-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; color: #fff; flex-shrink: 0;}
    .hli-info { flex: 1; }
    .hlii-title { font-size: 12px; font-weight: 700; color: #fff; margin-bottom: 2px; }
    .hlii-desc { font-size: 10px; color: rgba(255,255,255,0.5); }
    .hli-arrow { color: rgba(255,255,255,0.3); font-size: 12px; }

</style>
</head>
<body>
<div class="app">

    <!-- HEADER CURVED -->
    <div class="r-header">
        <div class="rh-top">
            <div class="rht-left">
                <div class="rht-ava">
                    <i class="fa-regular fa-user"></i>
                </div>
                <div class="rht-info">
                    <div class="rhti-greet">SELAMAT MALAM</div>
                    <div class="rhti-name"><?= htmlspecialchars($display_name) ?></div>
                    <div class="rhti-phone"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($phone) ?></div>
                </div>
            </div>
            <a href="#" class="rht-btn" onclick="return confirmLogout(event)">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
        <div class="saldo-card">
            <div class="sc-info">
                <div class="sc-lbl">TOTAL SALDO</div>
                <div class="sc-val">Rp <?= number_format($saldo, 0, ',', '.') ?></div>
            </div>
            <div class="sc-icon">
                <i class="fa-solid fa-bolt"></i>
            </div>
        </div>
    </div>

    <!-- 2x2 STATS -->
    <div class="r-stats">
        <div class="rs-card">
            <div class="rs-icon"><i class="fa-regular fa-credit-card"></i></div>
            <div class="rs-lbl">DEPOSIT</div>
            <div class="rs-val">Rp <?= number_format($t_deposit, 0, ',', '.') ?></div>
        </div>
        <div class="rs-card">
            <div class="rs-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="rs-lbl">PENARIKAN</div>
            <div class="rs-val">Rp <?= number_format($t_wd, 0, ',', '.') ?></div>
        </div>
        <div class="rs-card">
            <div class="rs-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="rs-lbl">TOTAL INVESTASI</div>
            <div class="rs-val">Rp <?= number_format($t_invest, 0, ',', '.') ?></div>
        </div>
        <div class="rs-card">
            <div class="rs-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
            <div class="rs-lbl">PROFIT HARIAN</div>
            <div class="rs-val y">+Rp <?= number_format($profit, 0, ',', '.') ?></div>
        </div>
    </div>

    <!-- QUICK MENU -->
    <div class="qm-card">
        <div class="c-m-title">Menu cepat</div>
        <div class="qm-grid">
            <a href="<?= base_url('pages/deposit') ?>" class="qm-btn">
                <div class="qmb-icon" style="background: linear-gradient(135deg, #a855f7, #6b21a8);"><i class="fa-solid fa-wallet"></i></div>
                <div class="qmb-lbl">Top Up</div>
            </a>
            <a href="<?= base_url('pages/withdraw') ?>" class="qm-btn">
                <div class="qmb-icon" style="background: linear-gradient(135deg, #ec4899, #be185d);"><i class="fa-solid fa-money-bill-transfer"></i></div>
                <div class="qmb-lbl">Tarik</div>
            </a>
            <a href="<?= base_url('pages/plans') ?>" class="qm-btn">
                <div class="qmb-icon" style="background: linear-gradient(135deg, #f59e0b, #b45309);"><i class="fa-solid fa-chart-line"></i></div>
                <div class="qmb-lbl">Investasi</div>
            </a>
            <a href="<?= base_url('pages/checkin') ?>" class="qm-btn">
                <div class="qmb-icon" style="background: linear-gradient(135deg, #d946ef, #a21caf);"><i class="fa-solid fa-gift"></i></div>
                <div class="qmb-lbl">Bonus</div>
            </a>
        </div>
    </div>

    <!-- RIWAYAT TRANSAKSI -->
    <div class="hl-card">
        <div class="hl-head">
            <div class="hlh-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="hlh-title">Riwayat transaksi</div>
        </div>
        <div class="hl-list">
            <a href="<?= base_url('pages/history') ?>?tab=depo" class="hl-item">
                <div class="hli-icon" style="background: rgba(16,185,129,0.15); color: #10b981;"><i class="fa-solid fa-receipt"></i></div>
                <div class="hli-info">
                    <div class="hlii-title">Riwayat top up</div>
                    <div class="hlii-desc">Deposit & isi saldo</div>
                </div>
                <div class="hli-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            </a>
            <a href="<?= base_url('pages/history') ?>?tab=inv" class="hl-item">
                <div class="hli-icon" style="background: rgba(245,158,11,0.15); color: #f59e0b;"><i class="fa-solid fa-chart-line"></i></div>
                <div class="hli-info">
                    <div class="hlii-title">Riwayat investasi</div>
                    <div class="hlii-desc">Paket yang diambil</div>
                </div>
                <div class="hli-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            </a>
            <a href="<?= base_url('pages/history') ?>?tab=wd" class="hl-item">
                <div class="hli-icon" style="background: rgba(236,72,153,0.15); color: #ec4899;"><i class="fa-solid fa-money-bill-transfer"></i></div>
                <div class="hli-info">
                    <div class="hlii-title">Riwayat penarikan</div>
                    <div class="hlii-desc">Pencairan dana</div>
                </div>
                <div class="hli-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            </a>
            <a href="<?= base_url('pages/bank') ?>" class="hl-item">
                <div class="hli-icon" style="background: rgba(99,102,241,0.15); color: #6366f1;"><i class="fa-solid fa-building-columns"></i></div>
                <div class="hli-info">
                    <div class="hlii-title">Rekening Bank</div>
                    <div class="hlii-desc">Ubah rekening bank / dompet</div>
                </div>
                <div class="hli-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            </a>
        </div>
    </div>
</div>

<?php require '../lib/footer_user.php'; ?>

<script>
function confirmLogout(e) {
  e.preventDefault();
  Swal.fire({
    icon: 'question',
    title: 'Keluar?',
    text: 'Apakah Anda yakin ingin keluar?',
    showCancelButton: true,
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#023e35',
    cancelButtonColor: '#333',
    background: '#012b26',
    color: '#fff',
    reverseButtons: true
  }).then((r) => {
    if (r.isConfirmed) window.location.href = '<?= base_url("pages/logout") ?>';
  });
  return false;
}
</script>
</body>
</html>
