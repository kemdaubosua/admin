<?php
// Mật khẩu bạn muốn đặt
$password = '123456';

// Tạo mã hash
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Mã hash của 123456 là: <br><b>" . $hash . "</b>";
?>