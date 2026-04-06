<?php
require '../../mainconfig.php';
require '../../lib/check_session_admin.php';
$page_type = 'web_settings';
$page_name = 'Pengaturan WEB';

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (!$csrf_token) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }

    if (isset($_POST['referral_bonus'])) {
        if (in_array($_POST['referral_bonus'], ['on', 'off'])) {
            $model->db_update($db, "settings", ['referral_bonus' => $_POST['referral_bonus']], "id = '1'");
            $result_msg = ['response' => 'success', 'msg' => 'Referral Bonus berhasil disimpan.'];
        } else { $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.']; }
    }

    if (isset($_POST['referral_lvl2_status'])) {
        if (in_array($_POST['referral_lvl2_status'], ['on', 'off'])) {
            $model->db_update($db, "settings", ['referral_lvl2_status' => $_POST['referral_lvl2_status']], "id = '1'");
            $result_msg = ['response' => 'success', 'msg' => 'Referral Level 2 berhasil diubah.'];
        } else { $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.']; }
    }

    if (isset($_POST['referral_lvl3_status'])) {
        if (in_array($_POST['referral_lvl3_status'], ['on', 'off'])) {
            $model->db_update($db, "settings", ['referral_lvl3_status' => $_POST['referral_lvl3_status']], "id = '1'");
            $result_msg = ['response' => 'success', 'msg' => 'Referral Level 3 berhasil diubah.'];
        } else { $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.']; }
    }

    if (isset($_POST['withdraw_status'])) {
        if (in_array($_POST['withdraw_status'], ['on', 'off'])) {
            $model->db_update($db, "settings", ['withdraw_status' => $_POST['withdraw_status']], "id = '1'");
            $result_msg = ['response' => 'success', 'msg' => 'Pengaturan Withdraw berhasil disimpan.'];
        } else { $result_msg = ['response' => 'error', 'msg' => 'Input tidak sesuai.']; }
    }



    if (isset($_POST['title'])) {
        $input_post = [
            'title'                => protect(trim($_POST['title'])),
            'meta_description'     => protect(trim($_POST['meta_description'])),
            'meta_keyword'         => protect(trim($_POST['meta_keyword'])),
            'link_telegram'        => protect(trim($_POST['link_telegram'])),
            'wd_product_id' => (int) protect(trim($_POST['wd_product_id'] ?? 0)),
            'mt_web'               => (int) protect(trim($_POST['mt_web'])),
            'commission_biasa'     => protect(trim($_POST['commission_biasa'])),
            'commission_promotor'  => protect(trim($_POST['commission_promotor'])),
            'commission_lvl_2'     => protect(trim($_POST['commission_lvl_2'])),
            'commission_lvl_3'     => protect(trim($_POST['commission_lvl_3'])),
            'bonus_checkin'        => (int) protect(trim($_POST['bonus_checkin'])),
            'api_key'     => protect(trim($_POST['api_key'])),
            'secret_key'  => protect(trim($_POST['secret_key'])),
            'min_wd'               => (int) protect(trim($_POST['min_wd'])),
            'min_depo'             => (int) protect(trim($_POST['min_depo'])),
            'withdraw_fee'         => (int) protect(trim($_POST['withdraw_fee'])),
            'withdraw_fee_percent' => (int) protect(trim($_POST['withdraw_fee_percent'])),
            'pesan_wd'         => protect(trim($_POST['pesan_wd'])),
        ];

        if (check_empty([$input_post['title'], $input_post['commission_biasa'], $input_post['commission_promotor']])) {
            $result_msg = ['response' => 'error', 'msg' => 'Mohon isi semua input wajib.'];
        } else {
            if (!empty($_FILES['web_logo']['name'])) {
                $allow_ext = ['png', 'jpg', 'jpeg', 'ico'];
                $ext = pathinfo($_FILES['web_logo']['name'], PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), $allow_ext)) { $result_msg = ['response' => 'error', 'msg' => 'Format gambar harus PNG, JPG, atau ICO.']; goto end_process; }
                if ($_FILES['web_logo']['size'] > 2097152) { $result_msg = ['response' => 'error', 'msg' => 'Ukuran gambar maksimal 2MB.']; goto end_process; }
                $new_name = 'logo_' . time() . '.' . $ext;
                $upload_dir = '../../assets/images/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (move_uploaded_file($_FILES['web_logo']['tmp_name'], $upload_dir . $new_name)) {
                    $input_post['web_logo'] = $new_name;
                } else { $result_msg = ['response' => 'error', 'msg' => 'Gagal mengupload gambar.']; goto end_process; }
            }
            $model->db_update($db, "settings", $input_post, "id = '1'");
            
            // PROFILE BANNER UPLOAD LOGIC
            $pb_url = protect($_POST['profile_banner_url'] ?? '');
            $upload_exts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            if (isset($_FILES['profile_banner_file']) && $_FILES['profile_banner_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['profile_banner_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $upload_exts)) {
                    $filename = 'prof_ban_'.time().'_'.mt_rand(10,99).'.'.$ext;
                    $upload_dir = '../../assets/uploads/';
                    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                    if (move_uploaded_file($_FILES['profile_banner_file']['tmp_name'], $upload_dir . $filename)) {
                        $pb_url = base_url('assets/uploads/' . $filename);
                    }
                }
            }
            if ($pb_url !== '') {
                // Ensure record exists
                $db->query("INSERT INTO app_images (image_key, image_url, description) VALUES ('profile_banner', '{$db->real_escape_string($pb_url)}', 'Banner Halaman Profil') ON DUPLICATE KEY UPDATE image_url='{$db->real_escape_string($pb_url)}'");
            }
            
            $result_msg = ['response' => 'success', 'msg' => 'Pengaturan berhasil disimpan.'];
        }
    }

    end_process:
    if (isset($result_msg)) { ?>
        <script type="text/javascript">
            Swal.fire({
                icon: "<?= $result_msg['response']; ?>",
                title: "<?= ($result_msg['response'] == 'success') ? 'Yeay!' : 'Ups!'; ?>",
                html: "<?= $result_msg['msg']; ?>",
                customClass: { confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green' },
                buttonsStyling: false, allowOutsideClick: false, allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed && "<?= $result_msg['response']; ?>" == "success") {
                    window.location.reload();
                }
            });
        </script>
<?php } exit(); }

require '../../lib/header_admin.php';
$sett = $model->db_query($db, "*", "settings")['rows'];

$app_images = [];
$imgQ = $db->query("SELECT * FROM app_images");
if($imgQ) { while($r = $imgQ->fetch_assoc()) $app_images[$r['image_key']] = $r['image_url']; }
?>

<div class="content-header row">
    <div class="content-header-left col-md-4 col-12 mb-2">
        <h3 class="content-header-title"><?= $page_name; ?></h3>
    </div>
    <div class="content-header-right col-md-8 col-12">
        <div class="breadcrumbs-top float-md-right">
            <div class="breadcrumb-wrapper mr-1">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="javascript:;"><?= base_title(); ?></a></li>
                    <li class="breadcrumb-item active">Pengaturan</li>
                    <li class="breadcrumb-item active">WEB</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
<div class="row justify-content-center">
<div class="col-md-12">
<div class="card">
<div class="card-header"><h4 class="card-title float-left"><?= $page_name; ?></h4></div>
<div class="card-body pt-0">
    <div id="body-result"></div>
    <?php require '../../lib/flash_message.php'; ?>

    <form method="POST" class="row" id="form" enctype="multipart/form-data">

       <!-- ============================= -->
<!-- SYSTEM TOGGLES -->
<!-- ============================= -->
<div class="col-md-12">
    <hr>
    <h5>⚙️ Pengaturan Sistem</h5>
</div>

<div class="col-md-12">
    <div class="row">

        <!-- Referral Bonus -->
        <div class="col-md-6 mb-2">
            <div class="card shadow-sm border h-100">
                <div class="card-body d-flex justify-content-between align-items-center py-2">

                    <div>
                        <div class="font-weight-bold">
                            Referral Bonus
                        </div>
                        <small class="text-muted">
                            Aktifkan bonus referral untuk setiap transaksi member.
                        </small>
                    </div>

                    <input type="checkbox"
                           id="referral_bonus"
                           class="switchery"
                           data-color="info"
                           data-size="small"
                           <?= ($sett['referral_bonus'] == 'on') ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>


        <!-- Referral Level 2 -->
        <div class="col-md-6 mb-2">
            <div class="card shadow-sm border h-100">
                <div class="card-body d-flex justify-content-between align-items-center py-2">

                    <div>
                        <div class="font-weight-bold">
                            Referral Level 2
                        </div>
                        <small class="text-muted">
                            ON: Komisi ke upline L2 • OFF: hanya Level 1.
                        </small>
                    </div>

                    <input type="checkbox"
                           id="referral_lvl2_status"
                           class="switchery"
                           data-color="warning"
                           data-size="small"
                           <?= (($sett['referral_lvl2_status'] ?? 'off') == 'on') ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>


        <!-- Referral Level 3 -->
        <div class="col-md-6 mb-2">
            <div class="card shadow-sm border h-100">
                <div class="card-body d-flex justify-content-between align-items-center py-2">

                    <div>
                        <div class="font-weight-bold">
                            Referral Level 3
                        </div>
                        <small class="text-muted">
                            ON: Komisi ke upline L3.<br>
                            <span class="text-warning">⚠ Butuh Level 2 aktif.</span>
                        </small>
                    </div>

                    <input type="checkbox"
                           id="referral_lvl3_status"
                           class="switchery"
                           data-color="danger"
                           data-size="small"
                           <?= (($sett['referral_lvl3_status'] ?? 'off') == 'on') ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>


        <!-- Withdraw -->
        <div class="col-md-6 mb-2">
            <div class="card shadow-sm border h-100">
                <div class="card-body d-flex justify-content-between align-items-center py-2">

                    <div>
                        <div class="font-weight-bold">
                            Withdraw System
                        </div>
                        <small class="text-muted">
                            Aktifkan / nonaktifkan fitur withdraw member.
                        </small>
                    </div>

                    <input type="checkbox"
                           id="withdraw_status"
                           class="switchery"
                           data-color="success"
                           data-size="small"
                           <?= ($sett['withdraw_status'] == 'on') ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>

    </div>
</div>

            <!-- ============================= -->
<!-- WITHDRAW SETTINGS -->
<!-- ============================= -->
<div class="col-md-12">
    <hr>
    <h5 class="mb-3">💸 Pengaturan Withdraw</h5>
</div>

<div class="col-md-12">
    <div class="row">

        <!-- PESAN WITHDRAW -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-light py-2">
                    <strong>Pesan Withdraw</strong>
                </div>

                <div class="card-body">
                    <textarea class="form-control"
                              name="pesan_wd"
                              rows="5"
                              placeholder="Pesan informasi yang tampil di halaman withdraw..."><?= $sett['pesan_wd']; ?></textarea>

                    <small class="text-muted mt-2 d-block">
                        Pesan ini tampil pada halaman withdraw user.
                    </small>
                </div>
            </div>
        </div>


        <!-- PRODUK SYARAT WD -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-light py-2">
                    <strong>Produk Wajib Withdraw</strong>
                </div>

                <div class="card-body">

                    <input type="number"
                           class="form-control"
                           name="wd_product_id"
                           value="<?= $sett['wd_product_id'] ?? 0; ?>"
                           placeholder="Contoh: 12"
                           min="0">

                    <small class="text-muted d-block mt-2">
                        Isi ID produk investasi yang wajib dimiliki.<br>
                        <b>0 = Tanpa syarat produk</b>
                    </small>

<?php
$wd_produk_info = null;
if (!empty($sett['wd_product_id'])) {
    $cek = $model->db_query(
        $db,
        "nama_produk, harga",
        "produk_investasi",
        "id = '".$sett['wd_product_id']."'"
    );

    if ($cek['count'] > 0) {
        $wd_produk_info = $cek['rows'];
    }
}
?>

<?php if ($wd_produk_info): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong>✅ Produk Aktif:</strong><br>

                        <div class="mt-1">
                            <?= $wd_produk_info['nama_produk']; ?>
                        </div>

                        <div class="text-success font-weight-bold">
                            Rp <?= number_format($wd_produk_info['harga'],0,',','.'); ?>
                        </div>
                    </div>
<?php endif; ?>

                </div>
            </div>
        </div>

    </div>
</div>
        <div class="col-md-6 mb-3">
    <div class="card shadow-sm border h-100">
        <div class="card-header bg-light py-2">
            <strong>Logo Website</strong>
        </div>

        <div class="card-body text-center">

            <?php if (!empty($sett['web_logo'])): ?>
                <img src="<?= base_url('assets/images/' . $sett['web_logo']); ?>"
                     style="max-height:70px;border:1px solid #ddd;padding:6px;border-radius:6px;">
            <?php else: ?>
                <div class="text-muted mb-2">Belum ada logo</div>
            <?php endif; ?>

            <input type="file"
                   class="form-control mt-3"
                   name="web_logo"
                   accept="image/*">

            <small class="text-muted d-block mt-2">
                PNG, JPG, ICO — Maks 2MB
            </small>
        </div>
    </div>
</div>

<div class="col-md-6 mb-3">
    <div class="card shadow-sm border h-100">
        <div class="card-header bg-light py-2">
            <strong>Banner Halaman Profil</strong>
        </div>
        <div class="card-body text-center">
            <?php if (!empty($app_images['profile_banner'])): ?>
                <img src="<?= htmlspecialchars($app_images['profile_banner']); ?>"
                     style="max-height:70px;border:1px solid #ddd;padding:6px;border-radius:6px;">
            <?php else: ?>
                <div class="text-muted mb-2">Belum ada banner</div>
            <?php endif; ?>

            <input type="file" class="form-control mt-2" name="profile_banner_file" accept="image/*">
            <input type="url" class="form-control mt-1" name="profile_banner_url" placeholder="Atau pakai URL..." value="<?= htmlspecialchars($app_images['profile_banner'] ?? '') ?>">

            <small class="text-muted d-block mt-2">
                Tampil di bawah top header halaman Profile.
            </small>
        </div>
    </div>
</div>


       <div class="col-md-12">
    <hr>
    <h5 class="mb-3">📋 Informasi Umum</h5>
</div>

<div class="col-md-12">
    <div class="card shadow-sm border">
        <div class="card-body">
            <div class="row">

                <div class="col-md-4">
                    <label class="font-weight-bold">Nama Web</label>
                    <input type="text" class="form-control" name="title"
                           value="<?= $sett['title']; ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="font-weight-bold">Link Telegram</label>
                    <input type="text" class="form-control"
                           name="link_telegram"
                           value="<?= $sett['link_telegram']; ?>">
                </div>

                <div class="col-md-4">
                    <label class="font-weight-bold">MT Web</label>
                    <input type="number" class="form-control"
                           name="mt_web"
                           value="<?= $sett['mt_web']; ?>">
                    <small class="text-muted">0 = normal</small>
                </div>

            </div>
        </div>
    </div>
</div>

        <!-- ===== KOMISI ===== -->
        <div class="col-md-12"><hr><h5>💰 Konfigurasi Komisi Referral</h5></div>

        <div class="col-md-12 mb-1"><small class="text-muted font-weight-bold text-uppercase">🔵 Level 1 (Selalu Aktif)</small></div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Komisi Biasa <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" class="form-control" name="commission_biasa" value="<?= $sett['commission_biasa']; ?>" placeholder="10" min="0" max="100" step="0.01" required>
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
                <small class="text-muted">Komisi L1 untuk member biasa</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Komisi Promotor <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" class="form-control" name="commission_promotor" value="<?= $sett['commission_promotor']; ?>" placeholder="50" min="0" max="100" step="0.01" required>
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
                <small class="text-muted">Komisi L1 untuk member promotor</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-info mb-0" style="padding:10px;margin-top:32px;">
                <small><strong>ℹ️ Info:</strong> Komisi L1 berbeda berdasarkan tipe akun.<br><strong>Biasa</strong> = member reguler | <strong>Promotor</strong> = diset manual oleh admin.</small>
            </div>
        </div>

        <div class="col-md-12 mt-1 mb-1"><small class="text-muted font-weight-bold text-uppercase">🟡 Level 2 & 3 (Bisa ON/OFF)</small></div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Komisi Level 2
                    <span class="badge badge-<?= (($sett['referral_lvl2_status'] ?? 'off')=='on') ? 'success' : 'secondary'; ?> ml-1" style="font-size:10px;"><?= (($sett['referral_lvl2_status'] ?? 'off')=='on') ? 'ON' : 'OFF'; ?></span>
                </label>
                <div class="input-group">
                    <input type="number" class="form-control" name="commission_lvl_2" value="<?= $sett['commission_lvl_2'] ?? 3; ?>" placeholder="3" min="0" max="100" step="0.01">
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
                <small class="text-muted">Komisi untuk upline L2 (kakek)</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Komisi Level 3
                    <span class="badge badge-<?= (($sett['referral_lvl3_status'] ?? 'off')=='on') ? 'success' : 'secondary'; ?> ml-1" style="font-size:10px;"><?= (($sett['referral_lvl3_status'] ?? 'off')=='on') ? 'ON' : 'OFF'; ?></span>
                </label>
                <div class="input-group">
                    <input type="number" class="form-control" name="commission_lvl_3" value="<?= $sett['commission_lvl_3'] ?? 2; ?>" placeholder="2" min="0" max="100" step="0.01">
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
                <small class="text-muted">Komisi untuk upline L3 (buyut)</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-light" style="border:1px solid #ddd;padding:10px;margin-top:28px;font-size:12px;">
                <strong>📊 Contoh Alur Komisi (Deposit Rp 100.000):</strong>
                <div class="mt-1">
                    🧑 Member deposit Rp 100.000<br>
                    ↑ <strong>L1 Biasa (<?= $sett['commission_biasa'] ?? 10 ?>%)</strong> → Rp <?= number_format(100000*(($sett['commission_biasa']??10)/100),0,',','.'); ?><br>
                    <?php if (($sett['referral_lvl2_status']??'off')=='on'): ?>
                    ↑ <strong>L2 (<?= $sett['commission_lvl_2']??3 ?>%)</strong> → Rp <?= number_format(100000*(($sett['commission_lvl_2']??3)/100),0,',','.'); ?><br>
                    <?php else: ?>↑ <strong>L2</strong> → <span class="text-muted">OFF</span><br><?php endif; ?>
                    <?php if (($sett['referral_lvl3_status']??'off')=='on'): ?>
                    ↑ <strong>L3 (<?= $sett['commission_lvl_3']??2 ?>%)</strong> → Rp <?= number_format(100000*(($sett['commission_lvl_3']??2)/100),0,',','.'); ?>
                    <?php else: ?>↑ <strong>L3</strong> → <span class="text-muted">OFF</span><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== BONUS CHECK-IN ===== -->
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Bonus Check-in</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" class="form-control" name="bonus_checkin" value="<?= $sett['bonus_checkin']; ?>" placeholder="1000" min="0">
                </div>
                <small class="text-muted">Bonus harian untuk check-in</small>
            </div>
        </div>

        <!-- ===== WITHDRAW & DEPOSIT ===== -->
        <div class="col-md-12"><hr><h5>💳 Konfigurasi Withdraw & Deposit</h5></div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Minimal Withdraw</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" class="form-control" name="min_wd" value="<?= $sett['min_wd']; ?>" placeholder="50000" min="0">
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Minimal Deposit</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" class="form-control" name="min_depo" value="<?= $sett['min_depo']; ?>" placeholder="10000" min="0">
                </div>
            </div>
        </div>
        <div class="col-md-3" style="display: none;">
            <div class="form-group">
                <label class="form-label">Biaya Withdraw (Tetap)</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" class="form-control" name="withdraw_fee" value="<?= $sett['withdraw_fee']; ?>" placeholder="5000" min="0">
                </div>
                <small class="text-muted">Biaya tetap per transaksi</small>
            </div>
        </div>
        <div class="col-md-3" style="display: none;">
            <div class="form-group">
                <label class="form-label">Biaya Withdraw (Persen)</label>
                <div class="input-group">
                    <input type="number" class="form-control" name="withdraw_fee_percent" value="<?= $sett['withdraw_fee_percent']; ?>" placeholder="1" min="0" max="100" step="1">
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
                <small class="text-muted">Persentase dari nominal WD</small>
            </div>
        </div>

        <div class="col-md-12" style="display: none;">
            <div class="alert alert-info" style="background:#e3f2fd;border:1px solid #2196f3;">
                <strong>💡 Contoh Perhitungan Biaya Withdraw (Nominal Rp 100.000):</strong><br>
                Biaya Tetap: <strong>Rp <?= number_format($sett['withdraw_fee'],0,',','.'); ?></strong> &nbsp;|&nbsp;
                Biaya Persen (<?= $sett['withdraw_fee_percent']; ?>%): <strong>Rp <?= number_format(100000*($sett['withdraw_fee_percent']/100),0,',','.'); ?></strong><br>
                <hr style="margin:8px 0;">
                <strong style="color:#f44336;">Dipotong: Rp <?= number_format($sett['withdraw_fee']+(100000*($sett['withdraw_fee_percent']/100)),0,',','.'); ?></strong> &nbsp;|&nbsp;
                <strong style="color:#4caf50;">Diterima: Rp <?= number_format(100000-$sett['withdraw_fee']-(100000*($sett['withdraw_fee_percent']/100)),0,',','.'); ?></strong>
            </div>
        </div>

       <!-- ============================= -->
<!-- SEO & META -->
<!-- ============================= -->
<div class="col-md-12">
    <hr>
    <h5 class="mb-3">🔍 SEO & Meta Website</h5>
</div>

<div class="col-md-12">
    <div class="card shadow-sm border">
        <div class="card-body">
            <div class="row">

                <!-- META DESCRIPTION -->
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">
                        Meta Description
                    </label>

                    <textarea class="form-control"
                              name="meta_description"
                              rows="4"
                              placeholder="Deskripsi website untuk Google search..."><?= $sett['meta_description']; ?></textarea>

                    <small class="text-muted">
                        Disarankan 140–160 karakter.
                    </small>
                </div>

                <!-- META KEYWORDS -->
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">
                        Meta Keywords
                    </label>

                    <textarea class="form-control"
                              name="meta_keyword"
                              rows="4"
                              placeholder="contoh: investasi, deposit, ewallet"><?= $sett['meta_keyword']; ?></textarea>

                    <small class="text-muted">
                        Pisahkan keyword dengan tanda koma (,).
                    </small>
                </div>

            </div>
        </div>
    </div>
</div>

       <!-- ============================= -->
<!-- PAYMENT GATEWAY -->
<!-- ============================= -->
<div class="col-md-12">
    <hr>
    <h5 class="mb-3">💎 Konfigurasi Payment Gateway</h5>
</div>

<div class="col-md-12">
    <div class="card shadow-sm border">
        <div class="card-header bg-light py-2">
            <strong>Pengaturan API</strong>
        </div>

        <div class="card-body">
            <div class="row">

                <!-- API KEY -->
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">
                        API Key
                    </label>

                    <input type="text"
                           class="form-control"
                           name="api_key"
                           value="<?= $sett['api_key']; ?>"
                           placeholder="Masukkan API Key">

                    <small class="text-muted">
                        Digunakan oleh cron <b>auto_status.php</b>.
                    </small>
                </div>

                <!-- SECRET KEY -->
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">
                        Secret Key
                    </label>

                    <input type="password"
                           class="form-control"
                           name="secret_key"
                           value="<?= $sett['secret_key']; ?>"
                           placeholder="Masukkan Secret Key">

                    <small class="text-danger">
                        ⚠ Jangan bagikan key ini ke siapapun.
                    </small>
                </div>

            </div>

            <!-- INFO BOX -->
            <div class="alert alert-info mt-2 mb-0">
                <strong>ℹ️ Informasi:</strong><br>
                Payment gateway digunakan untuk otomatisasi deposit QRIS.
                Pastikan API aktif agar cron berjalan normal.
            </div>
<!-- ===== SUBMIT ===== -->
        <div class="col-md-12">
            <div class="form-group">
                <a href="javascript:;" onclick="btn_post();" id="btn_post" class="btn btn-glow btn-bg-gradient-x-blue-green float-right">
                    <i class="fa fa-save"></i> Simpan Pengaturan
                </a>
            </div>
        </div>
        </div>
    </div>
</div>

    </form>
</div></div></div></div></div>

<script type="text/javascript">
    $(window).keypress(function(e) { if (e.which == 13 && !$(e.target).is('textarea')) e.preventDefault(); });
    $('form').submit(function(e) { e.preventDefault(); btn_post(); });

    $('#referral_bonus').on('change', function() { kirimAjaxSwitch('referral_bonus=' + ($(this).is(':checked') ? 'on' : 'off')); });
    $('#withdraw_status').on('change', function() { kirimAjaxSwitch('withdraw_status=' + ($(this).is(':checked') ? 'on' : 'off')); });

    $('#referral_lvl2_status').on('change', function() {
        var val = $(this).is(':checked') ? 'on' : 'off';
        if (val == 'off' && $('#referral_lvl3_status').is(':checked')) {
            Swal.fire({
                title: 'Perhatian', icon: 'warning',
                html: 'Mematikan Level 2 akan otomatis mematikan <strong>Level 3</strong> juga. Lanjutkan?',
                showCancelButton: true, confirmButtonText: 'Ya, matikan keduanya!', cancelButtonText: 'Batal',
                customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
            }).then((r) => {
                if (r.isConfirmed) {
                    kirimAjaxSwitch('referral_lvl2_status=off');
                    kirimAjaxSwitch('referral_lvl3_status=off');
                    var sw3 = $('#referral_lvl3_status')[0].switchery; if (sw3) sw3.setPosition(false);
                } else {
                    var sw2 = $('#referral_lvl2_status')[0].switchery; if (sw2) sw2.setPosition(true);
                }
            });
            return;
        }
        kirimAjaxSwitch('referral_lvl2_status=' + val);
    });

    $('#referral_lvl3_status').on('change', function() {
        var val = $(this).is(':checked') ? 'on' : 'off';
        if (val == 'on' && !$('#referral_lvl2_status').is(':checked')) {
            Swal.fire({ icon: 'warning', title: 'Ups!', text: 'Level 3 tidak bisa diaktifkan jika Level 2 masih OFF.',
                customClass: { confirmButton: 'btn btn-warning' }, buttonsStyling: false });
            var sw3 = $('#referral_lvl3_status')[0].switchery; if (sw3) sw3.setPosition(false);
            return;
        }
        kirimAjaxSwitch('referral_lvl3_status=' + val);
    });

    $('#autowd').on('change', function() {
        var val = $(this).is(':checked') ? 'on' : 'off';
        Swal.fire({
            title: 'Konfirmasi', icon: 'question',
            html: 'Ubah Auto Withdraw menjadi <strong>' + (val=='on'?'AKTIF':'NONAKTIF') + '</strong>?<br><br>' +
                (val=='on' ? '<small class="text-success">✓ Transfer otomatis via KiraQRIS</small>'
                           : '<small class="text-warning">⚠ Proses manual admin</small>'),
            showCancelButton: true, confirmButtonText: 'Ya, Ubah!', cancelButtonText: 'Batal',
            customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-danger' }, buttonsStyling: false
        }).then((r) => {
            if (r.isConfirmed) { kirimAjaxSwitch('autowd=' + val); }
            else { var sw = $('#autowd')[0].switchery; if (sw) sw.setPosition(val=='on' ? false : true); }
        });
    });

    function setProfitMode(mode) {
        Swal.fire({
            title: 'Konfirmasi', icon: 'question',
            html: 'Ubah mode profit ke <strong>' + (mode=='profit' ? '🔒 Saldo Profit (Terkunci)' : '💰 Saldo Point (Bisa WD)') + '</strong>?',
            showCancelButton: true, confirmButtonText: 'Ya, Ubah!', cancelButtonText: 'Batal',
            customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-secondary' }, buttonsStyling: false
        }).then((r) => { if (r.isConfirmed) kirimAjaxSwitch('profit_mode=' + mode); });
    }

    function btn_post() {
        var formData = new FormData($('#form')[0]);
        formData.append('csrf_token', '<?= csrf_token(); ?>');
        $.ajax({
            type: 'POST', url: '<?= base_url('babikode/web/'); ?>', data: formData, contentType: false, processData: false,
            success: function(d) { $('#btn_post').removeClass('disabled'); $('#body-result').html(d); },
            error:   function()  { $('#btn_post').removeClass('disabled'); $('#body-result').html('<div class="alert alert-danger">Terjadi kesalahan sistem!</div>'); },
            beforeSend: function() {
                $('#btn_post').addClass('disabled');
                $('#body-result').html('<div class="progress mb-4"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%">Menyimpan Data...</div></div>');
            }
        });
    }

    function kirimAjaxSwitch(dataString) {
        $.ajax({
            type: 'POST', url: '<?= base_url('babikode/web/'); ?>', dataType: 'html',
            data: dataString + '&csrf_token=<?= csrf_token(); ?>',
            success: function(d) { $('#body-result').html(d); },
            error:   function()  { $('#body-result').html('<div class="alert alert-danger">Terjadi kesalahan!</div>'); }
        });
    }
</script>
<?php require '../../lib/footer_admin.php'; ?>