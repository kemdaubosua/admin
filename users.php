<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Quản lý Người dùng';
$active_page = 'users';

// --- PHẦN XỬ LÝ LOGIC PHP ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        
        // 1. Lấy dữ liệu Họ và Tên riêng biệt giống register.php
        $ho = sanitize($_POST['ho'] ?? '');
        $ten = sanitize($_POST['ten'] ?? '');
        // Nối lại để lưu vào CSDL
        $ho_ten = trim($ho . ' ' . $ten);

        $email = sanitize($_POST['email']);
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
                // Nếu đang Edit
                $stmt = $pdo->prepare("UPDATE nguoi_dung SET ho_ten = ?, ngay_sinh = ?, so_dien_thoai = ?, vai_tro = ?, trang_thai = ? WHERE id = ?");
                $stmt->execute([$ho_ten, $ngay_sinh, $so_dien_thoai, $vai_tro, $trang_thai, $id]);
                
                // Nếu có nhập mật khẩu mới thì cập nhật, không thì thôi
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE nguoi_dung SET mat_khau_bam = ? WHERE id = ?");
                    $stmt->execute([$password, $id]);
                }

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
// --- KẾT THÚC PHẦN LOGIC ---

include 'includes/header.php';
?>

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
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Họ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ho" id="userHo" placeholder="Nguyễn" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Tên <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ten" id="userTen" placeholder="Văn A" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Ngày sinh <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="ngay_sinh" id="userNgaySinh" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="so_dien_thoai" id="userPhone" placeholder="09xxxxxxxx" required pattern="[0-9]{10}">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="userEmail" placeholder="name@example.com" required>
                    </div>
                    
                    <div class="mb-3" id="passwordField">
                        <label class="form-label fw-bold small text-secondary">Mật khẩu <span class="text-danger" id="pwdReq">*</span></label>
                        <input type="password" class="form-control" name="password" id="userPassword" placeholder="Nhập mật khẩu">
                        <small class="text-muted fst-italic d-none" id="pwdNote">(Để trống nếu không muốn đổi mật khẩu)</small>
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

<div class="d-flex min-vh-100 bg-light">
    <div class="d-none d-lg-block border-end bg-white" style="width: 260px; min-width: 260px;">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <main class="flex-grow-1 p-3 p-lg-4" style="overflow-x: hidden;">
        <div class="d-lg-none d-flex align-items-center justify-content-between mb-4">
            <button class="btn btn-white border shadow-sm rounded-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="fw-bold fs-5">AdminCenter</span>
            <div style="width: 40px;"></div>
        </div>

        <?php displayMessage(); ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold text-dark mb-1">Quản lý Người dùng</h2>
                <p class="text-secondary small mb-0">Quản lý tài khoản và phân quyền</p>
            </div>
            <button onclick="openModal()" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i> Thêm người dùng
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control bg-light border-0" placeholder="Tìm theo tên, email, SĐT..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="role" class="form-select bg-light border-0">
                            <option value="">Tất cả vai trò</option>
                            <option value="NGUOI_DUNG" <?php echo $role_filter === 'NGUOI_DUNG' ? 'selected' : ''; ?>>Người dùng</option>
                            <option value="QUAN_TRI" <?php echo $role_filter === 'QUAN_TRI' ? 'selected' : ''; ?>>Quản trị</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select bg-light border-0">
                            <option value="">Tất cả trạng thái</option>
                            <option value="HOAT_DONG" <?php echo $status_filter === 'HOAT_DONG' ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="KHOA" <?php echo $status_filter === 'KHOA' ? 'selected' : ''; ?>>Khóa</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark w-100"><i class="fas fa-search"></i> Lọc</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold border-0">ID</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Thông tin</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Liên hệ</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Vai trò</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Trạng thái</th>
                            <th class="py-3 text-secondary text-uppercase small fw-bold border-0">Ngày tạo</th>
                            <th class="pe-4 py-3 text-end text-secondary text-uppercase small fw-bold border-0">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-secondary">#<?php echo $user['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['ho_ten'] ?? 'User'); ?>&background=random" 
                                             class="rounded-circle" width="36" height="36" alt="Avatar">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['ho_ten'] ?? 'Chưa cập nhật'); ?></div>
                                            <div class="text-secondary small" style="font-size: 0.8rem;"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark"><?php echo htmlspecialchars($user['so_dien_thoai'] ?? '-'); ?></div>
                                    <div class="text-secondary small" style="font-size: 0.8rem;"><?php echo $user['ngay_sinh'] ? formatDate($user['ngay_sinh']) : '-'; ?></div>
                                </td>
                                <td>
                                    <?php if ($user['vai_tro'] === 'QUAN_TRI'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Quản trị</span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">Người dùng</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getStatusBadge($user['trang_thai'], 'user'); ?></td>
                                <td class="text-secondary small"><?php echo formatDateTime($user['tao_luc']); ?></td>
                                <td class="text-end pe-4">
                                    <button onclick='editUser(<?php echo json_encode($user); ?>)' class="btn btn-sm btn-white border shadow-sm text-primary me-1" title="Sửa">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa người dùng này?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-white border shadow-sm text-danger" title="Xóa">
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
</div>

<script>
let userModal;

document.addEventListener('DOMContentLoaded', function() {
    userModal = new bootstrap.Modal(document.getElementById('userModal'));
});

// 3. CẬP NHẬT LOGIC JS
function openModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').innerText = 'Thêm người dùng';
    document.getElementById('userId').value = '';
    
    // Reset các trường mới
    document.getElementById('userHo').value = '';
    document.getElementById('userTen').value = '';
    
    document.getElementById('userEmail').value = '';
    document.getElementById('userEmail').removeAttribute('readonly');
    
    // Xử lý mật khẩu khi Thêm
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = true;
    document.getElementById('pwdReq').style.display = 'inline'; // Hiện dấu *
    document.getElementById('pwdNote').classList.add('d-none'); // Ẩn ghi chú
    
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
    
    // Tách Họ và Tên từ chuỗi ho_ten trong CSDL
    let fullName = user.ho_ten || '';
    let lastSpaceIndex = fullName.lastIndexOf(' ');
    let ho = '';
    let ten = fullName;
    
    if (lastSpaceIndex !== -1) {
        ho = fullName.substring(0, lastSpaceIndex);
        ten = fullName.substring(lastSpaceIndex + 1);
    }
    
    document.getElementById('userHo').value = ho;
    document.getElementById('userTen').value = ten;
    
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userEmail').setAttribute('readonly', true);
    
    // Xử lý mật khẩu khi Sửa (không bắt buộc)
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('pwdReq').style.display = 'none'; // Ẩn dấu *
    document.getElementById('pwdNote').classList.remove('d-none'); // Hiện ghi chú
    
    document.getElementById('userNgaySinh').value = user.ngay_sinh || '';
    document.getElementById('userPhone').value = user.so_dien_thoai || '';
    document.getElementById('userRole').value = user.vai_tro;
    document.getElementById('userStatus').value = user.trang_thai;
    userModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>