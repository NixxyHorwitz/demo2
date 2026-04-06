<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'mainconfig.php';
require 'lib/check_session.php';
require 'lib/flash_message.php';
require 'lib/is_login.php';
require 'cron/auto_profit.php';
require 'cron/auto_status.php';

use \Firebase\JWT\JWT;

if (isset($_COOKIE['X_SESSION'])) {
    try {
        $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], ['HS256']);
        $check_user = $model->db_query($db, "*", "users",
            "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_SESSION']) . "'"
        );
        if ($check_user['count'] !== 1 || $check_user['rows']['status'] != 'Active') {
            logout(); exit(header("Location: " . base_url('auth/login')));
        }
    } catch (Exception $e) {
        logout(); exit(header("Location: " . base_url('auth/login')));
    }
} else {
    exit(header("Location: " . base_url('auth/login')));
}

$user_id    = $check_user['rows']['id'];
$user_level = $check_user['rows']['level'] ?? 'Member';

if (!function_exists('protect')) {
    function protect($s) { return is_scalar($s) ? trim((string)$s) : ''; }
}

function formatHarga($angka) {
    $angka = (int)$angka;
    if ($angka >= 1000) {
        $hasil = number_format($angka / 1000, 1);
        $hasil = rtrim(rtrim($hasil, '0'), '.');
        return $hasil . 'k';
    }
    return $angka;
}

$sq        = mysqli_query($db, "SELECT * FROM settings LIMIT 1");
$settings  = $sq ? mysqli_fetch_assoc($sq) : [];
$site_name = $settings['title']         ?? 'Platform';
$web_logo  = $settings['web_logo']      ?? '';
$tg_link   = $settings['link_telegram'] ?? '#';

$usnnya      = $check_user['rows']['username'] ?? 'Member';
$topups      = $check_user['rows']['saldo']    ?? 0;
$ubal        = $check_user['rows']['point']    ?? 0;
$uprofit_val = profitDisplay($db, $user_id) ? (float)($check_user['rows']['profit'] ?? 0) : 0;

/* ── Banners ── */
$banners = [];
$baq = mysqli_query($db, "SELECT * FROM banners WHERE is_active=1 ORDER BY order_position ASC LIMIT 5");
if ($baq) while ($r = mysqli_fetch_assoc($baq)) $banners[] = $r;

/* ── Product Logic (from plans) ── */
$saldo_akun = $topups;
$app_images = [];
$img_q = $db->query("SELECT * FROM app_images");
if ($img_q) while ($r = $img_q->fetch_assoc()) $app_images[$r['image_key']] = $r['image_url'];
$prod_img_url = !empty($app_images['product_image']) ? $app_images['product_image'] : 'https://placehold.co/100x120/eee/999?text=IMG';

$all_kat=[];$qk=mysqli_query($db,"SELECT * FROM produk_kategori WHERE is_hidden=0 ORDER BY urutan ASC,id ASC");
while($r=mysqli_fetch_assoc($qk))$all_kat[]=$r;

$all_produk=[];$qp=mysqli_query($db,"SELECT p.*,k.nama as kat_nama,k.is_locked,k.syarat,tb.nama_produk as to_buy_nama,tb.harga as to_buy_harga FROM produk_investasi p LEFT JOIN produk_kategori k ON k.id=p.kategori_id LEFT JOIN produk_investasi tb ON tb.id=p.to_buy ORDER BY k.urutan ASC,p.harga ASC");
while($r=mysqli_fetch_assoc($qp))$all_produk[]=$r;
$pby=[];foreach($all_produk as $p)$pby[$p['kategori_id']][]=$p;

$owned=[];$qo=mysqli_query($db,"SELECT produk_id,COUNT(*) as cnt FROM orders WHERE user_id='$user_id' AND status='Active' GROUP BY produk_id");
while($r=mysqli_fetch_assoc($qo))$owned[$r['produk_id']]=(int)$r['cnt'];

/* ── Stats Logic ── */
$total_investasi_berjalan = array_sum($owned);

$uid_safe = mysqli_real_escape_string($db, (string)$user_id);
$q_stat = mysqli_query($db, "SELECT COALESCE(SUM(amount),0) AS total_komisi FROM refferals WHERE user_id='$uid_safe'");
$total_komisi_ref = (float)(mysqli_fetch_assoc($q_stat)['total_komisi'] ?? 0);

$q_l1 = mysqli_query($db, "SELECT COUNT(*) AS total FROM users WHERE uplink='$uid_safe'");
$total_l1 = (int)(mysqli_fetch_assoc($q_l1)['total'] ?? 0);
$q_l2 = mysqli_query($db, "SELECT COUNT(u2.id) AS total FROM users u1 INNER JOIN users u2 ON u2.uplink = u1.id WHERE u1.uplink = '$uid_safe'");
$total_l2 = (int)(mysqli_fetch_assoc($q_l2)['total'] ?? 0);
$q_l3 = mysqli_query($db, "SELECT COUNT(u3.id) AS total FROM users u1 INNER JOIN users u2 ON u2.uplink = u1.id INNER JOIN users u3 ON u3.uplink = u2.id WHERE u1.uplink = '$uid_safe'");
$total_l3 = (int)(mysqli_fetch_assoc($q_l3)['total'] ?? 0);

$lvl2_on = ($settings['referral_lvl2_status'] ?? 'off') === 'on';
$lvl3_on = ($settings['referral_lvl3_status'] ?? 'off') === 'on';
$total_member_ref = $total_l1 + ($lvl2_on ? $total_l2 : 0) + ($lvl3_on ? $total_l3 : 0);

$tcount_q = mysqli_query($db, "SELECT COUNT(*) as c FROM daily_checkin WHERE user_id='$uid_safe'");
$total_checkin_count = (int)(mysqli_fetch_assoc($tcount_q)['c'] ?? 0);

$today = date('Y-m-d');
$cq = mysqli_query($db, "SELECT id FROM daily_checkin WHERE user_id='$uid_safe' AND tanggal='$today'");
$already_checkin = ($cq && mysqli_num_rows($cq) > 0);

/* ── Popup ── */
$show_popup = false; $popup_data = null;
$pq = mysqli_query($db, "SELECT * FROM news_popup WHERE is_active=1 ORDER BY id DESC LIMIT 1");
if ($pq && mysqli_num_rows($pq) > 0) {
    $pop    = mysqli_fetch_assoc($pq);
    $pop_id = $pop['id']; $pop_upd = $pop['updated_at'];
    $vq = mysqli_query($db, "SELECT * FROM news_popup_viewed WHERE user_id='$user_id' AND popup_id='$pop_id' AND popup_updated_at >= '$pop_upd'");
    if (!$vq || mysqli_num_rows($vq) == 0) {
        $show_popup = true;
        $popup_data = [
            'id'          => $pop_id,
            'title'       => htmlspecialchars($pop['title'], ENT_QUOTES),
            'description' => nl2br(htmlspecialchars($pop['description'], ENT_QUOTES)),
            'image'       => !empty($pop['image'])       ? htmlspecialchars($pop['image'],       ENT_QUOTES) : null,
            'button_url'  => !empty($pop['button_url'])  ? htmlspecialchars($pop['button_url'],  ENT_QUOTES) : null,
            'button_text' => !empty($pop['button_text']) ? htmlspecialchars($pop['button_text'], ENT_QUOTES) : null,
            'updated_at'  => $pop_upd,
        ];
    }
}

/* ── Lock check ── */
function hasLockProfitProduct($db, $user_id) {
    $uid = (int)$user_id;
    $r   = mysqli_query($db, "SELECT 1 FROM orders o JOIN produk_investasi p ON p.id=o.produk_id JOIN produk_kategori k ON k.id=p.kategori_id WHERE o.user_id='$uid' AND o.status='Active' AND k.is_locked=1 LIMIT 1");
    return $r && mysqli_num_rows($r) > 0;
}
$haslock = hasLockProfitProduct($db, (int)$user_id);

?>
<?php require 'lib/header_user.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
<title><?= htmlspecialchars($site_name) ?> Dashboard</title>
<link rel="apple-touch-icon" href="<?= base_url('assets/images/' . $web_logo) ?>">
<link rel="shortcut icon" type="image/x-icon" href="<?= base_url('assets/images/' . $web_logo) ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* Reset & Base */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #012b26; font-family: 'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }

.app {
  max-width: 480px;
  margin: 0 auto;
  background: #012b26;
  min-height: 100vh;
  padding-bottom: 90px;
  position: relative;
  overflow-x: hidden;
}

/* TOP HEADER (Green Gradient) */
.top-header {
  background: linear-gradient(135deg, #023e35 0%, #01312b 100%);
  border-bottom-left-radius: 40px;
  border-bottom-right-radius: 40px;
  padding: 24px 5px 45px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
}

.top-header::after {
  content: ''; position: absolute; right: -50px; top: -50px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 70%); border-radius: 50%; pointer-events: none;
}

.header-row {
  display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2; margin-bottom: 24px;
}

.user-info { display: flex; align-items: center; gap: 12px; }
.logo-box {
  width: 46px; height: 46px; background: #023e35; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); border: 2px solid #facc15;
}
.logo-box img { width: 100%; height: 100%; object-fit: contain; }

.user-text h2 { font-size: 16px; font-weight: 800; color: #facc15; line-height: 1.2; }
.user-text p { font-size: 12px; color: rgba(255,255,255,0.85); margin-top: 2px; font-weight: 500;}

.header-actions { display: flex; align-items: center; gap: 10px; }
.action-btn {
  width: 38px; height: 38px; background: rgba(250, 204, 21, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #facc15; position: relative; cursor: pointer; transition: 0.2s; border: 1px solid rgba(250,204,21,0.2);
}
.action-btn:active { transform: scale(0.95); }
.action-btn i { font-size: 18px; }
.badge-dot {
  position: absolute; top: -2px; right: -2px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 800; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 2px solid #023e35;
}

/* MAIN CARD (Floating overlapping) */
.wallet-card {
  background: #023e35;
  border-radius: 20px;
  padding: 16px 20px;
  color: #ffffff;
  position: relative;
  z-index: 2;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  margin: -60px 20px 0;
  border: 1px solid #facc15;
  margin-top: 2rem;
}

.wc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;}
.wc-label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #facc15;}
.wc-label svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }
.wc-eye { width: 26px; height: 26px; background: rgba(250, 204, 21, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #facc15;}

.wc-balance { font-size: 28px; font-weight: 800; display: flex; align-items: flex-start; gap: 4px; line-height: 1; letter-spacing: -0.5px; color: #ffffff;}
.wc-balance span { font-size: 14px; font-weight: 600; margin-top: 4px; opacity: 0.8; color: #facc15;}

.wc-stats {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 18px;
}
.wc-stat { background: rgba(0,0,0,0.2); border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 4px; border: 1px solid rgba(255,255,255,0.05); }
.wc-stat-lbl { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 500; color: #cbd5e1;}
.wc-stat-lbl i { font-size: 12px; color: #facc15; }
.wc-stat-val { font-size: 14px; font-weight: 700; color: #ffffff; }
.wc-stat-val.green { color: #facc15; }

/* MENU ICONS */
.q-menus { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; padding: 16px; position: relative; z-index: 5; }
.q-btn { display: flex; flex-direction: column; align-items: center; gap: 6px; text-decoration: none; background: #023e35; border-radius: 14px; padding: 12px 4px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.15); transition: 0.2s;}
.q-icon-box { color: #facc15; font-size: 18px; }
.q-lbl { font-size: 10px; font-weight: 600; color: #facc15; text-align: center; }
.q-btn:active { transform: scale(0.95); }

/* MID STATS FLEX */
.mid-stats-flex { display: flex; overflow-x: auto; gap: 10px; padding: 0 16px 16px; scrollbar-width: none; }
.mid-stats-flex::-webkit-scrollbar { display: none; }
.mid-card { flex-shrink: 0; width: 170px; background: #023e35; border-radius: 14px; padding: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; border: 1px solid #035246; text-decoration: none;}
.mid-card.border-dashed { border: 1px dashed rgba(250,204,21,0.5); width: auto; padding: 12px 16px;}
.mid-card.border-dashed .mc-text { flex-direction: row; align-items: center; justify-content: center; width: 100%; gap: 6px;}
.mid-card .mc-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink:0;}
.mc-text { display: flex; flex-direction: column; gap: 2px;}
.mc-lbl { font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.7); }
.mc-val { font-size: 13px; font-weight: 800; color: #ffffff; line-height: 1;}
.mc-val.plus { color: #facc15; }



/* ACTION BANNERS */
.action-banners { padding: 0 16px 20px; display: flex; flex-direction: column; gap: 8px; }
.a-banner {
  display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.2); position: relative; overflow: hidden; border: 1px solid rgba(250, 204, 21, 0.2);
}
.a-banner.dark { background: #facc15; color: #012b26; border-color: #facc15;}

.a-icon {
  width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(250,204,21,0.1); flex-shrink: 0; z-index: 2; color: #facc15; font-size: 16px;
}
.a-banner.dark .a-icon { background: rgba(1,43,38,0.1); color: #012b26;}

.a-text { flex: 1; padding: 0 12px; z-index: 2; }
.a-banner.dark .a-text h4 { font-size: 13px; font-weight: 800; color: #012b26; margin-bottom: 2px; }
.a-banner.dark .a-text p { font-size: 10px; color: rgba(1,43,38,0.8); line-height: 1.2; }
.a-banner.light { background: #023e35; }
.a-banner.light .a-text h4 { font-size: 14px; font-weight: 700; color: #facc15; margin-bottom: 4px; }
.a-banner.light .a-text p { font-size: 11px; color: rgba(255,255,255,0.7); line-height: 1.3; }

.a-chevron { display: flex; align-items: center; justify-content: center; color: #facc15; font-size: 14px; flex-shrink: 0; z-index: 2;}
.a-banner.dark .a-chevron { color: #012b26; }

/* SECTION HEADER */
.sec-head { display: flex; align-items: center; justify-content: space-between; padding: 0 16px 14px; }
.sec-head h3 { font-size: 16px; font-weight: 800; color: #facc15; display: flex; align-items: center; gap: 8px; }
.sec-head h3::before { content: ''; width: 5px; height: 16px; background: #facc15; border-radius: 4px; display: inline-block; }
.sec-head a { font-size: 11px; font-weight: 600; color: #ffffff; text-decoration: none; }

/* POPUP CSS */
.pop-ov { position: fixed; inset: 0; background: rgba(1, 43, 38, 0.8); backdrop-filter: blur(4px); display: none; align-items: flex-start; justify-content: center; z-index: 9999; }
.pop-card { background: #023e35; border: 1px solid #facc15; border-radius: 0 0 30px 30px; width: 100%; max-width: 480px; padding: 0 0 24px 0; position: relative; animation: slideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1); box-shadow: 0 20px 40px rgba(0,0,0,0.5); display: flex; flex-direction: column; align-items: center;}
@keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }

.pop-close { position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; background: rgba(0,0,0,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #facc15; cursor: pointer; transition: 0.2s; z-index: 10;}
.pop-close:active { background: rgba(0,0,0,0.4); }
.pop-logo { background: #012b26; border-radius: 16px; padding: 16px; margin: 30px auto 20px; display: inline-block; max-width: 80%; border: 1px solid #035246; }
.pop-logo img { height: 60px; object-fit: contain; width: 100%; border-radius:8px;}
.pop-title { font-size: 18px; font-weight: 800; color: #facc15; margin-bottom: 6px; line-height: 1.3;}
.pop-sub { font-size: 12px; font-weight: 500; color: rgba(255,255,255,0.7); margin-bottom: 20px; }
.pop-desc { font-size: 12.5px; color: #ffffff; line-height: 1.6; margin-bottom: 24px; text-align: justify; text-align-last: center; padding: 0 20px;}
.pop-btn-gold { display: flex; align-items: center; justify-content: center; padding: 14px; border-radius: 12px; background: #facc15; color: #012b26; text-decoration: none; font-size: 14px; font-weight: 800; margin: 0 20px 12px; box-shadow: 0 6px 15px rgba(250, 204, 21, 0.2); transition: 0.2s;}
.pop-btn-gold:active { transform: scale(0.97); }
.pop-link { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.5); text-decoration: none; cursor: pointer; display: block; margin-bottom: 10px;}
.pop-handle { position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); width: 44px; height: 44px; background: #023e35; border-radius: 50%; border: 2px solid #facc15; box-shadow: 0 4px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; z-index: 3; color: #facc15; font-size: 18px; cursor:pointer;}




</style>
</head>
<body>
<div class="app">

  <!-- TOP HEADER -->
  <div class="top-header">
    <div class="header-row">
      <div class="user-info">
        <div class="logo-box">
           <?php if(!empty($web_logo)): ?>
              <img src="<?= base_url('assets/images/' . $web_logo) ?>" alt="Logo">
           <?php else: ?>
              <svg viewBox="0 0 36 36" fill="none"><rect x="18" y="2" width="22" height="22" transform="rotate(45 18 2)" fill="#FEF3C7" rx="3"/><path d="M10 24L18 10L26 24" stroke="#B45309" stroke-width="3" stroke-linecap="round"/><path d="M14 24V16L18 20L22 16V24" stroke="#D97706" stroke-width="2" stroke-linecap="round"/><circle cx="18" cy="18" r="1.5" fill="#B45309"/></svg>
           <?php endif; ?>
        </div>
        <div class="user-text">
          <h2>Selamat Datang</h2>
          <p><?= htmlspecialchars($site_name) ?> Dashboard</p>
        </div>
      </div>
      <div class="header-actions">
        <div class="action-btn" onclick="openPopup()">
          <i class="fa-regular fa-bell"></i>
          <span class="badge-dot">3</span>
        </div>
        <a href="<?= base_url('pages/profile') ?>" style="text-decoration:none;" class="action-btn">
          <i class="fa-solid fa-gear"></i>
        </a>
      </div>
    </div>

    <!-- WALLET CARD (New Shape) -->
    <div class="wallet-card">
      <div class="wc-top">
        <div class="wc-label" style="font-size:10px; letter-spacing: 0.5px; opacity:0.8;">
          TOTAL KEKAYAAN BERSIH
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="border: 1px solid rgba(250,204,21,0.5); padding: 3px 8px; border-radius:20px; font-size:9px; font-weight:700; color:#facc15;">LIVE MARKET</div>
            <div class="wc-eye" id="toggleBalance">
              <i class="fa-regular fa-eye"></i>
            </div>
        </div>
      </div>
      <div class="wc-balance" id="valBalance" style="margin-bottom:16px;">
        <span>Rp</span> <?= number_format($ubal, 0, ',', '.') ?>
      </div>
      <div class="wc-stats">
        <div class="wc-stat" style="text-align:left; align-items:flex-start;">
          <div class="wc-stat-lbl" style="font-size:9px; font-weight:700; opacity:0.9;">TOTAL DOMPET</div>
          <div class="wc-stat-val" id="valDompet">Rp <?= number_format($ubal, 0, ',', '.') ?></div>
        </div>
        <div class="wc-stat" style="text-align:left; align-items:flex-start;">
          <div class="wc-stat-lbl" style="font-size:9px; font-weight:700; opacity:0.9;">NILAI INVESTASI</div>
          <div class="wc-stat-val" id="valInvestasi">Rp <?= number_format($topups, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- QUICK MENUS (4 Icons) -->
  <div class="q-menus">
    <a href="<?= base_url('pages/deposit') ?>" class="q-btn">
      <div class="q-icon-box"><i class="fa-solid fa-wallet"></i></div>
      <div class="q-lbl">Top Up</div>
    </a>
    <a href="<?= base_url('pages/withdraw') ?>" class="q-btn">
      <div class="q-icon-box"><i class="fa-solid fa-money-bill-transfer"></i></div>
      <div class="q-lbl">Tarik</div>
    </a>
    <a href="<?= base_url('pages/product') ?>" class="q-btn">
      <div class="q-icon-box"><i class="fa-solid fa-arrow-trend-up"></i></div>
      <div class="q-lbl">Porto</div>
    </a>
    <a href="<?= base_url('pages/checkin') ?>" class="q-btn">
      <div class="q-icon-box"><i class="fa-solid fa-gift"></i></div>
      <div class="q-lbl">Bonus</div>
    </a>
  </div>

  <!-- MID FLEX STATS -->
  <div class="mid-stats-flex">
      <div class="mid-card">
          <div class="mc-icon" style="color:#a78bfa;background:rgba(167,139,250,0.15);"><i class="fa-solid fa-arrow-trend-up"></i></div>
          <div class="mc-text">
              <div class="mc-lbl">INVESTASI</div>
              <div class="mc-val" id="valInvestasiMid">Rp <?= number_format($topups, 0, ',', '.') ?></div>
          </div>
      </div>
      <div class="mid-card">
          <div class="mc-icon" style="color:#f472b6;background:rgba(244,114,182,0.15);"><i class="fa-solid fa-arrow-up"></i></div>
          <div class="mc-text">
              <div class="mc-lbl">PROFIT</div>
              <div class="mc-val plus" id="valProfitMid">+Rp <?= number_format($uprofit_val, 0, ',', '.') ?></div>
          </div>
      </div>
      <a href="<?= base_url('pages/history') ?>" class="mid-card border-dashed">
          <div class="mc-text" style="color:#facc15;font-weight:700;font-size:13px;">
              <span>Riwayat</span> <i class="fa-solid fa-arrow-right"></i>
          </div>
      </a>
  </div>

  <!-- ACTION BANNERS -->
  <div class="action-banners">
    <a href="<?= base_url('pages/agent') ?>" class="a-banner dark">
      <div class="a-icon"><i class="fa-solid fa-users"></i></div>
      <div class="a-text">
        <h4>Referral - komisi 30%</h4>
        <p>Ajak teman, dapat passive income</p>
      </div>
      <div class="a-chevron"><i class="fa-solid fa-arrow-right"></i></div>
    </a>
    
    <a href="<?= htmlspecialchars($tg_link ?? '#') ?>" class="a-banner light">
      <div class="a-icon"><i class="fa-solid fa-shield-halved"></i></div>
      <div class="a-text">
        <h4>Forum bukti</h4>
        <p>Bukti penarikan member</p>
      </div>
      <div class="a-chevron"><i class="fa-solid fa-arrow-right"></i></div>
    </a>

    <a href="<?= base_url('pages/history') ?>" class="a-banner light">
      <div class="a-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
      <div class="a-text">
        <h4>Riwayat penarikan</h4>
        <p>Status penarikan Anda</p>
      </div>
      <div class="a-chevron"><i class="fa-solid fa-arrow-right"></i></div>
    </a>
  </div>

  <div style="height: 30px;"></div>

</div><!-- .app -->

<!-- POPUP -->
<?php if ($show_popup && $popup_data): ?>
<div class="pop-ov" id="infoPopup">
  <div class="pop-card">
    <div class="pop-close" onclick="closePopup()"><i class="fa-solid fa-xmark"></i></div>
    
    <div class="pop-logo">
       <?php if(!empty($web_logo)): ?>
          <img src="<?= base_url('assets/images/' . $web_logo) ?>" alt="Logo">
       <?php else: ?>
          <h2 style="color:#facc15;font-weight:800;font-size:24px;margin:10px;"><?= htmlspecialchars($site_name) ?></h2>
       <?php endif; ?>
    </div>
    
    <div class="pop-title"><?= $popup_data['title'] ?></div>
    <div class="pop-sub">Platform Investasi Terpercaya</div>
    <div class="pop-desc">
       <?php if(!empty($popup_data['description'])): ?>
          <?= $popup_data['description'] ?>
       <?php else: ?>
          FMB Finance hadir sebagai platform investasi yang menggabungkan keamanan, transparansi, dan teknologi modern untuk membantu Anda mencapai kebebasan finansial.
       <?php endif; ?>
    </div>
    
    <a href="<?= htmlspecialchars($popup_data['button_url'] ?? $tg_link) ?>" target="_blank" class="pop-btn-gold">
      <i class="fa-solid fa-paper-plane" style="margin-right:6px;"></i> <?= $popup_data['button_text'] ?: 'Gabung Komunitas' ?>
    </a>
    <div class="pop-link" onclick="closePopup()">Nanti saja</div>
    
    <div class="pop-handle" onclick="closePopup()"><i class="fa-solid fa-bolt"></i></div>
  </div>
</div>
<?php endif; ?>

<script>
/* Toggle Balance */
let isHidden = false;
let realBalance = '<?= number_format($ubal, 0, ',', '.') ?>';
let realTopup = '<?= number_format($topups, 0, ',', '.') ?>';
let realProfit = '<?= number_format($uprofit_val, 0, ',', '.') ?>';

document.getElementById('toggleBalance').addEventListener('click', function(){
  isHidden = !isHidden;
  let censor = '•••••••';
  if(isHidden) {
    document.getElementById('valBalance').innerHTML = '<span>Rp</span> ' + censor;
    if(document.getElementById('valDompet')) document.getElementById('valDompet').innerText = 'Rp ' + censor;
    if(document.getElementById('valInvestasi')) document.getElementById('valInvestasi').innerText = 'Rp ' + censor;
    if(document.getElementById('valInvestasiMid')) document.getElementById('valInvestasiMid').innerText = 'Rp ' + censor;
    if(document.getElementById('valProfitMid')) document.getElementById('valProfitMid').innerText = '+Rp ' + censor;
    this.innerHTML = '<i class="fa-regular fa-eye-slash"></i>';
  } else {
    document.getElementById('valBalance').innerHTML = '<span>Rp</span> ' + realBalance;
    if(document.getElementById('valDompet')) document.getElementById('valDompet').innerText = 'Rp ' + realBalance;
    if(document.getElementById('valInvestasi')) document.getElementById('valInvestasi').innerText = 'Rp ' + realTopup;
    if(document.getElementById('valInvestasiMid')) document.getElementById('valInvestasiMid').innerText = 'Rp ' + realTopup;
    if(document.getElementById('valProfitMid')) document.getElementById('valProfitMid').innerText = '+Rp ' + realProfit;
    this.innerHTML = '<i class="fa-regular fa-eye"></i>';
  }
});

/* Popup Logic */
function openPopup() { 
    var pp = document.getElementById('infoPopup');
    if(pp) pp.style.display = 'flex'; 
}
function closePopup() { 
  document.getElementById('infoPopup').style.display = 'none'; 
  <?php if ($show_popup && $popup_data): ?>
  fetch('<?= base_url('api/mark_popup') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'popup_id=<?= $popup_data['id'] ?? '' ?>'}).catch(function(){});
  <?php endif; ?>
}
document.getElementById('infoPopup')?.addEventListener('click', function(e) { if(e.target === this) closePopup(); });

<?php if ($show_popup && $popup_data): ?>
  setTimeout(openPopup, 500);
<?php endif; ?>

/* Filter Product UI */
function swKat(id, btn){
    document.querySelectorAll('.kat-sec').forEach(e => e.style.display='none');
    document.getElementById(id).style.display = 'grid';
    document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    btn.scrollIntoView({behavior:'smooth', inline:'center', block:'nearest'});
}

function buyAlert(canBuy, textMsg, urlAction) {
    if (canBuy) {
        Swal.fire({
            title: 'Konfirmasi Beli',
            html: textMsg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Beli Sekarang',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            background: '#ffffff',
            color: '#1e293b',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#94a3b8'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = urlAction;
            }
        });
    } else {
        Swal.fire({
            title: 'Saldo Tidak Cukup',
            text: textMsg,
            icon: 'error',
            showCancelButton: true,
            confirmButtonText: 'Top Up',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            background: '#ffffff',
            color: '#1e293b',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#94a3b8'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = urlAction;
            }
        });
    }
}
</script>

<?php require 'lib/footer_user.php'; ?>
</body>
</html>