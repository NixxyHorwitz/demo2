<?php

require '../../mainconfig.php';
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';
    if (!isset($_GET['id'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif (empty($_GET['id'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }
    $data_target = $model->db_query($db, "*", "topups", "id = '" . protect($_GET['id']) . "'");
    if ($data_target['count'] == 1) {
        if ($data_target['rows']['status'] !== 'Expired') {
            $result_msg = ['response' => 'error', 'msg' => 'Hanya dapat menghapus data dengan status <b>Expired.</b>'];
        } else {
            $delete = $model->db_delete($db, "topups", "id = '" . $data_target['rows']['id'] . "'");
            if ($delete) {
                $result_msg = ['response' => 'success', 'msg' => 'Data berhasil dihapus.'];
            } else {
                $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server.'];
            }
        }
    } else {
        $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
    }
    require '../../lib/result.php';
} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}
