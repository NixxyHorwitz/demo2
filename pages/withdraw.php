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

.h-card { background: transparent; border: 1px solid rgba(250,204,21,0.25); border-radius: 16px; padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.hc-left { display: flex; align-items: center; gap: 14px; }
.hc-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(250,204,21,0.1); display: flex; align-items: center; justify-content: center; color: #facc15; font-size: 18px; }
.hc-texts { display: flex; flex-direction: column; }
.hc-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;}
.hc-val { font-size: 18px; font-weight: 800; color: #fff; line-height: 1; }

.c-body { padding: 0 20px; margin-top: -65px; position: relative; z-index: 2; }
.w-card { background: #023e35; border-radius: 20px; padding: 20px; margin-bottom: 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.wc-title { font-size: 12px; font-weight: 800; color: #fff; margin-bottom: 14px; }

/* INPUT */
.input-wrap { display: flex; align-items: center; background: #012b26; border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; transition: 0.2s;}
.input-wrap.focus { border-color: #facc15; }
.iw-curr { font-size: 14px; font-weight: 700; color: rgba(255,255,255,0.5); margin-right: 12px; }
.iw-input { flex: 1; min-width: 0; background: none; border: none; font-size: 18px; font-weight: 800; color: #fff; outline: none; font-family: 'Poppins', sans-serif;}
.iw-input::placeholder { color: rgba(255,255,255,0.2); }
.wc-min { font-size: 10px; color: rgba(255,255,255,0.5); margin-bottom: 16px; font-weight: 500;}

/* PILLS */
.quick-pills { display: flex; flex-wrap: wrap; gap: 8px; }
.qp-btn { flex: 1; min-width: 45px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 10px; padding: 10px 0; font-size: 11px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: 'Poppins', sans-serif; text-align: center; }
.qp-btn:active { background: rgba(255,255,255,0.1); }

/* REK BOX */
.rek-box { background: rgba(250, 204, 21, 0.05); border: 1px solid rgba(250, 204, 21, 0.2); border-radius: 14px; padding: 14px; display: flex; align-items: center; gap: 14px; margin-bottom: 12px; cursor: pointer;}
.rb-icon { width: 44px; height: 44px; border-radius: 12px; background: #012b26; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #facc15; }
.rb-info { flex: 1; display:flex; flex-direction:column; }
.rb-info h5 { font-size: 13px; font-weight: 800; color: #fff; margin-bottom: 2px;}
.rb-info p { font-size: 11px; color: rgba(255,255,255,0.7); font-weight: 500; margin-bottom: 2px;}
.rb-info small { font-size: 10px; color: rgba(255,255,255,0.4); font-weight: 500;}
.rb-arrow { width: 32px; height: 32px; border-radius: 10px; background: rgba(250, 204, 21, 0.1); color: #facc15; display: flex; align-items: center; justify-content: center; font-size: 12px; }

.wc-link { font-size: 11px; font-weight: 700; color: #facc15; text-decoration: none; }

/* SUMMARY & PASSWORD */
.s-item { display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.7); padding: 8px 0; border-bottom: 1px dashed rgba(255,255,255,0.05); }
.s-item:last-child { border-bottom: none; }
.s-item span { color: #fff; font-weight: 800; }
.s-item .red { color: #f87171; }
.s-item .green { color: #facc15; }

/* WARNING BOX */
.warn-box { background: rgba(250, 204, 21, 0.05); border: 1px solid rgba(250, 204, 21, 0.2); border-radius: 16px; padding: 16px; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px;}
.wb-icon { width: 24px; height: 24px; border-radius: 50%; background: #facc15; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #012b26; flex-shrink: 0; }
.wb-text { flex: 1; }
.wb-title { font-size: 12px; font-weight: 800; color: #fff; margin-bottom: 6px; }
.wb-list { padding-left: 14px; margin: 0;}
.wb-list li { font-size: 10px; color: rgba(255,255,255,0.6); margin-bottom: 6px; font-weight: 500; line-height: 1.4;}
.wb-list li:last-child { margin-bottom: 0; }

/* ACTIONS */
.f-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;}
.btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 14px; font-size: 13px; font-weight: 800; border-radius: 14px; cursor: pointer; text-align: center; font-family: 'Poppins', sans-serif;}
.btn-primary { background: #facc15; color: #012b26; border: none; padding: 14px; font-size: 13px; font-weight: 800; border-radius: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(250, 204, 21, 0.2); font-family: 'Poppins', sans-serif; transition: 0.2s;}
.btn-primary:active { transform: scale(0.96); }

.alert { margin-bottom: 16px; padding: 14px 16px; border-radius: 12px; font-size: 11px; font-weight: 600; display: none; text-align: center;}
.alert.err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; display:block; }
.alert.suc { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; display:block; }
#errBox { display:none; }
#errBox.show { display:block; }
</style>
</head>
<body>
<div class="app">

    <!-- HEADER CURVED -->
    <div class="h-bg">
        <div class="h-nav">
            <a href="javascript:history.back()" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
            <div class="h-title">
                <h3>Penarikan dana</h3>
                <p>Tarik saldo penarikan ke rekening terdaftar</p>
            </div>
        </div>
        <div class="h-card">
            <div class="hc-left">
                <div class="hc-icon"><i class="fa-solid fa-wallet"></i></div>
                <div class="hc-texts">
                    <span class="hc-lbl">SALDO BISA DITARIK</span>
                    <h4 class="hc-val">Rp <?= number_format($saldo, 0, ',', '.') ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="c-body">

        <?php if (isset($withdraw_result) && $withdraw_result['ok']): ?>
        <div class="alert suc show"><?= htmlspecialchars($withdraw_result['msg']) ?></div>
        <?php endif; ?>
        <?php if (isset($withdraw_result) && !$withdraw_result['ok']): ?>
        <div class="alert err show"><?= htmlspecialchars($withdraw_result['msg']) ?></div>
        <?php endif; ?>
        <div class="alert err" id="errBox"></div>

        <div class="w-card">
            <h4 class="wc-title">Jumlah penarikan</h4>
            <div class="input-wrap">
                <span class="iw-curr">Rp</span>
                <input type="text" class="iw-input" id="amountInput" inputmode="numeric" placeholder="0" oninput="onInput(this)" onfocus="this.parentElement.classList.add('focus')" onblur="this.parentElement.classList.remove('focus')">
            </div>
            <div class="wc-min">Minimum Rp <?= number_format($MIN_WITHDRAW,0,',','.') ?></div>
            
            <div class="quick-pills">
                <button class="qp-btn" onclick="setAmt(30000)">30rb</button>
                <button class="qp-btn" onclick="setAmt(50000)">50rb</button>
                <button class="qp-btn" onclick="setAmt(100000)">100rb</button>
                <button class="qp-btn" onclick="setAmt(500000)">500rb</button>
                <button class="qp-btn" onclick="setAmt(1000000)">1jt</button>
            </div>
        </div>

        <div class="w-card">
            <h4 class="wc-title">Rekening tujuan</h4>
            <?php if($has_rekening): ?>
            <div class="rek-box" onclick="window.location.href='<?= base_url('pages/bank') ?>'">
                <div class="rb-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div class="rb-info">
                    <h5><?= htmlspecialchars($ew_name) ?></h5>
                    <p><?= htmlspecialchars($rek_no) ?></p>
                    <small><?= htmlspecialchars($rek_pemilik) ?></small>
                </div>
                <div class="rb-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            </div>
            <a href="<?= base_url('pages/bank') ?>" class="wc-link">Kelola rekening</a>
            <?php else: ?>
            <div class="rek-box" style="border-color:#ef4444;" onclick="window.location.href='<?= base_url('pages/bank') ?>'">
                <div class="rb-icon" style="background:#ef4444; color:#fff;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="rb-info">
                    <h5 style="color:#fca5a5;">Belum Diatur</h5>
                    <p>Klik disini mengatur bank tujuan</p>
                </div>
                <div class="rb-arrow" style="background:transparent; color:#ef4444;"><i class="fa-solid fa-arrow-right"></i></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="w-card" style="padding-bottom:10px;">
            <h4 class="wc-title">Konfirmasi keamanan</h4>
            
            <div class="input-wrap" style="position:relative; margin-bottom:14px;">
                <input type="password" id="inputPwd" class="iw-input" placeholder="Kata sandi login Anda" style="font-size:13px;" onfocus="this.parentElement.classList.add('focus')" onblur="this.parentElement.classList.remove('focus')">
                <div style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor:pointer; color:rgba(255,255,255,0.4);" onclick="togglePwd()">
                    <i class="fa-regular fa-eye" id="eyeIcon"></i>
                </div>
            </div>
            
            <div class="s-item">
                <div>Jumlah Penarikan</div>
                <span id="dispAmount">Rp0</span>
            </div>
            <div class="s-item">
                <div>Biaya Penanganan (<?= $fee_persen ?>%)</div>
                <span class="red" id="dispBiaya">-Rp0</span>
            </div>
            <div class="s-item" style="border-bottom:none;">
                <div>Total Diterima</div>
                <span class="green" id="didapat">Rp0</span>
            </div>
        </div>

        <div class="warn-box">
            <div class="wb-icon"><i class="fa-solid fa-info"></i></div>
            <div class="wb-text">
                <div class="wb-title">Informasi</div>
                <ul class="wb-list">
                    <li>Biaya admin <?= $fee_persen ?>% + Rp <?= number_format($FIXED_FEE,0,',','.') ?> per transaksi</li>
                    <li>Proses pencairan biasanya 1&ndash;5 menit</li>
                    <li>Hanya ke rekening yang terdaftar di profil</li>
                </ul>
            </div>
        </div>

        <div class="f-actions">
            <button class="btn-outline" onclick="window.location.href='<?= base_url('pages/profile') ?>'">Batal</button>
            <button class="btn-primary" id="btnProses" onclick="doWithdraw()">
                <i class="fa-solid fa-money-bill-transfer"></i> Tarik dana
            </button>
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
let fee_fixed = <?= (int)$FIXED_FEE ?>;
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
    let pct_fee = Math.floor(val * (fee_pct/100));
    let c_fee = fee_fixed + pct_fee;
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
    btn.style.opacity = '0.5';

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
                btn.style.opacity = '1';
            } else {
                if(!d.silent) {
                    err.textContent = d.error || 'Terjadi kesalahan.';
                    err.classList.add('show');
                }
                btn.disabled = true;
            }
        } catch(e) {
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }, 400); // debounce 400ms
}

async function doWithdraw() {
    let btn = document.getElementById('btnProses');
    let err = document.getElementById('errBox');
    err.classList.remove('show');

    if(!has_rek) {
        err.textContent = "Harap tambahkan Rekening/E-wallet terlebih dahulu di Kelola Rekening.";
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

    btn.disabled = true; btn.style.opacity = '0.7'; 
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
    
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
            btn.disabled = false; btn.style.opacity = '1';
            btn.innerHTML = '<i class="fa-solid fa-money-bill-transfer"></i> Tarik dana';
        }
    } catch(e) {
        err.textContent = 'Koneksi error.';
        err.classList.add('show');
        btn.disabled = false; btn.style.opacity = '1';
        btn.innerHTML = '<i class="fa-solid fa-money-bill-transfer"></i> Tarik dana';
    }
}

function togglePwd() {
    let inp = document.getElementById('inputPwd');
    let eye = document.getElementById('eyeIcon');
    if(inp.type === 'password') {
        inp.type = 'text';
        eye.className = 'fa-regular fa-eye-slash';
    } else {
        inp.type = 'password';
        eye.className = 'fa-regular fa-eye';
    }
}

// Display block reason on page load
if (block && block !== 'no_rekening') {
    let err = document.getElementById('errBox');
    err.textContent = block;
    err.classList.add('show');
}
</script>
<?php require '../lib/footer_user.php'; ?>
</body>
</html>t>
</body>
</html>
