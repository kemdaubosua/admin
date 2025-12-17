<?php
// /admin/product-process.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$conn = getDBConnection();

// Xóa ảnh sản phẩm
if (isset($_GET['delete_image'])) {
    $image_id = intval($_GET['delete_image']);
    $product_id = intval($_GET['product_id']);
    
    $conn->query("DELETE FROM anh_san_pham WHERE id = $image_id");
    
    $_SESSION['success'] = 'Đã xóa ảnh';
    header("Location: product-detail.php?id=$product_id");
    exit();
}

// Xóa sản phẩm
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    
    // Kiểm tra xem sản phẩm có trong đơn hàng không
    $check = $conn->query("SELECT COUNT(*) as count FROM chi_tiet_don_hang WHERE san_pham_id = $product_id");
    $result = $check->fetch_assoc();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = 'Không thể xóa sản phẩm đã có trong đơn hàng';
    } else {
        // Xóa các SKU trước
        $conn->query("DELETE FROM sku_san_pham WHERE san_pham_id = $product_id");
        
        // Xóa các ảnh
        $conn->query("DELETE FROM anh_san_pham WHERE san_pham_id = $product_id");
        
        // Xóa sản phẩm
        $conn->query("DELETE FROM san_pham WHERE id = $product_id");
        
        $_SESSION['success'] = 'Đã xóa sản phẩm';
    }
    
    header('Location: products.php');
    exit();
}

closeDBConnection($conn);
?>