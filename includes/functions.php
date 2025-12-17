<?php
// /admin/includes/functions.php

// Include file database
require_once 'db.php';

// Hàm lấy thống kê tổng quan
function getDashboardStats() {
    $conn = getDBConnection();
    $stats = [];
    
    // Tổng số người dùng
    $result = $conn->query("SELECT COUNT(*) as total FROM nguoi_dung");
    $stats['total_users'] = $result->fetch_assoc()['total'];
    
    // Tổng số sản phẩm
    $result = $conn->query("SELECT COUNT(*) as total FROM san_pham WHERE trang_thai != 'DA_GO'");
    $stats['total_products'] = $result->fetch_assoc()['total'];
    
    // Tổng số đơn hàng
    $result = $conn->query("SELECT COUNT(*) as total FROM don_hang");
    $stats['total_orders'] = $result->fetch_assoc()['total'];
    
    // Doanh thu tháng này
    $current_month = date('Y-m');
    $result = $conn->query("SELECT SUM(tong_tien) as revenue FROM don_hang WHERE DATE_FORMAT(tao_luc, '%Y-%m') = '$current_month' AND trang_thai = 'HOAN_TAT'");
    $stats['monthly_revenue'] = $result->fetch_assoc()['revenue'] ?: 0;
    
    // Đơn hàng mới hôm nay
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as new_orders FROM don_hang WHERE DATE(tao_luc) = '$today'");
    $stats['today_orders'] = $result->fetch_assoc()['new_orders'];
    
    closeDBConnection($conn);
    return $stats;
}

// Hàm format tiền tệ
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// Hàm format ngày tháng
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '';
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

// Hàm hiển thị trạng thái đơn hàng
function displayOrderStatus($status) {
    $badge_classes = [
        'CHO_XU_LY' => 'bg-warning',
        'DANG_XU_LY' => 'bg-info',
        'HOAN_TAT' => 'bg-success',
        'HUY' => 'bg-danger'
    ];
    
    $status_text = [
        'CHO_XU_LY' => 'Chờ xử lý',
        'DANG_XU_LY' => 'Đang xử lý',
        'HOAN_TAT' => 'Hoàn tất',
        'HUY' => 'Đã hủy'
    ];
    
    $class = $badge_classes[$status] ?? 'bg-secondary';
    $text = $status_text[$status] ?? $status;
    
    return "<span class='badge $class'>$text</span>";
}

// Hàm hiển thị trạng thái sản phẩm
function displayProductStatus($status) {
    $badge_classes = [
        'DANG_BAN' => 'bg-success',
        'NGUNG_BAN' => 'bg-warning',
        'DA_GO' => 'bg-danger'
    ];
    
    $status_text = [
        'DANG_BAN' => 'Đang bán',
        'NGUNG_BAN' => 'Ngừng bán',
        'DA_GO' => 'Đã gỡ'
    ];
    
    $class = $badge_classes[$status] ?? 'bg-secondary';
    $text = $status_text[$status] ?? $status;
    
    return "<span class='badge $class'>$text</span>";
}

// Hàm upload ảnh
function uploadImage($file, $product_id) {
    $upload_dir = __DIR__ . '/../uploads/products/';
    
    // Tạo thư mục nếu chưa tồn tại
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Kiểm tra file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Lỗi upload file'];
    }
    
    // Kiểm tra định dạng file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Chỉ chấp nhận file ảnh (JPEG, PNG, GIF, WebP)'];
    }
    
    // Tạo tên file mới
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . $product_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Di chuyển file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Trả về URL tương đối
        return ['success' => true, 'url' => '/admin/uploads/products/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Không thể lưu file'];
}

// Hàm phân trang
function paginate($total_items, $items_per_page, $current_page, $url) {
    $total_pages = ceil($total_items / $items_per_page);
    
    $pagination = '';
    if ($total_pages > 1) {
        $pagination .= '<nav aria-label="Page navigation"><ul class="pagination">';
        
        // Nút Previous
        if ($current_page > 1) {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page - 1) . '">&laquo;</a></li>';
        }
        
        // Các trang
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = $i == $current_page ? ' active' : '';
            $pagination .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
        
        // Nút Next
        if ($current_page < $total_pages) {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page + 1) . '">&raquo;</a></li>';
        }
        
        $pagination .= '</ul></nav>';
    }
    
    return $pagination;
}

// Hàm lấy dữ liệu với phân trang
function getPaginatedData($conn, $query, $params = [], $page = 1, $per_page = 10) {
    // Đếm tổng số bản ghi
    $count_query = preg_replace('/SELECT.*FROM/i', 'SELECT COUNT(*) as total FROM', $query, 1);
    $count_query = preg_replace('/ORDER BY.*/i', '', $count_query);
    
    $stmt = $conn->prepare($count_query);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Tính toán phân trang
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $params[] = $per_page;
    $params[] = $offset;
    
    if ($params) {
        $types = str_repeat('s', count($params) - 2) . 'ii';
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return [
        'data' => $data,
        'total' => $total,
        'total_pages' => ceil($total / $per_page)
    ];
}
?>