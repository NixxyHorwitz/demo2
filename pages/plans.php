<?php
error_reporting(E_ALL); ini_set('display_errors',1);
require '../mainconfig.php';
require '../lib/check_session.php';
require '../lib/flash_message.php';
require '../lib/is_login.php';
use \Firebase\JWT\JWT;
$page_type='plans';

if(isset($_COOKIE['X_SESSION'])){
    try{
        $jwt=\Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'],$config['jwt']['secret'],['HS256']);
        $check_user=$model->db_query($db,"*","users","id='".protect($jwt->id)."' AND x_session='".protect($_COOKIE['X_SESSION'])."'");
        if($check_user['count']!==1||$check_user['rows']['status']!='Active'){logout();exit(header("Location:".base_url('auth/login')));}
    }catch(Exception $e){logout();exit(header("Location:".base_url('auth/login')));}
}else{exit(header("Location:".base_url('auth/login')));}
$uid=$check_user['rows']['id'];
if(!function_exists('protect')){function protect($s){return is_scalar($s)?trim((string)$s):'';}}
$cfg=$model->db_query($db,"*","settings","id=1")['rows'];
$nama_web=htmlspecialchars($cfg['title']??'Platform');

$bq=mysqli_query($db,"SELECT saldo,point,profit FROM users WHERE id='$uid'");
$bd=mysqli_fetch_assoc($bq);
$saldo_akun = $bd['saldo']??0;
$pendapatan = $bd['point']??0;

// Ambil app_images
$img_q = $db->query("SELECT * FROM app_images");
$app_images = [];
if ($img_q) while ($r = $img_q->fetch_assoc()) $app_images[$r['image_key']] = $r['image_url'];

$banner_url = !empty($app_images['plans_banner']) ? $app_images['plans_banner'] : 'https://placehold.co/600x200/a34e94/fff?text=Banner+Produk';
$prod_img_url = !empty($app_images['product_image']) ? $app_images['product_image'] : 'https://placehold.co/100x120/eee/999?text=IMG';

$all_kat=[];$qk=mysqli_query($db,"SELECT * FROM produk_kategori WHERE is_hidden=0 ORDER BY urutan ASC,id ASC");
while($r=mysqli_fetch_assoc($qk))$all_kat[]=$r;

$all_produk=[];$qp=mysqli_query($db,"SELECT p.*,k.nama as kat_nama,k.is_locked,k.syarat,tb.nama_produk as to_buy_nama,tb.harga as to_buy_harga FROM produk_investasi p LEFT JOIN produk_kategori k ON k.id=p.kategori_id LEFT JOIN produk_investasi tb ON tb.id=p.to_buy ORDER BY k.urutan ASC,p.harga ASC");
while($r=mysqli_fetch_assoc($qp))$all_produk[]=$r;
$pby=[];foreach($all_produk as $p)$pby[$p['kategori_id']][]=$p;

$owned=[];$qo=mysqli_query($db,"SELECT produk_id,COUNT(*) as cnt FROM orders WHERE user_id='$uid' AND status='Active' GROUP BY produk_id");
while($r=mysqli_fetch_assoc($qo))$owned[$r['produk_id']]=(int)$r['cnt'];
require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Paket Investasi – <?=$nama_web?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
/* Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #012b26; font-family: 'Poppins', sans-serif; color: #fff; -webkit-font-smoothing: antialiased; }
.app { max-width: 480px; margin: 0 auto; min-height: 100vh; background: #012b26; position: relative; padding-bottom: 90px; }

/* HEADER */
.p-header {
    background: linear-gradient(135deg, #023e35 0%, #01312b 100%);
    padding: 30px 20px 80px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px;
    position: relative; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1;
}
.ph-title { font-size: 24px; font-weight: 800; color: #facc15; line-height: 1.2; margin-bottom: 4px; }
.ph-sub { font-size: 13px; color: rgba(255,255,255,0.8); margin-bottom: 24px; }

/* HEADER FLOATING CARD */
.ph-card {
    background: #023e35; border: 1px solid #facc15; border-radius: 20px; padding: 16px 20px;
    display: flex; justify-content: space-between; align-items: center; position: relative;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.ph-col { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.ph-col:last-child { align-items: flex-end; text-align: right; }
.ph-div { width: 1px; height: 34px; background: rgba(250, 204, 21, 0.4); margin: 0 16px; }
.ph-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #facc15; flex-shrink: 0; margin-right: 12px; }

.ph-lbl { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;}
.ph-val { font-size: 16px; font-weight: 800; color: #fff; line-height: 1; }
.ph-val.plus { color: #facc15; }
.ph-val i { font-size: 12px; margin-right: 4px; }

/* CATEGORY TABS */
.cat-tabs { display: flex; overflow-x: auto; gap: 10px; padding: 0 16px 20px; margin-top: -24px; position: relative; z-index: 2; scrollbar-width: none; }
.cat-tabs::-webkit-scrollbar { display: none; }
.c-pill {
    flex-shrink: 0; background: #023e35; color: rgba(255,255,255,0.6); padding: 12px 20px;
    border-radius: 14px; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid #035246;
    display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); transition: 0.2s;
}
.c-pill.on { background: #facc15; color: #012b26; border-color: #facc15; }
.c-pill i { font-size: 14px; }

/* PRODUCT SEC */
.kat-sec { display: flex; flex-direction: column; gap: 12px; padding: 0 16px 20px; }
.prod-card {
    background: #023e35; border-radius: 16px; padding: 14px; box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    border: 1px solid #035246; position: relative; overflow: hidden;
}
.pc-head { display: flex; gap: 12px; margin-bottom: 12px; align-items: center; }
.pc-logo { width: 44px; height: 44px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: #012b26; }
.pc-logo img { width: 100%; height: 100%; object-fit: cover; }
.pc-head-info { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 4px; }

.pc-badge-top { display: flex; justify-content: space-between; align-items: center; }
.pc-kat { background: #facc15; color: #012b26; font-size: 9px; font-weight: 800; padding: 3px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.pc-rp { text-align: right; display: flex; align-items: baseline; gap: 3px; }
.pc-rp h4 { font-size: 13px; font-weight: 800; color: #facc15; line-height: 1; margin: 0; }
.pc-rp span { font-size: 8px; color: rgba(255,255,255,0.6); }

.pc-title { font-size: 15px; font-weight: 800; color: #fff; line-height: 1.1; }

.pc-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.pc-sub { font-size: 10px; color: rgba(255,255,255,0.7); }
.pc-days { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.8); display: flex; align-items: center; gap: 4px; }
.pc-days i { color: rgba(255,255,255,0.5); font-size: 11px; }

.pc-3box { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-bottom: 10px; }
.pc-box { background: #012b26; border-radius: 10px; padding: 8px 6px; border: 1px solid rgba(250,204,21,0.1); text-align: center; }
.pc-box-lbl { font-size: 8px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; margin-bottom: 3px; }
.pc-box-val { font-size: 11px; font-weight: 800; color: #fff; }
.pc-box.highlight { border-color: rgba(250,204,21,0.3); background: rgba(250,204,21,0.05); }
.pc-box.highlight .pc-box-val { color: #facc15; }

.pc-tot { font-size: 10.5px; color: rgba(255,255,255,0.7); margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; }
.pc-tot b { color: #fff; font-size: 12px; font-weight: 700;}

.btn-buy { background: #facc15; color: #012b26; border: none; font-size: 13px; font-weight: 800; padding: 10px; border-radius: 10px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(250, 204, 21, 0.2);}
.btn-buy:active { transform: scale(0.97); }
.btn-buy.sold { background: #012b26; color: rgba(255,255,255,0.3); border: 1px dashed rgba(255,255,255,0.2); box-shadow: none; pointer-events: none; }
    </style>
</head>
<body>
<div class="app">

    <!-- Header Section (Matching the Image) -->
    <div class="p-header">
        <div class="ph-title">Investasi</div>
        <div class="ph-sub">Pilih paket dan kunci profit harian</div>
        
        <div class="ph-card">
            <div class="ph-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="ph-col">
                <div class="ph-lbl">TOTAL INVESTASI</div>
                <div class="ph-val">Rp <?= number_format($saldo_akun, 0, ',', '.') ?></div>
            </div>
            <div class="ph-div"></div>
            <div class="ph-col">
                <div class="ph-lbl">PROFIT / HARI</div>
                <div class="ph-val plus"><i class="fa-solid fa-arrow-trend-up"></i> Rp <?= number_format($pendapatan, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Categories Filter -->
    <?php if(count($all_kat) > 0): ?>
    <div class="cat-tabs">
        <div class="c-pill on" onclick="swKat('all', this)"><i class="fa-solid fa-layer-group"></i> Semua</div>
        <?php foreach($all_kat as $i => $kat): 
            $icon = ($i % 2 == 0) ? 'fa-gem' : 'fa-crown';
        ?>
        <div class="c-pill" onclick="swKat('k<?= $kat['id'] ?>', this)"><i class="fa-solid <?= $icon ?>"></i> <?= htmlspecialchars($kat['nama']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Product Lists -->
    <div class="kat-sec" id="all">
    <?php foreach($all_produk as $p): 
        $bought  = $owned[$p['id']] ?? 0;
        $max_buy = (int)($p['max_buy'] ?? 5);
        $soldout = ($max_buy - $bought <= 0);
        $harga   = (int)$p['harga'];
        $ok_sal  = ($saldo_akun >= $harga);
        
        $est = ($harga > 0) ? (($p['profit_harian'] / $harga) * 100) : 0;
        
        $hfmt = number_format($harga, 0, ',', '.');
        $pfmt = number_format($p['profit_harian'], 0, ',', '.');
        $tfmt = number_format($p['total_profit'], 0, ',', '.');
        $kfmt = number_format(max($harga - $saldo_akun, 0), 0, ',', '.');

        if ($soldout) {
            $btn_class = 'sold';
            $btn_text = 'Sold Out';
            $on_click = '';
        } elseif (!$ok_sal) {
            $btn_class = '';
            $btn_text = 'Mulai investasi';
            $on_click = "buyAlert(false, 'Saldo Anda kurang Rp{$kfmt}. Silahkan top up terlebih dahulu.', '" . base_url('pages/deposit') . "')";
        } else {
            $btn_class = '';
            $btn_text = 'Mulai investasi';
            $on_click = "buyAlert(true, 'Anda akan membeli paket <b>".htmlspecialchars($p['nama_produk'])."</b> seharga <b>Rp".number_format($harga, 0, ',', '.')."</b>', '" . base_url('pages/buy?produk_id='.$p['id']) . "')";
        }
    ?>
        <div class="prod-card item-product" data-cat="k<?= $p['kategori_id'] ?>">
            <div class="pc-head">
                <div class="pc-logo">
                    <img src="<?= htmlspecialchars($prod_img_url) ?>" alt="Product">
                </div>
                <div class="pc-head-info">
                    <div class="pc-badge-top">
                        <div class="pc-kat"><?= htmlspecialchars($p['kat_nama']) ?></div>
                        <div class="pc-rp">
                            <h4><?= number_format($p['profit_harian']/1000, 0, ',', '.') ?>rb</h4>
                            <span>/ hr</span>
                        </div>
                    </div>
                    <div class="pc-title"><?= htmlspecialchars($p['nama_produk']) ?></div>
                </div>
            </div>
            
            <div class="pc-meta">
                <div class="pc-sub">Estimasi <?= number_format($est, 2, ',', '.') ?>% / hari</div>
                <div class="pc-days">
                    <i class="fa-regular fa-calendar-days"></i> <?= $p['masa_aktif'] ?> hr
                </div>
            </div>
            
            <div class="pc-3box">
                <div class="pc-box">
                    <div class="pc-box-lbl">MODAL</div>
                    <div class="pc-box-val">Rp <?= number_format($harga/1000, 0, ',', '.') ?>k</div>
                </div>
                <div class="pc-box highlight">
                    <div class="pc-box-lbl">PROFIT</div>
                    <div class="pc-box-val">+Rp <?= number_format($p['profit_harian']/1000, 0, ',', '.') ?>k</div>
                </div>
                <div class="pc-box highlight">
                    <div class="pc-box-lbl">TOTAL</div>
                    <div class="pc-box-val">+Rp <?= number_format($p['total_profit']/1000, 0, ',', '.') ?>k</div>
                </div>
            </div>
            
            <div class="pc-tot">
                <span>Profit total kontrak</span>
                <b>Rp <?= $tfmt ?></b>
            </div>
            
            <button class="btn-buy <?= $btn_class ?>" onclick="<?= $on_click ?>">
                <i class="fa-solid fa-rocket"></i> <?= $btn_text ?>
            </button>
        </div>
    <?php endforeach; ?>
    </div>

</div><!-- /app -->

<script>
function swKat(id, btn){
    document.querySelectorAll('.c-pill').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    
    if (id === 'all') {
        document.querySelectorAll('.item-product').forEach(e => e.style.display = 'block');
    } else {
        document.querySelectorAll('.item-product').forEach(e => {
            if (e.getAttribute('data-cat') === id) {
                e.style.display = 'block';
            } else {
                e.style.display = 'none';
            }
        });
    }
    btn.scrollIntoView({behavior:'smooth', inline:'center', block:'nearest'});
}

function buyAlert(canBuy, textMsg, urlAction) {
    if (canBuy) {
        Swal.fire({
            title: 'Konfirmasi Investasi',
            html: textMsg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Beli Sekarang',
            cancelButtonText: 'Batal',
            reverseButtons: true
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
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = urlAction;
            }
        });
    }
}
</script>

<?php require '../lib/footer_user.php'; ?>
</body>
</html>