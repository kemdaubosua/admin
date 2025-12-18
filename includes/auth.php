<?php
// includes/auth.php - Kiểm tra đăng nhập
session_start();

function checkAdminAuth() {
    if (!isset($_SESSION['admin_id']) || $_SESSION['vai_tro'] !== 'QUAN_TRI') {
        header('Location: login.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && $_SESSION['vai_tro'] === 'QUAN_TRI';
}

function getAdminInfo() {
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'email' => $_SESSION['admin_email'] ?? null,
        'ho_ten' => $_SESSION['admin_ho_ten'] ?? 'Admin'
    ];
}