<?php 

require '../../mainconfig.php';
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';
    if ($_POST) {
        $data = array('nama_produk', 'harga', 'persentase', 'masa_aktif');
        if (!check_input($_POST, $data)) {
            $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.'];
        } elseif (!$csrf_token) {
            $result_msg = ['response' => 'error', 'msg' => 'Permintaan tidak diterima.'];
        } else {

            $input_post = array(
                'nama_produk' => protect(trim($_POST['nama_produk'])),
                'harga'       => protect(trim($_POST['harga'])),
                'persentase'  => protect(trim($_POST['persentase'])),
                'masa_aktif'  => protect(trim($_POST['masa_aktif']))
            );

            if (check_empty($input_post)) {
                $result_msg = ['response' => 'error', 'msg' => 'Mohon isi semua input.'];
            } else {

                $harga  = (int)$input_post['harga'];
                $persen = (float)$input_post['persentase']; // float agar support desimal
                $hari   = (int)$input_post['masa_aktif'];

                $profit_harian = $harga * ($persen / 100);
                $total_profit  = $profit_harian * $hari;

                $input_data = array(
                    'nama_produk'   => $input_post['nama_produk'],
                    'harga'         => $harga,
                    'persentase'    => $persen,
                    'masa_aktif'    => $hari,
                    'profit_harian' => round($profit_harian),
                    'total_profit'  => round($total_profit),
                    'type'          => 1,
                    'created_at'    => date('Y-m-d H:i:s')
                );

                if ($model->db_insert($db, "produk_investasi", $input_data)) {
                    $result_msg = ['response' => 'success', 'msg' => 'Data berhasil ditambahkan.', 'path' => base_url('babikode/service/')];
                } else {
                    $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server.'];
                }
            }
        }
        require '../../lib/result.php';
    } else { 
?>
        <form class="form-horizontal" id="form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
            <div class="form-group">
                <label>Nama Produk</label>
                <input type="text" class="form-control" name="nama_produk" placeholder="Contoh: Paket Gold">
            </div>
            <div class="form-group">
                <label>Harga Produk</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" class="form-control" name="harga" min="0" placeholder="Contoh: 100000" oninput="hitungPreview()">
                </div>
            </div>
            <div class="form-group">
                <label>Persentase Keuntungan (%)</label>
                <div class="input-group">
                    <input type="number" class="form-control" name="persentase" min="0" max="100" step="0.01" placeholder="Contoh: 5" oninput="hitungPreview()">
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
            </div>
            <div class="form-group">
                <label>Masa Kontrak (Hari)</label>
                <div class="input-group">
                    <input type="number" class="form-control" name="masa_aktif" min="1" placeholder="Contoh: 30" oninput="hitungPreview()">
                    <div class="input-group-append"><span class="input-group-text">Hari</span></div>
                </div>
            </div>

            <!-- Preview perhitungan otomatis -->
            <div class="alert alert-info" id="preview-box" style="display:none;font-size:13px;">
                <strong>📊 Preview Perhitungan:</strong><br>
                Profit Harian: <strong id="prev-harian">-</strong><br>
                Total Profit (<span id="prev-hari">-</span> hari): <strong id="prev-total">-</strong>
            </div>

            <div id="modal-result"></div>
            <div class="form-group text-right mb-0">
                <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink" onclick="document.getElementById('preview-box').style.display='none'"><i class="ft-rotate-ccw mr-1"></i>Ulangi</button>
                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green" onclick="btn_post('#form', '<?= base_url('babikode/service/add'); ?>');"><i class="ft-plus mr-1"></i>Tambah</button>
            </div>
        </form>

        <script>
        function hitungPreview() {
            var harga   = parseFloat(document.querySelector('[name=harga]').value) || 0;
            var persen  = parseFloat(document.querySelector('[name=persentase]').value) || 0;
            var hari    = parseInt(document.querySelector('[name=masa_aktif]').value) || 0;
            var box     = document.getElementById('preview-box');

            if (harga > 0 && persen > 0 && hari > 0) {
                var harian = harga * (persen / 100);
                var total  = harian * hari;
                document.getElementById('prev-harian').textContent = 'Rp ' + Math.round(harian).toLocaleString('id-ID');
                document.getElementById('prev-total').textContent  = 'Rp ' + Math.round(total).toLocaleString('id-ID');
                document.getElementById('prev-hari').textContent   = hari;
                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
        }
        </script>
<?php
    }
} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}