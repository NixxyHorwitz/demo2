<?php

if (empty($_SESSION['token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$config['csrf_token'] = $_SESSION['token'];
$csrf_token = true;
if ($_POST and !isset($_POST['csrf_token'])) {
    $csrf_token = false;
} elseif ($_POST and isset($_POST['csrf_token'])) {
    if (hash_equals($_SESSION['token'], $_POST['csrf_token']) == false) {
        $csrf_token = false;
    }
}
