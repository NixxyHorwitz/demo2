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
$uprofit = profitDisplay($db, $user_id) ? ($uq['profit'] ?? 0) : 0;

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
    body { font-family: 'Poppins', sans-serif; background: #012b26; color: #fff; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
    .app { 
        max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; 
    }

    /* ====== HEADER CURVED ====== */
    .h-bg { background: linear-gradient(135deg, #023e35 0%, #01312b 100%); padding: 25px 20px 100px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; position: relative; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1; }
    .h-nav { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
    .back-btn { width: 36px; height: 36px; border-radius: 12px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
    .back-btn:active { background: rgba(255,255,255,0.1); }
    .h-title { display: flex; flex-direction: column; }
    .h-title h3 { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 2px; }
    .h-title p { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.7); }

    /* ====== HERO BOX ====== */
    .hero-box {
        margin: -60px 20px 20px; padding: 22px; border-radius: 20px;
        background: #023e35;
        border: 1px solid rgba(250, 204, 21, 0.25);
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        position: relative; z-index: 2; overflow: hidden;
    }
    .hero-box::before {
        content: ''; position: absolute; right: -20px; top: -30px; width: 120px; height: 120px;
        background: radial-gradient(circle, rgba(250, 204, 21, 0.15) 0%, transparent 70%); border-radius: 50%;
    }
    .hb-title { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
    .hb-title i { color: #facc15; font-size: 12px; }
    .hb-main { font-size: 32px; font-weight: 800; color: #fff; line-height: 1; letter-spacing: -1px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 4px; }
    .hb-main span { font-size: 14px; font-weight: 700; margin-top: 4px; color: #facc15; }
    
    .hb-split { display: flex; align-items: center; justify-content: space-between; gap: 15px; background: rgba(1, 43, 38, 0.6); padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
    .hbs-col { flex: 1; }
    .hbs-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.5); margin-bottom: 4px; text-transform: uppercase; }
    .hbs-val { font-size: 14px; font-weight: 800; color: #fff; }
    .hbs-val.green { color: #34d399; }
    .hbs-line { width: 1px; height: 30px; background: rgba(255,255,255,0.1); }

    /* ====== TWIN BADGES ====== */
    .twin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 0 20px 20px; }
    .twin-badge {
        background: #023e35; border: 1px solid rgba(255,255,255,0.05); padding: 12px 14px; border-radius: 16px;
        display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .tb-icon {
        width: 34px; height: 34px; background: rgba(250, 204, 21, 0.1); border-radius: 10px; color: #facc15; 
        display: flex; align-items: center; justify-content: center; font-size: 14px; border: 1px solid rgba(250, 204, 21, 0.2);
    }
    .twin-badge:last-child .tb-icon { background: rgba(52, 211, 153, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.2);}
    
    .tb-info h4 { font-size: 12px; font-weight: 800; color: #fff; margin-bottom: 2px; }
    .tb-info p { font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.5); }

    /* ====== TABS ====== */
    .cat-tabs { display: flex; gap: 8px; margin: 0 20px 20px; background: rgba(255,255,255,0.03); padding: 4px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.05); }
    .c-tab {
        flex: 1; text-align: center; font-size: 11px; font-weight: 800; padding: 12px 0; border-radius: 10px; border: none; cursor: pointer; transition: 0.2s;
        background: transparent; color: rgba(255,255,255,0.5); font-family: 'Poppins', sans-serif;
    }
    .c-tab.active { background: #023e35; color: #facc15; box-shadow: 0 2px 8px rgba(0,0,0,0.2); border: 1px solid rgba(250, 204, 21, 0.2); }

    /* ====== PANE & TRACKING CARDS ====== */
    .pr-pane { display: none; }
    .pr-pane.show { display: block; animation: fadeUp 0.3s ease; }
    @keyframes fadeUp { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }

    .sec-title { font-size: 13px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px; margin: 0 20px 14px; text-transform: uppercase; letter-spacing: 0.5px;}
    .sec-title span { width: 4px; height: 14px; background: #facc15; border-radius: 2px; }

    .trk-card {
        margin: 0 20px 16px; background: #023e35; border-radius: 18px; padding: 16px; 
        border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .tc-top { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
    .tc-icon { width: 48px; height: 48px; border-radius: 12px; overflow: hidden; background: #011f1c; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; }
    .tc-icon img { width: 100%; height: 100%; object-fit: cover; }
    .tc-info { flex: 1; min-width: 0; }
    .tc-info h4 { font-size: 14px; font-weight: 800; color: #fff; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;}
    .tc-info p { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.5); }
    .tc-badge { padding: 4px 8px; border-radius: 20px; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .tc-badge.active { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .tc-badge.done { background: rgba(255, 255, 255, 0.1); color: #ccc; border: 1px solid rgba(255, 255, 255, 0.2); }
    
    .tc-grid { display: flex; justify-content: space-between; background: rgba(1, 43, 38, 0.6); border-radius: 12px; padding: 12px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.03); }
    .tcg-item { display: flex; flex-direction: column; justify-content: center;}
    .tcg-item:nth-child(2) { align-items: center; text-align: center; }
    .tcg-item:last-child { align-items: flex-end; text-align: right; }
    .tcg-item span { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-bottom: 4px; }
    .tcg-item div { font-size: 13px; font-weight: 800; color: #fff; }
    .tcg-item:nth-child(2) div { color: #34d399; }
    .tcg-item:last-child div { color: #facc15; }

    .tc-prog-text { display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.6); margin-bottom: 8px; }
    .tc-prog-text b { color: #facc15; font-weight: 800;}
    .tc-prog-bar { width: 100%; height: 8px; background: rgba(0,0,0,0.3); border-radius: 6px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.5); }
    .tc-prog-fill { height: 100%; background: linear-gradient(90deg, #ca8a04 0%, #facc15 100%); border-radius: 6px; box-shadow: 0 0 10px rgba(250, 204, 21, 0.4); transition: width 0.4s ease; }
    .tc-prog-fill.done { background: #34d399; box-shadow: 0 0 10px rgba(52, 211, 153, 0.4); }

    /* ====== EMPTY STATE ====== */
    .empty-state { text-align: center; padding: 40px 30px; margin: 0 20px; background: rgba(2, 62, 53, 0.5); border: 1px dashed rgba(255,255,255,0.1); border-radius: 20px; }
    .empty-state h3 { font-size: 15px; font-weight: 800; color: rgba(255,255,255,0.8); margin-bottom: 8px; }
    .empty-state p { font-size: 11px; color: rgba(255,255,255,0.5); line-height: 1.5; font-weight: 500;}

</style>
</head>
<body>
<div class="app">

    <!-- HEADER CURVED -->
    <div class="h-bg">
        <div class="h-nav">
            <a href="<?= base_url() ?>" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
            <div class="h-title">
                <h3>Aset Investasi Saya</h3>
                <p>Pantau paket & pendapatan</p>
            </div>
        </div>
    </div>

    <!-- HERO BOX -->
    <div class="hero-box">
        <div class="hb-title"><i class="fa-solid fa-wallet"></i> Total Pendapatan Anda</div>
        <div class="hb-main"><span>Rp</span><?= number_format($ubal, 0, ',', '.') ?></div>
        
        <div class="hb-split">
            <div class="hbs-col">
                <div class="hbs-lbl">Modal Aktif</div>
                <div class="hbs-val">Rp<?= number_format($total_investasi, 0, ',', '.') ?></div>
            </div>
            <div class="hbs-line"></div>
            <div class="hbs-col">
                <div class="hbs-lbl">Profit Harian</div>
                <div class="hbs-val green">+Rp<?= number_format($total_profit_harian, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- TWIN BADGES -->
    <div class="twin-grid">
        <div class="twin-badge">
            <div class="tb-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
            <div class="tb-info">
                <h4><?=$total_aktif?> Aktif</h4>
                <p>Paket Berjalan</p>
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
        <button class="c-tab active" onclick="swTab('active', this)"><i class="fa-solid fa-play" style="margin-right:4px;"></i> Sedang Aktif</button>
        <button class="c-tab" onclick="swTab('done', this)"><i class="fa-solid fa-clock-rotate-left" style="margin-right:4px;"></i> Selesai Sempurna</button>
    </div>

    <!-- PANE ACTIVE -->
    <div class="pr-pane show" id="pane-active">
        <div class="sec-title"><span></span> Daftar Paket Aktif (<?=$total_aktif?>)</div>
        
        <?php if(empty($all_active)): ?>
           <div class="empty-state">
              <i class="fa-solid fa-box-open" style="font-size:40px;color:rgba(255,255,255,0.15);margin-bottom:15px;"></i>
              <h3>Belum Ada Aset</h3>
              <p>Anda belum memiliki paket investasi yang berjalan aktif saat ini. Mulai investasi sekarang dan raih profit berlipat!</p>
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
                       <p>Dibeli: <?= date('d M Y', strtotime($row['created_at'])) ?></p>
                   </div>
                   <div class="tc-badge active"><i class="fa-solid fa-circle" style="font-size:6px; margin-right:3px; vertical-align:middle;"></i> AKTIF</div>
               </div>
               <div class="tc-grid">
                   <div class="tcg-item"><span>Modal</span><div>Rp<?=$h_fmt?></div></div>
                   <div class="tcg-item"><span>Harian</span><div>+Rp<?=$p_fmt?></div></div>
                   <div class="tcg-item"><span>Total Profit</span><div>Rp<?=$t_fmt?></div></div>
               </div>
               <div class="tc-prog-text">
                   <span>Perkembangan Siklus</span>
                   <b>Sisa <?=$sisa?> Hari</b>
               </div>
               <div class="tc-prog-bar">
                   <div class="tc-prog-fill" style="width:<?=$prog?>%;"></div>
               </div>
               <div style="font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.4); text-align: right; margin-top: 6px;">Hari ke-<?=$jalan?> dari <?=$masa?></div>
           </div>
           <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PANE DONE -->
    <div class="pr-pane" id="pane-done">
        <div class="sec-title"><span></span> Riwayat Selesai</div>

        <?php if(empty($orders_done)): ?>
           <div class="empty-state">
              <i class="fa-solid fa-clock-rotate-left" style="font-size:40px;color:rgba(255,255,255,0.15);margin-bottom:15px;"></i>
              <h3>Belum Ada Riwayat</h3>
              <p>Paket investasi yang sudah selesai siklusnya 100% akan tampil di sini untuk keperluan pencatatan Anda.</p>
           </div>
        <?php else: ?>
           <?php foreach($orders_done as $row):
                $masa  = max(1, (int)$row['masa_aktif']);
                $h_fmt = number_format($row['harga'], 0, ',', '.');
                $t_fmt = number_format($row['total_profit'], 0, ',', '.');
           ?>
           <div class="trk-card" style="opacity: 0.7;">
               <div class="tc-top">
                   <div class="tc-icon" style="filter: grayscale(1);"><img src="<?= htmlspecialchars($prod_img_url) ?>" alt="Prod"></div>
                   <div class="tc-info">
                       <h4 style="color:rgba(255,255,255,0.7);"><?= htmlspecialchars($row['nama_produk'] ?: $row['p_name'] ?: 'Paket Investasi') ?></h4>
                       <p>Selesai: <?= date('d M Y', strtotime($row['created_at'] . " +$masa days")) ?></p>
                   </div>
                   <div class="tc-badge done"><i class="fa-solid fa-check" style="font-size:8px; margin-right:3px;"></i> SELESAI</div>
               </div>
               <div class="tc-grid">
                   <div class="tcg-item"><span>Harga Beli</span><div style="color:rgba(255,255,255,0.7);">Rp<?=$h_fmt?></div></div>
                   <div class="tcg-item"><span>Status</span><div style="color:#34d399;">Selesai</div></div>
                   <div class="tcg-item"><span>Return Diterima</span><div style="color:rgba(255,255,255,0.7);">Rp<?=$t_fmt?></div></div>
               </div>
               <div class="tc-prog-text">
                   <span>Siklus Penuh Berakhir (<?=$masa?> Hr)</span>
                   <b style="color:#34d399;">100% Berhasil</b>
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