<?php
/**
 * lib/referral_commission.php
 * 
 * Helper fungsi pembagian komisi referral multi-level.
 * Bisa dipanggil dari cron (auto_check.php) maupun admin (topup update).
 *
 * @param mysqli  $db
 * @param array   $settings       — baris dari tabel settings
 * @param int     $buyer_id       — user_id yang melakukan deposit
 * @param float   $amount_received — nominal deposit
 * @param string  $trx            — nomor transaksi (untuk log & anti-dobel)
 * @param bool    $silent         — true = tidak echo output (untuk konteks admin/AJAX)
 */
 
 function isSameIP($db, $userA, $userB)
{
    $userA = (int)$userA;
    $userB = (int)$userB;

    $q = $db->query("
        SELECT id, ip_address
        FROM users
        WHERE id IN ($userA, $userB)
    ");

    if (!$q || $q->num_rows < 2) {
        return false;
    }

    $ips = [];

    while ($row = $q->fetch_assoc()) {
        $ips[$row['id']] = trim($row['ip_address'] ?? '');
    }

    // jika salah satu kosong → jangan blokir
    if (empty($ips[$userA]) || empty($ips[$userB])) {
        return false;
    }

    return $ips[$userA] === $ips[$userB];
}

function processReferralCommission($db, $settings, $buyer_id, $amount_received, $trx, $silent = false) {

    $log = function($msg) use ($silent) { if (!$silent) echo $msg . "\n"; };

    $referral_status = $settings['referral_bonus'] ?? 'off';

    $lvl2_on = ($settings['referral_lvl2_status'] ?? 'off') === 'on';
    $lvl3_on = ($settings['referral_lvl3_status'] ?? 'off') === 'on';

    // Ambil data buyer
    $buyerQ = $db->query("SELECT id, uplink FROM users WHERE id='" . (int)$buyer_id . "' LIMIT 1");
    if (!$buyerQ || $buyerQ->num_rows === 0) { 
    //$log("ℹ User buyer tidak ditemukan");
    return; 
        
    }
    $buyer = $buyerQ->fetch_assoc();

    // ── LEVEL 1 (selalu aktif) ──
    $uplink_l1_id = (int)($buyer['uplink'] ?? 0);
    if ($uplink_l1_id <= 0) { 
        //$log("ℹ User tidak punya uplink, tidak ada komisi"); 
        return; 
        }

    $uplinkL1Q = $db->query("SELECT id, uplink, uplink_level FROM users WHERE id='{$uplink_l1_id}' LIMIT 1");
    if (!$uplinkL1Q || $uplinkL1Q->num_rows === 0) { 
        //$log("ℹ Uplink L1 #{$uplink_l1_id} tidak ditemukan");
        return; 
        }

    $uplink_l1    = $uplinkL1Q->fetch_assoc();
    $uplink_level = strtolower($uplink_l1['uplink_level'] ?? 'biasa');
    
    // ❌ Jika referral OFF → hentikan semua komisi
        if ($referral_status !== 'on') {
            return;
        }
        
    if ($uplink_level === 'demo') {
    //$log("⚠ Uplink L1 #{$uplink_l1_id} level DEMO, komisi di-skip");
    return;
   }
    
    // 🚫 Anti multi akun (IP sama buyer & uplink)
    if (isSameIP($db, $buyer_id, $uplink_l1_id)) {
        return;
    }

    if ($uplink_level === 'promotor') {
        $commission_pct_l1 = (float)(($settings['commission_promotor'] ?? 50) / 100);
        $pct_display_l1    = (float)($settings['commission_promotor'] ?? 50);
    } else {
        $commission_pct_l1 = (float)(($settings['commission_biasa'] ?? 10) / 100);
        $pct_display_l1    = (float)($settings['commission_biasa'] ?? 10);
    }

    $bonus_l1 = (int)floor($amount_received * $commission_pct_l1);

    // Anti dobel: cek apakah komisi L1 untuk trx ini sudah pernah dikirim
    $dupQ = $db->query("SELECT id FROM refferals WHERE user_id='{$uplink_l1_id}' AND from_id='{$buyer_id}' AND level='Level 1' AND keterangan LIKE '%" . $db->real_escape_string($trx) . "%' LIMIT 1");
    if ($dupQ && $dupQ->num_rows > 0) { 
        //$log("⚠ Komisi L1 sudah pernah dikirim untuk trx #{$trx}, skip"); 
        return; 
        }
        
    if ($bonus_l1 > 0) {
        $db->query("UPDATE users SET point = point + {$bonus_l1} WHERE id='{$uplink_l1_id}'");
        $ket = $db->real_escape_string("Bonus referral deposit #{$trx}");
        $db->query("INSERT INTO refferals (user_id, from_id, level, amount, keterangan, created_at) VALUES ('{$uplink_l1_id}', '{$buyer_id}', 'Level 1', '{$bonus_l1}', '{$ket}', NOW())");
        $db->query("INSERT INTO point_logs (user_id, type, amount, description, created_at) VALUES ('{$uplink_l1_id}', 'plus', '{$bonus_l1}', 'Bonus referral Level 1 deposit', NOW())");
        //$log("🎁 L1 Komisi Rp{$bonus_l1} ({$pct_display_l1}%) → uplink #{$uplink_l1_id} [{$uplink_level}]");
    } else {
        //$log("ℹ Komisi L1 = 0, tidak dikirim");
    }

    // ── LEVEL 2 (jika ON) ──
    if (!$lvl2_on) { 
        //$log("ℹ Level 2 OFF, skip");
        return; 
        
    }

    $uplink_l2_id = (int)($uplink_l1['uplink'] ?? 0);
    if ($uplink_l2_id <= 0) { 
        //$log("ℹ Tidak ada uplink L2");
        return;
        }

    $uplinkL2Q = $db->query("SELECT id, uplink, uplink_level FROM users WHERE id='{$uplink_l2_id}' LIMIT 1");
    if (!$uplinkL2Q || $uplinkL2Q->num_rows === 0) { 
        //$log("ℹ Uplink L2 #{$uplink_l2_id} tidak ditemukan"); 
        return; 
        }

    $uplink_l2         = $uplinkL2Q->fetch_assoc();
    
        $uplink_level_l2 = strtolower($uplink_l2['uplink_level'] ?? 'biasa');
        if ($uplink_level_l2 === 'demo') {
            //$log("⚠ Uplink L2 #{$uplink_l2_id} level DEMO, komisi di-skip");
            return;
        }
    
    // 🚫 Anti multi akun L2 (buyer & uplink L2 IP sama)
    if (isSameIP($db, $buyer_id, $uplink_l2_id)) {
        return;
    }

    $commission_pct_l2 = (float)(($settings['commission_lvl_2'] ?? 3) / 100);
    $pct_display_l2    = (float)($settings['commission_lvl_2'] ?? 3);
    $bonus_l2          = (int)floor($amount_received * $commission_pct_l2);

    if ($bonus_l2 > 0) {
        $db->query("UPDATE users SET point = point + {$bonus_l2} WHERE id='{$uplink_l2_id}'");
        $ket = $db->real_escape_string("Bonus referral L2 deposit #{$trx}");
        $db->query("INSERT INTO refferals (user_id, from_id, level, amount, keterangan, created_at) VALUES ('{$uplink_l2_id}', '{$buyer_id}', 'Level 2', '{$bonus_l2}', '{$ket}', NOW())");
        $db->query("INSERT INTO point_logs (user_id, type, amount, description, created_at) VALUES ('{$uplink_l2_id}', 'plus', '{$bonus_l2}', 'Bonus referral Level 2 deposit', NOW())");
        //$log("🎁 L2 Komisi Rp{$bonus_l2} ({$pct_display_l2}%) → uplink #{$uplink_l2_id}");
    } else {
        //$log("ℹ Komisi L2 = 0, tidak dikirim");
    }

    // ── LEVEL 3 (jika ON) ──
    if (!$lvl3_on) { 
        //$log("ℹ Level 3 OFF, skip");
        return; 
        
    }

    $uplink_l3_id = (int)($uplink_l2['uplink'] ?? 0);
    if ($uplink_l3_id <= 0) 
    { 
        //$log("ℹ Tidak ada uplink L3"); 
        return; 
        
    }

    $uplinkL3Q = $db->query("SELECT id, uplink_level FROM users WHERE id='{$uplink_l3_id}' LIMIT 1");
    if (!$uplinkL3Q || $uplinkL3Q->num_rows === 0) { 
        //$log("ℹ Uplink L3 #{$uplink_l3_id} tidak ditemukan"); 
        return; 
    }
    $uplink_l3 = $uplinkL3Q->fetch_assoc();
    $uplink_level_l3 = strtolower($uplink_l3['uplink_level'] ?? 'biasa');
    if ($uplink_level_l3 === 'demo') { 
        return; 
    }
    
    // 🚫 Anti multi akun L3
        if (isSameIP($db, $buyer_id, $uplink_l3_id)) {
            return;
        }

    $commission_pct_l3 = (float)(($settings['commission_lvl_3'] ?? 2) / 100);
    $pct_display_l3    = (float)($settings['commission_lvl_3'] ?? 2);
    $bonus_l3          = (int)floor($amount_received * $commission_pct_l3);

    if ($bonus_l3 > 0) {
        $db->query("UPDATE users SET point = point + {$bonus_l3} WHERE id='{$uplink_l3_id}'");
        $ket = $db->real_escape_string("Bonus referral L3 deposit #{$trx}");
        $db->query("INSERT INTO refferals (user_id, from_id, level, amount, keterangan, created_at) VALUES ('{$uplink_l3_id}', '{$buyer_id}', 'Level 3', '{$bonus_l3}', '{$ket}', NOW())");
        $db->query("INSERT INTO point_logs (user_id, type, amount, description, created_at) VALUES ('{$uplink_l3_id}', 'plus', '{$bonus_l3}', 'Bonus referral Level 3 deposit', NOW())");
        //$log("🎁 L3 Komisi Rp{$bonus_l3} ({$pct_display_l3}%) → uplink #{$uplink_l3_id}");
    } else {
        //$log("ℹ Komisi L3 = 0, tidak dikirim");
    }
}