<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Dashboard';
$active_page = 'dashboard';

// Lấy filter từ URL
$period = $_GET['period'] ?? 'month';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Xác định khoảng thời gian
$date_condition = '';
$date_condition_alias = ''; // Cho query có alias dh
$params = [];

if ($period === 'year') {
    $date_condition = "YEAR(tao_luc) = ?";
    $date_condition_alias = "YEAR(dh.tao_luc) = ?";
    $params[] = $year;
    $period_label = "Năm $year";
} elseif ($period === 'month') {
    $date_condition = "YEAR(tao_luc) = ? AND MONTH(tao_luc) = ?";
    $date_condition_alias = "YEAR(dh.tao_luc) = ? AND MONTH(dh.tao_luc) = ?";
    $params[] = $year;
    $params[] = $month;
    $period_label = "Tháng $month/$year";
} else {
    $date_condition = "1=1";
    $date_condition_alias = "1=1";
    $period_label = "Toàn bộ";
}

// Thống kê tổng quan
$stats = [];

// Tổng người dùng
$stmt = $pdo->query("SELECT COUNT(*) as total FROM nguoi_dung WHERE vai_tro = 'NGUOI_DUNG'");
$stats['users'] = $stmt->fetch()['total'];

// Tổng sản phẩm
$stmt = $pdo->query("SELECT COUNT(*) as total FROM san_pham WHERE trang_thai = 'DANG_BAN'");
$stats['products'] = $stmt->fetch()['total'];

// Thống kê đơn hàng & doanh thu
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as tong_don_hang,
    COALESCE(SUM(tong_tien), 0) as tong_doanh_thu,
    COALESCE(AVG(tong_tien), 0) as gia_tri_trung_binh
FROM don_hang 
WHERE $date_condition AND trang_thai != 'HUY'");
$stmt->execute($params);
$revenue_stats = $stmt->fetch();

// Thống kê đơn hàng theo trạng thái
$stmt = $pdo->prepare("SELECT trang_thai, COUNT(*) as so_luong 
FROM don_hang 
WHERE $date_condition 
GROUP BY trang_thai");
$stmt->execute($params);
$order_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Đơn hàng gần đây
$stmt = $pdo->prepare("SELECT d.*, n.ho_ten, n.email 
                       FROM don_hang d 
                       LEFT JOIN nguoi_dung n ON d.nguoi_dung_id = n.id 
                       ORDER BY d.tao_luc DESC LIMIT 10");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Top 5 sản phẩm bán chạy
$stmt = $pdo->prepare("SELECT 
    ct.san_pham_id,
    ct.ten_san_pham,
    sp.anh_dai_dien_url,
    SUM(ct.so_luong) as tong_ban,
    SUM(ct.thanh_tien) as doanh_thu
FROM chi_tiet_don_hang ct
LEFT JOIN don_hang dh ON ct.don_hang_id = dh.id
LEFT JOIN san_pham sp ON ct.san_pham_id = sp.id
WHERE $date_condition_alias AND dh.trang_thai != 'HUY'
GROUP BY ct.san_pham_id, ct.ten_san_pham, sp.anh_dai_dien_url
ORDER BY tong_ban DESC
LIMIT 5");
$stmt->execute($params);
$top_products = $stmt->fetchAll();

// Doanh thu 7 ngày gần nhất
$revenue_by_day = [];
if ($period === 'month') {
    $stmt = $pdo->prepare("SELECT 
        DATE(tao_luc) as ngay,
        COALESCE(SUM(tong_tien), 0) as doanh_thu,
        COUNT(*) as so_don
    FROM don_hang 
    WHERE YEAR(tao_luc) = ? AND MONTH(tao_luc) = ? AND trang_thai != 'HUY'
    GROUP BY DATE(tao_luc)
    ORDER BY ngay DESC
    LIMIT 7");
    $stmt->execute([$year, $month]);
    $revenue_by_day = array_reverse($stmt->fetchAll());
}

include 'includes/header.php';
?>

<style>
/* Stats Cards with Gradients */
.stat-card {
    border-radius: 1rem !important;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1) !important;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.stat-icon.indigo { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.2) 100%); color: #6366f1; }
.stat-icon.emerald { background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.2) 100%); color: #10b981; }
.stat-icon.orange { background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.2) 100%); color: #f59e0b; }
.stat-icon.sky { background: linear-gradient(135deg, rgba(14, 165, 233, 0.1) 0%, rgba(14, 165, 233, 0.2) 100%); color: #0ea5e9; }

/* Chart Bar */
.chart-bar {
    background: linear-gradient(180deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 0.5rem 0.5rem 0 0;
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
}
.chart-bar:hover {
    background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%);
}

.chart-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #1f2937;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s;
    margin-bottom: 8px;
    pointer-events: none;
}
.chart-bar:hover .chart-tooltip {
    opacity: 1;
}

/* Progress Bar Custom */
.progress-custom {
    height: 8px;
    border-radius: 10px;
    background-color: #f3f4f6;
}
.progress-bar-custom {
    border-radius: 10px;
    transition: width 0.6s ease;
}

/* Table Modern */
.table-modern {
    font-size: 0.875rem;
}
.table-modern thead th {
    background-color: #f9fafb;
    border: none;
    color: #6b7280;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    padding: 1rem;
}
.table-modern tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s;
}
.table-modern tbody tr:hover {
    background-color: #f9fafb;
}
.table-modern tbody td {
    padding: 1rem;
    vertical-align: middle;
}

/* Product List Item */
.product-item {
    padding: 1rem 0;
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.2s;
}
.product-item:last-child {
    border-bottom: none;
}
.product-item:hover {
    background-color: #f9fafb;
    margin: 0 -1rem;
    padding-left: 1rem;
    padding-right: 1rem;
}

/* Card Modern */
.card-modern {
    border: none !important;
    border-radius: 1rem !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .stat-card { margin-bottom: 1rem; }
    .chart-container { height: 200px !important; }
}
</style>

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

<main class="main-content min-vh-100 p-3 p-lg-5" style="background-color: #f8fafc;">
    
    <!-- Mobile Header -->
    <div class="d-lg-none d-flex align-items-center justify-content-between mb-4">
        <button class="btn btn-white border shadow-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="fw-bold fs-5">AdminCenter</span>
        <img src="https://ui-avatars.com/api/?name=Admin+User" class="rounded-circle border" width="36" height="36" alt="Admin">
    </div>

    <?php displayMessage(); ?>

    <!-- Page Header -->
    <div class="row align-items-center mb-4">
        <div class="col-12 col-md-7 mb-3 mb-md-0">
            <h2 class="fw-bold text-dark mb-1">Dashboard Overview</h2>
            <p class="text-secondary small mb-0">Tổng quan hoạt động kinh doanh • <?php echo $period_label; ?></p>
        </div>
        <div class="col-12 col-md-5">
            <form method="GET" action="" class="d-flex flex-wrap gap-2 justify-content-md-end">
                <select name="period" class="form-select form-select-sm" style="max-width: 120px;" onchange="togglePeriodInputs(this.value)">
                    <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>Toàn bộ</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Năm</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Tháng</option>
                </select>
                <select name="year" id="yearSelect" class="form-select form-select-sm" style="max-width: 100px; <?php echo $period === 'all' ? 'display:none;' : ''; ?>">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <select name="month" id="monthSelect" class="form-select form-select-sm" style="max-width: 110px; <?php echo $period !== 'month' ? 'display:none;' : ''; ?>">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                            Tháng <?php echo $m; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-3 p-lg-4">
                    <div class="stat-icon indigo mb-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <p class="text-secondary small mb-1 fw-medium">Người dùng</p>
                    <h3 class="fw-bold mb-2 fs-4 fs-lg-3"><?php echo number_format($stats['users']); ?></h3>
                    <p class="text-success small mb-0 fw-medium">
                        <i class="fas fa-arrow-up me-1"></i> <span class="d-none d-sm-inline">Tổng tài khoản</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-3 p-lg-4">
                    <div class="stat-icon emerald mb-3">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <p class="text-secondary small mb-1 fw-medium">Doanh thu</p>
                    <h3 class="fw-bold mb-2 fs-5 fs-lg-3"><?php echo formatPrice($revenue_stats['tong_doanh_thu']); ?></h3>
                    <p class="text-success small mb-0 fw-medium">
                        <i class="fas fa-arrow-up me-1"></i> <span class="d-none d-sm-inline"><?php echo $period_label; ?></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-3 p-lg-4">
                    <div class="stat-icon orange mb-3">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <p class="text-secondary small mb-1 fw-medium">Đơn hàng</p>
                    <h3 class="fw-bold mb-2 fs-4 fs-lg-3"><?php echo number_format($revenue_stats['tong_don_hang']); ?></h3>
                    <p class="text-secondary small mb-0 fw-medium">
                        <i class="fas fa-check-circle me-1"></i> <span class="d-none d-sm-inline">Tổng đơn</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-3 p-lg-4">
                    <div class="stat-icon sky mb-3">
                        <i class="fas fa-box"></i>
                    </div>
                    <p class="text-secondary small mb-1 fw-medium">Sản phẩm</p>
                    <h3 class="fw-bold mb-2 fs-4 fs-lg-3"><?php echo number_format($stats['products']); ?></h3>
                    <p class="text-success small mb-0 fw-medium">
                        <i class="fas fa-check me-1"></i> <span class="d-none d-sm-inline">Đang bán</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Status -->
    <div class="row g-3 g-lg-4 mb-4">
        
        <!-- Doanh thu 7 ngày -->
        <?php if ($period === 'month' && !empty($revenue_by_day)): ?>
        <div class="col-12 col-lg-8">
            <div class="card card-modern">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
                        <h5 class="fw-bold mb-0">Doanh thu 7 ngày gần nhất</h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle" style="width: 8px; height: 8px; background-color: #6366f1;"></span>
                            <span class="text-secondary small">Doanh thu</span>
                        </div>
                    </div>
                    <div class="chart-container d-flex align-items-end justify-content-between gap-2" style="height: 280px;">
                        <?php 
                        $max_revenue = max(array_column($revenue_by_day, 'doanh_thu'));
                        foreach ($revenue_by_day as $day): 
                            $height = $max_revenue > 0 ? ($day['doanh_thu'] / $max_revenue * 100) : 0;
                        ?>
                        <div class="flex-fill d-flex flex-column align-items-center h-100">
                            <div class="chart-bar w-100 position-relative" style="height: <?php echo $height; ?>%; max-height: 100%;">
                                <div class="chart-tooltip"><?php echo formatPrice($day['doanh_thu']); ?></div>
                            </div>
                            <span class="text-secondary mt-2" style="font-size: 10px;">
                                <?php echo date('d/m', strtotime($day['ngay'])); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Trạng thái đơn hàng -->
        <div class="col-12 <?php echo ($period === 'month' && !empty($revenue_by_day)) ? 'col-lg-4' : 'col-lg-12'; ?>">
            <div class="card card-modern h-100">
                <div class="card-body p-3 p-lg-4">
                    <h5 class="fw-bold mb-4">Trạng thái đơn hàng</h5>
                    <?php 
                    $statuses = [
                        'CHO_XU_LY' => ['label' => 'Chờ xử lý', 'color' => '#0ea5e9'],
                        'DANG_XU_LY' => ['label' => 'Đang xử lý', 'color' => '#6366f1'],
                        'HOAN_TAT' => ['label' => 'Hoàn tất', 'color' => '#10b981'],
                        'HUY' => ['label' => 'Đã hủy', 'color' => '#ef4444']
                    ];
                    $total_orders = array_sum($order_status);
                    ?>
                    <div class="row g-3">
                        <?php foreach ($statuses as $key => $status): ?>
                            <?php 
                            $count = $order_status[$key] ?? 0;
                            $percent = $total_orders > 0 ? round(($count / $total_orders) * 100, 1) : 0;
                            ?>
                            <div class="col-12 col-md-6 <?php echo ($period === 'month' && !empty($revenue_by_day)) ? 'col-lg-12' : 'col-lg-3'; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-medium"><?php echo $status['label']; ?></span>
                                    <span class="small text-secondary"><?php echo $count; ?> (<?php echo $percent; ?>%)</span>
                                </div>
                                <div class="progress progress-custom">
                                    <div class="progress-bar progress-bar-custom" role="progressbar" 
                                         style="width: <?php echo $percent; ?>%; background-color: <?php echo $status['color']; ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Orders & Products -->
    <div class="row g-3 g-lg-4 mb-4">
        
        <!-- Recent Orders -->
        <div class="col-12 col-lg-8">
            <div class="card card-modern">
                <div class="card-body p-0">
                    <div class="p-3 p-lg-4 border-bottom">
                        <h5 class="fw-bold mb-0">Đơn hàng gần đây</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3 ps-lg-4">Mã đơn</th>
                                    <th class="d-none d-md-table-cell">Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th class="pe-3 pe-lg-4 d-none d-lg-table-cell">Ngày</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-5">Chưa có đơn hàng</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($recent_orders, 0, 6) as $order): ?>
                                        <tr>
                                            <td class="ps-3 ps-lg-4">
                                                <span class="badge bg-light text-primary font-monospace border small"><?php echo $order['ma_don_hang']; ?></span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div class="fw-medium small"><?php echo $order['ho_ten'] ?? 'Khách hàng'; ?></div>
                                                <div class="text-secondary" style="font-size: 11px;"><?php echo $order['email']; ?></div>
                                            </td>
                                            <td class="fw-bold small"><?php echo formatPrice($order['tong_tien']); ?></td>
                                            <td><?php echo getStatusBadge($order['trang_thai'], 'order'); ?></td>
                                            <td class="pe-3 pe-lg-4 text-secondary small d-none d-lg-table-cell"><?php echo formatDate($order['tao_luc']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top text-center">
                        <a href="orders.php" class="btn btn-sm btn-light text-primary fw-medium">
                            Xem tất cả <i class="fas fa-arrow-right ms-1 small"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="col-12 col-lg-4">
            <div class="card card-modern">
                <div class="card-body p-3 p-lg-4">
                    <h5 class="fw-bold mb-4">Sản phẩm bán chạy</h5>
                    <?php if (empty($top_products)): ?>
                        <p class="text-center text-secondary">Chưa có dữ liệu</p>
                    <?php else: ?>
                        <?php foreach ($top_products as $product): ?>
                            <div class="product-item d-flex align-items-center gap-3">
                                <img src="<?php echo $product['anh_dai_dien_url'] ?: 'https://placehold.co/50x50'; ?>" 
                                     class="rounded-3 flex-shrink-0" width="50" height="50" style="object-fit: cover;" alt="">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-medium small text-truncate"><?php echo $product['ten_san_pham']; ?></div>
                                    <div class="text-secondary" style="font-size: 11px;">Đã bán: <?php echo $product['tong_ban']; ?></div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="fw-bold text-primary" style="font-size: 13px;"><?php echo formatPrice($product['doanh_thu']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="text-center mt-3 pt-3 border-top">
                        <a href="products.php" class="btn btn-sm btn-light text-success fw-medium w-100">
                            Xem tất cả sản phẩm
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

</main>

<script>
function togglePeriodInputs(period) {
    const yearSelect = document.getElementById('yearSelect');
    const monthSelect = document.getElementById('monthSelect');
    
    if (period === 'all') {
        yearSelect.style.display = 'none';
        monthSelect.style.display = 'none';
    } else if (period === 'year') {
        yearSelect.style.display = 'block';
        monthSelect.style.display = 'none';
    } else if (period === 'month') {
        yearSelect.style.display = 'block';
        monthSelect.style.display = 'block';
    }
}

// Set initial state
document.addEventListener('DOMContentLoaded', function() {
    togglePeriodInputs('<?php echo $period; ?>');
});
</script>

<?php include 'includes/footer.php'; ?>