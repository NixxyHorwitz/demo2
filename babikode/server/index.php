<?php
require '../../mainconfig.php';
require '../../lib/check_session_admin.php';
$page_type = 'Server';
$page_name = 'Data Orders';
require '../../lib/header_admin.php';
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
                    <li class="breadcrumb-item active"><?= $page_name; ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title float-left"><?= $page_name; ?></h4>
                </div>
                <div class="card-body pt-0">
                    <?php
                    require '../../lib/flash_message.php';
                    $q_row        = isset($_GET['row'])        ? protect($_GET['row'])        : '';
                    $q_search     = isset($_GET['search'])     ? protect($_GET['search'])     : '';
                    $q_masa_aktif = isset($_GET['masa_aktif']) ? protect($_GET['masa_aktif']) : '';
                    $q_status     = isset($_GET['status'])     ? protect($_GET['status'])     : '';
                    $q_start_date = isset($_GET['start_date']) ? protect($_GET['start_date']) : '';
                    $q_end_date   = isset($_GET['end_date'])   ? protect($_GET['end_date'])   : '';
                    ?>

                    <!-- Filter Status -->
                    <div class="btn-group flex-wrap mb-2">
                        <a href="<?= base_url('babikode/server/'); ?>" class="btn btn-glow btn-primary <?= (empty($q_status)) ? 'active' : ''; ?>">Semua</a>
                        <a href="<?= base_url('babikode/server/?status=Active'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'Active') ? 'active' : ''; ?>">Active</a>
                        <a href="<?= base_url('babikode/server/?status=Inactive'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'Inactive') ? 'active' : ''; ?>">Inactive</a>
                    </div>

                    <form method="get" class="row mt-2">
                        <?php if (!empty($q_status)): ?>
                            <input type="hidden" name="status" value="<?= $q_status; ?>">
                        <?php endif; ?>

                        <!-- Tampilkan -->
                        <div class="col-md-2">
                            <div class="input-group mb-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Tampilkan</span>
                                </div>
                                <select class="form-control" name="row" id="table-row">
                                    <option value="10"  <?= ($q_row == '10')  ? 'selected' : ''; ?>>10</option>
                                    <option value="25"  <?= ($q_row == '25')  ? 'selected' : ''; ?>>25</option>
                                    <option value="50"  <?= ($q_row == '50')  ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?= ($q_row == '100') ? 'selected' : ''; ?>>100</option>
                                </select>
                                <div class="input-group-append">
                                    <span class="input-group-text">baris</span>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Masa Aktif -->
                        <div class="col-md-2">
                            <div class="input-group mb-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Masa Aktif</span>
                                </div>
                                <select class="form-control" name="masa_aktif" id="masa-aktif">
                                    <option value="">Semua</option>
                                    <option value="90" <?= ($q_masa_aktif == '90') ? 'selected' : ''; ?>>90 Hari</option>
                                    <option value="30" <?= ($q_masa_aktif == '30') ? 'selected' : ''; ?>>30 Hari</option>
                                </select>
                            </div>
                        </div>

                        <!-- Filter Tanggal -->
                        <div class="col-md-5">
                            <div class="input-group mb-2">
                                <input type="date" class="form-control" name="start_date" id="table-start-date" value="<?= $q_start_date; ?>">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">s/d</span>
                                </div>
                                <input type="date" class="form-control" name="end_date" id="table-end-date" value="<?= $q_end_date; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-glow btn-bg-gradient-x-purple-blue" type="submit"><i class="ft-filter"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="col-md-3">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="search" id="table-search"
                                       value="<?= $q_search; ?>" placeholder="Cari produk / user / phone...">
                                <div class="input-group-append">
                                    <button class="btn btn-glow btn-bg-gradient-x-purple-blue" type="submit"><i class="ft-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr class="text-uppercase">
                                    <th>ID</th>
                                    <th>Tgl Order</th>
                                    <th>User</th>
                                    <th>Nama Produk</th>
                                    <th>Harga</th>
                                    <th>Profit Harian</th>
                                    <th>Total Profit</th>
                                    <th>Masa Aktif</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
<?php
$records_per_page = in_array($q_row, ['10','25','50','100']) ? $q_row : 10;

// cari user_id yang beli lebih dari 1 kali
$user_count = [];
$count_query_all = mysqli_query($db, "SELECT user_id, COUNT(*) as total FROM orders GROUP BY user_id HAVING total > 1");
while ($row_count = mysqli_fetch_assoc($count_query_all)) {
    $user_count[$row_count['user_id']] = $row_count['total'];
}

$query_list = "SELECT o.*, u.phone
               FROM orders o
               LEFT JOIN users u ON o.user_id = u.id
               WHERE o.id <> ''";

// Filter status
if (!empty($q_status) && in_array($q_status, ['Active','Inactive'])) {
    $query_list .= " AND o.status = '$q_status'";
}

// Filter masa aktif
if (!empty($q_masa_aktif) && in_array($q_masa_aktif, ['90','30'])) {
    $query_list .= " AND o.masa_aktif = '$q_masa_aktif'";
}

// Filter tanggal
if (!empty($q_start_date) && !empty($q_end_date)) {
    $query_list .= " AND DATE(o.created_at) BETWEEN '$q_start_date' AND '$q_end_date'";
} elseif (!empty($q_start_date)) {
    $query_list .= " AND DATE(o.created_at) = '$q_start_date'";
} elseif (!empty($q_end_date)) {
    $query_list .= " AND DATE(o.created_at) = '$q_end_date'";
}

// Search
if (!empty($q_search)) {
    $query_list .= " AND (o.nama_produk LIKE '%$q_search%'
                    OR o.user_id LIKE '%$q_search%'
                    OR o.produk_id LIKE '%$q_search%'
                    OR u.phone LIKE '%$q_search%')";
}

// urut: duplikat dulu, lalu terbaru
$query_list .= " ORDER BY (o.user_id IN (SELECT user_id FROM orders GROUP BY user_id HAVING COUNT(*) > 1)) DESC, o.id DESC";

$starting_position = 0;
if (isset($_GET['page'])) {
    $starting_position = ((int)$_GET['page'] - 1) * $records_per_page;
}

$paginated_query = $query_list . " LIMIT $starting_position, $records_per_page";
$result = mysqli_query($db, $paginated_query);

if (mysqli_num_rows($result) == 0) {
    echo '<tr><td colspan="10" align="center">Data belum tersedia.</td></tr>';
}

$colors = ['#ffeeba','#d4edda','#cce5ff','#f8d7da','#e2e3e5','#fff3cd','#f5c6cb','#b8daff','#d6d8d9'];
$color_map   = [];
$color_index = 0;

while ($order = mysqli_fetch_assoc($result)):
    $highlight = '';
    $user_display = htmlspecialchars($order['phone'] ?? '-');

    if (isset($user_count[$order['user_id']])) {
        if (!isset($color_map[$order['user_id']])) {
            $color_map[$order['user_id']] = $colors[$color_index % count($colors)];
            $color_index++;
        }
        $highlight    = 'style="background-color:' . $color_map[$order['user_id']] . ';"';
        $user_display .= ' <span class="badge badge-warning badge-sm">' . $user_count[$order['user_id']] . 'x beli</span>';
    }

    $status_badge = match($order['status'] ?? 'Active') {
        'Active'   => 'badge-success',
        'Inactive' => 'badge-secondary',
        default    => 'badge-warning',
    };
?>
                                <tr <?= $highlight; ?>>
                                    <td><?= $order['id']; ?></td>
                                    <td class="text-nowrap">
                                        <?= format_date(date: substr($order['created_at'], 0, -8), print_day: true, short_month: true); ?><br>
                                        <small class="text-muted"><?= substr($order['created_at'], 11, -3); ?></small>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="javascript:;"
                                           onclick="modal_open('detail','md','<?= base_url('babikode/user/detail?id='.$order['user_id']); ?>');"
                                           class="btn btn-glow btn-sm btn-bg-gradient-x-purple-red">
                                            <?= $user_display; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <b><?= htmlspecialchars($order['nama_produk']); ?></b><br>
                                        <small class="text-muted">ID Produk: <?= $order['produk_id']; ?></small>
                                    </td>
                                    <td>Rp <?= number_format($order['harga'], 0, ',', '.'); ?></td>
                                    <td>Rp <?= number_format($order['profit_harian'], 0, ',', '.'); ?>/hari</td>
                                    <td><b>Rp <?= number_format($order['total_profit'], 0, ',', '.'); ?></b></td>
                                    <td><?= $order['masa_aktif']; ?> Hari</td>
                                    <td>
                                        <span class="badge <?= $status_badge; ?>"><?= $order['status'] ?? 'Active'; ?></span>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="javascript:;"
                                           onclick="modal_open('detail','lg','<?= base_url('babikode/server/detail?id='.$order['id']); ?>');"
                                           class="btn btn-glow btn-sm btn-bg-gradient-x-blue-cyan mb-1">
                                            <i class="ft-eye mr-1"></i>Detail
                                        </a>
                                    </td>
                                </tr>
<?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer">
                    <div class="row justify-content-center">
                        <div class="col-md-12">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-md justify-content-center">
                                    <?php
                                    $url = base_url('babikode/server/');
                                    $count_result        = mysqli_query($db, $query_list);
                                    $total_no_of_records = mysqli_num_rows($count_result);

                                    if ($total_no_of_records > 0) {
                                        $post_data = '';
                                        if (!empty($q_status))     $post_data .= '&status='     . $q_status;
                                        if (!empty($q_masa_aktif)) $post_data .= '&masa_aktif=' . $q_masa_aktif;
                                        if (!empty($q_row))        $post_data .= '&row='        . $q_row;
                                        if (!empty($q_start_date)) $post_data .= '&start_date=' . $q_start_date;
                                        if (!empty($q_end_date))   $post_data .= '&end_date='   . $q_end_date;
                                        if (!empty($q_search))     $post_data .= '&search='     . $q_search;

                                        $total_no_of_pages = ceil($total_no_of_records / $records_per_page);
                                        $current_page      = isset($_GET['page']) ? (int)$_GET['page'] : 1;

                                        if ($current_page != 1) {
                                            $previous = $current_page - 1;
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="'.$url.'?page=1'.$post_data.'">&lsaquo; First</a></li>';
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="'.$url.'?page='.$previous.$post_data.'">&laquo;</a></li>';
                                        }

                                        $jumlah_number = 2;
                                        $start_number  = max(1, $current_page - $jumlah_number);
                                        $end_number    = min($total_no_of_pages, $current_page + $jumlah_number);

                                        for ($i = $start_number; $i <= $end_number; $i++) {
                                            $active = ($i == $current_page) ? 'active' : '';
                                            echo '<li class="page-item pull-up shadow '.$active.'"><a class="page-link" href="'.$url.'?page='.$i.$post_data.'">'.$i.'</a></li>';
                                        }

                                        if ($current_page != $total_no_of_pages) {
                                            $next = $current_page + 1;
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="'.$url.'?page='.$next.$post_data.'">&raquo;</a></li>';
                                            echo '<li class="page-item pull-up shadow"><a class="page-link" href="'.$url.'?page='.$total_no_of_pages.$post_data.'">Last &rsaquo;</a></li>';
                                        }
                                    }
                                    ?>
                                </ul>
                            </nav>
                        </div>
                        <span class="btn btn-glow btn-bg-gradient-x-blue-green btn-sm">
                            Total data: <b><?= number_format($total_no_of_records, 0, ',', '.'); ?></b>
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
$(function () {
    $('#table-row, #masa-aktif').on('change', function () {
        var row        = $('#table-row').val();
        var search     = $('#table-search').val();
        var masa_aktif = $('#masa-aktif').val();
        var start_date = $('#table-start-date').val();
        var end_date   = $('#table-end-date').val();
        var status     = '<?= $q_status; ?>';

        var url = '<?= base_url('babikode/server/'); ?>?row=' + row
                + '&search=' + search
                + '&masa_aktif=' + masa_aktif
                + '&start_date=' + start_date
                + '&end_date=' + end_date;

        if (status) url += '&status=' + status;
        window.location = url;
    });
});

$(window).keypress(function (event) {
    if (event.which == '13' && !$(event.target).is('textarea')) {
        event.preventDefault();
    }
});

$('form').submit(function () {
    $(':submit').attr('disabled', true);
});
</script>

<?php require '../../lib/footer_admin.php'; ?>