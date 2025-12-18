<?php
// includes/functions.php - Các hàm tiện ích

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . 'đ';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function getStatusBadge($status, $type = 'product') {
    $badges = [
        'product' => [
            'DANG_BAN' => '<span class="badge bg-success-subtle text-success border border-success-subtle">Đang bán</span>',
            'NGUNG_BAN' => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Ngừng bán</span>',
            'DA_GO' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Đã gỡ</span>'
        ],
        'order' => [
            'CHO_XU_LY' => '<span class="badge bg-info-subtle text-info border border-info-subtle">Chờ xử lý</span>',
            'DANG_XU_LY' => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Đang xử lý</span>',
            'HOAN_TAT' => '<span class="badge bg-success-subtle text-success border border-success-subtle">Hoàn tất</span>',
            'HUY' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Đã hủy</span>'
        ],
        'user' => [
            'HOAT_DONG' => '<span class="badge bg-success-subtle text-success border border-success-subtle">Hoạt động</span>',
            'KHOA' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Khóa</span>',
            'NGUNG_HOAT_DONG' => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Ngừng</span>'
        ],
        'category' => [
            'HOAT_DONG' => '<span class="badge bg-success-subtle text-success border border-success-subtle">Hoạt động</span>',
            'NGUNG_HOAT_DONG' => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Ngừng</span>'
        ]
    ];
    return $badges[$type][$status] ?? '<span class="badge bg-secondary">N/A</span>';
}

function createSlug($str) {
    $str = trim(mb_strtolower($str));
    $str = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/', 'a', $str);
    $str = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', 'e', $str);
    $str = preg_replace('/(ì|í|ị|ỉ|ĩ)/', 'i', $str);
    $str = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/', 'o', $str);
    $str = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/', 'u', $str);
    $str = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/', 'y', $str);
    $str = preg_replace('/(đ)/', 'd', $str);
    $str = preg_replace('/[^a-z0-9-\s]/', '', $str);
    $str = preg_replace('/([\s]+)/', '-', $str);
    return $str;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function uploadImage($file, $folder = 'uploads/') {
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'Định dạng file không hợp lệ'];
    }
    
    $newname = uniqid() . '.' . $ext;
    $destination = $folder . $newname;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'path' => $destination];
    }
    
    return ['success' => false, 'message' => 'Lỗi upload file'];
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function showMessage($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $bgClass = $type === 'success' ? 'success' : 'danger';
        echo "<div class='alert alert-{$bgClass} alert-dismissible fade show' role='alert'>
                {$_SESSION['message']}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['message'], $_SESSION['message_type']);
    }
}