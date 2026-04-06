<?php
require '../mainconfig.php';
require '../lib/is_login.php';
require '../lib/flash_message.php';

$page_type = 'product';
$page_name = 'Produk Aktif Saya';
$user_id   = $check_user['rows']['id'];

$q_set    = $model->db_query($db,"*","settings","id=1");
$cfg      = $q_set['rows'];
$nama_web = htmlspecialchars($cfg['title']??'Platform');

$uq      = mysqli_fetch_assoc(mysqli_query($db,"SELECT saldo,point,profit FROM users WHERE id='$user_id'"));
$topup   = $uq['saldo']  ?? 0;
$ubal    = $uq['point']  ?? 0;
$uprofit = $uq['profit'] ?? 0;

$wdq      = mysqli_fetch_assoc(mysqli_query($db,"SELECT COALESCE(SUM(amount),0) t FROM withdraws WHERE user_id='$user_id' AND status='Success'"));
$total_wd = $wdq['t'] ?? 0;

$all_kat = [];
$qk = mysqli_query($db,"SELECT * FROM produk_kategori ORDER BY urutan ASC, id ASC");
while ($r = mysqli_fetch_assoc($qk)) $all_kat[] = $r;

$orders_by_kat = [];
foreach ($all_kat as $kat) {
    $kid = (int)$kat['id'];
    $q = mysqli_query($db,"
        SELECT o.*, k.nama as kat_nama, k.is_locked
        FROM orders o
        LEFT JOIN produk_investasi p ON o.produk_id = p.id
        LEFT JOIN produk_kategori k ON k.id = p.kategori_id
        WHERE o.user_id='$user_id' AND o.status='Active' AND p.kategori_id='$kid'
        ORDER BY o.created_at DESC");
    $orders_by_kat[$kid] = [];
    if ($q) while ($r = mysqli_fetch_assoc($q)) $orders_by_kat[$kid][] = $r;
}

$orders_done = [];
$q_done = mysqli_query($db,"
    SELECT o.*, k.nama as kat_nama, p.nama_produk as p_name
    FROM orders o
    LEFT JOIN produk_investasi p ON o.produk_id = p.id
    LEFT JOIN produk_kategori k ON k.id = p.kategori_id
    WHERE o.user_id='$user_id' AND o.status != 'Active'
    ORDER BY o.created_at DESC LIMIT 30");
if ($q_done) while ($r = mysqli_fetch_assoc($q_done)) $orders_done[] = $r;

$all_active = [];
foreach ($orders_by_kat as $list) $all_active = array_merge($all_active, $list);

$total_aktif         = count($all_active);
$total_investasi     = array_sum(array_column($all_active,'harga'));
$total_profit_harian = array_sum(array_column($all_active,'profit_harian'));

// Ambil app_images
$img_q = $db->query("SELECT * FROM app_images");
$app_images = [];
if ($img_q) while ($r = $img_q->fetch_assoc()) $app_images[$r['image_key']] = $r['image_url'];
$prod_img_url = !empty($app_images['product_image']) ? $app_images['product_image'] : 'https://placehold.co/100x120/eee/999?text=IMG';

require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>Aset Investasi – <?=$nama_web?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Poppins', sans-serif; background: #0A0A0A; color: #fff; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
    .app { 
        max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; 
        background: radial-gradient(circle at top right, #1a1a1a 0%, #0a0a0a 40%);
    }

    /* ====== HEADER ====== */
    .pr-head { display: flex; align-items: center; gap: 16px; padding: 25px 20px 20px; }
    .pr-back {
        width: 38px; height: 38px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none;
    }
    .pr-title h2 { font-size: 16px; font-weight: 800; color: #fff; line-height: 1.2; }
    .pr-title p { font-size: 10.5px; font-weight: 500; color: rgba(255,255,255,0.5); }

    /* ====== HERO BOX ====== */
    .hero-box {
        margin: 0 20px 20px; padding: 24px; border-radius: 20px;
        background: linear-gradient(135deg, #161616 0%, #0D0D0D 100%);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05), 0 10px 20px rgba(0,0,0,0.3);
        position: relative; overflow: hidden;
    }
    .hero-box::before {
        content: ''; position: absolute; right: -20px; top: -30px; width: 120px; height: 120px;
        background: radial-gradient(circle, rgba(197, 147, 39, 0.2) 0%, transparent 70%); border-radius: 50%;
    }
    .hb-title { font-size: 9.5px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
    .hb-main { font-size: 32px; font-weight: 800; color: #fff; line-height: 1; letter-spacing: -1px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 4px; }
    .hb-main span { font-size: 14px; font-weight: 600; margin-top: 4px; color: rgba(255,255,255,0.5); }
    
    .hb-split { display: flex; align-items: center; justify-content: space-between; gap: 15px; }
    .hbs-col { flex: 1; }
    .hbs-lbl { font-size: 9.5px; font-weight: 600; color: rgba(255,255,255,0.4); margin-bottom: 4px; }
    .hbs-val { font-size: 14px; font-weight: 700; color: #F5D061; }
    .hbs-line { width: 1px; height: 26px; background: rgba(255,255,255,0.1); }

    /* ====== TWIN BADGES ====== */
    .twin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 0 20px 25px; }
    .twin-badge {
        background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 12px; border-radius: 12px;
        display: flex; align-items: center; gap: 12px;
    }
    .tb-icon {
        width: 32px; height: 32px; background: rgba(197, 147, 39, 0.1); border-radius: 8px; color: #F5D061; 
        display: flex; align-items: center; justify-content: center; font-size: 14px;
    }
    .twin-badge:last-child .tb-icon { background: rgba(16, 185, 129, 0.1); color: #10B981; }
    
    .tb-info h4 { font-size: 11px; font-weight: 700; color: #fff; margin-bottom: 1px; }
    .tb-info p { font-size: 9px; font-weight: 500; color: rgba(255,255,255,0.4); }

    /* ====== TABS ====== */
    .cat-tabs { display: flex; gap: 8px; margin: 0 20px 20px; }
    .c-tab {
        flex: 1; text-align: center; font-size: 11px; font-weight: 700; padding: 10px; border-radius: 12px; border: none; cursor: pointer; transition: 0.2s;
        background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.5);
    }
    .c-tab.active { background: linear-gradient(135deg, #18181B 0%, #000000 100%); color: #F5D061; box-shadow: inset 0 0 0 1px rgba(197, 147, 39, 0.4); }

    /* ====== SECTION TITLE ====== */
    .sec-title { font-size: 14px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px; margin: 0 20px 15px; }
    .sec-title span { width: 3px; height: 14px; background: #F5D061; border-radius: 2px; }

    /* ====== PANES ====== */
    .pr-pane { display: none; }
    .pr-pane.show { display: block; animation: fadeUp 0.3s ease; }
    @keyframes fadeUp { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }

    /* ====== TRACKING CARD (Active) ====== */
    .trk-card {
        margin: 0 20px 15px; background: rgba(255,255,255,0.02); border-radius: 16px; padding: 16px; 
        border: 1px solid rgba(255,255,255,0.05);
    }
    .tc-top { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .tc-icon { width: 44px; height: 44px; border-radius: 10px; overflow: hidden; background: #fff; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.1); }
    .tc-icon img { width: 100%; height: 100%; object-fit: cover; }
    .tc-info h4 { font-size: 13px; font-weight: 800; color: #fff; margin-bottom: 2px; text-transform: uppercase;}
    .tc-info p { font-size: 9.5px; font-weight: 500; color: rgba(255,255,255,0.4); }
    
    .tc-grid { display: flex; justify-content: space-between; background: rgba(0,0,0,0.2); border-radius: 10px; padding: 12px; margin-bottom: 16px; }
    .tcg-item { display: flex; flex-direction: column; gap: 4px; }
    .tcg-item:nth-child(2) { align-items: center; }
    .tcg-item:last-child { align-items: flex-end; }
    .tcg-item span { font-size: 8.5px; font-weight: 700; color: rgba(255,255,255,0.3); text-transform: uppercase; }
    .tcg-item div { font-size: 11px; font-weight: 700; color: #fff; }
    .tcg-item:nth-child(2) div { color: #10B981; }

    .tc-prog-text { display: flex; justify-content: space-between; font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.5); margin-bottom: 6px; }
    .tc-prog-bar { width: 100%; height: 6px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden; }
    .tc-prog-fill { height: 100%; background: linear-gradient(90deg, #C59327 0%, #F5D061 100%); border-radius: 4px; box-shadow: 0 0 10px rgba(245, 208, 97, 0.5); transition: width 0.3s; }
    .tc-prog-fill.done { background: #10B981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }

    /* ====== EMPTY STATE ====== */
    .empty-state { text-align: center; padding: 40px 30px; }
    .empty-state h3 { font-size: 15px; font-weight: 800; color: rgba(255,255,255,0.8); margin-bottom: 8px; }
    .empty-state p { font-size: 11px; color: rgba(255,255,255,0.4); line-height: 1.5; margin-bottom: 20px; }
    .empty-btn { display: inline-block; background: #F5D061; color: #111; font-size: 12px; font-weight: 800; padding: 10px 24px; border-radius: 20px; text-decoration: none; }

</style>
</head>
<body>
<div class="app">

    <!-- HEADER -->
    <div class="pr-head">
        <a href="<?= base_url() ?>" class="pr-back"><i class="fa-solid fa-chevron-left"></i></a>
        <div class="pr-title">
            <h2>Investasi Saya</h2>
            <p>Paket aktif & pendapatan</p>
        </div>
    </div>

    <!-- HERO BOX -->
    <div class="hero-box">
        <div class="hb-title">Total Pendapatan (Point)</div>
        <div class="hb-main"><span>Rp</span><?= number_format($ubal, 0, ',', '.') ?></div>
        
        <div class="hb-split">
            <div class="hbs-col">
                <div class="hbs-lbl">Saldo Aktif Investasi</div>
                <div class="hbs-val">Rp<?= number_format($total_investasi, 0, ',', '.') ?></div>
            </div>
            <div class="hbs-line"></div>
            <div class="hbs-col">
                <div class="hbs-lbl">Estimasi Harian</div>
                <div class="hbs-val">Rp<?= number_format($total_profit_harian, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- TWIN BADGES -->
    <div class="twin-grid">
        <div class="twin-badge">
            <div class="tb-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
            <div class="tb-info">
                <h4>Aktif</h4>
                <p>Semua Berjalan</p>
            </div>
        </div>
        <div class="twin-badge">
            <div class="tb-icon"><i class="fa-solid fa-shield-check"></i></div>
            <div class="tb-info">
                <h4>Aman</h4>
                <p>Terverifikasi</p>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="cat-tabs">
        <button class="c-tab active" onclick="swTab('active', this)">Sedang Aktif</button>
        <button class="c-tab" onclick="swTab('done', this)">Riwayat Selesai</button>
    </div>

    <!-- PANE ACTIVE -->
    <div class="pr-pane show" id="pane-active">
        <div class="sec-title"><span></span> Paket Investasi Aktif</div>
        
        <?php if(empty($all_active)): ?>
           <div class="empty-state">
              <i class="fa-solid fa-box-open" style="font-size:36px;color:rgba(255,255,255,0.1);margin-bottom:15px;"></i>
              <h3>Belum Ada Aset</h3>
              <p>Anda belum memiliki paket investasi yang berjalan aktif saat ini. Mulai investasi sekarang!</p>
           </div>
        <?php else: ?>
           <?php foreach($all_active as $row):
                $masa  = max(1, (int)$row['masa_aktif']);
                $jalan = max(0, floor((time() - strtotime($row['created_at'])) / 86400));
                if ($jalan > $masa) $jalan = $masa;
                $prog  = min(100, round(($jalan / $masa) * 100));
                $sisa  = max(0, $masa - $jalan);
                
                $h_fmt = number_format($row['harga'], 0, ',', '.');
                $p_fmt = number_format($row['profit_harian'], 0, ',', '.');
                $t_fmt = number_format($row['total_profit'], 0, ',', '.');
           ?>
           <div class="trk-card">
               <div class="tc-top">
                   <div class="tc-icon"><img src="<?= htmlspecialchars($prod_img_url) ?>" alt="Prod"></div>
                   <div class="tc-info">
                       <h4><?= htmlspecialchars($row['nama_produk'] ?: $row['p_name'] ?: 'Paket Investasi') ?></h4>
                       <p>Dibeli: <?= date('d M Y, H:i', strtotime($row['created_at'])) ?></p>
                   </div>
               </div>
               <div class="tc-grid">
                   <div class="tcg-item"><span>Harga</span><div>Rp<?=$h_fmt?></div></div>
                   <div class="tcg-item"><span>Harian</span><div>Rp<?=$p_fmt?></div></div>
                   <div class="tcg-item"><span>Estimasi</span><div>Rp<?=$t_fmt?></div></div>
               </div>
               <div class="tc-prog-text">
                   <span>Hari ke-<?=$jalan?> dari <?=$masa?></span>
                   <span style="color:#F5D061;">Sisa <?=$sisa?> Hari</span>
               </div>
               <div class="tc-prog-bar">
                   <div class="tc-prog-fill" style="width:<?=$prog?>%;"></div>
               </div>
           </div>
           <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PANE DONE -->
    <div class="pr-pane" id="pane-done">
        <div class="sec-title"><span></span> Riwayat Selesai</div>

        <?php if(empty($orders_done)): ?>
           <div class="empty-state">
              <i class="fa-solid fa-clock-rotate-left" style="font-size:36px;color:rgba(255,255,255,0.1);margin-bottom:15px;"></i>
              <h3>Belum Ada Riwayat</h3>
              <p>Paket investasi yang sudah selesai siklusnya akan tampil di sini.</p>
           </div>
        <?php else: ?>
           <?php foreach($orders_done as $row):
                $masa  = max(1, (int)$row['masa_aktif']);
                $h_fmt = number_format($row['harga'], 0, ',', '.');
                $t_fmt = number_format($row['total_profit'], 0, ',', '.');
           ?>
           <div class="trk-card" style="opacity: 0.65;">
               <div class="tc-top">
                   <div class="tc-icon" style="filter: grayscale(1);"><img src="<?= htmlspecialchars($prod_img_url) ?>" alt="Prod"></div>
                   <div class="tc-info">
                       <h4 style="color:rgba(255,255,255,0.6);"><?= htmlspecialchars($row['nama_produk'] ?: $row['p_name'] ?: 'Paket Investasi') ?></h4>
                       <p>Selesai: <?= date('d M Y', strtotime($row['created_at'] . " +$masa days")) ?></p>
                   </div>
               </div>
               <div class="tc-grid">
                   <div class="tcg-item"><span>Modal</span><div style="color:rgba(255,255,255,0.6);">Rp<?=$h_fmt?></div></div>
                   <div class="tcg-item"><span>Status</span><div style="color:#10B981;">Selesai</div></div>
                   <div class="tcg-item"><span>Return</span><div style="color:rgba(255,255,255,0.6);">Rp<?=$t_fmt?></div></div>
               </div>
               <div class="tc-prog-text">
                   <span>Siklus Penuh (<?=$masa?> Hr)</span>
                   <span style="color:#10B981;">100% Selesai</span>
               </div>
               <div class="tc-prog-bar">
                   <div class="tc-prog-fill done" style="width:100%;"></div>
               </div>
           </div>
           <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function swTab(id, btn) {
    document.querySelectorAll('.pr-pane').forEach(p => p.classList.remove('show'));
    document.querySelectorAll('.c-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('pane-' + id).classList.add('show');
    btn.classList.add('active');
}
</script>

<?php require '../lib/footer_user.php'; ?>
</body>
</html>