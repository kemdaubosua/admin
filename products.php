<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Quản lý Sản phẩm';
$active_page = 'products';

// Xử lý các thao tác
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $ten_san_pham = sanitize($_POST['ten_san_pham']);
        $duong_dan = createSlug($_POST['duong_dan'] ?: $ten_san_pham);
        $danh_muc_id = $_POST['danh_muc_id'] ?: null;
        $mo_ta = sanitize($_POST['mo_ta']);
        $gia_ban = floatval($_POST['gia_ban']);
        $so_luong_ton = intval($_POST['so_luong_ton']);
        $trang_thai = $_POST['trang_thai'];
        $anh_dai_dien_url = sanitize($_POST['anh_dai_dien_url']);
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO san_pham (danh_muc_id, ten_san_pham, duong_dan, mo_ta, gia_ban, so_luong_ton, trang_thai, anh_dai_dien_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$danh_muc_id, $ten_san_pham, $duong_dan, $mo_ta, $gia_ban, $so_luong_ton, $trang_thai, $anh_dai_dien_url]);
                showMessage('Thêm sản phẩm thành công!');
            } else {
                $stmt = $pdo->prepare("UPDATE san_pham SET danh_muc_id = ?, ten_san_pham = ?, duong_dan = ?, mo_ta = ?, gia_ban = ?, so_luong_ton = ?, trang_thai = ?, anh_dai_dien_url = ? WHERE id = ?");
                $stmt->execute([$danh_muc_id, $ten_san_pham, $duong_dan, $mo_ta, $gia_ban, $so_luong_ton, $trang_thai, $anh_dai_dien_url, $id]);
                showMessage('Cập nhật sản phẩm thành công!');
            }
            redirect('products.php');
        } catch (PDOException $e) {
            showMessage('Lỗi: ' . $e->getMessage(), 'danger');
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM san_pham WHERE id = ?");
            $stmt->execute([$id]);
            showMessage('Xóa sản phẩm thành công!');
        } catch (PDOException $e) {
            showMessage('Không thể xóa sản phẩm này!', 'danger');
        }
        redirect('products.php');
    }
}

// Lấy danh sách sản phẩm
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT sp.*, dm.ten_danh_muc 
        FROM san_pham sp 
        LEFT JOIN danh_muc_san_pham dm ON sp.danh_muc_id = dm.id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND sp.ten_san_pham LIKE ?";
    $params[] = "%$search%";
}

if ($category_filter) {
    $sql .= " AND sp.danh_muc_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $sql .= " AND sp.trang_thai = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY sp.tao_luc DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Lấy danh mục để show trong filter và form
$categories = $pdo->query("SELECT * FROM danh_muc_san_pham WHERE trang_thai = 'HOAT_DONG' ORDER BY ten_danh_muc")->fetchAll();

include 'includes/header.php';
?>

<!-- Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalTitle">Thêm sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body px-4 pb-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="productId">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-secondary">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ten_san_pham" id="productName" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-secondary">Danh mục</label>
                            <select class="form-select" name="danh_muc_id" id="productCategory">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['ten_danh_muc']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary">Đường dẫn (slug)</label>
                            <input type="text" class="form-control" name="duong_dan" id="productSlug" placeholder="Tự động tạo nếu để trống">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary">Mô tả</label>
                            <textarea class="form-control" name="mo_ta" id="productDesc" rows="3"></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">Giá bán <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="gia_ban" id="productPrice" required min="0" step="1000">
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">Số lượng tồn <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="so_luong_ton" id="productStock" required min="0">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary">URL ảnh đại diện</label>
                            <input type="text" class="form-control" name="anh_dai_dien_url" id="productImage" placeholder="https://example.com/image.jpg">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary">Trạng thái</label>
                            <select class="form-select" name="trang_thai" id="productStatus">
                                <option value="DANG_BAN">Đang bán</option>
                                <option value="NGUNG_BAN">Ngừng bán</option>
                                <option value="DA_GO">Đã gỡ</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light flex-grow-1 rounded-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-dark-custom flex-grow-1">Lưu lại</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
            <h2 class="fw-bold text-dark mb-1">Quản lý Sản phẩm</h2>
            <p class="text-secondary small mb-0">Quản lý thông tin và tồn kho sản phẩm</p>
        </div>
        <button onclick="openModal()" class="btn btn-dark-custom d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i> Thêm sản phẩm
        </button>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Tìm kiếm sản phẩm..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['ten_danh_muc']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="DANG_BAN" <?php echo $status_filter === 'DANG_BAN' ? 'selected' : ''; ?>>Đang bán</option>
                        <option value="NGUNG_BAN" <?php echo $status_filter === 'NGUNG_BAN' ? 'selected' : ''; ?>>Ngừng bán</option>
                        <option value="DA_GO" <?php echo $status_filter === 'DA_GO' ? 'selected' : ''; ?>>Đã gỡ</option>
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
                        <th class="ps-4">Sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá bán</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-5">
                                <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
                                Chưa có sản phẩm nào
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo $product['anh_dai_dien_url'] ?: 'https://placehold.co/80x80?text=No+Image'; ?>" 
                                             class="rounded-3 border" width="60" height="60" 
                                             style="object-fit: cover;" alt="<?php echo $product['ten_san_pham']; ?>">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo $product['ten_san_pham']; ?></div>
                                            <div class="text-secondary small">#<?php echo $product['id']; ?> • <?php echo $product['duong_dan']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($product['ten_danh_muc']): ?>
                                        <span class="badge bg-light text-primary border"><?php echo $product['ten_danh_muc']; ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary small">Chưa phân loại</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-primary"><?php echo formatPrice($product['gia_ban']); ?></td>
                                <td>
                                    <?php if ($product['so_luong_ton'] > 10): ?>
                                        <span class="badge bg-success-subtle text-success"><?php echo $product['so_luong_ton']; ?> sp</span>
                                    <?php elseif ($product['so_luong_ton'] > 0): ?>
                                        <span class="badge bg-warning-subtle text-warning"><?php echo $product['so_luong_ton']; ?> sp</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Hết hàng</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getStatusBadge($product['trang_thai'], 'product'); ?></td>
                                <td class="text-end pe-4">
                                    <button onclick='editProduct(<?php echo json_encode($product); ?>)' class="btn btn-sm btn-light text-primary border me-1">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete()">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-danger border">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
let productModal;

document.addEventListener('DOMContentLoaded', function() {
    productModal = new bootstrap.Modal(document.getElementById('productModal'));
});

function openModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').innerText = 'Thêm sản phẩm';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productSlug').value = '';
    document.getElementById('productCategory').value = '';
    document.getElementById('productDesc').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productStock').value = '';
    document.getElementById('productImage').value = '';
    document.getElementById('productStatus').value = 'DANG_BAN';
    productModal.show();
}

function editProduct(product) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').innerText = 'Chỉnh sửa sản phẩm';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.ten_san_pham;
    document.getElementById('productSlug').value = product.duong_dan;
    document.getElementById('productCategory').value = product.danh_muc_id || '';
    document.getElementById('productDesc').value = product.mo_ta || '';
    document.getElementById('productPrice').value = product.gia_ban;
    document.getElementById('productStock').value = product.so_luong_ton;
    document.getElementById('productImage').value = product.anh_dai_dien_url || '';
    document.getElementById('productStatus').value = product.trang_thai;
    productModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>