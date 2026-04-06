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
body { font-family: 'Poppins', sans-serif; background: #012b26; color: #fff; -webkit-font-smoothing: antialiased; }
.app { max-width: 480px; margin: 0 auto; min-height: 100vh; position: relative; background: #012b26; padding-bottom: 90px; }

/* HEADER */
.h-bg { background: linear-gradient(135deg, #023e35 0%, #01312b 100%); padding: 25px 20px 100px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; position: relative; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1; }
.h-nav { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
.back-btn { width: 36px; height: 36px; border-radius: 12px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
.back-btn:active { background: rgba(255,255,255,0.1); }
.h-title { display: flex; flex-direction: column; }
.h-title h3 { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 2px; }
.h-title p { font-size: 10px; font-weight: 500; color: rgba(255,255,255,0.7); }

/* TOP INPUT CARD */
.ti-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(250,204,21,0.25); border-radius: 16px; padding: 20px 16px; display: flex; flex-direction: column; }
.ti-card label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.8); margin-bottom: 12px;}
.ti-wrap { display: flex; align-items: center; border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 8px; margin-bottom: 12px; transition: 0.2s; }
.ti-wrap.focus { border-color: #facc15; }
.ti-wrap span { font-size: 24px; font-weight: 800; color: rgba(255,255,255,0.5); margin-right: 12px; }
.ti-input { flex: 1; min-width: 0; background: none; border: none; font-size: 36px; font-weight: 800; color: #fff; outline: none; font-family: 'Poppins', sans-serif;}
.ti-input::placeholder { color: rgba(255,255,255,0.1); }
.ti-card small { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 500; }

.c-body { padding: 0 20px; margin-top: -65px; position: relative; z-index: 2; }
.w-card { background: #023e35; border-radius: 20px; padding: 20px; margin-bottom: 16px; border: 1px solid #035246; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.wc-title { font-size: 12px; font-weight: 800; color: #fff; margin-bottom: 14px; }

/* PILLS */
.quick-pills { display: flex; flex-wrap: wrap; gap: 8px; }
.qp-btn { flex: 1; min-width: 60px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 12px; padding: 12px 0; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: 'Poppins', sans-serif; text-align: center; }
.qp-btn:active, .qp-btn.active { background: rgba(250, 204, 21, 0.15); border-color: #facc15; color: #facc15; }

/* PAYMENT METHOD */
.pm-card { background: rgba(250, 204, 21, 0.05); border: 1px solid #facc15; border-radius: 14px; padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 14px; cursor: pointer;}
.pm-left { display: flex; align-items: center; gap: 12px; }
.pm-logo { background: #fff; padding: 6px; border-radius: 8px; display:flex; align-items:center; justify-content:center; }
.pm-logo img { height: 18px; object-fit: contain;}
.pm-name { font-size: 14px; font-weight: 800; color: #fff; }
.pm-radio { width: 20px; height: 20px; border-radius: 50%; border: 2px solid #facc15; display: flex; align-items: center; justify-content: center; color: #facc15;}
.pm-radio i { font-size: 10px; }

/* WARNING BOX */
.warn-box { background: rgba(250, 204, 21, 0.05); border: 1px solid rgba(250, 204, 21, 0.2); border-radius: 16px; padding: 16px; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px;}
.wb-icon { width: 24px; height: 24px; border-radius: 50%; background: #facc15; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #012b26; flex-shrink: 0; }
.wb-text { flex: 1; }
.wb-title { font-size: 12px; font-weight: 800; color: #fff; margin-bottom: 8px; }
.wb-list { padding-left: 14px; margin: 0;}
.wb-list li { font-size: 10.5px; color: rgba(255,255,255,0.7); margin-bottom: 6px; font-weight: 500; line-height: 1.4;}
.wb-list li:last-child { margin-bottom: 0; }

/* ACTIONS */
.fixed-btn-wrap { position: fixed; bottom: 0; left: 0; right: 0; padding: 16px 20px; background: rgba(1, 43, 38, 0.9); backdrop-filter: blur(10px); display: flex; justify-content: center; z-index: 100;}
.fixed-btn-wrap .app-cont { width: 100%; max-width: 440px; }
.btn-primary { width: 100%; background: #facc15; color: #012b26; border: none; padding: 16px; font-size: 14px; font-weight: 800; border-radius: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 15px rgba(250, 204, 21, 0.25); font-family: 'Poppins', sans-serif; transition: 0.2s;}
.btn-primary:active { transform: scale(0.96); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.alert { margin-bottom: 16px; padding: 14px 16px; border-radius: 12px; font-size: 11px; font-weight: 600; display: none; text-align: center;}
.alert.err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
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
                <h3>Top Up Saldo</h3>
                <p>Isi saldo untuk mulai investasi</p>
            </div>
        </div>
        <div class="ti-card">
            <label>Jumlah Top Up</label>
            <div class="ti-wrap">
                <span>Rp</span>
                <input type="text" class="ti-input" id="amountInput" inputmode="numeric" placeholder="0" oninput="onInput(this)" onfocus="this.parentElement.classList.add('focus')" onblur="this.parentElement.classList.remove('focus')">
            </div>
            <small>Minimal Rp <?= number_format($MIN_DEPOSIT,0,',','.') ?></small>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="c-body">

        <div class="alert err" id="errBox"></div>

        <div class="w-card">
            <h4 class="wc-title">Pilih Nominal</h4>
            <div class="quick-pills">
                <button class="qp-btn" onclick="setAmt(50000, this)">50rb</button>
                <button class="qp-btn" onclick="setAmt(100000, this)">100rb</button>
                <button class="qp-btn" onclick="setAmt(500000, this)">500rb</button>
                <button class="qp-btn" onclick="setAmt(1000000, this)">1jt</button>
                <button class="qp-btn" onclick="setAmt(5000000, this)">5jt</button>
            </div>
        </div>

        <div class="w-card" style="padding-bottom:10px;">
            <h4 class="wc-title">Metode Pembayaran</h4>
            
            <div style="font-size:10px; font-weight:700; color:rgba(255,255,255,0.5); letter-spacing:0.5px; margin-bottom:8px;">INSTANT PAYMENT</div>
            <!-- METHOD QRIS ONLY -->
            <div class="pm-card">
                <div class="pm-left">
                    <div class="pm-logo"><img src="https://upload.wikimedia.org/wikipedia/commons/a/a2/Logo_QRIS.svg" alt="QRIS"></div>
                    <div class="pm-name">QRIS</div>
                </div>
                <div class="pm-radio"><i class="fa-solid fa-circle"></i></div>
            </div>
        </div>

        <div class="warn-box">
            <div class="wb-icon"><i class="fa-solid fa-info"></i></div>
            <div class="wb-text">
                <div class="wb-title">Informasi</div>
                <ul class="wb-list">
                    <li>Top up diproses dalam 1-5 menit</li>
                    <li>Simpan bukti scan QRIS sebagai referensi</li>
                    <li>Jika saldo belum masuk, hubungi Customer Service</li>
                </ul>
            </div>
        </div>

    </div>
    
    <!-- FIXED BUTTON SUMMARY -->
    <div class="fixed-btn-wrap">
        <div class="app-cont">
            <button class="btn-primary" id="btnProses" onclick="doDeposit()" disabled>
                <i class="fa-solid fa-wallet"></i> Lanjutkan Pembayaran
            </button>
        </div>
    </div>
</div>

<!-- HIDDEN FORM -->
<form id="depF" style="display:none">
  <input type="hidden" name="action" value="deposit">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input type="hidden" name="ajax" value="1">
  <input type="hidden" name="amount" id="fAmount">
  <input type="hidden" name="method" value="QRIS">
</form>

<script>
let val = 0;
let min_val = <?= (int)$MIN_DEPOSIT ?>;
let max_val = <?= (int)$MAX_DEPOSIT ?>;

function setAmt(v, el) {
    document.querySelectorAll('.qp-btn').forEach(b => b.classList.remove('active'));
    if(el) el.classList.add('active');
    val = v;
    document.getElementById('amountInput').value = val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    validate();
}

function onInput(el) {
    let clean = el.value.replace(/[^0-9]/g, '');
    val = parseInt(clean) || 0;
    el.value = clean ? clean.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") : ""; 
    document.querySelectorAll('.qp-btn').forEach(b => b.classList.remove('active'));
    validate();
}

function validate() {
    let err = document.getElementById('errBox');
    let btn = document.getElementById('btnProses');
    
    err.classList.remove('show');
    
    if(val > 0 && val < min_val) {
        err.textContent = "Minimal deposit Rp " + min_val.toLocaleString('id-ID');
        err.classList.add('show');
        btn.disabled = true;
    } else if(val > max_val) {
        err.textContent = "Maksimal deposit Rp " + max_val.toLocaleString('id-ID');
        err.classList.add('show');
        btn.disabled = true;
    } else {
        btn.disabled = !(val >= min_val);
    }
}

async function doDeposit() {
    let btn = document.getElementById('btnProses');
    btn.disabled = true; 
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
    
    document.getElementById('fAmount').value = val;
    
    let fd = new FormData(document.getElementById('depF'));
    try {
        let r = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' });
        let d = await r.json();
        if(d.ok && d.redirect) {
            window.location.href = d.redirect;
        } else {
            document.getElementById('errBox').textContent = d.error || 'Terjadi kesalahan gateway.';
            document.getElementById('errBox').classList.add('show');
            btn.disabled = false; 
            btn.innerHTML = '<i class="fa-solid fa-wallet"></i> Lanjutkan Pembayaran';
            window.scrollTo({top:0, behavior:'smooth'});
        }
    } catch(e) {
        document.getElementById('errBox').textContent = 'Koneksi error ke server.';
        document.getElementById('errBox').classList.add('show');
        btn.disabled = false; 
        btn.innerHTML = '<i class="fa-solid fa-wallet"></i> Lanjutkan Pembayaran';
        window.scrollTo({top:0, behavior:'smooth'});
    }
}
</script>
</body>
</html>
