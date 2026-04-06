<?php
require '../mainconfig.php';
require '../lib/check_session_admin.php';

$page_type = 'dashboard';
$page_name = 'Dashboard';
require '../lib/header_admin.php';

// ============================================================
// DATA STATISTIK UTAMA
// ============================================================
$point       = $model->db_query($db, "SUM(point) AS total",  "users",  "status != 'admin'")['rows']['total'] ?? 0;
$total_users = $model->db_query($db, "COUNT(*) AS total",    "users")['rows']['total']  ?? 0;

// Saldo 
$sett        = $model->db_query($db, "*", "settings", "id = 1")['rows'];
$balance_idr = 0;

if (!empty($sett['api_key']) && !empty($sett['secret_key'])) {
    $balance_idr = 0;
   $ch = curl_init('https://asteelass.icu/api/saldo.php');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([]), // tidak butuh body
        CURLOPT_HTTPHEADER     => [
            "X-API-KEY: "    . $sett['api_key'],
            "X-SECRET-KEY: " . $sett['secret_key'],
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false // bisa true kalau sudah pakai SSL valid
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $balance_idr = 0;
    } else {
        $res = json_decode($response, true);

        if (!empty($res['status'])) {
            $balance_idr = $res['data']['balance'] ?? 0;
        }
    }

    curl_close($ch);
}
// ============================================================
// DATA PROMOTOR — ambil dari uplink_level = 'promotor'
// ============================================================
$query_promotor = "
    SELECT 
        u.id AS user_id,
        u.phone,
        u.point,
        u.profit,
        u.status,
        COALESCE(SUM(CASE WHEN w.status = 'Success' THEN w.amount ELSE 0 END), 0) AS total_withdraw,
        COALESCE(SUM(CASE WHEN w.status = 'Success' THEN w.komisi ELSE 0 END), 0) AS total_komisi_wd,
        (SELECT COALESCE(SUM(rf.amount), 0) FROM refferals rf WHERE rf.user_id = u.id) AS total_komisi_reff,
        (SELECT COUNT(*) FROM users d WHERE d.uplink = u.id) AS total_downline,
        (SELECT COUNT(*) FROM users d 
         INNER JOIN topups t ON t.user_id = d.id AND t.status = 'Success' 
         WHERE d.uplink = u.id) AS total_downline_aktif
    FROM users u
    LEFT JOIN withdraws w ON w.user_id = u.id AND (w.description != 'WD FAKE' OR w.description IS NULL)
    WHERE u.uplink_level = 'promotor'
    GROUP BY u.id, u.phone, u.point, u.profit, u.status
    ORDER BY (u.point + COALESCE(SUM(CASE WHEN w.status = 'Success' THEN w.amount ELSE 0 END), 0)) DESC
";
$result_promotor = mysqli_query($db, $query_promotor);
$promotor_count  = mysqli_num_rows($result_promotor);

// ============================================================
// DATA PENGGUNA YANG SUDAH UNTUNG
// ============================================================
$result_untung = mysqli_query($db, "
    SELECT 
        u.phone,
        COALESCE(SUM(t.amount), 0)  AS total_deposit,
        COALESCE(SUM(w.amount), 0)  AS total_withdraw,
        (COALESCE(SUM(w.amount), 0) - COALESCE(SUM(t.amount), 0)) AS keuntungan
    FROM users u
    LEFT JOIN topups   t ON t.user_id = u.id AND t.status = 'Success'
    LEFT JOIN withdraws w ON w.user_id = u.id AND w.status = 'Success' AND (w.description != 'WD FAKE' OR w.description IS NULL)
    GROUP BY u.id, u.phone
    HAVING total_withdraw > total_deposit
    ORDER BY keuntungan DESC
");

require '../lib/flash_message.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>

<div class="content-header row">
    <div class="content-header-left col-md-6 col-12 mb-2">
        <h3 class="content-header-title fw-bold"><?= $page_name; ?></h3>
    </div>
</div>

<div class="content-body">
    <div class="row">

       <!-- Card Saldo  -->
<div class="col-xl-4 col-lg-4 col-md-6 col-12">
    <div class="stat-card" style="background: linear-gradient(135deg, #f7971e, #ffd200); border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(247,151,30,0.4);">
        <div style="padding: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h4 style="color:#fff; margin:0 0 4px 0; font-weight:700; font-size:1.4rem;">Rp <?= number_format($balance_idr, 0, ',', '.'); ?></h4>
                    <span style="color:rgba(255,255,255,0.85); font-size:13px;">Saldo Payment Gateway</span>
                </div>
                <i class="ft-credit-card" style="font-size:2.5rem; color:rgba(255,255,255,0.45);"></i>
            </div>
        </div>
        <div style="background:rgba(0,0,0,0.12); padding:8px 20px;">
            <span style="color:rgba(255,255,255,0.85); font-size:12px;">
                <i class="ft-refresh-ccw"></i> Payment Gateway Aktif
            </span>
        </div>
    </div>
</div>

<!-- Card Total Saldo User -->
<div class="col-xl-4 col-lg-4 col-md-6 col-12">
    <div class="stat-card" style="background: linear-gradient(135deg, #1a73e8, #0d47a1); border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(26,115,232,0.4);">
        <div style="padding: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h4 style="color:#fff; margin:0 0 4px 0; font-weight:700; font-size:1.4rem;">Rp <?= number_format($point, 0, ',', '.'); ?></h4>
                    <span style="color:rgba(255,255,255,0.85); font-size:13px;">Total Saldo User</span>
                </div>
                <i class="ft-dollar-sign" style="font-size:2.5rem; color:rgba(255,255,255,0.45);"></i>
            </div>
        </div>
        <div style="background:rgba(0,0,0,0.12); padding:8px 20px;">
            <span style="color:rgba(255,255,255,0.85); font-size:12px;">
                <i class="ft-trending-up"></i> Akumulasi semua saldo aktif
            </span>
        </div>
    </div>
</div>

<!-- Card Total User -->
<div class="col-xl-4 col-lg-4 col-md-6 col-12">
    <div class="stat-card" style="background: linear-gradient(135deg, #7b2ff7, #3a0ca3); border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(123,47,247,0.4);">
        <div style="padding: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h4 style="color:#fff; margin:0 0 4px 0; font-weight:700; font-size:1.4rem;"><?= number_format($total_users, 0, ',', '.'); ?></h4>
                    <span style="color:rgba(255,255,255,0.85); font-size:13px;">Total Pengguna</span>
                </div>
                <i class="ft-users" style="font-size:2.5rem; color:rgba(255,255,255,0.45);"></i>
            </div>
        </div>
        <div style="background:rgba(0,0,0,0.12); padding:8px 20px;">
            <span style="color:rgba(255,255,255,0.85); font-size:12px;">
                <i class="ft-user-plus"></i> <?= $promotor_count; ?> di antaranya Promotor
            </span>
        </div>
    </div>
</div>

        <!-- Tabel Pengguna yang Sudah Untung -->
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3 mt-3">
                <div class="card-body">
                    <h5 class="mb-3 fw-bold text-success">
                        <i class="fa fa-chart-line me-2"></i> Pengguna yang Sudah Untung
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor Akun</th>
                                    <th>Total Deposit</th>
                                    <th>Total Withdraw</th>
                                    <th>Keuntungan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result_untung) > 0):
                                    while ($row = mysqli_fetch_assoc($result_untung)): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['phone']); ?></td>
                                        <td>Rp <?= number_format($row['total_deposit'],  0, ',', '.'); ?></td>
                                        <td>Rp <?= number_format($row['total_withdraw'], 0, ',', '.'); ?></td>
                                        <td class="text-success fw-bold">Rp <?= number_format($row['keuntungan'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada pengguna yang untung</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Peringkat Promotor -->
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3 mt-3">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="fa fa-trophy me-2"></i> Peringkat Promotor
                        <span class="badge bg-primary"><?= $promotor_count; ?> Promotor</span>
                    </h5>
                    <small class="text-muted">Berdasarkan <code>uplink_level = promotor</code></small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Peringkat</th>
                                    <th>Nomor Akun</th>
                                    <th>Status</th>
                                    <th>Saldo (point)</th>
                                    <th>Total WD</th>
                                    <th>Komisi Referral</th>
                                    <th>Downline</th>
                                    <th>Downline Aktif</th>
                                    <th>Total Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($promotor_count > 0):
                                    $rank = 1;
                                    $sum_point   = 0;
                                    $sum_wd      = 0;
                                    $sum_komisi  = 0;

                                    while ($row = mysqli_fetch_assoc($result_promotor)):
                                        $total_nilai = $row['point'] + $row['total_withdraw'] + $row['total_komisi_reff'];
                                        $sum_point  += $row['point'];
                                        $sum_wd     += $row['total_withdraw'];
                                        $sum_komisi += $row['total_komisi_reff'];

                                        $row_class = match(true) {
                                            $rank == 1 => 'table-warning',
                                            $rank == 2 => 'table-secondary',
                                            $rank == 3 => 'table-info',
                                            default    => ''
                                        };
                                        $icon = match(true) {
                                            $rank == 1 => '🥇',
                                            $rank == 2 => '🥈',
                                            $rank == 3 => '🥉',
                                            default    => '🏅'
                                        };

                                        $status_color = match($row['status']) {
                                            'Active'   => 'success',
                                            'Inactive' => 'danger',
                                            default    => 'warning'
                                        };
                                        $status_text = match($row['status']) {
                                            'Active'   => 'Aktif',
                                            'Inactive' => 'Nonaktif',
                                            default    => 'Unverified'
                                        };
                                ?>
                                    <tr class="<?= $row_class; ?>">
                                        <td class="fw-bold text-primary"><?= $icon; ?> TOP <?= $rank; ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($row['phone']); ?></td>
                                        <td><span class="badge bg-<?= $status_color; ?>"><?= $status_text; ?></span></td>
                                        <td>Rp <?= number_format($row['point'],             0, ',', '.'); ?></td>
                                        <td>Rp <?= number_format($row['total_withdraw'],     0, ',', '.'); ?></td>
                                        <td class="text-primary">Rp <?= number_format($row['total_komisi_reff'], 0, ',', '.'); ?></td>
                                        <td><span class="badge bg-info"><?= number_format($row['total_downline'],       0, ',', '.'); ?> user</span></td>
                                        <td><span class="badge bg-success"><?= number_format($row['total_downline_aktif'], 0, ',', '.'); ?> aktif</span></td>
                                        <td class="fw-bold text-success">Rp <?= number_format($total_nilai, 0, ',', '.'); ?></td>
                                    </tr>
                                <?php
                                        $rank++;
                                    endwhile;
                                ?>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="3" class="text-end fw-bold">Total:</th>
                                        <th class="text-primary fw-bold">Rp <?= number_format($sum_point,  0, ',', '.'); ?></th>
                                        <th class="text-danger fw-bold">Rp <?= number_format($sum_wd,     0, ',', '.'); ?></th>
                                        <th class="text-info fw-bold">Rp <?= number_format($sum_komisi, 0, ',', '.'); ?></th>
                                        <th colspan="3"></th>
                                    </tr>
                                </tfoot>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fa fa-info-circle fa-2x mb-2"></i><br>
                                            Belum ada user dengan uplink level Promotor.<br>
                                            <small>Ubah uplink_level user menjadi <strong>promotor</strong> di menu Kelola Pengguna.</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .gradient-blue {
        background: linear-gradient(135deg, #007bff, #0056b3);
    }
    .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
</style>

<?php require '../lib/footer_admin.php'; ?>