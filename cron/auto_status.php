<?php
/*
|--------------------------------------------------------------------------
| AUTO KIRA CHECKER (HOME TRIGGER)
|--------------------------------------------------------------------------
*/

if (!defined('MAIN_CONFIG_LOADED')) {
    require_once __DIR__ . '/../mainconfig.php';
}

date_default_timezone_set("Asia/Jakarta");

$INTERVAL = 30;
$now = time();

/*
|--------------------------------------------------------------------------
| AUTO CREATE LOCK
|--------------------------------------------------------------------------
*/
$db->query("
    INSERT IGNORE INTO auto_cron (name,value)
    VALUES ('kira_last_run','0')
");

/*
|--------------------------------------------------------------------------
| AMBIL LAST RUN
|--------------------------------------------------------------------------
*/
$get_lock = $db->query("
    SELECT value
    FROM auto_cron
    WHERE name='kira_last_run'
    LIMIT 1
");

if(!$get_lock || $get_lock->num_rows==0){
    return;
}

$data = $get_lock->fetch_assoc();
$last_run = (int)$data['value'];

if (($now - $last_run) < $INTERVAL) {
    return;
}

/*
|--------------------------------------------------------------------------
| LOCK ANTI DOUBLE RUN
|--------------------------------------------------------------------------
*/
$db->begin_transaction();

$db->query("
    UPDATE auto_cron
    SET value='$now'
    WHERE name='kira_last_run'
    AND value='$last_run'
");

if ($db->affected_rows === 0) {
    $db->rollback();
    return;
}

$db->commit();

/*
|--------------------------------------------------------------------------
| CONFIG API
|--------------------------------------------------------------------------
*/
$GATEWAY_URL = 'https://asteelass.icu/api/status.php';

$_credQ  = $db->query("SELECT api_key, secret_key FROM settings WHERE id='1' LIMIT 1");
$_cred   = $_credQ ? $_credQ->fetch_assoc() : [];

$API_KEY    = $_cred['api_key']    ?? '';
$SECRET_KEY = $_cred['secret_key'] ?? '';

/*
|--------------------------------------------------------------------------
| REQUEST HELPER
|--------------------------------------------------------------------------
*/
function gateway(string $reference_id, string $url, string $api_key, string $secret_key): array|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['reference_id' => $reference_id]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-KEY: '.$api_key,
            'X-SECRET-KEY: '.$secret_key,
        ],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return false;

    $json = json_decode($response, true);
    // Response: {"status":true,"data":{"current_status":"pending","created_at":"..."}}
    if (!is_array($json) || empty($json['status']) || $json['status'] !== true) return false;

    return $json;
}

/*
|--------------------------------------------------------------------------
| LOAD REFERRAL HELPER
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../lib/referral_commission.php';

/* =====================================================
   CEK DEPOSIT
===================================================== */

$topups=$db->query("
    SELECT * FROM topups
    WHERE status='Pending'
    ORDER BY id ASC
    LIMIT 50
");

while($topups && $topup=$topups->fetch_assoc()){

    if (($topup['provider'] ?? '') !== 'GATEWAY') continue;
    $trx = $topup['trxtopup'];
    $pref = $topup['provider_ref'];
    if (empty($pref)) continue;

    $res = gateway($pref, $GATEWAY_URL, $API_KEY, $SECRET_KEY);
    if (!$res) continue;

    $status = strtolower($res['data']['status'] ?? '');

    $db->begin_transaction();
    $lock=$db->query("SELECT * FROM topups WHERE trxtopup='".$db->real_escape_string($trx)."' FOR UPDATE");

    if(!$lock || $lock->num_rows!=1){
        $db->rollback();
        continue;
    }

    $current=$lock->fetch_assoc();
    if(in_array($current['status'],['Success','Failed','Expired'])){
        $db->rollback();
        continue;
    }

    $user_id=(int)$current['user_id'];
    $amount =(float)$current['amount'];

    if(in_array($status,['success','paid','settlement'])){
        $db->query("UPDATE topups SET status='Success', note='Deposit sukses (Auto Check)', paid_at=NOW(), updated_at=NOW() WHERE trxtopup='".$db->real_escape_string($trx)."'");
        $db->query("UPDATE users SET saldo=saldo+$amount WHERE id='$user_id'");
        $db->query("INSERT INTO point_logs (user_id,type,amount,description,created_at) VALUES ('$user_id','plus','$amount','Deposit #$trx',NOW())");

        $settingsQ=$db->query("SELECT * FROM settings WHERE id='1' LIMIT 1");
        $settings=$settingsQ?$settingsQ->fetch_assoc():[];
        processReferralCommission($db,$settings,$user_id,$amount,$trx);
    } elseif(in_array($status,['failed','expired','cancelled'])) {
        $db->query("UPDATE topups SET status='Expired', note='Deposit gagal (Auto Check)', updated_at=NOW() WHERE trxtopup='".$db->real_escape_string($trx)."'");
    }
    $db->commit();
}

/* =====================================================
   CEK WITHDRAW
===================================================== */

$withdraws=$db->query("
    SELECT * FROM withdraws
    WHERE status='Pending'
    ORDER BY id ASC
    LIMIT 20
");

while($withdraws && $wd=$withdraws->fetch_assoc()){

    if (($wd['provider'] ?? '') !== 'GATEWAY') continue;
    $trx = $wd['provider_ref'];
    if (empty($trx)) continue;

    $res = gateway($trx, $GATEWAY_URL, $API_KEY, $SECRET_KEY);
    if (!$res) continue;

    $status = strtolower($res['data']['status'] ?? '');

    $db->begin_transaction();
    $lock=$db->query("SELECT * FROM withdraws WHERE provider_ref='".$db->real_escape_string($trx)."' FOR UPDATE");

    if(!$lock || $lock->num_rows!=1){
        $db->rollback();
        continue;
    }

    $current=$lock->fetch_assoc();
    if(in_array($current['status'],['Success','Failed'])){
        $db->rollback();
        continue;
    }

    $user_id=(int)$current['user_id'];
    $amount=(float)$current['amount'];

    if($status==='success'){
        $db->query("UPDATE withdraws SET status='Success', updated_at=NOW() WHERE provider_ref='".$db->real_escape_string($trx)."'");
    } elseif(in_array($status,['failed','cancelled','expired'])){
        $db->query("UPDATE withdraws SET status='Failed', updated_at=NOW() WHERE provider_ref='".$db->real_escape_string($trx)."'");
        $db->query("UPDATE users SET point=point+$amount WHERE id='$user_id'");
        $db->query("INSERT INTO point_logs (user_id,type,amount,description,created_at) VALUES ('$user_id','plus','$amount','Refund Withdraw #$trx',NOW())");
    }
    $db->commit();
}