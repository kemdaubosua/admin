<?php
// /admin/order-detail.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Chi tiết đơn hàng';
$conn = getDBConnection();

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id === 0) {
    header('Location: orders.php');
    exit();
}

// Lấy thông tin đơn hàng
$order = $conn->query("
    SELECT d.*, n.ho_ten, n.email, n.so_dien_thoai as sdt_khach
    FROM don_hang d 
    LEFT JOIN nguoi_dung n ON d.nguoi_dung_id = n.id
    WHERE d.id = $order_id
")->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = 'Đơn hàng không tồn tại';
    header('Location: orders.php');
    exit();
}

// Lấy chi tiết đơn hàng
$order_items = $conn->query("
    SELECT ct.*, sp.ten_san_pham, sp.anh_dai_dien_url
    FROM chi_tiet_don_hang ct
    LEFT JOIN san_pham sp ON ct.san_pham_id = sp.id
    WHERE ct.don_hang_id = $order_id
")->fetch_all(MYSQLI_ASSOC);

// Lấy lịch sử trạng thái
$status_history = $conn->query("
    SELECT ls.*, n.ho_ten 
    FROM lich_su_trang_thai_don_hang ls
    LEFT JOIN nguoi_dung n ON ls.nguoi_thay_doi_id = n.id
    WHERE ls.don_hang_id = $order_id
    ORDER BY ls.tao_luc DESC
")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);

$pageTitle = 'Đơn hàng #' . $order['ma_don_hang'];
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Thông tin đơn hàng</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Thay đổi trạng thái
                    </button>
                    <ul class="dropdown-menu">
                        <?php if ($order['trang_thai'] != 'CHO_XU_LY'): ?>
                        <li>
                            <a class="dropdown-item" href="orders.php?change_status&id=<?php echo $order_id; ?>&status=CHO_XU_LY&old_status=<?php echo $order['trang_thai']; ?>">
                                Chuyển về Chờ xử lý
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($order['trang_thai'] != 'DANG_XU_LY'): ?>
                        <li>
                            <a class="dropdown-item" href="orders.php?change_status&id=<?php echo $order_id; ?>&status=DANG_XU_LY&old_status=<?php echo $order['trang_thai']; ?>">
                                Đang xử lý
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($order['trang_thai'] != 'HOAN_TAT'): ?>
                        <li>
                            <a class="dropdown-item" href="orders.php?change_status&id=<?php echo $order_id; ?>&status=HOAN_TAT&old_status=<?php echo $order['trang_thai']; ?>">
                                Hoàn tất
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($order['trang_thai'] != 'HUY'): ?>
                        <li>
                            <a class="dropdown-item text-danger" href="orders.php?change_status&id=<?php echo $order_id; ?>&status=HUY&old_status=<?php echo $order['trang_thai']; ?>" onclick="return confirm('Hủy đơn hàng này?')">
                                Hủy đơn
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Thông tin khách hàng</h6>
                        <table class="table table-sm">
                            <tr>
                                <td width="120">Họ tên:</td>
                                <td><strong><?php echo htmlspecialchars($order['ho_ten'] ?: 'Khách vãng lai'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>Email:</td>
                                <td><?php echo htmlspecialchars($order['email'] ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>SĐT:</td>
                                <td><?php echo htmlspecialchars($order['sdt_khach'] ?: 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Thông tin nhận hàng</h6>
                        <table class="table table-sm">
                            <tr>
                                <td width="120">Người nhận:</td>
                                <td><strong><?php echo htmlspecialchars($order['nguoi_nhan']); ?></strong></td>
                            </tr>
                            <tr>
                                <td>SĐT:</td>
                                <td><?php echo htmlspecialchars($order['sdt_nguoi_nhan']); ?></td>
                            </tr>
                            <tr>
                                <td>Địa chỉ:</td>
                                <td><?php echo htmlspecialchars($order['dia_chi_giao_hang']); ?></td>
                            </tr>
                            <tr>
                                <td>Ghi chú:</td>
                                <td><?php echo nl2br(htmlspecialchars($order['ghi_chu'] ?? '')); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h6>Chi tiết sản phẩm</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Sản phẩm</th>
                                <th>Đơn giá</th>
                                <th>Số lượng</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['anh_dai_dien_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['anh_dai_dien_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['ten_san_pham']); ?>"
                                             class="product-img me-3">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['ten_san_pham']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['ten_san_pham']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatCurrency($item['don_gia']); ?></td>
                                <td><?php echo $item['so_luong']; ?></td>
                                <td><strong><?php echo formatCurrency($item['thanh_tien']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6 offset-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td>Tạm tính:</td>
                                <td class="text-end"><?php echo formatCurrency($order['tam_tinh']); ?></td>
                            </tr>
                            <tr>
                                <td>Phí vận chuyển:</td>
                                <td class="text-end"><?php echo formatCurrency($order['phi_van_chuyen']); ?></td>
                            </tr>
                            <tr>
                                <td>Giảm giá:</td>
                                <td class="text-end text-danger">-<?php echo formatCurrency($order['giam_gia']); ?></td>
                            </tr>
                            <tr>
                                <th>Tổng tiền:</th>
                                <th class="text-end text-primary"><?php echo formatCurrency($order['tong_tien']); ?></th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lịch sử trạng thái -->
        <?php if (count($status_history) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Lịch sử trạng thái</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($status_history as $history): ?>
                    <div class="timeline-item mb-3">
                        <div class="d-flex">
                            <div class="timeline-icon me-3">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <?php echo displayOrderStatus($history['den_trang_thai']); ?>
                                    <?php if ($history['tu_trang_thai']): ?>
                                    <small class="text-muted">(từ <?php echo displayOrderStatus($history['tu_trang_thai']); ?>)</small>
                                    <?php endif; ?>
                                </h6>
                                <p class="mb-1">
                                    <small class="text-muted">
                                        <?php echo formatDate($history['tao_luc'], 'd/m/Y H:i:s'); ?>
                                        <?php if ($history['ho_ten']): ?>
                                        • Thay đổi bởi: <?php echo htmlspecialchars($history['ho_ten']); ?>
                                        <?php endif; ?>
                                    </small>
                                </p>
                                <?php if ($history['ghi_chu']): ?>
                                <p class="mb-0"><small><?php echo htmlspecialchars($history['ghi_chu']); ?></small></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Tóm tắt đơn hàng</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td>Mã đơn:</td>
                        <td><strong><?php echo $order['ma_don_hang']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Trạng thái:</td>
                        <td><?php echo displayOrderStatus($order['trang_thai']); ?></td>
                    </tr>
                    <tr>
                        <td>Thanh toán:</td>
                        <td>
                            <?php if ($order['trang_thai_thanh_toan'] == 'DA_THANH_TOAN'): ?>
                            <span class="badge bg-success">Đã thanh toán</span>
                            <?php else: ?>
                            <span class="badge bg-warning">Chưa thanh toán</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Phương thức:</td>
                        <td><?php echo $order['phuong_thuc_thanh_toan'] == 'COD' ? 'COD (Thanh toán khi nhận hàng)' : $order['phuong_thuc_thanh_toan']; ?></td>
                    </tr>
                    <tr>
                        <td>Ngày đặt:</td>
                        <td><?php echo formatDate($order['tao_luc']); ?></td>
                    </tr>
                    <tr>
                        <td>Cập nhật:</td>
                        <td><?php echo formatDate($order['cap_nhat_luc']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Thao tác</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                    
                    <?php if ($order['trang_thai'] == 'CHO_XU_LY'): ?>
                    <a href="orders.php?change_status&id=<?php echo $order_id; ?>&status=DANG_XU_LY&old_status=<?php echo $order['trang_thai']; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-play"></i> Bắt đầu xử lý
                    </a>
                    <?php elseif ($order['trang_thai'] == 'DANG_XU_LY'): ?>
                    <a href="orders.php?change_status&id=<?php echo $order_id; ?>&status=HOAN_TAT&old_status=<?php echo $order['trang_thai']; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-check"></i> Hoàn tất
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($order['trang_thai'] != 'HUY'): ?>
                    <a href="orders.php?change_status&id=<?php echo $order_id; ?>&status=HUY&old_status=<?php echo $order['trang_thai']; ?>" 
                       class="btn btn-danger" onclick="return confirm('Hủy đơn hàng này?')">
                        <i class="fas fa-times"></i> Hủy đơn
                    </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> In đơn hàng
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>