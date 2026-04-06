<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

$_ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if (!preg_match('/android|iphone|ipad|ipod|mobile/i', $_ua)) { ?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Khusus Mobile</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg, #a34e94 0%, #733066 100%);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;color:#1e293b;}
.box{background:#ffffff;border-radius:24px;padding:40px 24px;max-width:360px;width:100%;text-align:center;box-shadow:0 12px 32px rgba(163,78,148,0.2);}
.icon{width:64px;height:64px;background:#faebf6;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#a34e94;}
.icon svg{width:32px;height:32px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
h2{font-size:1.2rem;font-weight:800;margin-bottom:8px;}
p{font-size:0.85rem;color:#64748b;margin-bottom:24px;line-height:1.6;}
.steps{display:flex;flex-direction:column;gap:12px;text-align:left;background:#f8fafc;padding:16px;border-radius:16px;}
.step{display:flex;align-items:flex-start;gap:12px;font-size:0.8rem;color:#475569;line-height:1.5;}
.sn{width:20px;height:20px;border-radius:50%;background:#a34e94;color:#fff;font-weight:700;font-size:0.7rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
</style></head><body>
<div class="box">
<div class="icon"><svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><path d="M12 18h.01"/></svg></div>
<h2>Khusus Mobile</h2>
<p>Halaman ini hanya dapat diakses melalui browser <strong>smartphone</strong> Anda.</p>
<div class="steps">
<div class="step"><div class="sn">1</div><div>Buka browser di HP/Smartphone kamu</div></div>
<div class="step"><div class="sn">2</div><div>Ketik ulang link halaman web ini</div></div>
<div class="step"><div class="sn">3</div><div>Gunakan Chrome atau Safari untuk hasil terbaik</div></div>
</div></div></body></html>
<?php exit; }

require_once __DIR__ . '/../mainconfig.php';
require_once __DIR__ . '/../lib/check_session.php';
require_once __DIR__ . '/../lib/is_login.php';
require_once __DIR__ . '/../lib/flash_message.php';

error_reporting(E_ALL);

if (!function_exists('protect')) {
    function protect($s) { return is_scalar($s) ? trim((string)$s) : ''; }
}
if (!function_exists('check_input')) {
    function check_input(array $arr, array $keys): bool {
        foreach ($keys as $k) {
            if (!isset($arr[$k]) || $arr[$k] === '' || $arr[$k] === null) return false;
        }
        return true;
    }
}
function wants_json_dep(): bool {
    if (!empty($_POST['ajax']) && $_POST['ajax'] === '1') return true;
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}
function respond_json_dep(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* SETTINGS */
$sq       = $model->db_query($db, "*", "settings", "id='1'");
$settings = $sq['rows'] ?? [];

// === PROXY API ===
$api_key    = $settings['api_key']    ?? '';
$secret_key = $settings['secret_key'] ?? '';
$deposit_url = 'https://asteelass.icu/api/deposit.php';

$site_name   = $settings['title']          ?? 'Platform';
$MIN_DEPOSIT = (int)($settings['min_depo'] ?? $settings['min_deposit'] ?? 50000);
$MAX_DEPOSIT = (int)($settings['max_depo'] ?? $settings['max_deposit'] ?? 50000000);

/* User saldo */
$uid  = $login['id'];
$uq   = mysqli_query($db, "SELECT saldo, point FROM users WHERE id='".$db->real_escape_string($uid)."' LIMIT 1");
$user = $uq ? mysqli_fetch_assoc($uq) : [];
$saldo_aset  = (float)($user['saldo'] ?? 0);
$point       = (float)($user['point'] ?? 0);

/* ACTION: DEPOSIT */
$deposit_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deposit') {
    ob_clean();
    try {
        if (!check_input($_POST, ['amount','method','csrf_token']))
            throw new Exception('Masukan tidak lengkap.');
        if (!hash_equals(csrf_token(), $_POST['csrf_token']))
            throw new Exception('Token tidak valid.');

        $method_raw   = protect($_POST['method']);
        $amount_input = (int) floor((float) protect($_POST['amount']));

        if ($amount_input < $MIN_DEPOSIT)
            throw new Exception('Minimal deposit Rp '.number_format((float)$MIN_DEPOSIT,0,',','.'));
        if ($amount_input > $MAX_DEPOSIT)
            throw new Exception('Maksimal deposit Rp '.number_format((float)$MAX_DEPOSIT,0,',','.'));

        $trx_number  = 'INV'.date('YmdHis').mt_rand(100,999);
        $is_qris     = ($method_raw === 'QRIS');
        $is_transfer = (!$is_qris && is_numeric($method_raw));
        $method_id   = $is_transfer ? (int)$method_raw : 0;

        if ($is_transfer) {
            $tm = $model->db_query($db, "id,name,min_amount,note,rekening,rekening_owner", "topup_methods", "id='$method_id' AND status=1");
            if (!$tm['count']) throw new Exception('Metode transfer tidak ditemukan atau tidak aktif.');
            $tm_row       = $tm['rows'];
            $min_transfer = (float)$tm_row['min_amount'];
            if ($amount_input < $min_transfer)
                throw new Exception('Minimal deposit via '.$tm_row['name'].' adalah Rp'.number_format($min_transfer,0,',','.'));

            $method_display = $tm_row['name'];
            $rek_number     = $tm_row['rekening']       ?? '';
            $rek_owner      = $tm_row['rekening_owner'] ?? '';
            $unique_code    = generate_unique_code_dep($db, $amount_input);
            $final_amount   = $amount_input + $unique_code;

            $insert = $model->db_insert($db, 'topups', [
                'trxtopup'   => $trx_number,
                'user_id'    => $uid,
                'method'     => $method_display,
                'note'       => $method_display.' Deposit',
                'post_amount'=> $amount_input,
                'amount'     => $final_amount,
                'status'     => 'Pending',
                'created_at' => date('Y-m-d H:i:s'),
                'provider'   => 'Manual',
            ]);
            if (!$insert) throw new Exception('Gagal menyimpan data transaksi.');

            $model->db_update($db, 'topups', [
                'remark'        => "Unique code: +$unique_code",
                'provider_meta' => json_encode([
                    'method_id'     => $method_id,
                    'bank_name'     => $method_display,
                    'rekening'      => $rek_number,
                    'rekening_owner'=> $rek_owner,
                    'unique_code'   => $unique_code,
                    'base_amount'   => $amount_input,
                    'note'          => $tm_row['note'] ?? '',
                ]),
                'updated_at'    => date('Y-m-d H:i:s'),
            ], "trxtopup='".$db->real_escape_string($trx_number)."'");

            $payment_url = base_url('pages/pay?trxid='.$trx_number);
        } else {


            $payload = json_encode([
                'amount' => $amount_input
            ]);
            $ch = curl_init($deposit_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-API-KEY: '.$api_key,
                    'X-SECRET-KEY: '.$secret_key,
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            if (curl_errno($ch)) { $e = curl_error($ch); curl_close($ch); throw new Exception('Curl Error: '.$e); }
            curl_close($ch);

            $result = json_decode($response, true);
            if (!$result) throw new Exception('Gagal parsing response dari payment gateway.');
            if (empty($result['status']) || $result['status'] !== true)
                throw new Exception($result['message'] ?? 'Gagal membuat pembayaran QRIS.');

            // Response: reference_id, total_payment, qr_code_url
            $ref_id    = $result['reference_id'] ?? $trx_number;
            $qris_url  = $result['qr_code_url']  ?? '';
            $amt_final = $result['total_payment'] ?? $amount_input;

            if (!$qris_url) throw new Exception('QR Code tidak ditemukan dalam response.');

            // Simpan full QR URL (bisa relative, jadikan absolute ke domain proxy)
            $qris_full = (str_starts_with($qris_url, 'http'))
                ? $qris_url
                : 'https://asteelass.icu/'.$qris_url;

            $insert = $model->db_insert($db, 'topups', [
                'trxtopup'   => $ref_id,
                'user_id'    => $uid,
                'method'     => 'QRIS',
                'note'       => 'QRIS Deposit',
                'post_amount'=> $amount_input,
                'amount'     => $amt_final,
                'status'     => 'Pending',
                'created_at' => date('Y-m-d H:i:s'),
                'provider'   => 'GATEWAY',
                'qris_url'   => $qris_full,
                'provider_meta' => json_encode([
                    'reference_id' => $ref_id,
                    'total_payment'=> $amt_final,
                    'qr_code_url'  => $qris_full,
                ]),
                'provider_ref'  => $ref_id
            ]);
            if (!$insert) throw new Exception('Gagal menyimpan data transaksi setelah API merespons.');

            $payment_url = base_url('pages/pay?trxid='.$ref_id);
        }

        if (wants_json_dep()) respond_json_dep(['ok'=>true,'redirect'=>$payment_url]);
        header('Location: '.$payment_url); exit;

    } catch (Exception $e) {
        if (isset($trx_number))
            $model->db_update($db,'topups',['status'=>'Failed','remark'=>$e->getMessage(),'updated_at'=>date('Y-m-d H:i:s')],"trxtopup='".$db->real_escape_string($trx_number)."'");
        $deposit_result = ['ok'=>false, 'msg'=>$e->getMessage()];
        if (wants_json_dep()) respond_json_dep(['ok'=>false,'error'=>$e->getMessage()]);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Deposit &bullet; <?= htmlspecialchars($site_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
.th-back, .th-history { 
    display: flex; align-items: center; justify-content: center; 
    width: 36px; height: 36px; background: rgba(255,255,255,0.08); 
    border-radius: 10px; color: #fff; text-decoration: none; 
}
.th-history { width: auto; padding: 0 12px; font-size: 11px; font-weight: 600; gap: 6px; margin-left: auto; border: 1px solid rgba(255,255,255,0.15);}
.th-back svg { width: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }
.th-history svg { width: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.th-title { font-size: 16px; font-weight: 700; color: #fff;}

.content-area { padding: 0 20px; }

/* MAIN CARD */
.form-card {
    background: linear-gradient(135deg, #18181B 0%, #000000 100%); border: 1px solid #333;
    border-radius: 16px; padding: 16px; margin-bottom: 20px;
}

.fc-head { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.fc-icon { width: 36px; height: 36px; background: linear-gradient(135deg, #C59327 0%, #F5D061 50%, #9C7012 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #111;}
.fc-icon svg { width: 18px; stroke: currentColor; fill: none; stroke-width: 2; }
.fc-title { font-size: 14.5px; font-weight: 700; color: #fff; }

.form-group { margin-bottom: 16px; }
.form-lbl {
    display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; 
    color: #fff; margin-bottom: 8px;
}
.form-lbl svg { width: 13px; stroke: #F5D061; fill: none; stroke-width: 2.5; }

/* CUSTOM SELECTOR */
.custom-select-wrapper { position: relative; margin-bottom: 16px; }
.method-grp {
    background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; padding: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: space-between;
    color: #F5D061; font-size: 13px; font-weight: 600;
}
.method-grp.active { border-color: #F5D061; }
.mg-text { flex: 1; user-select: none; }
.method-grp i { font-size: 14px; pointer-events: none; transition: transform 0.3s; }
.method-grp.active i { transform: rotate(180deg); }

.method-dropdown {
    position: absolute; left: 0; right: 0; top: 100%;
    background: #18181B; border: 1px solid #333; border-radius: 8px;
    margin-top: 5px; max-height: 0; overflow-y: auto; opacity: 0; pointer-events: none;
    transition: max-height 0.3s ease, opacity 0.3s ease; z-index: 99; box-shadow: 0 10px 25px rgba(0,0,0,0.5);
}
.method-dropdown.open { max-height: 250px; opacity: 1; pointer-events: auto; }
.md-item {
    padding: 12px 14px; font-size: 12.5px; color: #9ca3af; cursor: pointer;
    border-bottom: 1px solid #333; transition: 0.2s; font-weight: 500;
}
.md-item:last-child { border-bottom: none; }
.md-item:hover, .md-item.active { background: #000; color: #F5D061; font-weight: 600; }

/* INPUT BOX */
.input-wrap { position: relative; display: flex; align-items: center; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0 14px;}
.input-wrap .curr { font-size: 13px; font-weight: 700; color: #F5D061; margin-right: 8px; }
.form-input {
    flex: 1; background: transparent; border: none; padding: 14px 0; font-size: 14px; font-weight: 600; color: #fff;
    outline: none; font-family: 'Poppins', sans-serif;
}
.form-input::placeholder { color: #6b7280; font-weight: 500;}
.input-wrap.focus { border-color: #F5D061; }

/* GRID AMOUNT */
.grid-amt {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px;
}
.ga-btn {
    background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 8px;
    padding: 10px 0; color: #9ca3af; font-size: 11.5px; font-weight: 600;
    cursor: pointer; outline: none; transition: 0.2s; font-family: 'Poppins', sans-serif;
}
.ga-btn.active { background: rgba(245, 208, 97, 0.15); border-color: #F5D061; color: #F5D061; }

/* Button */
.btn-submit {
    display: block; width: 100%; background: linear-gradient(135deg, #C59327 0%, #F5D061 50%, #9C7012 100%); border-radius: 10px;
    padding: 14px; color: #111; font-size: 14px; font-weight: 800; font-family: 'Poppins', sans-serif;
    border: none; outline: none; cursor: pointer; transition: 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 10px;
}
.btn-submit:active { transform: scale(0.98); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-submit svg { width: 18px; stroke: currentColor; fill: none; stroke-width: 2; }

/* Info Box */
.info-box {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px; padding: 16px; margin-top: 20px;
}
.ib-head { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: #F5D061; margin-bottom: 12px;}
.ib-head svg { width: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
.ib-list { display: flex; flex-direction: column; gap: 10px;}
.ib-item { display: flex; align-items: flex-start; gap: 10px; font-size: 10px; color: #fff; line-height: 1.5; font-weight: 500;}
.ib-num { width: 16px; height: 16px; background: rgba(245, 208, 97, 0.15); color: #F5D061; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 700;}

/* ALERTS */
.err-alert {
    margin-bottom: 15px; padding: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5; border-radius: 8px; font-size: 11.5px; font-weight: 600; display: none; text-align:center;
}
.err-alert.show { display: block; }

</style>
</head>
<body>
<div class="app">

    <!-- HEADER -->
    <div class="th-container">
        <a href="<?= base_url('pages/profile') ?>" class="th-back">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="th-title">Deposit Saldo</div>
        <a href="<?= base_url('pages/history?type=deposit') ?>" class="th-history">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Riwayat
        </a>
    </div>

    <div class="content-area">

        <div class="err-alert" id="errBox"></div>

        <div class="form-card">
            <div class="fc-head">
                <div class="fc-icon"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2-2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg></div>
                <div class="fc-title">Pengisian Saldo</div>
            </div>

            <!-- METHOD SELECTOR -->
            <div class="form-group">
                <div class="form-lbl"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg> Metode Deposit</div>
                <div class="custom-select-wrapper">
                    <div class="method-grp" id="customMethodBtn">
                        <div class="mg-text" id="methodSelectedText">-- Pilih Metode --</div>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    
                    <div class="method-dropdown" id="methodDropdown">
                        <div class="md-item active" data-val="" data-min="">-- Pilih Metode --</div>
                        <div class="md-item" data-val="QRIS" data-min="<?= $MIN_DEPOSIT ?>">QRIS Instant</div>
                    </div>

                    <select id="methodSelect" style="display:none;" onchange="validate()">
                        <option value="">Pilih metode</option>
                        <option value="QRIS">QRIS</option>
                    </select>
                </div>
            </div>

            <!-- AMOUNT SELECTION -->
            <div class="form-group">
                <div class="form-lbl"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M16 11v1a4 4 0 0 1-8 0v-1"></path><line x1="12" y1="2" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="22"></line></svg> Pilih Jumlah Cepat</div>
                <div class="grid-amt">
                    <?php 
                    $possible_qa = [600, 1300, 4000, 7000, 10000, 15000, 20000, 25000, 50000, 100000, 200000, 300000, 500000, 1000000, 2000000, 5000000, 10000000];
                    $quick_amounts = [];
                    foreach ($possible_qa as $qa) {
                        if ($qa >= $MIN_DEPOSIT && count($quick_amounts) < 8) {
                            $quick_amounts[] = $qa;
                        }
                    }
                    if (empty($quick_amounts)) {
                        $mults = [1, 2, 3, 5, 10, 20, 50, 100];
                        foreach ($mults as $m) $quick_amounts[] = $MIN_DEPOSIT * $m;
                    }
                    foreach (array_slice($quick_amounts, 0, 8) as $qa): 
                    ?>
                    <button class="ga-btn" onclick="setAmt(<?= $qa ?>, this)"><?= number_format($qa/1000,0,'','.') ?>k</button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- INPUT FIELD -->
            <div class="form-group">
                <div class="form-lbl">IDR Jumlah Deposit</div>
                <div class="input-wrap">
                    <div class="curr">Rp</div>
                    <input type="text" class="form-input" id="amountInput" inputmode="numeric" placeholder="Masukkan jumlah deposit anda" oninput="onInput(this)" onfocus="this.parentElement.classList.add('focus')" onblur="this.parentElement.classList.remove('focus')">
                </div>
            </div>

            <button class="btn-submit" id="btnProses" onclick="doDeposit()" disabled>
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2-2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                Deposit Sekarang
            </button>
        </div>

        <!-- INFO -->
        <div class="info-box">
            <div class="ib-head"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Petunjuk Deposit</div>
            <div class="ib-list">
                <div class="ib-item"><div class="ib-num">1</div> Jam kerja layanan deposit adalah 09:00-20:30.</div>
                <div class="ib-item"><div class="ib-num">2</div> Lakukan pembayaran hanya melalui rekening bank yang didapatkan setelah Anda menekan tombol di atas.</div>
                <div class="ib-item"><div class="ib-num">3</div> Jumlah minimum adalah <strong>Rp<?= number_format($MIN_DEPOSIT,0,'','.') ?></strong>.</div>
                <div class="ib-item"><div class="ib-num">4</div> Dana akan diproses otomatis oleh sistem selama sesuai dengan ketentuan dan instruksi pada laman bayar berikutnya.</div>
            </div>
        </div>

    </div>
</div>

<!-- HIDDEN FORM -->
<form id="depF" style="display:none">
  <input type="hidden" name="action" value="deposit">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input type="hidden" name="ajax" value="1">
  <input type="hidden" name="amount" id="fAmount">
  <input type="hidden" name="method" id="fMethod">
</form>

<script>
let val = 0;
let min_val = <?= (int)$MIN_DEPOSIT ?>;

function setAmt(v, el) {
    document.querySelectorAll('.ga-btn').forEach(b => b.classList.remove('active'));
    if(el) el.classList.add('active');
    val = v;
    document.getElementById('amountInput').value = v;
    validate();
}

function onInput(el) {
    let clean = el.value.replace(/[^0-9]/g, '');
    val = parseInt(clean) || 0;
    el.value = clean;
    document.querySelectorAll('.ga-btn').forEach(b => b.classList.remove('active'));
    validate();
}

function validate() {
    let err = document.getElementById('errBox');
    err.classList.remove('show');
    let sel = document.getElementById('methodSelect');
    let opt = sel.options[sel.selectedIndex];
    let cm = parseInt(opt.getAttribute('data-min') || min_val) || min_val;
    
    let btn = document.getElementById('btnProses');
    if(val > 0 && val < cm) {
        err.textContent = "Minimal deposit Rp " + cm;
        err.classList.add('show');
    }
    btn.disabled = !(val >= cm && sel.value !== '');
}

async function doDeposit() {
    let btn = document.getElementById('btnProses');
    btn.disabled = true; btn.textContent = "Memproses...";
    
    document.getElementById('fAmount').value = val;
    document.getElementById('fMethod').value = document.getElementById('methodSelect').value;
    
    let fd = new FormData(document.getElementById('depF'));
    try {
        let r = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' });
        let d = await r.json();
        if(d.ok && d.redirect) window.location.href = d.redirect;
        else {
            document.getElementById('errBox').textContent = d.error || 'Terjadi kesalahan.';
            document.getElementById('errBox').classList.add('show');
            btn.disabled = false; btn.textContent = "Deposit Sekarang";
        }
    } catch(e) {
        document.getElementById('errBox').textContent = 'Koneksi error.';
        document.getElementById('errBox').classList.add('show');
        btn.disabled = false; btn.textContent = "Deposit Sekarang";
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('customMethodBtn');
    const dp = document.getElementById('methodDropdown');
    const sel = document.getElementById('methodSelect');
    const txt = document.getElementById('methodSelectedText');
    const items = document.querySelectorAll('.md-item');

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        dp.classList.toggle('open');
        btn.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!dp.contains(e.target) && !btn.contains(e.target)) {
            dp.classList.remove('open');
            btn.classList.remove('active');
        }
    });

    items.forEach(it => {
        it.addEventListener('click', () => {
            items.forEach(i => i.classList.remove('active'));
            it.classList.add('active');
            let v = it.getAttribute('data-val');
            sel.value = v;
            txt.textContent = it.textContent.trim();
            sel.dispatchEvent(new Event('change'));
            dp.classList.remove('open');
            btn.classList.remove('active');
        });
    });
});
</script>
</body>
</html>
