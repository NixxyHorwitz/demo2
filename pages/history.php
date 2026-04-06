<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../mainconfig.php';
require_once __DIR__ . '/../lib/check_session.php';
require_once __DIR__ . '/../lib/is_login.php';

$uid     = $login['id'];
$uid_esc = $db->real_escape_string($uid);

/* ── FILTER ── */
$filter  = $_GET['filter'] ?? 'deposit';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perpage = 15;
$offset  = ($page - 1) * $perpage;

/* ── STATS: Pemasukan & Penarikan ── */
$sum_in_q = mysqli_query($db, "SELECT SUM(amount) as t FROM topups WHERE user_id='$uid_esc' AND status='Success'");
$sum_in = (float)(mysqli_fetch_assoc($sum_in_q)['t'] ?? 0);

$total_masuk = $sum_in;

$sum_out_q = mysqli_query($db, "SELECT SUM(amount) as t FROM withdraws WHERE user_id='$uid_esc' AND status='Success'");
$total_tarik = (float)(mysqli_fetch_assoc($sum_out_q)['t'] ?? 0);

/* ── FETCH: Deposits ── */
$deposits = [];
if (in_array($filter, ['all','deposit'])) {
    $q = mysqli_query($db,
        "SELECT 'deposit' as jenis, trxtopup as ref, method, amount, status, created_at,
                COALESCE(pay_url,'') as pay_url
         FROM topups WHERE user_id='$uid_esc'
         ORDER BY created_at DESC LIMIT 200"
    );
    if ($q) while ($r = mysqli_fetch_assoc($q)) $deposits[] = $r;
}

/* ── FETCH: Withdrawals ── */
$withdrawals = [];
if (in_array($filter, ['all','withdraw'])) {
    $ref_col = 'id';
    $cols_q  = mysqli_query($db, "SHOW COLUMNS FROM withdraws");
    if ($cols_q) {
        $col_names = [];
        while ($c = mysqli_fetch_assoc($cols_q)) $col_names[] = $c['Field'];
        if (in_array('plat_order_num', $col_names))   $ref_col = 'plat_order_num';
        elseif (in_array('order_num', $col_names))     $ref_col = 'order_num';
        elseif (in_array('trxwithdraw', $col_names))   $ref_col = 'trxwithdraw';
        elseif (in_array('trx_id', $col_names))        $ref_col = 'trx_id';
        elseif (in_array('reference', $col_names))     $ref_col = 'reference';
    }
    $q = mysqli_query($db,
        "SELECT 'withdraw' as jenis, $ref_col as ref, method, amount, status, created_at, '' as pay_url
         FROM withdraws WHERE user_id='$uid_esc'
         ORDER BY created_at DESC LIMIT 200"
    );
    if ($q) while ($r = mysqli_fetch_assoc($q)) $withdrawals[] = $r;
}

/* ── MERGE & SORT ── */
$all = array_merge($deposits, $withdrawals);
usort($all, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$total    = count($all);
$items    = array_slice($all, $offset, $perpage);
$has_more = ($offset + $perpage) < $total;

/* ── SUMMARY COUNTS ── */
$cnt_deposit  = count($deposits);
$cnt_withdraw = count($withdrawals);

$page_name = 'Riwayat Transaksi';
require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>History &bullet; <?= htmlspecialchars($site_name ?? 'Platform') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Poppins', sans-serif; background: #012b26; color: #fff; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
    .app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; background: #012b26; }

    /* ====== HEADER (CURVED Theme) ====== */
    .h-bg { background: linear-gradient(135deg, #023e35 0%, #01312b 100%); padding: 25px 20px 80px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; position: relative; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1; }
    .h-nav { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
    .back-btn { width: 36px; height: 36px; border-radius: 12px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
    .back-btn:active { background: rgba(255,255,255,0.1); }
    .h-title { display: flex; flex-direction: column; }
    .h-title h3 { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 2px; }
    .h-title p { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.7); }

    /* ====== TOP BOXES (Floating inside curved header) ====== */
    .h-top-boxes { display: flex; gap: 10px; margin-top: -50px; padding: 0 20px; position: relative; z-index: 2; margin-bottom: 20px;}
    .htb-item {
        flex: 1; background: #023e35; border: 1px solid rgba(250, 204, 21, 0.25);
        border-radius: 16px; padding: 12px; display: flex; flex-direction: column; gap: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    .htb-icon {
        width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px;
        background: rgba(250, 204, 21, 0.1); color: #facc15;
    }
    .htb-icon.out {
        background: rgba(239, 68, 68, 0.1); color: #f87171;
    }
    
    .htb-lbl { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 2px;}
    .htb-val { font-size: 14.5px; font-weight: 800; font-family: 'Poppins', sans-serif;}
    .htb-val.in { color: #facc15; }
    .htb-val.out { color: #f87171; }

    /* ====== FILTER TABS ====== */
    .h-filter-bar {
        display: flex; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px; margin: 0 20px 15px; padding: 4px; gap: 4px;
    }
    .hf-btn {
        flex: 1; text-align: center; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.5); text-decoration: none;
        padding: 10px 0; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 6px;
        transition: 0.2s;
    }
    .hf-btn span {
        background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 8px; font-size: 9px; font-weight: 700; color: #fff;
    }
    .hf-btn.active {
        background: #023e35;
        border: 1px solid #035246; color: #facc15; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .hf-btn.active span { background: rgba(250, 204, 21, 0.2); color: #facc15; }

    /* ====== SECTION TITLE ====== */
    .sec-title { font-size: 12px; font-weight: 800; color: #fff; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; margin: 0 20px 12px; text-transform: uppercase; }
    .sec-title span { width: 4px; height: 12px; background: #facc15; border-radius: 2px; }

    /* ====== LIST ITEMS (TRANSACTION CARD) ====== */
    .record-list { padding: 0 20px; display: flex; flex-direction: column; gap: 12px; }
    .trx-card {
        background: #023e35; border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px; padding: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .tc-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .tc-head-l { display: flex; align-items: center; gap: 12px; }
    .tc-icon {
        width: 36px; height: 36px; background: rgba(250, 204, 21, 0.1); border: 1px solid rgba(250, 204, 21, 0.2);
        border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #facc15;
    }
    .tc-icon.out {
         background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #f87171;
    }
    .tc-title h4 { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 2px; }
    .tc-title p { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.5); text-transform: capitalize; }
    
    .tc-badge { font-size: 8.5px; font-weight: 800; padding: 4px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;}
    .tc-badge.success { background: rgba(250, 204, 21, 0.15); color: #facc15; border: 1px solid rgba(250, 204, 21, 0.3); }
    .tc-badge.warning { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .tc-badge.danger  { background: rgba(244, 63, 94, 0.1); color: #fca5a5; border: 1px solid rgba(244, 63, 94, 0.3); }
    .tc-badge.neutral { background: rgba(255, 255, 255, 0.05); color: #ccc; border: 1px solid rgba(255, 255, 255, 0.1); }

    .tc-mid {
        background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 12px; position: relative; overflow: hidden;
    }
    .tcm-l p { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.5); margin-bottom: 4px; }
    .tcm-l h3 { font-size: 18px; font-weight: 800; color: #fff; }
    .tcm-l h3 span { font-size: 18px; margin-right: 2px; }
    
    .tcm-r { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; background: rgba(250, 204, 21, 0.05); border: 1px solid rgba(250, 204, 21, 0.2); color: #facc15; }
    .tcm-r.out { background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
    .tcm-r i { display: block; }

    .tc-bot { display: flex; gap: 8px; }
    .tcb-box {
        flex: 1; background: #012b26; border: 1px solid rgba(255,255,255,0.05); border-radius: 10px;
        padding: 10px; display: flex; align-items: center; gap: 10px;
    }
    .tcb-box i { font-size: 12px; color: rgba(255,255,255,0.4); }
    .tcb-box p { font-size: 8.5px; font-weight: 700; color: rgba(255,255,255,0.4); margin-bottom: 2px; }
    .tcb-box h5 { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.8); }

    /* LOAD MORE */
    .load-more {
        display: block; width: calc(100% - 40px); margin: 20px auto 30px; text-align: center; padding: 14px; 
        font-size: 12px; font-weight: 800; color: #012b26; background: #facc15; 
        border-radius: 14px; text-decoration: none; border: none; font-family: 'Poppins', sans-serif;
        box-shadow: 0 4px 15px rgba(250, 204, 21, 0.2); transition: 0.2s;
    }
    .load-more:active { transform: scale(0.96); }
</style>
</head>
<body>
<div class="app">

    <!-- HEADER CURVED -->
    <div class="h-bg">
        <div class="h-nav">
            <a href="javascript:history.back()" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
            <div class="h-title">
                <h3>Riwayat Transaksi</h3>
                <p>Semua catatan keuangan Anda</p>
            </div>
        </div>
    </div>

    <!-- TOP BOXES -->
    <div class="h-top-boxes">
       <div class="htb-item">
          <div class="htb-icon in"><i class="fa-solid fa-arrow-up"></i></div>
          <div class="htb-lbl">Total Pemasukan</div>
          <div class="htb-val in">Rp<?=number_format($total_masuk,0,',','.')?></div>
       </div>
       <div class="htb-item">
          <div class="htb-icon out"><i class="fa-solid fa-arrow-down"></i></div>
          <div class="htb-lbl">Total Penarikan</div>
          <div class="htb-val out">Rp<?=number_format($total_tarik,0,',','.')?></div>
       </div>
    </div>

    <!-- FILTER TABS -->
    <div class="h-filter-bar">
       <a href="?filter=deposit" class="hf-btn <?=$filter=='deposit'?'active':''?>">
          <i class="fa-solid fa-cloud-arrow-up"></i> Deposit <span><?=$cnt_deposit?></span>
       </a>
       <a href="?filter=withdraw" class="hf-btn <?=$filter=='withdraw'?'active':''?>">
          <i class="fa-solid fa-money-bill-transfer"></i> Tarik <span><?=$cnt_withdraw?></span>
       </a>
    </div>

    <!-- LIST -->
    <div class="sec-title"><span></span> Daftar Transaksi Terakhir</div>
    <div class="record-list">
        <?php if(empty($items)): ?>
            <div style="text-align:center; padding: 40px 10px; color:rgba(255,255,255,0.4); font-size:12px; background: #023e35; border-radius: 16px; border: 1px solid #035246;">Belum ada catatan transaksi di sini.</div>
        <?php else: ?>
            <?php foreach($items as $item): 
                $jenis  = $item['jenis'];
                $method = htmlspecialchars($item['method'] ?? '-');
                $amt    = number_format((float)($item['amount']??0), 0, ',', '.');
                
                $date_only = $item['created_at'] ? date('d M Y', strtotime($item['created_at'])) : '-';
                $time_only = $item['created_at'] ? date('H:i', strtotime($item['created_at'])) : '-';

                if ($jenis == 'deposit') {
                    $icon = '<i class="fa-solid fa-wallet"></i>';
                    $sub  = 'Isi Saldo';
                    $color_class = 'in';
                    $icon_arrow = 'fa-arrow-trend-up';
                    $icon_cl = 'in';
                } else {
                    $icon = '<i class="fa-solid fa-building-columns"></i>';
                    $sub  = 'Penarikan Dana';
                    $color_class = 'out';
                    $icon_arrow = 'fa-arrow-trend-down';
                    $icon_cl = 'out';
                }
                
                $st_lower = strtolower($item['status'] ?? '-');
                $badge_tx = strtoupper(match($st_lower) {
                    'success','paid'  => 'SUKSES',
                    'completed'       => 'SELESAI',
                    'pending'         => 'PENDING',
                    'processing'      => 'PROSES',
                    'active'          => 'AKTIF',
                    'failed'          => 'GAGAL',
                    'expired'         => 'KEDALUWARSA',
                    'rejected'        => 'DITOLAK',
                    default           => ucfirst($item['status'])
                });
                
                $bg_color = match($badge_tx) {
                    'SUKSES','SELESAI','AKTIF' => 'success',
                    'PENDING','PROSES'         => 'warning',
                    'GAGAL','DITOLAK'          => 'danger',
                    default                    => 'neutral'
                };
            ?>
            <div class="trx-card">
               <div class="tc-head">
                   <div class="tc-head-l">
                       <div class="tc-icon <?=$icon_cl?>"><?=$icon?></div>
                       <div class="tc-title">
                           <h4><?=$method?></h4>
                           <p><?=$sub?></p>
                       </div>
                   </div>
                   <div class="tc-head-r">
                       <span class="tc-badge <?=$bg_color?>">&bull; <?=$badge_tx?></span>
                   </div>
               </div>

               <div class="tc-mid">
                   <div class="tcm-l">
                       <p>Total Nominal</p>
                       <h3><span>Rp</span> <?=$amt?></h3>
                   </div>
                   <div class="tcm-r <?=$color_class?>"><i class="fa-solid <?=$icon_arrow?>"></i></div>
               </div>

               <div class="tc-bot">
                   <div class="tcb-box">
                       <i class="fa-regular fa-calendar"></i>
                       <div>
                           <p>TANGGAL</p>
                           <h5><?=$date_only?></h5>
                       </div>
                   </div>
                   <div class="tcb-box">
                       <i class="fa-regular fa-clock"></i>
                       <div>
                           <p>WAKTU</p>
                           <h5><?=$time_only?></h5>
                       </div>
                   </div>
               </div>
               <?php if ($jenis === 'deposit' && in_array($st_lower, ['pending'])): ?>
                   <a href="<?= base_url('pages/pay?trxid=' . $item['ref']) ?>" style="display:block;margin-top:12px;background:#facc15;color:#012b26;text-decoration:none;text-align:center;padding:12px;border-radius:10px;font-size:12px;font-weight:800;font-family:'Poppins',sans-serif;">
                       <i class="fa-solid fa-arrow-up-right-from-square" style="margin-right:4px;"></i> Lanjutkan Pembayaran
                   </a>
               <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if($has_more): ?>
        <a href="?filter=<?=$filter?>&page=<?=$page+1?>" class="load-more">Tampilkan Lebih Banyak</a>
    <?php endif; ?>

</div>
<?php require '../lib/footer_user.php'; ?>
</body>
</html>