<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Chi tiết Đơn hàng';
$active_page = 'orders';

$order_id = $_GET['id'] ?? 0;

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("SELECT dh.*, nd.ho_ten, nd.email, nd.so_dien_thoai 
                       FROM don_hang dh 
                       LEFT JOIN nguoi_dung nd ON dh.nguoi_dung_id = nd.id 
                       WHERE dh.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php');
}

// Lấy chi tiết sản phẩm
$stmt = $pdo->prepare("SELECT * FROM chi_tiet_don_hang WHERE don_hang_id = ?");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Lấy lịch sử trạng thái
$stmt = $pdo->prepare("SELECT ls.*, nd.ho_ten as nguoi_thay_doi 
                       FROM lich_su_trang_thai_don_hang ls 
                       LEFT JOIN nguoi_dung nd ON ls.nguoi_thay_doi_id = nd.id 
                       WHERE ls.don_hang_id = ? 
                       ORDER BY ls.tao_luc DESC");
$stmt->execute([$order_id]);
$history = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php include 'includes/sidebar.php'; ?>
    </div>
</div>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content min-vh-100 p-4 p-lg-5">
    
    <div class="d-lg-none d-flex align-items-center justify-content-between mb-4">
        <button class="btn btn-white border shadow-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="fw-bold fs-5">AdminCenter</span>
        <img src="https://ui-avatars.com/api/?name=Admin+User" class="rounded-circle border" width="36" height="36" alt="Admin">
    </div>

    <?php displayMessage(); ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <a href="orders.php" class="btn btn-sm btn-light border rounded-3 mb-2">
                <i class="fas fa-arrow-left me-1"></i> Quay lại
            </a>
            <h2 class="fw-bold text-dark mb-1">Chi tiết Đơn hàng #<?php echo $order['ma_don_hang']; ?></h2>
            <p class="text-secondary small mb-0">Đặt lúc: <?php echo formatDateTime($order['tao_luc']); ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php echo getStatusBadge($order['trang_thai'], 'order'); ?>
            <button class="btn btn-dark-custom" onclick="window.print()">
                <i class="fas fa-print me-1"></i> In đơn
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            
            <!-- Sản phẩm -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Sản phẩm đã đặt</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($order_items)): ?>
                        <p class="text-secondary text-center">Không có sản phẩm</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>Đơn giá</th>
                                        <th>SL</th>
                                        <th class="text-end">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo $item['ten_san_pham']; ?></div>
                                                <div class="text-secondary small">ID: #<?php echo $item['san_pham_id']; ?></div>
                                            </td>
                                            <td><?php echo formatPrice($item['don_gia']); ?></td>
                                            <td><span class="badge bg-light text-dark border">x<?php echo $item['so_luong']; ?></span></td>
                                            <td class="text-end fw-bold"><?php echo formatPrice($item['thanh_tien']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tổng tiền -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Tạm tính:</span>
                        <span class="fw-bold"><?php echo formatPrice($order['tam_tinh']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Phí vận chuyển:</span>
                        <span class="fw-bold"><?php echo formatPrice($order['phi_van_chuyen']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-secondary">Giảm giá:</span>
                        <span class="fw-bold text-danger">-<?php echo formatPrice($order['giam_gia']); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold fs-5">Tổng cộng:</span>
                        <span class="fw-bold fs-4 text-primary"><?php echo formatPrice($order['tong_tien']); ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            
            <!-- Thông tin khách hàng -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="text-secondary small mb-1">Họ tên:</div>
                        <div class="fw-bold"><?php echo $order['ho_ten'] ?? 'N/A'; ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-secondary small mb-1">Email:</div>
                        <div><?php echo $order['email'] ?? 'N/A'; ?></div>
                    </div>
                    <div>
                        <div class="text-secondary small mb-1">Số điện thoại:</div>
                        <div class="fw-bold"><?php echo $order['so_dien_thoai'] ?? 'N/A'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Thông tin giao hàng -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Thông tin giao hàng</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="text-secondary small mb-1">Người nhận:</div>
                        <div class="fw-bold"><?php echo $order['nguoi_nhan']; ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-secondary small mb-1">SĐT người nhận:</div>
                        <div class="fw-bold"><?php echo $order['sdt_nguoi_nhan']; ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-secondary small mb-1">Địa chỉ:</div>
                        <div><?php echo $order['dia_chi_giao_hang']; ?></div>
                    </div>
                    <?php if ($order['ghi_chu']): ?>
                    <div>
                        <div class="text-secondary small mb-1">Ghi chú:</div>
                        <div class="fst-italic"><?php echo $order['ghi_chu']; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thanh toán -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Thanh toán</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="text-secondary small mb-1">Phương thức:</div>
                        <div class="fw-bold"><?php echo $order['phuong_thuc_thanh_toan']; ?></div>
                    </div>
                    <div>
                        <div class="text-secondary small mb-1">Trạng thái:</div>
                        <?php if ($order['trang_thai_thanh_toan'] === 'DA_THANH_TOAN'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Đã thanh toán</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Chưa thanh toán</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Lịch sử trạng thái -->
            <?php if (!empty($history)): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Lịch sử trạng thái</h5>
                </div>
                <div class="card-body p-4">
                    <div class="timeline">
                        <?php foreach ($history as $h): ?>
                            <div class="timeline-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-bold small"><?php echo formatDateTime($h['tao_luc']); ?></div>
                                </div>
                                <div class="text-secondary small mb-1">
                                    <?php if ($h['tu_trang_thai']): ?>
                                        Từ: <span class="fw-bold"><?php echo $h['tu_trang_thai']; ?></span>
                                    <?php endif; ?>
                                    → Đến: <span class="fw-bold text-primary"><?php echo $h['den_trang_thai']; ?></span>
                                </div>
                                <?php if ($h['nguoi_thay_doi']): ?>
                                    <div class="text-secondary small">Bởi: <?php echo $h['nguoi_thay_doi']; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</main>

<style>
@media print {
    .main-content { margin-left: 0 !important; }
    .btn, .offcanvas, aside { display: none !important; }
}
</style>

<?php include 'includes/footer.php'; ?>