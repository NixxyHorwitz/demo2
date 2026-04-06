<?php
require '../../mainconfig.php';

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require '../../lib/check_session_ajax_admin.php';

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(403);
        exit('No direct script access allowed!');
    }

    $id = protect($_GET['id']);

    /* ── Ambil data order ── */
    $data_target = $model->db_query($db, "*", "orders", "id = '$id'");

    if (empty($data_target) || $data_target['count'] == 0) {
        $result_msg = ['response' => 'error', 'msg' => 'Data tidak ditemukan.'];
        require '../../lib/result.php';
        exit();
    }

    $order = $data_target['rows'];

    /* ── Ambil data user pemilik order ── */
    $user_data  = $model->db_query($db, "id, phone, username, status", "users", "id = '" . (int)$order['user_id'] . "'");
    $user       = $user_data['rows'] ?? [];
    $user_phone = $user['phone']    ?? '-';
    $user_name  = $user['username'] ?? '-';

    /* ── Badge status order ── */
    $status = $order['status'] ?? 'Active';
    $status_color = match($status) {
        'Active'    => 'blue-green',
        'Completed' => 'blue-cyan',
        'Cancelled' => 'red-pink',
        default     => 'blue-cyan',
    };
    $status_label = match($status) {
        'Active'    => 'Aktif',
        'Completed' => 'Selesai',
        'Cancelled' => 'Dibatalkan',
        default     => $status,
    };

    /* ── Hitung data turunan ── */
    $harga         = (float)$order['harga'];
    $profit_harian = (float)$order['profit_harian'];
    $masa_aktif    = (int)$order['masa_aktif'];
    $total_profit  = (float)$order['total_profit'];

    $created_ts   = strtotime($order['created_at']);
    $hari_jalan   = max(0, (int)floor((time() - $created_ts) / 86400));
    $hari_sisa    = max(0, $masa_aktif - $hari_jalan);
    $progress_pct = min(100, $masa_aktif > 0 ? round($hari_jalan / $masa_aktif * 100) : 0);
    $profit_terkumpul = min($total_profit, $profit_harian * $hari_jalan);

    $tgl_mulai   = date('d M Y H:i', $created_ts);
    $tgl_selesai = date('d M Y', strtotime($order['created_at'] . " +{$masa_aktif} days"));

    /* ── Format tanggal created ── */
    $created_at = $order['created_at'] ?? '';
    $tgl_dibuat = !empty($created_at)
        ? (function_exists('format_date')
            ? format_date(date: substr($created_at, 0, 10), print_day: true, short_month: true)
            : substr($created_at, 0, 10))
          . ' ' . substr($created_at, 11, 5)
        : '-';

?>

<div class="table-responsive">
    <table class="table table-bordered table-sm">

        <!-- INFORMASI UMUM -->
        <tr>
            <th colspan="2" class="text-center bg-light">📊 INFORMASI UMUM</th>
        </tr>
        <tr>
            <th width="40%">Progress</th>
            <td>
                <div style="background:#eee;border-radius:4px;height:8px;overflow:hidden;margin-bottom:4px;">
                    <div style="background:#28a745;height:100%;width:<?= $progress_pct ?>%;"></div>
                </div>
                <small class="text-muted"><?= $hari_jalan ?> / <?= $masa_aktif ?> hari (<?= $progress_pct ?>%)</small>
            </td>
        </tr>
        <tr>
            <th>Profit Terkumpul</th>
            <td><strong style="color:#28a745;">Rp <?= number_format($profit_terkumpul, 0, ',', '.') ?></strong></td>
        </tr>
        <tr>
            <th>Sisa Hari</th>
            <td><?= $hari_sisa ?> hari lagi</td>
        </tr>

        <!-- INFORMASI ORDER -->
        <tr>
            <th colspan="2" class="text-center bg-light">📦 INFORMASI ORDER</th>
        </tr>
        <tr>
            <th>ID Order</th>
            <td>#<?= htmlspecialchars($order['id'], ENT_QUOTES) ?></td>
        </tr>
        <tr>
            <th>Nama Produk</th>
            <td><?= htmlspecialchars($order['nama_produk'], ENT_QUOTES) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="btn btn-glow btn-sm btn-bg-gradient-x-<?= $status_color ?>"><?= $status_label ?></span></td>
        </tr>
        <tr>
            <th>Tipe</th>
            <td><?= !empty($order['type']) ? htmlspecialchars($order['type'], ENT_QUOTES) : '<span class="text-muted">Reguler</span>' ?></td>
        </tr>

        <!-- INFORMASI KEUANGAN -->
        <tr>
            <th colspan="2" class="text-center bg-light">💰 INFORMASI KEUANGAN</th>
        </tr>
        <tr>
            <th>Harga Paket</th>
            <td>Rp <?= number_format($harga, 0, ',', '.') ?></td>
        </tr>
        <tr>
            <th>Profit per Hari</th>
            <td><strong style="color:#28a745;">Rp <?= number_format($profit_harian, 0, ',', '.') ?></strong></td>
        </tr>
        <tr>
            <th>Durasi</th>
            <td><?= $masa_aktif ?> Hari</td>
        </tr>
        <tr>
            <th>Persentase Return</th>
            <td><?= htmlspecialchars($order['persentase'] ?? '0', ENT_QUOTES) ?>%</td>
        </tr>
        <tr>
            <th>Total Profit</th>
            <td><strong style="color:#28a745;">Rp <?= number_format($total_profit, 0, ',', '.') ?></strong></td>
        </tr>
        <tr>
            <th>Total Kembali</th>
            <td><strong>Rp <?= number_format($harga + $total_profit, 0, ',', '.') ?></strong></td>
        </tr>

        <!-- INFORMASI USER -->
        <tr>
            <th colspan="2" class="text-center bg-light">👤 INFORMASI USER</th>
        </tr>
        <tr>
            <th>User ID</th>
            <td><?= htmlspecialchars($order['user_id'], ENT_QUOTES) ?></td>
        </tr>
        <tr>
            <th>Username</th>
            <td><?= htmlspecialchars($user_name, ENT_QUOTES) ?></td>
        </tr>
        <tr>
            <th>Telepon</th>
            <td><?= htmlspecialchars($user_phone, ENT_QUOTES) ?></td>
        </tr>

        <!-- INFORMASI WAKTU -->
        <tr>
            <th colspan="2" class="text-center bg-light">🕒 INFORMASI WAKTU</th>
        </tr>
        <tr>
            <th>Tgl. Dibuat</th>
            <td><?= $tgl_dibuat ?></td>
        </tr>
        <tr>
            <th>Tgl. Mulai</th>
            <td><?= $tgl_mulai ?></td>
        </tr>
        <tr>
            <th>Tgl. Selesai (Est.)</th>
            <td><?= $tgl_selesai ?></td>
        </tr>

    </table>
</div>

<div class="form-group mb-0">
    <a href="javascript:;" onclick="modal_open('edit', 'md', '<?= base_url('babikode/order/?action=edit&id=' . $order['id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-orange-yellow">
        <i class="ft-edit mr-1"></i>Edit Order
    </a>
    <button type="button" data-dismiss="modal" class="btn btn-glow btn-bg-gradient-x-purple-blue float-right">Tutup</button>
</div>

<?php
} else {
    http_response_code(403);
    exit('No direct script access allowed!');
}
?>