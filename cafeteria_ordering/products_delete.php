<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin' && (($_SESSION['username'] ?? '') !== 'admin'))) {
    http_response_code(403);
    die('Forbidden');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if ($id <= 0) {
    $msg = 'Invalid id';
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    die($msg);
}

try {
    // Fetch image path to remove file
    $old = $pdo->prepare("SELECT image_path FROM menu_items WHERE id = :id");
    $old->execute([':id' => $id]);
    $row = $old->fetch();
    $img = $row['image_path'] ?? null;

    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if (!empty($img) && file_exists(__DIR__ . '/' . $img)) {
        @unlink(__DIR__ . '/' . $img);
    }
    $_SESSION['flash_success'] = 'Product deleted successfully.';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
        exit;
    }

    header('Location: products.php');
    exit;
} catch (Exception $e) {
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        exit;
    }
    die('Delete failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

