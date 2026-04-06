<?php

if (isset($_COOKIE['X_ADMIN_SESSION'])) {
    try {
        $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_ADMIN_SESSION'], $config['jwt']['secret'], array('HS256'));
        $check_user = $model->db_query($db, "*", "admins", "id = '" . $jwt->id . "'");
        if ($check_user['count'] !== 1) {
            logout();
            $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
            exit(header("Location: " . base_url('babikode/login')));
        }
        $login = $check_user['rows'];
    } catch (Exception $e) {
        logout();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
        exit(header("Location: " . base_url('babikode/login')));
    }
}
