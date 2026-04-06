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

$user_id = $check_user['rows']['id'];
if (!function_exists('protect')) {
    function protect($s) { return is_scalar($s) ? trim((string)$s) : ''; }
}

$sq            = mysqli_query($db, "SELECT * FROM settings LIMIT 1");
$settings      = $sq ? mysqli_fetch_assoc($sq) : [];
$site_name     = $settings['title'] ?? 'Platform';
$bonus_checkin = (!empty($settings['bonus_checkin']) && (int)$settings['bonus_checkin'] > 0)
                 ? (int)$settings['bonus_checkin'] : 1000;

$today   = date('Y-m-d');
$cq      = mysqli_query($db, "SELECT * FROM daily_checkin WHERE user_id='$user_id' AND tanggal='$today'");
$already = ($cq && mysqli_num_rows($cq) > 0);

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_checkin'])) {
    if (!$already) {
        mysqli_query($db, "UPDATE users SET saldo = saldo + $bonus_checkin WHERE id='$user_id'");
        mysqli_query($db, "INSERT INTO daily_checkin (user_id, amount, tanggal) VALUES ('$user_id', '$bonus_checkin', '$today')");
        $_SESSION['ci_flash'] = ['type'=>'success','amount'=>$bonus_checkin];
    }
    header("Location: " . base_url('pages/checkin')); exit;
}

$show_toast = false; $toast_amount = 0;
if (!empty($_SESSION['ci_flash'])) {
    $show_toast   = ($_SESSION['ci_flash']['type'] === 'success');
    $toast_amount = (int)$_SESSION['ci_flash']['amount'];
    unset($_SESSION['ci_flash']);
    $already = true;
}

$tq          = mysqli_query($db, "SELECT IFNULL(SUM(amount),0) as t FROM daily_checkin WHERE user_id='$user_id'");
$total_bonus = (float)(mysqli_fetch_assoc($tq)['t'] ?? 0);

$tcount_q    = mysqli_query($db, "SELECT COUNT(*) as c FROM daily_checkin WHERE user_id='$user_id'");
$total_count = (int)(mysqli_fetch_assoc($tcount_q)['c'] ?? 0);

// Week streak
$week_start  = date('Y-m-d', strtotime('monday this week'));
$week_end    = date('Y-m-d', strtotime('sunday this week'));
$wq          = mysqli_query($db, "SELECT tanggal FROM daily_checkin WHERE user_id='$user_id' AND tanggal BETWEEN '$week_start' AND '$week_end'");
$week_done   = [];
if ($wq) while ($r = mysqli_fetch_assoc($wq)) $week_done[] = $r['tanggal'];
$week_count  = count($week_done);
$day_of_week = (int)date('N');

// History
$rq = mysqli_query($db, "SELECT * FROM daily_checkin WHERE user_id='$user_id' ORDER BY tanggal DESC LIMIT 20");
$history = [];
if ($rq) while ($r = mysqli_fetch_assoc($rq)) $history[] = $r;

$day_short = ['S','S','R','K','J','S','M'];
?>
<?php require '../lib/header_user.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Bonus Harian — <?= htmlspecialchars($site_name) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: #111; color: #fff; font-family: 'Poppins', sans-serif;
    -webkit-font-smoothing: antialiased;
}
.app { max-width: 480px; margin: 0 auto; min-height: 100vh; background: #0A0A0A; position: relative; padding-bottom: 90px; overflow-x: hidden;}

/* ====== HEADER ====== */
.top-header { padding: 25px 20px; display: flex; align-items: center; position: relative; z-index: 10; }
.th-back { 
    width: 38px; height: 38px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
    border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none;
}
.th-title { flex: 1; text-align: center; font-size: 16px; font-weight: 800; color: #fff; padding-right: 38px;}

/* ====== STATS CHIP ====== */
.stats-chip-wrap { display: flex; justify-content: center; margin-top: 10px; margin-bottom: 40px; position: relative; z-index: 10; }
.stats-chip {
    background: rgba(197, 147, 39, 0.1); border: 1px solid rgba(197, 147, 39, 0.3);
    padding: 8px 20px; border-radius: 30px; display: inline-flex; align-items: center; gap: 15px;
}
.sc-item { display: flex; flex-direction: column; align-items: center; }
.sc-val { font-size: 14px; font-weight: 800; color: #F5D061; line-height: 1; margin-bottom: 2px;}
.sc-lbl { font-size: 8px; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1px;}
.sc-div { width: 1px; height: 20px; background: rgba(197, 147, 39, 0.3); }

/* ====== HERO BUTTON AREA ====== */
.hero-tap-area { position: relative; display: flex; justify-content: center; align-items: center; height: 240px; margin-bottom: 50px; }
.big-circle {
    width: 180px; height: 180px; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    position: relative; z-index: 2; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.big-circle.active-btn {
    background: linear-gradient(135deg, #18181B 0%, #000000 100%);
    box-shadow: inset 0 0 0 2px #F5D061, 0 15px 35px rgba(197, 147, 39, 0.3), 0 0 60px rgba(197, 147, 39, 0.2);
    cursor: pointer;
    animation: pulseGlow 2s infinite;
}
@keyframes pulseGlow {
    0% { box-shadow: inset 0 0 0 2px #F5D061, 0 0 0 rgba(197, 147, 39, 0.6); }
    70% { box-shadow: inset 0 0 0 2px #F5D061, 0 0 40px rgba(197, 147, 39, 0); }
    100% { box-shadow: inset 0 0 0 2px #F5D061, 0 0 0 rgba(197, 147, 39, 0); }
}

.big-circle.claimed-btn {
    background: rgba(255,255,255,0.02);
    border: 2px dashed rgba(255,255,255,0.1);
    cursor: default;
}

.bc-icon { font-size: 50px; margin-bottom: 10px; }
.active-btn .bc-icon {
    background: linear-gradient(135deg, #F5D061 0%, #C59327 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.claimed-btn .bc-icon { color: rgba(255,255,255,0.1); }

.bc-text { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; }
.active-btn .bc-text { color: #fff; }
.claimed-btn .bc-text { color: rgba(255,255,255,0.3); }

.form-claim { position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 10; opacity: 0; cursor: pointer; }

/* Background ring decorations */
.ring-1 { position: absolute; width: 260px; height: 260px; border-radius: 50%; border: 1px solid rgba(197, 147, 39, 0.1); z-index: 1; }
.ring-2 { position: absolute; width: 340px; height: 340px; border-radius: 50%; border: 1px dashed rgba(197, 147, 39, 0.05); z-index: 0; }

/* ====== TIMELINE TRACKER ====== */
.tracker-box { margin: 0 20px 40px; padding: 25px 20px; background: rgba(255,255,255,0.02); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); }
.tb-head { text-align: center; margin-bottom: 25px; }
.tb-head h3 { font-size: 13px; font-weight: 800; color: #fff; margin-bottom: 4px;}
.tb-head p { font-size: 9.5px; color: rgba(255,255,255,0.4); }

.path-wrapper { position: relative; height: 40px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }

.path-line-bg { position: absolute; top: 19px; left: 10px; right: 10px; height: 2px; background: rgba(255,255,255,0.1); z-index: 1; }
<?php 
// calculate line fill percentage
$last_done_day = 0;
for ($dd = 1; $dd <= 7; $dd++) {
    $cur_d = date('Y-m-d', strtotime($week_start . ' +' . ($dd-1) . ' days'));
    if (in_array($cur_d, $week_done)) $last_done_day = $dd;
}
$fill_pct = 0;
if ($last_done_day > 1) {
    if ($last_done_day == 7) $fill_pct = 100;
    else $fill_pct = (($last_done_day - 1) / 6) * 100;
}
?>
.path-line-fill { position: absolute; top: 19px; left: 10px; height: 2px; background: #F5D061; z-index: 2; width: <?= $fill_pct ?>%; transition: 1s ease-in-out; box-shadow: 0 0 8px rgba(245, 208, 97, 0.8);}

.step-node { 
    width: 20px; height: 20px; border-radius: 50%; background: #111; border: 2px solid rgba(255,255,255,0.1);
    position: relative; z-index: 3; display: flex; align-items: center; justify-content: center;
}
.step-node.done { background: #F5D061; border-color: #F5D061; box-shadow: 0 0 10px rgba(245, 208, 97, 0.5); }
.step-node.today { border-color: #F5D061; background: #111; }
.step-node.today::after { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #F5D061; }
.step-node i { font-size: 9px; color: #111; }

.path-labels { display: flex; justify-content: space-between; padding: 0 2px;}
.p-lbl { width: 20px; text-align: center; font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.3); }
.p-lbl.active { color: #F5D061; }

/* ====== HISTORY BOTTOM ====== */
.history-title { padding: 0 20px; font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-bottom: 15px; }
.h-scroll { padding: 0 20px; display: flex; flex-direction: column; gap: 10px; }
.h-card {
    background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.03); border-radius: 12px; padding: 15px;
    display: flex; justify-content: space-between; align-items: center;
}
.hc-left { display: flex; flex-direction: column; gap: 4px; }
.hc-left span { font-size: 13px; font-weight: 700; color: #fff; }
.hc-left small { font-size: 9px; color: rgba(255,255,255,0.3); }
.hc-right { font-size: 14px; font-weight: 800; color: #10B981; }

/* ====== TOAST ====== */
.ci-toast {
    position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
    background: #111; border-radius: 14px; padding: 15px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(16, 185, 129, 0.3); z-index: 9999; transition: 0.4s; width: 90%; max-width: 320px;
    color: #fff;
}
.cit-title { font-size: 13px; font-weight: 800; color: #fff; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;}
.cit-title i { color: #10B981; }
.cit-desc { font-size: 11px; color: rgba(255,255,255,0.6); }
.cit-desc span { color: #10B981; font-weight: 700; }
</style>
</head>
<body>
<div class="app">

  <?php if ($show_toast): ?>
  <div class="ci-toast" id="ciToast">
      <div class="cit-title"><i class="fa-solid fa-circle-check"></i> Klaim Berhasil</div>
      <div class="cit-desc">Bonus harian <span>+Rp <?= number_format($toast_amount,0,',','.') ?></span> ditambahkan ke saldo utama.</div>
  </div>
  <script>
    setTimeout(function(){
      var t = document.getElementById('ciToast');
      if(t){ t.style.opacity = '0'; t.style.transform = 'translate(-50%, -20px)'; setTimeout(()=>t.remove(), 400); }
    }, 3500);
  </script>
  <?php endif; ?>

  <!-- HEADER -->
  <div class="top-header">
      <a href="<?= base_url('') ?>" class="th-back"><i class="fa-solid fa-chevron-left"></i></a>
      <div class="th-title">Daily Rewards</div>
  </div>

  <!-- STATS -->
  <div class="stats-chip-wrap">
      <div class="stats-chip">
          <div class="sc-item">
              <div class="sc-val">Rp <?= number_format($total_bonus,0,',','.') ?></div>
              <div class="sc-lbl">Total Diperoleh</div>
          </div>
          <div class="sc-div"></div>
          <div class="sc-item">
              <div class="sc-val"><?=$total_count?> Hari</div>
              <div class="sc-lbl">Level Streak</div>
          </div>
      </div>
  </div>

  <!-- BIG TAP AREA -->
  <div class="hero-tap-area">
      <div class="ring-2"></div>
      <div class="ring-1"></div>
      
      <?php if ($already): ?>
      <!-- CLAIMED -->
      <div class="big-circle claimed-btn">
          <i class="fa-solid fa-gift bc-icon"></i>
          <span class="bc-text">Telah Diambil</span>
      </div>
      <?php else: ?>
      <!-- ACTIVE TO CLAIM -->
      <div class="big-circle active-btn">
          <i class="fa-solid fa-fingerprint bc-icon" style="background: linear-gradient(135deg, #F5D061 0%, #C59327 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
          <span class="bc-text">Tap to Claim</span>
          
          <form method="POST">
            <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <?php endif; ?>
            <input type="hidden" name="do_checkin" value="1">
            <button type="submit" class="form-claim"></button>
          </form>
      </div>
      <?php endif; ?>
  </div>

  <!-- TIMELINE PROGRESS -->
  <div class="tracker-box">
      <div class="tb-head">
          <h3>Progress Minggu Ini</h3>
          <p>Kumpulkan terus agar tidak putus rantai</p>
      </div>
      
      <div class="path-wrapper">
          <div class="path-line-bg"></div>
          <div class="path-line-fill"></div>
          
          <?php for ($d = 1; $d <= 7; $d++):
            $dd   = date('Y-m-d', strtotime($week_start . ' +' . ($d-1) . ' days'));
            $isT  = ($dd === $today);
            $isDo = in_array($dd, $week_done);
            $cls  = '';
            if ($isDo) $cls = 'done';
            elseif ($isT) $cls = 'today';
          ?>
          <div class="step-node <?= $cls ?>">
              <?php if($isDo): ?><i class="fa-solid fa-check"></i><?php endif; ?>
          </div>
          <?php endfor; ?>
      </div>
      <div class="path-labels">
          <?php for ($d = 1; $d <= 7; $d++):
            $dd   = date('Y-m-d', strtotime($week_start . ' +' . ($d-1) . ' days'));
            $isDo = in_array($dd, $week_done);
            $isT  = ($dd === $today);
            $active = ($isDo || $isT) ? 'active' : '';
          ?>
          <div class="p-lbl <?=$active?>"><?= $day_short[$d-1] ?></div>
          <?php endfor; ?>
      </div>
  </div>

  <!-- HISTORY -->
  <div class="history-title">Riwayat Terakhir</div>
  <div class="h-scroll">
    <?php if (!empty($history)): ?>
      <?php foreach ($history as $idx => $h):
        if ($idx >= 5) break; 
        $h_date = date('d M Y', strtotime($h['tanggal']));
      ?>
      <div class="h-card">
          <div class="hc-left">
              <span>Bonus Reward</span>
              <small><i class="fa-solid fa-calendar-check" style="margin-right:4px;"></i> <?= $h_date ?></small>
          </div>
          <div class="hc-right">+Rp <?= number_format($h['amount'],0,',','.') ?></div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center; padding: 20px; color:rgba(255,255,255,0.3); font-size:11px;">Belum ada history</div>
    <?php endif; ?>
  </div>

</div>

<?php require '../lib/footer_user.php'; ?>
</body>
</html>