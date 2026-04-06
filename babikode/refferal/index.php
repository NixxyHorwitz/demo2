<?php

require '../../mainconfig.php';
require '../../lib/check_session_admin.php';
$page_type = 'refferals';
$page_name = 'Refferal';
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
                    <li class="breadcrumb-item"><a href="javascript:;"><?= base_title(); ?></a>
                    </li>
                    <li class="breadcrumb-item active"><?= $page_name; ?>
                    </li>
                </ol>
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
                </div>
                <div class="card-body pt-0">
                    <?php
                    require '../../lib/flash_message.php';
                    $q_row = (isset($_GET['row'])) ? protect($_GET['row']) : '';
                    $q_start_date = (isset($_GET['start_date'])) ? protect($_GET['start_date']) : '';
                    $q_end_date = (isset($_GET['end_date'])) ? protect($_GET['end_date']) : '';
                    $q_search = (isset($_GET['search'])) ? protect($_GET['search']) : '';
                    ?>
                    <form method="get" class="row">
                        <div class="col-md-3">
                            <div class="input-group mb-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Tampilkan</span>
                                </div>
                                <select class="form-control" name="row" id="table-row">
                                    <option value="10" <?= ($q_row == '10') ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?= ($q_row == '25') ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?= ($q_row == '50') ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?= ($q_row == '100') ? 'selected' : ''; ?>>100</option>
                                </select>
                                <div class="input-group-append">
                                    <span class="input-group-text">baris.</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-2">
                                <input type="date" class="form-control" name="start_date" id="table-start-date" value="<?= $q_start_date; ?>">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">sampai</span>
                                </div>
                                <input type="date" class="form-control" name="end_date" id="table-end-date" value="<?= $q_end_date; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-glow btn-bg-gradient-x-purple-blue" type="submit"><i class="ft-filter"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="search" id="table-search" value="<?= $q_search; ?>" placeholder="Cari...">
                                <div class="input-group-append">
                                    <button class="btn btn-glow btn-bg-gradient-x-purple-blue" type="submit"><i class="ft-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr class="text-uppercase">
                                    <th>ID</th>
                                    <th>Pengguna</th>
                                    <th>Downline</th>
                                    <th>Keterangan</th>
                                    <th>Jumlah</th>
                                    <th>Tanggal & Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (isset($q_row) && in_array($q_row, array('10', '25', '50', '100'))) {
                                    $records_per_page = $q_row; // edit
                                } else {
                                    $records_per_page = 10; // edit
                                }

                                $query_list = "SELECT a.*, b.phone AS phone, c.phone AS from_user FROM refferals AS a INNER JOIN users AS b ON a.user_id = b.id INNER JOIN users AS c ON a.from_id = c.id WHERE a.id <> ''";

                                if (!empty($q_start_date) and !empty($q_end_date)) {
                                    $query_list .= " AND DATE(login_logs.created_at) BETWEEN '" . $q_start_date . "' AND '" . $q_end_date . "'";
                                } elseif (!empty($q_start_date) and empty($q_end_date)) {
                                    $query_list .= " AND DATE(login_logs.created_at) BETWEEN '" . $q_start_date . "' AND '" . $q_start_date . "'";
                                } elseif (empty($q_start_date) and !empty($q_end_date)) {
                                    $query_list .= " AND DATE(login_logs.created_at) BETWEEN '" . $q_end_date . "' AND '" . $q_end_date . "'";
                                }

                                if (!empty($q_search)) {
                                    $query_list .= " AND (b.phone LIKE '%$q_search%' OR c.phone LIKE '%$q_search%' OR a.amount LIKE '%$q_search%')";
                                }

                                $query_list .= " ORDER BY a.id DESC";

                                $starting_position = 0;

                                if (isset($_GET["page"])) {
                                    $starting_position = ((int)$_GET["page"] - 1) * $records_per_page;
                                }

                                $new_query = $query_list . " LIMIT $starting_position, $records_per_page";
                                $new_query = mysqli_query($db, $new_query);

                                if (mysqli_num_rows($new_query) == 0) {
                                    echo '<tr><td colspan="5" align="center">Data belum tersedia.</td></tr>';
                                }

                                while ($log = mysqli_fetch_assoc($new_query)) { ?>
                                    <tr>
                                        <td><?= $log['id']; ?></td>
                                        <td class="text-nowrap">
                                            <a href="javascript:;" onclick="modal_open('detail', 'md', '<?= base_url('babikode/user/detail?id=' . $log['user_id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-red"><?= $log['phone']; ?></a>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="javascript:;" onclick="modal_open('detail', 'md', '<?= base_url('babikode/user/detail?id=' . $log['from_id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-red"><?= $log['from_user']; ?></a>
                                        </td>
                                        <td> <?= $log['keterangan']; ?></td>
                                        <td>+ Rp <?= number_format($log['amount'], 0, ',', '.'); ?></td>
                                        <td><?= format_date(date: substr($log['created_at'], 0, -8), print_day: true, short_month: true) . ' ' . substr($log['created_at'], 11, -3); ?></td>
                                    </tr>
                                <?php
                                } ?>
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
                                    // start paging link
                                    $url = base_url('babikode/refferal/');
                                    $query_list = mysqli_query($db, $query_list);
                                    $total_no_of_records = mysqli_num_rows($query_list);
                                    if ($total_no_of_records > 0) {
                                        $post_data = '';
                                        if (!empty($q_row) or !empty($q_start_date) or !empty($q_end_date) or !empty($q_search)) {
                                            $post_data .= "&row=" . $q_row . "&start_date=" . $q_start_date . "&end_date=" . $q_end_date . "&search=" . $q_search;
                                        }
                                        $total_no_of_pages = ceil($total_no_of_records / $records_per_page);
                                        $current_page = 1;
                                        if (isset($_GET["page"])) {
                                            $current_page = $_GET["page"];
                                        }
                                        if ($current_page != 1) {
                                            $previous = $current_page - 1;
                                    ?>
                                            <li class="page-item pull-up shadow"><a class="page-link" href="<?= $url . "?page=1$post_data"; ?>">&lsaquo; First</a></li>
                                            <li class="page-item pull-up shadow"><a class="page-link" href="<?= $url . "?page=" . $previous . $post_data; ?>" aria-label="Previous"><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>
                                            <?php
                                        }
                                        $jumlah_number = 2;
                                        $start_number = ($current_page > $jumlah_number) ? $current_page - $jumlah_number : 1;
                                        $end_number = ($current_page < ($total_no_of_pages - $jumlah_number)) ? $current_page + $jumlah_number : $total_no_of_pages;
                                        for ($i = $start_number; $i <= $end_number; $i++) {
                                            if ($i == $current_page) {
                                            ?>
                                                <li class="page-item pull-up shadow active"><a class="page-link" href="<?= $url . "?page=" . $i . $post_data; ?>"><?= $i; ?></a></li>
                                            <?php } else { ?>
                                                <li class="page-item pull-up shadow"><a class="page-link" href="<?= $url . "?page=" . $i . $post_data; ?>"><?= $i; ?></a></li>
                                            <?php }
                                        }
                                        if ($current_page != $total_no_of_pages) {
                                            $next = $current_page + 1;
                                            ?>
                                            <li class="page-item pull-up shadow"><a class="page-link" href="<?= $url . "?page=" . $next . $post_data; ?>" aria-label="Next"><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>
                                            <li class="page-item pull-up shadow"><a class="page-link" href="<?= $url . "?page=" . $total_no_of_pages . $post_data; ?>">Last &rsaquo;</a></li>
                                    <?php
                                        }
                                    }
                                    // end paging link
                                    ?>
                                </ul>
                            </nav>
                        </div>
                        <span class="btn btn-glow btn-bg-gradient-x-blue-green btn-sm justify-content-center pull-up">Total data: <b><?= number_format($total_no_of_records, 0, ',', '.'); ?></b></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(function() {
        $('#table-row').on('change', function() {
            var row = $('#table-row').val();
            var search = $('#table-search').val();
            var start_date = $('#table-start-date').val();
            var end_date = $('#table-end-date').val();
            window.location = "<?= base_url('babikode/refferal/'); ?>?row=" + row + "&start_date=" + start_date + "&end_date=" + end_date + "&search=" + search;
        });
    });
    $(window).keypress(function(event) {
        if (event.which == '13' && !$(event.target).is('textarea')) {
            event.preventDefault();
        }
    });
    $('form').submit(function() {
        $(':submit').attr("disabled", true);
    });
</script>

<?php
require '../../lib/footer_admin.php';
?>