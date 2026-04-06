<?php

require '../../mainconfig.php';
$tokens = $model->db_query($db, "*", "tokens", "status = 'Active'");
if ($tokens['count'] == 0) {
    echo 'Tidak ada data.';
} elseif ($tokens['count'] == 1) {
    $token = $tokens['rows'];
    $start_date = new DateTime($token['created_at']);
    $since_start = $start_date->diff(new DateTime(date('Y-m-d H:i:s')));
    if ($since_start->h >= 1 || $since_start->days > 0) {
        $model->db_update($db, "tokens", array('status' => 'Expired', 'updated_at' => date('Y-m-d H:i:s')), "id = '" . $token['id'] . "'");
        echo 'Token Expired.' . PHP_EOL;
    }
} else {
    foreach ($tokens['rows'] as $token) {
        $start_date = new DateTime($token['created_at']);
        $since_start = $start_date->diff(new DateTime(date('Y-m-d H:i:s')));
        if ($since_start->h >= 1 || $since_start->days > 0) {
            $model->db_update($db, "tokens", array('status' => 'Expired', 'updated_at' => date('Y-m-d H:i:s')), "id = '" . $token['id'] . "'");
            echo 'Token Expired.' . PHP_EOL;
        }
    }
}
