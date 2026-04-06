<?php

function base_url(?string $path = null): string
{
    global $config;
    if (!is_null($path)) {
        $result = $config['web']['base_url'] . $path;
    } else {
        $result = $config['web']['base_url'];
    }

    return $result;
}

function base_url_ws(?string $path = null): string
{
    global $config;
    if (!is_null($path)) {
        $result = substr($config['web']['base_url'], 0, -1) . $path;
    } else {
        $result = substr($config['web']['base_url'], 0, -1);
    }

    return $result;
}

function base_title(): string
{
    global $config;
    return $config['web']['title'];
}

function csrf_token(bool $generate = false): string
{
    global $config;
    if ($generate) {
        if (function_exists('random_bytes')) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        } else {
            $_SESSION['token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    } else {
        return $config['csrf_token'];
    }
}

function check_input(array $input, array $data): bool
{
    $input = array_keys($input);
    $false = 0;
    foreach ($data as $key) {
        if (in_array($key, $input) == false) {
            $false++;
        }
    }
    if ($false == 0) {
        return true;
    } else {
        return false;
    }
}

function check_empty(array $input): bool
{
    $result = true;
    foreach ($input as $key => $value) {
        $result = false;
        if (empty($value) == true) {
            $result = true;
            break;
        }
    }
    return $result;
}

function str_rand(int|float $length = 10, bool $num = false): string
{
    if ($num) {
        $result = substr(str_shuffle(str_repeat($x = '0123456789', ceil($length / strlen($x)))), 1, $length);
    } else {
        $result = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
    }
    return $result;
}

function csrf_init(bool $a = true): string
{
    $flength = 28;
    $slength = 29;
    $frand = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyz', ceil($flength / strlen($x)))), 1, $flength);
    $srand = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyz', ceil($slength / strlen($x)))), 1, $slength);
    if ($a) {
        $csrf_init = csrf_token();
    } else {
        $csrf_init = $frand . 'bin2hex' . $srand;
    }
    return $csrf_init;
}

function format_date(string $date, bool $print_day = false, bool $short_month = false): string
{
    $days = array(
        1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'
    );
    if (!$short_month) {
        $months = array(
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        );
    } else {
        $months = array(
            1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'
        );
    }
    $split       = explode('-', $date);
    $result = $split[2] . ' ' . $months[(int)$split[1]] . ' ' . $split[0];
    if ($print_day) {
        $num = date('N', strtotime($date));
        return $days[$num] . ', ' . $result;
    }
    return $result;
}

function format_time(string $datetime): string
{
    $selisih = time() - strtotime($datetime);
    $detik = $selisih;
    $menit = round($selisih / 60);
    $jam = round($selisih / 3600);
    $hari = round($selisih / 86400);
    $minggu = round($selisih / 604800);
    $bulan = round($selisih / 2419200);
    $tahun = round($selisih / 29030400);
    $waktu = match (true) {
        $detik <= 60 => $detik . ' detik yang lalu',
        $menit <= 60 => $menit . ' menit yang lalu',
        $jam <= 60 => $jam . ' jam yang lalu',
        $hari <= 60 => $hari . ' hari yang lalu',
        $minggu <= 60 => $minggu . ' minggu yang lalu',
        $bulan <= 60 => $bulan . ' bulan yang lalu',
        $tahun <= 60 => $tahun . ' tahun yang lalu'
    };
    return $waktu;
}

function protect(string $string): string
{
    global $db;
    if (ini_get('magic_quotes_gpc') == 'off') {
        $string = addslashes($string);
    }
    $string = htmlspecialchars($string);
    $codes = array("script", "java", "applet", "iframe", "meta", "object", "html", "<", ">", ";", "'", "%");
    $string = str_replace($codes, "", $string);
    $string = mysqli_real_escape_string($db, $string);
    return $string;
}

function get_client_ip(bool $get_browser = false): string
{
    $ipaddress = '';
    $ipaddress = match (true) {
        getenv('HTTP_CLIENT_IP') <> '' => getenv('HTTP_CLIENT_IP'),
        getenv('HTTP_X_FORWARDED_FOR') <> '' => getenv('HTTP_X_FORWARDED_FOR'),
        getenv('HTTP_X_FORWARDED') <> '' => getenv('HTTP_X_FORWARDED'),
        getenv('HTTP_FORWARDED_FOR') <> '' => getenv('HTTP_FORWARDED_FOR'),
        getenv('HTTP_FORWARDED') <> '' => getenv('HTTP_FORWARDED'),
        getenv('REMOTE_ADDR') <> '' => getenv('REMOTE_ADDR'),
        default => 'UNKNOWN'
    };
    if ($get_browser) {
        $ipaddress = $_SERVER['HTTP_USER_AGENT'];
    }
    return $ipaddress;
}

function validateDate(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function sensor_email(string $email): string|bool|array
{
    $exp = explode('@', $email, 2);
    if (isset($exp[0], $exp[1])) {
        $e1 = $exp[0];
        $e2 = $exp[1];
        $result = match ($e1) {
            strlen($e1) == 3 or strlen($e1) == 4 => substr($e1, 0, -2) . '**@' . $e2,
            strlen($e1) == 5 or strlen($e1) == 6 => substr($e1, 0, -3) . '***@' . $e2,
            strlen($e1) < 10 => substr($e1, 0, -4) . '****@' . $e2,
            strlen($e1) <= 15 => substr($e1, 0, -5) . '*****@' . $e2,
            strlen($e1) <= 20 => substr($e1, 0, -6) . '******@' . $e2,
            strlen($e1) <= 25 => substr($e1, 0, -7) . '*******@' . $e2,
            default => substr($e1, 0, -8) . '********@' . $e2
        };
    } else {
        $result = false;
    }
    return $result;
}

function logout(): bool
{
    $logout = setcookie(name: 'X_SESSION', value: '', expires_or_options: time() - 3600, path: "/");
    $result = false;
    if ($logout) {
        $result = true;
    }
    return $result;
}

function logout_admin(): bool
{
    $logout = setcookie(name: 'X_ADMIN_SESSION', value: '', expires_or_options: time() - 3600, path: "/");
    $result = false;
    if ($logout) {
        $result = true;
    }
    return $result;
}

function post_curl($end_point, $post, $header = '')
{
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL             => $end_point,
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => http_build_query($post),
        CURLOPT_HTTPHEADER      => (empty($header)) ? [] : $header,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_SSL_VERIFYPEER  => 0,
        CURLOPT_RETURNTRANSFER  => true
    ));
    $result = curl_exec($ch);
    if (curl_errno($ch) != 0 && empty($result)) {
        $result = false;
    }
    curl_close($ch);
    return $result;
}
