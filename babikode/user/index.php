<?php
require '../../mainconfig.php';
require '../../lib/check_session_admin.php';
$page_type = 'users';
$page_name = 'Kelola Pengguna';

if (!function_exists('db_update_nullable')) {
    function db_update_nullable($db, $table, $data, $where) {
        $fields = [];
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $fields[] = "`$key` = NULL";
            } else {
                $fields[] = "`$key` = '" . mysqli_real_escape_string($db, $value) . "'";
            }
        }
        $sql = "UPDATE `$table` SET " . implode(", ", $fields) . " WHERE $where";
        return mysqli_query($db, $sql);
    }
}

$action = isset($_GET['action']) ? protect(trim($_GET['action'])) : '';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$is_json_action = in_array($action, ['admin_login', 'delete']);

if ($is_json_action) {
    header('Content-Type: application/json');

    if ($action === 'admin_login') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['ajax_action'] ?? '') !== 'admin_login') {
            echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
        }
        $user_id = protect(trim($_POST['user_id'] ?? ''));
        if (empty($user_id)) { echo json_encode(['success' => false, 'message' => 'User ID tidak valid']); exit; }
        $check = $model->db_query($db, "*", "users", "id = '$user_id'");
        if ($check['count'] == 0) { echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']); exit; }
        $u        = $check['rows'];
        $uniqueid = time();
        $payload  = [
            "id"   => $u['id'],
            "sign" => hash_hmac('sha256', $u['id'] . $uniqueid, $config['hmac']['key']),
            "exp"  => time() + \Firebase\JWT\JWT::$leeway + 86400
        ];
        $jwt = \Firebase\JWT\JWT::encode($payload, $config['jwt']['secret']);
        setcookie('X_SESSION', $jwt, time() + 86400, '/');
        $model->db_update($db, "users", ['x_session' => $jwt, 'x_uniqueid' => $uniqueid], "id = '" . $u['id'] . "'");
        if (isset($_COOKIE['X_SESSION_ADMIN'])) setcookie('X_SESSION_ADMIN', '', time() - 3600, '/');
        echo json_encode(['success' => true, 'message' => 'Login berhasil', 'redirect' => base_url()]); exit;
    }

    if ($action === 'delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['ajax_action'] ?? '') !== 'delete_user') {
            echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
        }
        $user_id = protect(trim($_POST['id'] ?? ''));
        if (empty($user_id)) { echo json_encode(['success' => false, 'message' => 'User ID tidak valid']); exit; }
        $check = $model->db_query($db, "id", "users", "id = '$user_id'");
        if ($check['count'] == 0) { echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']); exit; }
        $del = mysqli_query($db, "DELETE FROM users WHERE id = '" . mysqli_real_escape_string($db, $user_id) . "'");
        echo $del
            ? json_encode(['success' => true,  'message' => 'User berhasil dihapus'])
            : json_encode(['success' => false, 'message' => 'Gagal: ' . mysqli_error($db)]);
        exit;
    }
}

if ($is_ajax && in_array($action, ['status', 'uplink_level', 'add', 'edit'])) {
    require '../../lib/check_session_ajax_admin.php';

    // --- status ---
    if ($action === 'status') {
        $valid = ['Active', 'Inactive', 'Unverified'];
        if (!isset($_GET['id'], $_GET['status']) || empty($_GET['id']) || !in_array($_GET['status'], $valid)) {
            exit(header("HTTP/1.1 403 No direct script access allowed!"));
        }
        $target = $model->db_query($db, "*", "users", "id = '" . protect($_GET['id']) . "'");
        if ($target['count'] == 1) {
            $upd = $model->db_update($db, "users", ['status' => $_GET['status']], "id = '" . $target['rows']['id'] . "'");
            $result_msg = $upd
                ? ['response' => 'success', 'msg' => 'Status berhasil diubah.']
                : ['response' => 'error',   'msg' => 'Terjadi kesalahan server.'];
        } else {
            $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
        }
        require '../../lib/result.php'; exit;
    }

    // --- uplink_level ---
    if ($action === 'uplink_level') {
        $valid = ['promotor', 'biasa', 'demo'];
        if (!isset($_GET['id'], $_GET['uplink_level']) || empty($_GET['id']) || !in_array($_GET['uplink_level'], $valid)) {
            exit(header("HTTP/1.1 403 No direct script access allowed!"));
        }
        $target = $model->db_query($db, "id", "users", "id = '" . protect($_GET['id']) . "'");
        if ($target['count'] == 1) {
            $upd = $model->db_update($db, "users", ['uplink_level' => $_GET['uplink_level']], "id = '" . $target['rows']['id'] . "'");
            $result_msg = $upd
                ? ['response' => 'success', 'msg' => 'Uplink level berhasil diubah.']
                : ['response' => 'error',   'msg' => 'Terjadi kesalahan server.'];
        } else {
            $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
        }
        require '../../lib/result.php'; exit;
    }

    // ================================================================
    // --- ADD ---
    // ================================================================
    if ($action === 'add') {
        if ($_POST) {
            if (!$csrf_token) {
                $result_msg = ['response' => 'error', 'msg' => 'Permintaan tidak diterima.'];
                require '../../lib/result.php'; exit;
            }

            // ── [USERNAME] ambil & validasi ──
            $username     = trim(protect($_POST['username'] ?? ''));
            $phone        = protect(trim($_POST['phone'] ?? ''));
            $password     = trim($_POST['password'] ?? '');
            $point        = (int)protect(trim($_POST['point'] ?? '0'));
            $uplink       = !empty($_POST['uplink']) ? (int)protect(trim($_POST['uplink'])) : null;
            $uplink_level = in_array($_POST['uplink_level'] ?? '', ['promotor', 'biasa'])
                            ? protect(trim($_POST['uplink_level'])) : 'biasa';

            if (empty($phone)) {
                $result_msg = ['response' => 'error', 'msg' => 'No. HP wajib diisi.'];
            } elseif (empty($password) || strlen($password) < 6) {
                $result_msg = ['response' => 'error', 'msg' => 'Password wajib diisi, minimal 6 karakter.'];
            } else {
                // Cek duplikat phone
                $cek_phone = $model->db_query($db, "id", "users", "phone = '" . mysqli_real_escape_string($db, $phone) . "'");
                if ($cek_phone['count'] > 0) {
                    $result_msg = ['response' => 'error', 'msg' => 'Nomor HP sudah terdaftar.'];
                }
                // ── [USERNAME] cek duplikat username jika diisi ──
                elseif (!empty($username) && $model->db_query($db, "id", "users", "username = '" . mysqli_real_escape_string($db, $username) . "'")['count'] > 0) {
                    $result_msg = ['response' => 'error', 'msg' => 'Username sudah digunakan.'];
                } else {
                    $ins = $model->db_insert($db, "users", [
                        'username'     => !empty($username) ? $username : null, // ── [USERNAME] ──
                        'phone'        => $phone,
                        'password'     => password_hash($password, PASSWORD_DEFAULT),
                        'point'        => $point,
                        'profit'       => 0,
                        'uplink_level' => $uplink_level,
                        'uplink'       => $uplink,
                        'status'       => 'Active',
                        'created_at'   => date('Y-m-d H:i:s')
                    ]);
                    $result_msg = $ins
                        ? ['response' => 'success', 'msg' => 'User berhasil ditambahkan.']
                        : ['response' => 'error',   'msg' => 'Terjadi kesalahan server.'];
                }
            }
            require '../../lib/result.php';
        } else { ?>
            <form class="form-horizontal" id="form-add">
                <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">

                <div class="row">
                    <!-- ── [USERNAME] field baru ── -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Username <small class="text-muted">(opsional)</small></label>
                            <input type="text" class="form-control" name="username"
                                   placeholder="Contoh: johndoe"
                                   autocomplete="off">
                            <small class="text-muted">Harus unik. Kosongkan jika tidak diperlukan.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>No. WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" placeholder="628xxxxxxxxxx">
                            <small class="text-muted">Format internasional, contoh: 6281234567890</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="password" placeholder="Min. 6 karakter">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Saldo Utama</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                <input type="number" class="form-control" name="point" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Uplink Level</label>
                            <select class="form-control" name="uplink_level">
                                <option value="biasa">Biasa</option>
                                <option value="demo">Demo</option>
                                <option value="promotor">Promotor</option>
                                
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>ID Uplink <small class="text-muted">(opsional)</small></label>
                    <input type="number" class="form-control" name="uplink" placeholder="Kosongkan jika tidak ada">
                </div>
                <div id="modal-result"></div>
                <div class="form-group text-right mb-0">
                    <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink">
                        <i class="ft-rotate-ccw mr-1"></i>Ulangi
                    </button>
                    <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green"
                        onclick="btn_post('#form-add', '<?= base_url('babikode/user/?action=add'); ?>');">
                        <i class="ft-plus mr-1"></i>Tambah
                    </button>
                </div>
            </form>
        <?php }
        exit;
    }

    // ================================================================
    // --- EDIT ---
    // ================================================================
    if ($action === 'edit') {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            exit(header("HTTP/1.1 403 No direct script access allowed!"));
        }
        $target = $model->db_query($db, "*", "users", "id = '" . protect($_GET['id']) . "'");
        if ($target['count'] == 0) {
            $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
            require '../../lib/result.php'; exit;
        }
        $u = $target['rows'];

        if ($_POST) {
            if (!$csrf_token) {
                $result_msg = ['response' => 'error', 'msg' => 'Permintaan tidak diterima.'];
                require '../../lib/result.php'; exit;
            }

            $phone    = protect(trim($_POST['phone'] ?? ''));
            // ── [USERNAME] ambil dari POST ──
            $username = trim(protect($_POST['username'] ?? ''));

            if (empty($phone)) {
                $result_msg = ['response' => 'error', 'msg' => 'No. HP wajib diisi.'];
                require '../../lib/result.php'; exit;
            }

            // ── [USERNAME] cek duplikat, kecualikan user ini sendiri ──
            if (!empty($username)) {
                $cek_uname = mysqli_query($db,
                    "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($db, $username) . "'
                     AND id != '" . (int)$u['id'] . "' LIMIT 1"
                );
                if (mysqli_num_rows($cek_uname) > 0) {
                    $result_msg = ['response' => 'error', 'msg' => 'Username sudah digunakan oleh user lain.'];
                    require '../../lib/result.php'; exit;
                }
            }

            $input = [
                'username'     => !empty($username) ? $username : null, // ── [USERNAME] ──
                'phone'        => $phone,
                'uplink_level' => in_array($_POST['uplink_level'] ?? '', ['promotor', 'biasa'])
                                  ? protect(trim($_POST['uplink_level'])) : 'biasa',
                'rekening'     => ($_POST['rekening']  !== '') ? protect(trim($_POST['rekening']))  : null,
                'bank_code'    => ($_POST['bank_code'] !== '') ? protect(trim($_POST['bank_code'])) : null,
                'pemilik'      => ($_POST['pemilik']   !== '') ? protect(trim($_POST['pemilik']))   : null,
                'no_rek'       => ($_POST['no_rek']    !== '') ? protect(trim($_POST['no_rek']))    : null,
                'saldo'        => protect(trim($_POST['saldo']   ?? '0')),
                'point'        => protect(trim($_POST['point']   ?? '0')),
                'profit'       => protect(trim($_POST['profit']  ?? '0')),
                'wd_product_id' => isset($_POST['wd_product_id']) ? (int) $_POST['wd_product_id'] : 0,
            ];

            if ((float)$input['point'] < (float)$u['point']) {
                $selisih = $u['point'] - $input['point'];
                $model->db_insert($db, "point_logs", [
                    'user_id'     => $u['id'],
                    'type'        => 'minus',
                    'amount'      => $selisih,
                    'description' => 'Dipotong Admin Sebesar ' . $selisih . ' saldo.',
                    'created_at'  => date('Y-m-d H:i:s')
                ]);
            }

            $upd = db_update_nullable($db, "users", $input, "id = '" . $u['id'] . "'");
            $result_msg = $upd
                ? ['response' => 'success', 'msg' => 'Data berhasil diubah.']
                : ['response' => 'error',   'msg' => 'Terjadi kesalahan server.'];
            require '../../lib/result.php';
        } else { ?>
            <form class="form-horizontal" id="form-edit">
                <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">

                <div class="row">
                    <!-- ── [USERNAME] field baru ── -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Username <small class="text-muted">(opsional)</small></label>
                            <input type="text" class="form-control" name="username"
                                   value="<?= htmlspecialchars($u['username'] ?? ''); ?>"
                                   placeholder="Kosongkan untuk hapus username">
                            <small class="text-muted">Harus unik. Kosongkan untuk menghapus.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" class="form-control" name="phone"
                                   value="<?= htmlspecialchars($u['phone']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Uplink Level</label>
                    <select class="form-control" name="uplink_level">
                        <option value="biasa"    <?= ($u['uplink_level'] == 'biasa')    ? 'selected' : ''; ?>>Biasa</option>
                        <option value="demo"    <?= ($u['uplink_level'] == 'demo')    ? 'selected' : ''; ?>>Demo</option>
                        <option value="promotor" <?= ($u['uplink_level'] == 'promotor') ? 'selected' : ''; ?>>Promotor</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Bank / E-Wallet</label>
                            <input type="text" class="form-control" name="rekening"
                                   value="<?= htmlspecialchars($u['rekening'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Kode Bank</label>
                            <input type="text" class="form-control" name="bank_code"
                                   value="<?= htmlspecialchars($u['bank_code'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Pemilik Rekening</label>
                            <input type="text" class="form-control" name="pemilik"
                                   value="<?= htmlspecialchars($u['pemilik'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>No. Rekening</label>
                            <input type="text" class="form-control" name="no_rek"
                                   value="<?= htmlspecialchars($u['no_rek'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">

    <div class="col-md-4">
        <div class="form-group">
            <label>Saldo Topup</label>
            <div class="input-group">
                <div class="input-group-prepend">
                </div>
                <input type="number" class="form-control" name="saldo" value="<?= $u['saldo']; ?>">
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label>Saldo Point</label>
            <div class="input-group">
                <div class="input-group-prepend">
                </div>
                <input type="number" class="form-control" name="point" value="<?= $u['point']; ?>">
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-group">
            <label>Saldo Profit</label>
            <div class="input-group">
                <div class="input-group-prepend">
                </div>
                <input type="number" class="form-control" name="profit" value="<?= $u['profit']; ?>">
            </div>
        </div>
    </div>

</div>

                <div class="form-group">
                <label>Produk Wajib Untuk Withdraw</label>
                
                <select class="form-control" name="wd_product_id">
                
                <option value="0">Ikuti Pengaturan Sistem</option>
                
                <?php
                $products = mysqli_query($db, "SELECT id,nama_produk,harga  FROM produk_investasi ORDER BY nama_produk ASC");
                while ($p = mysqli_fetch_assoc($products)) {
                
                $selected = ($u['wd_product_id'] == $p['id']) ? 'selected' : '';
                
                echo '<option value="'.$p['id'].'" '.$selected.'>(ID '.$p['id'].') '.$p['nama_produk'].' - Rp '.number_format($p['harga'],0,',','.').'</option>';
                
                }
                ?>
                
                </select>
                
                <small class="text-muted">
                Jika memilih produk, pengguna harus membeli produk tersebut sebelum withdraw.
                </small>
                
                </div>
                <div id="modal-result"></div>
                <div class="form-group text-right mb-0">
                    <button type="reset" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink">
                        <i class="ft-rotate-ccw mr-1"></i>Ulangi
                    </button>
                    <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green"
                        onclick="btn_post('#form-edit', '<?= base_url('babikode/user/?action=edit&id=' . $u['id']); ?>');">
                        <i class="ft-save mr-1"></i>Simpan
                    </button>
                </div>
            </form>
        <?php }
        exit;
    }
}

// ============================================================
// FRONTEND
// ============================================================
require '../../lib/header_admin.php';

$q_status     = isset($_GET['status'])     ? protect($_GET['status'])     : '';
$q_row        = isset($_GET['row'])        ? protect($_GET['row'])        : '';
$q_start_date = isset($_GET['start_date']) ? protect($_GET['start_date']) : '';
$q_end_date   = isset($_GET['end_date'])   ? protect($_GET['end_date'])   : '';
$q_search     = isset($_GET['search'])     ? protect($_GET['search'])     : '';
?>

<div class="content-header row">
    <div class="content-header-left col-md-4 col-12 mb-2">
        <h3 class="content-header-title"><?= $page_name; ?></h3>
    </div>
    <div class="content-header-right col-md-8 col-12">
        <div class="breadcrumbs-top float-md-right">
            <div class="breadcrumb-wrapper mr-1">
               
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title float-left"><?= $page_name; ?></h4>
                    <a href="javascript:;" onclick="modal_open('add', 'md', '<?= base_url('babikode/user/?action=add'); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-blue float-right">
                        <b><i class="ft-plus mr-1"></i>Tambah</b>
                    </a>
                </div>
                <div class="card-body pt-0">
                    <div id="body-result"></div>
                    <?php require '../../lib/flash_message.php'; ?>

                    <!-- Filter Status -->
                    <div class="btn-group flex-wrap">
                        <a href="<?= base_url('babikode/user/'); ?>" class="btn btn-glow btn-primary <?= empty($q_status) ? 'active' : ''; ?>">Semua</a>
                        <a href="<?= base_url('babikode/user/?status=active'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'active') ? 'active' : ''; ?>">Aktif</a>
                        <a href="<?= base_url('babikode/user/?status=inactive'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'inactive') ? 'active' : ''; ?>">Nonaktif</a>
                        <a href="<?= base_url('babikode/user/?status=unverified'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'unverified') ? 'active' : ''; ?>">Unverified</a>
                    </div>

                    <!-- Form Filter -->
                    <form method="get" class="row mt-2">
                        <?php if (!empty($q_status)) { ?>
                            <input type="hidden" name="status" id="q_status" value="<?= $q_status; ?>">
                        <?php } ?>
                        <div class="col-md-3">
                            <div class="input-group mb-2">
                                <div class="input-group-prepend"><span class="input-group-text">Tampilkan</span></div>
                                <select class="form-control" name="row" id="table-row">
                                    <option value="10"  <?= ($q_row == '10')  ? 'selected' : ''; ?>>10</option>
                                    <option value="25"  <?= ($q_row == '25')  ? 'selected' : ''; ?>>25</option>
                                    <option value="50"  <?= ($q_row == '50')  ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?= ($q_row == '100') ? 'selected' : ''; ?>>100</option>
                                </select>
                                <div class="input-group-append"><span class="input-group-text">baris.</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-2">
                                <input type="date" class="form-control" name="start_date" id="table-start-date" value="<?= $q_start_date; ?>">
                                <div class="input-group-prepend"><span class="input-group-text">sampai</span></div>
                                <input type="date" class="form-control" name="end_date" id="table-end-date" value="<?= $q_end_date; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-glow btn-bg-gradient-x-purple-blue" type="submit"><i class="ft-filter"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group mb-2">
                                <!-- ── [USERNAME] placeholder diperbarui ── -->
                                <input type="text" class="form-control" name="search" id="table-search"
                                       value="<?= $q_search; ?>"
                                       placeholder="Cari username / nomor / ID...">
                                <div class="input-group-append">
                                    <button class="btn btn-glow btn-bg-gradient-x-purple-blue" type="submit"><i class="ft-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Tabel -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr class="text-uppercase">
                                    <th>ID</th>
                                    <th>Username</th><!-- ── [USERNAME] kolom baru ── -->
                                    <th>Pengguna (Telepon)</th>
                                    <th>IP</th>
                                    <th>Reff Dari</th>
                                    <th>Total Uplink</th>
                                    <th>Saldo Topup</th>
                                    <th>Saldo Point</th>
                                    <th>Saldo Profit</th>
                                    <th>Uplink Level</th>
                                    <th>Status</th>
                                    <th>Tgl. Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $records_per_page = (in_array($q_row, ['10','25','50','100'])) ? (int)$q_row : 10;

                                $query_list = "SELECT * FROM users WHERE id <> ''";

                                if (!empty($q_status) && in_array($q_status, ['active','inactive','unverified'])) {
                                    $query_list .= " AND status = '" . ucfirst($q_status) . "'";
                                }
                                if (!empty($q_start_date) && !empty($q_end_date)) {
                                    $query_list .= " AND DATE(created_at) BETWEEN '$q_start_date' AND '$q_end_date'";
                                } elseif (!empty($q_start_date)) {
                                    $query_list .= " AND DATE(created_at) = '$q_start_date'";
                                } elseif (!empty($q_end_date)) {
                                    $query_list .= " AND DATE(created_at) = '$q_end_date'";
                                }
                                if (!empty($q_search)) {
                                    $esc = mysqli_real_escape_string($db, $q_search);
                                    // ── [USERNAME] tambahkan username ke pencarian ──
                                    $query_list .= " AND (phone LIKE '%$esc%' OR id LIKE '%$esc%' OR uplink LIKE '%$esc%' OR username LIKE '%$esc%')";
                                }
                                $query_list .= " ORDER BY id DESC";

                                $start_pos = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * $records_per_page : 0;
                                $paged     = mysqli_query($db, $query_list . " LIMIT $start_pos, $records_per_page");

                                if (mysqli_num_rows($paged) == 0) {
                                    echo '<tr><td colspan="13" class="text-center">Data belum tersedia.</td></tr>';
                                }

                                while ($user = mysqli_fetch_assoc($paged)):
                                    $total_uplink = $model->db_query($db, "COUNT(*) AS total", "users", "uplink = '" . $user['id'] . "'")['rows']['total'];
                                    $uplink_id    = $user['uplink'];
                                    $uplinkData   = !empty($uplink_id) ? $model->db_query($db, "*", "users", "id = '$uplink_id'")['rows'] : [];
                                    $wd_uplink    = !empty($uplink_id)
                                        ? ($model->db_query($db, "SUM(amount) AS total", "withdraws", "user_id = '$uplink_id' AND status = 'Success'")['rows']['total'] ?? 0)
                                        : 0;

                                    $color  = match($user['status']) { 'Active' => 'blue-green', 'Inactive' => 'red-pink', default => 'blue-cyan' };
                                    $status = match($user['status']) { 'Active' => 'Aktif', 'Inactive' => 'Nonaktif', default => 'Unverified' };

                                   $ul_val   = $user['uplink_level'] ?? 'biasa';

                                    if ($ul_val === 'promotor') {
                                        $ul_color = 'orange-yellow';
                                        $ul_text  = 'Promotor';
                                    } elseif ($ul_val === 'demo') {
                                        $ul_color = 'gray';       // warna bisa kamu sesuaikan
                                        $ul_text  = 'Demo';
                                    } else {
                                        $ul_color = 'blue-cyan';
                                        $ul_text  = 'Biasa';
                                    }
                                ?>
                                    <tr>
                                        <td><?= $user['id']; ?></td>

                                        <!-- ── [USERNAME] kolom baru di tbody ── -->
                                        <td>
                                            <?php if (!empty($user['username'])): ?>
                                                <span class="badge badge-secondary">
                                                    <?= htmlspecialchars($user['username']); ?>
                                                </span>
                                            <?php else: ?>
                                                <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-nowrap">
                                            <a href="javascript:;" onclick="modal_open('detail', 'md', '<?= base_url('babikode/user/detail?id=' . $user['id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-red">
                                                <?= htmlspecialchars($user['phone']); ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($user['ip_address'] ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($uplinkData)): ?>
                                                <small class="text-muted">ID: <?= $uplinkData['id'] ?? '-'; ?></small><br>
                                                <!-- ── [USERNAME] tampilkan username uplink jika ada ── -->
                                                <?php if (!empty($uplinkData['username'])): ?>
                                                    <small class="font-weight-bold"><?= htmlspecialchars($uplinkData['username']); ?></small><br>
                                                <?php endif; ?>
                                                <small><?= htmlspecialchars($uplinkData['phone'] ?? '-'); ?></small><br>
                                                <small class="text-success">WD: Rp <?= number_format($wd_uplink, 0, ',', '.'); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">— Tidak ada —</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-info"><?= number_format($total_uplink, 0, ',', '.'); ?> user</span></td>
                                        <td>Rp <?= number_format($user['saldo'],   0, ',', '.'); ?></td>
                                        <td>Rp <?= number_format($user['point'],   0, ',', '.'); ?></td>
                                        <td>Rp <?= number_format($user['profit'],  0, ',', '.'); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-<?= $ul_color; ?> dropdown-toggle" data-toggle="dropdown"><?= $ul_text; ?></button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="get_data('<?= base_url('babikode/user/?action=uplink_level&id=' . $user['id'] . '&uplink_level=biasa'); ?>');">Biasa</a>
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="get_data('<?= base_url('babikode/user/?action=uplink_level&id=' . $user['id'] . '&uplink_level=demo'); ?>');">Demo</a>
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="get_data('<?= base_url('babikode/user/?action=uplink_level&id=' . $user['id'] . '&uplink_level=promotor'); ?>');">Promotor</a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-<?= $color; ?> dropdown-toggle" data-toggle="dropdown"><?= $status; ?></button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="get_data('<?= base_url('babikode/user/?action=status&id=' . $user['id'] . '&status=Active'); ?>');">Aktif</a>
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="get_data('<?= base_url('babikode/user/?action=status&id=' . $user['id'] . '&status=Inactive'); ?>');">Nonaktif</a>
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="get_data('<?= base_url('babikode/user/?action=status&id=' . $user['id'] . '&status=Unverified'); ?>');">Unverified</a>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= format_date(date: substr($user['created_at'], 0, 10), print_day: true, short_month: true) . ' ' . substr($user['created_at'], 11, 5); ?></td>
                                        <td class="text-nowrap">
                                            <a href="javascript:void(0);" onclick="modal_open('edit', 'md', '<?= base_url('babikode/user/?action=edit&id=' . $user['id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-orange-yellow"><b><i class="ft-edit mr-1"></i>Ubah</b></a>
                                            <a href="javascript:void(0);" onclick="loginAsUser('<?= $user['id']; ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-cyan"><b><i class="ft-log-in mr-1"></i>Login</b></a>
                                            <a href="javascript:void(0);" onclick="confirmDelete('<?= $user['id']; ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><b><i class="ft-trash mr-1"></i>Hapus</b></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="card-footer">
                    <div class="row justify-content-center">
                        <div class="col-md-12">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-md justify-content-center">
                                    <?php
                                    $count_result  = mysqli_query($db, $query_list);
                                    $total_records = mysqli_num_rows($count_result);
                                    $url           = base_url('babikode/user');

                                    if ($total_records > 0) {
                                        $post_data = '';
                                        if (!empty($q_status))     $post_data .= "&status=$q_status";
                                        if (!empty($q_row))        $post_data .= "&row=$q_row";
                                        if (!empty($q_start_date)) $post_data .= "&start_date=$q_start_date";
                                        if (!empty($q_end_date))   $post_data .= "&end_date=$q_end_date";
                                        if (!empty($q_search))     $post_data .= "&search=$q_search";

                                        $total_pages  = ceil($total_records / $records_per_page);
                                        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

                                        if ($current_page > 1) {
                                            $prev = $current_page - 1;
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="' . $url . '?page=1' . $post_data . '">&lsaquo; First</a></li>';
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="' . $url . '?page=' . $prev . $post_data . '">&laquo;</a></li>';
                                        }
                                        $range = 2;
                                        for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++) {
                                            $active = ($i == $current_page) ? ' active' : '';
                                            echo '<li class="page-item pull-up shadow' . $active . '"><a class="page-link" href="' . $url . '?page=' . $i . $post_data . '">' . $i . '</a></li>';
                                        }
                                        if ($current_page < $total_pages) {
                                            $next = $current_page + 1;
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="' . $url . '?page=' . $next . $post_data . '">&raquo;</a></li>';
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="' . $url . '?page=' . $total_pages . $post_data . '">Last &rsaquo;</a></li>';
                                        }
                                    }
                                    ?>
                                </ul>
                            </nav>
                        </div>
                        <span class="btn btn-glow btn-bg-gradient-x-blue-green btn-sm justify-content-center pull-up">
                            Total data: <b><?= number_format($total_records ?? 0, 0, ',', '.'); ?></b>
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    function loginAsUser(userId) {
        if (confirm('Anda akan login sebagai user ini. Lanjutkan?')) {
            $.ajax({
                url: '<?= base_url('babikode/user/?action=admin_login'); ?>',
                type: 'POST',
                dataType: 'json',
                data: { user_id: userId, ajax_action: 'admin_login' },
                success: function(r) {
                    if (r.success) { alert('Login berhasil. Mengalihkan...'); window.location.href = '<?= base_url(); ?>'; }
                    else alert('Gagal: ' + (r.message || 'Terjadi kesalahan'));
                },
                error: function() { alert('Error: Gagal menghubungi server'); }
            });
        }
    }

    function confirmDelete(userId) {
        if (confirm('Apakah Anda yakin ingin menghapus user ini?')) {
            $.ajax({
                url: '<?= base_url('babikode/user/?action=delete'); ?>',
                type: 'POST',
                dataType: 'json',
                data: { id: userId, ajax_action: 'delete_user' },
                success: function(r) {
                    if (r.success) { alert('User berhasil dihapus'); location.reload(); }
                    else alert('Gagal: ' + (r.message || 'Terjadi kesalahan'));
                },
                error: function() { alert('Error: Gagal menghubungi server'); }
            });
        }
    }

    $(function() {
        $('#table-row').on('change', function() {
            var row  = $(this).val(), search = $('#table-search').val(),
                sd   = $('#table-start-date').val(), ed = $('#table-end-date').val(),
                stat = $('#q_status').val() || '';
            var url  = '<?= base_url('babikode/user/'); ?>?row=' + row + '&start_date=' + sd + '&end_date=' + ed + '&search=' + search;
            if (stat) url += '&status=' + stat;
            window.location = url;
        });
    });

    $(window).keypress(function(e) {
        if (e.which == 13 && !$(e.target).is('textarea')) e.preventDefault();
    });

    $('form').submit(function() { $(':submit').attr('disabled', true); });
</script>

<?php require '../../lib/footer_admin.php'; ?>