<?php
// /admin/products.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý sản phẩm';
$conn = getDBConnection();

// Xử lý thay đổi trạng thái sản phẩm
if (isset($_GET['change_status'])) {
    $product_id = intval($_GET['id']);
    $status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE san_pham SET trang_thai = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $product_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Đã cập nhật trạng thái sản phẩm';
    } else {
        $_SESSION['error'] = 'Lỗi khi cập nhật trạng thái';
    }
    
    $stmt->close();
    header('Location: products.php');
    exit();
}

// Phân trang
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];
$types = '';

if ($search) {
    $where_conditions[] = "sp.ten_san_pham LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

if ($category && $category != 'all') {
    $where_conditions[] = "sp.danh_muc_id = ?";
    $params[] = $category;
    $types .= 'i';
}

if ($status_filter && $status_filter != 'all') {
    $where_conditions[] = "sp.trang_thai = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Lấy tổng số sản phẩm
$count_sql = "SELECT COUNT(*) as total FROM san_pham sp $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_products = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $per_page);
$count_stmt->close();

// Lấy danh sách sản phẩm
$sql = "
    SELECT sp.*, dm.ten_danh_muc 
    FROM san_pham sp
    LEFT JOIN danh_muc_san_pham dm ON sp.danh_muc_id = dm.id
    $where_sql
    ORDER BY sp.id DESC 
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
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lấy danh mục cho filter
$categories = $conn->query("SELECT id, ten_danh_muc FROM danh_muc_san_pham WHERE trang_thai = 'HOAT_DONG'")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Danh sách sản phẩm</h5>
        <div>
            <a href="product-detail.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm sản phẩm
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm sản phẩm..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="category">
                    <option value="all">Tất cả danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['ten_danh_muc']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="DANG_BAN" <?php echo $status_filter == 'DANG_BAN' ? 'selected' : ''; ?>>Đang bán</option>
                    <option value="NGUNG_BAN" <?php echo $status_filter == 'NGUNG_BAN' ? 'selected' : ''; ?>>Ngừng bán</option>
                    <option value="DA_GO" <?php echo $status_filter == 'DA_GO' ? 'selected' : ''; ?>>Đã gỡ</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
            <div class="col-md-2">
                <a href="products.php" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá bán</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td>
                            <?php if ($product['anh_dai_dien_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['anh_dai_dien_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['ten_san_pham']); ?>"
                                 class="product-img">
                            <?php else: ?>
                            <div class="product-img bg-light d-flex align-items-center justify-content-center">
                                <i class="fas fa-image text-muted"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['ten_san_pham']); ?></strong><br>
                            <small class="text-muted">/<?php echo htmlspecialchars($product['duong_dan']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($product['ten_danh_muc'] ?? 'N/A'); ?></td>
                        <td><?php echo formatCurrency($product['gia_ban']); ?></td>
                        <td>
                            <span class="badge <?php echo $product['so_luong_ton'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $product['so_luong_ton']; ?>
                            </span>
                        </td>
                        <td><?php echo displayProductStatus($product['trang_thai']); ?></td>
                        <td><?php echo formatDate($product['tao_luc']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-edit"></i> Sửa
                                </a>
                                
                                <?php if ($product['trang_thai'] == 'DANG_BAN'): ?>
                                <a href="products.php?change_status&id=<?php echo $product['id']; ?>&status=NGUNG_BAN" 
                                   class="btn btn-warning" onclick="return confirm('Ngừng bán sản phẩm này?')">
                                    <i class="fas fa-pause"></i>
                                </a>
                                <?php elseif ($product['trang_thai'] == 'NGUNG_BAN'): ?>
                                <a href="products.php?change_status&id=<?php echo $product['id']; ?>&status=DANG_BAN" 
                                   class="btn btn-success" onclick="return confirm('Tiếp tục bán sản phẩm này?')">
                                    <i class="fas fa-play"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="product-process.php?action=delete&id=<?php echo $product['id']; ?>" 
                                   class="btn btn-danger" onclick="return confirm('Xóa sản phẩm này?')">
                                    <i class="fas fa-trash"></i>
                                </a>
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
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status_filter; ?>">Trước</a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status_filter; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status_filter; ?>">Sau</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>