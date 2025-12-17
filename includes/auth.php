<?php
// /admin/includes/auth.php

// Include file database
require_once 'db.php';

// Hàm đăng nhập
function adminLogin($email, $password) {
    $conn = getDBConnection();
    
    // Sử dụng prepared statement để tránh SQL injection
    $stmt = $conn->prepare("SELECT id, email, mat_khau_bam, ho_ten, vai_tro FROM nguoi_dung WHERE email = ? AND vai_tro = 'QUAN_TRI'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Kiểm tra mật khẩu (mã hóa bcrypt)
        if (password_verify($password, $user['mat_khau_bam'])) {
            // Lưu thông tin session
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_name'] = $user['ho_ten'];
            $_SESSION['admin_role'] = $user['vai_tro'];
            
            // Cập nhật thời gian đăng nhập
            $update_stmt = $conn->prepare("UPDATE nguoi_dung SET lan_dang_nhap_gan_nhat = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            $stmt->close();
            closeDBConnection($conn);
            return true;
        }
    }
    
    $stmt->close();
    closeDBConnection($conn);
    return false;
}

// Hàm đăng xuất
function adminLogout() {
    session_unset();
    session_destroy();
}
?>