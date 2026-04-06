<?php

require '../../mainconfig.php';
require '../../lib/check_session_admin.php';
$page_type = 'withdraw_methods';
$page_name = 'Metode Withdraw';
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
                    <li class="breadcrumb-item active">Point
                    </li>
                    <li class="breadcrumb-item active"><?= $page_name; ?>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>
<div class="content-body">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title float-left"><?= $page_name; ?></h4>
                    <a href="javascript:;" onclick="modal_open('add', 'md', '<?= base_url('babikode/withdraw_method/add'); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-blue float-right"><b><i class="ft-plus mr-1"></i>Tambah</b></a>
                </div>
                <div class="card-body pt-0">
                    <div id="body-result"></div>
                    <?php
                    require '../../lib/flash_message.php';
                    $q_row = (isset($_GET['row'])) ? protect($_GET['row']) : '';
                    $q_start_date = (isset($_GET['start_date'])) ? protect($_GET['start_date']) : '';
                    $q_end_date = (isset($_GET['end_date'])) ? protect($_GET['end_date']) : '';
                    $q_search = (isset($_GET['search'])) ? protect($_GET['search']) : '';
                    ?>
                    <form method="get" class="row">
                        <div class="col-md-6">
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
                                    <th>Nama Metode</th>
                                    <th>Min. Withdraw</th>
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

                                $query_list = "SELECT * FROM withdraw_methods WHERE id <> ''";

                                if (!empty($q_search)) {
                                    $query_list .= " AND (name LIKE '%$q_search%' OR min_amount LIKE '%$q_search%')";
                                }

                                $query_list .= " ORDER BY id DESC";

                                $starting_position = 0;

                                if (isset($_GET["page"])) {
                                    $starting_position = ((int)$_GET["page"] - 1) * $records_per_page;
                                }

                                $new_query = $query_list . " LIMIT $starting_position, $records_per_page";
                                $new_query = mysqli_query($db, $new_query);

                                if (mysqli_num_rows($new_query) == 0) {
                                    echo '<tr><td colspan="5" align="center">Data belum tersedia.</td></tr>';
                                }

                                while ($wm = mysqli_fetch_assoc($new_query)) {
                                    $color = match ($wm['status']) {
                                        '1' => 'blue-green',
                                        default => 'red-pink'
                                    };
                                    $status = match ($wm['status']) {
                                        '1' => 'Aktif',
                                        default => 'Nonaktif'
                                    };
                                ?>
                                    <tr>
                                        <td class="text-nowrap"><?= $wm['id']; ?></td>
                                        <td><?= $wm['name']; ?></td>
                                        <td><?= number_format($wm['min_amount'], 0, ',', '.'); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-<?= $color; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"><?= $status; ?><i class="ft-chevron-down ml-1"></i></button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="javascript:;" onclick="get_data('<?= base_url('babikode/withdraw_method/status?id=' . $wm['id'] . '&status=1'); ?>');">Aktif</a>
                                                    <a class="dropdown-item" href="javascript:;" onclick="get_data('<?= base_url('babikode/withdraw_method/status?id=' . $wm['id'] . '&status=0'); ?>');">Nonaktif</a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="javascript:;" onclick="modal_open('edit', 'md', '<?= base_url('babikode/withdraw_method/edit?id=' . $wm['id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-orange-yellow"><b><i class="ft-edit mr-1"></i>Ubah</b></a>
                                            <a href="javascript:;" onclick="swal_delete('<?= $wm['id']; ?>', '<?= base_url('babikode/withdraw_method/delete?id=' . $wm['id']); ?>');" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"><b><i class="ft-trash mr-1"></i>Hapus</b></a>
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
                                    $url = base_url('babikode/withdraw_method/');
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
            window.location = "<?= base_url('babikode/withdraw_method/'); ?>?row=" + row + "&search=" + search;
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