<?php
$menu_items = [
    ['icon' => 'fa-chart-line', 'label' => 'Dashboard', 'link' => 'index.php', 'key' => 'dashboard'],
    ['icon' => 'fa-users', 'label' => 'Người dùng', 'link' => 'users.php', 'key' => 'users'],
    ['icon' => 'fa-box', 'label' => 'Sản phẩm', 'link' => 'products.php', 'key' => 'products'],
    ['icon' => 'fa-folder', 'label' => 'Danh mục', 'link' => 'categories.php', 'key' => 'categories'],
    ['icon' => 'fa-shopping-cart', 'label' => 'Đơn hàng', 'link' => 'orders.php', 'key' => 'orders'],
    ['icon' => 'fa-warehouse', 'label' => 'Tồn kho', 'link' => 'inventory.php', 'key' => 'inventory'],
    ['icon' => 'fa-percent', 'label' => 'Khuyến mãi', 'link' => 'promotion.php', 'key' => 'khuyenmai'],
    ['icon' => 'fa-chart-bar', 'label' => 'Báo cáo', 'link' => 'reports.php', 'key' => 'reports'],
];
?>

<aside class="d-none d-lg-flex flex-column position-fixed top-0 start-0 vh-100 bg-white border-end" style="width: var(--sidebar-width); z-index: 1000;">
    <div class="p-4 border-bottom">
        <h4 class="fw-bold text-dark mb-0">AdminCenter</h4>
        <p class="text-secondary small mb-0">Quản trị hệ thống</p>
    </div>
    
    <nav class="flex-grow-1 p-3 overflow-auto">
        <?php foreach ($menu_items as $item): ?>
            <?php $isActive = (isset($active_page) && $active_page === $item['key']); ?>
            <a href="<?php echo $item['link']; ?>" 
               class="d-flex align-items-center gap-3 text-decoration-none px-3 py-3 rounded-3 mb-1 <?php echo $isActive ? 'bg-dark text-white' : 'text-secondary hover-bg-light'; ?>"
               style="transition: all 0.2s;">
                <i class="fas <?php echo $item['icon']; ?> fa-fw"></i>
                <span class="fw-medium"><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="p-3 border-top">
        <div class="d-flex align-items-center gap-3 px-3 py-2">
            <img src="https://ui-avatars.com/api/?name=Admin+User&background=0f172a&color=fff" 
                 class="rounded-circle" width="40" height="40" alt="Admin">
            <div class="flex-grow-1">
                <div class="fw-bold text-dark small"><?php echo getAdminInfo()['ho_ten'] ?? 'Admin'; ?></div>
                <div class="text-secondary" style="font-size: 0.75rem;">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="btn btn-outline-danger btn-sm w-100 mt-2 rounded-3">
            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
        </a>
    </div>
</aside>

<style>
.hover-bg-light:hover {
    background-color: #f8fafc !important;
    color: #0f172a !important;
}
</style>