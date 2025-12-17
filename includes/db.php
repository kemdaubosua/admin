<?php
// /admin/includes/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cấu hình kết nối database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Mặc định XAMPP để trống
define('DB_NAME', 'PTUD_Final');

// Hàm kết nối database
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Kiểm tra kết nối
        if ($conn->connect_error) {
            throw new Exception("Kết nối thất bại: " . $conn->connect_error);
        }
        
        // Đặt charset UTF-8
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        die("Lỗi kết nối database: " . $e->getMessage());
    }
}

// Hàm đóng kết nối
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Kiểm tra xem người dùng đã đăng nhập chưa
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_email']);
}

// Kiểm tra quyền admin
function isAdmin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'QUAN_TRI';
}

// Chuyển hướng nếu chưa đăng nhập
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Chuyển hướng nếu không phải admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// Xử lý lỗi SQL
function handleSQLError($conn) {
    if ($conn->error) {
        $_SESSION['error'] = "Lỗi database: " . $conn->error;
        return false;
    }
    return true;
}
?>