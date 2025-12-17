<?php
// /admin/inventory.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý tồn kho';
$conn = getDBConnection();

// Xử lý cập nhật tồn kho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inventory'])) {
    $sku_id = intval($_POST['sku_id']);
    $so_luong_ton = intval($_POST['so_luong_ton']);
    
    $stmt = $conn->prepare("UPDATE sku_san_pham SET so_luong_ton = ? WHERE id = ?");
    $stmt->bind_param("ii", $so_luong_ton, $sku_id);
    
    if ($stmt->execute()) {
        // Cập nhật tổng tồn kho trong bảng sản phẩm
        $sku = $conn->query("SELECT san_pham_id FROM sku_san_pham WHERE id = $sku_id")->fetch_assoc();
        $product_id = $sku['san_pham_id'];
        
        $total_inventory = $conn->query("SELECT SUM(so_luong_ton) as total FROM sku_san_pham WHERE san_pham_id = $product_id AND trang_thai = 'DANG_BAN'")->fetch_assoc()['total'];
        $conn->query("UPDATE san_pham SET so_luong_ton = $total_inventory WHERE id = $product_id");
        
        $_SESSION['success'] = 'Đã cập nhật tồn kho';
    } else {
        $_SESSION['error'] = 'Lỗi khi cập nhật tồn kho';
    }
    
    $stmt->close();
    header('Location: inventory.php');
    exit();
}

// Lọc dữ liệu
$category = $_GET['category'] ?? '';
$size = $_GET['size'] ?? '';
$color = $_GET['color'] ?? '';
$low_stock = isset($_GET['low_stock']) ? true : false;

$where_conditions = ["sku.trang_thai = 'DANG_BAN'"];
$params = [];
$types = '';

if ($category && $category != 'all') {
    $where_conditions[] = "sp.danh_muc_id = ?";
    $params[] = $category;
    $types .= 'i';
}

if ($size && $size != 'all') {
    $where_conditions[] = "sku.kich_co_id = ?";
    $params[] = $size;
    $types .= 'i';
}

if ($color && $color != 'all') {
    $where_conditions[] = "sku.mau_sac_id = ?";
    $params[] = $color;
    $types .= 'i';
}

if ($low_stock) {
    $where_conditions[] = "sku.so_luong_ton <= 10";
}

$where_sql = implode(' AND ', $where_conditions);

// Lấy danh sách SKU
$sql = "
    SELECT sku.*, sp.ten_san_pham, sp.anh_dai_dien_url, sp.danh_muc_id,
           kc.ten_kich_co, ms.ten_mau, ms.ma_mau,
           dm.ten_danh_muc
    FROM sku_san_pham sku
    JOIN san_pham sp ON sku.san_pham_id = sp.id
    JOIN danh_muc_san_pham dm ON sp.danh_muc_id = dm.id
    JOIN kich_co kc ON sku.kich_co_id = kc.id
    JOIN mau_sac ms ON sku.mau_sac_id = ms.id
    WHERE $where_sql
    ORDER BY sku.so_luong_ton ASC, sp.ten_san_pham
";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$skus = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lấy danh mục, sizes, colors cho filter
$categories = $conn->query("SELECT id, ten_danh_muc FROM danh_muc_san_pham WHERE trang_thai = 'HOAT_DONG' ORDER BY ten_danh_muc")->fetch_all(MYSQLI_ASSOC);
$sizes = $conn->query("SELECT * FROM kich_co WHERE trang_thai = 'HOAT_DONG' ORDER BY thu_tu")->fetch_all(MYSQLI_ASSOC);
$colors = $conn->query("SELECT * FROM mau_sac WHERE trang_thai = 'HOAT_DONG' ORDER BY ten_mau")->fetch_all(MYSQLI_ASSOC);

// Tính thống kê
$total_skus = count($skus);
$low_stock_count = 0;
$out_of_stock_count = 0;
$total_inventory_value = 0;

foreach ($skus as $sku) {
    if ($sku['so_luong_ton'] <= 10) $low_stock_count++;
    if ($sku['so_luong_ton'] == 0) $out_of_stock_count++;
    $total_inventory_value += $sku['gia_ban'] * $sku['so_luong_ton'];
}

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Quản lý tồn kho</h5>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
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
                <select class="form-select" name="size">
                    <option value="all">Tất cả size</option>
                    <?php foreach ($sizes as $size_item): ?>
                    <option value="<?php echo $size_item['id']; ?>" <?php echo $size == $size_item['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($size_item['ten_kich_co']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="color">
                    <option value="all">Tất cả màu</option>
                    <?php foreach ($colors as $color_item): ?>
                    <option value="<?php echo $color_item['id']; ?>" <?php echo $color == $color_item['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($color_item['ten_mau']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="low_stock" id="low_stock" <?php echo $low_stock ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="low_stock">
                        Chỉ hiển thị sản phẩm sắp hết hàng (≤ 10)
                    </label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>

        <!-- Thống kê -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Tổng SKU</h6>
                        <h4 class="mb-0"><?php echo $total_skus; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body py-3">
                        <h6 class="card-title">Sắp hết hàng</h6>
                        <h4 class="mb-0"><?php echo $low_stock_count; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Hết hàng</h6>
                        <h4 class="mb-0"><?php echo $out_of_stock_count; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body py-3">
                        <h6 class="card-title">Tổng giá trị</h6>
                        <h4 class="mb-0"><?php echo formatCurrency($total_inventory_value); ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Size</th>
                        <th>Màu</th>
                        <th>Giá bán</th>
                        <th>Tồn kho</th>
                        <th>Giá trị</th>
                        <th>Cập nhật</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skus as $sku): 
                    $inventory_value = $sku['gia_ban'] * $sku['so_luong_ton'];
                    ?>
                    <tr class="<?php echo $sku['so_luong_ton'] == 0 ? 'table-danger' : ($sku['so_luong_ton'] <= 10 ? 'table-warning' : ''); ?>">
                        <td><code><?php echo $sku['ma_sku']; ?></code></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($sku['anh_dai_dien_url']): ?>
                                <img src="<?php echo htmlspecialchars($sku['anh_dai_dien_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($sku['ten_san_pham']); ?>"
                                     class="product-img me-3">
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($sku['ten_san_pham']); ?></strong><br>
                                    <small class="text-muted">ID: <?php echo $sku['san_pham_id']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($sku['ten_danh_muc']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo $sku['ten_kich_co']; ?></span></td>
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
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="sku_id" value="<?php echo $sku['id']; ?>">
                                <input type="hidden" name="update_inventory" value="1">
                                <div class="input-group input-group-sm" style="width: 120px;">
                                    <input type="number" class="form-control" name="so_luong_ton" 
                                           value="<?php echo $sku['so_luong_ton']; ?>" min="0" style="width: 80px;">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </form>
                        </td>
                        <td><?php echo formatCurrency($inventory_value); ?></td>
                        <td><?php echo formatDate($sku['cap_nhat_luc']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Export button -->
        <div class="mt-3">
            <button class="btn btn-success" onclick="exportInventory()">
                <i class="fas fa-file-excel"></i> Xuất Excel
            </button>
        </div>
    </div>
</div>

<script>
function exportInventory() {
    // Chuyển đến trang export
    window.location.href = 'export-inventory.php?' + window.location.search.substring(1);
}
</script>

<?php include 'includes/footer.php'; ?>