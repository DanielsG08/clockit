<?php
require_once 'config/init.php';

requireAuth();

$userId = $_SESSION['user_id'];
$user = getCurrentUser();

ActivityLogger::log($userId, 'LOGOUT', 'user', $userId);

session_destroy();

header('Location: index.php?logout=1');
exit;
?>
