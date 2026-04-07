<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../mainconfig.php';
require_once __DIR__ . '/../lib/check_session.php';
require_once __DIR__ . '/../lib/is_login.php';

/* ══════════════════════════════════════════════
   DAFTAR BANK & E-WALLET
══════════════════════════════════════════════ */
$AVAILABLE_METHODS = [
    'DANA'   => ['label' => 'DANA',       'color' => '#118EEA', 'icon' => 'fa-solid fa-wallet'],
    'OVO'    => ['label' => 'OVO',        'color' => '#4C3494', 'icon' => 'fa-solid fa-mobile-screen-button'],
    'GOPAY'  => ['label' => 'GoPay',      'color' => '#00AED6', 'icon' => 'fa-solid fa-g'],
    'SHOPEE' => ['label' => 'ShopeePay', 'color' => '#EE4D2D', 'icon' => 'fa-solid fa-bag-shopping'],
];

/* ══════════════════════════════════════════════
   USER DATA & REKENING SEKARANG
══════════════════════════════════════════════ */
$uid_esc = $db->real_escape_string($login['id']);
$user_q  = mysqli_query($db, "SELECT username, rekening, no_rek, pemilik FROM users WHERE id='{$uid_esc}' LIMIT 1");
$user    = $user_q ? mysqli_fetch_assoc($user_q) : [];

$usnnya      = $user['username'] ?? 'User';
$rek_code    = strtoupper($user['rekening'] ?? '');
$rek_no      = $user['no_rek']  ?? '';
$rek_pemilik = $user['pemilik'] ?? '';
$has_rek     = !empty($rek_code) && !empty($rek_no);

/* ══════════════════════════════════════════════
   ACTION: SAVE BANK
══════════════════════════════════════════════ */
$bank_result = null;
$show_form   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bank') {
    if ($has_rek) {
        $bank_result = ['ok' => false, 'msg' => 'Rekening sudah terdaftar dan tidak dapat diubah.'];
    } else {
        $b_rekening = strtoupper(trim($_POST['metode'] ?? ''));
        $b_no_rek   = preg_replace('/[^0-9]/', '', $_POST['rekening'] ?? '');
        $b_pemilik  = trim($_POST['nama_pemilik'] ?? $usnnya);

        $b_errors = [];

        if (empty($b_rekening) || !array_key_exists($b_rekening, $AVAILABLE_METHODS)) {
            $b_errors[] = 'Pilih metode bank / e-wallet yang valid.';
        }
        if (strlen($b_no_rek) < 8) {
            $b_errors[] = 'Nomor rekening / HP tidak valid (minimal 8 digit).';
        }

        if (empty($b_errors)) {
            $b_r_esc = $db->real_escape_string($b_rekening);
            $b_n_esc = $db->real_escape_string($b_no_rek);
            $dup_q   = mysqli_query($db, "SELECT id FROM users WHERE rekening='{$b_r_esc}' AND no_rek='{$b_n_esc}' AND id != '{$uid_esc}' LIMIT 1");
            if ($dup_q && mysqli_num_rows($dup_q) > 0) {
                $b_errors[] = 'Nomor rekening / e-wallet tersebut sudah terdaftar pada akun lain.';
            }
        }

        if (empty($b_errors)) {
            $b_p_esc = $db->real_escape_string($b_pemilik);
            $b_r_esc = $db->real_escape_string($b_rekening);
            $b_n_esc = $db->real_escape_string($b_no_rek);
            $upd = mysqli_query($db,
                "UPDATE users SET rekening='{$b_r_esc}', pemilik='{$b_p_esc}', no_rek='{$b_n_esc}'
                 WHERE id='{$uid_esc}' AND (rekening IS NULL OR rekening='') AND (no_rek IS NULL OR no_rek='')"
            );
            
            if ($upd && mysqli_affected_rows($db) > 0) {
                $bank_result = ['ok' => true,  'msg' => 'Rekening berhasil disimpan.'];
                $has_rek = true;
                $rek_code = $b_rekening;
                $rek_no = $b_no_rek;
                $rek_pemilik = $b_pemilik;
            } elseif ($upd) {
                $bank_result = ['ok' => false, 'msg' => 'Rekening sudah terdaftar. Tidak dapat mengubah data.'];
            } else {
                $bank_result = ['ok' => false, 'msg' => 'Gagal menyimpan rekening. Coba lagi.'];
                $show_form = true;
            }
        } else {
            $bank_result = ['ok' => false, 'msg' => implode(' ', $b_errors)];
            $show_form = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Rekening Bank</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Poppins', sans-serif; background: #012b26; color: #fff; -webkit-font-smoothing: antialiased; }
.app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; background: #012b26; padding-bottom: 40px; }

/* HEADER */
.h-bg { background: linear-gradient(135deg, #023e35 0%, #01312b 100%); padding: 25px 20px 100px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; position: relative; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1; }
.h-nav { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
.back-btn { width: 36px; height: 36px; border-radius: 12px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
.back-btn:active { background: rgba(255,255,255,0.1); }
.h-title { display: flex; flex-direction: column; }
.h-title h3 { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 2px; }
.h-title p { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.7); }

.h-card { background: transparent; border: 1px solid rgba(250,204,21,0.25); border-radius: 16px; padding: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.hc-left { display: flex; align-items: center; gap: 12px; }
.hc-icon { width: 42px; height: 42px; border-radius: 10px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; color: #facc15; font-size: 18px; }
.hc-texts { display: flex; flex-direction: column; }
.hc-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;}
.hc-val { font-size: 16px; font-weight: 800; color: #fff; line-height: 1; }
.hc-add { background: #fff; color: #012b26; border: none; padding: 10px 16px; font-size: 11px; font-weight: 700; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); transition: 0.2s; }
.hc-add:active { transform: scale(0.95); }
.hc-add i { font-size: 12px; }

/* MAIN CONTENT */
.c-body { padding: 0 20px; margin-top: -65px; position: relative; z-index: 2; }
.v-card { background: #023e35; border-radius: 20px; padding: 30px 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.2); border: 1px solid #035246; text-align: center; }

/* EMPTY STATE */
.es-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(255,255,255,0.05); display: inline-flex; align-items: center; justify-content: center; font-size: 24px; color: rgba(255,255,255,0.3); margin-bottom: 16px; }
.es-title { font-size: 14px; font-weight: 800; color: #fff; margin-bottom: 8px; }
.es-desc { font-size: 11px; font-weight: 500; color: rgba(255,255,255,0.6); line-height: 1.5; margin-bottom: 24px; }
.btn-primary { background: #facc15; color: #012b26; border: none; padding: 14px; font-size: 13px; font-weight: 800; border-radius: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; box-shadow: 0 4px 15px rgba(250, 204, 21, 0.2); transition: 0.2s; }
.btn-primary:active { transform: scale(0.97); }

/* LISTED BANK */
.lb-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(250, 204, 21, 0.1); display: inline-flex; align-items: center; justify-content: center; font-size: 24px; color: #facc15; margin-bottom: 16px; border: 1px solid rgba(250, 204, 21, 0.2); }
.lb-info { display: flex; flex-direction: column; align-items: flex-start; background: #012b26; border: 1px solid rgba(250,204,21,0.1); padding: 16px; border-radius: 14px; text-align: left; margin-bottom: 0;}
.lb-info div { display: flex; flex-direction: column; width: 100%; margin-bottom: 12px; }
.lb-info div:last-child { margin-bottom: 0; }
.lb-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-bottom: 4px;}
.lb-val { font-size: 13px; font-weight: 800; color: #fff; }

/* FORM WRAPPER */
.f-card { background: #023e35; border-radius: 20px; padding: 20px; margin-bottom: 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.f-group { margin-bottom: 16px; display: flex; flex-direction: column; }
.f-group:last-child { margin-bottom: 0; }
.f-lbl { font-size: 11px; font-weight: 800; color: #fff; margin-bottom: 8px; }
.f-inp, .f-sel { width: 100%; background: #012b26; border: 1px solid rgba(255,255,255,0.05); padding: 14px; border-radius: 12px; font-size: 13px; font-weight: 500; color: #fff; outline: none; font-family: 'Poppins', sans-serif; transition: 0.2s;}
.f-inp:focus, .f-sel:focus { border-color: #facc15; }
.f-sel { appearance: none; cursor: pointer; }
.f-sel-wrap { position: relative; }
.f-sel-icon { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); pointer-events: none; font-size: 12px; }

/* WARNING BOX */
.warn-box { background: rgba(250, 204, 21, 0.05); border: 1px solid rgba(250, 204, 21, 0.2); border-radius: 16px; padding: 16px; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px;}
.wb-icon { width: 24px; height: 24px; border-radius: 50%; background: #facc15; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #012b26; flex-shrink: 0; }
.wb-text { flex: 1; }
.wb-title { font-size: 12px; font-weight: 800; color: #facc15; margin-bottom: 6px; }
.wb-list { padding-left: 14px; margin: 0;}
.wb-list li { font-size: 10px; color: rgba(255,255,255,0.7); margin-bottom: 4px; font-weight: 500; }
.wb-list li:last-child { margin-bottom: 0; }

/* ACTIONS */
.f-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 14px; font-size: 13px; font-weight: 800; border-radius: 14px; cursor: pointer; text-align: center; }

/* CUSTOM DROPDOWN */
.cd-wrap { position: relative; user-select: none; }
.cd-selected {
  display: flex; align-items: center; gap: 12px;
  background: #012b26; border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px; padding: 13px 14px; cursor: pointer;
  transition: 0.2s;
}
.cd-selected:hover, .cd-wrap.open .cd-selected { border-color: #facc15; box-shadow: 0 0 0 3px rgba(250,204,21,0.1); }
.cd-placeholder { font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.4); flex: 1; }
.cd-sel-text { font-size: 13px; font-weight: 700; color: #fff; flex: 1; }
.cd-chevron { color: rgba(255,255,255,0.4); font-size: 12px; transition: transform 0.2s; }
.cd-wrap.open .cd-chevron { transform: rotate(180deg); }
.cd-ew-icon {
  width: 32px; height: 32px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; color: #fff; flex-shrink: 0;
}

.cd-list {
  display: none; position: absolute; top: calc(100% + 8px); left: 0; right: 0;
  background: #023e35; border: 1px solid rgba(250,204,21,0.2);
  border-radius: 16px; overflow: hidden; z-index: 999;
  box-shadow: 0 12px 30px rgba(0,0,0,0.4);
}
.cd-wrap.open .cd-list { display: block; animation: dropDown 0.18s ease; }
@keyframes dropDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

.cd-item {
  display: flex; align-items: center; gap: 12px; padding: 13px 14px;
  cursor: pointer; transition: background 0.15s;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.cd-item:last-child { border-bottom: none; }
.cd-item:hover { background: rgba(250,204,21,0.06); }
.cd-item-label { font-size: 13px; font-weight: 700; color: #fff; flex: 1; }
.cd-item-check { color: #facc15; font-size: 12px; opacity: 0; }
.cd-item.selected .cd-item-check { opacity: 1; }
.cd-item.selected { background: rgba(250,204,21,0.05); }

/* ALERT */
.alert { padding: 14px; border-radius: 14px; font-size: 11px; font-weight: 600; text-align: center; margin-bottom: 16px; }
.alert.ok { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
.alert.err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
</style>
</head>
<body>
<div class="app">

    <!-- HEADER CURVED -->
    <div class="h-bg">
        <div class="h-nav">
            <a href="<?= base_url('pages/profile') ?>" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
            <div class="h-title" id="head-title">
                <h3>Rekening bank</h3>
                <p>Kelola rekening & e-wallet</p>
            </div>
        </div>
        <div class="h-card">
            <div class="hc-left">
                <div class="hc-icon"><i class="fa-regular fa-credit-card"></i></div>
                <div class="hc-texts">
                    <span class="hc-lbl">TERDAFTAR</span>
                    <h4 class="hc-val"><?= $has_rek ? '1' : '0' ?></h4>
                </div>
            </div>
            <?php if(!$has_rek): ?>
            <button class="hc-add" id="btn-add-top" onclick="showForm()"><i class="fa-solid fa-plus"></i> Tambah</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="c-body">
        
        <?php if ($bank_result): ?>
        <div class="alert <?= $bank_result['ok'] ? 'ok' : 'err' ?>">
            <?= htmlspecialchars($bank_result['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- VIEW: LIST -->
        <div id="v-list" style="display: <?= ($has_rek || !$show_form && !$bank_result) ? 'block' : 'none' ?>;">
            <?php if(!$has_rek): ?>
            <div class="v-card" style="padding: 40px 20px;">
                <div class="es-icon"><i class="fa-regular fa-credit-card"></i></div>
                <div class="es-title">Belum ada rekening</div>
                <div class="es-desc">Tambahkan bank atau e-wallet untuk penarikan dana dengan mudah</div>
                <button class="btn-primary" onclick="showForm()">Tambah rekening</button>
            </div>
            <?php else: ?>
            <div class="v-card">
                <div class="lb-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div class="es-title" style="margin-bottom: 20px;">Rekening Anda</div>
                
                <div class="lb-info">
                    <div>
                        <span class="lb-lbl">Bank / E-Wallet</span>
                        <span class="lb-val"><?= htmlspecialchars($AVAILABLE_METHODS[$rek_code]['label'] ?? $rek_code) ?></span>
                    </div>
                    <div>
                        <span class="lb-lbl">Nomor Rekening / HP</span>
                        <span class="lb-val"><?= htmlspecialchars($rek_no) ?></span>
                    </div>
                    <div>
                        <span class="lb-lbl">Nama Pemilik</span>
                        <span class="lb-val"><?= htmlspecialchars($rek_pemilik) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- VIEW: FORM -->
        <div id="v-form" style="display: <?= (!$has_rek && ($show_form || isset($_GET['action']))) ? 'block' : 'none' ?>;">
            <form method="POST">
                <input type="hidden" name="action" value="save_bank">
                
                <div class="f-card">
                    <div class="f-group">
                        <label class="f-lbl">Bank / e-wallet</label>
                        <!-- Hidden real input for form submit -->
                        <input type="hidden" name="metode" id="metodenHidden" required>
                        <!-- Custom Dropdown -->
                        <div class="cd-wrap" id="cdWrap">
                          <div class="cd-selected" id="cdSelected" onclick="toggleDropdown()">
                            <span class="cd-placeholder" id="cdPlaceholder">Pilih e-wallet</span>
                            <span class="cd-sel-text" id="cdSelText" style="display:none;"></span>
                            <i class="fa-solid fa-chevron-down cd-chevron" id="cdChevron"></i>
                          </div>
                          <div class="cd-list" id="cdList">
                            <?php foreach ($AVAILABLE_METHODS as $code => $ew): ?>
                            <div class="cd-item" data-value="<?= $code ?>" onclick="selectWallet('<?= $code ?>', '<?= htmlspecialchars($ew['label']) ?>', '<?= $ew['color'] ?>', '<?= $ew['icon'] ?>')">
                              <div class="cd-ew-icon" style="background:<?= $ew['color'] ?>22; color:<?= $ew['color'] ?>;">
                                <i class="<?= $ew['icon'] ?>"></i>
                              </div>
                              <span class="cd-item-label"><?= htmlspecialchars($ew['label']) ?></span>
                              <i class="fa-solid fa-check cd-item-check"></i>
                            </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                    </div>
                </div>

                <div class="f-card">
                    <div class="f-group">
                        <label class="f-lbl">Nomor rekening / HP</label>
                        <input type="text" name="rekening" class="f-inp" inputmode="numeric" placeholder="Nomor rekening atau nomor HP e-wallet" required>
                    </div>
                </div>

                <div class="f-card">
                    <div class="f-group">
                        <label class="f-lbl">Nama pemilik</label>
                        <input type="text" name="nama_pemilik" class="f-inp" placeholder="Sesuai di buku tabungan / e-wallet" required>
                    </div>
                </div>

                <div class="warn-box">
                    <div class="wb-icon"><i class="fa-solid fa-info"></i></div>
                    <div class="wb-text">
                        <div class="wb-title">Perhatian</div>
                        <ul class="wb-list">
                            <li>Pastikan nomor benar sebelum simpan</li>
                            <li>Nama harus sesuai identitas</li>
                            <li>Rekening / akun harus aktif</li>
                        </ul>
                    </div>
                </div>

                <div class="f-actions">
                    <button type="button" class="btn-outline" onclick="showList()">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
const hasRek = <?= $has_rek ? 'true' : 'false' ?>;

function showForm() {
    if(hasRek) return;
    document.getElementById('v-list').style.display = 'none';
    document.getElementById('v-form').style.display = 'block';
    const btnTop = document.getElementById('btn-add-top');
    if(btnTop) btnTop.style.display = 'none';
    document.querySelector('#head-title h3').innerText = 'Tambah rekening';
    document.querySelector('#head-title p').innerText = 'Data untuk penarikan dana';
}

function showList() {
    document.getElementById('v-list').style.display = 'block';
    document.getElementById('v-form').style.display = 'none';
    const btnTop = document.getElementById('btn-add-top');
    if(btnTop) btnTop.style.display = 'flex';
    document.querySelector('#head-title h3').innerText = 'Rekening bank';
    document.querySelector('#head-title p').innerText = 'Kelola rekening & e-wallet';
}

/* ── Custom Dropdown ── */
function toggleDropdown() {
    document.getElementById('cdWrap').classList.toggle('open');
}

function selectWallet(code, label, color, icon) {
    // Set hidden input
    document.getElementById('metodenHidden').value = code;

    // Update trigger display
    const selText = document.getElementById('cdSelText');
    const placeholder = document.getElementById('cdPlaceholder');
    selText.innerHTML = `<div class="cd-ew-icon" style="background:${color}22;color:${color};width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:13px;margin-right:4px;"><i class="${icon}"></i></div>${label}`;
    selText.style.display = 'flex';
    selText.style.alignItems = 'center';
    selText.style.gap = '8px';
    placeholder.style.display = 'none';

    // Mark selected item
    document.querySelectorAll('.cd-item').forEach(el => el.classList.remove('selected'));
    document.querySelector(`.cd-item[data-value="${code}"]`)?.classList.add('selected');

    // Close dropdown
    document.getElementById('cdWrap').classList.remove('open');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('cdWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});

// Initial state binding if validation failed
<?php if(isset($_POST['action']) && !empty($bank_result) && !$bank_result['ok'] && !$has_rek): ?>
    showForm();
<?php endif; ?>
</script>
</body>
</html>
