<?php 

require '../../mainconfig.php';
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';
    if ($_POST) {
        // Data yang WAJIB ada di POST (sesuai kolom NOT NULL pada DB, dan yang perlu diisi manual)
        $data = array('user_id', 'no_rek', 'name_rek', 'amount'); 

        if (!check_input($_POST, $data)) {
            $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.'];
        } elseif (!$csrf_token) {
            $result_msg = ['response' => 'error', 'msg' => 'Permintaan tidak diterima.'];
        } else {

            // Ambil dan bersihkan data POST yang relevan untuk withdraw
            $input_post = array(
                'user_id'    => protect(trim($_POST['user_id'])),
                'method'     => 'DANA',
                'bank_code'  => '10002', // Kolom 'bank_code' boleh NULL, tapi dimasukkan jika ada
                'no_rek'     => protect(trim($_POST['no_rek'])),
                'name_rek'   => protect(trim($_POST['name_rek'])),
                'komisi'     => '1000',
                'amount'     => protect(trim($_POST['amount'])),
                'fee'        => '1000', // Kolom 'fee' boleh NULL, tapi dimasukkan jika ada
                'description'=> 'WD FAKE' 
            );

            // Cek jika ada input wajib yang kosong
            if (check_empty($input_post, ['bank_code', 'fee'])) { // Abaikan 'bank_code' dan 'fee' karena boleh NULL
                $result_msg = ['response' => 'error', 'msg' => 'Mohon isi semua input yang wajib.'];
            } elseif (!is_numeric($input_post['user_id']) || $input_post['user_id'] <= 0) {
                 $result_msg = ['response' => 'error', 'msg' => 'ID Pengguna tidak valid.'];
            } elseif (!is_numeric($input_post['amount']) || $input_post['amount'] <= 0) {
                 $result_msg = ['response' => 'error', 'msg' => 'Jumlah (Amount) tidak valid.'];
            } elseif (!is_numeric($input_post['komisi']) || $input_post['komisi'] < 0) {
                 $result_msg = ['response' => 'error', 'msg' => 'Komisi tidak valid.'];
            } else {

                // Konversi ke tipe data yang sesuai
                $user_id  = (int)$input_post['user_id'];
                $amount   = (double)$input_post['amount'];
                $komisi   = (double)$input_post['komisi']; 
                $fee      = (isset($input_post['fee']) && is_numeric($input_post['fee'])) ? (int)$input_post['fee'] : NULL;
                
                // Data yang akan dimasukkan ke database 'withdraws'
                $input_data = array(
                    'user_id'     => $user_id,
                    'method'      => 'DANA', 
                    'bank_code'   => '10002', 
                    'no_rek'      => $input_post['no_rek'],
                    'name_rek'    => $input_post['name_rek'],
                    'komisi'      => '1000',
                    'amount'      => $amount,
                    'fee'         => $fee,
                    'status'      => 'Success', // Status awal, bisa disesuaikan
                    'description' => 'WD FAKE',
                    'created_at'  => date('Y-m-d H:i:s')
                    // Kolom lain seperti provider, order_id, plat_order_num dibiarkan NULL atau diisi sesuai kebutuhan
                );


                if ($model->db_insert($db, "withdraws", $input_data)) {
                    $result_msg = ['response' => 'success', 'msg' => 'Data Withdraw berhasil ditambahkan.', 'path' => base_url('babikode/withdraw/')];
                } else {
                    $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server saat menyimpan data.'];
                }
            }
        }
        require '../../lib/result.php';
    } else { 
?>

        <form class="form-horizontal" id="form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
            
            <div class="form-group">
                <label>ID Pengguna (user_id)</label>
                <input type="number" class="form-control" name="user_id" required>
            </div>
            <div class="form-group">
                <label>Nomor Rekening/Akun (no_rek)</label>
                <input type="text" class="form-control" name="no_rek" required>
            </div>

            <div class="form-group">
                <label>Nama Pemilik Rekening (name_rek)</label>
                <input type="text" class="form-control" name="name_rek" required>
            </div>
            
            <div class="form-group">
                <label>Jumlah Penarikan (amount)</label>
                <input type="number" step="0.01" class="form-control" name="amount" required>
            </div>

            <div id="modal-result"></div>
            <div class="form-group text-right mb-0">
                <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><i class="ft-rotate-ccw mr-1"></i>Ulangi</button>
                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green" onclick="btn_post('#form', '<?= base_url('babikode/withdraw/add'); ?>');"><i class="ft-plus mr-1"></i>Tambah Withdraw</button>
            </div>
        </form>
<?php
    }
} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}