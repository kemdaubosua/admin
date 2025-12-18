<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Dashboard';
$active_page = 'dashboard';

// Thống kê tổng quan
$stats = [];

// Tổng người dùng
$stmt = $pdo->query("SELECT COUNT(*) as total FROM nguoi_dung WHERE vai_tro = 'NGUOI_DUNG'");
$stats['users'] = $stmt->fetch()['total'];

// Tổng sản phẩm
$stmt = $pdo->query("SELECT COUNT(*) as total FROM san_pham WHERE trang_thai = 'DANG_BAN'");
$stats['products'] = $stmt->fetch()['total'];

// Tổng đơn hàng
$stmt = $pdo->query("SELECT COUNT(*) as total FROM don_hang");
$stats['orders'] = $stmt->fetch()['total'];

// Doanh thu tháng này
$stmt = $pdo->query("SELECT COALESCE(SUM(tong_tien), 0) as total FROM don_hang WHERE MONTH(tao_luc) = MONTH(CURRENT_DATE()) AND YEAR(tao_luc) = YEAR(CURRENT_DATE()) AND trang_thai != 'HUY'");
$stats['revenue'] = $stmt->fetch()['total'];

// Đơn hàng gần đây
$stmt = $pdo->prepare("SELECT d.*, n.ho_ten, n.email 
                       FROM don_hang d 
                       LEFT JOIN nguoi_dung n ON d.nguoi_dung_id = n.id 
                       ORDER BY d.tao_luc DESC LIMIT 5");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Sản phẩm bán chạy (giả lập - cần join với chi_tiet_don_hang)
$stmt = $pdo->prepare("SELECT sp.*, dm.ten_danh_muc 
                       FROM san_pham sp 
                       LEFT JOIN danh_muc_san_pham dm ON sp.danh_muc_id = dm.id 
                       WHERE sp.trang_thai = 'DANG_BAN' 
                       ORDER BY sp.so_luong_ton DESC LIMIT 5");
$stmt->execute();
$top_products = $stmt->fetchAll();

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
    
    <!-- Mobile Header -->
    <div class="d-lg-none d-flex align-items-center justify-content-between mb-4">
        <button class="btn btn-white border shadow-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="fw-bold fs-5">AdminCenter</span>
        <img src="https://ui-avatars.com/api/?name=Admin+User" class="rounded-circle border" width="36" height="36" alt="Admin">
    </div>

    <!-- Page Header -->
    <div class="mb-4">
        <h2 class="fw-bold text-dark mb-1">Dashboard</h2>
        <p class="text-secondary small mb-0">Tổng quan hoạt động hệ thống</p>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card card-custom bg-gradient-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">NGƯỜI DÙNG</div>
                            <h3 class="fw-bold mb-0"><?php echo number_format($stats['users']); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        <i class="fas fa-arrow-up me-1"></i> Tổng tài khoản
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card card-custom bg-gradient-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">SẢN PHẨM</div>
                            <h3 class="fw-bold mb-0"><?php echo number_format($stats['products']); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-box fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        <i class="fas fa-check me-1"></i> Đang bán
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card card-custom bg-gradient-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">ĐƠN HÀNG</div>
                            <h3 class="fw-bold mb-0"><?php echo number_format($stats['orders']); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        <i class="fas fa-chart-line me-1"></i> Tổng đơn
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card card-custom bg-gradient-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">DOANH THU</div>
                            <h3 class="fw-bold mb-0"><?php echo formatPrice($stats['revenue']); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-money-bill-wave fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        <i class="fas fa-calendar me-1"></i> Tháng này
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders & Top Products -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Đơn hàng gần đây</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-4">Chưa có đơn hàng</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge bg-light text-primary font-monospace"><?php echo $order['ma_don_hang']; ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo $order['ho_ten'] ?? 'Khách hàng'; ?></div>
                                                <div class="text-secondary small"><?php echo $order['email']; ?></div>
                                            </td>
                                            <td class="fw-bold"><?php echo formatPrice($order['tong_tien']); ?></td>
                                            <td><?php echo getStatusBadge($order['trang_thai'], 'order'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 py-3 px-4">
                    <a href="orders.php" class="btn btn-sm btn-outline-primary rounded-3 fw-bold">
                        Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Sản phẩm nổi bật</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_products)): ?>
                        <p class="text-center text-secondary">Chưa có sản phẩm</p>
                    <?php else: ?>
                        <?php foreach ($top_products as $product): ?>
                            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                                <img src="<?php echo $product['anh_dai_dien_url'] ?? 'https://placehold.co/80x80'; ?>" 
                                     class="rounded-3" width="60" height="60" alt="<?php echo $product['ten_san_pham']; ?>">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark"><?php echo $product['ten_san_pham']; ?></div>
                                    <div class="text-secondary small"><?php echo $product['ten_danh_muc'] ?? 'Chưa phân loại'; ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary"><?php echo formatPrice($product['gia_ban']); ?></div>
                                    <div class="text-secondary small">Tồn: <?php echo $product['so_luong_ton']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-0 py-3 px-4">
                    <a href="products.php" class="btn btn-sm btn-outline-success rounded-3 fw-bold w-100">
                        Xem tất cả sản phẩm <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</main>

<style>
.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
</style>

<?php include 'includes/footer.php'; ?>