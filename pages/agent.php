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
    body { font-family: 'Poppins', sans-serif; background: #c59327; color: #111; -webkit-font-smoothing: antialiased; padding-bottom: 90px; }
    .app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; background: linear-gradient(135deg, #CF9C2B 0%, #E6BA4A 40%, #CF9C2B 100%); }

    /* ====== HEADER ====== */
    .p-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px 5px; }
    .p-brand { font-size: 15px; font-weight: 800; color: #111; letter-spacing: 0px; }

    /* ====== USER INFO ====== */
    .p-user { padding: 10px 20px; display: flex; align-items: center; gap: 12px; margin-bottom: 5px; }
    .pu-ava { 
        width: 60px; height: 60px; border-radius: 50%; padding: 3px; border: 2px solid #111; 
        background: #111; position: relative; display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 22px; font-weight: 800; color: #F5D061;
    }
    .pu-info { flex: 1; }
    .pu-name { font-size: 15px; font-weight: 800; color: #111; display: flex; align-items: center; gap: 8px; margin-bottom: 2px; }
    .pu-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 11px; color: rgba(0,0,0,0.6); font-weight: 600; }
    .pu-meta div { display: flex; align-items: center; gap: 4px; }
    .pu-meta svg { width: 12px; stroke: #111; fill: none; stroke-width: 2; }

    /* ====== STATS BOX ====== */
    .p-stats { margin: 10px 20px 20px; display: flex; padding: 10px 0; border-top: 1px dashed rgba(0,0,0,0.06); border-bottom: 1px dashed rgba(0,0,0,0.06); }
    .ps-col { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
    .ps-col:not(:last-child)::after { content: ''; position: absolute; right: 0; top: 15%; height: 70%; width: 1px; background: rgba(0,0,0,0.08); }
    .ps-icon { width: 24px; height: 24px; background: rgba(0,0,0,0.08); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #111; margin-bottom: 6px; }
    .ps-icon svg { width: 12px; stroke: currentColor; fill: none; stroke-width: 2.5; }
    .ps-lbl { font-size: 8.5px; font-weight: 700; color: rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; text-align: center;}
    .ps-val { font-size: 13px; font-weight: 800; color: #111; }

    /* ====== TABS ====== */
    .p-tabs { display: flex; gap: 8px; margin: 0 20px 15px; }
    .p-tab { flex: 1; text-align: center; font-size: 10px; font-weight: 800; color: rgba(0,0,0,0.6); background: rgba(0,0,0,0.04); padding: 8px; border-radius: 10px; text-decoration: none; border: 1px solid rgba(0,0,0,0.06); transition: 0.2s;}
    .p-tab.active { background: #111; color: #F5D061; border-color: #111; }

    /* ====== MENU LIST ====== */
    .p-wrap { margin-bottom: 15px; padding: 0 20px; }
    .pm-title { font-size: 10px; font-weight: 800; color: #111; border-left: 3px solid #111; padding-left: 8px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;}
    .pm-list { background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.06); border-radius: 12px; overflow: hidden; margin-bottom: 30px;}
    .pm-item { display: flex; align-items: center; padding: 12px 14px; border-bottom: 1px solid rgba(0,0,0,0.04); cursor: pointer; transition: 0.2s; }
    .pm-item:active { background: rgba(0,0,0,0.08); }
    .pm-item:last-child { border-bottom: none; }
    .pmi-icon { width: 28px; height: 28px; background: rgba(0,0,0,0.06); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #111; margin-right: 12px; flex-shrink: 0; }
    .pmi-icon svg { width: 14px; stroke: currentColor; fill: none; stroke-width: 2; }
    .pmi-text { flex: 1; min-width:0; }
    .pmi-title { font-size: 11.5px; font-weight: 700; color: #111; margin-bottom: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pmi-desc { font-size: 9px; font-weight: 600; color: rgba(0,0,0,0.45); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
    .pmi-arrow svg { width: 14px; stroke: rgba(0,0,0,0.3); fill: none; stroke-width: 2; margin-left: 5px; }

    /* Toast */
    .toast {
      position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%) translateY(20px);
      font-size: 11px; font-weight: 700; padding: 10px 20px; border-radius: 20px;
      opacity: 0; transition: all .25s; z-index: 99999;
      background: #111; color: #F5D061; box-shadow: 0 4px 12px rgba(0,0,0,.15);
      pointer-events: none; text-align: center;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
</head>
<body>
<div class="app">

    <div class="p-header">
        <div class="p-brand">Team Kerja</div>
    </div>

    <!-- USER INFO (Team Summary) -->
    <div class="p-user">
        <div class="pu-ava">
            <svg viewBox="0 0 24 24" style="width:28px;fill:currentColor;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </div>
        <div class="pu-info">
            <div class="pu-name">Tim Keseluruhan</div>
            <div class="pu-meta">
                <div><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg> Total Anggota: <?= $total_member ?></div>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="p-stats">
        <div class="ps-col">
            <div class="ps-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
            <div class="ps-lbl">Total Komisi</div>
            <div class="ps-val">Rp<?= number_format($total_komisi, 0, ',', '.') ?></div>
        </div>
        <div class="ps-col">
            <div class="ps-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
            <div class="ps-lbl">Anggota LV <?= $level ?></div>
            <div class="ps-val"><?= count($members) ?></div>
        </div>
    </div>

    <!-- TAUTAN REFERRAL -->
    <div class="p-wrap">
        <div class="pm-title">Tautan Referral</div>
        <div class="pm-list" style="margin-bottom:15px;">
            <div class="pm-item" onclick="copyValue('<?= htmlspecialchars($referral_link) ?>')">
                <div class="pmi-icon"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></div>
                <div class="pmi-text">
                    <div class="pmi-title">Copy Link Referensi Saya</div>
                    <div class="pmi-desc"><?= htmlspecialchars($referral_link) ?></div>
                </div>
                <div class="pmi-arrow"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></div>
            </div>
        </div>
    </div>

    <!-- TABS LEVEL -->
    <div class="p-tabs">
        <a href="?level=1" class="p-tab <?= $level===1?'active':'' ?>">LV 1 (<?= $total_l1 ?>)</a>
        <?php if ($lvl2_on): ?><a href="?level=2" class="p-tab <?= $level===2?'active':'' ?>">LV 2 (<?= $total_l2 ?>)</a><?php endif; ?>
        <?php if ($lvl3_on): ?><a href="?level=3" class="p-tab <?= $level===3?'active':'' ?>">LV 3 (<?= $total_l3 ?>)</a><?php endif; ?>
    </div>

    <!-- DAFTAR MEMBER -->
    <div class="p-wrap">
        <div class="pm-title">Daftar Anggota LV <?= $level ?></div>
        <div class="pm-list">
            <?php if (empty($members)): ?>
                <div class="pm-item">
                    <div class="pmi-text" style="text-align:center;padding:15px 0;">
                         <div class="pmi-title">Belum ada anggota.</div>
                         <div class="pmi-desc">Bagikan link referral untuk rekrut!</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($members as $mi => $m): 
                    $rawPhone = preg_replace('/[^0-9]/', '', $m['phone'] ?? '');
                    $ini = strtoupper(substr($rawPhone, -3, 2) ?: 'M' . ($mi+1));
                    $displayName = $m['username'] ?: ($m['phone'] ? substr($m['phone'],0,6).'...' : 'Member #'.($mi+1));
                ?>
                <div class="pm-item">
                    <div class="pmi-icon" style="border-radius:50%;font-size:12px;font-weight:800;background:#111;color:#F5D061;"><?= htmlspecialchars($ini) ?></div>
                    <div class="pmi-text">
                        <div class="pmi-title"><?= htmlspecialchars($displayName) ?></div>
                        <div class="pmi-desc"><?= date('d M Y', strtotime($m['created_at'])) ?> &bullet; <?= ($m['status']??'')==='Active' ? 'Aktif' : 'Nonaktif' ?></div>
                    </div>
                    <div class="pmi-arrow" style="text-align:right;">
                        <div style="font-size:11px;font-weight:800;color:#111;">+Rp<?= number_format((float)$m['komisi_dari_dia'],0,',','.') ?></div>
                        <div style="font-size:8px;color:rgba(0,0,0,0.5);font-weight:700;text-transform:uppercase;">Komisi</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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