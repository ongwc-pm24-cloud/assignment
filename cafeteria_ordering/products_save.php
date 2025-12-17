<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin' && (($_SESSION['username'] ?? '') !== 'admin'))) {
    http_response_code(403);
    die('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name        = trim($_POST['name'] ?? '');
$category    = trim($_POST['category'] ?? '');
$price       = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$description = trim($_POST['description'] ?? '');
$uploadedPath = null;

$uploadsDir = __DIR__ . '/assets/uploads/';

if ($name === '' || $price < 0 || $category === '' || $description === '') {
    $msg = 'Name, category, description and non-negative price are required.';
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    die($msg);
}

// Handle image upload
if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    if ($ext === '') {
        $ext = 'jpg';
    }
    $filename = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $dest = $uploadsDir . $filename;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        $uploadedPath = 'assets/uploads/' . $filename;
    }
}

try {
    if ($id > 0) {
        // fetch existing image path
        $old = $pdo->prepare("SELECT image_path FROM menu_items WHERE id = :id");
        $old->execute([':id' => $id]);
        $oldRow = $old->fetch();
        $currentImage = $oldRow['image_path'] ?? null;

        $stmt = $pdo->prepare("
            UPDATE menu_items
            SET name = :name, category = :category, price = :price, description = :description, image_path = :image_path
            WHERE id = :id
        ");
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':price' => $price,
            ':description' => $description,
            ':image_path' => $uploadedPath !== null ? $uploadedPath : $currentImage,
            ':id' => $id,
        ]);

        if ($uploadedPath !== null && !empty($currentImage) && file_exists(__DIR__ . '/' . $currentImage)) {
            @unlink(__DIR__ . '/' . $currentImage);
        }
        $_SESSION['flash_success'] = 'Product updated successfully.';
        $updatedId = $id;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO menu_items (name, category, price, description, image_path)
            VALUES (:name, :category, :price, :description, :image_path)
        ");
        $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':price' => $price,
            ':description' => $description,
            ':image_path' => $uploadedPath,
        ]);
        $_SESSION['flash_success'] = 'Product added successfully.';
        $updatedId = (int)$pdo->lastInsertId();
    }

    if ($isAjax) {
        $fetch = $pdo->prepare("SELECT id, name, description, category, price, image_path FROM menu_items WHERE id = :id");
        $fetch->execute([':id' => $updatedId]);
        $row = $fetch->fetch();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $id > 0 ? 'Product updated successfully.' : 'Product added successfully.',
            'item'    => $row,
        ]);
        exit;
    }

    header('Location: products.php');
    exit;
} catch (Exception $e) {
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Save failed: ' . $e->getMessage(),
        ]);
        exit;
    }
    die('Save failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

