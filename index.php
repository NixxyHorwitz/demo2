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
$uprofit_val = (float)($check_user['rows']['profit'] ?? 0);

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
body { background: #F4F7FB; font-family: 'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }

.app {
  max-width: 480px;
  margin: 0 auto;
  background: #F4F7FB;
  min-height: 100vh;
  padding-bottom: 90px;
  position: relative;
  overflow-x: hidden;
}

/* TOP HEADER (Premium Metallic Gold) */
.top-header {
  background: linear-gradient(135deg, #C59327 0%, #F5D061 30%, #F8E28B 50%, #C59327 80%, #9C7012 100%);
  border-bottom-left-radius: 28px;
  border-bottom-right-radius: 28px;
  padding: 24px 20px 40px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 25px rgba(197, 147, 39, 0.3);
}

/* Header subtle decor */
.top-header::after {
  content: ''; position: absolute; right: -50px; top: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(255,255,255,0.4) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;
}

.header-row {
  display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2; margin-bottom: 24px;
}

.user-info { display: flex; align-items: center; gap: 12px; }
.logo-box {
  width: 42px; height: 42px; background: #111; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); border: 2px solid #F5D061;
}
.logo-box img { width: 100%; height: 100%; object-fit: contain; }

.user-text h2 { font-size: 15px; font-weight: 800; color: #111; line-height: 1.2; text-shadow: none; }
.user-text p { font-size: 11px; color: rgba(17,17,17,0.85); display: flex; align-items: center; gap: 4px; margin-top: 2px; font-weight: 500;}
.user-text p::before { content: ''; width: 6px; height: 6px; background: #111; border-radius: 50%; display: inline-block; }

.header-actions { display: flex; align-items: center; gap: 10px; }
.action-btn {
  width: 36px; height: 36px; background: rgba(17, 17, 17, 0.1); border: 1px solid rgba(17, 17, 17, 0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #111; position: relative; cursor: pointer; backdrop-filter: blur(5px); transition: 0.2s;
}
.action-btn:active { transform: scale(0.95); }
.action-btn i { font-size: 16px; }
.badge-dot {
  position: absolute; top: -4px; right: -4px; background: #EF4444; color: #fff; font-size: 9px; font-weight: 800; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 2px solid #F5D061;
}

/* MAIN CARD (VIP BLACK & GOLD) */
.wallet-card {
  background: linear-gradient(135deg, #18181B 0%, #000000 100%);
  border-radius: 16px;
  padding: 18px 20px;
  color: #fff;
  position: relative;
  z-index: 2;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
  overflow: hidden;
  border: 1px solid #333;
}

.wallet-card::before {
  content: ''; position: absolute; left: 0; bottom: 0; width: 100%; height: 100%;
  background: url("data:image/svg+xml;utf8,<svg viewBox='0 0 400 200' xmlns='http://www.w3.org/2000/svg'><path d='M-20,220 C100,50 250,220 420,-20' fill='none' stroke='%23C59327' stroke-width='3' stroke-opacity='0.4'/><path d='M-50,220 C120,120 280,270 480,-10' stroke='%23F5D061' stroke-width='1.5' fill='none' stroke-opacity='0.6'/><circle cx='400' cy='0' r='80' fill='none' stroke='%23C59327' stroke-width='1.5' stroke-opacity='0.2'/></svg>") no-repeat center bottom;
  background-size: cover; pointer-events: none; opacity: 1;
}
.wallet-card::after { display: none; }

.wc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; position: relative; z-index: 2;}
.wc-label { display: flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; opacity: 0.9; color: #D4AF37;}
.wc-label svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }
.wc-eye { width: 22px; height: 22px; background: rgba(245, 208, 97, 0.15); border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #D4AF37;}

.wc-balance { font-size: 26px; font-weight: 800; display: flex; align-items: flex-start; gap: 4px; line-height: 1; letter-spacing: -0.5px; position: relative; z-index: 1;}
.wc-balance span { font-size: 13.5px; font-weight: 600; margin-top: 3px; opacity: 0.9; color: #D4AF37;}

.wc-stats {
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px; position: relative; z-index: 1;
}
.wc-stat { background: rgba(255, 255, 255, 0.05); border-radius: 10px; padding: 10px; display: flex; flex-direction: column; gap: 4px; border: 1px solid rgba(255,255,255,0.1); }
.wc-stat-lbl { display: flex; align-items: center; gap: 4px; font-size: 10px; opacity: 0.9; font-weight: 500; color: #D4AF37;}
.wc-stat-lbl i { font-size: 10px; color: #F5D061; }
.wc-stat-val { font-size: 12.5px; font-weight: 700; color: #fff; }
.wc-stat-val.green { color: #F5D061; }

/* MENU ICONS */
.q-menus { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; padding: 16px 12px; margin-top: 15px; position: relative; z-index: 5; }
.q-btn { display: flex; flex-direction: column; align-items: center; gap: 6px; text-decoration: none; }
.q-icon-box {
  width: 42px; height: 42px; background: linear-gradient(135deg, #C59327 0%, #F5D061 50%, #9C7012 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 12px rgba(197, 147, 39, 0.15); border: 2px solid #111; position: relative;
}
.q-icon-box svg { width: 18px; height: 18px; stroke: #111; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;}
.q-lbl { font-size: 10px; font-weight: 600; color: #475569; text-align: center; }

/* MIDDLE BANNER */
.hero-slider { margin: 0 16px 16px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); position: relative; z-index: 1;}
.hero-slider img { width: 100%; height: 110px; display: block; object-fit: cover; border-radius: 12px; }

/* STATS GRID 2x2 */
.s-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 16px 16px; }
.s-card { background: #fff; border-radius: 10px; padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #E2E8F0; position: relative; }
.s-card .s-icon {
  width: 26px; height: 26px; background: rgba(197, 147, 39, 0.15); border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; color: #C59327;
}
.s-card .s-icon svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5;}
.s-card .s-badge {
  position: absolute; top: 12px; right: 12px; background: #F1F5F9; padding: 2px 6px; border-radius: 20px; font-size: 8px; font-weight: 700; color: #64748B;
}
.s-card .s-badge.green { background: rgba(197, 147, 39, 0.15); color: #C59327; }
.s-card .s-val { font-size: 14px; font-weight: 800; color: #111; line-height: 1; margin-bottom: 2px; }
.s-card .s-lbl { font-size: 10px; font-weight: 600; color: #94A3B8; }

/* ACTION BANNERS */
.action-banners { padding: 0 16px 20px; display: flex; flex-direction: column; gap: 10px; }
.a-banner {
  display: flex; align-items: center; justify-content: space-between; padding: 14px; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: relative; overflow: hidden;
}
.a-banner.gold { background: linear-gradient(135deg, #18181B 0%, #000000 100%); border: 1px solid #333; }
.a-banner.dark { background: linear-gradient(135deg, #C59327 0%, #F5D061 40%, #9C7012 100%); border: 1px solid #D4AF37; }

.a-icon {
  width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.15); flex-shrink: 0; z-index: 2;
}
.a-banner.gold .a-icon { background: rgba(245, 208, 97, 0.15); color: #F5D061; }
.a-banner.dark .a-icon { background: rgba(17, 17, 17, 0.1); border-color: rgba(17,17,17,0.2); color: #111; }

.a-text { flex: 1; padding: 0 10px; z-index: 2; }
.a-text h4 { font-size: 12px; font-weight: 700; color: #fff; margin-bottom: 2px; }
.a-banner.dark .a-text h4 { color: #111; }
.a-text p { font-size: 9.5px; color: rgba(255,255,255,0.75); line-height: 1.3; }
.a-banner.dark .a-text p { color: #111; opacity: 0.8; }

.a-chevron {
  width: 24px; height: 24px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 11px; flex-shrink: 0; z-index: 2;
}
.a-banner.dark .a-chevron { background: rgba(17, 17, 17, 0.1); color: #111; }

/* SECTION HEADER */
.sec-head { display: flex; align-items: center; justify-content: space-between; padding: 0 16px 10px; margin-top: 5px; }
.sec-head h3 { font-size: 14px; font-weight: 800; color: #111; display: flex; align-items: center; gap: 8px; }
.sec-head h3::before { content: ''; width: 4px; height: 14px; background: #C59327; border-radius: 4px; display: inline-block; }
.sec-head a { font-size: 10px; font-weight: 600; color: #64748B; text-decoration: none; }

/* POPUP CSS */
.pop-ov { position: fixed; inset: 0; background: rgba(30,41,59,0.7); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; }
.pop-card { background: #ffffff; border-radius: 20px; width: 90%; max-width: 320px; padding: 30px 24px 24px; text-align: center; position: relative; animation: fadeIn 0.3s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.pop-close { position: absolute; top: 12px; right: 12px; width: 28px; height: 28px; background: #F8FAFC; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #94A3B8; cursor: pointer; border: 1px solid #E2E8F0; transition: 0.2s;}
.pop-close:active { background: #E2E8F0; }
.pop-logo { border: 1px solid #F1F5F9; border-radius: 16px; padding: 12px 24px; margin: 0 auto 20px; display: inline-block; box-shadow: 0 4px 20px rgba(0,0,0,0.03); max-width: 80%; }
.pop-logo img { height: 60px; object-fit: contain; width: 100%; }
.pop-title { font-size: 16px; font-weight: 800; color: #1E293B; margin-bottom: 4px; line-height: 1.3;}
.pop-sub { font-size: 11px; font-weight: 500; color: #94A3B8; margin-bottom: 20px; }
.pop-desc { font-size: 11.5px; color: #64748B; line-height: 1.6; margin-bottom: 24px; text-align: justify; text-align-last: center; }
.pop-btn-gold { display: flex; align-items: center; justify-content: center; padding: 14px; border-radius: 12px; background: #1E293B; color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 12px; box-shadow: 0 6px 15px rgba(30, 41, 59, 0.3); transition: 0.2s;}
.pop-btn-gold:active { transform: scale(0.97); }
.pop-btn-gold i { margin-right: 8px; color: #C59A25; }
.pop-link { font-size: 12px; font-weight: 500; color: #94A3B8; text-decoration: none; cursor: pointer; }

/* CATEGORY & PRODUCTS LIST (Single Column Box) */
.cat-scroll { display: flex; overflow-x: auto; gap: 8px; padding: 0 16px 12px; scrollbar-width: none; }
.cat-scroll::-webkit-scrollbar { display: none; }
.cat-pill {
    flex-shrink: 0; font-size: 12px; font-weight: 600; padding: 6px 14px;
    border-radius: 20px; background: #fff; border: 1px solid #ddd;
    color: #555; cursor: pointer; transition: 0.2s;
}
.cat-pill.on { background: linear-gradient(135deg, #18181B 0%, #000000 100%); color: #F5D061; border-color: #111; }

.kat-sec { display: flex; flex-direction: column; gap: 16px; padding: 0 16px 16px; margin-bottom: 5px; }
.prod-card {
    background: #fff; border-radius: 12px; margin: 0; padding: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #f3f4f6;
    border-top: 3.5px solid #C59327;
}

.pc-top { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.pc-icon {
    width: 48px; height: 48px; background: #111; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; color: #F5D061; font-size: 22px;
    position: relative; flex-shrink: 0;
}
.pc-icon .i-badge {
    position: absolute; bottom: -4px; right: -4px; background: #fff; color: #10B981; 
    border-radius: 50%; font-size: 14px; padding: 2px; display: flex; align-items: center; justify-content: center;
}
.pc-info { flex: 1; }
.pc-title { font-size: 14.5px; font-weight: 800; color: #111; line-height: 1.2; text-transform: uppercase; margin-bottom: 3px; }
.pc-badge { display: inline-block; background: rgba(16, 185, 129, 0.15); color: #059669; font-size: 8px; font-weight: 800; padding: 2px 6px; border-radius: 4px; letter-spacing: 0.5px; margin-bottom: 3px; }
.pc-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
.pc-tag { font-size: 9px; font-weight: 500; color: #64748b; background: #fff; border: 1px solid #e2e8f0; padding: 3px 8px; border-radius: 14px; }
.pc-tag span { color: #10B981; font-weight: 700; margin-right: 2px; }

.pc-stats {
    background: #f8fafc; border-radius: 8px; padding: 12px 10px; display: flex; justify-content: space-between; border: 1px solid #f1f5f9; margin-bottom: 8px;
}
.pcs-col { flex: 1; text-align: center; }
.pcs-col:not(:last-child) { border-right: 1px dashed #cbd5e1; }
.pcs-lbl { font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
.pcs-val { font-size: 11.5px; font-weight: 800; color: #10B981; }

.pc-desc { font-size: 8px; color: #94a3b8; text-align: center; font-style: italic; margin-bottom: 15px; }

.pc-price { font-size: 12.5px; font-weight: 600; color: #111; margin-bottom: 8px; }
.pc-price span { font-weight: 800; }

.btn-invest {
    background: linear-gradient(135deg, #18181B 0%, #000000 100%); color: #F5D061; border: none; font-size: 12.5px;
    font-weight: 700; padding: 12px; border-radius: 8px; cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2); transition: 0.15s; outline: none;
    width: 100%; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-invest:active { transform: scale(0.97); }
.btn-invest.sold { background: #cbd5e1; color: #64748b; box-shadow: none; pointer-events: none; }

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

    <!-- WALLET CARD -->
    <div class="wallet-card">
      <div class="wc-top">
        <div class="wc-label">
          <svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="15" rx="2" ry="2"></rect><path d="M3 10h18"></path><path d="M7 14h.01"></path></svg>
          Total Saldo
        </div>
        <div class="wc-eye" id="toggleBalance">
          <i class="fa-regular fa-eye"></i>
        </div>
      </div>
      <div class="wc-balance" id="valBalance">
        <span>Rp</span> <?= number_format($ubal, 0, ',', '.') ?>
      </div>
      <div class="wc-stats">
        <div class="wc-stat">
          <div class="wc-stat-lbl">
            <i class="fa-solid fa-arrow-trend-up"></i> Hari Ini
          </div>
          <div class="wc-stat-val green">+Rp <?= number_format($uprofit_val, 0, ',', '.') ?></div>
        </div>
        <div class="wc-stat">
          <div class="wc-stat-lbl">
            <i class="fa-solid fa-layer-group"></i> Total Investasi
          </div>
          <div class="wc-stat-val">Rp <?= number_format($topups, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- QUICK MENUS (5 Icons) -->
  <div class="q-menus">
    <a href="<?= base_url('pages/deposit') ?>" class="q-btn">
      <div class="q-icon-box"><svg viewBox="0 0 24 24"><path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h7"/><path d="M3 7h18"/><path d="M15 16h6M18 13v6"/></svg></div>
      <div class="q-lbl">Deposit</div>
    </a>
    <a href="<?= base_url('pages/withdraw') ?>" class="q-btn">
      <div class="q-icon-box"><svg viewBox="0 0 24 24"><path d="M21 12V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h7"/><path d="M3 7h18"/><path d="M21 16h-6M18 13v6"/></svg></div>
      <div class="q-lbl">Penarikan</div>
    </a>
    <a href="<?= base_url('pages/history') ?>" class="q-btn">
      <div class="q-icon-box"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div class="q-lbl">Undang</div>
    </a>
    <a href="<?= base_url('pages/checkin') ?>" class="q-btn">
      <div class="q-icon-box"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg></div>
      <div class="q-lbl">Checkin</div>
    </a>
    <a href="javascript:void(0)" class="q-btn">
      <div class="q-icon-box"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
      <div class="q-lbl">Unduh APK</div>
    </a>
  </div>

  <!-- MIDDLE BANNER CAROUSEL -->
  <div class="hero-slider">
    <?php if(!empty($banners) && !empty($banners[0]['image_url'])): ?>
      <img src="<?= htmlspecialchars($banners[0]['image_url']) ?>" alt="Banner">
    <?php else: ?>
       <!-- Fallback banner logic -->
       <div style="background: linear-gradient(135deg, #1E293B, #0F172A); padding: 24px; text-align: center; color: #fff;">
          <h3 style="font-size:18px;font-weight:900;color:#FACC15;margin-bottom:8px;">SOLUSI KEUANGAN ANDA</h3>
          <p style="font-size:12px;opacity:0.8;">Platform terpercaya masa kini.</p>
       </div>
    <?php endif; ?>
  </div>

  <!-- STATS GRID 2x2 -->
  <div class="s-grid">
    <div class="s-card">
      <div class="s-icon" style="background:#E0F2FE; color:#0284C7;"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
      <div class="s-val">Rp <?= number_format($uprofit_val, 0, ',', '.') ?></div>
      <div class="s-lbl">Total Pendapatan</div>
    </div>
    <div class="s-card" onclick="window.location.href='<?= base_url('pages/product') ?>'" style="cursor:pointer;">
      <div class="s-icon" style="background:#E0E7FF; color:#4F46E5;"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      <div class="s-badge <?= $total_investasi_berjalan > 0 ? 'green' : '' ?>"><?= $total_investasi_berjalan > 0 ? 'Aktif' : 'Kosong' ?></div>
      <div class="s-val"><?= $total_investasi_berjalan ?> Paket</div>
      <div class="s-lbl">Investasi Berjalan</div>
    </div>
    <div class="s-card" onclick="window.location.href='<?= base_url('pages/agent') ?>'" style="cursor:pointer;">
      <div class="s-icon" style="background:#FEF3C7; color:#D97706;"><svg viewBox="0 0 24 24"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg></div>
      <div class="s-badge"><?= $total_member_ref ?> orang</div>
      <div class="s-val">Rp <?= number_format($total_komisi_ref, 0, ',', '.') ?></div>
      <div class="s-lbl">Bonus Referral</div>
    </div>
    <div class="s-card" onclick="window.location.href='<?= base_url('pages/checkin') ?>'" style="cursor:pointer;">
      <div class="s-icon" style="background:#F3E8FF; color:#9333EA;"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
      <div class="s-badge <?= $already_checkin ? 'green' : '' ?>"><?= $already_checkin ? '&check; Sudah' : 'Belum' ?></div>
      <div class="s-val"><?= $total_checkin_count ?>x</div>
      <div class="s-lbl">Total Checkin</div>
    </div>
  </div>

  <!-- ACTION BANNERS -->
  <div class="action-banners">
    <a href="<?= base_url('pages/agent') ?>" class="a-banner dark">
      <div class="a-icon"><i class="fa-solid fa-user-plus"></i></div>
      <div class="a-text">
        <h4>Ajak Teman, Dapat Bonus! <span style="font-weight:400;opacity:0.8;font-size:9.5px;">(0 orang)</span></h4>
        <p>Total bonus Rp 0 dari 0 teman yang Anda undang.</p>
      </div>
      <div class="a-chevron"><i class="fa-solid fa-chevron-right"></i></div>
    </a>
  </div>

  <!-- Section Title -->
  <div class="sec-head">
    <h3>Paket Investasi Terkini</h3>
  </div>
  
  <!-- Categories Filter -->
  <?php if(count($all_kat) > 1): ?>
  <div class="cat-scroll">
      <?php foreach($all_kat as $i => $kat): ?>
      <button class="cat-pill <?= $i===0?'on':'' ?>" onclick="swKat('k<?= $kat['id'] ?>', this)"><?= htmlspecialchars($kat['nama']) ?></button>
      <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Product Lists -->
  <?php foreach($all_kat as $ki => $kat): 
      $prods = $pby[$kat['id']] ?? [];
  ?>
  <div id="k<?= $kat['id'] ?>" class="kat-sec" style="display: <?= $ki===0?'flex':'none' ?>;">
      <?php if(empty($prods)): ?>
          <div style="text-align:center; padding: 30px; color:#999; font-size: 14px;">Belum ada investasi di sini.</div>
      <?php else: ?>
          <?php foreach($prods as $p): 
              $bought  = $owned[$p['id']] ?? 0;
              $max_buy = (int)($p['max_buy'] ?? 5);
              $soldout = ($max_buy - $bought <= 0);
              $harga   = (int)$p['harga'];
              $ok_sal  = ($saldo_akun >= $harga);
              
              $hfmt = number_format($harga, 0, '.', '.');
              $pfmt = number_format($p['profit_harian'], 0, '.', '.');
              $tfmt = number_format($p['total_profit'], 0, '.', '.');
              $kfmt = number_format(max($harga - $saldo_akun, 0), 0, ',', '.');

              if ($soldout) {
                  $btn_class = 'sold';
                  $btn_text = 'Habis';
                  $on_click = '';
              } elseif (!$ok_sal) {
                  $btn_class = '';
                  $btn_text = 'Miliki Sekarang';
                  $on_click = "buyAlert(false, 'Saldo Anda kurang Rp{$kfmt}. Silahkan top up terlebih dahulu.', '" . base_url('pages/deposit') . "')";
              } else {
                  $btn_class = '';
                  $btn_text = 'Miliki Sekarang';
                  $on_click = "buyAlert(true, 'Anda akan membeli paket <b>".htmlspecialchars($p['nama_produk'], ENT_QUOTES)."</b> seharga <b>Rp".number_format($harga, 0, ',', '.')."</b>', '" . base_url('pages/buy?produk_id='.$p['id']) . "')";
              }
          ?>
          <div class="prod-card">
              <div class="pc-top">
                  <div class="pc-icon">
                      <img src="<?= htmlspecialchars($prod_img_url) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:12px;" alt="Product">
                      <div class="i-badge"><i class="fa-solid fa-circle-check"></i></div>
                  </div>
                  <div class="pc-info">
                      <div class="pc-title"><?= htmlspecialchars($p['nama_produk']) ?></div>
                      <div class="pc-badge">PREMIUM</div>
                      <div class="pc-tags">
                           <div class="pc-tag"><span>&bull;</span> Halal & Stabil</div>
                           <div class="pc-tag"><span>&bull;</span> Transparan</div>
                      </div>
                  </div>
              </div>
              
              <div class="pc-stats">
                  <div class="pcs-col">
                      <div class="pcs-lbl">Harian</div>
                      <div class="pcs-val">Rp<?= $pfmt ?></div>
                  </div>
                  <div class="pcs-col">
                      <div class="pcs-lbl">Total</div>
                      <div class="pcs-val">Rp<?= $tfmt ?></div>
                  </div>
                  <div class="pcs-col">
                      <div class="pcs-lbl">Siklus</div>
                      <div class="pcs-val"><?= $p['masa_aktif'] ?> Hari</div>
                  </div>
              </div>
              
              <div class="pc-desc">*Keuntungan yang telah masuk dapat ditarik ke rekening atau wallet Anda</div>
              
              <div class="pc-price">Harga Pembelian : <span>Rp<?= $hfmt ?></span></div>
              
              <button class="btn-invest <?= $btn_class ?>" onclick="<?= $on_click ?>">
                  <?= $btn_text ?> <i class="fa-solid fa-arrow-right"></i>
              </button>
          </div>
          <?php endforeach; ?>
      <?php endif; ?>
  </div>
  <?php endforeach; ?>

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
          <h2 style="color:#C59A25;font-weight:800;font-size:24px;margin:10px;"><?= htmlspecialchars($site_name) ?></h2>
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
      <i class="fa-solid fa-users"></i> <?= $popup_data['button_text'] ?: 'Gabung Komunitas' ?>
    </a>
    <div class="pop-link" onclick="closePopup()">Nanti saja</div>
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
  if(isHidden) {
    document.getElementById('valBalance').innerHTML = '<span>Rp</span> •••••••';
    this.innerHTML = '<i class="fa-regular fa-eye-slash"></i>';
    // optionally hide the sub-stats
  } else {
    document.getElementById('valBalance').innerHTML = '<span>Rp</span> ' + realBalance;
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
            background: '#111111',
            color: '#ffffff',
            confirmButtonColor: '#C59327',
            cancelButtonColor: '#333'
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
            background: '#111111',
            color: '#ffffff',
            confirmButtonColor: '#C59327',
            cancelButtonColor: '#333'
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