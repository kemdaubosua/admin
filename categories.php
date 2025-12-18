<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkAdminAuth();
$page_title = 'Quản lý Danh mục';
$active_page = 'categories';

// Xử lý các thao tác
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $ten_danh_muc = sanitize($_POST['ten_danh_muc']);
        $duong_dan = createSlug($_POST['duong_dan'] ?: $ten_danh_muc);
        $mo_ta = sanitize($_POST['mo_ta']);
        $trang_thai = $_POST['trang_thai'];
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO danh_muc_san_pham (ten_danh_muc, duong_dan, mo_ta, trang_thai) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ten_danh_muc, $duong_dan, $mo_ta, $trang_thai]);
                showMessage('Thêm danh mục thành công!');
            } else {
                $stmt = $pdo->prepare("UPDATE danh_muc_san_pham SET ten_danh_muc = ?, duong_dan = ?, mo_ta = ?, trang_thai = ? WHERE id = ?");
                $stmt->execute([$ten_danh_muc, $duong_dan, $mo_ta, $trang_thai, $id]);
                showMessage('Cập nhật danh mục thành công!');
            }
            redirect('categories.php');
        } catch (PDOException $e) {
            showMessage('Lỗi: ' . $e->getMessage(), 'danger');
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM danh_muc_san_pham WHERE id = ?");
            $stmt->execute([$id]);
            showMessage('Xóa danh mục thành công!');
        } catch (PDOException $e) {
            showMessage('Không thể xóa danh mục có sản phẩm!', 'danger');
        }
        redirect('categories.php');
    }
}

// Lấy danh sách danh mục với số lượng sản phẩm
$stmt = $pdo->query("SELECT dm.*, COUNT(sp.id) as so_luong_sp 
                     FROM danh_muc_san_pham dm 
                     LEFT JOIN san_pham sp ON dm.id = sp.danh_muc_id 
                     GROUP BY dm.id 
                     ORDER BY dm.tao_luc DESC");
$categories = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="modalTitle">Thêm danh mục</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body px-4 pb-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="categoryId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_danh_muc" id="categoryName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Đường dẫn (slug)</label>
                        <input type="text" class="form-control" name="duong_dan" id="categorySlug" placeholder="Tự động tạo nếu để trống">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" id="categoryDesc" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Trạng thái</label>
                        <select class="form-select" name="trang_thai" id="categoryStatus">
                            <option value="HOAT_DONG">Hoạt động</option>
                            <option value="NGUNG_HOAT_DONG">Ngừng hoạt động</option>
                        </select>
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
            <h2 class="fw-bold text-dark mb-1">Quản lý Danh mục</h2>
            <p class="text-secondary small mb-0">Phân loại và tổ chức sản phẩm</p>
        </div>
        <button onclick="openModal()" class="btn btn-dark-custom d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus text-xs"></i> Thêm danh mục
        </button>
    </div>

    <div class="row g-4">
        <?php foreach ($categories as $cat): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-custom h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-folder fa-2x text-primary"></i>
                            </div>
                            <div>
                                <?php echo getStatusBadge($cat['trang_thai'], 'category'); ?>
                            </div>
                        </div>
                        
                        <h5 class="fw-bold text-dark mb-2"><?php echo $cat['ten_danh_muc']; ?></h5>
                        <p class="text-secondary small mb-3"><?php echo $cat['mo_ta'] ?: 'Chưa có mô tả'; ?></p>
                        
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div>
                                <div class="text-secondary small">Sản phẩm</div>
                                <div class="fw-bold"><?php echo $cat['so_luong_sp']; ?></div>
                            </div>
                            <div class="vr"></div>
                            <div>
                                <div class="text-secondary small">Slug</div>
                                <div class="fw-bold font-monospace small"><?php echo $cat['duong_dan']; ?></div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button onclick='editCategory(<?php echo json_encode($cat); ?>)' class="btn btn-sm btn-primary flex-grow-1 rounded-3">
                                <i class="fas fa-pen me-1"></i> Sửa
                            </button>
                            <form method="POST" action="" class="flex-grow-1" onsubmit="return confirmDelete()">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100 rounded-3">
                                    <i class="fas fa-trash-can me-1"></i> Xóa
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<script>
let categoryModal;

document.addEventListener('DOMContentLoaded', function() {
    categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
});

function openModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').innerText = 'Thêm danh mục';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categorySlug').value = '';
    document.getElementById('categoryDesc').value = '';
    document.getElementById('categoryStatus').value = 'HOAT_DONG';
    categoryModal.show();
}

function editCategory(cat) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').innerText = 'Chỉnh sửa danh mục';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.ten_danh_muc;
    document.getElementById('categorySlug').value = cat.duong_dan;
    document.getElementById('categoryDesc').value = cat.mo_ta || '';
    document.getElementById('categoryStatus').value = cat.trang_thai;
    categoryModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>