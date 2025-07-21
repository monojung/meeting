<?php
// เรียกใช้งาน Routing
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$page = $_GET['page'] ?? 'auth/login';
$pagePath = "../views/{$page}.php";

if (file_exists($pagePath)) {
    include '../includes/header.php';
    include $pagePath;
    include '../includes/footer.php';
} else {
    include '../views/error.php';
}
