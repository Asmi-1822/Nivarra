<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer-only.php';

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Customer Orders
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.table_number,
        o.qr_identifier,
        o.allergies,
        o.special_notes,
        o.status,
        o.subtotal,
        o.tax,
        o.total,
        o.created_at,
        COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi
        ON oi.order_id = o.id
    WHERE o.customer_id = ?
    GROUP BY
        o.id,
        o.table_number,
        o.qr_identifier,
        o.allergies,
        o.special_notes,
        o.status,
        o.subtotal,
        o.tax,
        o.total,
        o.created_at
    ORDER BY o.created_at DESC
");

$stmt->execute([$customerId]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/customer-navbar.php';
?>

<!-- =========================================================
     Main Content
========================================================= -->

<div class="container py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div class="d-flex align-items-center gap-3">

            <h2 class="mb-0">
                My Orders
            </h2>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">
            <a
                href="menu.php"
                class="btn btn-danger">
                Browse Menu
            </a>

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">
                Back
            </a>

        </div>

    </div>

    


    <!-- =====================================================
         Orders
    ====================================================== -->

    <?php if (empty($orders)): ?>

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <h5 class="mb-2">
                    No Orders Found
                </h5>

                <p class="text-muted mb-4">
                    You have not placed any orders yet.
                </p>

                <a
                    href="menu.php"
                    class="btn btn-danger">
                    Browse Menu
                </a>

            </div>

        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($orders as $order): ?>

                <?php
                $status = (string)($order['status'] ?? '');
                $itemCount = (int)($order['item_count'] ?? 0);

                $total = (float)($order['total'] ?? 0);

                $createdAt = $order['created_at'] ?? '';

                $formattedDate = '';

                if ($createdAt !== '') {
                    $timestamp = strtotime((string)$createdAt);

                    if ($timestamp !== false) {
                        $formattedDate = date(
                            'd M Y, h:i A',
                            $timestamp
                        );
                    }
                }
                ?>

                <div class="col-12">

                    <div class="card shadow-sm">

                        <div class="card-body">

                            <div class="row align-items-center g-3">

                                <!-- Order Information -->

                                <div class="col-md-4">

                                    <h5 class="mb-1">

                                        Order #<?= (int)$order['id'] ?>

                                    </h5>

                                    <?php if ($formattedDate !== ''): ?>

                                        <div class="text-muted small">
                                            <?= e($formattedDate) ?>
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- Items -->

                                <div class="col-md-2">

                                    <div class="text-muted small">
                                        Items
                                    </div>

                                    <strong>
                                        <?= $itemCount ?>
                                    </strong>

                                </div>


                                <!-- Table / QR -->

                                <div class="col-md-2">

                                    <?php if (
                                        isset($order['table_number']) &&
                                        $order['table_number'] !== ''
                                    ): ?>

                                        <div class="text-muted small">
                                            Table
                                        </div>

                                        <strong>
                                            <?= e((string)$order['table_number']) ?>
                                        </strong>

                                    <?php elseif (
                                        isset($order['qr_identifier']) &&
                                        $order['qr_identifier'] !== ''
                                    ): ?>

                                        <div class="text-muted small">
                                            QR
                                        </div>

                                        <strong>
                                            <?= e((string)$order['qr_identifier']) ?>
                                        </strong>

                                    <?php else: ?>

                                        <div class="text-muted small">
                                            Order Type
                                        </div>

                                        <strong>
                                            Online
                                        </strong>

                                    <?php endif; ?>

                                </div>


                                <!-- Total -->

                                <div class="col-md-2">

                                    <div class="text-muted small">
                                        Total
                                    </div>

                                    <strong>
                                        ₹<?= number_format($total, 2) ?>
                                    </strong>

                                </div>


                                <!-- Status -->

                                <div class="col-md-2 text-md-end">

                                    <span
                                        class="badge <?= e(orderStatusClass($status)) ?> mb-2">

                                        <?= e(orderStatusLabel($status)) ?>

                                    </span>

                                    <br>

                                    <a
                                        href="order-details.php?id=<?= (int)$order['id'] ?>"
                                        class="btn btn-sm btn-outline-danger">

                                        View Details

                                    </a>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


</body>
</html>