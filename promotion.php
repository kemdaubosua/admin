<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Quản lý Khuyến mãi';
$active_page = 'khuyenmai';

// Tạo bảng khuyến mãi nếu chưa có
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS khuyen_mai (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ma_khuyen_mai VARCHAR(12) NOT NULL UNIQUE,
        ten_chuong_trinh VARCHAR(255) NOT NULL,
        loai_hinh ENUM('san-pham', 'voucher') NOT NULL DEFAULT 'san-pham',
        ngay_bat_dau DATE NOT NULL,
        gio_bat_dau TIME NOT NULL,
        ngay_ket_thuc DATE NOT NULL,
        gio_ket_thuc TIME NOT NULL,
        trang_thai ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
        tao_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cap_nhat_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ma_khuyen_mai (ma_khuyen_mai),
        INDEX idx_trang_thai (trang_thai)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Bảng đã tồn tại
}

// Xử lý các thao tác
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $ma_khuyen_mai = strtoupper(sanitize($_POST['ma_khuyen_mai']));
        $ten_chuong_trinh = sanitize($_POST['ten_chuong_trinh']);
        $loai_hinh = $_POST['loai_hinh'];
        $ngay_bat_dau = $_POST['ngay_bat_dau'];
        $gio_bat_dau = $_POST['gio_bat_dau'];
        $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
        $gio_ket_thuc = $_POST['gio_ket_thuc'];
        $trang_thai = $_POST['trang_thai'] ?? 'active';
        
        // Validate mã khuyến mãi
        if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]{1,12}$/', $ma_khuyen_mai)) {
            showMessage('Mã khuyến mãi không hợp lệ! Phải có ít nhất 1 chữ và 1 số, tối đa 12 ký tự.', 'danger');
            redirect('promotion.php');
        }
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO khuyen_mai (ma_khuyen_mai, ten_chuong_trinh, loai_hinh, ngay_bat_dau, gio_bat_dau, ngay_ket_thuc, gio_ket_thuc, trang_thai) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$ma_khuyen_mai, $ten_chuong_trinh, $loai_hinh, $ngay_bat_dau, $gio_bat_dau, $ngay_ket_thuc, $gio_ket_thuc, $trang_thai]);
                showMessage('Thêm khuyến mãi thành công!');
            } else {
                $stmt = $pdo->prepare("UPDATE khuyen_mai SET ten_chuong_trinh = ?, loai_hinh = ?, ngay_bat_dau = ?, gio_bat_dau = ?, ngay_ket_thuc = ?, gio_ket_thuc = ?, trang_thai = ? WHERE id = ?");
                $stmt->execute([$ten_chuong_trinh, $loai_hinh, $ngay_bat_dau, $gio_bat_dau, $ngay_ket_thuc, $gio_ket_thuc, $trang_thai, $id]);
                showMessage('Cập nhật khuyến mãi thành công!');
            }
            redirect('promotion.php');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                showMessage('Mã khuyến mãi đã tồn tại!', 'danger');
            } else {
                showMessage('Lỗi: ' . $e->getMessage(), 'danger');
            }
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM khuyen_mai WHERE id = ?");
            $stmt->execute([$id]);
            showMessage('Xóa khuyến mãi thành công!');
        } catch (PDOException $e) {
            showMessage('Không thể xóa khuyến mãi này!', 'danger');
        }
        redirect('promotion.php');
    }
    
    if ($action === 'update_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        try {
            $stmt = $pdo->prepare("UPDATE khuyen_mai SET trang_thai = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            showMessage('Cập nhật trạng thái thành công!');
        } catch (PDOException $e) {
            showMessage('Lỗi: ' . $e->getMessage(), 'danger');
        }
        redirect('promotion.php');
    }
}

// Lấy danh sách khuyến mãi
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT * FROM khuyen_mai WHERE 1=1";
$params = [];

if ($type_filter && $type_filter !== 'all') {
    $sql .= " AND loai_hinh = ?";
    $params[] = $type_filter;
}

if ($status_filter && $status_filter !== 'all') {
    $sql .= " AND trang_thai = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY tao_luc DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$promotions = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Modal -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalTitle">Tạo khuyến mãi mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body px-4 pb-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="promoId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Loại hình</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="loai_hinh" id="typeProduct" value="san-pham" checked>
                                <label class="btn btn-outline-primary w-100 py-3 rounded-3 d-flex flex-column align-items-center gap-1 border-2" for="typeProduct">
                                    <i class="fa-solid fa-percent"></i>
                                    <span class="small fw-bold">Sản phẩm</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="loai_hinh" id="typeVoucher" value="voucher">
                                <label class="btn btn-outline-secondary w-100 py-3 rounded-3 d-flex flex-column align-items-center gap-1 border-2" for="typeVoucher">
                                    <i class="fa-solid fa-ticket"></i>
                                    <span class="small fw-bold">Voucher</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Mã khuyến mãi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-secondary">
                                <i class="fa-solid fa-barcode"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" name="ma_khuyen_mai" id="promoCode" 
                                   placeholder="Ví dụ: SUMMER2025" required maxlength="12"
                                   pattern="^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]+$"
                                   title="Phải có ít nhất 1 chữ và 1 số, không dấu, tối đa 12 ký tự">
                        </div>
                        <div class="form-text text-xs text-muted">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Tối đa 12 ký tự. Viết liền không dấu, in hoa, ít nhất 1 số & 1 chữ.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Tên chương trình <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_chuong_trinh" id="promoName" 
                               placeholder="Ví dụ: Siêu sale mùa hè" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Ngày bắt đầu</label>
                            <input type="date" class="form-control" name="ngay_bat_dau" id="startDate" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Giờ bắt đầu</label>
                            <input type="time" class="form-control" name="gio_bat_dau" id="startTime" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Ngày kết thúc</label>
                            <input type="date" class="form-control" name="ngay_ket_thuc" id="endDate" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Giờ kết thúc</label>
                            <input type="time" class="form-control" name="gio_ket_thuc" id="endTime" required>
                        </div>
                    </div>

                    <input type="hidden" name="trang_thai" id="promoStatus" value="active">
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light flex-grow-1 fw-bold py-2 rounded-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-dark-custom flex-grow-1 shadow">Lưu lại</button>
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
        <img src="https://ui-avatars.com/api/?name=Admin+User" class="rounded-circle border" width="36" height="36" alt="Admin Avatar">
    </div>

    <?php displayMessage(); ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">Quản lý Khuyến mãi</h2>
            <p class="text-secondary small mb-0">Chi tiết thời gian và trạng thái chương trình.</p>
        </div>
        <button onclick="openModal()" class="btn btn-dark-custom d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i> Thêm khuyến mãi
        </button>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-5">
                    <select name="type" class="form-select">
                        <option value="all">Tất cả loại hình</option>
                        <option value="san-pham" <?php echo $type_filter === 'san-pham' ? 'selected' : ''; ?>>Sản phẩm</option>
                        <option value="voucher" <?php echo $type_filter === 'voucher' ? 'selected' : ''; ?>>Voucher</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="status" class="form-select">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                        <option value="disabled" <?php echo $status_filter === 'disabled' ? 'selected' : ''; ?>>Vô hiệu hóa</option>
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
            <table class="table table-custom table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Chương trình / Mã Code</th>
                        <th>Thời gian bắt đầu</th>
                        <th>Thời gian kết thúc</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php if (empty($promotions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-5">
                                <i class="fas fa-percent fa-3x mb-3 d-block"></i>
                                Chưa có chương trình khuyến mãi nào
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($promotions as $promo): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark text-lg"><?php echo $promo['ten_chuong_trinh']; ?></div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-light text-primary border border-primary-subtle fw-bold font-monospace">
                                            <i class="fa-solid fa-barcode me-1"></i><?php echo $promo['ma_khuyen_mai']; ?>
                                        </span>
                                        <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 10px;">
                                            <?php echo $promo['loai_hinh'] === 'san-pham' ? 'Sản phẩm' : 'Voucher'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($promo['ngay_bat_dau'])); ?></div>
                                    <div class="text-secondary small"><?php echo date('H:i', strtotime($promo['gio_bat_dau'])); ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($promo['ngay_ket_thuc'])); ?></div>
                                    <div class="text-secondary small"><?php echo date('H:i', strtotime($promo['gio_ket_thuc'])); ?></div>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <?php 
                                        $badgeClass = $promo['trang_thai'] === 'active' 
                                            ? "badge bg-success-subtle text-success border border-success-subtle" 
                                            : "badge bg-danger-subtle text-danger border border-danger-subtle";
                                        ?>
                                        <button class="btn btn-sm <?php echo $badgeClass; ?> dropdown-toggle text-uppercase fw-bold" 
                                                style="font-size: 10px;" type="button" data-bs-toggle="dropdown">
                                            <?php echo $promo['trang_thai'] === 'active' ? 'Đang hoạt động' : 'Vô hiệu hóa'; ?>
                                        </button>
                                        <ul class="dropdown-menu border-0 shadow">
                                            <?php if ($promo['trang_thai'] !== 'active'): ?>
                                            <li>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" class="dropdown-item text-success fw-bold text-uppercase small">
                                                        Kích hoạt
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($promo['trang_thai'] !== 'disabled'): ?>
                                            <li>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                                    <input type="hidden" name="status" value="disabled">
                                                    <button type="submit" class="dropdown-item text-danger fw-bold text-uppercase small">
                                                        Vô hiệu hóa
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <button onclick='editPromo(<?php echo json_encode($promo); ?>)' 
                                            class="btn btn-light btn-sm text-primary border me-1">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmDelete()">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                        <button type="submit" class="btn btn-light btn-sm text-danger border">
                                            <i class="fa-solid fa-trash-can"></i>
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
let promoModal;

document.addEventListener('DOMContentLoaded', function() {
    promoModal = new bootstrap.Modal(document.getElementById('promoModal'));
    
    // Auto-format mã khuyến mãi
    const promoCodeInput = document.getElementById('promoCode');
    promoCodeInput.addEventListener('input', function() {
        let val = this.value.toUpperCase();
        // Xóa dấu
        val = val.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        // Chỉ giữ A-Z và 0-9
        val = val.replace(/[^A-Z0-9]/g, "");
        // Cắt nếu quá 12 ký tự
        if(val.length > 12) val = val.slice(0, 12);
        this.value = val;
    });
});

function openModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').innerText = 'Tạo khuyến mãi mới';
    document.getElementById('promoId').value = '';
    document.getElementById('promoCode').value = '';
    document.getElementById('promoCode').removeAttribute('readonly');
    document.getElementById('promoName').value = '';
    document.getElementById('startDate').value = '';
    document.getElementById('startTime').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('endTime').value = '';
    document.getElementById('typeProduct').checked = true;
    document.getElementById('promoStatus').value = 'active';
    promoModal.show();
}

function editPromo(promo) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').innerText = 'Chỉnh sửa khuyến mãi';
    document.getElementById('promoId').value = promo.id;
    document.getElementById('promoCode').value = promo.ma_khuyen_mai;
    document.getElementById('promoCode').setAttribute('readonly', true);
    document.getElementById('promoName').value = promo.ten_chuong_trinh;
    document.getElementById('startDate').value = promo.ngay_bat_dau;
    document.getElementById('startTime').value = promo.gio_bat_dau;
    document.getElementById('endDate').value = promo.ngay_ket_thuc;
    document.getElementById('endTime').value = promo.gio_ket_thuc;
    document.getElementById('promoStatus').value = promo.trang_thai;
    
    if (promo.loai_hinh === 'san-pham') {
        document.getElementById('typeProduct').checked = true;
    } else {
        document.getElementById('typeVoucher').checked = true;
    }
    
    promoModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>