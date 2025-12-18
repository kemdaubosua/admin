<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Quản lý Tồn kho';
$active_page = 'inventory';

// Xử lý thao tác
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $san_pham_id = $_POST['san_pham_id'];
        $kich_co_id = $_POST['kich_co_id'] ?: null;
        $mau_sac_id = $_POST['mau_sac_id'] ?: null;
        $ma_sku = sanitize($_POST['ma_sku']);
        $gia_ban = floatval($_POST['gia_ban']);
        $so_luong_ton = intval($_POST['so_luong_ton']);
        $trang_thai = $_POST['trang_thai'];
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO sku_san_pham (san_pham_id, ma_sku, kich_co_id, mau_sac_id, gia_ban, so_luong_ton, trang_thai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$san_pham_id, $ma_sku, $kich_co_id, $mau_sac_id, $gia_ban, $so_luong_ton, $trang_thai]);
                showMessage('Thêm SKU thành công!');
            } else {
                $stmt = $pdo->prepare("UPDATE sku_san_pham SET kich_co_id = ?, mau_sac_id = ?, gia_ban = ?, so_luong_ton = ?, trang_thai = ? WHERE id = ?");
                $stmt->execute([$kich_co_id, $mau_sac_id, $gia_ban, $so_luong_ton, $trang_thai, $id]);
                showMessage('Cập nhật SKU thành công!');
            }
            redirect('inventory.php');
        } catch (PDOException $e) {
            showMessage('Lỗi: ' . $e->getMessage(), 'danger');
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM sku_san_pham WHERE id = ?");
            $stmt->execute([$id]);
            showMessage('Xóa SKU thành công!');
        } catch (PDOException $e) {
            showMessage('Không thể xóa SKU này!', 'danger');
        }
        redirect('inventory.php');
    }
}

// Lấy danh sách SKU
$search = $_GET['search'] ?? '';
$product_filter = $_GET['product'] ?? '';

$sql = "SELECT sku.*, sp.ten_san_pham, sp.anh_dai_dien_url, 
        kc.ten_kich_co, ms.ten_mau, ms.ma_mau
        FROM sku_san_pham sku 
        LEFT JOIN san_pham sp ON sku.san_pham_id = sp.id
        LEFT JOIN kich_co kc ON sku.kich_co_id = kc.id
        LEFT JOIN mau_sac ms ON sku.mau_sac_id = ms.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (sku.ma_sku LIKE ? OR sp.ten_san_pham LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($product_filter) {
    $sql .= " AND sku.san_pham_id = ?";
    $params[] = $product_filter;
}

$sql .= " ORDER BY sp.ten_san_pham, kc.thu_tu, ms.ten_mau";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$skus = $stmt->fetchAll();

// Lấy danh sách để dùng trong form
$products = $pdo->query("SELECT id, ten_san_pham FROM san_pham WHERE trang_thai = 'DANG_BAN' ORDER BY ten_san_pham")->fetchAll();
$sizes = $pdo->query("SELECT * FROM kich_co WHERE trang_thai = 'HOAT_DONG' ORDER BY thu_tu")->fetchAll();
$colors = $pdo->query("SELECT * FROM mau_sac WHERE trang_thai = 'HOAT_DONG' ORDER BY ten_mau")->fetchAll();

// Thống kê
$total_sku = count($skus);
$low_stock = 0;
$out_of_stock = 0;
foreach ($skus as $sku) {
    if ($sku['so_luong_ton'] == 0) $out_of_stock++;
    elseif ($sku['so_luong_ton'] <= 5) $low_stock++;
}

include 'includes/header.php';
?>

<!-- Modal -->
<div class="modal fade" id="skuModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalTitle">Thêm SKU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body px-4 pb-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="skuId">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary">Sản phẩm <span class="text-danger">*</span></label>
                            <select class="form-select" name="san_pham_id" id="productSelect" required>
                                <option value="">-- Chọn sản phẩm --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo $p['ten_san_pham']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary">Mã SKU <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" name="ma_sku" id="skuCode" required placeholder="SP19-M-DEN">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">Kích cỡ</label>
                            <select class="form-select" name="kich_co_id" id="sizeSelect">
                                <option value="">-- Không có --</option>
                                <?php foreach ($sizes as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo $s['ten_kich_co']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">Màu sắc</label>
                            <select class="form-select" name="mau_sac_id" id="colorSelect">
                                <option value="">-- Không có --</option>
                                <?php foreach ($colors as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-color="<?php echo $c['ma_mau']; ?>">
                                        <?php echo $c['ten_mau']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">Giá bán <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="gia_ban" id="skuPrice" required min="0" step="1000">
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">Số lượng tồn <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="so_luong_ton" id="skuStock" required min="0">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary">Trạng thái</label>
                            <select class="form-select" name="trang_thai" id="skuStatus">
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
            <h2 class="fw-bold text-dark mb-1">Quản lý Tồn kho (SKU)</h2>
            <p class="text-secondary small mb-0">Quản lý biến thể sản phẩm theo size và màu</p>
        </div>
        <button onclick="openModal()" class="btn btn-dark-custom d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i> Thêm SKU
        </button>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Tổng SKU</div>
                    <h4 class="fw-bold mb-0"><?php echo $total_sku; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-warning border-4">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Sắp hết hàng (≤5)</div>
                    <h4 class="fw-bold mb-0 text-warning"><?php echo $low_stock; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 border-start border-danger border-4">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Hết hàng</div>
                    <h4 class="fw-bold mb-0 text-danger"><?php echo $out_of_stock; ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo mã SKU hoặc tên sản phẩm..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-5">
                    <select name="product" class="form-select">
                        <option value="">Tất cả sản phẩm</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo $p['ten_san_pham']; ?>
                            </option>
                        <?php endforeach; ?>
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
                        <th class="ps-4">Mã SKU</th>
                        <th>Sản phẩm</th>
                        <th>Size</th>
                        <th>Màu</th>
                        <th>Giá</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($skus)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-5">
                                <i class="fas fa-warehouse fa-3x mb-3 d-block"></i>
                                Chưa có SKU nào
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($skus as $sku): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border font-monospace"><?php echo $sku['ma_sku']; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo $sku['anh_dai_dien_url'] ?: 'https://placehold.co/40x40'; ?>" 
                                             class="rounded" width="40" height="40" style="object-fit: cover;">
                                        <div class="fw-bold small"><?php echo $sku['ten_san_pham']; ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($sku['ten_kich_co']): ?>
                                        <span class="badge bg-primary-subtle text-primary"><?php echo $sku['ten_kich_co']; ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sku['ten_mau']): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($sku['ma_mau']): ?>
                                                <div class="rounded-circle border" style="width: 20px; height: 20px; background-color: <?php echo $sku['ma_mau']; ?>"></div>
                                            <?php endif; ?>
                                            <span class="small"><?php echo $sku['ten_mau']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?php echo formatPrice($sku['gia_ban']); ?></td>
                                <td>
                                    <?php if ($sku['so_luong_ton'] > 10): ?>
                                        <span class="badge bg-success-subtle text-success"><?php echo $sku['so_luong_ton']; ?></span>
                                    <?php elseif ($sku['so_luong_ton'] > 0): ?>
                                        <span class="badge bg-warning-subtle text-warning"><?php echo $sku['so_luong_ton']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getStatusBadge($sku['trang_thai'], 'product'); ?></td>
                                <td class="text-end pe-4">
                                    <button onclick='editSKU(<?php echo json_encode($sku); ?>)' class="btn btn-sm btn-light text-primary border me-1">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete()">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $sku['id']; ?>">
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
let skuModal;

document.addEventListener('DOMContentLoaded', function() {
    skuModal = new bootstrap.Modal(document.getElementById('skuModal'));
});

function openModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').innerText = 'Thêm SKU';
    document.getElementById('skuId').value = '';
    document.getElementById('productSelect').value = '';
    document.getElementById('productSelect').disabled = false;
    document.getElementById('skuCode').value = '';
    document.getElementById('sizeSelect').value = '';
    document.getElementById('colorSelect').value = '';
    document.getElementById('skuPrice').value = '';
    document.getElementById('skuStock').value = '';
    document.getElementById('skuStatus').value = 'DANG_BAN';
    skuModal.show();
}

function editSKU(sku) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').innerText = 'Chỉnh sửa SKU';
    document.getElementById('skuId').value = sku.id;
    document.getElementById('productSelect').value = sku.san_pham_id;
    document.getElementById('productSelect').disabled = true;
    document.getElementById('skuCode').value = sku.ma_sku;
    document.getElementById('skuCode').setAttribute('readonly', true);
    document.getElementById('sizeSelect').value = sku.kich_co_id || '';
    document.getElementById('colorSelect').value = sku.mau_sac_id || '';
    document.getElementById('skuPrice').value = sku.gia_ban;
    document.getElementById('skuStock').value = sku.so_luong_ton;
    document.getElementById('skuStatus').value = sku.trang_thai;
    skuModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>