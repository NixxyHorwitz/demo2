<?php

if (!isset($_COOKIE['X_ADMIN_SESSION'])) {
    $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
    exit(header("Location: " . base_url('babikode/login')));
}
try {
    $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_ADMIN_SESSION'], $config['jwt']['secret'], array('HS256'));
    $check_user = $model->db_query($db, "*", "admins", "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_ADMIN_SESSION']) . "'");
    if ($check_user['count'] !== 1) {
        logout_admin();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
        exit(header("Location: " . base_url('babikode/login')));
    } elseif (!hash_equals(hash_hmac('sha256', $check_user['rows']['id'] . $check_user['rows']['x_uniqueid'], $config['hmac']['key']), $jwt->sign)) {
        logout_admin();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
        exit(header("Location: " . base_url('babikode/login')));
    }
} catch (Exception $e) {
    logout_admin();
    $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
    exit(header("Location: " . base_url('babikode/login')));
}
