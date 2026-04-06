<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

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
function wants_json_wd(): bool {
    if (!empty($_POST['ajax']) && $_POST['ajax'] === '1') return true;
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}
function respond_json_wd(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* SETTINGS */
$sq       = $model->db_query($db, "*", "settings", "id='1'");
$settings = $sq['rows'] ?? [];

$site_name    = $settings['title']          ?? 'Platform';
$MIN_WITHDRAW = (int)($settings['min_wd']   ?? $settings['min_withdraw'] ?? 50000);
$MAX_WITHDRAW = (int)($settings['max_wd']   ?? $settings['max_withdraw'] ?? 50000000);
$FIXED_FEE    = (int)($settings['withdraw_fee'] ?? 0);
$PERCENT_FEE  = (float)($settings['withdraw_fee_percent'] ?? 0) / 100;
$WD_PRODUCT_ID= (int)($settings['wd_product_id'] ?? 0);
$fee_persen   = (float)($settings['withdraw_fee_percent'] ?? 0);

// API Gateway config
$api_key    = $settings['api_key']    ?? '';
$secret_key = $settings['secret_key'] ?? '';

$EWALLET_ONLY = [
    'OVO'    => ['color'=>'#4C3494'],
    'DANA'   => ['color'=>'#118EEA'],
    'GOPAY'  => ['color'=>'#00AA13'],
    'SHOPEE' => ['color'=>'#EE4D2D'],
];

/* USER DATA */
$uid  = $login['id'];
$uq   = mysqli_query($db, "SELECT * FROM users WHERE id='".$db->real_escape_string($uid)."' LIMIT 1");
$user = $uq ? mysqli_fetch_assoc($uq) : $login;
$saldo = (float)($user['point'] ?? 0);

$rek_code    = strtoupper($user['rekening'] ?? '');
$rek_no      = $user['no_rek']    ?? '';
$rek_pemilik = $user['pemilik']   ?? '';
$has_rekening = !empty($rek_code) && !empty($rek_no);
$ew_color    = $EWALLET_ONLY[$rek_code]['color'] ?? '#2d5a16';
$ew_name     = match($rek_code) {
    'OVO'    => 'OVO',
    'DANA'   => 'DANA',
    'GOPAY'  => 'GoPay',
    'SHOPEE' => 'ShopeePay',
    default  => $rek_code ?: '-'
};

/* Total withdraw berhasil */
$twq = mysqli_query($db, "SELECT COALESCE(SUM(amount),0) AS total FROM withdraws WHERE user_id='".$db->real_escape_string($uid)."' AND status='Success'");
$total_wd_success = $twq ? (float)(mysqli_fetch_assoc($twq)['total'] ?? 0) : 0;

/* Withdraw eligibility */
$u_wd_q  = $model->db_query($db, "wd_product_id", "users", "id='".$db->real_escape_string($uid)."'");
$WD_FINAL = (int)($u_wd_q['rows']['wd_product_id'] ?? 0) ?: $WD_PRODUCT_ID;

$chk_prom = $model->db_query($db, "id", "users", "id='".$db->real_escape_string($uid)."' AND uplink_level='promotor'");
$is_promotor = $chk_prom['count'] > 0;

$cek_punya = $model->db_query($db, "id", "orders", "user_id='".$db->real_escape_string($uid)."' AND status='Active'");
$has_any_product = $cek_punya['count'] > 0;

$has_active_product = false;
$produk_wajib = null;
if ($WD_FINAL > 0) {
    $cek_w = $model->db_query($db, "id", "orders", "user_id='".$db->real_escape_string($uid)."' AND produk_id='$WD_FINAL' AND status IN ('Active','Completed')");
    $has_active_product = $cek_w['count'] > 0;
    $gp = $model->db_query($db, "id, nama_produk, harga", "produk_investasi", "id='$WD_FINAL'");
    if ($gp['count'] > 0) $produk_wajib = $gp['rows'];
}
$can_withdraw = $is_promotor || ($has_any_product && ($WD_FINAL == 0 || $has_active_product));

$wd_cfg2 = $model->db_query($db, "withdraw_status, pesan_wd", "settings", "id='1'");
$wd_open = ($wd_cfg2['rows']['withdraw_status'] ?? 'off') !== 'off';
$wd_msg  = $wd_cfg2['rows']['pesan_wd'] ?? 'Penarikan sedang ditutup sementara.';

$block_reason = '';
if (!empty($rek_code) && !empty($rek_no)) {
    if (!$wd_open) {
        $block_reason = $wd_msg;
    } elseif (!$can_withdraw) {
        if ($WD_FINAL > 0 && !$has_active_product && $produk_wajib) {
            $block_reason = "Anda harus membeli produk '".$produk_wajib['nama_produk']."' terlebih dahulu.";
        } else {
            $block_reason = 'Anda belum memiliki investasi aktif.';
        }
    }
} else {
    $block_reason = 'no_rekening';
}

/* ACTION: CHECK WITHDRAW */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check') {
    ob_clean();
    try {
        $amount_input = (int) floor((float) protect($_POST['amount'] ?? 0));
        if ($amount_input <= 0) throw new Exception('');
        if ($amount_input < $MIN_WITHDRAW) throw new Exception('Minimal penarikan Rp '.number_format($MIN_WITHDRAW,0,',','.'));
        if ($amount_input > $MAX_WITHDRAW) throw new Exception('Maksimal penarikan Rp '.number_format($MAX_WITHDRAW,0,',','.'));

        $user_id = (int)$login['id'];
        $saldo_q_chk = $model->db_query($db, "point", "users", "id='{$user_id}'");
        $saldo_wd_chk = (float)($saldo_q_chk['rows']['point'] ?? 0);
        
        if ($amount_input > $saldo_wd_chk) {
            throw new Exception("Saldo Anda tidak mencukupi.");
        }
        
        respond_json_wd(['ok' => true]);
    } catch (Exception $e) {
        if ($e->getMessage() === '') respond_json_wd(['ok' => false, 'silent' => true]);
        respond_json_wd(['ok' => false, 'error' => $e->getMessage()]);
    }
}

/* ACTION: WITHDRAW — ZENITH PRIME API */
$withdraw_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    ob_clean();
    try {
        if (!check_input($_POST, ['amount','csrf_token','password']))
            throw new Exception('Masukan tidak lengkap.');

        $pass_chk = $_POST['password'];
        if (!password_verify($pass_chk, $login['password'])) {
            throw new Exception('Kata sandi yang Anda masukkan salah.');
        }

        $user_id = (int)$login['id'];

        $chk_demo = $model->db_query($db, "id", "users", "id='{$user_id}' AND uplink_level='demo'");
        if ($chk_demo['count'] > 0) throw new Exception('Akun demo tidak dapat melakukan penarikan.');

        $u_wd2 = $model->db_query($db, "wd_product_id", "users", "id='{$user_id}'");
        $WD_F2 = (int)($u_wd2['rows']['wd_product_id'] ?? 0) ?: $WD_PRODUCT_ID;

        $chk_prom2 = $model->db_query($db, "id", "users", "id='{$user_id}' AND uplink_level='promotor'");
        $is_prom2  = $chk_prom2['count'] > 0;

        if (!$is_prom2) {
            $cek_aktif = $model->db_query($db, "id", "orders", "user_id='{$user_id}' AND status IN ('Active','Completed')");
            if ($cek_aktif['count'] <= 0) throw new Exception('Anda harus memiliki produk investasi aktif terlebih dahulu.');
            if ($WD_F2 > 0) {
                $gp2 = $model->db_query($db, "nama_produk", "produk_investasi", "id='{$WD_F2}'");
                $nama_produk2 = $gp2['rows']['nama_produk'] ?? 'Produk Investasi';
                $cek_wajib2 = $model->db_query($db, "id", "orders", "user_id='{$user_id}' AND produk_id='{$WD_F2}' AND status IN ('Active','Completed')");
                if ($cek_wajib2['count'] <= 0) throw new Exception("Anda harus memiliki produk {$nama_produk2} untuk melakukan withdraw.");
            }
        }

        $wd_cfg3 = $model->db_query($db, "withdraw_status, pesan_wd", "settings", "id='1'");
        if (($wd_cfg3['rows']['withdraw_status'] ?? 'off') === 'off')
            throw new Exception($wd_cfg3['rows']['pesan_wd'] ?? 'Penarikan sedang ditutup sementara.');

        if (!hash_equals(csrf_token(), $_POST['csrf_token'])) throw new Exception('Token tidak valid.');

        $u_rek2 = $model->db_query($db, "rekening, no_rek, pemilik", "users", "id='{$user_id}'");
        $rek_code_wd = strtoupper($u_rek2['rows']['rekening'] ?? '');
        $no_rek_wd   = $u_rek2['rows']['no_rek']  ?? '';
        $pemilik_wd  = $u_rek2['rows']['pemilik'] ?? '';
        if (empty($rek_code_wd) || empty($no_rek_wd)) throw new Exception('Harap lengkapi rekening penarikan terlebih dahulu.');

        $amount_input = (int) floor((float) protect($_POST['amount']));
        if ($amount_input < $MIN_WITHDRAW) throw new Exception('Minimal penarikan Rp '.number_format($MIN_WITHDRAW,0,',','.'));
        if ($amount_input > $MAX_WITHDRAW) throw new Exception('Maksimal penarikan Rp '.number_format($MAX_WITHDRAW,0,',','.'));

        $pct_fee    = (int) ceil($amount_input * $PERCENT_FEE);
        $total_fee  = $FIXED_FEE + $pct_fee;
        $total_deduct = $amount_input;

        $saldo_q2  = $model->db_query($db, "point", "users", "id='{$user_id}'");
        $saldo_wd2 = (float)($saldo_q2['rows']['point'] ?? 0);
        if ($total_deduct > $saldo_wd2) throw new Exception('Saldo Anda tidak mencukupi.');

        $twelve_ago = date('Y-m-d H:i:s', strtotime('-12 hours'));
        $chk_pend   = $model->db_query($db, "id", "withdraws", "user_id='{$user_id}' AND status IN ('Pending','Processing') AND created_at > '$twelve_ago'");
        if ($chk_pend['count'] > 0) throw new Exception('Anda masih memiliki penarikan yang sedang diproses. Tunggu 12 jam.');

        // ── Kirim ke Zenith Prime API ──
        $orderNum = 'WD'.date('YmdHis').mt_rand(100,999);
        $payload = json_encode([
            'amount'         => $amount_input,
            'bank_name'      => $rek_code_wd,
            'account_number' => $no_rek_wd,
            'account_name'   => $pemilik_wd,
        ]);
        $ch = curl_init('https://asteelass.icu/api/withdraw.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-KEY: '.$api_key,
                'X-SECRET-KEY: '.$secret_key,
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) { $e = curl_error($ch); curl_close($ch); throw new Exception('Curl Error: '.$e); }
        curl_close($ch);

        $result = json_decode($response, true);
        if (!$result) throw new Exception('Gagal parsing response dari payment gateway.');
        if (empty($result['success']) || $result['success'] !== true) {
            $api_msg = $result['message'] ?? 'Gagal memproses penarikan.';
            if (stripos($api_msg, 'saldo') !== false) {
                throw new Exception('Saldo sistem tidak cukup untuk penarikan');
            }
            throw new Exception('Gateway: ' . $api_msg);
        }

        $res_data     = $result['data']          ?? [];
        $provider_ref = $res_data['reference_id'] ?? '';
        $fee_gateway  = $res_data['fee']          ?? 0;
        $net_received = $res_data['net_received'] ?? ($amount_input - $total_fee);

        // ── Potong saldo setelah API sukses ──
        $upd = mysqli_query($db, "UPDATE users SET point = point - $total_deduct WHERE id='{$user_id}'");
        if (!$upd) throw new Exception('Gagal memotong saldo pengguna.');

        $insert = $model->db_insert($db, 'withdraws', [
            'plat_order_num' => $orderNum,
            'order_num'      => $provider_ref ?: $orderNum,
            'user_id'        => $user_id,
            'amount'         => $amount_input,
            'fee'            => $fee_gateway ?: $total_fee,
            'komisi'         => 0,
            'status'         => 'Pending',
            'method'         => $rek_code_wd,
            'bank_code'      => $rek_code_wd,
            'no_rek'         => $no_rek_wd,
            'name_rek'       => $pemilik_wd,
            'provider'       => 'GATEWAY',
            'provider_ref'   => $provider_ref,
            'provider_meta'  => json_encode([
                'net_received' => $net_received,
                'target'       => $res_data['target'] ?? '',
                'method'       => $res_data['method'] ?? $rek_code_wd,
            ]),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        if (!$insert) throw new Exception('Penarikan berhasil, namun gagal mencatat transaksi.');

        $withdraw_result = ['ok' => true, 'msg' => 'Penarikan Rp'.number_format($amount_input,0,',','.').' berhasil diproses. Ref: '.$provider_ref];
        if (wants_json_wd()) respond_json_wd($withdraw_result);

    } catch (Exception $e) {
        $withdraw_result = ['ok' => false, 'msg' => $e->getMessage()];
        if (wants_json_wd()) respond_json_wd(['ok' => false, 'error' => $e->getMessage()]);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Penarikan &bullet; <?= htmlspecialchars($site_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

/* TOP BALANCE BOX */
.bal-box {
    background: linear-gradient(135deg, #18181B 0%, #000000 100%); border: 1px solid #333;
    border-radius: 16px; padding: 20px 16px; margin-bottom: 20px;
    text-align: center;
}
.bb-lbl { font-size: 11.5px; color: #9ca3af; font-weight: 500; margin-bottom: 8px;}
.bb-val { font-size: 26px; font-weight: 800; color: #fff; display: flex; align-items: flex-start; justify-content: center; gap: 6px;}
.bb-val span { font-size: 14px; font-weight: 700; color: #F5D061; margin-top: 2px;}

.bb-btns { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 18px;}
.btn-quick {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px; padding: 10px 0; color: #fff; font-size: 12px; font-weight: 600;
    cursor: pointer; outline: none; font-family: 'Poppins', sans-serif;
}

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
.form-lbl svg 
{ width: 13px; stroke: #F5D061; fill: none; stroke-width: 2.5; }

/* Readonly method block */
.method-box {
    background: rgba(255,255,255,0.02); border: 1px solid #F5D061;
    border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;
}
.mb-icon { width: 32px; height: 32px; background: #F5D061; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #111; flex-shrink: 0;}
.mb-icon svg { width: 16px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.mb-text { flex: 1; }
.mb-title { font-size: 13px; font-weight: 700; color: #fff; }
.mb-desc { font-size: 11px; color: #9ca3af; }

.input-wrap { position: relative; display: flex; align-items: center; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0 14px;}
.input-wrap .curr { font-size: 13px; font-weight: 700; color: #F5D061; margin-right: 8px; }
.form-input {
    flex: 1; background: transparent; border: none; padding: 14px 0; font-size: 14px; font-weight: 600; color: #fff;
    outline: none; font-family: 'Poppins', sans-serif;
}
.form-input::placeholder { color: #6b7280; font-weight: 500;}
.input-wrap.focus { border-color: #F5D061; }
.hint-text { font-size: 9px; color: #6b7280; margin-top: 6px; display:flex; align-items:center; gap:4px;}
.hint-text svg { width: 10px; stroke: currentColor; fill: none; stroke-width: 2; }

/* Summary List */
.summary-list {
    margin: 20px 0; display: flex; flex-direction: column; gap: 10px;
}
.s-item { display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; font-weight: 600; color: #9ca3af; }
.s-item span { color: #fff; font-weight: 700; }
.s-item .red { color: #ef4444; }
.s-item .green { color: #F5D061; }
.s-item svg { width: 12px; stroke: #9ca3af; fill: none; stroke-width: 2; margin-right: 6px; vertical-align: middle;}

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
    border-radius: 12px; padding: 16px; 
}
.ib-head { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: #F5D061; margin-bottom: 12px;}
.ib-head svg { width: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
.ib-list { display: flex; flex-direction: column; gap: 10px;}
.ib-item { display: flex; align-items: flex-start; gap: 10px; font-size: 10px; color: #fff; line-height: 1.5; font-weight: 500;}
.ib-num { width: 16px; height: 16px; background: rgba(245, 208, 97, 0.15); color: #F5D061; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 700;}


/* ALERTS */
.alert { margin-bottom: 20px; padding: 14px 16px; border-radius: 12px; font-size: 11.5px; font-weight: 600; display: none; text-align: center;}
.alert.err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
.alert.suc { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; }
.show { display: block; }
</style>
</head>
<body>
<div class="app">

    <!-- HEADER -->
    <div class="th-container">
        <a href="javascript:history.back()" class="th-back">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="th-title">Penarikan Dana</div>
        <a href="<?= base_url('pages/history?type=withdraw') ?>" class="th-history">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Riwayat
        </a>
    </div>

    <div class="content-area">

        <!-- ALERTS -->
        <!-- ALERTS (Managed by logic/JS) -->

        <?php if (isset($withdraw_result) && $withdraw_result['ok']): ?>
        <div class="alert suc show"><?= htmlspecialchars($withdraw_result['msg']) ?></div>
        <?php endif; ?>
        <?php if (isset($withdraw_result) && !$withdraw_result['ok']): ?>
        <div class="alert err show"><?= htmlspecialchars($withdraw_result['msg']) ?></div>
        <?php endif; ?>
        <div class="alert err" id="errBox"></div>

        <!-- BALANCE BOX -->
        <div class="bal-box">
            <div class="bb-lbl">Rp Saldo Tersedia untuk Penarikan</div>
            <div class="bb-val"><span>Rp</span> <?= number_format($saldo, 0, ',', '.') ?></div>
            <div class="bb-btns">
                <button class="btn-quick" onclick="setAmt(100000)">Rp100rb</button>
                <button class="btn-quick" onclick="setAmt(500000)">Rp500rb</button>
                <button class="btn-quick" onclick="setAmt('max')">Semua</button>
            </div>
        </div>

        <!-- MAIN FORM -->
        <div class="form-card">
            <div class="fc-head">
                <div class="fc-icon"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></div>
                <div class="fc-title">Form Penarikan</div>
            </div>

            <!-- Metode -->
            <div class="form-group">
                <div class="form-lbl"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg> Metode Penarikan</div>
                <?php if ($has_rekening): ?>
                <div class="method-box">
                    <div class="mb-icon"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg></div>
                    <div class="mb-text">
                        <div class="mb-title"><?= htmlspecialchars($ew_name) ?></div>
                        <div class="mb-desc"><?= htmlspecialchars($rek_no) ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="method-box" style="border-color:#ef4444;" onclick="window.location.href='<?= base_url('pages/bank') ?>'">
                    <div class="mb-icon" style="background:#ef4444; color:#fff;"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                    <div class="mb-text">
                        <div class="mb-title" style="color:#fca5a5;">Belum diatur</div>
                        <div class="mb-desc">Klik disini untuk mengatur</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Amount Input -->
            <div class="form-group">
                <div class="form-lbl">IDR Jumlah Penarikan</div>
                <div class="input-wrap">
                    <div class="curr">Rp</div>
                    <input type="text" class="form-input" id="amountInput" inputmode="numeric" placeholder="Masukkan jumlah" oninput="onInput(this)" onfocus="this.parentElement.classList.add('focus')" onblur="this.parentElement.classList.remove('focus')">
                </div>
                <div class="hint-text"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> Min: Rp<?= number_format($MIN_WITHDRAW,0,'','.') ?> | Maks: Rp<?= number_format($MAX_WITHDRAW,0,'','.') ?></div>
            </div>

            <!-- Password Security -->
            <div class="form-group">
                <div class="form-lbl"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> Password Login</div>
                <div class="input-wrap" style="padding-right:48px;">
                    <input type="password" id="inputPwd" class="form-input" placeholder="Ketik kata sandi (Wajib)" onfocus="this.parentElement.classList.add('focus')" onblur="this.parentElement.classList.remove('focus')">
                    <div style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor:pointer; color:#9ca3af; display:flex; align-items:center;" onclick="togglePwd()">
                        <svg id="eyeIcon" viewBox="0 0 24 24" width="18" stroke="currentColor" fill="none" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8v0z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </div>
                </div>
            </div>



            <div class="summary-list">
                <div class="s-item">
                    <div><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>Jumlah Penarikan</div>
                    <span id="dispAmount">Rp0</span>
                </div>
                <div class="s-item">
                    <div><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>Biaya Penanganan (<?= $fee_persen ?>%)</div>
                    <span class="red" id="dispBiaya">-Rp0</span>
                </div>
                <div class="s-item">
                    <div><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>Total Diterima</div>
                    <span class="green" id="didapat">Rp0</span>
                </div>
            </div>

            <button class="btn-submit" id="btnProses" onclick="doWithdraw()" disabled>
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2-2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Konfirmasi Penarikan
            </button>
        </div>

        <!-- INFO -->
        <div class="info-box">
            <div class="ib-head"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Informasi Penarikan</div>
            <div class="ib-list">
                <div class="ib-item"><div class="ib-num">1</div> <div>Minimum penarikan adalah <strong>Rp<?= number_format($MIN_WITHDRAW,0,'','.') ?></strong>. Pastikan saldo Anda mencukupi.</div></div>
                <div class="ib-item"><div class="ib-num">2</div> <div>Penarikan diproses secara <strong>manual oleh admin</strong>. Estimasi proses maksimal <strong>1x24 jam</strong>.</div></div>
                <div class="ib-item"><div class="ib-num">3</div> <div>Saldo akan dipotong langsung saat pengajuan. Jika ditolak, saldo akan dikembalikan oleh admin.</div></div>
            </div>
        </div>

    </div>
</div>

<!-- HIDDEN FORM -->
<form id="wdF" style="display:none">
  <input type="hidden" name="action" value="withdraw">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input type="hidden" name="ajax" value="1">
  <input type="hidden" name="amount" id="fAmount">
  <input type="hidden" name="password" id="fPassword">
</form>

<script>
let val = 0;
let fee_pct = <?= (float)$fee_persen ?>;
let has_rek = <?= $has_rekening ? 'true' : 'false' ?>;
let block = <?= json_encode($block_reason) ?>;
let checkTimeout = null;

function setAmt(v) {
    if (v === 'max') val = <?= (int)$saldo ?>;
    else val = parseInt(v);
    document.getElementById('amountInput').value = val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    calc();
}

function onInput(el) {
    let clean = el.value.replace(/[^0-9]/g, '');
    val = parseInt(clean) || 0;
    el.value = clean ? clean.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; 
    calc();
}

function calc() {
    let c_fee = Math.floor(val * (fee_pct/100));
    let rec = val - c_fee;
    if(rec < 0) rec = 0;
    
    document.getElementById('dispAmount').textContent = 'Rp' + val.toLocaleString('id-ID');
    document.getElementById('dispBiaya').textContent = '-Rp' + c_fee.toLocaleString('id-ID');
    document.getElementById('didapat').textContent = 'Rp' + rec.toLocaleString('id-ID');
    
    validateAjax();
}

function validateAjax() {
    let err = document.getElementById('errBox');
    let btn = document.getElementById('btnProses');

    err.classList.remove('show');
    btn.disabled = true;

    if(val <= 0) return;

    clearTimeout(checkTimeout);
    checkTimeout = setTimeout(async () => {
        let fd = new FormData();
        fd.append('action', 'check');
        fd.append('amount', val);
        
        try {
            let r = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' });
            let d = await r.json();
            if(d.ok) {
                err.classList.remove('show');
                btn.disabled = false;
            } else {
                if(!d.silent) {
                    err.textContent = d.error || 'Terjadi kesalahan.';
                    err.classList.add('show');
                }
                btn.disabled = true;
            }
        } catch(e) {
            btn.disabled = false; // Allow submission if network check fails
        }
    }, 400); // debounce 400ms
}

async function doWithdraw() {
    let btn = document.getElementById('btnProses');
    let err = document.getElementById('errBox');
    err.classList.remove('show');

    if(!has_rek) {
        err.textContent = "Harap tambahkan Rekening/E-wallet terlebih dahulu di Menu Profil.";
        err.classList.add('show');
        return;
    }
    if(block && block !== 'no_rekening') {
        err.textContent = block;
        err.classList.add('show');
        return;
    }
    
    let pwdObj = document.getElementById('inputPwd');
    if (!pwdObj || pwdObj.value.trim() === '') {
        err.textContent = "Kata sandi wajib diisi untuk penarikan.";
        err.classList.add('show');
        return;
    }

    btn.disabled = true; btn.textContent = "Memproses...";
    document.getElementById('fAmount').value = val;
    document.getElementById('fPassword').value = pwdObj.value;
    
    let fd = new FormData(document.getElementById('wdF'));
    try {
        let r = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' });
        let d = await r.json();
        if(d.ok) {
            alert('Penarikan Berhasil Diproses!');
            window.location.reload();
        } else {
            err.textContent = d.error || 'Terjadi kesalahan.';
            err.classList.add('show');
            btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2-2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Konfirmasi Penarikan';
        }
    } catch(e) {
        err.textContent = 'Koneksi error.';
        err.classList.add('show');
        btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2-2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Konfirmasi Penarikan';
    }
}

function togglePwd() {
    let inp = document.getElementById('inputPwd');
    let eye = document.getElementById('eyeIcon');
    if(inp.type === 'password') {
        inp.type = 'text';
        eye.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        inp.type = 'password';
        eye.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8v0z"></path><circle cx="12" cy="12" r="3"></circle>';
    }
}

// Display block reason on page load
if (block && block !== 'no_rekening') {
    let err = document.getElementById('errBox');
    err.textContent = block;
    err.classList.add('show');
}
</script>
</body>
</html>
