<?php

require '../../mainconfig.php';
require '../../lib/check_session_admin.php';
$page_type = 'withdraws';
$page_name = 'Data Withdraw';
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
                    <li class="breadcrumb-item active">Komisi
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
                <div class="card-header">
                    <h4 class="card-title float-left"><?= $page_name; ?></h4>
                    <a href="javascript:;" onclick="modal_open('add', 'md', '<?= base_url('babikode/withdraw/add'); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-blue float-right"><b><i class="ft-plus mr-1"></i>Tambah</b></a>
                </div>
                <div class="card-body pt-0">
                    <div id="body-result"></div>
                    <?php
                    require '../../lib/flash_message.php';
                    $q_status = (isset($_GET['status'])) ? protect($_GET['status']) : '';
                    $q_row = (isset($_GET['row'])) ? protect($_GET['row']) : '';
                    $q_start_date = (isset($_GET['start_date'])) ? protect($_GET['start_date']) : '';
                    $q_end_date = (isset($_GET['end_date'])) ? protect($_GET['end_date']) : '';
                    $q_search = (isset($_GET['search'])) ? protect($_GET['search']) : '';
                    ?>
                    <div class="btn-group flex-wrap">
                        <a href="<?= base_url('babikode/withdraw/'); ?>" class="btn btn-glow btn-primary <?= (empty($q_status) or $q_status == '') ? 'active' : ''; ?>">Semua</a>
                        <a href="<?= base_url('babikode/withdraw/?status=pending'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'pending') ? 'active' : ''; ?>">Pending</a>
                        <a href="<?= base_url('babikode/withdraw/?status=success'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'success') ? 'active' : ''; ?>">Success</a>
                        <a href="<?= base_url('babikode/withdraw/?status=canceled'); ?>" class="btn btn-glow btn-primary <?= ($q_status == 'canceled') ? 'active' : ''; ?>">Canceled</a>
                    </div>
                    <form method="get" class="row mt-2">
                        <?php
                        if (isset($q_status) and !empty($q_status)) { ?>
                            <input type="hidden" name="status" id="q_status" value="<?= $q_status; ?>">
                        <?php
                        } ?>
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
                                    <th>Tgl. Dibuat</th>
                                    <th>Pengguna</th>
                                    <th>Bank</th>
                                    <th>Rekening</th>
                                    <th>Jumlah</th>
                                    <th>Total Withdraw</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (isset($q_row) && in_array($q_row, array('10', '25', '50', '100'))) {
                                    $records_per_page = $q_row; // edit
                                } else {
                                    $records_per_page = 10; // edit
                                }

                                $query_list = "SELECT withdraws.*, users.phone FROM withdraws INNER JOIN users ON withdraws.user_id = users.id WHERE withdraws.id <> ''";

                                if (isset($q_status) && in_array($q_status, array('pending', 'success', 'canceled'))) {
                                    $query_list .= " AND withdraws.status = '" . ucfirst($q_status) . "'";
                                }

                                if (!empty($q_start_date) and !empty($q_end_date)) {
                                    $query_list .= " AND DATE(withdraws.created_at) BETWEEN '" . $q_start_date . "' AND '" . $q_end_date . "'";
                                } elseif (!empty($q_start_date) and empty($q_end_date)) {
                                    $query_list .= " AND DATE(withdraws.created_at) BETWEEN '" . $q_start_date . "' AND '" . $q_start_date . "'";
                                } elseif (empty($q_start_date) and !empty($q_end_date)) {
                                    $query_list .= " AND DATE(withdraws.created_at) BETWEEN '" . $q_end_date . "' AND '" . $q_end_date . "'";
                                }

                                if (!empty($q_search)) {
                                    $query_list .= " AND (withdraws.id LIKE '%$q_search%' OR withdraws.method LIKE '%$q_search%' OR withdraws.no_rek LIKE '%$q_search%' OR withdraws.name_rek LIKE '%$q_search%' OR withdraws.komisi LIKE '%$q_search%' OR withdraws.description LIKE '%$q_search%' OR users.phone LIKE '%$q_search%')";
                                }

                                $query_list .= " ORDER BY withdraws.id DESC";

                                $starting_position = 0;

                                if (isset($_GET["page"])) {
                                    $starting_position = ((int)$_GET["page"] - 1) * $records_per_page;
                                }

                                $new_query = $query_list . " LIMIT $starting_position, $records_per_page";
                                $new_query = mysqli_query($db, $new_query);

                                if (mysqli_num_rows($new_query) == 0) {
                                    echo '<tr><td colspan="9" align="center">Data belum tersedia.</td></tr>';
                                }

                                while ($wd = mysqli_fetch_assoc($new_query)) {
                                    $color = match ($wd['status']) {
                                        'Success' => 'blue-green disabled',
                                        'Canceled' => 'red-pink disabled',
                                        default => 'orange-yellow'
                                    };
                                ?>
                                    <tr>
                                        <td><?= $wd['id']; ?></td>
                                        <td><?= format_date(date: substr($wd['created_at'], 0, -8), print_day: true, short_month: true) . ' ' . substr($wd['created_at'], 11, -3); ?></td>
                                        <td class="text-nowrap">
                                            <a href="javascript:;" onclick="modal_open('detail', 'md', '<?= base_url('babikode/user/detail?id=' . $wd['user_id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-red"><?= $wd['phone']; ?></a>
                                        </td>
                                        <td><?= $wd['method']; ?></td>
                                        <td><?= $wd['no_rek'] . ' (' . $wd['name_rek'] . ')'; ?></td>
                                        <td><?= number_format($wd['komisi'], 0, ',', '.'); ?></td>
                                        <td>Rp <?= number_format($wd['amount'], 0, ',', '.'); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-<?= $color; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"><?= $wd['status']; ?><i class="ft-chevron-down ml-1"></i></button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="javascript:;" onclick="get_data('<?= base_url('babikode/withdraw/status?id=' . $wd['id'] . '&status=success'); ?>');">Success</a>
                                                    <a class="dropdown-item" href="javascript:;" onclick="get_data('<?= base_url('babikode/withdraw/status?id=' . $wd['id'] . '&status=canceled'); ?>');">Canceled</a>
                                                </div>
                                            </div> 
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="javascript:;" onclick="swal_delete('<?= $wd['id']; ?>', '<?= base_url('babikode/withdraw/delete?id=' . $wd['id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><b><i class="ft-trash mr-1"></i>Hapus</b></a>
                                        </td>
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
                                    $url = base_url('babikode/withdraw');
                                    $query_list = mysqli_query($db, $query_list);
                                    $total_no_of_records = mysqli_num_rows($query_list);
                                    if ($total_no_of_records > 0) {
                                        $post_data = '';
                                        if (!empty($q_status)) {
                                            $post_data .= "&status=" . $q_status;
                                        }
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
            var status = $('#q_status').val();
            if (status == '' || status == undefined) {
                window.location = "<?= base_url('babikode/withdraw/'); ?>?row=" + row + "&start_date=" + start_date + "&end_date=" + end_date + "&search=" + search;
            } else {
                window.location = "<?= base_url('babikode/withdraw/'); ?>?status=" + status + "&row=" + row + "&start_date=" + start_date + "&end_date=" + end_date + "&search=" + search;
            }
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