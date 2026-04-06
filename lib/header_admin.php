<?php
if (isset($model) && isset($db)) {
    $query_settings = $model->db_query($db, "*", "settings", "id = 1");
    $config_web = $query_settings['rows'];
}
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start_pageload = $time;
require 'is_login_admin.php';
?>

<!DOCTYPE html>
<html class="loading" lang="en" data-textdirection="ltr">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="<?= $config['meta']['description']; ?>">
    <meta name="keywords" content="<?= $config['meta']['keyword']; ?>">
    <title><?= base_title(); ?> - <?= $page_name; ?></title>
    <link rel="apple-touch-icon" href="<?= base_url('assets/images/' . $config_web['web_logo']); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= base_url('assets/images/' . $config_web['web_logo']); ?>">
    <link href="https://fonts.googleapis.com/css?family=Muli:300,300i,400,400i,600,600i,700,700i%7CComfortaa:300,400,700" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/vendors/css/vendors.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/vendors/css/forms/toggle/switchery.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/plugins/forms/switch.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/core/colors/palette-switch.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/bootstrap-extended.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/colors.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/components.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/core/menu/menu-types/horizontal-menu.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/css/core/colors/palette-gradient.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/vendors/css/forms/selects/select2.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('app-assets/fonts/simple-line-icons/style.min.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/css/style.css'); ?>">
    <style>
        /* === ROOT VARIABLES === */
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --white: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* === RESET & BASE === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif !important;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
            color: var(--text-primary) !important;
            font-size: 15px;
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        /* === TOP vvnBAR (HEADER) === */
        .header-vvnbar.vvnbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3) !important;
            border: none !important;
            padding: 0.75rem 1rem !important;
            backdrop-filter: blur(10px);
            position: relative;
        }

        .header-vvnbar .vvnbar-brand {
            transition: var(--transition);
        }

        .header-vvnbar .vvnbar-brand:hover {
            transform: scale(1.05);
        }

        .header-vvnbar .brand-text {
            color: var(--white) !important;
            font-weight: 800 !important;
            font-size: 1.5rem !important;
            letter-spacing: -0.5px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
            margin: 0 !important;
        }

        .header-vvnbar .vvn-link {
            color: var(--white) !important;
            transition: var(--transition);
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
        }

        .header-vvnbar .vvn-link:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            transform: translateY(-2px);
        }

        .header-vvnbar .vvn-link i,
        .header-vvnbar .vvn-link .ft-menu {
            color: var(--white) !important;
            font-size: 1.2rem;
        }

        /* === HORIZONTAL MENU (MAIN vvnIGATION) === */
        .header-vvnbar.vvnbar-horizontal {
            background: var(--white) !important;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08) !important;
            border-bottom: 2px solid var(--border-color) !important;
            padding: 0 !important;
            margin-top: 0 !important;
        }

        .vvnbar-horizontal .main-menu-content {
            padding: 0 1rem;
        }

        .vvnbar-horizontal #main-menu-vvnigation {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
            margin: 0;
            padding: 0.5rem 0;
        }

        .vvnbar-horizontal .vvn-item {
            position: relative;
            margin: 0;
        }

        .vvnbar-horizontal .vvn-link {
            color: var(--text-primary) !important;
            font-weight: 600 !important;
            font-size: 0.9rem;
            padding: 0.75rem 1.25rem !important;
            border-radius: 10px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent !important;
            position: relative;
            overflow: hidden;
        }

        .vvnbar-horizontal .vvn-link i,
        .vvnbar-horizontal .vvn-link [class^="ft-"],
        .vvnbar-horizontal .vvn-link [class*=" ft-"] {
            color: var(--primary-color) !important;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .vvnbar-horizontal .vvn-link span {
            color: var(--text-primary) !important;
            position: relative;
        }

        .vvnbar-horizontal .vvn-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            transition: var(--transition);
            border-radius: 10px;
        }

        .vvnbar-horizontal .vvn-link:hover {
            background: transparent !important;
            transform: translateY(-2px);
        }

        .vvnbar-horizontal .vvn-link:hover::before {
            width: 100%;
        }

        .vvnbar-horizontal .vvn-link:hover span,
        .vvnbar-horizontal .vvn-link:hover i,
        .vvnbar-horizontal .vvn-link:hover [class^="ft-"],
        .vvnbar-horizontal .vvn-link:hover [class*=" ft-"] {
            color: var(--white) !important;
        }

        /* === ACTIVE STATE MENU === */
        .vvnbar-horizontal .vvn-item.active>.vvn-link {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: var(--white) !important;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .vvnbar-horizontal .vvn-item.active>.vvn-link span,
        .vvnbar-horizontal .vvn-item.active>.vvn-link i,
        .vvnbar-horizontal .vvn-item.active>.vvn-link [class^="ft-"],
        .vvnbar-horizontal .vvn-item.active>.vvn-link [class*=" ft-"] {
            color: var(--white) !important;
        }

        .vvnbar-horizontal .vvn-item.active>.vvn-link::before {
            width: 100%;
        }

        /* === DROPDOWN MENU === */
        .dropdown-menu {
            background: var(--white) !important;
            border: none !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
            border-radius: 12px !important;
            min-width: 220px;
            animation: dropdownFade 0.3s ease;
            z-index: 999 !important;
        }

        @keyframes dropdownFade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .vvnbar-horizontal .dropdown-menu .arrow_box {
            padding: 0;
        }

    

        .vvnbar-horizontal .dropdown-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: transparent !important;
            transform: translateX(5px);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));

        }

        .vvnbar-horizontal .dropdown-item:hover::before {
            width: 100%;
        }

        .vvnbar-horizontal .dropdown-item:hover {
            color: var(--white) !important;
        }

        .vvnbar-horizontal .dropdown-item.active,
        .vvnbar-horizontal li.active .dropdown-item {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: var(--white) !important;
        }

        .vvnbar-horizontal .dropdown-item.active::before,
        .vvnbar-horizontal li.active .dropdown-item::before {
            width: 100%;
        }

        /* === CONTENT WRAPPER === */
        .app-content {
            position: relative;
        }

        .content-wrapper {
            padding: 2rem 1.5rem !important;
            position: relative;
            min-height: calc(100vh - 160px);
        }

        .content-wrapper-before {
            display: none !important;
        }

        /* === CONTENT HEADER === */
        .content-header {
            margin-bottom: 2rem !important;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .content-header-title {
            font-size: 2rem !important;
            font-weight: 800 !important;
            color: var(--text-primary) !important;
            margin: 0 !important;
            position: relative;
            display: inline-block;
            padding-left: 1rem;
        }

        .content-header-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 70%;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
        }

        .breadcrumb {
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* === CARDS === */
        .card {
            background: var(--white) !important;
            border: none !important;
            border-radius: 16px !important;
            box-shadow: var(--shadow-md) !important;
            transition: var(--transition);
            overflow: hidden;
            position: relative;
            margin-bottom: 1.5rem;
        }

        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.03), rgba(139, 92, 246, 0.03));
            opacity: 0;
            transition: var(--transition);
            pointer-events: none;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl) !important;
        }

        .card:hover::after {
            opacity: 1;
        }

        .card-body {
            padding: 1.5rem !important;
            position: relative;
        }

        /* === GRADIENT CARDS FULL OVERRIDE === */
        .gradient-green,
        .gradient-blue,
        .gradient-red,
        .gradient-orange {
            position: relative;
            overflow: hidden;
            border: none !important;
        }

        .gradient-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        }

        .gradient-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
        }

        .gradient-red {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        }

        .gradient-orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
        }

        .gradient-green .card-body,
        .gradient-blue .card-body,
        .gradient-red .card-body,
        .gradient-orange .card-body {
            position: relative;
        }

        .gradient-green h6,
        .gradient-blue h6,
        .gradient-red h6,
        .gradient-orange h6,
        .gradient-green h3,
        .gradient-blue h3,
        .gradient-red h3,
        .gradient-orange h3,
        .gradient-green i,
        .gradient-blue i,
        .gradient-red i,
        .gradient-orange i {
            color: var(--white) !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .gradient-green::before,
        .gradient-blue::before,
        .gradient-red::before,
        .gradient-orange::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 0.5;
            }

            50% {
                transform: scale(1.1) rotate(180deg);
                opacity: 0.8;
            }
        }

        /* === TABLES FULL REDESIGN === */
        .table-responsive {
            border-radius: 12px !important;
            box-shadow: var(--shadow-sm) !important;
            background: var(--white);
            border: 1px solid var(--border-color);
        }

        .table {
            margin: 0 !important;
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
        }

        .table thead th {
            color: var(--white) !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.8rem !important;
            padding: 1rem 1.25rem !important;
            border: none !important;
            white-space: nowrap;
        }

        .table tbody {
            background: var(--white);
        }

        .table tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color) !important;
        }

        .table tbody tr:last-child {
            border-bottom: none !important;
        }

        .table tbody tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .table tbody td {
            padding: 1rem 1.25rem !important;
            vertical-align: middle !important;
            color: var(--text-primary) !important;
            font-size: 0.9rem;
            border: none !important;
        }

        .table tbody td.fw-bold {
            color: var(--primary-color) !important;
            font-weight: 700 !important;
        }

        .table tfoot {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
            border-top: 2px solid var(--border-color);
        }

        .table tfoot th {
            padding: 1rem 1.25rem !important;
            font-weight: 700 !important;
            color: var(--text-primary) !important;
            border: none !important;
        }

        /* === TABLE VARIANT COLORS === */
        .table-warning {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%) !important;
        }

        .table-warning:hover {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.08) 100%) !important;
        }

        .table-secondary {
            background: linear-gradient(90deg, rgba(100, 116, 139, 0.1) 0%, rgba(100, 116, 139, 0.05) 100%) !important;
        }

        .table-secondary:hover {
            background: linear-gradient(90deg, rgba(100, 116, 139, 0.15) 0%, rgba(100, 116, 139, 0.08) 100%) !important;
        }

        .table-info {
            background: linear-gradient(90deg, rgba(6, 182, 212, 0.1) 0%, rgba(6, 182, 212, 0.05) 100%) !important;
        }

        .table-info:hover {
            background: linear-gradient(90deg, rgba(6, 182, 212, 0.15) 0%, rgba(6, 182, 212, 0.08) 100%) !important;
        }

        .table-light {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
        }

        /* === TEXT COLORS === */
        .text-success {
            color: var(--success-color) !important;
        }

        .text-danger {
            color: var(--danger-color) !important;
        }

        .text-warning {
            color: var(--warning-color) !important;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .text-info {
            color: var(--info-color) !important;
        }

        /* === BUTTONS FULL OVERRIDE === */
        .btn {
            border: none !important;
            border-radius: 10px !important;
            padding: 0.65rem 1.5rem !important;
            font-weight: 600 !important;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            box-shadow: var(--shadow-sm) !important;
            margin-left: 0.5rem !important;
            margin-right: 0.5rem !important;
            z-index: 0 !important;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: var(--white) !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: var(--white) !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            color: var(--white) !important;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: var(--white) !important;
        }

        .btn-info {
            background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
            color: var(--white) !important;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569) !important;
            color: var(--white) !important;
        }

        /* === MODAL FULL OVERRIDE === */
        .modal-content {
            border: none !important;
            border-radius: 16px !important;
            box-shadow: var(--shadow-xl) !important;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            border: none !important;
            padding: 1.25rem 1.5rem !important;
        }

        .modal-title {
            color: var(--white) !important;
            font-weight: 700 !important;
            font-size: 1.25rem !important;
        }

        .modal-header .close {
            color: var(--white) !important;
            opacity: 1 !important;
            text-shadow: none !important;
            transition: var(--transition);
            font-size: 1.5rem;
        }

        .modal-header .close:hover {
            transform: rotate(90deg) scale(1.2);
            opacity: 0.8 !important;
        }

        .modal-body {
            padding: 1.5rem !important;
            background: var(--white);
        }

        .modal-footer {
            background: var(--light-color);
            border-top: 1px solid var(--border-color) !important;
            padding: 1rem 1.5rem !important;
        }

        /* === FORMS FULL OVERRIDE === */
        .form-control {
            border: 2px solid var(--border-color) !important;
            border-radius: 10px !important;
            padding: 0.65rem 1rem !important;
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white) !important;
            color: var(--text-primary) !important;
        }

        .form-control:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
            outline: none !important;
        }

        .form-control::placeholder {
            color: var(--text-secondary) !important;
            opacity: 0.6;
        }

        select.form-control {
            cursor: pointer;
        }

        /* === ALERTS === */
        .alert {
            border: none !important;
            border-radius: 12px !important;
            padding: 1rem 1.25rem !important;
            box-shadow: var(--shadow-sm) !important;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%) !important;
            color: #065f46 !important;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%) !important;
            color: #991b1b !important;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%) !important;
            color: #92400e !important;
        }

        .alert-info {
            background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%) !important;
            color: #164e63 !important;
        }

        /* === PROGRESS BAR === */
        .progress {
            height: 8px !important;
            border-radius: 10px !important;
            background: var(--border-color) !important;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)) !important;
            transition: width 0.6s ease;
            border-radius: 10px;
        }

        .progress-bar-striped {
            background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent) !important;
            background-size: 1rem 1rem;
        }

        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }

        /* === BADGES === */
        .badge {
            padding: 0.4rem 0.8rem !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .badge-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: var(--white) !important;
        }

        .badge-success {
            background: var(--success-color) !important;
            color: var(--white) !important;
        }

        .badge-danger {
            background: var(--danger-color) !important;
            color: var(--white) !important;
        }

        /* === SCROLLBAR CUSTOM === */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-color);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
            border: 2px solid var(--light-color);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--primary-dark), var(--primary-color));
        }

        /* === ANIMATIONS === */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .card {
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .col-md-6:nth-child(1) .card,
        .col-12:nth-child(1) .card {
            animation-delay: 0.1s;
        }

        .col-md-6:nth-child(2) .card,
        .col-12:nth-child(2) .card {
            animation-delay: 0.2s;
        }

        .col-md-6:nth-child(3) .card,
        .col-12:nth-child(3) .card {
            animation-delay: 0.3s;
        }

        .col-md-6:nth-child(4) .card,
        .col-12:nth-child(4) .card {
            animation-delay: 0.4s;
        }

        .col-12:nth-child(5) .card {
            animation-delay: 0.5s;
        }

        .col-12:nth-child(6) .card {
            animation-delay: 0.6s;
        }

        /* === UTILITY CLASSES === */
        .shadow-sm {
            box-shadow: var(--shadow-sm) !important;
        }

        .shadow-md {
            box-shadow: var(--shadow-md) !important;
        }

        .shadow-lg {
            box-shadow: var(--shadow-lg) !important;
        }

        .shadow-xl {
            box-shadow: var(--shadow-xl) !important;
        }

        .rounded-lg {
            border-radius: 12px !important;
        }

        .rounded-xl {
            border-radius: 16px !important;
        }

        /* === ICONS === */
        .fa,
        .ft,
        [class^="ft-"],
        [class*=" ft-"] {
            transition: var(--transition);
        }

        .card-body i.fa-2x,
        .card-body i.opacity-75 {
            opacity: 0.9 !important;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        /* === LOADING STATE === */
        .block {
            position: relative;
            pointer-events: none;
        }

        .block::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
        }

        /* === RESPONSIVE === */
        @media (max-width: 992px) {
            .content-header-title {
                font-size: 1.5rem !important;
            }

            .vvnbar-horizontal #main-menu-vvnigation {
                flex-direction: column;
                align-items: stretch;
            }

            .vvnbar-horizontal .vvn-item {
                width: 100%;
            }

            .vvnbar-horizontal .vvn-link {
                width: 100%;
                justify-content: flex-start;
            }

            .card:hover {
                transform: translateY(-4px);
            }



            .content-wrapper {
                padding: 1rem !important;
            }
        }

        @media (max-width: 768px) {
            .header-vvnbar .brand-text {
                font-size: 1.2rem !important;
            }

            .content-header-title {
                font-size: 1.3rem !important;
            }

            .table thead th,
            .table tbody td,
            .table tfoot th {
                padding: 0.75rem !important;
                font-size: 0.85rem !important;
            }

            .card-body {
                padding: 1rem !important;
            }

            .btn {
                padding: 0.5rem 1rem !important;
                font-size: 0.85rem !important;
            }
        }

        /* === PRINT STYLES === */
        @media print {

            .header-vvnbar,
            .vvnbar-horizontal,
            .btn,
            .modal {
                display: none !important;
            }

            .card {
                box-shadow: none !important;
                break-inside: avoid;
            }

            body {
                background: white !important;
            }
        }

        /* === ADDITIONAL ENHANCEMENTS === */
        .fw-bold {
            font-weight: 700 !important;
        }

        .fw-semibold {
            font-weight: 600 !important;
        }

        .text-end {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-start {
            text-align: left !important;
        }

        .mb-1 {
            margin-bottom: 0.25rem !important;
        }

        .mb-2 {
            margin-bottom: 0.5rem !important;
        }

        .mb-3 {
            margin-bottom: 1rem !important;
        }

        .mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .mt-3 {
            margin-top: 1rem !important;
        }

        .me-2 {
            margin-right: 0.5rem !important;
        }

        .g-3 {
            gap: 1rem !important;
        }

        /* === HOVER EFFECTS FOR ICONS === */
        .vvn-link:hover i,
        .vvn-link:hover [class^="ft-"],
        .vvn-link:hover [class*=" ft-"] {
            transform: scale(1.1);
        }

        /* === FOCUS STATES === */
        button:focus,
        a:focus,
        .btn:focus,
        .form-control:focus {
            outline: none !important;
        }

        /* === SELECTION COLORS === */
        ::selection {
            background: var(--primary-color);
            color: var(--white);
        }

        ::-moz-selection {
            background: var(--primary-color);
            color: var(--white);
        }

        /* === CARD TITLE STYLING === */
        .card-body h5 {
            color: var(--text-primary) !important;
            font-weight: 700 !important;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-left: 12px;
            position: relative;
        }

        .card-body h5::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        .card-body h5 i {
            color: var(--primary-color) !important;
            margin-right: 0.5rem;
        }

        /* === NICE NUMBER FORMATTING === */
        .card-body h3 {
            font-weight: 800 !important;
            font-size: 1.8rem;
            line-height: 1.2;
        }

        .card-body h6 {
            font-weight: 600 !important;
            font-size: 0.9rem;
            opacity: 0.9;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        /* === ENSURE ALL TEXT IS READABLE === */
        .gradient-green *,
        .gradient-blue *,
        .gradient-red *,
        .gradient-orange * {
            color: var(--white) !important;
        }

        /* === FIX FOR ANY REMAINING CONTRAST ISSUES === */
        .vvnbar-horizontal .vvn-link,
        .vvnbar-horizontal .dropdown-item {
            position: relative;
        }

        /* === MOBILE MENU TOGGLE === */
        .vvn-menu-main {
            color: var(--white) !important;
        }

        .vvnbar-horizontal .vvn-menu-main {
            color: var(--text-primary) !important;
        }

        /* === FINAL TOUCHES === */
        .opacity-75 {
            opacity: 0.75 !important;
        }

        .d-flex {
            display: flex !important;
        }

        .align-items-center {
            align-items: center !important;
        }

        .justify-content-between {
            justify-content: space-between !important;
        }

        /* === ENSURE PROPER STACKING === */
        .vvnbar-horizontal {
            position: relative;
        }

        .header-vvnbar {
            position: relative;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript">
        var base_url = "<?= base_url(); ?>";
        var csrf_token = "<?= csrf_token(); ?>";
        var csrf_init = "<?= csrf_init(0); ?>";

        function modal_open(type, size, url) {
            $('#modal-size').removeClass('modal-xl').removeClass('modal-md').removeClass('modal-sm');
            $('#modal-size').addClass('modal-' + size);
            $('#modal').modal('show');
            $(document).on('focusin', function(e) {
                if ($(e.target).closest(".tox-tinymce-aux, .moxman-window, .tam-assetmanager-root").length) {
                    e.stopImmediatePropagation();
                }
            });
            if (type == 'add') {
                $('#modal-title').html('<i class="fas fa-plus-circle"></i> Tambah Data');
            } else if (type == 'edit') {
                $('#modal-title').html('<i class="fas fa-edit"></i> Ubah Data');
            } else if (type == 'detail') {
                $('#modal-title').html('<i class="fas fa-eye"></i> Detail Data');
            } else if (type == 'search') {
                $('#modal-title').html('<i class="fas fa-search"></i> Lihat Data');
            } else if (type == 'api_order') {
                $('#modal-title').html('<i class="fas fa-code"></i> API Pemesanan');
            } else if (type == 'api_status') {
                $('#modal-title').html('<i class="fas fa-code"></i> API Status');
            } else if (type == 'api_service') {
                $('#modal-title').html('<i class="fas fa-code"></i> API Layanan');
            } else {
                $('#modal-title').html('Empty');
            }
            var delay = 1000;
            $.ajax({
                type: "GET",
                url: url,
                dataType: "html",
                success: function($data) {
                    // setTimeout(function() {
                    $('#modal-body').html($data);
                    // }, delay);
                },
                error: function() {
                    $('#modal').modal('hide');
                    Swal.fire({
                        icon: "error",
                        title: "Ups!",
                        html: "Terjadi Kesalahan.",
                        customClass: {
                            confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
                        },
                        buttonsStyling: false,
                    }).then((result) => {
                        if (result.value) {
                            $('#modal').modal('hide');
                        }
                    });
                },
                beforeSend: function() {
                    $('#modal-body').html('<div class="text-center mb-2">Sedang memuat...</div>');
                }
            });
        }

        function get_data(url) {
            $('#modal-delete').modal('hide');
            $.ajax({
                type: "GET",
                url: url,
                dataType: "html",
                success: function($data) {
                    $('#body-result').html($data);
                },
                error: function() {
                    Swal.fire({
                        icon: "error",
                        title: "Ups!",
                        html: "Terjadi Kesalahan.",
                        customClass: {
                            confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
                        },
                        buttonsStyling: false,
                    }).then((result) => {
                        if (result.value) {
                            $('#modal').modal('hide');
                        }
                    });
                },
                beforeSend: function() {
                    $('#modal-body').html('<div class="progress rounded-corner m-b-15"><div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width: 100%">Loading...</div></div>');
                }
            });
        }

        function modal_delete(id, url) {
            $('#modal-delete').modal('show');
            $('#modal-delete-body').html('Yakin ingin menghapus data #' + id + '?');
            $('#btn-delete').attr('onclick', "get_data('" + url + "')");
        }

        function swal_delete(id, url) {
            Swal.fire({
                icon: 'question',
                title: 'Konfirmasi',
                html: 'Yakin ingin menghapus data <b>#' + id + '</b>?',
                showCancelButton: true,
                confirmButtonText: `Yakin`,
                cancelButtonText: `Batal`,
                confirmButtonColor: '#00cef9',
                cancelButtonColor: '#ff5858'
            }).then((result) => {
                if (result.value) {
                    get_data(url);
                }
            })
        }

        function btn_post(form, url) {
            $.ajax({
                type: 'POST',
                url: url,
                dataType: 'html',
                data: $(form).serialize(),
                success: function(data) {
                    $('#block').removeClass('block');
                    $('#modal-result').html(data);
                },
                error: function() {
                    $('#block').removeClass('block');
                    $('#modal-result').html('<div class="alert alert-danger alert-dismissable"><button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>Terjadi kesalahan!</div>');
                },
                beforeSend: function() {
                    $('#block').addClass('block');
                    $('#modal-result').html('<div class="progress mb-4"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">Loading...</div></div');
                }
            });
        }
    </script>

</head>

<body class="horizontal-layout horizontal-menu 2-columns  " data-open="hover" data-menu="horizontal-menu" data-color="bg-gradient-x-purple-blue" data-col="2-columns">

    <nav class="header-navbar navbar-expand-md navbar navbar-with-menu navbar-without-dd-arrow navbar-static-top navbar-light navbar-brand-center">
        <div class="navbar-header">
            <ul class="nav navbar-nav flex-row">
                <li class="nav-item mobile-menu d-md-none mr-auto"><a class="nav-link nav-menu-main menu-toggle hidden-xs" href="#"><i class="ft-menu font-large-1"></i></a></li>
                <li class="nav-item">
                    <a class="navbar-brand" href="<?= base_url(); ?>">
                        <h3 class="brand-text text-center"><?= base_title(); ?></h3>
                    </a>
                </li>
            </ul>
        </div>
        <div class="navbar-wrapper">
            <div class="navbar-container content">
                <div class="collapse navbar-collapse" id="navbar-mobile">
                    <ul class="nav navbar-nav mr-auto float-left">
                        <li class="nav-item d-none d-md-block"><a class="nav-link nav-menu-main menu-toggle hidden-xs" href="#"><i class="ft-menu"></i></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="header-navbar navbar-expand-sm navbar navbar-horizontal navbar-fixed navbar-dark navbar-without-dd-arrow navbar-shadow" role="navigation" data-menu="menu-wrapper">
        <div class="navbar-container main-menu-content" data-menu="menu-container">
            <ul class="nav navbar-nav" id="main-menu-navigation" data-menu="menu-navigation">
                <li class="nav-item <?= ($page_type == 'dashboard') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/'); ?>"><i class="ft-home"></i><span>Dashboard</span></a>
                </li>
                <li class="nav-item <?= ($page_type == 'users') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/user/'); ?>"><i class="ft-users"></i><span>Pengguna</span></a>
                </li>
                <li class="nav-item <?= ($page_type == 'server') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/server/'); ?>"><i class="ft-shuffle"></i><span>Orders</span></a>
                </li>
                <li class="dropdown nav-item <?= ($page_type == 'products' || $page_type == 'product_orders') ? 'active' : ''; ?>" data-menu="dropdown">
                    <a class="dropdown-toggle nav-link" href="#" data-toggle="dropdown">
                        <i class="ft-shuffle"></i><span>Produk</span>
                    </a>
                    <ul class="dropdown-menu">
                        <div class="arrow_box">
                            <li <?= ($page_type == 'products') ? 'class="active"' : ''; ?> data-menu="">
                                <a class="dropdown-item" href="<?= base_url('babikode/service/'); ?>" data-toggle="dropdown">
                                    Data Produk
                                </a>
                            </li>
                   
                        </div>
                    </ul>
                </li>

                <li class="dropdown nav-item <?= ($page_type == 'topups') ? 'active' : ''; ?>" data-menu="dropdown"><a class="dropdown-toggle nav-link" href="#" data-toggle="dropdown"><i class="ft-gitlab"></i><span>Top Up</span></a>
                    <ul class="dropdown-menu">
                        <div class="arrow_box">
                            <li <?= ($page_type == 'topups') ? 'class="active"' : ''; ?> data-menu=""><a class="dropdown-item" href="<?= base_url('babikode/topup/'); ?>" data-toggle="dropdown">Data Top Up</a>
                            </li>
                        </div>
                    </ul>
                </li>
                <li class="nav-item <?= ($page_type == 'refferals') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/refferal/'); ?>"><i class="ft-download-cloud"></i><span>Refferal</span></a>
                </li>
                <li class="nav-item <?= ($page_type == 'banner') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/banners/'); ?>"><i class="ft-image"></i><span>Banners</span></a>
                </li>
                <li class="nav-item <?= ($page_type == 'popup') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/popups/'); ?>"><i class="ft-more-vertical"></i><span>News Popups</span></a>
                </li>
                <li class="dropdown nav-item <?= ($page_type == 'withdraw_methods' || $page_type == 'withdraws' || $page_type == 'transfers') ? 'active' : ''; ?>" data-menu="dropdown"><a class="dropdown-toggle nav-link" href="#" data-toggle="dropdown"><i class="ft-repeat"></i><span>Withdraws</span></a>
                    <ul class="dropdown-menu">
                        <div class="arrow_box">
                            <li <?= ($page_type == 'withdraws') ? 'class="active"' : ''; ?> data-menu=""><a class="dropdown-item" href="<?= base_url('babikode/withdraw/'); ?>" data-toggle="dropdown">Data Withdraw</a>
                            </li>
                            <li <?= ($page_type == 'withdraw_methods') ? 'class="active"' : ''; ?> data-menu=""><a class="dropdown-item" href="<?= base_url('babikode/withdraw_method/'); ?>" data-toggle="dropdown">Metode Withdraw</a>
                            </li>
                        </div>
                    </ul>
                </li>
                <li class="dropdown nav-item <?= ($page_type == 'point_logs' || $page_type == 'login_logs') ? 'active' : ''; ?>" data-menu="dropdown"><a class="dropdown-toggle nav-link" href="#" data-toggle="dropdown"><i class="ft-refresh-ccw"></i><span>Logs</span></a>
                    <ul class="dropdown-menu">
                        <div class="arrow_box">
                            <li <?= ($page_type == 'point_logs') ? 'class="active"' : ''; ?> data-menu=""><a class="dropdown-item" href="<?= base_url('babikode/point_log/'); ?>" data-toggle="dropdown">Log Saldo</a>
                            </li>
                            <li <?= ($page_type == 'login_logs') ? 'class="active"' : ''; ?> data-menu=""><a class="dropdown-item" href="<?= base_url('babikode/login_log/'); ?>" data-toggle="dropdown">Log Masuk</a>
                            </li>
                        </div>
                    </ul>
                </li>
                <li class="nav-item <?= ($page_type == 'tele_ai') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/tele_ai/'); ?>"><i class="ft-message-square"></i><span>Bot Telegram</span></a>
                </li>
                <li class="nav-item <?= ($page_type == 'web_settings') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?= base_url('babikode/web/'); ?>"><i class="ft-settings"></i><span>Pengaturan</span></a>
                </li>
            </ul>
        </div>
    </div>

    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-wrapper-before"></div>
            <div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" id="modal-size">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="modal-title"></h4>
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                        </div>
                        <div class="modal-body" id="modal-body">
                        </div>
                    </div>
                </div>
            </div>