<?php
// /admin/orders.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý đơn hàng';
$conn = getDBConnection();

// Xử lý thay đổi trạng thái đơn hàng
if (isset($_GET['change_status'])) {
    $order_id = intval($_GET['id']);
    $new_status = $_GET['status'];
    $old_status = $_GET['old_status'] ?? '';
    
    // Lưu lịch sử trạng thái
    $admin_id = $_SESSION['admin_id'];
    $stmt = $conn->prepare("INSERT INTO lich_su_trang_thai_don_hang (don_hang_id, tu_trang_thai, den_trang_thai, nguoi_thay_doi_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $order_id, $old_status, $new_status, $admin_id);
    $stmt->execute();
    $stmt->close();
    
    // Cập nhật trạng thái đơn hàng
    $stmt = $conn->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Đã cập nhật trạng thái đơn hàng';
    } else {
        $_SESSION['error'] = 'Lỗi khi cập nhật trạng thái';
    }
    
    $stmt->close();
    header('Location: orders.php');
    exit();
}

// Phân trang và lọc
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];
$types = '';

if ($status_filter && $status_filter != 'all') {
    $where_conditions[] = "d.trang_thai = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(d.ma_don_hang LIKE ? OR n.ho_ten LIKE ? OR d.sdt_nguoi_nhan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if ($date_from) {
    $where_conditions[] = "DATE(d.tao_luc) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(d.tao_luc) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Lấy tổng số đơn hàng
$count_sql = "SELECT COUNT(*) as total FROM don_hang d LEFT JOIN nguoi_dung n ON d.nguoi_dung_id = n.id $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_orders = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);
$count_stmt->close();

// Lấy danh sách đơn hàng
$sql = "
    SELECT d.*, n.ho_ten, n.email 
    FROM don_hang d 
    LEFT JOIN nguoi_dung n ON d.nguoi_dung_id = n.id
    $where_sql
    ORDER BY d.tao_luc DESC 
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Tính tổng doanh thu
$revenue_result = $conn->query("SELECT SUM(tong_tien) as total_revenue FROM don_hang WHERE trang_thai = 'HOAN_TAT'");
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'];

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Quản lý đơn hàng</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Mã đơn, tên, SĐT..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="CHO_XU_LY" <?php echo $status_filter == 'CHO_XU_LY' ? 'selected' : ''; ?>>Chờ xử lý</option>
                    <option value="DANG_XU_LY" <?php echo $status_filter == 'DANG_XU_LY' ? 'selected' : ''; ?>>Đang xử lý</option>
                    <option value="HOAN_TAT" <?php echo $status_filter == 'HOAN_TAT' ? 'selected' : ''; ?>>Hoàn tất</option>
                    <option value="HUY" <?php echo $status_filter == 'HUY' ? 'selected' : ''; ?>>Đã hủy</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
            <div class="col-md-1">
                <a href="orders.php" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>

        <!-- Thống kê -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Tổng đơn hàng</h6>
                        <h4 class="mb-0"><?php echo $total_orders; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Đơn hoàn tất</h6>
                        <?php 
                        $conn_temp = getDBConnection();
                        $completed = $conn_temp->query("SELECT COUNT(*) as count FROM don_hang WHERE trang_thai = 'HOAN_TAT'")->fetch_assoc()['count'];
                        closeDBConnection($conn_temp);
                        ?>
                        <h4 class="mb-0"><?php echo $completed; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body py-3">
                        <h6 class="card-title">Đơn chờ xử lý</h6>
                        <?php 
                        $conn_temp = getDBConnection();
                        $pending = $conn_temp->query("SELECT COUNT(*) as count FROM don_hang WHERE trang_thai = 'CHO_XU_LY'")->fetch_assoc()['count'];
                        closeDBConnection($conn_temp);
                        ?>
                        <h4 class="mb-0"><?php echo $pending; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Tổng doanh thu</h6>
                        <h4 class="mb-0"><?php echo formatCurrency($total_revenue); ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Người nhận</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Thanh toán</th>
                        <th>Ngày đặt</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <strong><?php echo $order['ma_don_hang']; ?></strong>
                        </td>
                        <td>
                            <?php if ($order['ho_ten']): ?>
                            <div><?php echo htmlspecialchars($order['ho_ten']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                            <?php else: ?>
                            <span class="text-muted">Khách vãng lai</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($order['nguoi_nhan']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($order['sdt_nguoi_nhan']); ?></small>
                        </td>
                        <td><?php echo formatCurrency($order['tong_tien']); ?></td>
                        <td><?php echo displayOrderStatus($order['trang_thai']); ?></td>
                        <td>
                            <?php if ($order['trang_thai_thanh_toan'] == 'DA_THANH_TOAN'): ?>
                            <span class="badge bg-success">Đã thanh toán</span>
                            <?php else: ?>
                            <span class="badge bg-warning">Chưa thanh toán</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($order['tao_luc']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Dropdown thay đổi trạng thái -->
                                <div class="btn-group">
                                    <button type="button" class="btn btn-warning dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($order['trang_thai'] != 'CHO_XU_LY'): ?>
                                        <li>
                                            <a class="dropdown-item" href="orders.php?change_status&id=<?php echo $order['id']; ?>&status=CHO_XU_LY&old_status=<?php echo $order['trang_thai']; ?>">
                                                Chuyển về Chờ xử lý
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['trang_thai'] != 'DANG_XU_LY'): ?>
                                        <li>
                                            <a class="dropdown-item" href="orders.php?change_status&id=<?php echo $order['id']; ?>&status=DANG_XU_LY&old_status=<?php echo $order['trang_thai']; ?>">
                                                Đang xử lý
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['trang_thai'] != 'HOAN_TAT'): ?>
                                        <li>
                                            <a class="dropdown-item" href="orders.php?change_status&id=<?php echo $order['id']; ?>&status=HOAN_TAT&old_status=<?php echo $order['trang_thai']; ?>">
                                                Hoàn tất
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['trang_thai'] != 'HUY'): ?>
                                        <li>
                                            <a class="dropdown-item text-danger" href="orders.php?change_status&id=<?php echo $order['id']; ?>&status=HUY&old_status=<?php echo $order['trang_thai']; ?>" onclick="return confirm('Hủy đơn hàng này?')">
                                                Hủy đơn
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Phân trang -->
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Trước</a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Sau</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>