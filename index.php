<?php
// /admin/index.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$stats = getDashboardStats();

// Lấy 5 đơn hàng mới nhất
$conn = getDBConnection();
$recent_orders = $conn->query("
    SELECT d.*, n.ho_ten 
    FROM don_hang d 
    LEFT JOIN nguoi_dung n ON d.nguoi_dung_id = n.id 
    ORDER BY d.tao_luc DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Lấy 5 sản phẩm bán chạy nhất
$best_sellers = $conn->query("
    SELECT s.id, s.ten_san_pham, s.gia_ban, 
           SUM(ct.so_luong) as total_sold
    FROM san_pham s
    LEFT JOIN chi_tiet_don_hang ct ON s.id = ct.san_pham_id
    GROUP BY s.id
    ORDER BY total_sold DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-1">
            <div class="row">
                <div class="col-8">
                    <h5 class="fw-bold">Người dùng</h5>
                    <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                </div>
                <div class="col-4 text-end">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-2">
            <div class="row">
                <div class="col-8">
                    <h5 class="fw-bold">Sản phẩm</h5>
                    <h2 class="mb-0"><?php echo $stats['total_products']; ?></h2>
                </div>
                <div class="col-4 text-end">
                    <i class="fas fa-tshirt"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-3">
            <div class="row">
                <div class="col-8">
                    <h5 class="fw-bold">Đơn hàng</h5>
                    <h2 class="mb-0"><?php echo $stats['total_orders']; ?></h2>
                </div>
                <div class="col-4 text-end">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-4">
            <div class="row">
                <div class="col-8">
                    <h5 class="fw-bold">Doanh thu tháng</h5>
                    <h2 class="mb-0"><?php echo formatCurrency($stats['monthly_revenue']); ?></h2>
                </div>
                <div class="col-4 text-end">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Orders -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đơn hàng mới nhất</h5>
                <a href="orders.php" class="btn btn-sm btn-primary">Xem tất cả</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Ngày đặt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><a href="order-detail.php?id=<?php echo $order['id']; ?>"><?php echo $order['ma_don_hang']; ?></a></td>
                                <td><?php echo $order['ho_ten'] ?: 'Khách vãng lai'; ?></td>
                                <td><?php echo formatCurrency($order['tong_tien']); ?></td>
                                <td><?php echo displayOrderStatus($order['trang_thai']); ?></td>
                                <td><?php echo formatDate($order['tao_luc']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Best Selling Products -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Sản phẩm bán chạy</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($best_sellers as $product): ?>
                    <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo $product['ten_san_pham']; ?></h6>
                            <small><?php echo formatCurrency($product['gia_ban']); ?></small>
                        </div>
                        <p class="mb-1">Đã bán: <?php echo $product['total_sold'] ?: 0; ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>