<?php
require '../mainconfig.php';
require '../lib/is_login.php';
require '../lib/flash_message.php';

$page_type = 'order';
$page_name = 'Detail Saham';
$user_id   = $check_user['rows']['id'];

$q_set = $model->db_query($db,"*","settings","id=1");
$cfg   = $q_set['rows'];
$nama_web = htmlspecialchars($cfg['title']??'Platform');

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query_order = mysqli_query($db,"
    SELECT o.*, p.persentase as roi_percentage,
           k.nama as kat_nama, COALESCE(k.is_locked,0) as is_locked
    FROM orders o
    LEFT JOIN produk_investasi p ON o.produk_id = p.id
    LEFT JOIN produk_kategori k ON k.id = p.kategori_id
    WHERE o.id='$order_id' AND o.user_id='$user_id'");
if(!$query_order||mysqli_num_rows($query_order)==0){ header("Location: product"); exit; }
$order = mysqli_fetch_assoc($query_order);

$profit_h        = (int)($order['profit_harian'] ?? 1);
$total_masa_awal = ($profit_h > 0) ? (int)round($order['total_profit'] / $profit_h) : (int)($order['masa_aktif'] ?? 30);
$sisa_hari       = max(0, (int)$order['masa_aktif']);
$hari_berjalan   = max(0, $total_masa_awal - $sisa_hari);
$progress        = $total_masa_awal > 0 ? min(100, round(($hari_berjalan / $total_masa_awal) * 100)) : 0;
$is_active       = ($order['status'] === 'Active');
$is_locked       = (bool)($order['is_locked'] ?? false);
$dest_label      = $is_locked ? 'Profit' : 'Point';
$dest_icon       = $is_locked ? 'ph-lock-key' : 'ph-wallet';
$profit_label    = $is_locked ? 'Profit Harian' : 'Profit Point Harian';

$order_log_id = (int)$order['id'];
$query_logs = mysqli_query($db,"
    SELECT * FROM point_logs
    WHERE user_id='$user_id' AND type='hasil'
    AND (
        description LIKE 'Profit Harian Produk $order_log_id Tanggal%'
        OR description LIKE 'Profit Point Harian Produk $order_log_id Tanggal%'
    )
    ORDER BY created_at DESC");
$profit_logs=[];
if($query_logs) while($r=mysqli_fetch_assoc($query_logs)) $profit_logs[]=$r;
$total_earned = array_sum(array_column($profit_logs,'amount'));
$days_paid    = count($profit_logs);
$earned_pct   = ($order['total_profit'] > 0) ? min(100, round(($total_earned / $order['total_profit']) * 100)) : 0;

function getProductIcon($name) {
    $n = strtolower($name);
    if (str_contains($n,'blue chip')||str_contains($n,'lq45')) return 'buildings';
    if (str_contains($n,'dividen')||str_contains($n,'konsisten')) return 'hand-coins';
    if (str_contains($n,'growth')||str_contains($n,'agresif')) return 'trend-up';
    if (str_contains($n,'perbankan')||str_contains($n,'bank')) return 'bank';
    if (str_contains($n,'infrastruktur')||str_contains($n,'konstruksi')) return 'crane';
    if (str_contains($n,'energi')||str_contains($n,'pertambangan')) return 'lightning';
    if (str_contains($n,'teknologi')||str_contains($n,'digital')||str_contains($n,'tech')) return 'cpu';
    if (str_contains($n,'consumer')||str_contains($n,'goods')) return 'shopping-bag';
    if (str_contains($n,'properti')||str_contains($n,'real estate')) return 'house';
    if (str_contains($n,'global')||str_contains($n,'international')||str_contains($n,'capital')) return 'globe';
    return 'chart-line-up';
}
function getProdHue($name) {
    $n = strtolower($name);
    if (str_contains($n,'blue chip')||str_contains($n,'lq45'))          return [210,72];
    if (str_contains($n,'dividen')||str_contains($n,'konsisten'))       return [152,68];
    if (str_contains($n,'growth')||str_contains($n,'agresif'))          return [24,78];
    if (str_contains($n,'perbankan')||str_contains($n,'bank'))          return [220,70];
    if (str_contains($n,'infrastruktur')||str_contains($n,'konstruksi'))return [258,62];
    if (str_contains($n,'energi')||str_contains($n,'pertambangan'))     return [42,80];
    if (str_contains($n,'teknologi')||str_contains($n,'digital'))       return [188,72];
    if (str_contains($n,'consumer')||str_contains($n,'goods'))          return [328,65];
    if (str_contains($n,'properti')||str_contains($n,'real'))           return [172,67];
    return [258,62];
}

$icon    = getProductIcon($order['nama_produk']);
[$h,$s]  = getProdHue($order['nama_produk']);
$prod_bg = "linear-gradient(150deg,hsl($h,{$s}%,18%) 0%,hsl($h,{$s}%,36%) 100%)";
$accent  = "hsl($h,{$s}%,42%)";
$accent_light = "hsl($h,{$s}%,96%)";

// calendar build
$start_date = strtotime($order['created_at']);
$end_date   = strtotime($order['created_at'].' +'.$total_masa_awal.' days');
$today      = strtotime(date('Y-m-d'));
$paid_dates = [];
foreach($profit_logs as $lg) $paid_dates[date('Y-m-d', strtotime($lg['created_at']))] = true;

$cal_weeks = [];
$cur = $start_date;
$week = [];
$dow = (int)date('N', $cur);
for($i=1; $i<$dow; $i++) $week[] = null;
while($cur <= $end_date) {
    $week[] = $cur;
    if(count($week)==7){ $cal_weeks[] = $week; $week=[]; }
    $cur = strtotime('+1 day', $cur);
}
if(!empty($week)){
    while(count($week)<7) $week[] = null;
    $cal_weeks[] = $week;
}

// progress circle math
$r_val  = 44; $circ = 2 * M_PI * $r_val;
$offset = $circ * (1 - $progress/100);

require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1">
<title>Detail – <?=$nama_web?></title>
<link rel="shortcut icon" href="<?=base_url('assets/images/'.$cfg['web_logo'])?>">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
  --pu:#7c3aed;--pu2:#5b21b6;--pu-bg:#f4f0ff;
  --grn:#16a34a;--grn-bg:#dcfce7;--grn2:#4ade80;
  --amb:#d97706;--amb-bg:#fffbeb;
  --rose:#e11d48;--rose-bg:#fff1f5;
  --n50:#f8f8fc;--n100:#f0f0f7;--n200:#e5e5f0;
  --n400:#aaa;--n600:#666;--n900:#111;
  --acc:<?=$accent?>;
  --acc-light:<?=$accent_light?>;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito',sans-serif;background:var(--n100);color:var(--n900);-webkit-font-smoothing:antialiased;padding-bottom:32px}
a{text-decoration:none;color:inherit}
.page{max-width:430px;margin:0 auto;min-height:100vh;background:var(--n100)}

/* ── TOPBAR ── */
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 18px;
  position:sticky;top:0;z-index:50;
  background:transparent;
}
.tb-ic{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.22);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;color:#fff;font-size:18px;text-decoration:none;flex-shrink:0}

/* ── HERO — full bleed product gradient ── */
.hero{
  margin-top:-62px;padding-top:80px;
  padding-bottom:0;
  position:relative;overflow:hidden;
  background:<?=$prod_bg?>;
}
/* deco rings */
.hero::before{content:'';position:absolute;width:280px;height:280px;border-radius:50%;border:1.5px solid rgba(255,255,255,.1);top:-80px;right:-60px;pointer-events:none}
.hero::after{content:'';position:absolute;width:150px;height:150px;border-radius:50%;border:1px solid rgba(255,255,255,.08);bottom:40px;left:-40px;pointer-events:none}

/* hero inner content */
.hero-inner{position:relative;z-index:2;padding:0 18px 0}

/* status pill */
.h-status-row{display:flex;align-items:center;gap:8px;margin-bottom:14px}
.h-status{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:900;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em}
.h-status.active{background:rgba(74,222,128,.2);color:#4ade80;border:1px solid rgba(74,222,128,.3)}
.h-status.done{background:rgba(255,255,255,.15);color:rgba(255,255,255,.7)}
.h-status-dot{width:6px;height:6px;border-radius:50%;background:#4ade80;animation:pulse 2s ease infinite}
.h-kat{font-size:10px;font-weight:800;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.06em}
.h-id-badge{font-size:9.5px;font-weight:900;color:rgba(255,255,255,.5);background:rgba(255,255,255,.1);padding:3px 8px;border-radius:6px}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* product icon + name row */
.h-prod-row{display:flex;align-items:center;gap:14px;margin-bottom:16px}
.h-icon{
  width:56px;height:56px;border-radius:16px;
  background:rgba(255,255,255,.18);
  display:flex;align-items:center;justify-content:center;
  font-size:26px;color:#fff;flex-shrink:0;
  box-shadow:0 4px 16px rgba(0,0,0,.15);
}
.h-name{font-size:20px;font-weight:900;color:#fff;line-height:1.25;letter-spacing:-.01em}
.h-subname{font-size:11px;font-weight:700;color:rgba(255,255,255,.5);margin-top:3px}

/* big profit number */
.h-profit-wrap{margin-bottom:20px}
.h-profit-lbl{font-size:9.5px;font-weight:800;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.h-profit-row{display:flex;align-items:flex-end;gap:6px}
.h-profit-val{font-size:32px;font-weight:900;color:#fff;letter-spacing:-.03em;line-height:1}
.h-profit-unit{font-size:12px;font-weight:700;color:rgba(255,255,255,.5);padding-bottom:4px}
.h-profit-total{font-size:11px;font-weight:700;color:rgba(255,255,255,.4);padding-bottom:5px;margin-left:4px}

/* progress bar in hero */
.h-prog-wrap{margin-bottom:0}
.h-prog-labels{display:flex;justify-content:space-between;font-size:10px;font-weight:800;color:rgba(255,255,255,.55);margin-bottom:6px}
.h-prog-track{height:7px;background:rgba(255,255,255,.15);border-radius:4px;overflow:hidden}
.h-prog-fill{height:100%;border-radius:4px;background:rgba(255,255,255,.75);transition:width .8s ease}
.h-prog-fill.near{background:#fbbf24}
.h-prog-fill.complete{background:var(--grn2)}

/* wave divider between hero and body */
.hero-wave{display:block;margin-top:-1px}
.hero-wave svg{display:block;width:100%}

/* ── OVERLAP CARD (white, overlaps wave) ── */
.overlap-card{
  margin:-2px 16px 0;
  background:#fff;
  border-radius:20px;
  box-shadow:0 4px 20px rgba(80,40,160,.12);
  overflow:hidden;
  position:relative;z-index:10;
}

/* 4-cell grid inside overlap card */
.oc-grid{
  display:grid;grid-template-columns:repeat(4,1fr);
  gap:1px;background:var(--n200);
}
.oc-cell{
  background:#fff;
  padding:13px 6px;
  display:flex;flex-direction:column;align-items:center;gap:3px;
  text-align:center;
}
.oc-val{font-size:12.5px;font-weight:900;color:var(--n900)}
.oc-val.g{color:var(--grn)}
.oc-val.p{color:var(--pu)}
.oc-val.a{color:var(--amb)}
.oc-val.r{color:var(--rose)}
.oc-lbl{font-size:8.5px;font-weight:700;color:var(--n400)}

/* action buttons row */
.oc-actions{
  display:flex;gap:0;
  border-top:1px solid var(--n100);
}
.oc-act-btn{
  flex:1;padding:13px 8px;border:none;cursor:pointer;
  font-size:12px;font-weight:800;color:var(--n600);
  background:#fff;font-family:'Nunito',sans-serif;
  display:flex;align-items:center;justify-content:center;gap:6px;
  border-right:1px solid var(--n100);transition:background .15s;
}
.oc-act-btn:last-child{border-right:none}
.oc-act-btn:active{background:var(--n50)}
.oc-act-btn i{font-size:15px}
.oc-act-btn.hi{color:var(--pu)}
.oc-act-btn.hi i{color:var(--pu)}

/* ── BODY SECTIONS ── */
.body-section{padding:0 16px}

/* section title */
.s-title{
  font-size:11px;font-weight:900;color:var(--n400);
  text-transform:uppercase;letter-spacing:.08em;
  padding:16px 0 8px;display:flex;align-items:center;gap:6px;
}
.s-title::after{content:'';flex:1;height:1px;background:var(--n200)}

/* ── PROGRESS SECTION — ring + bar side by side ── */
.prog-section{
  background:#fff;border-radius:18px;
  padding:16px;
  box-shadow:0 2px 12px rgba(80,40,160,.07);
  display:flex;align-items:center;gap:16px;
  margin-bottom:10px;
}
/* SVG ring */
.ring-svg-wrap{position:relative;flex-shrink:0;width:100px;height:100px}
.ring-svg-wrap svg{transform:rotate(-90deg)}
.ring-bg{fill:none;stroke:var(--n100);stroke-width:8}
.ring-fill{fill:none;stroke:var(--acc);stroke-width:8;stroke-linecap:round;transition:stroke-dashoffset .8s ease}
.ring-center{
  position:absolute;inset:0;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
}
.ring-pct{font-size:20px;font-weight:900;color:var(--n900);line-height:1}
.ring-sub{font-size:9px;font-weight:700;color:var(--n400)}
/* ring info rows */
.ring-rows{flex:1;display:flex;flex-direction:column;gap:8px}
.rr{display:flex;align-items:center;justify-content:space-between}
.rr-lbl{font-size:11px;font-weight:700;color:var(--n400);display:flex;align-items:center;gap:4px}
.rr-lbl i{font-size:13px}
.rr-val{font-size:12px;font-weight:900;color:var(--n900)}
.rr-val.g{color:var(--grn)}
.rr-val.a{color:var(--amb)}
.rr-val.p{color:var(--pu)}

/* ── EARNED CARD ── */
.earned-card{
  background:var(--grn-bg);border-radius:18px;
  padding:16px;
  margin-bottom:10px;
  box-shadow:0 2px 12px rgba(22,163,74,.1);
}
.ec-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.ec-lbl{font-size:9.5px;font-weight:800;color:rgba(22,163,74,.7);text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px}
.ec-val{font-size:22px;font-weight:900;color:var(--grn);letter-spacing:-.02em}
.ec-pct-badge{font-size:11px;font-weight:900;color:#fff;background:var(--grn);padding:4px 10px;border-radius:20px;white-space:nowrap}
.ec-track{height:8px;background:rgba(22,163,74,.15);border-radius:4px;overflow:hidden;margin-bottom:8px}
.ec-fill{height:100%;border-radius:4px;background:linear-gradient(90deg,var(--grn),#4ade80)}
.ec-foot{display:flex;justify-content:space-between;font-size:10.5px;font-weight:700;color:rgba(22,163,74,.7)}

/* ── DETAIL GRID ── */
.detail-grid{
  background:#fff;border-radius:18px;
  overflow:hidden;
  box-shadow:0 2px 12px rgba(80,40,160,.07);
  margin-bottom:10px;
}
.dg-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:11px 16px;
  border-bottom:1px solid var(--n100);
}
.dg-row:last-child{border-bottom:none}
.dg-lbl{font-size:12px;font-weight:700;color:var(--n400);display:flex;align-items:center;gap:6px}
.dg-lbl i{font-size:14px;color:var(--n400)}
.dg-val{font-size:12.5px;font-weight:900;color:var(--n900)}
.dg-val.g{color:var(--grn)}
.dg-val.p{color:var(--pu)}
.dg-val.a{color:var(--amb)}
.dg-val.r{color:var(--rose)}
.dg-val .pill{font-size:10px;font-weight:900;padding:2px 8px;border-radius:6px;background:var(--grn-bg);color:var(--grn)}
.dg-val .pill.done{background:var(--n100);color:var(--n400)}

/* ── CALENDAR ── */
.cal-wrap{
  background:#fff;border-radius:18px;
  overflow:hidden;
  box-shadow:0 2px 12px rgba(80,40,160,.07);
  margin-bottom:10px;
}
.cal-header{
  padding:14px 16px 10px;
  display:flex;align-items:center;justify-content:space-between;
  border-bottom:1px solid var(--n100);
}
.cal-month{font-size:14px;font-weight:900;color:var(--n900)}
.cal-year{font-size:12px;font-weight:700;color:var(--n400)}
.cal-legend-row{display:flex;gap:12px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--n100)}
.cl-item{display:flex;align-items:center;gap:4px;font-size:10px;font-weight:700;color:var(--n600)}
.cl-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.cal-days-header{display:grid;grid-template-columns:repeat(7,1fr);padding:8px 10px 4px;gap:2px}
.cal-dh{font-size:9px;font-weight:800;color:var(--n400);text-align:center}
.cal-grid-wrap{padding:2px 10px 12px;display:flex;flex-direction:column;gap:2px}
.cal-week{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.cal-day{
  aspect-ratio:1;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:10px;font-weight:800;color:var(--n400);
  background:transparent;
}
.cal-day.in-range{color:var(--n600)}
.cal-day.paid{background:var(--pu-bg);color:var(--pu)}
.cal-day.start{background:var(--grn);color:#fff;border-radius:10px}
.cal-day.end{background:var(--rose);color:#fff;border-radius:10px}
.cal-day.today{background:var(--amb);color:#fff;border-radius:10px}
.cal-day.future{color:var(--n200)}

/* ── TABS ── */
.tab-bar{
  display:flex;gap:0;
  background:#fff;border-radius:18px;
  overflow:hidden;
  box-shadow:0 2px 12px rgba(80,40,160,.07);
  margin-bottom:10px;
}
.tab-btn{
  flex:1;padding:12px 8px;border:none;cursor:pointer;
  font-size:12px;font-weight:800;color:var(--n400);
  background:transparent;font-family:'Nunito',sans-serif;
  display:flex;align-items:center;justify-content:center;gap:5px;
  border-bottom:2px solid transparent;
  transition:all .15s;
}
.tab-btn i{font-size:15px}
.tab-btn.on{color:var(--pu);border-bottom-color:var(--pu);background:var(--pu-bg)}
.tab-content{display:none}
.tab-content.show{display:block}

/* ── LOG LIST ── */
.log-list{display:flex;flex-direction:column;gap:0}
.log-item{
  display:flex;align-items:center;gap:12px;
  padding:12px 16px;
  border-bottom:1px solid var(--n100);
  animation:fadeUp .3s ease both;
}
.log-item:last-child{border-bottom:none}
@keyframes fadeUp{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.log-ic{
  width:36px;height:36px;border-radius:11px;
  background:var(--grn-bg);
  display:flex;align-items:center;justify-content:center;
  font-size:17px;color:var(--grn);flex-shrink:0;
}
.log-info{flex:1;min-width:0}
.log-title{font-size:12.5px;font-weight:800;color:var(--n900);margin-bottom:2px}
.log-date{font-size:10.5px;font-weight:700;color:var(--n400);display:flex;align-items:center;gap:4px}
.log-date i{font-size:12px}
.log-amount{font-size:13px;font-weight:900;color:var(--grn);flex-shrink:0}

.log-empty{text-align:center;padding:36px 20px}
.log-empty i{font-size:40px;color:var(--n200);display:block;margin-bottom:10px}
.log-empty p{font-size:13px;font-weight:800;color:var(--n400);margin-bottom:4px}
.log-empty small{font-size:11px;font-weight:600;color:var(--n400)}

/* log sum strip */
.log-sum{
  display:grid;grid-template-columns:repeat(3,1fr);
  gap:1px;background:var(--n200);
  border-radius:14px;overflow:hidden;
  margin-bottom:0;border-bottom:1px solid var(--n200);
}
.ls-cell{background:#fff;padding:12px 8px;display:flex;flex-direction:column;align-items:center;gap:2px;text-align:center}
.ls-val{font-size:12px;font-weight:900;color:var(--n900)}
.ls-val.g{color:var(--grn)}
.ls-lbl{font-size:8.5px;font-weight:700;color:var(--n400)}
</style>
</head>
<body>
<div class="page">

<!-- TOPBAR (transparent over hero) -->
<div class="topbar">
  <a href="product" class="tb-ic"><i class="ph ph-arrow-left"></i></a>
  <div style="flex:1"></div>
  <button class="tb-ic" onclick="showInfo()"><i class="ph ph-info"></i></button>
</div>

<!-- HERO — full product gradient -->
<div class="hero">
  <div class="hero-inner">

    <!-- status + kat + id -->
    <div class="h-status-row">
      <div class="h-status <?=$is_active?'active':'done'?>">
        <?php if($is_active): ?><div class="h-status-dot"></div><?php endif; ?>
        <?=$is_active?'Aktif':'Selesai'?>
      </div>
      <div class="h-kat"><?=htmlspecialchars($order['kat_nama']??'Investasi')?></div>
      <div class="h-id-badge">#<?=$order['id']?></div>
    </div>

    <!-- icon + name -->
    <div class="h-prod-row">
      <div class="h-icon"><i class="ph ph-<?=$icon?>"></i></div>
      <div>
        <div class="h-name"><?=htmlspecialchars(mb_substr($order['nama_produk'],0,26))?></div>
        <div class="h-subname">Mulai <?=date('d M Y',strtotime($order['created_at']))?> · <?=$total_masa_awal?> Hari</div>
      </div>
    </div>

    <!-- big profit/day -->
    <div class="h-profit-wrap">
      <div class="h-profit-lbl">Profit per Hari</div>
      <div class="h-profit-row">
        <div class="h-profit-val">Rp<?=number_format($order['profit_harian'],0,',','.')?></div>
        <div class="h-profit-unit">/hari</div>
        <div class="h-profit-total">Total: Rp<?=number_format($order['total_profit'],0,',','.')?></div>
      </div>
    </div>

    <!-- progress bar -->
    <div class="h-prog-wrap">
      <div class="h-prog-labels">
        <span><?=$progress?>% selesai — Hari <?=$hari_berjalan?>/<?=$total_masa_awal?></span>
        <span><?=$sisa_hari?> hari lagi</span>
      </div>
      <div class="h-prog-track">
        <div class="h-prog-fill <?=$progress>=90?'near':($progress>=100?'complete':'')?>" style="width:<?=$progress?>%"></div>
      </div>
    </div>

  </div><!-- /hero-inner -->

  <!-- wave -->
  <div class="hero-wave">
    <svg viewBox="0 0 430 36" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M0 36 L0 20 Q70 0 140 16 Q215 32 290 14 Q360 0 430 18 L430 36Z" fill="#f0f0f7"/>
    </svg>
  </div>
</div><!-- /hero -->

<!-- OVERLAP CARD — 4-stat grid + action buttons -->
<div class="overlap-card">
  <div class="oc-grid">
    <div class="oc-cell">
      <div class="oc-val p">Rp<?=number_format($order['harga'],0,',','.')?></div>
      <div class="oc-lbl">Modal</div>
    </div>
    <div class="oc-cell">
      <div class="oc-val g">+<?=$order['roi_percentage']?>%</div>
      <div class="oc-lbl">ROI</div>
    </div>
    <div class="oc-cell">
      <div class="oc-val a"><?=$days_paid?> hr</div>
      <div class="oc-lbl">Terbayar</div>
    </div>
    <div class="oc-cell">
      <div class="oc-val g">Rp<?=number_format($total_earned,0,',','.')?></div>
      <div class="oc-lbl">Diterima</div>
    </div>
  </div>

</div>

<!-- BODY -->
<div class="body-section">

  <!-- PROGRESS section -->
  <div class="s-title"><i class="ph ph-chart-pie-slice"></i> Progress</div>

  <div class="prog-section">
    <!-- ring -->
    <div class="ring-svg-wrap">
      <svg width="100" height="100" viewBox="0 0 100 100">
        <circle class="ring-bg" cx="50" cy="50" r="<?=$r_val?>"/>
        <circle class="ring-fill" cx="50" cy="50" r="<?=$r_val?>"
          stroke-dasharray="<?=round($circ,2)?>"
          stroke-dashoffset="<?=round($offset,2)?>"/>
      </svg>
      <div class="ring-center">
        <div class="ring-pct"><?=$progress?>%</div>
        <div class="ring-sub">berjalan</div>
      </div>
    </div>
    <!-- rows -->
    <div class="ring-rows">
      <div class="rr">
        <div class="rr-lbl"><i class="ph ph-calendar-blank"></i>Hari ke</div>
        <div class="rr-val p"><?=$hari_berjalan?> / <?=$total_masa_awal?></div>
      </div>
      <div class="rr">
        <div class="rr-lbl"><i class="ph ph-hourglass"></i>Tersisa</div>
        <div class="rr-val a"><?=$sisa_hari?> hari</div>
      </div>
      <div class="rr">
        <div class="rr-lbl"><i class="ph ph-coins"></i>Profit cair</div>
        <div class="rr-val g"><?=$earned_pct?>%</div>
      </div>
      <div class="rr">
        <div class="rr-lbl"><i class="ph ph-flag"></i>Berakhir</div>
        <div class="rr-val" style="font-size:11px"><?=date('d M Y',strtotime($order['created_at'].' +'.$total_masa_awal.' days'))?></div>
      </div>
    </div>
  </div>

  <!-- EARNED card -->
  <div class="earned-card">
    <div class="ec-top">
      <div>
        <div class="ec-lbl">Sudah Diterima</div>
        <div class="ec-val">Rp<?=number_format($total_earned,0,',','.')?></div>
      </div>
      <div class="ec-pct-badge"><?=$earned_pct?>% cair</div>
    </div>
    <div class="ec-track">
      <div class="ec-fill" style="width:<?=$earned_pct?>%"></div>
    </div>
    <div class="ec-foot">
      <span><?=$days_paid?> hari sudah dibayar</span>
      <span>Target: Rp<?=number_format($order['total_profit'],0,',','.')?></span>
    </div>
  </div>

  <!-- TABS -->
  <div class="s-title"><i class="ph ph-stack"></i> Detail & Riwayat</div>

  <div class="tab-bar">
    <button id="tab-detail" class="tab-btn on" onclick="switchTab('detail',this)">
      <i class="ph ph-file-text"></i> Rincian
    </button>
    <button id="tab-riwayat" class="tab-btn" onclick="switchTab('riwayat',this)">
      <i class="ph ph-list-checks"></i> Riwayat <?php if($days_paid>0): ?><span style="font-size:9px;opacity:.7">(<?=$days_paid?>)</span><?php endif; ?>
    </button>
    <button id="tab-kalender" class="tab-btn" onclick="switchTab('kalender',this)">
      <i class="ph ph-calendar-dots"></i> Kalender
    </button>
  </div>

  <!-- TAB: RINCIAN -->
  <div id="tc-detail" class="tab-content show">
    <div class="detail-grid">
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-currency-circle-dollar"></i>Modal</div>
        <div class="dg-val p">Rp<?=number_format($order['harga'],0,',','.')?></div>
      </div>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-trend-up"></i>Profit / Hari</div>
        <div class="dg-val g">Rp<?=number_format($order['profit_harian'],0,',','.')?></div>
      </div>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-star"></i>Target Profit</div>
        <div class="dg-val g">Rp<?=number_format($order['total_profit'],0,',','.')?></div>
      </div>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-percent"></i>ROI</div>
        <div class="dg-val p">+<?=$order['roi_percentage']?>%</div>
      </div>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-clock"></i>Durasi</div>
        <div class="dg-val"><?=$total_masa_awal?> Hari</div>
      </div>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-calendar-check"></i>Mulai</div>
        <div class="dg-val"><?=date('d M Y',strtotime($order['created_at']))?></div>
      </div>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-calendar-x"></i>Berakhir</div>
        <div class="dg-val"><?=date('d M Y',strtotime($order['created_at'].' +'.$total_masa_awal.' days'))?></div>
      </div>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-circle-wavy-check"></i>Status</div>
        <div class="dg-val">
          <span class="pill <?=$is_active?'':'done'?>"><?=$is_active?'● Aktif':'Selesai'?></span>
        </div>
      </div>
      <?php if($is_locked): ?>
      <div class="dg-row">
        <div class="dg-lbl"><i class="ph ph-lock-key"></i>Tipe</div>
        <div class="dg-val a">★ VIP / Locked</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: RIWAYAT -->
  <div id="tc-riwayat" class="tab-content">
    <div class="detail-grid">
      <div class="log-sum">
        <div class="ls-cell"><div class="ls-val g">Rp<?=number_format($total_earned,0,',','.')?></div><div class="ls-lbl">Total Diterima</div></div>
        <div class="ls-cell"><div class="ls-val"><?=$days_paid?></div><div class="ls-lbl">Hari Dibayar</div></div>
        <div class="ls-cell"><div class="ls-val">Rp<?=number_format($order['profit_harian'],0,',','.')?></div><div class="ls-lbl">Per Hari</div></div>
      </div>
      <?php if(empty($profit_logs)): ?>
      <div class="log-empty">
        <i class="ph ph-clock-countdown"></i>
        <p>Belum Ada Riwayat</p>
        <small>Profit harian muncul setelah 1 hari aktif</small>
      </div>
      <?php else: ?>
      <div class="log-list">
        <?php foreach($profit_logs as $idx=>$log): ?>
        <div class="log-item" style="animation-delay:<?=$idx*.04?>s">
          <div class="log-ic"><i class="ph ph-coins"></i></div>
          <div class="log-info">
            <div class="log-title"><?=$profit_label?></div>
            <div class="log-date"><i class="ph ph-calendar"></i><?=date('d M Y · H:i',strtotime($log['created_at']))?></div>
          </div>
          <div class="log-amount">+Rp<?=number_format($log['amount'],0,',','.')?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: KALENDER -->
  <div id="tc-kalender" class="tab-content">
    <div class="cal-wrap">
      <div class="cal-header">
        <div class="cal-month"><?=date('F',$start_date)?> – <?=date('F',$end_date)?></div>
        <div class="cal-year"><?=date('Y',$start_date)?></div>
      </div>
      <div class="cal-legend-row">
        <div class="cl-item"><div class="cl-dot" style="background:var(--pu)"></div>Dibayar</div>
        <div class="cl-item"><div class="cl-dot" style="background:var(--amb)"></div>Hari ini</div>
        <div class="cl-item"><div class="cl-dot" style="background:var(--grn)"></div>Mulai</div>
        <div class="cl-item"><div class="cl-dot" style="background:var(--rose)"></div>Berakhir</div>
      </div>
      <div class="cal-days-header">
        <?php foreach(['S','S','R','K','J','S','M'] as $d): ?>
        <div class="cal-dh"><?=$d?></div>
        <?php endforeach; ?>
      </div>
      <div class="cal-grid-wrap">
        <?php foreach($cal_weeks as $week): ?>
        <div class="cal-week">
          <?php foreach($week as $ts):
            if($ts===null){echo '<div class="cal-day"></div>';continue;}
            $ds = date('Y-m-d',$ts);
            $dn = (int)date('j',$ts);
            $cls = 'in-range';
            if($ts==$start_date)          $cls='start';
            elseif($ts==$end_date)        $cls='end';
            elseif(isset($paid_dates[$ds]))$cls='paid';
            elseif($ts==$today)           $cls='today';
            elseif($ts>$today)            $cls='future';
          ?>
          <div class="cal-day <?=$cls?>"><?=$dn?></div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div><!-- /body-section -->
</div><!-- /page -->

<script>
function switchTab(id, btn){
  document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('show'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('on'));
  document.getElementById('tc-'+id).classList.add('show');
  btn.classList.add('on');
}

function showInfo(){
  Swal.fire({
    title:'<span style="font-size:15px;font-weight:900;font-family:Nunito,sans-serif">Ringkasan Investasi</span>',
    html:`<div style="text-align:left;font-size:12px;line-height:1.7;font-family:Nunito,sans-serif">
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f2f5"><span style="color:#aaa;font-weight:700">Produk</span><span style="font-weight:900"><?=addslashes(htmlspecialchars(mb_substr($order['nama_produk'],0,22)))?></span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f2f5"><span style="color:#aaa;font-weight:700">Modal</span><span style="font-weight:900">Rp<?=number_format($order['harga'],0,',','.')?></span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f2f5"><span style="color:#aaa;font-weight:700">Profit/Hari</span><span style="font-weight:900;color:#16a34a">Rp<?=number_format($order['profit_harian'],0,',','.')?></span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f2f5"><span style="color:#aaa;font-weight:700">Target Profit</span><span style="font-weight:900;color:#16a34a">Rp<?=number_format($order['total_profit'],0,',','.')?></span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f2f5"><span style="color:#aaa;font-weight:700">Sudah Diterima</span><span style="font-weight:900;color:#7c3aed">Rp<?=number_format($total_earned,0,',','.')?></span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f2f5"><span style="color:#aaa;font-weight:700">ROI</span><span style="font-weight:900;color:#7c3aed">+<?=$order['roi_percentage']?>%</span></div>
      <div style="display:flex;justify-content:space-between;padding:8px 0"><span style="color:#aaa;font-weight:700">Sisa Hari</span><span style="font-weight:900"><?=$sisa_hari?> Hari</span></div>
    </div>`,
    confirmButtonText:'Tutup',
    confirmButtonColor:'#7c3aed',
    width:'340px',
  });
}
</script>
</body>
</html>