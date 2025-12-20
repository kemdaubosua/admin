<?php
// Bắt đầu session an toàn
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Kiểm tra quyền Admin
checkAdminAuth();

$page_title = 'Dashboard';
$active_page = 'dashboard';

// 1. Xử lý Filter & Input Validation
$period = $_GET['period'] ?? 'month';
$year   = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month  = isset($_GET['month']) ? str_pad(intval($_GET['month']), 2, '0', STR_PAD_LEFT) : date('m');

if (!in_array($period, ['all', 'year', 'month'])) {
    $period = 'month';
}

// 2. Xây dựng Query Condition
$date_condition = "1=1";
$prev_date_condition = "1=1"; 
$params = [];
$prev_params = [];

if ($period === 'year') {
    $date_condition = "YEAR(tao_luc) = ?";
    $params[] = $year;
    
    $prev_date_condition = "YEAR(tao_luc) = ?";
    $prev_params[] = $year - 1;
    
    $period_label = "Năm $year";
} elseif ($period === 'month') {
    $date_condition = "YEAR(tao_luc) = ? AND MONTH(tao_luc) = ?";
    $params[] = $year;
    $params[] = $month;
    
    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month == 0) {
        $prev_month = 12;
        $prev_year--;
    }
    $prev_date_condition = "YEAR(tao_luc) = ? AND MONTH(tao_luc) = ?";
    $prev_params[] = $prev_year;
    $prev_params[] = $prev_month;
    
    $period_label = "Tháng $month/$year";
} else {
    $period_label = "Toàn bộ thời gian";
}

// Hàm tính % tăng trưởng
function calculateGrowth($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

// 3. TRUY VẤN DỮ LIỆU

// --- A. KHÁCH HÀNG ---
$stmt = $pdo->query("SELECT COUNT(*) as total FROM nguoi_dung WHERE vai_tro = 'NGUOI_DUNG'");
$stats['users'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro = 'NGUOI_DUNG' AND $date_condition");
$stmt->execute($params);
$users_new_curr = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro = 'NGUOI_DUNG' AND $prev_date_condition");
$stmt->execute($prev_params);
$users_new_prev = $stmt->fetchColumn();
$user_growth = calculateGrowth($users_new_curr, $users_new_prev);

// --- B. DOANH THU & ĐƠN HÀNG ---
$stmt = $pdo->prepare("SELECT COUNT(*) as tong_don, COALESCE(SUM(tong_tien), 0) as tong_tien FROM don_hang WHERE $date_condition AND trang_thai != 'HUY'");
$stmt->execute($params);
$curr_order_stats = $stmt->fetch();
$revenue_stats = [
    'tong_don_hang' => $curr_order_stats['tong_don'],
    'tong_doanh_thu' => $curr_order_stats['tong_tien']
];

$stmt = $pdo->prepare("SELECT COUNT(*) as tong_don, COALESCE(SUM(tong_tien), 0) as tong_tien FROM don_hang WHERE $prev_date_condition AND trang_thai != 'HUY'");
$stmt->execute($prev_params);
$prev_order_stats = $stmt->fetch();

$revenue_growth = calculateGrowth($curr_order_stats['tong_tien'], $prev_order_stats['tong_tien']);
$order_growth = calculateGrowth($curr_order_stats['tong_don'], $prev_order_stats['tong_don']);

// --- C. SẢN PHẨM ---
$stmt = $pdo->query("SELECT COUNT(*) as total FROM san_pham WHERE trang_thai = 'DANG_BAN'");
$stats['products'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM san_pham WHERE $date_condition");
$stmt->execute($params);
$prods_new_curr = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM san_pham WHERE $prev_date_condition");
$stmt->execute($prev_params);
$prods_new_prev = $stmt->fetchColumn();
$product_growth = calculateGrowth($prods_new_curr, $prods_new_prev);

// --- D. DỮ LIỆU KHÁC ---
$stmt = $pdo->prepare("SELECT trang_thai, COUNT(*) as so_luong FROM don_hang WHERE $date_condition GROUP BY trang_thai");
$stmt->execute($params);
$order_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare("SELECT d.*, n.ho_ten, n.email FROM don_hang d LEFT JOIN nguoi_dung n ON d.nguoi_dung_id = n.id ORDER BY d.tao_luc DESC LIMIT 6");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

$date_condition_alias = str_replace("tao_luc", "dh.tao_luc", $date_condition);
$stmt = $pdo->prepare("SELECT ct.san_pham_id, ct.ten_san_pham, sp.anh_dai_dien_url, SUM(ct.so_luong) as tong_ban, SUM(ct.thanh_tien) as doanh_thu 
FROM chi_tiet_don_hang ct LEFT JOIN don_hang dh ON ct.don_hang_id = dh.id LEFT JOIN san_pham sp ON ct.san_pham_id = sp.id 
WHERE $date_condition_alias AND dh.trang_thai != 'HUY' GROUP BY ct.san_pham_id, ct.ten_san_pham, sp.anh_dai_dien_url ORDER BY tong_ban DESC LIMIT 5");
$stmt->execute($params);
$top_products = $stmt->fetchAll();

$revenue_by_day = [];
if ($period === 'month') {
    $stmt = $pdo->prepare("SELECT DATE(tao_luc) as ngay, COALESCE(SUM(tong_tien), 0) as doanh_thu FROM don_hang WHERE YEAR(tao_luc) = ? AND MONTH(tao_luc) = ? AND trang_thai != 'HUY' GROUP BY DATE(tao_luc) ORDER BY ngay DESC LIMIT 7");
    $stmt->execute([$year, $month]);
    $revenue_by_day = array_reverse($stmt->fetchAll());
}

include 'includes/header.php';
?>

<style>
/* --- Stat Cards --- */
.stat-card {
    border: none;
    border-radius: 1rem;
    transition: all 0.3s ease;
    background-color: #fff;
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important;
}
.stat-card h4 { font-size: 1.25rem; }

/* --- Icons --- */
.stat-icon-box {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.bg-indigo-soft { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
.bg-emerald-soft { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.bg-orange-soft { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.bg-sky-soft { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }

/* --- Growth Badge --- */
.growth-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    line-height: 1;
    white-space: nowrap;
}
.growth-up { color: #059669; background-color: #d1fae5; }
.growth-down { color: #dc2626; background-color: #fee2e2; }
.growth-neutral { color: #4b5563; background-color: #f3f4f6; }

/* --- Chart Layout --- */
.chart-wrapper {
    position: relative;
    padding-left: 60px;
    padding-right: 10px;
}
.y-axis {
    position: absolute;
    top: 0; left: 0; bottom: 30px;
    width: 60px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    pointer-events: none;
    z-index: 1;
}
.y-axis-label {
    font-size: 10px;
    color: #94a3b8;
    text-align: right;
    padding-right: 10px;
    transform: translateY(50%);
}
.y-axis-label:first-child { transform: translateY(0); }
.y-axis-label:last-child { transform: translateY(100%); }

.grid-lines {
    position: absolute;
    top: 0; left: 60px; right: 0; bottom: 30px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 0;
}
.grid-line {
    width: 100%;
    border-bottom: 1px dashed #e2e8f0;
    height: 0;
}
.grid-line:last-child { border-bottom: 1px solid #cbd5e1; }

.bars-container {
    position: relative;
    z-index: 2;
    height: 100%;
}

.chart-bar { background: linear-gradient(180deg, #6366f1 0%, #a5b4fc 100%); border-radius: 6px 6px 0 0; transition: height 0.6s ease; cursor: pointer; min-height: 4px; }
.chart-bar:hover { opacity: 0.85; }
.chart-tooltip { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; opacity: 0; transition: opacity 0.2s; margin-bottom: 6px; pointer-events: none; z-index: 10; }
.chart-bar:hover .chart-tooltip { opacity: 1; }

/* --- Helpers --- */
.table-modern thead th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; color: #64748b; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 1rem; }
.table-modern td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.product-item:hover { background-color: #f8fafc; }
.min-w-0 { min-width: 0 !important; }

/* --- CSS FIX RESPONSIVE --- */
/* Mobile (dưới 576px): Filter dãn full, Stat Card full width */
@media (max-width: 576px) {
    .filter-form { width: 100%; }
    .filter-form select, .filter-form button { width: 100% !important; margin-bottom: 5px; }
    .stat-icon-box { width: 45px; height: 45px; font-size: 1.1rem; }
}
/* Desktop: Select box auto width */
@media (min-width: 768px) {
    .w-md-auto { width: auto !important; }
}
</style>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold text-primary">AdminPanel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php include 'includes/sidebar.php'; ?>
    </div>
</div>

<div class="d-flex min-vh-100 bg-light">
    
    <div class="d-none d-lg-block border-end bg-white" style="width: 260px; min-width: 260px;">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <main class="flex-grow-1 p-3 p-lg-4" style="overflow-x: hidden;">
        
        <div class="d-lg-none d-flex align-items-center justify-content-between mb-4">
            <button class="btn btn-white border shadow-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="fw-bold mb-0">Dashboard</h5>
            <div style="width: 40px;"></div> 
        </div>

        <?php displayMessage(); ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold text-dark mb-1">Tổng quan</h2>
                <p class="text-secondary small mb-0">
                    <i class="far fa-calendar-alt me-1"></i> <?php echo $period_label; ?>
                </p>
            </div>
            
            <form method="GET" action="" class="d-flex flex-column flex-md-row gap-2 card p-2 border-0 shadow-sm filter-form">
                <select name="period" class="form-select form-select-sm border-0 bg-light w-100 w-md-auto" onchange="togglePeriodInputs(this.value)">
                    <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Theo Năm</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Theo Tháng</option>
                </select>
                
                <select name="year" id="yearSelect" class="form-select form-select-sm border-0 bg-light w-100 w-md-auto" style="min-width: 80px; <?php echo $period === 'all' ? 'display:none;' : ''; ?>">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                
                <select name="month" id="monthSelect" class="form-select form-select-sm border-0 bg-light w-100 w-md-auto" style="min-width: 100px; <?php echo $period !== 'month' ? 'display:none;' : ''; ?>">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                            Tháng <?php echo $m; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                
                <button type="submit" class="btn btn-sm btn-primary px-3 rounded-3 w-100 w-md-auto">
                    <i class="fas fa-filter"></i> Lọc
                </button>
            </form>
        </div>

        <div class="row g-3 g-lg-4 mb-4">
            
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon-box bg-indigo-soft">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="text-secondary text-xs fw-bold text-uppercase">Khách hàng</span>
                                    <?php if ($period !== 'all'): 
                                        $class = $user_growth > 0 ? 'growth-up' : ($user_growth < 0 ? 'growth-down' : 'growth-neutral');
                                        $icon = $user_growth > 0 ? 'fa-arrow-up' : ($user_growth < 0 ? 'fa-arrow-down' : 'fa-minus');
                                    ?>
                                    <span class="growth-badge <?php echo $class; ?>">
                                        <i class="fas <?php echo $icon; ?>" style="font-size: 8px;"></i> <?php echo abs($user_growth); ?>%
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="fw-bold text-dark mb-0"><?php echo number_format($stats['users']); ?></h4>
                                <div class="text-xs text-secondary mt-1 text-truncate">Tổng tài khoản</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon-box bg-emerald-soft">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="text-secondary text-xs fw-bold text-uppercase">Doanh thu</span>
                                    <?php if ($period !== 'all'): 
                                        $class = $revenue_growth > 0 ? 'growth-up' : ($revenue_growth < 0 ? 'growth-down' : 'growth-neutral');
                                        $icon = $revenue_growth > 0 ? 'fa-arrow-up' : ($revenue_growth < 0 ? 'fa-arrow-down' : 'fa-minus');
                                    ?>
                                    <span class="growth-badge <?php echo $class; ?>">
                                        <i class="fas <?php echo $icon; ?>" style="font-size: 8px;"></i> <?php echo abs($revenue_growth); ?>%
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="fw-bold text-dark mb-0"><?php echo formatPrice($revenue_stats['tong_doanh_thu']); ?></h4>
                                <div class="text-xs text-secondary mt-1 text-truncate"><?php echo $period_label; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon-box bg-orange-soft">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="text-secondary text-xs fw-bold text-uppercase">Đơn hàng</span>
                                    <?php if ($period !== 'all'): 
                                        $class = $order_growth > 0 ? 'growth-up' : ($order_growth < 0 ? 'growth-down' : 'growth-neutral');
                                        $icon = $order_growth > 0 ? 'fa-arrow-up' : ($order_growth < 0 ? 'fa-arrow-down' : 'fa-minus');
                                    ?>
                                    <span class="growth-badge <?php echo $class; ?>">
                                        <i class="fas <?php echo $icon; ?>" style="font-size: 8px;"></i> <?php echo abs($order_growth); ?>%
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="fw-bold text-dark mb-0"><?php echo number_format($revenue_stats['tong_don_hang']); ?></h4>
                                <div class="text-xs text-secondary mt-1 text-truncate">Đơn thành công</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="stat-icon-box bg-sky-soft">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="text-secondary text-xs fw-bold text-uppercase">Sản phẩm</span>
                                    <?php if ($period !== 'all'): 
                                        $class = $product_growth > 0 ? 'growth-up' : ($product_growth < 0 ? 'growth-down' : 'growth-neutral');
                                        $icon = $product_growth > 0 ? 'fa-arrow-up' : ($product_growth < 0 ? 'fa-arrow-down' : 'fa-minus');
                                    ?>
                                    <span class="growth-badge <?php echo $class; ?>">
                                        <i class="fas <?php echo $icon; ?>" style="font-size: 8px;"></i> <?php echo abs($product_growth); ?>%
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="fw-bold text-dark mb-0"><?php echo number_format($stats['products']); ?></h4>
                                <div class="text-xs text-secondary mt-1 text-truncate">Đang bán</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-lg-4 mb-4">
            
            <?php if ($period === 'month'): ?>
            <div class="col-12 col-lg-8">
                <div class="card card-modern shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Biểu đồ doanh thu 7 ngày qua</h5>
                        </div>
                        <?php if (empty($revenue_by_day)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-chart-bar fs-1 mb-3 opacity-25"></i>
                                <p>Chưa có dữ liệu doanh thu trong khoảng thời gian này.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                                // Tìm giá trị lớn nhất
                                $raw_max = 0;
                                foreach($revenue_by_day as $d) {
                                    if($d['doanh_thu'] > $raw_max) $raw_max = $d['doanh_thu'];
                                }
                                
                                // Tính mốc chẵn cho trục Y
                                if ($raw_max == 0) {
                                    $max_revenue = 100000;
                                } else {
                                    $tick = $raw_max / 4;
                                    $len = strlen((int)$tick);
                                    $divisor = pow(10, $len - 1);
                                    if ($divisor < 1) $divisor = 1;
                                    $nice_tick = ceil($tick / $divisor) * $divisor;
                                    $max_revenue = $nice_tick * 4;
                                }
                            ?>

                            <div class="chart-wrapper" style="height: 300px;">
                                <div class="y-axis">
                                    <div class="y-axis-label"><?php echo formatPrice($max_revenue); ?></div>
                                    <div class="y-axis-label"><?php echo formatPrice($max_revenue * 0.75); ?></div>
                                    <div class="y-axis-label"><?php echo formatPrice($max_revenue * 0.5); ?></div>
                                    <div class="y-axis-label"><?php echo formatPrice($max_revenue * 0.25); ?></div>
                                    <div class="y-axis-label">0đ</div>
                                </div>
                                <div class="grid-lines">
                                    <div class="grid-line"></div>
                                    <div class="grid-line"></div>
                                    <div class="grid-line"></div>
                                    <div class="grid-line"></div>
                                    <div class="grid-line"></div>
                                </div>
                                <div class="bars-container d-flex align-items-end justify-content-between gap-2">
                                    <?php foreach ($revenue_by_day as $day): 
                                        $height = ($day['doanh_thu'] / $max_revenue) * 100;
                                    ?>
                                    <div class="d-flex flex-column align-items-center flex-fill" style="height: 100%;">
                                        <div class="w-100 d-flex align-items-end justify-content-center flex-grow-1">
                                            <div class="chart-bar position-relative w-100" style="height: <?php echo $height; ?>%; max-width: 40px;">
                                                <div class="chart-tooltip">
                                                    <?php echo formatPrice($day['doanh_thu']); ?>
                                                    <br>
                                                    <span class="fw-light"><?php echo date('d/m', strtotime($day['ngay'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-xs text-secondary fw-medium d-flex align-items-center justify-content-center" style="height: 30px;">
                                            <?php echo date('d/m', strtotime($day['ngay'])); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-12 <?php echo ($period === 'month') ? 'col-lg-4' : 'col-lg-12'; ?>">
                <div class="card card-modern shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Tỉ lệ đơn hàng</h5>
                        <?php 
                        $statuses = [
                            'CHO_XU_LY' => ['label' => 'Chờ xử lý', 'color' => 'bg-info'],
                            'DANG_XU_LY' => ['label' => 'Đang giao', 'color' => 'bg-primary'],
                            'HOAN_TAT'  => ['label' => 'Hoàn tất', 'color' => 'bg-success'],
                            'HUY'       => ['label' => 'Đã hủy', 'color' => 'bg-danger']
                        ];
                        $total_orders = array_sum($order_status);
                        ?>
                        <div class="d-flex flex-column gap-4">
                        <?php foreach ($statuses as $key => $status): ?>
                            <?php 
                            $count = $order_status[$key] ?? 0;
                            $percent = $total_orders > 0 ? round(($count / $total_orders) * 100, 1) : 0;
                            ?>
                            <div class="<?php echo ($period !== 'month') ? 'd-inline-block w-100 col-md-3 px-2' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-sm fw-medium text-dark"><?php echo $status['label']; ?></span>
                                    <span class="text-xs text-secondary"><?php echo $count; ?> đơn</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo $status['color']; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-lg-4">
            <div class="col-12 col-xl-8">
                <div class="card card-modern shadow-sm">
                    <div class="card-header bg-white border-bottom p-3 p-lg-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Đơn hàng mới nhất</h5>
                        <a href="orders.php" class="btn btn-sm btn-light text-primary fw-medium">Xem tất cả</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th class="pe-4 text-end">Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-secondary">Chưa có đơn hàng nào</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-light text-dark border font-monospace">#<?php echo $order['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark"><?php echo htmlspecialchars($order['ho_ten'] ?? 'Khách vãng lai'); ?></div>
                                            <div class="text-xs text-secondary"><?php echo htmlspecialchars($order['email']); ?></div>
                                        </td>
                                        <td class="fw-bold text-dark"><?php echo formatPrice($order['tong_tien']); ?></td>
                                        <td><?php echo getStatusBadge($order['trang_thai'], 'order'); ?></td>
                                        <td class="pe-4 text-end text-secondary text-xs">
                                            <?php echo date('H:i d/m', strtotime($order['tao_luc'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card card-modern shadow-sm h-100">
                    <div class="card-header bg-white border-bottom p-3 p-lg-4">
                        <h5 class="fw-bold mb-0">Sản phẩm bán chạy</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($top_products)): ?>
                            <div class="p-4 text-center text-secondary">Chưa có dữ liệu</div>
                        <?php else: ?>
                            <div class="p-3">
                            <?php foreach ($top_products as $index => $product): ?>
                                <div class="product-item d-flex align-items-center gap-3 mb-2">
                                    <div class="position-relative">
                                        <img src="<?php echo !empty($product['anh_dai_dien_url']) ? $product['anh_dai_dien_url'] : 'assets/images/no-image.png'; ?>" 
                                             class="rounded-3 border" width="50" height="50" style="object-fit: cover;">
                                        <span class="position-absolute top-0 start-0 m-1 badge rounded-pill bg-dark border border-white text-xs">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-medium text-dark text-truncate small mb-1"><?php echo htmlspecialchars($product['ten_san_pham']); ?></div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-xs text-secondary bg-light px-2 py-1 rounded">Đã bán: <b><?php echo $product['tong_ban']; ?></b></span>
                                            <span class="text-xs fw-bold text-success"><?php echo formatPrice($product['doanh_thu']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="p-3 border-top text-center">
                            <a href="products.php" class="text-decoration-none text-sm fw-medium">Quản lý sản phẩm <i class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function togglePeriodInputs(period) {
    const yearSelect = document.getElementById('yearSelect');
    const monthSelect = document.getElementById('monthSelect');
    
    // Reset display
    yearSelect.style.display = 'none';
    monthSelect.style.display = 'none';

    if (period === 'year') {
        yearSelect.style.display = 'block';
    } else if (period === 'month') {
        yearSelect.style.display = 'block';
        monthSelect.style.display = 'block';
    }
}
// Init on load
document.addEventListener('DOMContentLoaded', () => {
    togglePeriodInputs('<?php echo $period; ?>');
});
</script>

<?php include 'includes/footer.php'; ?>