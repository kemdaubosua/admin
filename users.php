<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Quản lý Người dùng';
$active_page = 'users';

// Xử lý các thao tác
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $email = sanitize($_POST['email']);
        $ho_ten = sanitize($_POST['ho_ten']);
        $so_dien_thoai = sanitize($_POST['so_dien_thoai']);
        $ngay_sinh = $_POST['ngay_sinh'] ?? null;
        $vai_tro = $_POST['vai_tro'];
        $trang_thai = $_POST['trang_thai'];
        
        try {
            if ($action === 'add') {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO nguoi_dung (email, mat_khau_bam, ho_ten, ngay_sinh, so_dien_thoai, vai_tro, trang_thai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$email, $password, $ho_ten, $ngay_sinh, $so_dien_thoai, $vai_tro, $trang_thai]);
                showMessage('Thêm người dùng thành công!');
            } else {
                $stmt = $pdo->prepare("UPDATE nguoi_dung SET ho_ten = ?, ngay_sinh = ?, so_dien_thoai = ?, vai_tro = ?, trang_thai = ? WHERE id = ?");
                $stmt->execute([$ho_ten, $ngay_sinh, $so_dien_thoai, $vai_tro, $trang_thai, $id]);
                showMessage('Cập nhật người dùng thành công!');
            }
            redirect('users.php');
        } catch (PDOException $e) {
            showMessage('Lỗi: ' . $e->getMessage(), 'danger');
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM nguoi_dung WHERE id = ?");
            $stmt->execute([$id]);
            showMessage('Xóa người dùng thành công!');
        } catch (PDOException $e) {
            showMessage('Không thể xóa người dùng này!', 'danger');
        }
        redirect('users.php');
    }
}

// Lấy danh sách người dùng
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT * FROM nguoi_dung WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (ho_ten LIKE ? OR email LIKE ? OR so_dien_thoai LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($role_filter) {
    $sql .= " AND vai_tro = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $sql .= " AND trang_thai = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY tao_luc DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Modal Thêm/Sửa -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalTitle">Thêm người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body px-4 pb-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="userEmail" required>
                    </div>
                    
                    <div class="mb-3" id="passwordField">
                        <label class="form-label fw-bold small text-secondary">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" id="userPassword">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Họ tên</label>
                        <input type="text" class="form-control" name="ho_ten" id="userHoTen">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Ngày sinh</label>
                            <input type="date" class="form-control" name="ngay_sinh" id="userNgaySinh">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Số điện thoại</label>
                            <input type="text" class="form-control" name="so_dien_thoai" id="userPhone">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Vai trò</label>
                            <select class="form-select" name="vai_tro" id="userRole">
                                <option value="NGUOI_DUNG">Người dùng</option>
                                <option value="QUAN_TRI">Quản trị</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Trạng thái</label>
                            <select class="form-select" name="trang_thai" id="userStatus">
                                <option value="HOAT_DONG">Hoạt động</option>
                                <option value="KHOA">Khóa</option>
                                <option value="NGUNG_HOAT_DONG">Ngừng</option>
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
            <h2 class="fw-bold text-dark mb-1">Quản lý Người dùng</h2>
            <p class="text-secondary small mb-0">Quản lý tài khoản và phân quyền</p>
        </div>
        <button onclick="openModal()" class="btn btn-dark-custom d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i> Thêm người dùng
        </button>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo tên, email, SĐT..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">Tất cả vai trò</option>
                        <option value="NGUOI_DUNG" <?php echo $role_filter === 'NGUOI_DUNG' ? 'selected' : ''; ?>>Người dùng</option>
                        <option value="QUAN_TRI" <?php echo $role_filter === 'QUAN_TRI' ? 'selected' : ''; ?>>Quản trị</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="HOAT_DONG" <?php echo $status_filter === 'HOAT_DONG' ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="KHOA" <?php echo $status_filter === 'KHOA' ? 'selected' : ''; ?>>Khóa</option>
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
                        <th class="ps-4">ID</th>
                        <th>Thông tin</th>
                        <th>Liên hệ</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo $user['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['ho_ten'] ?? 'User'); ?>&background=random" 
                                         class="rounded-circle" width="40" height="40" alt="Avatar">
                                    <div>
                                        <div class="fw-bold"><?php echo $user['ho_ten'] ?? 'Chưa cập nhật'; ?></div>
                                        <div class="text-secondary small"><?php echo $user['email']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo $user['so_dien_thoai'] ?? '-'; ?></div>
                                <div class="text-secondary small"><?php echo $user['ngay_sinh'] ? formatDate($user['ngay_sinh']) : '-'; ?></div>
                            </td>
                            <td>
                                <?php if ($user['vai_tro'] === 'QUAN_TRI'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Quản trị</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Người dùng</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo getStatusBadge($user['trang_thai'], 'user'); ?></td>
                            <td><?php echo formatDateTime($user['tao_luc']); ?></td>
                            <td class="text-end pe-4">
                                <button onclick='editUser(<?php echo json_encode($user); ?>)' class="btn btn-sm btn-light text-primary border me-1">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete()">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-light text-danger border">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
let userModal;

document.addEventListener('DOMContentLoaded', function() {
    userModal = new bootstrap.Modal(document.getElementById('userModal'));
});

function openModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').innerText = 'Thêm người dùng';
    document.getElementById('userId').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userEmail').removeAttribute('readonly');
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('userHoTen').value = '';
    document.getElementById('userNgaySinh').value = '';
    document.getElementById('userPhone').value = '';
    document.getElementById('userRole').value = 'NGUOI_DUNG';
    document.getElementById('userStatus').value = 'HOAT_DONG';
    userModal.show();
}

function editUser(user) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').innerText = 'Chỉnh sửa người dùng';
    document.getElementById('userId').value = user.id;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userEmail').setAttribute('readonly', true);
    document.getElementById('passwordField').style.display = 'none';
    document.getElementById('userHoTen').value = user.ho_ten || '';
    document.getElementById('userNgaySinh').value = user.ngay_sinh || '';
    document.getElementById('userPhone').value = user.so_dien_thoai || '';
    document.getElementById('userRole').value = user.vai_tro;
    document.getElementById('userStatus').value = user.trang_thai;
    userModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>