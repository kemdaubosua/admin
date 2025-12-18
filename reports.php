<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Báo cáo & Thống kê';
$active_page = 'reports';

// Lấy filter từ URL
$period = $_GET['period'] ?? 'month';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Xác định khoảng thời gian (chỉ rõ bảng don_hang)
$date_condition = '';
$params = [];

if ($period === 'year') {
    $date_condition = "YEAR(don_hang.tao_luc) = ?";
    $params[] = $year;
    $period_label = "Năm $year";
} elseif ($period === 'month') {
    $date_condition = "YEAR(don_hang.tao_luc) = ? AND MONTH(don_hang.tao_luc) = ?";
    $params[] = $year;
    $params[] = $month;
    $period_label = "Tháng $month/$year";
} else {
    $date_condition = "1=1";
    $period_label = "Toàn bộ";
}

// Thống kê doanh thu
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as tong_don_hang,
    COALESCE(SUM(tong_tien), 0) as tong_doanh_thu,
    COALESCE(AVG(tong_tien), 0) as gia_tri_trung_binh,
    COALESCE(SUM(CASE WHEN trang_thai = 'HOAN_TAT' THEN tong_tien ELSE 0 END), 0) as doanh_thu_hoan_tat
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

// Top 10 sản phẩm bán chạy
$stmt = $pdo->prepare("SELECT 
    ct.san_pham_id,
    ct.ten_san_pham,
    sp.anh_dai_dien_url,
    SUM(ct.so_luong) as tong_ban,
    SUM(ct.thanh_tien) as doanh_thu
FROM chi_tiet_don_hang ct
LEFT JOIN don_hang dh ON ct.don_hang_id = dh.id
LEFT JOIN san_pham sp ON ct.san_pham_id = sp.id
WHERE $date_condition AND dh.trang_thai != 'HUY'
GROUP BY ct.san_pham_id, ct.ten_san_pham, sp.anh_dai_dien_url
ORDER BY tong_ban DESC
LIMIT 10");
$stmt->execute($params);
$top_products = $stmt->fetchAll();

// Thống kê khách hàng mới
$stmt = $pdo->prepare("SELECT COUNT(*) as so_luong 
FROM nguoi_dung 
WHERE vai_tro = 'NGUOI_DUNG' AND $date_condition");
$date_cond_user = str_replace('tao_luc', 'nguoi_dung.tao_luc', $date_condition);
$stmt = $pdo->prepare("SELECT COUNT(*) as so_luong 
FROM nguoi_dung 
WHERE vai_tro = 'NGUOI_DUNG' AND $date_cond_user");
$stmt->execute($params);
$new_users = $stmt->fetchColumn();

// Doanh thu theo ngày (7 ngày gần nhất cho tháng hiện tại)
$revenue_by_day = [];
if ($period === 'month') {
    $stmt = $pdo->prepare("SELECT 
        DATE(don_hang.tao_luc) as ngay,
        COALESCE(SUM(don_hang.tong_tien), 0) as doanh_thu,
        COUNT(*) as so_don
    FROM don_hang 
    WHERE YEAR(don_hang.tao_luc) = ? AND MONTH(don_hang.tao_luc) = ? AND don_hang.trang_thai != 'HUY'
    GROUP BY DATE(don_hang.tao_luc)
    ORDER BY ngay DESC
    LIMIT 7");
    $stmt->execute([$year, $month]);
    $revenue_by_day = array_reverse($stmt->fetchAll());
}

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
            <h2 class="fw-bold text-dark mb-1">Báo cáo & Thống kê</h2>
            <p class="text-secondary small mb-0">Phân tích doanh thu và hiệu quả kinh doanh</p>
        </div>
        <div class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-3 py-2">
            <i class="fas fa-calendar me-1"></i> <?php echo $period_label; ?>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Kỳ báo cáo</label>
                    <select name="period" class="form-select" onchange="toggleDateInputs(this.value)">
                        <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>Toàn bộ</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Theo năm</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Theo tháng</option>
                    </select>
                </div>
                <div class="col-md-3" id="yearInput">
                    <label class="form-label small fw-bold text-secondary">Năm</label>
                    <select name="year" class="form-select">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3" id="monthInput" style="<?php echo $period !== 'month' ? 'display:none;' : ''; ?>">
                    <label class="form-label small fw-bold text-secondary">Tháng</label>
                    <select name="month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                Tháng <?php echo $m; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-chart-bar me-1"></i> Xem báo cáo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">TỔNG DOANH THU</div>
                            <h3 class="fw-bold mb-0"><?php echo formatPrice($revenue_stats['tong_doanh_thu']); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-money-bill-wave fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        Hoàn tất: <?php echo formatPrice($revenue_stats['doanh_thu_hoan_tat']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-gradient-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">TỔNG ĐƠN HÀNG</div>
                            <h3 class="fw-bold mb-0"><?php echo number_format($revenue_stats['tong_don_hang']); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        <?php 
                        $completed = $order_status['HOAN_TAT'] ?? 0;
                        $rate = $revenue_stats['tong_don_hang'] > 0 ? round(($completed / $revenue_stats['tong_don_hang']) * 100, 1) : 0;
                        ?>
                        Hoàn tất: <?php echo $completed; ?> (<?php echo $rate; ?>%)
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-gradient-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">GIÁ TRỊ TB/ĐƠN</div>
                            <h3 class="fw-bold mb-0"><?php echo formatPrice($revenue_stats['gia_tri_trung_binh']); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-receipt fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        Giá trị trung bình mỗi đơn
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-gradient-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-white-50 small fw-bold">KHÁCH HÀNG MỚI</div>
                            <h3 class="fw-bold mb-0"><?php echo number_format($new_users); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-3 p-3">
                            <i class="fas fa-user-plus fa-lg"></i>
                        </div>
                    </div>
                    <div class="text-white-50 small">
                        Người dùng đăng ký mới
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <div class="row g-4">
        
        <!-- Doanh thu theo ngày -->
        <?php if ($period === 'month' && !empty($revenue_by_day)): ?>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Doanh thu 7 ngày gần nhất</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Ngày</th>
                                    <th>Số đơn</th>
                                    <th class="text-end">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($revenue_by_day as $day): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo formatDate($day['ngay']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo $day['so_don']; ?> đơn</span></td>
                                        <td class="text-end fw-bold text-primary"><?php echo formatPrice($day['doanh_thu']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Trạng thái đơn hàng -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Trạng thái đơn hàng</h5>
                </div>
                <div class="card-body p-4">
                    <?php 
                    $statuses = [
                        'CHO_XU_LY' => ['label' => 'Chờ xử lý', 'color' => 'info'],
                        'DANG_XU_LY' => ['label' => 'Đang xử lý', 'color' => 'primary'],
                        'HOAN_TAT' => ['label' => 'Hoàn tất', 'color' => 'success'],
                        'HUY' => ['label' => 'Đã hủy', 'color' => 'danger']
                    ];
                    $total_orders = array_sum($order_status);
                    ?>
                    <?php foreach ($statuses as $key => $status): ?>
                        <?php 
                        $count = $order_status[$key] ?? 0;
                        $percent = $total_orders > 0 ? round(($count / $total_orders) * 100, 1) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold"><?php echo $status['label']; ?></span>
                                <span class="small text-secondary"><?php echo $count; ?> (<?php echo $percent; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-<?php echo $status['color']; ?>" role="progressbar" 
                                     style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top sản phẩm -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0">Top 10 sản phẩm bán chạy</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Sản phẩm</th>
                                    <th>Đã bán</th>
                                    <th class="text-end pe-4">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_products)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-4">Chưa có dữ liệu</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_products as $index => $product): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?php echo $product['anh_dai_dien_url'] ?: 'https://placehold.co/50x50'; ?>" 
                                                         class="rounded-3" width="50" height="50" style="object-fit: cover;">
                                                    <div>
                                                        <div class="fw-bold"><?php echo $product['ten_san_pham']; ?></div>
                                                        <div class="text-secondary small">#<?php echo $product['san_pham_id']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <?php echo $product['tong_ban']; ?> sản phẩm
                                                </span>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-primary"><?php echo formatPrice($product['doanh_thu']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</main>

<script>
function toggleDateInputs(period) {
    const yearInput = document.getElementById('yearInput');
    const monthInput = document.getElementById('monthInput');
    
    if (period === 'all') {
        yearInput.style.display = 'none';
        monthInput.style.display = 'none';
    } else if (period === 'year') {
        yearInput.style.display = 'block';
        monthInput.style.display = 'none';
    } else if (period === 'month') {
        yearInput.style.display = 'block';
        monthInput.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const period = '<?php echo $period; ?>';
    toggleDateInputs(period);
});
</script>

<style>
.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
</style>

<?php include 'includes/footer.php'; ?>