<?php
// lib/check_session.php - Logic Verifikasi Sesi dan Pemuatan Data User

use \Firebase\JWT\JWT;
// use \Firebase\JWT\Key; // Tambahkan ini jika Anda menggunakan JWT versi 6.0 ke atas

// Pastikan base_url, logout, protect, $db, $model, dan $config sudah didefinisikan di mainconfig.php

if (!isset($_COOKIE['X_SESSION'])) {
    $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, silahkan login untuk melanjutkan.'];
    exit(header("Location: " . base_url('auth/login')));
}
try {
    // 1. Decode JWT
    // Jika Anda menggunakan JWT versi baru, ganti baris di bawah ini:
    // $jwt = JWT::decode($_COOKIE['X_SESSION'], new Key($config['jwt']['secret'], 'HS256'));
    $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_SESSION'], $config['jwt']['secret'], array('HS256'));

    $user_id = protect($jwt->id);
    $session_token = protect($_COOKIE['X_SESSION']);

    // 2. Query Database: SELECT * untuk memastikan semua kolom bank terambil
    $check_user = $model->db_query($db, "*", "users", "id = '{$user_id}' AND x_session = '{$session_token}'");

    if ($check_user['count'] !== 1) {
        logout();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Otentikasi Dibutuhkan, Sesi tidak ditemukan.'];
        exit(header("Location: " . base_url('auth/login')));
    } 
    
    // Ambil data user
    $user_data = $check_user['rows'];

    // 3. Verifikasi Tanda Tangan (HMAC Check)
    $calculated_hmac = hash_hmac('sha256', $user_data['id'] . $user_data['x_uniqueid'], $config['hmac']['key']);
    
    if (!hash_equals($calculated_hmac, $jwt->sign)) {
        logout();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Tanda tangan sesi tidak valid.'];
        exit(header("Location: " . base_url('auth/login')));
    } 

    // 4. Cek Status Akun
    if ($user_data['status'] == 'Inactive') {
        logout();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Akun Anda dinonaktifkan.'];
        exit(header("Location: " . base_url('auth/login')));
    } elseif ($user_data['status'] == 'Unverified') {
        logout();
        $_SESSION['result'] = ['response' => 'error', 'msg' => 'Akun Anda belum diverifikasi.'];
        exit(header("Location: " . base_url('auth/login')));
    }

    // 5. LANGKAH KRITIS: Set variabel global $login
    // Semua data user, termasuk rekening, pemilik, dan no_rek, kini ada di $login
    $login = $user_data; 
    
} catch (Exception $e) {
    logout();
    $_SESSION['result'] = ['response' => 'error', 'msg' => 'Sesi tidak valid atau kedaluwarsa.'];
    exit(header("Location: " . base_url('auth/login')));
}
// Jika sampai baris ini, sesi valid dan $login sudah terisi.