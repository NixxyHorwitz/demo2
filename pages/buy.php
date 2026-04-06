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
body { background: #012b26; color: #fff; font-family: 'Poppins', sans-serif; -webkit-font-smoothing: antialiased; padding-bottom: 100px; }
.app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative;}

/* ====== HEADER ====== */
.h-bg { background: linear-gradient(135deg, #023e35 0%, #01312b 100%); padding: 25px 20px 90px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; position: relative; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1; }
.h-nav { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
.back-btn { width: 36px; height: 36px; border-radius: 12px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
.back-btn:active { background: rgba(255,255,255,0.1); }
.h-title { display: flex; flex-direction: column; }
.h-title h3 { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 2px; }
.h-title p { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.7); }

/* ====== PRODUCT CARD (OVERLAPPING) ====== */
.prod-card { background: rgba(250, 204, 21, 0.05); border: 1px solid rgba(250, 204, 21, 0.25); border-radius: 16px; padding: 20px 16px; display: flex; align-items: center; gap: 16px; backdrop-filter: blur(10px); }
.pc-icon { width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #facc15 0%, #ca8a04 100%); display: flex; align-items: center; justify-content: center; font-size: 24px; color: #012b26; flex-shrink: 0; box-shadow: 0 4px 15px rgba(250, 204, 21, 0.4); }
.pc-info { flex: 1; min-width: 0; }
.pc-cat { font-size: 10px; font-weight: 800; color: #facc15; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 4px; }
.pc-name { font-size: 15px; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
.pc-tags { display: flex; gap: 6px; flex-wrap: wrap; }
.pc-tags span { display: inline-block; font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 20px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.15); }

.c-body { padding: 0 20px; margin-top: -30px; position: relative; z-index: 2; margin-bottom: 20px; }

/* ALERT */
.b-alert { margin-bottom: 16px; padding: 12px 14px; border-radius: 14px; display: flex; align-items: center; gap: 12px; font-size: 11px; font-weight: 600; line-height: 1.4; }
.b-alert i { font-size: 18px; flex-shrink: 0; }
.ba-err  { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
.ba-warn { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #fcd34d; }

/* CARDS */
.w-card { background: #023e35; border-radius: 20px; padding: 20px; margin-bottom: 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.wc-title { font-size: 12px; font-weight: 800; color: #fff; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}
.wc-title i { color: #facc15; font-size: 14px; }

/* DETAIL ROWS */
.d-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed rgba(255,255,255,0.08); }
.d-row:last-child { border-bottom: none; padding-bottom: 0; }
.d-row:first-child { padding-top: 0; }
.d-lbl { display: flex; flex-direction: column; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.8); }
.d-lbl small { font-size: 9px; font-weight: 500; color: rgba(255,255,255,0.5); margin-top: 2px; line-height:1.2;}
.d-val { font-size: 14px; font-weight: 800; color: #fff; text-align: right;}
.d-val.yellow { color: #facc15; }
.d-val.green { color: #34d399; }

/* RECEIPT CARD */
.receipt-card { background: rgba(1, 43, 38, 0.5); border: 1px solid #035246; padding: 16px; }
.r-head { font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.5); letter-spacing: 1px; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;}
.r-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.7); }
.r-row:last-child { margin-bottom: 0; }
.r-row .val { font-size: 13px; font-weight: 800; color: #fff; text-align: right;}
.r-row .val.total { font-size: 20px; color: #facc15; }
.r-row .val.green { color: #34d399; }
.r-row .val.red { color: #f87171; }
.divider { height: 1px; background: rgba(255,255,255,0.08); margin: 12px 0; }

/* FIXED BUTTON SUMMARY */
.fixed-btn-wrap { position: fixed; bottom: 0; left: 0; right: 0; padding: 16px 20px; background: rgba(1, 43, 38, 0.95); backdrop-filter: blur(10px); display: flex; justify-content: center; z-index: 100; border-top: 1px solid rgba(255,255,255,0.05);}
.fixed-btn-wrap .app-cont { width: 100%; max-width: 440px; }
.btn-buy { width: 100%; background: #facc15; color: #012b26; border: none; padding: 16px; font-size: 14px; font-weight: 800; border-radius: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 15px rgba(250, 204, 21, 0.25); font-family: 'Poppins', sans-serif; transition: 0.2s; text-decoration: none;}
.btn-buy:active { transform: scale(0.96); }
.btn-buy.warn { background: #f59e0b; color: #fff; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.25); }
.btn-buy.dis { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.4); box-shadow: none; cursor: not-allowed; border: 1px solid rgba(255,255,255,0.1); }
</style>
</head>
<body>
<div class="app">

  <!-- HEADER -->
  <div class="h-bg">
    <div class="h-nav">
      <a href="javascript:history.back()" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
      <div class="h-title">
        <h3>Konfirmasi Pembelian</h3>
        <p>Detail produk investasi Anda</p>
      </div>
    </div>
    
    <div class="prod-card">
      <div class="pc-icon">
        <i class="fa-solid fa-chart-pie"></i>
      </div>
      <div class="pc-info">
        <span class="pc-cat"><?= htmlspecialchars($produk['kat_nama'] ?? 'Investasi') ?></span>
        <h4 class="pc-name"><?= htmlspecialchars($produk['nama_produk']) ?></h4>
        <div class="pc-tags">
          <span><i class="fa-solid fa-clock" style="margin-right:2px;"></i> <?= $masa_aktif ?> Hari</span>
          <span><i class="fa-solid fa-bolt" style="margin-right:2px;"></i> <?= number_format($roi_pct,1) ?>% ROI</span>
          <?php if ($is_locked): ?><span><i class="fa-solid fa-crown" style="margin-right:2px; color:#facc15;"></i> VIP</span><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="c-body">

      <!-- ALERTS -->
      <?php if (!$cukup): ?>
      <div class="b-alert ba-err">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div>Saldo Anda tidak mencukupi untuk membeli produk ini. Kurang <b>Rp<?= number_format(abs($saldo_after),0,',','.') ?></b>.</div>
      </div>
      <?php elseif (!$locked_ok): ?>
      <div class="b-alert ba-warn">
        <i class="fa-solid fa-lock"></i>
        <div>Silahkan beli produk <b><?= $to_buy_nama ?></b> terlebih dahulu agar paket ini terbuka.</div>
      </div>
      <?php elseif ($max_buy > 0 && $user_buy_count >= $max_buy): ?>
      <div class="b-alert ba-warn">
        <i class="fa-solid fa-ban"></i>
        <div>Batas pembelian produk ini telah tercapai (maks. <?= $max_buy ?>x).</div>
      </div>
      <?php endif; ?>

      <!-- DETAIL RINCIAN -->
      <div class="w-card">
        <h4 class="wc-title"><i class="fa-solid fa-file-invoice-dollar"></i> Ringkasan Paket</h4>
        <div class="d-row">
          <div class="d-lbl">Harga Paket</div>
          <div class="d-val">Rp <?= number_format($service_price,0,',','.') ?></div>
        </div>
        <div class="d-row">
          <div class="d-lbl">Profit Harian<small>Pendapatan masuk tiap hari</small></div>
          <div class="d-val green"><span style="font-size:10px;">+</span>Rp <?= number_format($profit_harian,0,',','.') ?></div>
        </div>
        <div class="d-row">
          <div class="d-lbl">Masa Aktif Investasi</div>
          <div class="d-val yellow"><?= $masa_aktif ?> Hari</div>
        </div>
        <div class="d-row">
          <div class="d-lbl">Total Profit<small>Diperoleh sampai akhir masa aktif</small></div>
          <div class="d-val green" style="font-size: 16px;"><span style="font-size:12px;">+</span>Rp <?= number_format($total_profit,0,',','.') ?></div>
        </div>
      </div>

      <!-- TOTAL BAYAR & SALDO -->
      <div class="w-card receipt-card">
        <div class="r-head">SUMBER DANA</div>
        <div class="r-row">
          <span>Total Pembayaran</span>
          <span class="val total">Rp <?= number_format($service_price,0,',','.') ?></span>
        </div>
        <div class="divider"></div>
        <div class="r-row">
          <span>Saldo Anda Saat Ini</span>
          <span class="val">Rp <?= number_format($ubal,0,',','.') ?></span>
        </div>
        <div class="r-row">
          <span>Sisa Saldo</span>
          <span class="val <?= $cukup ? 'green' : 'red' ?>">Rp <?= number_format(max(0,$saldo_after),0,',','.') ?></span>
        </div>
      </div>

  </div>

  <!-- FIXED CTA BUTTON -->
  <div class="fixed-btn-wrap">
      <div class="app-cont">
        <form method="POST" id="bForm">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <?php if (!$is_syarat && !$locked_ok && $to_buy_id > 0): ?>
            <a href="<?= base_url('pages/buy?produk_id='.$to_buy_id) ?>" class="btn-buy warn">
              <i class="fa-solid fa-lock-open"></i> Buka Kunci Dulu
            </a>
          <?php elseif (!$cukup): ?>
            <a href="<?= base_url('pages/deposit') ?>" class="btn-buy warn">
              <i class="fa-solid fa-plus-circle"></i> Tambah Saldo
            </a>
          <?php elseif ($max_buy > 0 && $user_buy_count >= $max_buy): ?>
            <button type="button" class="btn-buy dis" disabled>
              <i class="fa-solid fa-ban"></i> Terbatas (<?= $max_buy ?>x)
            </button>
          <?php else: ?>
            <button type="button" class="btn-buy" id="bBtn">
              <i class="fa-solid fa-bolt"></i> Bayar Sekarang
            </button>
          <?php endif; ?>
        </form>
      </div>
  </div>

</div>

<script>
var bBtn = document.getElementById('bBtn');
var bForm= document.getElementById('bForm');
if(bBtn) {
  bBtn.onclick = function() {
    this.disabled = true; this.style.opacity = '0.7';
    this.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
    bForm.submit();
  }
}
</script>
</body>
</html>