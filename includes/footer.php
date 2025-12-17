</main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    $(document).ready(function() {
        // Kiểm tra xem bảng có tồn tại không trước khi khởi tạo để tránh lỗi console
        if ($('.datatable').length > 0) {
            $('.datatable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Vietnamese.json"
                },
                "order": [[0, "desc"]],
                // Thêm responsive nếu cần
                "responsive": true
            });
        }
    });

    // Xác nhận xóa
    function confirmDelete(message = 'Bạn có chắc chắn muốn xóa dữ liệu này không?') {
        return confirm(message);
    }

    // Đổi trạng thái (Cải tiến bảo mật nên dùng Ajax POST, nhưng đây là sửa nhanh cho code hiện tại)
    function changeStatus(url, id, status) {
        if (confirm('Bạn có muốn đổi trạng thái không?')) {
            // Encode URI component để tránh lỗi ký tự đặc biệt
            window.location.href = url + '?id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status);
        }
    }
</script>
</body>
</html>