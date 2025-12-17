<?php
// /admin/logout.php
session_start();
require_once 'includes/auth.php';

adminLogout();
header('Location: login.php');
exit();
?>