<?php

require '../../mainconfig.php';
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';
    if ($_POST) {
        $data = array('method', 'min_amount');
        if (!check_input($_POST, $data)) {
            $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.'];
        } elseif (!$csrf_token) {
            $result_msg = ['response' => 'error', 'msg' => 'Permintaan tidak diterima.'];
        } else {
            $input_post = array(
                'name' => protect(trim($_POST['method'])),
                'min_amount' => protect(trim($_POST['min_amount']))
            );
            if (check_empty($input_post)) {
                $result_msg = ['response' => 'error', 'msg' => 'Mohon isi semua input.'];
            } else {
                $input_data = array(
                    'name' => $input_post['name'],
                    'min_amount' => $input_post['min_amount'],
                    'status' => 1
                );
                if ($model->db_insert($db, "withdraw_methods", $input_data)) {
                    $result_msg = ['response' => 'success', 'msg' => 'Data berhasil ditambahkan.', 'path' => base_url('babikode/withdraw_method/')];
                } else {
                    $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server.'];
                }
            }
        }
        require '../../lib/result.php';
    } else { ?>
        <form class="form-horizontal" id="form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
            <div class="form-group">
                <label>Nama Metode</label>
                <input type="text" class="form-control" name="method">
            </div>
            <div class="form-group">
                <label>Min. Withdraw</label>
                <input type="number" class="form-control" name="min_amount">
            </div>
            <div id="modal-result"></div>
            <div class="form-group text-right mb-0">
                <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><i class="ft-rotate-ccw mr-1"></i>Ulangi</button>
                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green" onclick="btn_post('#form', '<?= base_url('babikode/withdraw_method/add'); ?>');"><i class="ft-plus mr-1"></i>Tambah</button>
            </div>
        </form>
<?php
    }
} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}
