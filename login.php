<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        $stmt = $pdo->prepare("SELECT id, email, mat_khau_bam, ho_ten, vai_tro FROM nguoi_dung WHERE email = ? AND vai_tro = 'QUAN_TRI' AND trang_thai = 'HOAT_DONG'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['mat_khau_bam'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_ho_ten'] = $admin['ho_ten'];
            $_SESSION['vai_tro'] = $admin['vai_tro'];
            
            // Cập nhật lần đăng nhập cuối
            $stmt = $pdo->prepare("UPDATE nguoi_dung SET lan_dang_nhap_gan_nhat = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);
            
            redirect('index.php');
        } else {
            $error = 'Email hoặc mật khẩu không chính xác';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 440px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .form-control {
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.875rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-user-shield fa-3x text-white mb-3"></i>
            <h3 class="text-white fw-bold mb-1">Admin Login</h3>
            <p class="text-white-50 small mb-0">Đăng nhập vào hệ thống quản trị</p>
        </div>
        
        <div class="p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3">
                            <i class="fas fa-envelope text-secondary"></i>
                        </span>
                        <input type="email" name="email" class="form-control border-start-0 ps-0" 
                               placeholder="admin@example.com" required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold small text-secondary">Mật khẩu</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3">
                            <i class="fas fa-lock text-secondary"></i>
                        </span>
                        <input type="password" name="password" class="form-control border-start-0 ps-0" 
                               placeholder="••••••••" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>