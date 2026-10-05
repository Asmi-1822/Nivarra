<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/db.php';
}

$stmt = $pdo->prepare("
    SELECT
        id,
        customer_id,
        email,
        username,
        email_verified,
        status
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$customerId]);

$currentCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentCustomer) {
    session_unset();
    session_destroy();

    header('Location: ../login.php');
    exit;
}

$customerStatus = strtolower(
    trim((string)($currentCustomer['status'] ?? ''))
);

if ($customerStatus !== 'active') {
    session_unset();
    session_destroy();

    header('Location: ../login.php');
    exit;
}

$_SESSION['customer_id'] = (int)$currentCustomer['id'];
$_SESSION['customer_code'] = (string)(
    $currentCustomer['customer_id'] ?? ''
);
$_SESSION['customer_username'] = (string)(
    $currentCustomer['username'] ?? ''
);
$_SESSION['customer_email'] = (string)(
    $currentCustomer['email'] ?? ''
);