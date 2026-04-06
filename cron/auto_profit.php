<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../mainconfig.php';

date_default_timezone_set("Asia/Jakarta");
$tanggal_hari_ini = date("Y-m-d");


// =======================
// PASTIKAN TABEL PENDUKUNG ADA
// =======================

$db->query("
    CREATE TABLE IF NOT EXISTS `auto_profit` (
        `id`    int(11) NOT NULL AUTO_INCREMENT,
        `name`  varchar(100) NOT NULL,
        `value` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->query("
    INSERT INTO auto_profit (name, value)
    VALUES ('last_profit_date','')
    ON DUPLICATE KEY UPDATE name=name
");


// =======================
// CEK SUDAH DIJALANKAN HARI INI
// =======================

$get_setting = $db->query("
    SELECT value 
    FROM auto_profit 
    WHERE name='last_profit_date'
    LIMIT 1
");

if (!$get_setting) {
    die("[ERROR] Query auto_profit gagal: ".$db->error);
}

$setting   = $get_setting->fetch_assoc();
$last_date = trim($setting['value'] ?? '');

if ($last_date === $tanggal_hari_ini) {
    return;
}


// =======================
// LOCK AGAR CRON TIDAK DOBEL
// =======================

$db->query("
    UPDATE auto_profit
    SET value='$tanggal_hari_ini'
    WHERE name='last_profit_date'
    AND (value != '$tanggal_hari_ini' OR value IS NULL OR value='')
");

if ($db->affected_rows == 0) {
    return;
}


// =======================
// AMBIL ORDER AKTIF
// is_locked DIAMBIL LANGSUNG DARI ORDERS
// =======================

$get_orders = $db->query("
    SELECT 
        id,
        user_id,
        profit_harian,
        COALESCE(is_locked,0) AS is_locked
    FROM orders
    WHERE masa_aktif > 0
    AND status='Active'
");

if (!$get_orders) {
    die("[ERROR] Query orders gagal: ".$db->error);
}

$processed = 0;
$skipped   = 0;
$errors    = 0;


// =======================
// CEK DOUBLE PROFIT
// =======================

function sudahAdaPointHarian($db,$user_id,$order_id,$tanggal)
{
    $tanggal = $db->real_escape_string($tanggal);

    $sql = "
        SELECT id
        FROM point_logs
        WHERE user_id='$user_id'
        AND description LIKE '%Produk $order_id Tanggal $tanggal%'
        LIMIT 1
    ";

    $q = $db->query($sql);
    return ($q && $q->num_rows > 0);
}


// =======================
// PROSES PROFIT
// =======================

while ($order = $get_orders->fetch_assoc()) {

    $order_id  = (int)$order['id'];
    $user_id   = (int)$order['user_id'];
    $profit    = (float)$order['profit_harian'];
    $is_locked = (int)$order['is_locked'];

    // Tentukan saldo tujuan
    if ($is_locked === 1) {
        $kolom_target = 'profit';
        $log_prefix   = 'Profit Lock Harian';
    } else {
        $kolom_target = 'point';
        $log_prefix   = 'Point Harian';
    }

    // Anti double
    if (sudahAdaPointHarian($db,$user_id,$order_id,$tanggal_hari_ini)) {
        $skipped++;
        continue;
    }

    $desc = $db->real_escape_string(
        "$log_prefix Produk $order_id Tanggal $tanggal_hari_ini"
    );

    // =======================
    // TRANSACTION (ANTI BUG)
    // =======================
    $db->begin_transaction();

    try {

        // Tambah saldo user
        $r1 = $db->query("
            UPDATE users
            SET $kolom_target = $kolom_target + $profit
            WHERE id='$user_id'
            LIMIT 1
        ");

        if (!$r1 || $db->affected_rows == 0) {
            throw new Exception("Update saldo gagal");
        }

        // Kurangi masa aktif
        $r2 = $db->query("
            UPDATE orders
            SET masa_aktif = masa_aktif - 1
            WHERE id='$order_id'
            AND masa_aktif > 0
            LIMIT 1
        ");

        if (!$r2) {
            throw new Exception("Update masa aktif gagal");
        }

        // Auto completed
        $r3 = $db->query("
            UPDATE orders
            SET status='Completed'
            WHERE id='$order_id'
            AND masa_aktif = 0
            LIMIT 1
        ");

        // Insert log
        $r4 = $db->query("
            INSERT INTO point_logs
            (user_id,type,amount,description,created_at)
            VALUES
            ('$user_id','hasil','$profit','$desc',NOW())
        ");

        if (!$r4) {
            throw new Exception("Insert log gagal");
        }

        // Commit semua
        $db->commit();
        $processed++;

    } catch (Exception $e) {

        $db->rollback();
        $errors++;

        error_log(
            "[auto_profit ERROR] Order $order_id : ".$e->getMessage()
        );
    }
}

// echo "✓ Selesai $tanggal_hari_ini — Diproses:$processed | Skip:$skipped | Error:$errors";
?>