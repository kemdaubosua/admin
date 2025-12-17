<?php
// /admin/sku-process.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$conn = getDBConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'delete' && isset($_GET['id'])) {
    // Xóa SKU
    $sku_id = intval($_GET['id']);
    
    $conn->query("DELETE FROM sku_san_pham WHERE id = $sku_id");
    
    $_SESSION['success'] = 'Đã xóa SKU';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add') {
        // Thêm SKU mới
        $san_pham_id = intval($_POST['san_pham_id']);
        $kich_co_id = intval($_POST['kich_co_id']);
        $mau_sac_id = intval($_POST['mau_sac_id']);
        $gia_ban = floatval($_POST['gia_ban']);
        $so_luong_ton = intval($_POST['so_luong_ton']);
        $trang_thai = $_POST['trang_thai'];
        
        // Kiểm tra SKU đã tồn tại chưa
        $check = $conn->query("SELECT id FROM sku_san_pham WHERE san_pham_id = $san_pham_id AND kich_co_id = $kich_co_id AND mau_sac_id = $mau_sac_id");
        
        if ($check->num_rows > 0) {
            $_SESSION['error'] = 'SKU với size và màu này đã tồn tại';
        } else {
            // Tạo mã SKU
            $product = $conn->query("SELECT duong_dan FROM san_pham WHERE id = $san_pham_id")->fetch_assoc();
            $size = $conn->query("SELECT duong_dan FROM kich_co WHERE id = $kich_co_id")->fetch_assoc();
            $color = $conn->query("SELECT duong_dan FROM mau_sac WHERE id = $mau_sac_id")->fetch_assoc();
            
            $ma_sku = strtoupper(substr($product['duong_dan'], 0, 4)) . '-' . 
                     strtoupper($size['duong_dan']) . '-' . 
                     strtoupper($color['duong_dan']);
            
            $stmt = $conn->prepare("INSERT INTO sku_san_pham (san_pham_id, ma_sku, kich_co_id, mau_sac_id, gia_ban, so_luong_ton, trang_thai) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isiiids", $san_pham_id, $ma_sku, $kich_co_id, $mau_sac_id, $gia_ban, $so_luong_ton, $trang_thai);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Thêm SKU thành công';
            } else {
                $_SESSION['error'] = 'Lỗi khi thêm SKU';
            }
            $stmt->close();
        }
        
    } elseif ($_POST['action'] === 'update') {
        // Cập nhật SKU
        $sku_id = intval($_POST['id']);
        $gia_ban = floatval($_POST['gia_ban']);
        $so_luong_ton = intval($_POST['so_luong_ton']);
        $trang_thai = $_POST['trang_thai'];
        
        $stmt = $conn->prepare("UPDATE sku_san_pham SET gia_ban = ?, so_luong_ton = ?, trang_thai = ? WHERE id = ?");
        $stmt->bind_param("disi", $gia_ban, $so_luong_ton, $trang_thai, $sku_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Cập nhật SKU thành công';
        } else {
            $_SESSION['error'] = 'Lỗi khi cập nhật SKU';
        }
        $stmt->close();
    }
}

closeDBConnection($conn);
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?>