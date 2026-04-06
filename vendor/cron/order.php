<?php

require '../../mainconfig.php';
$api = $model->db_query($db, "api_key", "settings")['rows'];
$orders = $model->db_query($db, "*", "orders", "status IN ('Pending', 'Processing')", "rand()", "LIMIT 100");
if ($orders['count'] > 1)
    foreach ($orders['rows'] as $row) {
        $post_data = array(
            'api_key' => hash_hmac('sha256', base_url() . $_SERVER['SERVER_ADDR'] . ":" . $api['api_key'], $config['hmac']['key']),
            'id' => $row['poid']
        );
        $curl = post_curl(base_url('api/status'), $post_data);
        $result = json_decode($curl, true);
        $status = '';
        $remains = '';
        $start = '';
        if ($result['result']) {
            $status = $result['data']['status'];
            $start = $result['data']['start'];
            $remains = $result['data']['remains'];
        }
        if (!empty($status)) {
            $start = match (true) {
                $start > 0 => $start,
                default => $row['start_count']
            };
            $remains = match (true) {
                $remains >= 0 => $remains,
                default => $row['remains']
            };
            $update = $model->db_update($db, "orders", array('status' => $status, 'start_count' => $start, 'remains' => $remains, 'updated_at' => date('Y-m-d H:i:s')), "id = '" . $row['id'] . "'");
            if ($update) {
                if ($status == 'Error') {
                    $user = $model->db_query($db, "id, point", "users", "id = '" . $row['user_id'] . "'")['rows'];
                    $model->db_insert($db, "point_logs", array('user_id' => $row['user_id'], 'type' => 'plus', 'amount' => $row['price'], 'description' => 'Pengembalian Pemesanan. ID: ' . $row['id'], 'created_at' => date('Y-m-d H:i:s')));
                    $model->db_update($db, "users", array('point' => $user['point'] + $row['price']), "id = '" . $row['user_id'] . "'");
                    $model->db_update($db, "orders", array('price' => 0, 'updated_at' => date('Y-m-d H:i:s')), "id = '" . $row['id'] . "'");
                } elseif ($status == 'Partial') {
                    $user = $model->db_query($db, "id, point", "users", "id = '" . $row['user_id'] . "'")['rows'];
                    $rate = $row['price'] / $row['quantity'];
                    $count_success = $row['quantity'] - $row['remains'];
                    $total_price = $rate * $count_success;
                    $total_refund = $row['price'] - $total_price;
                    $model->db_insert($db, "point_logs", array('user_id' => $row['user_id'], 'type' => 'plus', 'amount' => $total_refund, 'description' => 'Pengembalian Pemesanan. ID: ' . $row['id'], 'created_at' => date('Y-m-d H:i:s')));
                    $model->db_update($db, "users", array('point' => $user['point'] + $total_refund), "id = '" . $row['user_id'] . "'");
                    $model->db_update($db, "orders", array('price' => $total_price, 'updated_at' => date('Y-m-d H:i:s')), "id = '" . $row['id'] . "'");
                }
                echo 'Cek status #' . $row['id'] . ' berhasil.' . PHP_EOL;
            } else {
                echo 'Cek status #' . $row['id'] . ' gagal (database).' . PHP_EOL;
            }
        } else {
            echo 'Cek status #' . $row['id'] . ' gagal (curl).' . PHP_EOL;
        }
    }
