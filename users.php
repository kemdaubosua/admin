<?php
// /admin/users.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý người dùng';
$conn = getDBConnection();

// Xử lý thay đổi trạng thái
if (isset($_GET['change_status'])) {
    $user_id = intval($_GET['id']);
    $status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE nguoi_dung SET trang_thai = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Đã cập nhật trạng thái người dùng';
    } else {
        $_SESSION['error'] = 'Lỗi khi cập nhật trạng thái';
    }
    
    $stmt->close();
    header('Location: users.php');
    exit();
}

// Xử lý xóa người dùng
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['id']);
    
    // Kiểm tra xem người dùng có đơn hàng không
    $check = $conn->query("SELECT COUNT(*) as count FROM don_hang WHERE nguoi_dung_id = $user_id");
    $result = $check->fetch_assoc();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = 'Không thể xóa người dùng đã có đơn hàng';
    } else {
        $stmt = $conn->prepare("DELETE FROM nguoi_dung WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Đã xóa người dùng thành công';
        } else {
            $_SESSION['error'] = 'Lỗi khi xóa người dùng';
        }
        $stmt->close();
    }
    
    header('Location: users.php');
    exit();
}

// Phân trang
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Lấy tổng số người dùng
$total_result = $conn->query("SELECT COUNT(*) as total FROM nguoi_dung");
$total_users = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Lấy danh sách người dùng
$users = $conn->query("
    SELECT * FROM nguoi_dung 
    ORDER BY id DESC 
    LIMIT $per_page OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

closeDBConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Danh sách người dùng</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus"></i> Thêm người dùng
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Họ tên</th>
                        <th>Điện thoại</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Ngày đăng ký</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['ho_ten'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($user['so_dien_thoai'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge <?php echo $user['vai_tro'] == 'QUAN_TRI' ? 'bg-danger' : 'bg-primary'; ?>">
                                <?php echo $user['vai_tro'] == 'QUAN_TRI' ? 'Quản trị' : 'Người dùng'; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $status_badge = [
                                'HOAT_DONG' => 'bg-success',
                                'KHOA' => 'bg-danger',
                                'NGUNG_HOAT_DONG' => 'bg-warning'
                            ];
                            $status_text = [
                                'HOAT_DONG' => 'Hoạt động',
                                'KHOA' => 'Đã khóa',
                                'NGUNG_HOAT_DONG' => 'Ngừng HĐ'
                            ];
                            ?>
                            <span class="badge <?php echo $status_badge[$user['trang_thai']] ?? 'bg-secondary'; ?>">
                                <?php echo $status_text[$user['trang_thai']] ?? $user['trang_thai']; ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($user['tao_luc']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" 
                                        data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($user['trang_thai'] == 'HOAT_DONG'): ?>
                                <a href="users.php?change_status&id=<?php echo $user['id']; ?>&status=KHOA" 
                                   class="btn btn-warning" onclick="return confirm('Khóa người dùng này?')">
                                    <i class="fas fa-lock"></i>
                                </a>
                                <?php else: ?>
                                <a href="users.php?change_status&id=<?php echo $user['id']; ?>&status=HOAT_DONG" 
                                   class="btn btn-success" onclick="return confirm('Mở khóa người dùng này?')">
                                    <i class="fas fa-unlock"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="users.php?delete&id=<?php echo $user['id']; ?>" 
                                   class="btn btn-danger" onclick="return confirm('Xóa người dùng này?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            
                            <!-- Modal chỉnh sửa -->
                            <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Chỉnh sửa người dùng</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="user-process.php">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="email" 
                                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Họ tên</label>
                                                    <input type="text" class="form-control" name="ho_ten" 
                                                           value="<?php echo htmlspecialchars($user['ho_ten'] ?? ''); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Số điện thoại</label>
                                                    <input type="tel" class="form-control" name="so_dien_thoai" 
                                                           value="<?php echo htmlspecialchars($user['so_dien_thoai'] ?? ''); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Vai trò</label>
                                                    <select class="form-select" name="vai_tro">
                                                        <option value="NGUOI_DUNG" <?php echo $user['vai_tro'] == 'NGUOI_DUNG' ? 'selected' : ''; ?>>Người dùng</option>
                                                        <option value="QUAN_TRI" <?php echo $user['vai_tro'] == 'QUAN_TRI' ? 'selected' : ''; ?>>Quản trị</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Trạng thái</label>
                                                    <select class="form-select" name="trang_thai">
                                                        <option value="HOAT_DONG" <?php echo $user['trang_thai'] == 'HOAT_DONG' ? 'selected' : ''; ?>>Hoạt động</option>
                                                        <option value="KHOA" <?php echo $user['trang_thai'] == 'KHOA' ? 'selected' : ''; ?>>Khóa</option>
                                                        <option value="NGUNG_HOAT_DONG" <?php echo $user['trang_thai'] == 'NGUNG_HOAT_DONG' ? 'selected' : ''; ?>>Ngừng hoạt động</option>
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
        
        <!-- Phân trang -->
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Trước</a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sau</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal thêm người dùng -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm người dùng mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="user-process.php">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Họ tên</label>
                        <input type="text" class="form-control" name="ho_ten">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="tel" class="form-control" name="so_dien_thoai">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vai trò</label>
                        <select class="form-select" name="vai_tro">
                            <option value="NGUOI_DUNG">Người dùng</option>
                            <option value="QUAN_TRI">Quản trị</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="trang_thai">
                            <option value="HOAT_DONG">Hoạt động</option>
                            <option value="KHOA">Khóa</option>
                            <option value="NGUNG_HOAT_DONG">Ngừng hoạt động</option>
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

<?php include 'includes/footer.php'; ?>