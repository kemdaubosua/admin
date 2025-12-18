<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Quản lý Đơn hàng';
$active_page = 'orders';

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $order_id = $_POST['order_id'];
        $new_status = $_POST['new_status'];
        
        try {
            // Lấy trạng thái cũ
            $stmt = $pdo->prepare("SELECT trang_thai FROM don_hang WHERE id = ?");
            $stmt->execute([$order_id]);
            $old_status = $stmt->fetchColumn();
            
            // Cập nhật trạng thái
            $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            // Lưu lịch sử
            $stmt = $pdo->prepare("INSERT INTO lich_su_trang_thai_don_hang (don_hang_id, tu_trang_thai, den_trang_thai, nguoi_thay_doi_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $old_status, $new_status, $_SESSION['admin_id']]);
            
            showMessage('Cập nhật trạng thái đơn hàng thành công!');
        } catch (PDOException $e) {
            showMessage('Lỗi: ' . $e->getMessage(), 'danger');
        }
        redirect('orders.php');
    }
}

// Lấy danh sách đơn hàng
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';

$sql = "SELECT dh.*, nd.ho_ten, nd.email 
        FROM don_hang dh 
        LEFT JOIN nguoi_dung nd ON dh.nguoi_dung_id = nd.id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (dh.ma_don_hang LIKE ? OR dh.nguoi_nhan LIKE ? OR dh.sdt_nguoi_nhan LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($status_filter) {
    $sql .= " AND dh.trang_thai = ?";
    $params[] = $status_filter;
}

if ($payment_filter) {
    $sql .= " AND dh.trang_thai_thanh_toan = ?";
    $params[] = $payment_filter;
}

$sql .= " ORDER BY dh.tao_luc DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Thống kê đơn hàng
$stats = [
    'total' => count($orders),
    'cho_xu_ly' => 0,
    'dang_xu_ly' => 0,
    'hoan_tat' => 0,
    'huy' => 0
];

foreach ($orders as $order) {
    $stats[strtolower($order['trang_thai'])]++;
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

    <div class="mb-4">
        <h2 class="fw-bold text-dark mb-1">Quản lý Đơn hàng</h2>
        <p class="text-secondary small mb-0">Theo dõi và xử lý đơn hàng</p>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Tổng đơn</div>
                    <h4 class="fw-bold mb-0"><?php echo $stats['total']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-info border-4">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Chờ xử lý</div>
                    <h4 class="fw-bold mb-0 text-info"><?php echo $stats['cho_xu_ly']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-primary border-4">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Đang xử lý</div>
                    <h4 class="fw-bold mb-0 text-primary"><?php echo $stats['dang_xu_ly']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Hoàn tất</div>
                    <h4 class="fw-bold mb-0 text-success"><?php echo $stats['hoan_tat']; ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo mã đơn, người nhận, SĐT..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="CHO_XU_LY" <?php echo $status_filter === 'CHO_XU_LY' ? 'selected' : ''; ?>>Chờ xử lý</option>
                        <option value="DANG_XU_LY" <?php echo $status_filter === 'DANG_XU_LY' ? 'selected' : ''; ?>>Đang xử lý</option>
                        <option value="HOAN_TAT" <?php echo $status_filter === 'HOAN_TAT' ? 'selected' : ''; ?>>Hoàn tất</option>
                        <option value="HUY" <?php echo $status_filter === 'HUY' ? 'selected' : ''; ?>>Đã hủy</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="payment" class="form-select">
                        <option value="">Thanh toán</option>
                        <option value="CHUA_THANH_TOAN" <?php echo $payment_filter === 'CHUA_THANH_TOAN' ? 'selected' : ''; ?>>Chưa TT</option>
                        <option value="DA_THANH_TOAN" <?php echo $payment_filter === 'DA_THANH_TOAN' ? 'selected' : ''; ?>>Đã TT</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Lọc</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Mã đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Thanh toán</th>
                        <th>Trạng thái</th>
                        <th>Ngày đặt</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">
                                <i class="fas fa-shopping-cart fa-3x mb-3 d-block"></i>
                                Chưa có đơn hàng nào
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold font-monospace text-primary"><?php echo $order['ma_don_hang']; ?></div>
                                    <div class="text-secondary small"><?php echo $order['phuong_thuc_thanh_toan']; ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $order['nguoi_nhan']; ?></div>
                                    <div class="text-secondary small"><?php echo $order['sdt_nguoi_nhan']; ?></div>
                                </td>
                                <td class="fw-bold text-dark"><?php echo formatPrice($order['tong_tien']); ?></td>
                                <td>
                                    <?php if ($order['trang_thai_thanh_toan'] === 'DA_THANH_TOAN'): ?>
                                        <span class="badge bg-success-subtle text-success">Đã thanh toán</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning">Chưa thanh toán</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <?php echo getStatusBadge($order['trang_thai'], 'order'); ?>
                                        <button class="btn btn-sm border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        </button>
                                        <ul class="dropdown-menu shadow border-0">
                                            <li><h6 class="dropdown-header">Cập nhật trạng thái</h6></li>
                                            <?php if ($order['trang_thai'] !== 'CHO_XU_LY'): ?>
                                            <li>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="new_status" value="CHO_XU_LY">
                                                    <button type="submit" class="dropdown-item">Chờ xử lý</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($order['trang_thai'] !== 'DANG_XU_LY'): ?>
                                            <li>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="new_status" value="DANG_XU_LY">
                                                    <button type="submit" class="dropdown-item">Đang xử lý</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($order['trang_thai'] !== 'HOAN_TAT'): ?>
                                            <li>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="new_status" value="HOAN_TAT">
                                                    <button type="submit" class="dropdown-item text-success">Hoàn tất</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($order['trang_thai'] !== 'HUY'): ?>
                                            <li>
                                                <form method="POST" action="" onsubmit="return confirm('Xác nhận hủy đơn hàng này?')">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="new_status" value="HUY">
                                                    <button type="submit" class="dropdown-item text-danger">Hủy đơn</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                                <td><?php echo formatDateTime($order['tao_luc']); ?></td>
                                <td class="text-end pe-4">
                                    <a href="orders_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light text-primary border">
                                        <i class="fas fa-eye"></i> Chi tiết
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php include 'includes/footer.php'; ?>