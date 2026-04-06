<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../mainconfig.php';
require_once __DIR__ . '/../lib/check_session.php';
require_once __DIR__ . '/../lib/is_login.php';

// Ekstraksi keamanan: Hanya user dengan uplink_level promotor atau demo yang bisa akses
if (!isset($login['uplink_level']) || !in_array((string)$login['uplink_level'], ['promotor', 'demo'])) {
    exit(header("Location: " . base_url('pages/history')));
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount    = (float)($_POST['amount'] ?? 0);
    $rek_code  = trim($_POST['rek_code'] ?? 'DANA');
    $rek_no    = trim($_POST['rek_no'] ?? '080000000000');
    $rek_owner = trim($_POST['rek_owner'] ?? 'Faker Account');
    $status    = trim($_POST['status'] ?? 'Success');
    $created_at= trim($_POST['created_at'] ?? date('Y-m-d H:i:s'));
    
    if ($amount > 0) {
        $trxDatePart = date('YmdHis', strtotime($created_at));
        $trxNum = 'WD' . $trxDatePart . mt_rand(100, 999);
        
        $ins = $model->db_insert($db, 'withdraws', [
            'plat_order_num'=> $trxNum,
            'order_num'     => $trxNum,
            'user_id'       => $login['id'],
            'amount'        => $amount,
            'fee'           => 0,
            'komisi'        => 0,
            'status'        => $status,
            'method'        => $rek_code,
            'bank_code'     => $rek_code,
            'no_rek'        => $rek_no,
            'name_rek'      => $rek_owner,
            'description'   => 'WD FAKE',
            'created_at'    => $created_at
        ]);
        
        if ($ins) {
            $msg = "<div style='color:#15803d; background:#dcfce7; border:1px dashed #22c55e; padding:10px; font-weight:bold; margin-bottom:15px; border-radius:6px;'>Withdraw Faker Berhasil Ditambahkan!<br>TRX: $trxNum</div>";
        } else {
            $msg = "<div style='color:#b91c1c; background:#fee2e2; border:1px dashed #ef4444; padding:10px; font-weight:bold; margin-bottom:15px; border-radius:6px;'>Gagal menambahkan data ke Database!</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<title>Menu Faker Withdraw</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* CSS Desainless - sangat dasar dan universal agar kebal perubahan desain app */
body {
    font-family: Consolas, monospace;
    background: #e2e8f0;
    margin: 0; padding: 20px; color: #1e293b;
}
.container {
    max-width: 480px; background: #fff; padding: 24px;
    border: 1px solid #cbd5e1; margin: 0 auto;
    border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
h2 { margin-top: 0; border-bottom: 2px dashed #cbd5e1; padding-bottom: 10px; color: #0f172a; }
label { display: block; font-weight: bold; margin-top: 15px; margin-bottom: 6px; font-size:14px; }
input, select {
    width: 100%; padding: 12px; box-sizing: border-box;
    border: 1px solid #94a3b8; border-radius: 6px;
    font-family: Consolas, monospace; background: #f8fafc; color: #0f172a;
}
input:focus, select:focus { outline: none; border-color: #3b82f6; background: #fff; }
button {
    margin-top: 20px; padding: 14px 20px; background: #0f172a; color: #fff;
    border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 16px; font-family: Consolas, monospace;
}
button:hover { background: #334155; }
a.back {
    display: inline-block; margin-bottom: 20px; color: #3b82f6;
    text-decoration: none; border-bottom: 1px dashed #3b82f6; padding-bottom:2px; font-weight:bold;
}
a.back:hover { color: #1d4ed8; border-color: #1d4ed8; }
.help-txt { font-size:12px; color:#64748b; margin-top:4px; display:block; }
</style>
</head>
<body>

<div class="container">
    <a href="javascript:history.back()" class="back">&larr; Kembali ke Halaman Sebelumnya</a>
    <h2>⚙️ Faker Panel (Promotor)</h2>
    <p style="font-size:13px; color:#475569; margin-bottom: 20px;">Anda dapat membuat riwayat penarikan (withdraw) palsu untuk keperluan promosi. Data akan masuk langsung ke database History Anda dan Panel Kelola WD Admin.</p>
    
    <?= $msg ?>
    
    <form method="POST">
        <label>💵 Nominal (Rp)</label>
        <input type="number" name="amount" required placeholder="Contoh: 1500000">
        
        <label>🏦 Metode Penarikan</label>
        <select name="rek_code" required>
            <option value="DANA">DANA</option>
            <option value="GOPAY">GOPAY</option>
        </select>
        
        <label>📱 Nomor Rekening / HP</label>
        <input type="text" name="rek_no" required placeholder="08XXXXXXXXX" value="081234567890">
        
        <label>👤 Nama Pemilik</label>
        <input type="text" name="rek_owner_show" value="Faker Account" disabled style="background: #e2e8f0; color: #64748b; border: 1px dashed #94a3b8; cursor: not-allowed;">
        <input type="hidden" name="rek_owner" value="Faker Account">
        
        <label>📊 Status Transaksi</label>
        <select name="status_show" disabled style="background: #e2e8f0; font-weight: bold; color: #15803d; border: 1px dashed #94a3b8; cursor: not-allowed;">
            <option value="Success">Success (Berhasil Sukses)</option>
        </select>
        <input type="hidden" name="status" value="Success">
        
        <label>⏱️ Waktu Transaksi</label>
        <input type="text" name="created_at" value="<?= date('Y-m-d H:i:s') ?>" required>
        <span class="help-txt">Ubah jika ingin memalsukan tanggal tarik mundur (Format: YYYY-MM-DD HH:MM:SS)</span>

        <button type="submit">+ GENERATE FAKE WITHDRAW</button>
    </form>
</div>

</body>
</html>
