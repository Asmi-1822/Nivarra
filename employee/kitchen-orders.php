<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Kitchen Orders';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);

if ($employeeId <= 0) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Update Order Status
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_status'])
) {

    verify_csrf();

    $orderId =
        (int)($_POST['order_id'] ?? 0);

    $newStatus =
        trim(
            (string)($_POST['status'] ?? '')
        );

    if (
        $orderId > 0
        && $newStatus !== ''
    ) {

        /*
        |--------------------------------------------------------------------------
        | Get Existing Status Values
        |--------------------------------------------------------------------------
        */

        $statusStmt = $pdo->query("
            SELECT DISTINCT status
            FROM orders
            WHERE status IS NOT NULL
              AND status <> ''
            ORDER BY status
        ");

        $allowedStatuses =
            $statusStmt->fetchAll(
                PDO::FETCH_COLUMN
            );

        /*
        |--------------------------------------------------------------------------
        | Only Allow Existing Database Status Values
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $newStatus,
                $allowedStatuses,
                true
            )
        ) {

            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $newStatus,
                $orderId
            ]);
        }
    }

    redirect('kitchen-orders.php');
}

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

$status =
    trim(
        (string)($_GET['status'] ?? '')
    );

/*
|--------------------------------------------------------------------------
| Available Statuses
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT DISTINCT status
    FROM orders
    WHERE status IS NOT NULL
      AND status <> ''
    ORDER BY status
");

$statuses =
    $stmt->fetchAll(
        PDO::FETCH_COLUMN
    );

/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/

$sql = "
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
        o.customer_id,

        oi.id AS order_item_id,
        oi.menu_item_id,
        oi.quantity,
        oi.unit_price,

        mi.name AS menu_item_name

    FROM orders o

    LEFT JOIN order_items oi
        ON oi.order_id = o.id

    LEFT JOIN menu_items mi
        ON mi.id = oi.menu_item_id
";

$params = [];

if (
    $status !== ''
    && in_array(
        $status,
        $statuses,
        true
    )
) {

    $sql .= "
        WHERE o.status = ?
    ";

    $params[] = $status;
}

$sql .= "
    ORDER BY
        o.created_at ASC,
        o.id ASC,
        oi.id ASC
";

$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$rows =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| Group Order Items
|--------------------------------------------------------------------------
*/

$orders = [];

foreach ($rows as $row) {

    $orderId =
        (int)$row['id'];

    if (!isset($orders[$orderId])) {

        $orders[$orderId] = [
            'id' =>
                $orderId,

            'table_number' =>
                $row['table_number'],

            'qr_identifier' =>
                $row['qr_identifier'],

            'allergies' =>
                $row['allergies'],

            'special_notes' =>
                $row['special_notes'],

            'status' =>
                $row['status'],

            'subtotal' =>
                $row['subtotal'],

            'tax' =>
                $row['tax'],

            'total' =>
                $row['total'],

            'created_at' =>
                $row['created_at'],

            'customer_id' =>
                $row['customer_id'],

            'items' => []
        ];
    }

    if (
        !empty($row['order_item_id'])
    ) {

        $orders[$orderId]['items'][] = [
            'id' =>
                (int)$row['order_item_id'],

            'menu_item_id' =>
                (int)$row['menu_item_id'],

            'name' =>
                $row['menu_item_name']
                ?? 'Menu Item',

            'quantity' =>
                (int)$row['quantity'],

            'unit_price' =>
                (float)$row['unit_price']
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalOrders =
    count($orders);

$activeFilterCount =
    0;

foreach ($orders as $order) {

    if (
        $status !== ''
        && (string)$order['status'] === $status
    ) {

        $activeFilterCount++;
    }
}

$pageTitle = 'Kitchen Orders';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-0">
                Kitchen Orders
            </h2>

            <p class="text-muted mb-0 mt-1">
                View and manage restaurant orders
            </p>

        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>


    <!-- Filters -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div class="d-flex gap-2 flex-wrap">

            <a
                href="kitchen-orders.php"
                class="btn btn-outline-secondary btn-sm">

                All

            </a>

            <?php foreach ($statuses as $orderStatus): ?>

                <a
                    href="?status=<?= urlencode(
                        (string)$orderStatus
                    ) ?>"
                    class="btn btn-outline-primary btn-sm">

                    <?= e(
                        ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                (string)$orderStatus
                            )
                        )
                    ) ?>

                </a>

            <?php endforeach; ?>

        </div>


        <span class="badge bg-primary">

            <?= $totalOrders ?>

            Order<?= $totalOrders === 1 ? '' : 's' ?>

        </span>

    </div>


    <!-- Orders -->

    <?php if (!$orders): ?>

        <div class="alert alert-info">

            No kitchen orders found.

        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($orders as $order): ?>

                <?php

                $createdAt =
                    strtotime(
                        (string)$order['created_at']
                    );

                $statusText =
                    (string)($order['status'] ?? '');

                ?>

                <div class="col-xl-6">

                    <div class="card shadow-sm h-100">

                        <!-- Order Header -->

                        <div class="card-header">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <h5 class="mb-1">

                                        Order #<?= (int)$order['id'] ?>

                                    </h5>

                                    <div class="text-muted small">

                                        Table:
                                        <strong>

                                            <?= e(
                                                (string)(
                                                    $order['table_number']
                                                    ?? 'N/A'
                                                )
                                            ) ?>

                                        </strong>

                                    </div>

                                </div>


                                <span class="badge bg-primary">

                                    <?= e(
                                        ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $statusText
                                            )
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <div class="card-body">

                            <!-- Order Information -->

                            <div class="row g-3 mb-3">

                                <div class="col-md-6">

                                    <small class="text-muted d-block">

                                        Order Time

                                    </small>

                                    <strong>

                                        <?php if (
                                            $createdAt !== false
                                        ): ?>

                                            <?= e(
                                                date(
                                                    'd M Y, h:i A',
                                                    $createdAt
                                                )
                                            ) ?>

                                        <?php else: ?>

                                            <?= e(
                                                (string)$order['created_at']
                                            ) ?>

                                        <?php endif; ?>

                                    </strong>

                                </div>


                                <div class="col-md-6">

                                    <small class="text-muted d-block">

                                        QR Identifier

                                    </small>

                                    <strong>

                                        <?= e(
                                            (string)(
                                                $order['qr_identifier']
                                                ?? 'N/A'
                                            )
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <!-- Allergies -->

                            <?php if (
                                trim(
                                    (string)(
                                        $order['allergies']
                                        ?? ''
                                    )
                                ) !== ''
                            ): ?>

                                <div class="alert alert-danger">

                                    <strong>

                                        Allergies:

                                    </strong>

                                    <?= nl2br(
                                        e(
                                            (string)$order['allergies']
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <!-- Special Notes -->

                            <?php if (
                                trim(
                                    (string)(
                                        $order['special_notes']
                                        ?? ''
                                    )
                                ) !== ''
                            ): ?>

                                <div class="alert alert-warning">

                                    <strong>

                                        Special Notes:

                                    </strong>

                                    <?= nl2br(
                                        e(
                                            (string)$order['special_notes']
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <!-- Order Items -->

                            <h6 class="mb-3">

                                Order Items

                            </h6>

                            <div class="table-responsive">

                                <table class="table table-sm align-middle mb-3">

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

                                                Unit Price

                                            </th>

                                            <th
                                                class="text-end">

                                                Total

                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php if (
                                            !$order['items']
                                        ): ?>

                                            <tr>

                                                <td
                                                    colspan="4"
                                                    class="text-center text-muted">

                                                    No items found.

                                                </td>

                                            </tr>

                                        <?php else: ?>

                                            <?php foreach (
                                                $order['items']
                                                as $item
                                            ): ?>

                                                <tr>

                                                    <td>

                                                        <?= e(
                                                            (string)$item['name']
                                                        ) ?>

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
                                                            (float)$item['unit_price']
                                                            *
                                                            (int)$item['quantity'],
                                                            2
                                                        ) ?>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>


                            <!-- Order Totals -->

                            <div class="border-top pt-3">

                                <div class="d-flex justify-content-between mb-1">

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['subtotal'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="d-flex justify-content-between mb-1">

                                    <span>
                                        Tax
                                    </span>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['tax'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="d-flex justify-content-between">

                                    <strong>
                                        Total
                                    </strong>

                                    <strong>

                                        Rs.

                                        <?= number_format(
                                            (float)$order['total'],
                                            2
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <!-- Status -->

                            <?php if ($statuses): ?>

                                <form
                                    method="POST"
                                    class="mt-3">

                                    <?= csrf_input() ?>

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= (int)$order['id'] ?>">

                                    <div class="input-group">

                                        <select
                                            name="status"
                                            class="form-select"
                                            required>

                                            <?php foreach (
                                                $statuses
                                                as $availableStatus
                                            ): ?>

                                                <option
                                                    value="<?= e(
                                                        (string)$availableStatus
                                                    ) ?>"
                                                    <?= (string)$availableStatus === $statusText
                                                        ? 'selected'
                                                        : '' ?>>

                                                    <?= e(
                                                        ucfirst(
                                                            str_replace(
                                                                '_',
                                                                ' ',
                                                                (string)$availableStatus
                                                            )
                                                        )
                                                    ) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button
                                            type="submit"
                                            name="update_status"
                                            class="btn btn-maroon">

                                            Update Status

                                        </button>

                                    </div>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<script>
    setTimeout(function () {
        window.location.reload();
    }, 5000);
</script>

<?php include '../includes/footer.php'; ?>