<?php

require '../mainconfig.php';
$page_type = 'login';
$page_name = 'Masuk';

$query_settings = $model->db_query($db, "*", "settings", "id = 1");
$config_web = $query_settings['rows'];

if (isset($_COOKIE['X_ADMIN_SESSION'])) {
    try {
        $jwt = \Firebase\JWT\JWT::decode($_COOKIE['X_ADMIN_SESSION'], $config['jwt']['secret'], array('HS256'));
        $check_user = $model->db_query($db, "*", "admins", "id = '" . protect($jwt->id) . "' AND x_session = '" . protect($_COOKIE['X_ADMIN_SESSION']) . "'");
        if ($check_user['count'] !== 1) {
            logout_admin();
            exit(header("Location: " . base_url('babikode/login')));
        } elseif (!hash_equals(hash_hmac('sha256', $check_user['rows']['id'] . $check_user['rows']['x_uniqueid'], $config['hmac']['key']), $jwt->sign)) {
            logout_admin();
            exit(header("Location: " . base_url('babikode/login')));
        } else {
            exit(header("Location: " . base_url()));
        }
    } catch (Exception $e) {
        logout_admin();
        exit(header("Location: " . base_url('babikode/login')));
    }
}
if ($_POST) {
    $data = array('username', 'password');
    if (!check_input($_POST, $data)) {
        $_SESSION['result'] = ['response' => 'error', 'title' => 'Ups!', 'msg' => 'Input tidak sesuai.'];
    } elseif (!$csrf_token) {
        $_SESSION['result'] = ['response' => 'error', 'title' => 'Ups!', 'msg' => 'Permintaan tidak diterima, mohon refresh halaman ini.'];
    } else {
        $input_post = array(
            'username' => protect(strtolower(trim($_POST['username']))),
            'password' => trim($_POST['password']),
        );
        if (check_empty($input_post)) {
            $_SESSION['result'] = ['response' => 'error', 'title' => 'Ups!', 'msg' => 'Mohon isi semua input.'];
        } else {
            $check_user = $model->db_query($db, "*", "admins", "BINARY username = '" . $input_post['username'] . "'");
            if ($check_user['count'] == 1) {
                if (password_verify($input_post['password'], $check_user['rows']['password'])) {
                    $uniqueid = time();
                    $payload = array(
                        "id" => $check_user['rows']['id'],
                        'sign' => hash_hmac('sha256', $check_user['rows']['id'] . $uniqueid, $config['hmac']['key']),
                        "exp" => time() + \Firebase\JWT\JWT::$leeway + 86400
                    );
                    $createJwt = \Firebase\JWT\JWT::encode($payload, $config['jwt']['secret']);
                    setcookie(name: 'X_ADMIN_SESSION', value: $createJwt, expires_or_options: time() + (86400 * 1), path: '/');
                    $model->db_update($db, "admins", array('x_session' => $createJwt, 'x_uniqueid' => $uniqueid), "id = '" . $check_user['rows']['id'] . "'");
                    $model->db_insert($db, "admin_login_logs", array('admin_id' => $check_user['rows']['id'], 'ip_address' => get_client_ip(), 'user_agent' => get_client_ip(get_browser: true), 'created_at' => date('Y-m-d H:i:s')));
                    $_SESSION['result'] = ['response' => 'success', 'title' => 'Yeay!', 'msg' => 'Selamat datang <b>' . $check_user['rows']['username'] . '</b>, semoga harimu menyenangkan!'];
                    exit(header("Location: " . base_url('babikode/')));
                } else {
                    $_SESSION['result'] = ['response' => 'error', 'title' => 'Ups!', 'msg' => 'Password tidak sesuai.'];
                }
            } else {
                $_SESSION['result'] = ['response' => 'error', 'title' => 'Ups!', 'msg' => 'Username tidak terdaftar.'];
            }
        }
    }
    exit(header("Location: " . base_url('babikode/login')));
}
?>
<!DOCTYPE html>
<html class="loading" lang="en" data-textdirection="ltr">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-pr©¡sident-compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta name="description" content="<?= htmlspecialchars($config['meta']['description'] ?? ''); ?>">
  <meta name="keywords" content="<?= htmlspecialchars($config['meta']['keyword'] ?? ''); ?>">

  <title><?= htmlspecialchars($page_name ?? 'Halaman'); ?></title>

  <link rel="apple-touch-icon" href="<?= base_url('assets/images/'.$config_web['web_logo']) ?>">
  <link rel="shortcut icon" type="image/x-icon" href="<?= base_url('assets/images/'.$config_web['web_logo']) ?>">
  <link href="https://fonts.googleapis.com/css?family=Muli:300,300i,400,400i,600,600i,700,700i%7CComfortaa:300,400,700" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
*{
    box-sizing:border-box;
    font-family:'Inter',sans-serif;
}

body{
    margin:0;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(135deg,#0f172a,#1e293b,#020617);
    overflow:hidden;
}

/* background glow */
body::before{
    content:'';
    position:absolute;
    width:600px;
    height:600px;
    background:radial-gradient(circle,#38bdf8,transparent 60%);
    top:-150px;
    left:-150px;
    opacity:.25;
    pointer-events: none;
}

body::after{
    content:'';
    position:absolute;
    width:500px;
    height:500px;
    background:radial-gradient(circle,#6366f1,transparent 60%);
    bottom:-150px;
    right:-150px;
    opacity:.25;
    pointer-events: none;
}

/* LOGIN CARD */
.login-box{
    position:relative;
    width:100%;
    max-width:420px;
    padding:40px 35px;
    border-radius:20px;

    background:rgba(255,255,255,0.08);
    backdrop-filter:blur(20px);
    -webkit-backdrop-filter:blur(20px);

    border:1px solid rgba(255,255,255,0.15);
    box-shadow:0 20px 60px rgba(0,0,0,.45);
    color:#fff;
    animation:fadeUp .6s ease;
}

@keyframes fadeUp{
    from{opacity:0; transform:translateY(25px);}
    to{opacity:1; transform:translateY(0);}
}

.login-logo{
    text-align:center;
    margin-bottom:15px;
}

.login-logo img{
    width:90px;
    filter:drop-shadow(0 5px 15px rgba(0,0,0,.4));
}

.login-title{
    text-align:center;
    font-size:24px;
    font-weight:700;
    margin-bottom:5px;
}

.login-sub{
    text-align:center;
    font-size:13px;
    color:#cbd5e1;
    margin-bottom:28px;
}

/* INPUT */
.form-group{
    margin-bottom:18px;
}

.input-wrapper{
    position:relative;
}

.input-wrapper i{
    position:absolute;
    top:50%;
    left:14px;
    transform:translateY(-50%);
    color:#94a3b8;
    font-size:16px;
}

.form-control{
    width:100%;
    height:48px;
    border-radius:12px;
    border:1px solid rgba(255,255,255,0.15);
    background:rgba(255,255,255,0.06);
    color:#fff;
    padding-left:44px;
    font-size:15px;
    outline:none;
    transition:.25s;
}

.form-control::placeholder{
    color:#94a3b8;
}

.form-control:focus{
    border-color:#38bdf8;
    box-shadow:0 0 0 3px rgba(56,189,248,.25);
}

/* BUTTON */
.btn-login{
    width:100%;
    height:48px;
    border:none;
    border-radius:12px;
    font-size:16px;
    font-weight:600;
    cursor:pointer;
    color:white;
    background:linear-gradient(135deg,#38bdf8,#6366f1);
    transition:.25s;
}

.btn-login:hover{
    transform:translateY(-2px);
    box-shadow:0 10px 25px rgba(99,102,241,.45);
}

/* FOOTER */
.login-footer{
    text-align:center;
    margin-top:18px;
    font-size:12px;
    color:#94a3b8;
}
</style>


<div class="login-box">

    <div class="login-logo">
        <img src="<?= base_url('assets/images/'.$config_web['web_logo']) ?>">
    </div>

    <div class="login-title">Admin Panel</div>
    <div class="login-sub">Silakan masuk untuk melanjutkan</div>

    <?php if (isset($_SESSION['result'])) { ?>
        <script>
            Swal.fire({
                title: '<?= ($_SESSION['result']['response']=='success')?'Yeay!':'Ups!'; ?>',
                icon: '<?= ($_SESSION['result']['response']=='success')?'success':'error'; ?>',
                html: '<?= $_SESSION['result']['msg']; ?>',
                confirmButtonColor:'#6366f1'
            });
        </script>
    <?php unset($_SESSION['result']); } ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">

        <div class="form-group">
            <div class="input-wrapper">
                <i class="ft-user"></i>
                <input type="text" name="username" class="form-control" placeholder="Username">
            </div>
        </div>

        <div class="form-group">
            <div class="input-wrapper">
                <i class="ft-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="Password">
            </div>
        </div>

        <button class="btn-login">Masuk Dashboard</button>
    </form>

    <div class="login-footer">
        © <?= date('Y'); ?> Admin System
    </div>

</div>

<?php
require '../lib/footer_guest.php';
?>