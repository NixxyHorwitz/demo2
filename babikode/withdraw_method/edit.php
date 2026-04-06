<?php

require '../../mainconfig.php';
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';
    if (!isset($_GET['id'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif (empty($_GET['id'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }
    $data_target = $model->db_query($db, "*", "withdraw_methods", "id = '" . protect($_GET['id']) . "'");
    if ($data_target['count'] == 0) {
        $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
        require '../../lib/result.php';
        exit();
    }
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
                if ($model->db_update($db, "withdraw_methods", $input_post, "id = '" . $data_target['rows']['id'] . "'")) {
                    $result_msg = ['response' => 'success', 'msg' => 'Data berhasil diubah.'];
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
                <input type="text" class="form-control" name="method" value="<?= $data_target['rows']['name'] ?>">
            </div>
            <div class="form-group">
                <label>Min. Withdraw</label>
                <input type="number" class="form-control" name="min_amount" value="<?= $data_target['rows']['min_amount'] ?>">
            </div>
            <div id="modal-result"></div>
            <div class="form-group text-right mb-0">
                <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><i class="ft-rotate-ccw mr-1"></i>Ulangi</button>
                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green" onclick="btn_post('#form', '<?= base_url('babikode/withdraw_method/edit?id=' . $data_target['rows']['id']); ?>');"><i class="ft-save mr-1"></i>Simpan</button>
            </div>
        </form>
<?php
    }
} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}
