<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin' && (($_SESSION['username'] ?? '') !== 'admin'))) {
    http_response_code(401);
    die('Not authorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status = $_POST['status'] ?? '';
$paymentStatus = $_POST['payment_status'] ?? '';

$validStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
$validPayment = ['PAID', 'UNPAID'];

if ($orderId <= 0 || !in_array($status, $validStatuses, true) || !in_array($paymentStatus, $validPayment, true)) {
    http_response_code(400);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    die('Invalid request');
}

$stmt = $pdo->prepare("UPDATE orders SET status = :status, payment_status = :pstatus WHERE id = :id");
$stmt->execute([
    ':status'   => $status,
    ':pstatus'  => $paymentStatus,
    ':id'       => $orderId,
]);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully.',
        'status'  => $status,
        'payment_status' => $paymentStatus,
    ]);
    exit;
}

header('Location: orders.php');
exit;

