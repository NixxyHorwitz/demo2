<?php
require '../../mainconfig.php';

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(403);
        exit('No direct script access allowed!');
    }

    $id          = protect($_GET['id']);
    $data_target = $model->db_query($db, "*", "users", "id = '$id'");

    if (empty($data_target) || $data_target['count'] == 0) {
        $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
        require '../../lib/result.php';
        exit();
    }

    $user = $data_target['rows'];

    // Badge status
    $color  = 'blue-cyan';
    $status = 'Unverified';
    if ($user['status'] == 'Active') {
        $color  = 'blue-green';
        $status = 'Aktif';
    } elseif ($user['status'] == 'Inactive') {
        $color  = 'red-pink';
        $status = 'Nonaktif';
    }

    // Uplink level
    $ul_val   = $user['uplink_level'] ?? 'biasa';
    $ul_color = ($ul_val === 'promotor') ? 'orange-yellow' : 'blue-cyan';
    $ul_text  = ($ul_val === 'promotor') ? 'Promotor' : 'Biasa';

    // Data referrer (uplink)
    if (!empty($user['uplink'])) {
        $uplink_data = $model->db_query($db, "id, phone", "users", "id = '" . $user['uplink'] . "'");
        $uplink_phone = !empty($uplink_data['rows']['phone']) ? $uplink_data['rows']['phone'] : 'Tidak diketahui';
        $uplink_id    = $uplink_data['rows']['id'] ?? '-';
    } else {
        $uplink_phone = '— Tidak ada —';
        $uplink_id    = '-';
    }

    // Komisi info
    $commission_note = '';
    if (!empty($user['uplink'])) {
        $commission_note = ($user['is_commission'] == 1)
            ? '<span class="badge badge-success">Sudah dapat komisi</span>'
            : '<span class="badge badge-warning">Belum dapat komisi</span>';
    }

    // Total downline
    $total_downline = $model->db_query($db, "COUNT(*) AS total", "users", "uplink = '$id'")['rows']['total'] ?? 0;

    // Total withdraw sukses milik user ini
    $withdraw_amount = (float)($model->db_query($db, "SUM(amount) AS total", "withdraws", "user_id = '$id' AND status = 'Success'")['rows']['total'] ?? 0);

    // Format tanggal
    $created_at = $user['created_at'] ?? '';
    if (!empty($created_at)) {
        $tgl_dibuat = (function_exists('format_date')
            ? format_date(date: substr($created_at, 0, 10), print_day: true, short_month: true)
            : substr($created_at, 0, 10))
            . ' ' . substr($created_at, 11, 5);
    } else {
        $tgl_dibuat = '-';
    }
    ?>

    <div class="table-responsive">
        <table class="table table-bordered table-sm">

            <!-- INFO UMUM -->
            <tr>
                <th colspan="2" class="text-center bg-light">📊 INFORMASI UMUM</th>
            </tr>
            <tr>
                <th width="40%">Total Withdraw</th>
                <td>Rp <?= number_format($withdraw_amount, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <th>Total Downline</th>
                <td><span class="badge badge-info"><?= number_format($total_downline, 0, ',', '.'); ?> user</span></td>
            </tr>

            <!-- INFO AKUN -->
            <tr>
                <th colspan="2" class="text-center bg-light">👤 INFORMASI AKUN</th>
            </tr>
            <tr>
                <th>ID</th>
                <td><?= htmlspecialchars($user['id'], ENT_QUOTES); ?></td>
            </tr>
            <tr>
                <th>Telepon</th>
                <td><?= htmlspecialchars($user['phone'] ?? '-', ENT_QUOTES); ?></td>
            </tr>
            <tr>
                <th>IP Address</th>
                <td><?= htmlspecialchars($user['ip_address'] ?? '-', ENT_QUOTES); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="btn btn-glow btn-sm btn-bg-gradient-x-<?= $color; ?>"><?= $status; ?></span></td>
            </tr>
            <tr>
                <th>Uplink Level</th>
                <td><span class="btn btn-glow btn-sm btn-bg-gradient-x-<?= $ul_color; ?>"><?= $ul_text; ?></span></td>
            </tr>

            <!-- SALDO -->
            <tr>
                <th colspan="2" class="text-center bg-light">💰 SALDO</th>
            </tr>
            <tr>
                <th>Saldo Topup </th>
                <td>Rp <?= number_format($user['saldo'] ?? 0, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <th>Saldo Profit</th>
                <td>Rp <?= number_format($user['point'] ?? 0, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <th>Saldo Profit</th>
                <td>Rp <?= number_format($user['profit'] ?? 0, 0, ',', '.'); ?></td>
            </tr>

            <!-- INFO REKENING -->
            <tr>
                <th colspan="2" class="text-center bg-light">🏦 REKENING</th>
            </tr>
            <tr>
                <th>Bank / E-Wallet</th>
                <td><?= htmlspecialchars($user['rekening'] ?? '-', ENT_QUOTES); ?></td>
            </tr>
            <tr>
                <th>Kode Bank</th>
                <td><?= htmlspecialchars($user['bank_code'] ?? '-', ENT_QUOTES); ?></td>
            </tr>
            <tr>
                <th>Pemilik</th>
                <td><?= htmlspecialchars($user['pemilik'] ?? '-', ENT_QUOTES); ?></td>
            </tr>
            <tr>
                <th>No. Rekening</th>
                <td><?= htmlspecialchars($user['no_rek'] ?? '-', ENT_QUOTES); ?></td>
            </tr>

            <!-- INFO REFERRAL -->
            <tr>
                <th colspan="2" class="text-center bg-light">🔗 REFERRAL</th>
            </tr>
            <tr>
                <th>Uplink (Referrer)</th>
                <td>
                    <?php if (!empty($user['uplink'])): ?>
                        <small class="text-muted">ID: <?= $uplink_id; ?></small><br>
                        <?= htmlspecialchars($uplink_phone, ENT_QUOTES); ?>
                    <?php else: ?>
                        <span class="text-muted">— Tidak ada —</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Komisi</th>
                <td>
                    <?= !empty($commission_note) ? $commission_note : '<span class="text-muted">-</span>'; ?>
                </td>
            </tr>

            <!-- LAINNYA -->
            <tr>
                <th colspan="2" class="text-center bg-light">🕒 LAINNYA</th>
            </tr>
            <tr>
                <th>Tgl. Dibuat</th>
                <td><?= $tgl_dibuat; ?></td>
            </tr>

        </table>
    </div>

    <div class="form-group mb-0">
        <a href="javascript:;" onclick="modal_open('edit', 'md', '<?= base_url('babikode/user/?action=edit&id=' . $user['id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-orange-yellow">
            <i class="ft-edit mr-1"></i>Edit User
        </a>
        <button type="button" data-dismiss="modal" class="btn btn-glow btn-bg-gradient-x-purple-blue float-right">Tutup</button>
    </div>

<?php
} else {
    http_response_code(403);
    exit('No direct script access allowed!');
}
?>