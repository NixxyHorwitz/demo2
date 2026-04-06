<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../mainconfig.php';
require '../lib/check_session.php';
require '../lib/flash_message.php';
require '../lib/is_login.php';
use \Firebase\JWT\JWT;

if (isset($_COOKIE['X_SESSION'])) {
    try {
        $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], ['HS256']);
        $check_user = $model->db_query($db, "*", "users",
            "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_SESSION']) . "'");
        if ($check_user['count'] !== 1 || $check_user['rows']['status'] != 'Active') {
            logout(); exit(header("Location: " . base_url('auth/login')));
        }
    } catch (Exception $e) { logout(); exit(header("Location: " . base_url('auth/login'))); }
} else { exit(header("Location: " . base_url('auth/login'))); }

$user_id = $check_user['rows']['id'];
$login   = $check_user['rows'];

if (!function_exists('protect')) {
    function protect($s) { return is_scalar($s) ? trim((string)$s) : ''; }
}

$q_set    = $model->db_query($db, "*", "settings", "id=1");
$cfg      = $q_set['rows'];
$nama_web = htmlspecialchars($cfg['title'] ?? 'Platform');

if (!isset($_GET['produk_id'])) {
    $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Produk tidak ditemukan.'];
    exit(header("Location: " . base_url('')));
}

$produk_id = (int)$_GET['produk_id'];
$qp = mysqli_query($db, "
    SELECT p.*, k.nama as kat_nama, k.is_locked, k.syarat,
           tb.nama_produk as to_buy_nama, tb.id as to_buy_real_id
    FROM produk_investasi p
    LEFT JOIN produk_kategori k ON k.id = p.kategori_id
    LEFT JOIN produk_investasi tb ON tb.id = p.to_buy
    WHERE p.id='$produk_id'
");
if (!$qp || mysqli_num_rows($qp) === 0) {
    $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Produk tidak ditemukan.'];
    exit(header("Location: " . base_url('')));
}
$produk = mysqli_fetch_assoc($qp);

$max_buy       = (int)($produk['max_buy'] ?? 0);
$service_price = (int)$produk['harga'];
$profit_harian = (int)$produk['profit_harian'];
$masa_aktif    = (int)$produk['masa_aktif'];
$total_profit  = $profit_harian * $masa_aktif;
$roi_pct       = $service_price > 0 ? round(($total_profit / $service_price) * 100, 1) : 0;
$ubal          = (int)$login['saldo'];
$saldo_after   = $ubal - $service_price;
$cukup         = $ubal >= $service_price;

$user_buy_count = 0;
if ($max_buy > 0) {
    $q_order   = mysqli_query($db, "SELECT COUNT(*) AS total FROM orders WHERE user_id='{$user_id}' AND produk_id='{$produk['id']}' AND status IN ('Active','Completed')");
    $row_order = mysqli_fetch_assoc($q_order);
    $user_buy_count = (int)$row_order['total'];
}

$is_locked   = (bool)($produk['is_locked'] ?? false);
$is_syarat   = (bool)($produk['syarat']    ?? false);
$to_buy_id   = (int)($produk['to_buy']     ?? 0);
$to_buy_nama = htmlspecialchars($produk['to_buy_nama'] ?? '');
$locked_ok   = true;
if (!$is_syarat && $to_buy_id > 0) {
    $chk = mysqli_fetch_assoc(mysqli_query($db,
        "SELECT COUNT(*) AS c FROM orders WHERE user_id='$user_id' AND produk_id='$to_buy_id' AND status='Active'"));
    $locked_ok = ((int)$chk['c']) > 0;
}

$can_buy = $cukup && $locked_ok && (!$max_buy || $user_buy_count < $max_buy);

function getProdHue($name) {
    $n = strtolower($name);
    if (str_contains($n,'blue chip')||str_contains($n,'lq45'))          return 210;
    if (str_contains($n,'dividen')||str_contains($n,'konsisten'))       return 152;
    if (str_contains($n,'growth')||str_contains($n,'agresif'))          return 24;
    if (str_contains($n,'perbankan')||str_contains($n,'bank'))          return 220;
    if (str_contains($n,'infrastruktur')||str_contains($n,'konstruksi'))return 258;
    if (str_contains($n,'energi')||str_contains($n,'pertambangan'))     return 42;
    if (str_contains($n,'teknologi')||str_contains($n,'digital'))       return 188;
    if (str_contains($n,'consumer')||str_contains($n,'goods'))          return 328;
    if (str_contains($n,'properti')||str_contains($n,'real'))           return 172;
    return 210;
}
$ph = getProdHue($produk['nama_produk']);
$thumb_bg = "linear-gradient(150deg,hsl($ph,65%,22%) 0%,hsl($ph,60%,40%) 100%)";

/* ── POST HANDLER ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_input($_POST, ['csrf_token'])) {
        $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Data tidak lengkap.'];
        exit(header("Location: ".$_SERVER['PHP_SELF'].'?produk_id='.$produk_id));
    }
    if (!hash_equals(csrf_token(), $_POST['csrf_token'])) {
        $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Token tidak valid.'];
        exit(header("Location: ".$_SERVER['PHP_SELF'].'?produk_id='.$produk_id));
    }
    if ($max_buy > 0 && $user_buy_count >= $max_buy) {
        $_SESSION['result'] = ['response'=>'warning','title'=>'Batas Pembelian!','msg'=>'Maks. '.$max_buy.' kali beli.'];
        exit(header("Location: ".$_SERVER['PHP_SELF'].'?produk_id='.$produk_id));
    }
    if ($ubal - $service_price < 0) {
        $_SESSION['result'] = ['response'=>'error','title'=>'Saldo Tidak Cukup!','msg'=>'Saldo tidak mencukupi.'];
        exit(header("Location: ".$_SERVER['PHP_SELF'].'?produk_id='.$produk_id));
    }
    if (!$is_syarat && $to_buy_id > 0 && !$locked_ok) {
        $_SESSION['result'] = ['response'=>'warning','title'=>'Syarat Belum Terpenuhi!',
            'msg'=>'Kamu harus memiliki order aktif dari "'.$to_buy_nama.'" dulu.'];
        exit(header("Location: ".$_SERVER['PHP_SELF'].'?produk_id='.$produk_id));
    }
    $new_point  = $ubal - $service_price;
    $trx_number = 'ORD'.date('YmdHis').rand(100,999);
    $model->db_insert($db,'orders',[
        'user_id'=>$login['id'],'produk_id'=>$produk['id'],'is_locked'=>$produk['is_locked'],
        'nama_produk'=>$produk['nama_produk'],'harga'=>$produk['harga'],
        'profit_harian'=>$produk['profit_harian'],'masa_aktif'=>$produk['masa_aktif'],
        'persentase'=>$produk['persentase'],'total_profit'=>$total_profit,
        'status'=>'Active','created_at'=>date('Y-m-d H:i:s')
    ]);
    $model->db_update($db,'users',['saldo'=>$new_point],"id='{$login['id']}'");
    $model->db_insert($db,'point_logs',[
        'user_id'=>$login['id'],'type'=>'minus','amount'=>$service_price,
        'description'=>'Beli: '.$produk['nama_produk'].' ('.$trx_number.')','created_at'=>date('Y-m-d H:i:s')
    ]);
    $_SESSION['result'] = ['response'=>'success','title'=>'Berhasil!','msg'=>'Paket berhasil diaktifkan!'];
    exit(header("Location: ".base_url('pages/product')));
}

require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Beli Paket — <?= htmlspecialchars($produk['nama_produk']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #0A0A0A; color: #fff; font-family: 'Poppins', sans-serif; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
.app { max-width: 480px; margin: 0 auto; min-height: 100vh; }
/* HEADER */
.bh { display: flex; align-items: center; gap: 12px; padding: 15px 15px 10px; }
.bh-back { width: 32px; height: 32px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; font-size: 12px; }
.bh-title { flex: 1; text-align: center; font-size: 13px; font-weight: 800; color: #fff; padding-right: 32px; }
/* PRODUCT STRIP */
.prod-strip { margin: 0 15px 12px; background: linear-gradient(135deg, #C59327 0%, #F5D061 100%); border-radius: 14px; padding: 14px; display: flex; align-items: center; gap: 12px; box-shadow: 0 6px 20px rgba(197, 147, 39, 0.3); }
.ps-icon { width: 40px; height: 40px; background: #111; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #F5D061; flex-shrink: 0; }
.ps-info { flex: 1; min-width: 0; }
.ps-cat { font-size: 9px; font-weight: 700; color: rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1px; }
.ps-name { font-size: 13.5px; font-weight: 800; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ps-tags { display: flex; gap: 5px; margin-top: 4px; flex-wrap: wrap; }
.ps-tag { font-size: 8.5px; font-weight: 700; padding: 2px 7px; border-radius: 20px; background: rgba(0,0,0,0.12); color: #111; }
/* ALERT */
.b-alert { margin: 0 15px 12px; padding: 10px 12px; border-radius: 12px; display: flex; align-items: center; gap: 10px; font-size: 11px; font-weight: 600; }
.b-alert i { font-size: 16px; flex-shrink: 0; }
.ba-err  { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #F87171; }
.ba-warn { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); color: #FCD34D; }
/* DETAIL ROWS */
.detail-box { margin: 0 15px 12px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; overflow: hidden; }
.db-head { padding: 10px 14px; border-bottom: 1px dashed rgba(255,255,255,0.07); display: flex; align-items: center; gap: 8px; }
.db-head i { color: #F5D061; font-size: 12px; }
.db-head span { font-size: 10.5px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; }
.d-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px dashed rgba(255,255,255,0.04); }
.d-row:last-child { border-bottom: none; }
.d-lbl { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.5); }
.d-lbl small { display: block; font-size: 9px; color: rgba(255,255,255,0.3); margin-top: 1px; }
.d-val { font-size: 12.5px; font-weight: 800; color: #fff; }
.d-val.gold { color: #F5D061; }
.d-val.green { color: #10B981; }
/* TOTAL ROW */
.total-row { margin: 0 15px 15px; background: linear-gradient(135deg, #18181B 0%, #111 100%); border: 1px solid rgba(197,147,39,0.3); border-radius: 14px; padding: 14px; display: flex; justify-content: space-between; align-items: center; }
.tr-lbl { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-bottom: 3px; }
.tr-val { font-size: 20px; font-weight: 800; color: #F5D061; }
.tr-right { text-align: right; }
.tr-balance { font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.4); }
.tr-balance span { color: <?= $cukup ? '#10B981' : '#F43F5E' ?>; }
/* CTA BUTTON */
.form-wrap { padding: 0 15px; }
.btn-buy { width: 100%; padding: 15px; border: none; border-radius: 14px; font-size: 13.5px; font-weight: 800; font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; text-decoration: none; transition: 0.15s; background: linear-gradient(135deg, #C59327 0%, #F5D061 100%); color: #111; box-shadow: 0 8px 20px rgba(197,147,39,0.3); }
.btn-buy:active { transform: scale(0.97); }
.btn-buy.warn { background: linear-gradient(135deg, #B45309 0%, #F59E0B 100%); color: #fff; box-shadow: 0 6px 16px rgba(245,158,11,0.25); }
.btn-buy.dis { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); box-shadow: none; cursor: not-allowed; border: 1px solid rgba(255,255,255,0.08); }
@keyframes rot { to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: rot 0.8s linear infinite; }
</style>
</head>
<body>
<div class="app">

  <!-- HEADER -->
  <div class="bh">
    <a href="javascript:history.back()" class="bh-back"><i class="fa-solid fa-chevron-left"></i></a>
    <div class="bh-title">Konfirmasi Order</div>
  </div>

  <!-- PRODUCT STRIP -->
  <div class="prod-strip">
    <div class="ps-icon"><i class="fa-solid fa-chart-line"></i></div>
    <div class="ps-info">
      <div class="ps-cat"><?= htmlspecialchars($produk['kat_nama'] ?? 'Investasi') ?></div>
      <div class="ps-name"><?= htmlspecialchars($produk['nama_produk']) ?></div>
      <div class="ps-tags">
        <span class="ps-tag"><i class="fa-solid fa-clock" style="margin-right:3px;"></i><?= $masa_aktif ?> Hari</span>
        <span class="ps-tag"><i class="fa-solid fa-chart-simple" style="margin-right:3px;"></i><?= number_format($roi_pct,1) ?>% ROI</span>
        <?php if ($is_locked): ?><span class="ps-tag">VIP</span><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ALERTS -->
  <?php if (!$cukup): ?>
  <div class="b-alert ba-err">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div>Saldo tidak cukup. Kurang <b>Rp<?= number_format(abs($saldo_after),0,',','.') ?></b></div>
  </div>
  <?php elseif (!$locked_ok): ?>
  <div class="b-alert ba-warn">
    <i class="fa-solid fa-lock"></i>
    <div>Beli <b><?= $to_buy_nama ?></b> terlebih dahulu untuk membuka paket ini.</div>
  </div>
  <?php elseif ($max_buy > 0 && $user_buy_count >= $max_buy): ?>
  <div class="b-alert ba-warn">
    <i class="fa-solid fa-ban"></i>
    <div>Batas pembelian tercapai (maks. <?= $max_buy ?>x).</div>
  </div>
  <?php endif; ?>

  <!-- DETAIL RINCIAN -->
  <div class="detail-box">
    <div class="db-head"><i class="fa-solid fa-receipt"></i><span>Rincian Paket</span></div>
    <div class="d-row">
      <div class="d-lbl">Harga Paket</div>
      <div class="d-val">Rp <?= number_format($service_price,0,',','.') ?></div>
    </div>
    <div class="d-row">
      <div class="d-lbl">Profit Harian<small>Diterima setiap hari</small></div>
      <div class="d-val green">+Rp <?= number_format($profit_harian,0,',','.') ?></div>
    </div>
    <div class="d-row">
      <div class="d-lbl">Masa Aktif</div>
      <div class="d-val gold"><?= $masa_aktif ?> Hari</div>
    </div>
    <div class="d-row">
      <div class="d-lbl">Total Keuntungan<small>Selama masa aktif</small></div>
      <div class="d-val green">+Rp <?= number_format($total_profit,0,',','.') ?></div>
    </div>
  </div>

  <!-- TOTAL -->
  <div class="total-row">
    <div>
      <div class="tr-lbl">Total Bayar</div>
      <div class="tr-val">Rp <?= number_format($service_price,0,',','.') ?></div>
    </div>
    <div class="tr-right">
      <div class="tr-balance">Saldo Anda</div>
      <div class="tr-balance">Rp <span><?= number_format($ubal,0,',','.') ?></span></div>
      <div class="tr-balance" style="margin-top:2px;">Sisa: <span>Rp <?= number_format(max(0,$saldo_after),0,',','.') ?></span></div>
    </div>
  </div>

  <!-- CTA BUTTON -->
  <div class="form-wrap">
    <form method="POST" id="bForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <?php if (!$is_syarat && !$locked_ok && $to_buy_id > 0): ?>
        <a href="<?= base_url('pages/buy?produk_id='.$to_buy_id) ?>" class="btn-buy warn">
          <i class="fa-solid fa-lock-open"></i> Beli Produk Syarat Dulu
        </a>
      <?php elseif (!$cukup): ?>
        <a href="<?= base_url('pages/deposit') ?>" class="btn-buy warn">
          <i class="fa-solid fa-plus"></i> Top Up Saldo
        </a>
      <?php elseif ($max_buy > 0 && $user_buy_count >= $max_buy): ?>
        <button type="button" class="btn-buy dis" disabled>
          <i class="fa-solid fa-ban"></i> Batas Beli Tercapai
        </button>
      <?php else: ?>
        <button type="button" class="btn-buy" id="bBtn">
          <i class="fa-solid fa-bolt"></i> Konfirmasi & Aktifkan Paket
        </button>
      <?php endif; ?>
    </form>
  </div>

</div>

<script>
var bBtn = document.getElementById('bBtn');
var bForm= document.getElementById('bForm');
if(bBtn) {
  bBtn.onclick = function() {
    this.disabled = true; this.style.opacity = '0.7';
    this.innerHTML = ' <span class="spin">↻</span> Memproses...';
    bForm.submit();
  }
}
</script>
</body>
</html>