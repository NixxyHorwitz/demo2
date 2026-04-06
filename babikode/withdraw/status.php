<?php
//status.php
require '../../mainconfig.php';

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';
    
    if (!isset($_GET['id']) || !isset($_GET['status'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif (empty($_GET['id'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif (!in_array($_GET['status'], array('success', 'canceled'))) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }
    
    $data_target = $model->db_query($db, "*", "withdraws", "id = '" . protect($_GET['id']) . "'");
    
    if ($data_target['count'] == 1) {
        $withdraw = $data_target['rows'];
        
        // Update status withdraw
        $update = $model->db_update(
            $db, 
            "withdraws", 
            array(
                'status' => ucfirst($_GET['status']), 
                'updated_at' => date('Y-m-d H:i:s')
            ), 
            "id = '" . $withdraw['id'] . "'"
        );
        
        if ($update) {
            // Jika withdraw dibatalkan, kembalikan saldo ke user
            if ($_GET['status'] == 'canceled') {
                $user = $model->db_query($db, "*", "users", "id = '" . $withdraw['user_id'] . "'")['rows'];
                
                // PERBAIKAN: Gunakan kolom 'amount' bukan 'point'
                $refund_amount = (int) $withdraw['amount'];
                
                // Update saldo user
                $model->db_update(
                    $db, 
                    "users", 
                    array('point' => $user['point'] + $refund_amount), 
                    "id = '" . $user['id'] . "'"
                );
                
                // Insert log pengembalian saldo
                $model->db_insert($db, "point_logs", array(
                    'user_id' => $user['id'], 
                    'type' => 'plus', 
                    'amount' => $refund_amount, // PERBAIKAN: Pastikan amount tidak NULL
                    'description' => 'Permintaan Withdraw Ditolak. ID: ' . $withdraw['id'], 
                    'created_at' => date('Y-m-d H:i:s')
                ));
            }
            
            $result_msg = ['response' => 'success', 'msg' => 'Status berhasil diubah.'];
        } else {
            $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server.'];
        }
    } else {
        $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
    }
    
    require '../../lib/result.php';
} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}