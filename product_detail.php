<?php
// /admin/product-detail.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Chi tiết sản phẩm';
$conn = getDBConnection();

$action = $_GET['action'] ?? 'edit';
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $ten_san_pham = $conn->real_escape_string($_POST['ten_san_pham']);
    $duong_dan = $conn->real_escape_string($_POST['duong_dan']);
    $mo_ta = $conn->real_escape_string($_POST['mo_ta'] ?? '');
    $gia_ban = floatval($_POST['gia_ban']);
    $danh_muc_id = intval($_POST['danh_muc_id']);
    $so_luong_ton = intval($_POST['so_luong_ton']);
    $trang_thai = $_POST['trang_thai'];
    $anh_dai_dien_url = $conn->real_escape_string($_POST['anh_dai_dien_url'] ?? '');
    
    if ($action === 'add') {
        // Thêm sản phẩm mới
        $stmt = $conn->prepare("INSERT INTO san_pham (ten_san_pham, duong_dan, mo_ta, gia_ban, danh_muc_id, so_luong_ton, trang_thai, anh_dai_dien_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdiiis", $ten_san_pham, $duong_dan, $mo_ta, $gia_ban, $danh_muc_id, $so_luong_ton, $trang_thai, $anh_dai_dien_url);
        
        if ($stmt->execute()) {
            $product_id = $stmt->insert_id;
            $_SESSION['success'] = 'Thêm sản phẩm thành công';
            
            // Xử lý upload ảnh
            if (isset($_FILES['anh_dai_dien']) && $_FILES['anh_dai_dien']['error'] === 0) {
                $upload_result = uploadImage($_FILES['anh_dai_dien'], $product_id);
                if ($upload_result['success']) {
                    $conn->query("UPDATE san_pham SET anh_dai_dien_url = '{$upload_result['url']}' WHERE id = $product_id");
                }
            }
            
            // Thêm ảnh sản phẩm
            if (isset($_FILES['anh_san_pham'])) {
                $anh_files = $_FILES['anh_san_pham'];
                $thu_tu = 1;
                
                for ($i = 0; $i < count($anh_files['name']); $i++) {
                    if ($anh_files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $anh_files['name'][$i],
                            'type' => $anh_files['type'][$i],
                            'tmp_name' => $anh_files['tmp_name'][$i],
                            'error' => $anh_files['error'][$i],
                            'size' => $anh_files['size'][$i]
                        ];
                        
                        $upload_result = uploadImage($file, $product_id);
                        if ($upload_result['success']) {
                            $conn->query("INSERT INTO anh_san_pham (san_pham_id, url_anh, thu_tu_hien_thi) VALUES ($product_id, '{$upload_result['url']}', $thu_tu)");
                            $thu_tu++;
                        }
                    }
                }
            }
            
            header("Location: product-detail.php?id=$product_id");
            exit();
        } else {
            $_SESSION['error'] = 'Lỗi khi thêm sản phẩm';
        }
        $stmt->close();
        
    } elseif ($action === 'update') {
        // Cập nhật sản phẩm
        $product_id = intval($_POST['id']);
        
        $stmt = $conn->prepare("UPDATE san_pham SET ten_san_pham = ?, duong_dan = ?, mo_ta = ?, gia_ban = ?, danh_muc_id = ?, so_luong_ton = ?, trang_thai = ?, anh_dai_dien_url = ? WHERE id = ?");
        $stmt->bind_param("sssdiiisi", $ten_san_pham, $duong_dan, $mo_ta, $gia_ban, $danh_muc_id, $so_luong_ton, $trang_thai, $anh_dai_dien_url, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Cập nhật sản phẩm thành công';
            
            // Xử lý upload ảnh đại diện
            if (isset($_FILES['anh_dai_dien']) && $_FILES['anh_dai_dien']['error'] === 0) {
                $upload_result = uploadImage($_FILES['anh_dai_dien'], $product_id);
                if ($upload_result['success']) {
                    $conn->query("UPDATE san_pham SET anh_dai_dien_url = '{$upload_result['url']}' WHERE id = $product_id");
                }
            }
            
            // Thêm ảnh sản phẩm mới
            if (isset($_FILES['anh_san_pham'])) {
                $anh_files = $_FILES['anh_san_pham'];
                
                // Lấy thứ tự hiện tại
                $result = $conn->query("SELECT MAX(thu_tu_hien_thi) as max_thu_tu FROM anh_san_pham WHERE san_pham_id = $product_id");
                $row = $result->fetch_assoc();
                $thu_tu = $row['max_thu_tu'] + 1;
                
                for ($i = 0; $i < count($anh_files['name']); $i++) {
                    if ($anh_files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $anh_files['name'][$i],
                            'type' => $anh_files['type'][$i],
                            'tmp_name' => $anh_files['tmp_name'][$i],
                            'error' => $anh_files['error'][$i],
                            'size' => $anh_files['size'][$i]
                        ];
                        
                        $upload_result = uploadImage($file, $product_id);
                        if ($upload_result['success']) {
                            $conn->query("INSERT INTO anh_san_pham (san_pham_id, url_anh, thu_tu_hien_thi) VALUES ($product_id, '{$upload_result['url']}', $thu_tu)");
                            $thu_tu++;
                        }
                    }
                }
            }
            
            header("Location: product-detail.php?id=$product_id");
            exit();
        } else {
            $_SESSION['error'] = 'Lỗi khi cập nhật sản phẩm';
        }
        $stmt->close();
    }
}

// Lấy thông tin sản phẩm nếu đang chỉnh sửa
$product = null;
$product_images = [];
$skus = [];
$categories = [];

if ($action === 'edit' && $product_id > 0) {
    // Lấy thông tin sản phẩm
    $result = $conn->query("SELECT * FROM san_pham WHERE id = $product_id");
    $product = $result->fetch_assoc();
    
    // Lấy ảnh sản phẩm
    $product_images = $conn->query("SELECT * FROM anh_san_pham WHERE san_pham_id = $product_id ORDER BY thu_tu_hien_thi")->fetch_all(MYSQLI_ASSOC);
    
    // Lấy SKUs
    $skus = $conn->query("
        SELECT sku.*, kc.ten_kich_co, ms.ten_mau 
        FROM sku_san_pham sku
        LEFT JOIN kich_co kc ON sku.kich_co_id = kc.id
        LEFT JOIN mau_sac ms ON sku.mau_sac_id = ms.id
        WHERE sku.san_pham_id = $product_id
        ORDER BY kc.thu_tu, ms.ten_mau
    ")->fetch_all(MYSQLI_ASSOC);
    
    $pageTitle = 'Chỉnh sửa: ' . $product['ten_san_pham'];
} elseif ($action === 'add') {
    $pageTitle = 'Thêm sản phẩm mới';
}

// Lấy danh sách danh mục, kích cỡ, màu sắc
$categories = $conn->query("SELECT id, ten_danh_muc FROM danh_muc_san_pham WHERE trang_thai = 'HOAT_DONG' ORDER BY ten_danh_muc")->fetch_all(MYSQLI_ASSOC);
$sizes = $conn->query("SELECT * FROM kich_co WHERE trang_thai = 'HOAT_DONG' ORDER BY thu_tu")->fetch_all(MYSQLI_ASSOC);
$colors = $conn->query("SELECT * FROM mau_sac WHERE trang_thai = 'HOAT_DONG' ORDER BY ten_mau")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo $pageTitle; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <?php if ($action === 'edit' && $product): ?>
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Tên sản phẩm *</label>
                                <input type="text" class="form-control" name="ten_san_pham" required
                                       value="<?php echo $product['ten_san_pham'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Đường dẫn *</label>
                                <input type="text" class="form-control" name="duong_dan" required
                                       value="<?php echo $product['duong_dan'] ?? ''; ?>">
                                <small class="text-muted">vd: ao-thun-basic</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3"><?php echo $product['mo_ta'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Danh mục *</label>
                                <select class="form-select" name="danh_muc_id" required>
                                    <option value="">Chọn danh mục</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                        <?php echo (isset($product['danh_muc_id']) && $product['danh_muc_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['ten_danh_muc']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Giá bán (₫) *</label>
                                <input type="number" class="form-control" name="gia_ban" required min="0" step="1000"
                                       value="<?php echo $product['gia_ban'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Tồn kho *</label>
                                <input type="number" class="form-control" name="so_luong_ton" required min="0"
                                       value="<?php echo $product['so_luong_ton'] ?? 0; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="trang_thai">
                                    <option value="DANG_BAN" <?php echo (isset($product['trang_thai']) && $product['trang_thai'] == 'DANG_BAN') ? 'selected' : ''; ?>>Đang bán</option>
                                    <option value="NGUNG_BAN" <?php echo (isset($product['trang_thai']) && $product['trang_thai'] == 'NGUNG_BAN') ? 'selected' : ''; ?>>Ngừng bán</option>
                                    <option value="DA_GO" <?php echo (isset($product['trang_thai']) && $product['trang_thai'] == 'DA_GO') ? 'selected' : ''; ?>>Đã gỡ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">URL ảnh đại diện</label>
                                <input type="text" class="form-control" name="anh_dai_dien_url"
                                       value="<?php echo $product['anh_dai_dien_url'] ?? ''; ?>">
                                <small class="text-muted">Hoặc upload file bên dưới</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Upload ảnh đại diện</label>
                        <input type="file" class="form-control" name="anh_dai_dien" accept="image/*">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Thêm ảnh sản phẩm</label>
                        <input type="file" class="form-control" name="anh_san_pham[]" multiple accept="image/*">
                        <small class="text-muted">Có thể chọn nhiều ảnh</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="products.php" class="btn btn-secondary">Quay lại</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'add' ? 'Thêm sản phẩm' : 'Cập nhật'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quản lý SKUs -->
        <?php if ($action === 'edit' && $product): ?>
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Quản lý SKU (Size & Color)</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSkuModal">
                    <i class="fas fa-plus"></i> Thêm SKU
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>SKU Code</th>
                                <th>Size</th>
                                <th>Color</th>
                                <th>Giá</th>
                                <th>Tồn kho</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($skus as $sku): ?>
                            <tr>
                                <td><code><?php echo $sku['ma_sku']; ?></code></td>
                                <td><?php echo $sku['ten_kich_co']; ?></td>
                                <td>
                                    <?php if ($sku['ma_mau']): ?>
                                    <span class="badge" style="background-color: <?php echo $sku['ma_mau']; ?>; color: <?php echo (hexdec(substr($sku['ma_mau'], 1)) > 0xffffff/2) ? 'black' : 'white'; ?>">
                                        <?php echo $sku['ten_mau']; ?>
                                    </span>
                                    <?php else: ?>
                                    <?php echo $sku['ten_mau']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($sku['gia_ban']); ?></td>
                                <td>
                                    <span class="badge <?php echo $sku['so_luong_ton'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $sku['so_luong_ton']; ?>
                                    </span>
                                </td>
                                <td><?php echo displayProductStatus($sku['trang_thai']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editSkuModal<?php echo $sku['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="sku-process.php?action=delete&id=<?php echo $sku['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa SKU này?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Modal edit SKU -->
                            <div class="modal fade" id="editSkuModal<?php echo $sku['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Chỉnh sửa SKU</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="sku-process.php">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?php echo $sku['id']; ?>">
                                            <input type="hidden" name="san_pham_id" value="<?php echo $product_id; ?>">
                                            
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Giá bán</label>
                                                    <input type="number" class="form-control" name="gia_ban" 
                                                           value="<?php echo $sku['gia_ban']; ?>" required min="0" step="1000">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Số lượng tồn</label>
                                                    <input type="number" class="form-control" name="so_luong_ton" 
                                                           value="<?php echo $sku['so_luong_ton']; ?>" required min="0">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Trạng thái</label>
                                                    <select class="form-select" name="trang_thai">
                                                        <option value="DANG_BAN" <?php echo $sku['trang_thai'] == 'DANG_BAN' ? 'selected' : ''; ?>>Đang bán</option>
                                                        <option value="NGUNG_BAN" <?php echo $sku['trang_thai'] == 'NGUNG_BAN' ? 'selected' : ''; ?>>Ngừng bán</option>
                                                        <option value="DA_GO" <?php echo $sku['trang_thai'] == 'DA_GO' ? 'selected' : ''; ?>>Đã gỡ</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                <button type="submit" class="btn btn-sm btn-primary">Cập nhật</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Xem trước ảnh đại diện -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Ảnh đại diện</h6>
            </div>
            <div class="card-body text-center">
                <?php if (isset($product['anh_dai_dien_url']) && $product['anh_dai_dien_url']): ?>
                <img src="<?php echo htmlspecialchars($product['anh_dai_dien_url']); ?>" 
                     alt="Ảnh đại diện" class="img-fluid rounded" style="max-height: 300px;">
                <?php else: ?>
                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px;">
                    <div class="text-center">
                        <i class="fas fa-image fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Chưa có ảnh</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Danh sách ảnh sản phẩm -->
        <?php if ($action === 'edit' && $product && count($product_images) > 0): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">Ảnh sản phẩm</h6>
                <small class="text-muted"><?php echo count($product_images); ?> ảnh</small>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($product_images as $image): ?>
                    <div class="col-4">
                        <div class="position-relative">
                            <img src="<?php echo htmlspecialchars($image['url_anh']); ?>" 
                                 alt="Product image" class="img-fluid rounded" style="height: 80px; object-fit: cover;">
                            <div class="position-absolute top-0 end-0">
                                <a href="product-process.php?delete_image=<?php echo $image['id']; ?>&product_id=<?php echo $product_id; ?>" 
                                   class="btn btn-sm btn-danger" onclick="return confirm('Xóa ảnh này?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal thêm SKU -->
<?php if ($action === 'edit' && $product): ?>
<div class="modal fade" id="addSkuModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm SKU mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="sku-process.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="san_pham_id" value="<?php echo $product_id; ?>">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kích cỡ *</label>
                                <select class="form-select" name="kich_co_id" required>
                                    <option value="">Chọn size</option>
                                    <?php foreach ($sizes as $size): ?>
                                    <option value="<?php echo $size['id']; ?>"><?php echo $size['ten_kich_co']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Màu sắc *</label>
                                <select class="form-select" name="mau_sac_id" required>
                                    <option value="">Chọn màu</option>
                                    <?php foreach ($colors as $color): ?>
                                    <option value="<?php echo $color['id']; ?>"><?php echo $color['ten_mau']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giá bán (₫) *</label>
                                <input type="number" class="form-control" name="gia_ban" required min="0" step="1000"
                                       value="<?php echo $product['gia_ban']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Số lượng tồn *</label>
                                <input type="number" class="form-control" name="so_luong_ton" required min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="DANG_BAN">Đang bán</option>
                            <option value="NGUNG_BAN">Ngừng bán</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>