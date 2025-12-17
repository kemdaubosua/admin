<?php
// /admin/categories.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý danh mục';
$conn = getDBConnection();

// Xử lý form thêm/sửa danh mục
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'update') {
        $ten_danh_muc = $conn->real_escape_string($_POST['ten_danh_muc']);
        $duong_dan = $conn->real_escape_string($_POST['duong_dan']);
        $mo_ta = $conn->real_escape_string($_POST['mo_ta'] ?? '');
        $trang_thai = $_POST['trang_thai'];
        
        if ($action === 'add') {
            // Thêm danh mục mới
            $stmt = $conn->prepare("INSERT INTO danh_muc_san_pham (ten_danh_muc, duong_dan, mo_ta, trang_thai) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $ten_danh_muc, $duong_dan, $mo_ta, $trang_thai);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Thêm danh mục thành công';
            } else {
                $_SESSION['error'] = 'Lỗi khi thêm danh mục';
            }
            $stmt->close();
            
        } elseif ($action === 'update') {
            // Cập nhật danh mục
            $id = intval($_POST['id']);
            
            $stmt = $conn->prepare("UPDATE danh_muc_san_pham SET ten_danh_muc = ?, duong_dan = ?, mo_ta = ?, trang_thai = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $ten_danh_muc, $duong_dan, $mo_ta, $trang_thai, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Cập nhật danh mục thành công';
            } else {
                $_SESSION['error'] = 'Lỗi khi cập nhật danh mục';
            }
            $stmt->close();
        }
        
        header('Location: categories.php');
        exit();
    }
}

// Xóa danh mục
if (isset($_GET['delete'])) {
    $category_id = intval($_GET['id']);
    
    // Kiểm tra xem danh mục có sản phẩm không
    $check = $conn->query("SELECT COUNT(*) as count FROM san_pham WHERE danh_muc_id = $category_id");
    $result = $check->fetch_assoc();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = 'Không thể xóa danh mục đang có sản phẩm';
    } else {
        $conn->query("DELETE FROM danh_muc_san_pham WHERE id = $category_id");
        $_SESSION['success'] = 'Đã xóa danh mục';
    }
    
    header('Location: categories.php');
    exit();
}

// Lấy danh sách danh mục
$categories = $conn->query("SELECT * FROM danh_muc_san_pham ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Thêm danh mục mới</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên danh mục *</label>
                        <input type="text" class="form-control" name="ten_danh_muc" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Đường dẫn *</label>
                        <input type="text" class="form-control" name="duong_dan" required>
                        <small class="text-muted">vd: ao-thun, quan-jeans</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="HOAT_DONG">Hoạt động</option>
                            <option value="NGUNG_HOAT_DONG">Ngừng hoạt động</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Thêm danh mục</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Danh sách danh mục</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên danh mục</th>
                                <th>Đường dẫn</th>
                                <th>Số sản phẩm</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): 
                            // Đếm số sản phẩm trong danh mục
                            $conn_temp = getDBConnection();
                            $count_result = $conn_temp->query("SELECT COUNT(*) as count FROM san_pham WHERE danh_muc_id = {$category['id']}");
                            $count = $count_result->fetch_assoc()['count'];
                            closeDBConnection($conn_temp);
                            ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['ten_danh_muc']); ?></strong>
                                    <?php if ($category['mo_ta']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($category['mo_ta']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>/<?php echo htmlspecialchars($category['duong_dan']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $count; ?> SP</span>
                                </td>
                                <td>
                                    <?php
                                    $status_badge = [
                                        'HOAT_DONG' => 'bg-success',
                                        'NGUNG_HOAT_DONG' => 'bg-danger'
                                    ];
                                    $status_text = [
                                        'HOAT_DONG' => 'Hoạt động',
                                        'NGUNG_HOAT_DONG' => 'Ngừng HĐ'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $status_badge[$category['trang_thai']]; ?>">
                                        <?php echo $status_text[$category['trang_thai']]; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($category['tao_luc']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-info" data-bs-toggle="modal" 
                                                data-bs-target="#editCategoryModal<?php echo $category['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if ($count == 0): ?>
                                        <a href="categories.php?delete&id=<?php echo $category['id']; ?>" 
                                           class="btn btn-danger" onclick="return confirm('Xóa danh mục này?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Modal chỉnh sửa -->
                                    <div class="modal fade" id="editCategoryModal<?php echo $category['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Chỉnh sửa danh mục</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Tên danh mục *</label>
                                                            <input type="text" class="form-control" name="ten_danh_muc" 
                                                                   value="<?php echo htmlspecialchars($category['ten_danh_muc']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Đường dẫn *</label>
                                                            <input type="text" class="form-control" name="duong_dan" 
                                                                   value="<?php echo htmlspecialchars($category['duong_dan']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Mô tả</label>
                                                            <textarea class="form-control" name="mo_ta" rows="2"><?php echo htmlspecialchars($category['mo_ta'] ?? ''); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Trạng thái</label>
                                                            <select class="form-select" name="trang_thai">
                                                                <option value="HOAT_DONG" <?php echo $category['trang_thai'] == 'HOAT_DONG' ? 'selected' : ''; ?>>Hoạt động</option>
                                                                <option value="NGUNG_HOAT_DONG" <?php echo $category['trang_thai'] == 'NGUNG_HOAT_DONG' ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>