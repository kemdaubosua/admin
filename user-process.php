<?php
// /admin/user-process.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Thêm người dùng mới
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $ho_ten = $conn->real_escape_string($_POST['ho_ten'] ?? '');
        $so_dien_thoai = $conn->real_escape_string($_POST['so_dien_thoai'] ?? '');
        $vai_tro = $_POST['vai_tro'];
        $trang_thai = $_POST['trang_thai'];
        
        // Kiểm tra email đã tồn tại chưa
        $check = $conn->query("SELECT id FROM nguoi_dung WHERE email = '$email'");
        if ($check->num_rows > 0) {
            $_SESSION['error'] = 'Email đã tồn tại';
        } else {
            $stmt = $conn->prepare("INSERT INTO nguoi_dung (email, mat_khau_bam, ho_ten, so_dien_thoai, vai_tro, trang_thai) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $email, $password, $ho_ten, $so_dien_thoai, $vai_tro, $trang_thai);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Thêm người dùng thành công';
            } else {
                $_SESSION['error'] = 'Lỗi khi thêm người dùng';
            }
            $stmt->close();
        }
        
    } elseif ($action === 'update') {
        // Cập nhật người dùng
        $id = intval($_POST['id']);
        $email = $conn->real_escape_string($_POST['email']);
        $ho_ten = $conn->real_escape_string($_POST['ho_ten'] ?? '');
        $so_dien_thoai = $conn->real_escape_string($_POST['so_dien_thoai'] ?? '');
        $vai_tro = $_POST['vai_tro'];
        $trang_thai = $_POST['trang_thai'];
        
        $stmt = $conn->prepare("UPDATE nguoi_dung SET email = ?, ho_ten = ?, so_dien_thoai = ?, vai_tro = ?, trang_thai = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $email, $ho_ten, $so_dien_thoai, $vai_tro, $trang_thai, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Cập nhật người dùng thành công';
        } else {
            $_SESSION['error'] = 'Lỗi khi cập nhật người dùng';
        }
        $stmt->close();
    }
}

closeDBConnection($conn);
header('Location: users.php');
exit();
?>