<?php

if (isset($_COOKIE['X_SESSION'])) {
    try {
        $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], array('HS256'));
        $check_user = $model->db_query($db, "*", "users", "id = '" . $jwt->id . "'");
        if ($check_user['count'] !== 1) {
            logout();
            $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
            exit(header("Location: " . base_url('auth/login')));
        } elseif ($check_user['rows']['status'] == 'Inactive') {
            logout();
            $_SESSION['result'] = ['response' => 'error', 'msg' => 'Akun Anda dinonaktifkan.'];
            exit(header("Location: " . base_url('auth/login')));
        } elseif ($check_user['rows']['status'] == 'Unverified') {
            logout();
            $_SESSION['result'] = ['response' => 'error', 'msg' => 'Akun Anda belum diverifikasi.'];
            exit(header("Location: " . base_url('auth/login')));
        }
        $login = $check_user['rows'];
    } catch (Exception $e) {
        logout();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
        exit(header("Location: " . base_url('auth/login')));
    }
}
