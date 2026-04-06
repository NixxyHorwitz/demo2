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
    'DANA'      => 'DANA',
    'OVO'       => 'OVO',
    'GOPAY'     => 'GoPay',
    'SHOPEE'    => 'ShopeePay',
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bank') {
    if ($has_rek) {
        $bank_result = ['ok' => false, 'msg' => 'Rekening sudah terdaftar dan tidak dapat diubah.'];
    } else {
        $b_rekening = strtoupper(trim($_POST['metode'] ?? ''));
        $b_no_rek   = preg_replace('/[^0-9]/', '', $_POST['rekening'] ?? '');
        $b_pemilik  = trim($_POST['nama_pemilik'] ?? $usnnya);

        $b_errors = [];

        if (empty($b_rekening) || !isset($AVAILABLE_METHODS[$b_rekening])) {
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
            }
        } else {
            $bank_result = ['ok' => false, 'msg' => implode(' ', $b_errors)];
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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { 
    font-family: 'Poppins', sans-serif; 
    background-color: #111111; 
    color: #fff; -webkit-font-smoothing: antialiased; 
}
.app { 
    max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative;
    background-color: #111111;
    background-image: 
      linear-gradient(45deg, rgba(255,255,255,0.02) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.02) 75%, rgba(255,255,255,0.02)), 
      linear-gradient(45deg, rgba(255,255,255,0.02) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.02) 75%, rgba(255,255,255,0.02));
    background-size: 40px 40px;
    background-position: 0 0, 20px 20px;
    padding-bottom: 40px;
}

/* HEADER */
.th-container { padding: 20px; display: flex; align-items: center; justify-content: flex-start; gap: 15px;}
.th-back { 
    display: flex; align-items: center; justify-content: center; 
    width: 36px; height: 36px; background: rgba(255,255,255,0.08); 
    border-radius: 10px; color: #fff; text-decoration: none; 
}
.th-back svg { width: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }
.th-title { font-size: 16px; font-weight: 700; color: #fff;}

.content-area { padding: 0 20px; }

/* TOP INFO CARD */
.info-card {
    background: linear-gradient(135deg, #18181B 0%, #000000 100%); border: 1px solid #333;
    border-radius: 12px; padding: 12px 16px; margin-bottom: 20px;
    display: flex; align-items: center; justify-content: flex-start; gap: 14px;
}
.ic-icon {
    width: 44px; height: 44px; background: rgba(245, 208, 97, 0.15); border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #F5D061; border: 1px solid rgba(245, 208, 97, 0.3);
}
.ic-icon svg { width: 22px; stroke: currentColor; fill: none; stroke-width: 2; }
.ic-text { flex: 1; display:flex; flex-direction:column; gap:2px; }
.ic-title { font-size: 13px; font-weight: 700; color: #F5D061; }
.ic-desc { font-size: 10px; color: #9ca3af; font-weight: 500; }

/* FORM CARD */
.form-card {
    background: linear-gradient(135deg, #18181B 0%, #000000 100%); border: 1px solid #333;
    border-radius: 16px; padding: 16px; margin-bottom: 20px;
}

/* Form Section Header */
.fs-head {
    display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
}
.fs-icon {
    width: 36px; height: 36px; background: linear-gradient(135deg, #C59327 0%, #F5D061 50%, #9C7012 100%); border-radius: 8px;
    display: flex; align-items: center; justify-content: center; color: #111;
}
.fs-icon svg { width: 18px; stroke: currentColor; fill: none; stroke-width: 2; }
.fs-title { font-size: 14.5px; font-weight: 700; color: #fff; }

/* Form Group */
.form-group { margin-bottom: 16px; }
.form-lbl {
    display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; 
    color: #fff; margin-bottom: 8px;
}
.form-lbl svg { width: 13px; stroke: #F5D061; fill: none; stroke-width: 2.5; }

.input-wrap { position: relative; }
.form-input, .form-select {
    width: 100%; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; padding: 12px 14px; font-size: 13px; font-weight: 500; color: #fff;
    outline: none; font-family: 'Poppins', sans-serif; transition: 0.2s;
}
.form-input:focus, .form-select:focus { border-color: #F5D061; }
.form-input::placeholder { color: #6b7280; }
.form-select { appearance: none; color: #e5e7eb; cursor: pointer;}
.input-wrap .trailing-icon {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,0.05); border-radius: 6px; width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center; color: #F5D061; pointer-events: none;
}
.trailing-icon svg { width: 14px; stroke: currentColor; fill: none; stroke-width: 2; }

/* Readonly Input Styling to match Locked state */
.form-input[readonly] { color: #9ca3af; pointer-events: none;}

/* Default Box */
.default-box {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px; padding: 15px; margin-bottom: 20px;
    display: flex; align-items: center; justify-content: space-between; gap: 15px;
}
.db-icon {
    width: 32px; height: 32px; background: rgba(245, 208, 97, 0.15); border-radius: 8px;
    display: flex; align-items: center; justify-content: center; color: #F5D061; flex-shrink: 0;
}
.db-icon svg { width: 16px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.db-text { flex: 1; display:flex; flex-direction:column; }
.db-title { font-size: 12px; font-weight: 700; color: #fff; }
.db-desc { font-size: 9.5px; color: #9ca3af; }
.db-toggle { width: 44px; height: 24px; background: #F5D061; border-radius: 14px; position: relative; }
.db-toggle::after { content: ''; position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; background: #111; border-radius: 50%; }

/* Button */
.btn-submit {
    display: block; width: 100%; background: linear-gradient(135deg, #C59327 0%, #F5D061 50%, #9C7012 100%); border-radius: 10px;
    padding: 14px; color: #111; font-size: 14px; font-weight: 800; font-family: 'Poppins', sans-serif;
    border: none; outline: none; cursor: pointer; transition: 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-submit:active { transform: scale(0.98); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-submit svg { width: 16px; stroke: currentColor; fill: none; stroke-width: 2; }

/* Security Box */
.security-box {
    background: rgba(245, 208, 97, 0.05); border: 1px solid rgba(245, 208, 97, 0.2);
    border-radius: 12px; padding: 16px; display: flex; align-items: flex-start; gap: 12px;
}
.sb-icon {
    width: 24px; height: 24px; background: rgba(245, 208, 97, 0.15); border-radius: 6px;
    display: flex; align-items: center; justify-content: center; color: #F5D061; flex-shrink: 0;
}
.sb-icon svg { width: 14px; stroke: currentColor; fill: none; stroke-width: 2; }
.sb-text { flex: 1; }
.sb-title { font-size: 11px; font-weight: 600; color: #F5D061; margin-bottom: 2px; }
.sb-desc { font-size: 9.5px; color: #9ca3af; line-height: 1.5; }

/* Alert Box */
.alert-box { padding: 12px; border-radius: 8px; font-size: 11.5px; text-align: center; margin-bottom: 16px; font-weight: 500; }
.alert-box.err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
.alert-box.ok  { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; }
</style>
</head>
<body>
<div class="app">

    <!-- HEADER -->
    <div class="th-container">
        <a href="<?= base_url('pages/profile') ?>" class="th-back">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="th-title">Rekening Bank</div>
    </div>

    <!-- CONTENT -->
    <div class="content-area">

        <!-- TOP INFO CARD -->
        <div class="info-card">
            <div class="ic-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg></div>
            <div class="ic-text">
                <div class="ic-title">Hubungkan Rekening Bank</div>
                <div class="ic-desc">Rekening bank digunakan untuk penarikan dana investasi Anda</div>
            </div>
        </div>

        <?php if ($bank_result): ?>
            <div class="alert-box <?= $bank_result['ok'] ? 'ok' : 'err' ?>">
                <?= htmlspecialchars($bank_result['msg']) ?>
            </div>
        <?php endif; ?>

        <?php if ($has_rek): ?>
            <div class="alert-box ok" style="margin-bottom:15px;">Akun telah tertaut dan dikunci.</div>
        <?php endif; ?>

        <form method="POST">
            <?php if(!$has_rek): ?>
            <input type="hidden" name="action" value="save_bank">
            <?php endif; ?>
            
            <div class="form-card">
                <div class="fs-head">
                    <div class="fs-icon"><svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg></div>
                    <div class="fs-title">Informasi Rekening</div>
                </div>

                <div class="form-group">
                    <div class="form-lbl"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Nama Lengkap Pemilik Rekening</div>
                    <div class="input-wrap">
                        <input type="text" class="form-input" <?= $has_rek?'readonly':'' ?> name="nama_pemilik" value="<?= htmlspecialchars($has_rek?$rek_pemilik:$usnnya) ?>" placeholder="Masukkan nama sesuai buku rekening" required>
                        <div class="trailing-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-lbl"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg> Pilih Bank</div>
                    <div class="input-wrap">
                        <?php if($has_rek): ?>
                            <input type="text" class="form-input" readonly value="<?= htmlspecialchars($AVAILABLE_METHODS[$rek_code] ?? $rek_code) ?>">
                        <?php else: ?>
                            <select name="metode" class="form-select" required>
                                <option value="" hidden>-- Pilih Bank Tujuan --</option>
                                <?php foreach ($AVAILABLE_METHODS as $code => $label): ?>
                                <option value="<?= $code ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="trailing-icon" style="color:#9ca3af; border:none; background:none;"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-lbl"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg> Nomor Rekening</div>
                    <div class="input-wrap">
                        <input type="text" class="form-input" <?= $has_rek?'readonly':'' ?> name="rekening" inputmode="numeric" value="<?= htmlspecialchars($rek_no) ?>" placeholder="Masukkan nomor rekening" required>
                        <div class="trailing-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg></div>
                    </div>
                </div>

                <div class="default-box">
                    <div class="db-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div>
                    <div class="db-text">
                        <div class="db-title">Jadikan Default</div>
                        <div class="db-desc">Gunakan untuk semua penarikan</div>
                    </div>
                    <div class="db-toggle"></div>
                </div>

                <button type="submit" class="btn-submit" <?= $has_rek?'disabled':'' ?> onclick="<?= $has_rek?'':'if(this.form.checkValidity()){ this.innerHTML=\'Memproses...\'; this.form.submit(); this.disabled=true; }' ?>">
                    <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <?= $has_rek ? 'Akun Anda Telah Dikunci' : 'Simpan Rekening' ?>
                </button>
            </div>
        </form>

        <!-- SECURITY BOX -->
        <div class="security-box">
            <div class="sb-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
            <div class="sb-text">
                <div class="sb-title">Keamanan Data Terjamin</div>
                <div class="sb-desc">Data rekening bank Anda dienkripsi dan dilindungi. Kami tidak akan membagikan informasi Anda kepada pihak ketiga.</div>
            </div>
        </div>

    </div>
</div>
</body>
</html>
