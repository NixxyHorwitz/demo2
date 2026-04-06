<?php

require '../../mainconfig.php';
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';
    if (!isset($_GET['id'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif (empty($_GET['id'])) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }
    $data_target = $model->db_query($db, "*", "withdraws", "id = '" . protect($_GET['id']) . "'");
    if ($data_target['count'] == 0) {
        $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
        require '../../lib/result.php';
        exit();
    }
    if ($_POST) {
        if ($data_target['rows']['status'] == 'Canceled') {
            $data = array('description');
            if (!check_input($_POST, $data)) {
                $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.'];
            } elseif (!$csrf_token) {
                $result_msg = ['response' => 'error', 'msg' => 'Permintaan tidak diterima.'];
            } else {
                $input_post = array(
                    'description' => protect(trim($_POST['description']))
                );
                if (check_empty($input_post)) {
                    $result_msg = ['response' => 'error', 'msg' => 'Mohon isi semua input.', 'no_act'];
                } else {
                    if ($model->db_update($db, "withdraws", $input_post, "id = '" . $data_target['rows']['id'] . "'")) {
                        $result_msg = ['response' => 'success', 'msg' => 'Alasan penolakan berhasil disimpan.'];
                    } else {
                        $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server.'];
                    }
                }
            }
        } elseif ($data_target['rows']['status'] == 'Success') {
            if (!$csrf_token) {
                $result_msg = ['response' => 'error', 'msg' => 'Permintaan tidak diterima.'];
            } else {
                if (!isset($_FILES['img'])) {
                    $result_msg = ['response' => 'error', 'msg' => 'Mohon isi semua input.'];
                } elseif (empty($_FILES['img']['tmp_name'])) {
                    $result_msg = ['response' => 'error', 'msg' => 'Mohon isi semua input.'];
                } else {
                    $fileName = $_FILES['img']['name'];
                    $fileTmpName = $_FILES['img']['tmp_name'];
                    $fileSize = $_FILES['img']['size'];
                    $fileError = $_FILES['img']['error'];
                    $fileType = $_FILES['img']['type'];
                    $fileExt = strtolower(pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION));
                    $allowedExt = array('jpg', 'jpeg', 'png');
                    if (!in_array($fileExt, $allowedExt)) {
                        $result_msg = ['response' => 'error', 'msg' => 'Hanya file dengan ekstensi <b>(.jpg .jpeg .png)</b> yang diizinkan untuk diupload.'];
                    } elseif ($fileSize > 2097152) {
                        $result_msg = ['response' => 'error', 'msg' => 'File terlalu besar, maksimal <b>2 MB.</b>'];
                    } else {
                        $fileNameNew = str_rand(5) . time() . "." . $fileExt;
                        $fileDestination = "../../assets/payment/" . $fileNameNew;
                        if (file_exists($fileDestination)) {
                            $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server, mohon ulangi permintaan.'];
                        } else {
                            if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                if ($model->db_update($db, "withdraws", array('description' => $fileNameNew), "id = '" . $data_target['rows']['id'] . "'")) {
                                    $result_msg = ['response' => 'success', 'msg' => 'Bukti pembayaran berhasil disimpan.'];
                                } else {
                                    unlink($fileDestination);
                                    $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server.'];
                                }
                            } else {
                                $result_msg = ['response' => 'error', 'msg' => 'Terjadi kesalahan server.'];
                            }
                        }
                    }
                }
            }
        }
        require '../../lib/result.php';
    } else {
        if ($data_target['rows']['status'] == 'Canceled') { ?>
            <form class="form-horizontal" id="form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
                <div class="form-group">
                    <label>Alasan Penolakan</label>
                    <textarea class="form-control" name="description" rows="5" placeholder="Nomor rekening tidak ditemukan."><?= $data_target['rows']['description'] ?></textarea>
                </div>
                <div id="modal-result"></div>
                <div class="form-group text-right mb-0">
                    <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><i class="ft-rotate-ccw mr-1"></i>Ulangi</button>
                    <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green" onclick="btn_post('#form', '<?= base_url('babikode/withdraw/upload?id=' . $data_target['rows']['id']); ?>');"><i class="ft-save mr-1"></i>Simpan</button>
                </div>
            </form>
        <?php
        } elseif ($data_target['rows']['status'] == 'Success') { ?>
            <form class="form-horizontal" enctype="multipart/form-data" id="form" onSubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
                <div class="form-group">
                    <label>Bukti Pembayaran <?= (!is_null($data_target['rows']['description'])) ? 'Baru' : ''; ?></label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" name="img" id="file">
                        <label class="custom-file-label" for="file">Choose file</label>
                    </div>
                </div>
                <?php
                if (!is_null($data_target['rows']['description'])) { ?>
                    <div class="form-group">
                        <label class="form-label">Bukti Pembayaran Saat Ini</label>
                        <img src="<?= base_url('assets/payment/' . $data_target['rows']['description']); ?>" width="100%">
                    </div>
                <?php
                }
                ?>
                <div id="modal-result"></div>
                <div class="form-group text-right mb-0">
                    <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><i class="ft-rotate-ccw mr-1"></i>Ulangi</button>
                    <button type="submit" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green" id="submit"><i class="ft-save mr-1"></i>Simpan</button>
                </div>
            </form>
            <script type="text/javascript">
                $(".custom-file input").change(function(t) {
                    $(this).next(".custom-file-label").html(t.target.files[0].name)
                });
                $('#form').on('submit', (function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    $.ajax({
                        type: 'POST',
                        url: '<?= base_url('babikode/withdraw/upload?id=' . $data_target['rows']['id']); ?>',
                        data: formData,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data) {
                            $('#block').removeClass('block');
                            $('#modal-result').html(data);
                        },
                        error: function() {
                            $('#block').removeClass('block');
                            $('#modal-result').html('<div class="alert alert-danger alert-dismissable"><button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>Terjadi kesalahan!</div>');
                        },
                        beforeSend: function() {
                            $('#block').addClass('block');
                            $('#modal-result').html('<div class="progress mb-4"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">Loading...</div></div');
                        }
                    });
                }));
            </script>
        <?php
        } ?>
<?php
    }
} else {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}
