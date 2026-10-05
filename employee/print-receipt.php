<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';

$pageTitle = 'Print Receipt';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);

if ($employeeId <= 0) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Receipt ID
|--------------------------------------------------------------------------
*/

$receiptId = (int)($_GET['id'] ?? 0);

if ($receiptId <= 0) {
    header('Location: kitchen-orders.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Receipt
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.receipt_number,
        r.order_id,
        r.customer_is,
        r.employee_id,
        r.subtotal,
        r.discount_amount,
        r.tax_amount,
        r.service_charge,
        r.total_amount,
        r.payment_method,
        r.payment_status,
        r.notes,
        r.created_at,

        o.table_number,
        o.qr_identifier,
        o.allergies,
        o.special_notes,
        o.status AS order_status,
        o.customer_id

    FROM receipts r

    LEFT JOIN orders o
        ON o.id = r.order_id

    WHERE r.id = ?

    LIMIT 1
");

$stmt->execute([$receiptId]);

$receipt =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    header('Location: kitchen-orders.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Receipt Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        receipt_id,
        menu_item_id,
        item_name,
        quantity,
        unit_price,
        line_total,
        notes
    FROM receipt_items
    WHERE receipt_id = ?
    ORDER BY id ASC
");

$stmt->execute([$receiptId]);

$items =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-start mb-4 no-print">

        <div>

            <h2 class="mb-0">
                Print Receipt
            </h2>

            <p class="text-muted mb-0 mt-1">
                Receipt #<?= e(
                    (string)$receipt['receipt_number']
                ) ?>
            </p>

        </div>

        <div class="d-flex gap-2">

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-maroon">

                Print Receipt

            </button>

            <a
                href="kitchen-orders.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>


    <!-- Receipt -->

    <div class="receipt-wrapper">

        <div class="receipt">

            <!-- Restaurant Header -->

            <div class="text-center mb-4">

                <h2 class="fw-bold mb-1">
                    NIVARRA
                </h2>

                <div class="text-muted">
                    Restaurant
                </div>

                <hr>

            </div>


            <!-- Receipt Information -->

            <div class="row g-2 mb-3">

                <div class="col-6">

                    <strong>
                        Receipt:
                    </strong>

                    <br>

                    <?= e(
                        (string)$receipt['receipt_number']
                    ) ?>

                </div>


                <div class="col-6 text-end">

                    <strong>
                        Order:
                    </strong>

                    <br>

                    #<?= (int)$receipt['order_id'] ?>

                </div>


                <div class="col-6">

                    <strong>
                        Table:
                    </strong>

                    <br>

                    <?= e(
                        (string)(
                            $receipt['table_number']
                            ?? 'N/A'
                        )
                    ) ?>

                </div>


                <div class="col-6 text-end">

                    <strong>
                        Date:
                    </strong>

                    <br>

                    <?php

                    $createdAt =
                        strtotime(
                            (string)$receipt['created_at']
                        );

                    ?>

                    <?php if (
                        $createdAt !== false
                    ): ?>

                        <?= e(
                            date(
                                'd M Y',
                                $createdAt
                            )
                        ) ?>

                        <br>

                        <?= e(
                            date(
                                'h:i A',
                                $createdAt
                            )
                        ) ?>

                    <?php else: ?>

                        <?= e(
                            (string)$receipt['created_at']
                        ) ?>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Items -->

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>

                            <th>
                                Item
                            </th>

                            <th
                                class="text-center">

                                Qty

                            </th>

                            <th
                                class="text-end">

                                Price

                            </th>

                            <th
                                class="text-end">

                                Total

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!$items): ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center text-muted">

                                    No receipt items found.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($items as $item): ?>

                                <tr>

                                    <td>

                                        <strong>

                                            <?= e(
                                                (string)$item['item_name']
                                            ) ?>

                                        </strong>

                                        <?php if (
                                            trim(
                                                (string)(
                                                    $item['notes']
                                                    ?? ''
                                                )
                                            ) !== ''
                                        ): ?>

                                            <div class="small text-muted">

                                                <?= nl2br(
                                                    e(
                                                        (string)$item['notes']
                                                    )
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <td
                                        class="text-center">

                                        <?= (int)$item['quantity'] ?>

                                    </td>


                                    <td
                                        class="text-end">

                                        Rs.

                                        <?= number_format(
                                            (float)$item['unit_price'],
                                            2
                                        ) ?>

                                    </td>


                                    <td
                                        class="text-end">

                                        Rs.

                                        <?= number_format(
                                            (float)$item['line_total'],
                                            2
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- Totals -->

            <div class="border-top pt-3">

                <div class="d-flex justify-content-between mb-2">

                    <span>
                        Subtotal
                    </span>

                    <strong>

                        Rs.

                        <?= number_format(
                            (float)$receipt['subtotal'],
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="d-flex justify-content-between mb-2">

                    <span>
                        Discount
                    </span>

                    <strong>

                        Rs.

                        <?= number_format(
                            (float)$receipt['discount_amount'],
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="d-flex justify-content-between mb-2">

                    <span>
                        Tax
                    </span>

                    <strong>

                        Rs.

                        <?= number_format(
                            (float)$receipt['tax_amount'],
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="d-flex justify-content-between mb-2">

                    <span>
                        Service Charge
                    </span>

                    <strong>

                        Rs.

                        <?= number_format(
                            (float)$receipt['service_charge'],
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="d-flex justify-content-between border-top pt-2">

                    <strong class="fs-5">
                        Total
                    </strong>

                    <strong class="fs-5">

                        Rs.

                        <?= number_format(
                            (float)$receipt['total_amount'],
                            2
                        ) ?>

                    </strong>

                </div>

            </div>


            <!-- Payment -->

            <div class="mt-4">

                <div class="d-flex justify-content-between">

                    <span>
                        Payment Method
                    </span>

                    <strong>

                        <?= e(
                            ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string)(
                                        $receipt['payment_method']
                                        ?? 'N/A'
                                    )
                                )
                            )
                        ) ?>

                    </strong>

                </div>


                <div class="d-flex justify-content-between mt-2">

                    <span>
                        Payment Status
                    </span>

                    <strong>

                        <?= e(
                            ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    (string)(
                                        $receipt['payment_status']
                                        ?? 'N/A'
                                    )
                                )
                            )
                        ) ?>

                    </strong>

                </div>

            </div>


            <!-- Order Notes -->

            <?php if (
                trim(
                    (string)(
                        $receipt['allergies']
                        ?? ''
                    )
                ) !== ''
            ): ?>

                <div class="alert alert-danger mt-4">

                    <strong>
                        Allergies:
                    </strong>

                    <br>

                    <?= nl2br(
                        e(
                            (string)$receipt['allergies']
                        )
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (
                trim(
                    (string)(
                        $receipt['special_notes']
                        ?? ''
                    )
                ) !== ''
            ): ?>

                <div class="alert alert-warning mt-3">

                    <strong>
                        Special Notes:
                    </strong>

                    <br>

                    <?= nl2br(
                        e(
                            (string)$receipt['special_notes']
                        )
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- Receipt Notes -->

            <?php if (
                trim(
                    (string)(
                        $receipt['notes']
                        ?? ''
                    )
                ) !== ''
            ): ?>

                <div class="mt-3">

                    <strong>
                        Receipt Notes:
                    </strong>

                    <p class="mb-0">

                        <?= nl2br(
                            e(
                                (string)$receipt['notes']
                            )
                        ) ?>

                    </p>

                </div>

            <?php endif; ?>


            <!-- Footer -->

            <div class="text-center mt-4 pt-3 border-top">

                <p class="mb-1 fw-semibold">
                    Thank you for dining with NIVARRA!
                </p>

                <small class="text-muted">
                    Please visit us again.
                </small>

            </div>

        </div>

    </div>

</div>


<style>

.receipt-wrapper {
    display: flex;
    justify-content: center;
}

.receipt {
    width: 100%;
    max-width: 800px;
    background: #ffffff;
    padding: 30px;
    border: 1px solid #dee2e6;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

@media print {

    @page {
        margin: 12mm;
    }

    body {
        background: #ffffff !important;
    }

    .no-print,
    nav,
    .navbar,
    header,
    footer {
        display: none !important;
    }

    .container,
    .container-fluid {
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .receipt-wrapper {
        display: block;
    }

    .receipt {
        max-width: 100%;
        border: none;
        box-shadow: none;
        padding: 0;
    }

    .alert {
        border: 1px solid #000 !important;
    }

    .table {
        border-collapse: collapse !important;
    }

    .table th,
    .table td {
        border-color: #000 !important;
    }

}

</style>

<?php include '../includes/footer.php'; ?>