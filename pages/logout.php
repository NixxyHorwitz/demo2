<?php

require '../mainconfig.php';

if (!isset($_COOKIE['X_SESSION'])) {
    exit(header("Location: " . base_url('auth/login')));
}
try {
    $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], array('HS256'));
    $check_user = $model->db_query($db, "*", "users", "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_SESSION']) . "'");
    if ($check_user['count'] !== 1) {
        logout();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
        exit(header("Location: " . base_url('auth/login')));
    }
    logout();
    exit(header("Location: " . base_url('auth/login')));
} catch (Exception $e) {
    logout();
    exit(header("Location: " . base_url('auth/login')));
}
