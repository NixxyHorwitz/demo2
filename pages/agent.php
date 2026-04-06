<?php
declare(strict_types=1);
require '../mainconfig.php';
require '../lib/check_session.php';
$page_type = 'Referral';
$page_name = 'Referral';

$settingsQ = mysqli_query($db, "SELECT * FROM settings WHERE id='1' LIMIT 1");
$settings  = ($settingsQ && $settingsQ !== false) ? mysqli_fetch_assoc($settingsQ) : [];

$level = max(1, min(3, (int)($_GET['level'] ?? 1)));

$uplink_level   = strtolower($login['uplink_level'] ?? 'biasa');
$lvl2_on = ($settings['referral_lvl2_status'] ?? 'off') === 'on';
$lvl3_on = ($settings['referral_lvl3_status'] ?? 'off') === 'on';

/* Total stats */
$q = mysqli_query($db, "SELECT COUNT(*) AS total FROM users WHERE uplink='{$login['id']}'");
$total_l1 = (int)(mysqli_fetch_assoc($q)['total'] ?? 0);

$q = mysqli_query($db, "SELECT COUNT(u2.id) AS total FROM users u1 INNER JOIN users u2 ON u2.uplink = u1.id WHERE u1.uplink = '{$login['id']}'");
$total_l2 = (int)(mysqli_fetch_assoc($q)['total'] ?? 0);

$q = mysqli_query($db, "SELECT COUNT(u3.id) AS total FROM users u1 INNER JOIN users u2 ON u2.uplink = u1.id INNER JOIN users u3 ON u3.uplink = u2.id WHERE u1.uplink = '{$login['id']}'");
$total_l3 = (int)(mysqli_fetch_assoc($q)['total'] ?? 0);

$total_member = $total_l1 + ($lvl2_on ? $total_l2 : 0) + ($lvl3_on ? $total_l3 : 0);

$uid_safe   = mysqli_real_escape_string($db, (string)$login['id']);
$q_stat = mysqli_query($db, "SELECT COALESCE(SUM(amount),0) AS total_komisi FROM refferals WHERE user_id='$uid_safe'");
$stat   = ($q_stat && $q_stat !== false) ? mysqli_fetch_assoc($q_stat) : [];
$total_komisi = (float)($stat['total_komisi'] ?? 0);

$referral_code = $login['id'];
$referral_link = base_url('auth/register?reff=' . $login['id']);

/* Fetch members for this level (logic from team.php) */
$members = [];
if ($level === 1) {
    $q = mysqli_query($db, "SELECT u.id, u.username, u.phone, u.uplink_level, u.status, u.created_at, COALESCE((SELECT SUM(r.amount) FROM refferals r WHERE r.user_id='$uid_safe' AND r.from_id=u.id), 0) AS komisi_dari_dia FROM users u WHERE u.uplink = '$uid_safe' ORDER BY u.created_at DESC");
} elseif ($level === 2 && $lvl2_on) {
    $q = mysqli_query($db, "SELECT u2.id, u2.username, u2.phone, u2.uplink_level, u2.status, u2.created_at, COALESCE((SELECT SUM(r.amount) FROM refferals r WHERE r.user_id='$uid_safe' AND r.from_id=u2.id), 0) AS komisi_dari_dia FROM users u1 INNER JOIN users u2 ON u2.uplink = u1.id WHERE u1.uplink = '$uid_safe' ORDER BY u2.created_at DESC");
} elseif ($level === 3 && $lvl3_on) {
    $q = mysqli_query($db, "SELECT u3.id, u3.username, u3.phone, u3.uplink_level, u3.status, u3.created_at, COALESCE((SELECT SUM(r.amount) FROM refferals r WHERE r.user_id='$uid_safe' AND r.from_id=u3.id), 0) AS komisi_dari_dia FROM users u1 INNER JOIN users u2 ON u2.uplink = u1.id INNER JOIN users u3 ON u3.uplink = u2.id WHERE u1.uplink = '$uid_safe' ORDER BY u3.created_at DESC");
}
if (isset($q) && $q && $q !== false) {
    while ($row = mysqli_fetch_assoc($q)) $members[] = $row;
}

require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Team Kerja</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Poppins', sans-serif; background: #012b26; color: #fff; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
    .app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; background: #012b26; }

    /* HEADER */
    .r-header {
        background: linear-gradient(135deg, #023e35 0%, #01312b 100%);
        padding: 30px 20px 100px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px;
        position: relative; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1;
    }
    .rh-title { font-size: 20px; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 6px; }
    .rh-sub { font-size: 11px; color: rgba(255,255,255,0.7); margin-bottom: 24px; }

    /* CODE CARD */
    .code-card { background: transparent; border: 1px solid rgba(250,204,21,0.25); border-radius: 16px; padding: 16px; }
    .cc-top { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .cc-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #facc15; flex-shrink: 0; border: 1px solid rgba(250,204,21,0.15);}
    .cc-info { flex: 1; }
    .cc-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;}
    .cc-code { font-size: 18px; font-weight: 800; color: #facc15; line-height: 1; letter-spacing: 1px;}

    .cc-btns { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .cc-btn { background: rgba(255,255,255,0.05); padding: 10px; border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; border: 1px solid rgba(255,255,255,0.1); color: #fff; font-size: 10px; font-weight: 600; cursor: pointer; transition: 0.2s;}
    .cc-btn:active { transform: scale(0.95); }
    .cc-btn i { font-size: 14px; }
    .cc-btn.w { background: #facc15; color: #012b26; border-color: #facc15; }

    /* TOP STATS */
    .r-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 0 20px; margin-top: -65px; position: relative; z-index: 2; margin-bottom: 20px;}
    .rs-card { background: #023e35; border-radius: 16px; padding: 16px; border: 1px solid #035246; box-shadow: 0 6px 15px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
    .rs-icon { width: 34px; height: 34px; border-radius: 10px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; font-size: 15px; color: #facc15; margin-bottom: 12px; }
    .rs-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;}
    .rs-val { font-size: 18px; font-weight: 800; color: #fff; line-height: 1; }
    .rs-val.y { color: #facc15; }

    /* LEVEL TABS */
    .l-tabs { display: flex; background: #023e35; border: 1px solid #035246; border-radius: 12px; margin: 0 20px 20px; padding: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .lt-btn { flex: 1; text-align: center; padding: 10px 0; font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.6); border-radius: 8px; cursor: pointer; transition: 0.2s; text-decoration: none; }
    .lt-btn.on { background: #facc15; color: #012b26; }

    /* LEVEL DETAILS CARD */
    .l-det { background: #023e35; border-radius: 16px; padding: 20px; margin: 0 20px 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .ld-line { width: 24px; height: 3px; background: #facc15; border-radius: 2px; margin-bottom: 12px; }
    .ld-title { font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; }
    .ld-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .ldg-box { background: #012b26; border-radius: 14px; padding: 14px 6px; text-align: center; border: 1px solid rgba(250,204,21,0.05); }
    .ldg-icon { color: #facc15; font-size: 16px; margin-bottom: 8px; }
    .ldg-lbl { font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 3px; }
    .ldg-val { font-size: 14px; font-weight: 800; color: #fff; }
    .ldg-val.y { color: #facc15; }

    /* COM/REF CARDS */
    .c-sec { background: #023e35; border-radius: 16px; padding: 20px; margin: 0 20px 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .cs-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px dashed rgba(255,255,255,0.1); }
    .cs-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #facc15; }
    .cs-title { font-size: 13px; font-weight: 800; color: #fff; }

    .cs-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 25px 0 10px; }
    .cse-icon { width: 50px; height: 50px; border-radius: 14px; background: rgba(255,255,255,0.04); display: flex; align-items: center; justify-content: center; font-size: 22px; color: rgba(255,255,255,0.2); margin-bottom: 14px; }
    .cse-txt { font-size: 11px; font-weight: 500; color: rgba(255,255,255,0.5); }

    /* LIST REFS */
    .ref-item { display: flex; align-items: center; gap: 12px; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
    .ref-item:last-child { border-bottom: none; padding-bottom: 0; }
    .ri-ava { width: 38px; height: 38px; border-radius: 50%; background: #012b26; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; color: #facc15; border: 1px solid rgba(250,204,21,0.2); flex-shrink: 0;}
    .ri-info { flex: 1; }
    .ri-name { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 2px; }
    .ri-date { font-size: 9px; color: rgba(255,255,255,0.5); }
    .ri-komisi { text-align: right; }
    .ri-k-val { font-size: 13px; font-weight: 800; color: #facc15; margin-bottom: 2px;}
    .ri-k-lbl { font-size: 8px; font-weight: 600; color: rgba(255,255,255,0.6); text-transform: uppercase; }

    /* Toast */
    .toast {
      position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%) translateY(20px);
      font-size: 11px; font-weight: 700; padding: 12px 24px; border-radius: 20px;
      opacity: 0; transition: all .25s; z-index: 99999;
      background: #facc15; color: #012b26; box-shadow: 0 4px 15px rgba(0,0,0,.3);
      pointer-events: none; text-align: center;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
</head>
<body>
<div class="app">

    <?php
        $level_komisi = 0;
        foreach($members as $m) $level_komisi += (float)$m['komisi_dari_dia'];
        $rate_str = ($level === 1) ? '30%' : (($level === 2) ? '10%' : '5%'); 
    ?>

    <!-- HEADER CURVED -->
    <div class="r-header">
        <div class="rh-title">Program referral</div>
        <div class="rh-sub">Ajak teman - komisi hingga 30%</div>

        <div class="code-card">
            <div class="cc-top">
                <div class="cc-icon"><i class="fa-solid fa-gift"></i></div>
                <div class="cc-info">
                    <div class="cc-lbl">KODE ANDA</div>
                    <div class="cc-code"><?= htmlspecialchars($referral_code) ?></div>
                </div>
            </div>
            <div class="cc-btns">
                <div class="cc-btn" onclick="copyValue('<?= htmlspecialchars($referral_code) ?>')">
                    <i class="fa-regular fa-copy"></i> Kode
                </div>
                <div class="cc-btn" onclick="copyValue('<?= htmlspecialchars($referral_link) ?>')">
                    <i class="fa-solid fa-share-nodes"></i> Link
                </div>
                <div class="cc-btn w" onclick="copyValue('<?= htmlspecialchars($referral_link) ?>')">
                    <i class="fa-solid fa-arrow-up-right-dots"></i> Bagikan
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN STATS -->
    <div class="r-stats">
        <div class="rs-card">
            <div class="rs-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div class="rs-lbl">TOTAL REFERRAL</div>
            <div class="rs-val"><?= $total_member ?></div>
        </div>
        <div class="rs-card">
            <div class="rs-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="rs-lbl">TOTAL KOMISI</div>
            <div class="rs-val y">Rp <?= number_format($total_komisi, 0, ',', '.') ?></div>
        </div>
    </div>

    <!-- TABS LEVEL -->
    <div class="l-tabs">
        <a href="?level=1" class="lt-btn <?= $level===1?'on':'' ?>">L1</a>
        <?php if ($lvl2_on): ?><a href="?level=2" class="lt-btn <?= $level===2?'on':'' ?>">L2</a><?php endif; ?>
        <?php if ($lvl3_on): ?><a href="?level=3" class="lt-btn <?= $level===3?'on':'' ?>">L3</a><?php endif; ?>
    </div>

    <!-- LEVEL DETAIL CARD -->
    <div class="l-det">
        <div class="ld-line"></div>
        <div class="ld-title">LEVEL <?= $level ?></div>
        <div class="ld-grid">
            <div class="ldg-box">
                <div class="ldg-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div class="ldg-lbl">Rate</div>
                <div class="ldg-val"><?= $rate_str ?></div>
            </div>
            <div class="ldg-box">
                <div class="ldg-icon"><i class="fa-solid fa-users"></i></div>
                <div class="ldg-lbl">Orang</div>
                <div class="ldg-val"><?= count($members) ?></div>
            </div>
            <div class="ldg-box">
                <div class="ldg-icon"><i class="fa-solid fa-coins"></i></div>
                <div class="ldg-lbl">Komisi</div>
                <div class="ldg-val y">Rp <?= number_format($level_komisi, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- REFERRAL LIST -->
    <div class="c-sec">
        <div class="cs-head">
            <div class="cs-icon"><i class="fa-solid fa-user-group"></i></div>
            <div class="cs-title">Referral level <?= $level ?></div>
        </div>
        
        <?php if (empty($members)): ?>
            <div class="cs-empty">
                <div class="cse-icon"><i class="fa-solid fa-user-group"></i></div>
                <div class="cse-txt">Belum ada referral di level ini</div>
            </div>
        <?php else: ?>
            <div class="ref-list">
                <?php foreach ($members as $mi => $m): 
                    $rawPhone = preg_replace('/[^0-9]/', '', $m['phone'] ?? '');
                    $ini = strtoupper(substr($rawPhone, -3, 2) ?: 'M' . ($mi+1));
                    $displayName = $m['username'] ?: ($m['phone'] ? substr($m['phone'],0,6).'...' : 'Member #'.($mi+1));
                ?>
                <div class="ref-item">
                    <div class="ri-ava"><?= htmlspecialchars($ini) ?></div>
                    <div class="ri-info">
                        <div class="ri-name"><?= htmlspecialchars($displayName) ?></div>
                        <div class="ri-date"><?= date('d M Y', strtotime($m['created_at'])) ?> &bullet; <?= ($m['status']??'')==='Active' ? 'Aktif' : 'Off' ?></div>
                    </div>
                    <div class="ri-komisi">
                        <div class="ri-k-val">+Rp <?= number_format((float)$m['komisi_dari_dia'], 0, ',', '.') ?></div>
                        <div class="ri-k-lbl">Komisi</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    
</div>

<div class="toast" id="toast"></div>

<script>
function copyValue(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => showToast("Disalin ke papan klip!"));
    }
}
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => { t.classList.remove('show'); }, 2500);
}
</script>

<?php require '../lib/footer_user.php'; ?>
</body>
</html>