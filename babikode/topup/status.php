<?php
require '../../mainconfig.php';

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';

    if (!isset($_GET['id']) || !isset($_GET['status'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }

    $id     = protect($_GET['id']);
    $status = $_GET['status'];

    if (!in_array($status, ['success', 'Expired'], true)) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }

    $row = $model->db_query($db, "*", "topups", "id = '" . $id . "'");
    if ($row['count'] !== 1) {
        $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
        require '../../lib/result.php';
        exit;
    }

    $t = $row['rows'];

    $ok = $model->db_update($db, "topups", [
        'status'     => ucfirst($status),
        'updated_at' => date('Y-m-d H:i:s')
    ], "id = '" . $t['id'] . "'");

    if ($ok && $status === 'success') {

        $credit  = (int)$t['post_amount'];
        $user    = $model->db_query($db, "*", "users", "id = '" . $t['user_id'] . "'")['rows'];
        $trx     = $t['trxtopup'];
        $user_id = (int)$user['id'];

        // ── Anti dobel ──
        $dupPts = $model->db_query($db, "COUNT(1) AS c", "point_logs",   "description LIKE '%" . $db->real_escape_string($trx) . "%'");
        $already = ((int)($dupBal['rows']['c'] ?? 0) > 0) || ((int)($dupPts['rows']['c'] ?? 0) > 0);

        if (!$already && $credit > 0) {

            // ── Kredit saldo point ──
            $model->db_update($db, "users", ['saldo' => $user['saldo'] + $credit], "id = '{$user_id}'");
            $model->db_insert($db, "point_logs", [
                'user_id'     => $user_id,
                'type'        => 'plus',
                'amount'      => $credit,
                'description' => 'Top Up (Admin) #' . $trx,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            // ── Proses komisi referral multi-level ──
            require_once '../../lib/referral_commission.php';

            $settingsQ = $db->query("SELECT * FROM settings WHERE id='1' LIMIT 1");
            $settings  = $settingsQ ? $settingsQ->fetch_assoc() : [];

            // silent=true karena ini konteks AJAX, tidak perlu echo
            processReferralCommission($db, $settings, $user_id, $credit, $trx, true);
        }
    }

    $result_msg = ['response' => 'success', 'msg' => 'Status berhasil diubah.'];
    require '../../lib/result.php';

} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}