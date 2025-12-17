<?php
// /admin/reports.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Báo cáo & Thống kê';
$conn = getDBConnection();

// Xử lý filter
$report_type = $_GET['report_type'] ?? 'daily_sales';
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Đầu tháng
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Lấy dữ liệu báo cáo
$report_data = [];
$chart_labels = [];
$chart_values = [];

if ($report_type === 'daily_sales') {
    // Doanh thu theo ngày
    $result = $conn->query("
        SELECT DATE(tao_luc) as date, SUM(tong_tien) as revenue, COUNT(*) as orders
        FROM don_hang 
        WHERE trang_thai = 'HOAN_TAT' 
        AND DATE(tao_luc) BETWEEN '$start_date' AND '$end_date'
        GROUP BY DATE(tao_luc)
        ORDER BY date
    ");
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
        $chart_labels[] = date('d/m', strtotime($row['date']));
        $chart_values[] = $row['revenue'];
    }
    
} elseif ($report_type === 'category_sales') {
    // Doanh thu theo danh mục
    $result = $conn->query("
        SELECT dm.ten_danh_muc, SUM(ct.thanh_tien) as revenue, COUNT(DISTINCT d.id) as orders
        FROM chi_tiet_don_hang ct
        JOIN don_hang d ON ct.don_hang_id = d.id
        JOIN san_pham sp ON ct.san_pham_id = sp.id
        JOIN danh_muc_san_pham dm ON sp.danh_muc_id = dm.id
        WHERE d.trang_thai = 'HOAN_TAT'
        AND DATE(d.tao_luc) BETWEEN '$start_date' AND '$end_date'
        GROUP BY dm.id
        ORDER BY revenue DESC
    ");
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
        $chart_labels[] = $row['ten_danh_muc'];
        $chart_values[] = $row['revenue'];
    }
    
} elseif ($report_type === 'product_sales') {
    // Sản phẩm bán chạy
    $result = $conn->query("
        SELECT sp.ten_san_pham, SUM(ct.so_luong) as quantity, SUM(ct.thanh_tien) as revenue
        FROM chi_tiet_don_hang ct
        JOIN don_hang d ON ct.don_hang_id = d.id
        JOIN san_pham sp ON ct.san_pham_id = sp.id
        WHERE d.trang_thai = 'HOAN_TAT'
        AND DATE(d.tao_luc) BETWEEN '$start_date' AND '$end_date'
        GROUP BY sp.id
        ORDER BY quantity DESC
        LIMIT 10
    ");
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
        $chart_labels[] = $row['ten_san_pham'];
        $chart_values[] = $row['quantity'];
    }
    
} elseif ($report_type === 'customer_orders') {
    // Khách hàng mua nhiều nhất
    $result = $conn->query("
        SELECT n.ho_ten, n.email, COUNT(d.id) as orders, SUM(d.tong_tien) as total_spent
        FROM don_hang d
        JOIN nguoi_dung n ON d.nguoi_dung_id = n.id
        WHERE d.trang_thai = 'HOAN_TAT'
        AND DATE(d.tao_luc) BETWEEN '$start_date' AND '$end_date'
        GROUP BY n.id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
}

// Tính tổng doanh thu
$total_revenue_result = $conn->query("
    SELECT SUM(tong_tien) as total_revenue, COUNT(*) as total_orders
    FROM don_hang 
    WHERE trang_thai = 'HOAN_TAT'
    AND DATE(tao_luc) BETWEEN '$start_date' AND '$end_date'
");
$total_stats = $total_revenue_result->fetch_assoc();

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Báo cáo & Thống kê</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <select class="form-select" name="report_type" id="report_type">
                    <option value="daily_sales" <?php echo $report_type == 'daily_sales' ? 'selected' : ''; ?>>Doanh thu theo ngày</option>
                    <option value="category_sales" <?php echo $report_type == 'category_sales' ? 'selected' : ''; ?>>Doanh thu theo danh mục</option>
                    <option value="product_sales" <?php echo $report_type == 'product_sales' ? 'selected' : ''; ?>>Sản phẩm bán chạy</option>
                    <option value="customer_orders" <?php echo $report_type == 'customer_orders' ? 'selected' : ''; ?>>Khách hàng thân thiết</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Xem báo cáo</button>
            </div>
        </form>

        <!-- Thống kê tổng quan -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Tổng doanh thu</h6>
                        <h4 class="mb-0"><?php echo formatCurrency($total_stats['total_revenue'] ?? 0); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Tổng đơn hàng</h6>
                        <h4 class="mb-0"><?php echo $total_stats['total_orders'] ?? 0; ?> đơn</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biểu đồ -->
        <?php if (count($chart_labels) > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Biểu đồ</h5>
            </div>
            <div class="card-body">
                <canvas id="reportChart" height="100"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bảng dữ liệu -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Chi tiết báo cáo</h5>
                <button class="btn btn-sm btn-success" onclick="exportReport()">
                    <i class="fas fa-file-excel"></i> Xuất Excel
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <?php if ($report_type === 'daily_sales'): ?>
                                <th>Ngày</th>
                                <th>Số đơn hàng</th>
                                <th>Doanh thu</th>
                                <th>Doanh thu trung bình/đơn</th>
                                <?php elseif ($report_type === 'category_sales'): ?>
                                <th>Danh mục</th>
                                <th>Số đơn hàng</th>
                                <th>Doanh thu</th>
                                <th>Tỷ trọng</th>
                                <?php elseif ($report_type === 'product_sales'): ?>
                                <th>Sản phẩm</th>
                                <th>Số lượng bán</th>
                                <th>Doanh thu</th>
                                <th>Đơn giá trung bình</th>
                                <?php elseif ($report_type === 'customer_orders'): ?>
                                <th>Khách hàng</th>
                                <th>Email</th>
                                <th>Số đơn hàng</th>
                                <th>Tổng chi tiêu</th>
                                <th>Trung bình/đơn</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_revenue_for_percent = $total_stats['total_revenue'] ?? 1;
                            foreach ($report_data as $data): 
                            ?>
                            <tr>
                                <?php if ($report_type === 'daily_sales'): ?>
                                <td><?php echo date('d/m/Y', strtotime($data['date'])); ?></td>
                                <td><?php echo $data['orders']; ?></td>
                                <td><?php echo formatCurrency($data['revenue']); ?></td>
                                <td><?php echo formatCurrency($data['revenue'] / max($data['orders'], 1)); ?></td>
                                
                                <?php elseif ($report_type === 'category_sales'): ?>
                                <td><strong><?php echo htmlspecialchars($data['ten_danh_muc']); ?></strong></td>
                                <td><?php echo $data['orders']; ?></td>
                                <td><?php echo formatCurrency($data['revenue']); ?></td>
                                <td>
                                    <?php 
                                    $percentage = ($data['revenue'] / $total_revenue_for_percent) * 100;
                                    echo number_format($percentage, 1) . '%';
                                    ?>
                                </td>
                                
                                <?php elseif ($report_type === 'product_sales'): ?>
                                <td><?php echo htmlspecialchars($data['ten_san_pham']); ?></td>
                                <td><span class="badge bg-info"><?php echo $data['quantity']; ?></span></td>
                                <td><?php echo formatCurrency($data['revenue']); ?></td>
                                <td><?php echo formatCurrency($data['revenue'] / max($data['quantity'], 1)); ?></td>
                                
                                <?php elseif ($report_type === 'customer_orders'): ?>
                                <td><strong><?php echo htmlspecialchars($data['ho_ten'] ?: 'Khách vãng lai'); ?></strong></td>
                                <td><?php echo htmlspecialchars($data['email'] ?? 'N/A'); ?></td>
                                <td><?php echo $data['orders']; ?></td>
                                <td><?php echo formatCurrency($data['total_spent']); ?></td>
                                <td><?php echo formatCurrency($data['total_spent'] / max($data['orders'], 1)); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Vẽ biểu đồ
<?php if (count($chart_labels) > 0): ?>
const ctx = document.getElementById('reportChart').getContext('2d');
const reportChart = new Chart(ctx, {
    type: '<?php echo $report_type === 'product_sales' ? 'bar' : 'line'; ?>',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: '<?php 
                if ($report_type === 'daily_sales') echo 'Doanh thu (₫)';
                elseif ($report_type === 'category_sales') echo 'Doanh thu (₫)';
                elseif ($report_type === 'product_sales') echo 'Số lượng bán';
                else echo 'Giá trị';
            ?>',
            data: <?php echo json_encode($chart_values); ?>,
            backgroundColor: '<?php echo $report_type === 'product_sales' ? 'rgba(54, 162, 235, 0.5)' : 'rgba(75, 192, 192, 0.2)'; ?>',
            borderColor: '<?php echo $report_type === 'product_sales' ? 'rgba(54, 162, 235, 1)' : 'rgba(75, 192, 192, 1)'; ?>',
            borderWidth: 2,
            fill: <?php echo $report_type === 'product_sales' ? 'false' : 'true'; ?>
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: '<?php 
                    if ($report_type === 'daily_sales') echo 'Doanh thu theo ngày';
                    elseif ($report_type === 'category_sales') echo 'Doanh thu theo danh mục';
                    elseif ($report_type === 'product_sales') echo 'Top 10 sản phẩm bán chạy';
                    else echo 'Báo cáo';
                ?>'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        <?php if ($report_type !== 'product_sales'): ?>
                        return new Intl.NumberFormat('vi-VN', { 
                            style: 'currency', 
                            currency: 'VND',
                            minimumFractionDigits: 0 
                        }).format(value);
                        <?php else: ?>
                        return value;
                        <?php endif; ?>
                    }
                }
            }
        }
    }
});
<?php endif; ?>

function exportReport() {
    // Chuyển đến trang export
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'export-report.php?' + params.toString();
}
</script>

<?php include 'includes/footer.php'; ?>