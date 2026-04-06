<?php

if (!isset($_COOKIE['X_SESSION'])) {
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}
try {
    $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], array('HS256'));
    $check_user = $model->db_query($db, "*", "users", "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_SESSION']) . "'");
    if ($check_user['count'] !== 1) {
        logout();
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif (!hash_equals(hash_hmac('sha256', $check_user['rows']['id'] . $check_user['rows']['x_uniqueid'], $config['hmac']['key']), $jwt->sign)) {
        logout();
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif ($check_user['rows']['status'] == 'Inactive') {
        logout();
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    } elseif ($check_user['rows']['status'] == 'Unverified') {
        logout();
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }
} catch (Exception $e) {
    logout();
    exit(header("HTTP/1.1 403 No direct script access allowed!"));
}
