<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer-only.php';
require_once __DIR__ . '/../includes/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$unavailableItems = [];
$orderPlaced = false;
$orderId = null;

/*
|--------------------------------------------------------------------------
| Customer Details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.email,
        c.username,
        cp.first_name,
        cp.last_name,
        cp.phone
    FROM customers c
    LEFT JOIN customer_profiles cp
        ON cp.customer_id = c.id
    WHERE c.id = ?
      AND c.status = 'active'
    LIMIT 1
");

$stmt->execute([$customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: ../login.php');
    exit;
}

$customerName = trim(
    (string)($customer['first_name'] ?? '') . ' ' .
    (string)($customer['last_name'] ?? '')
);

if ($customerName === '') {
    $customerName = (string)($customer['username'] ?? '');
}

/*
|--------------------------------------------------------------------------
| Read Cart
|--------------------------------------------------------------------------
| Expected session format:
| $_SESSION['cart'][menu_item_id] = quantity
| Array cart entries with menu_item_id/id and quantity/qty are
| also supported.
*/

function checkoutGetCart(): array
{
    $rawCart = $_SESSION['cart'] ?? [];

    if (!is_array($rawCart)) {
        return [];
    }

    $cart = [];

    foreach ($rawCart as $key => $value) {
        if (is_array($value)) {
            $itemId = (int)(
                $value['menu_item_id'] ??
                $value['id'] ??
                $key
            );

            $quantity = (int)(
                $value['quantity'] ??
                $value['qty'] ??
                0
            );
        } else {
            $itemId = (int)$key;
            $quantity = (int)$value;
        }

        if ($itemId > 0 && $quantity > 0) {
            $cart[$itemId] = ($cart[$itemId] ?? 0) + $quantity;
        }
    }

    return $cart;
}

$cart = checkoutGetCart();

/*
|--------------------------------------------------------------------------
| Load Current Menu Prices
|--------------------------------------------------------------------------
| Prices are always read from the database, never trusted from
| session data or submitted form fields.
*/

function checkoutLoadItems(PDO $pdo, array $cart): array
{
    if (empty($cart)) {
        return [];
    }

    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT id, name, description, image, price, category
        FROM menu_items
        WHERE active = 1
          AND id IN ($placeholders)
    ");

    $stmt->execute($ids);

    $items = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $id = (int)$item['id'];
        $quantity = (int)($cart[$id] ?? 0);

        if ($quantity <= 0) {
            continue;
        }

        $item['quantity'] = $quantity;
        $item['line_total'] = (float)$item['price'] * $quantity;

        $items[$id] = $item;
    }

    return $items;
}

$items = checkoutLoadItems($pdo, $cart);

/*
|--------------------------------------------------------------------------
| Validate Cart Availability
|--------------------------------------------------------------------------
*/

if (!empty($cart)) {
    foreach ($cart as $itemId => $quantity) {
        if (!isset($items[$itemId])) {
            $unavailableItems[] = $itemId;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Totals
|--------------------------------------------------------------------------
| Configure TAX_RATE in config.php as a decimal if tax should apply.
| Example: 0.05 represents 5%. The default is 0 until configured.
*/

$taxRate = defined('TAX_RATE') ? (float)TAX_RATE : 0.0;
$taxRate = max(0.0, $taxRate);

$subtotal = 0.0;

foreach ($items as $item) {
    $subtotal += (float)$item['line_total'];
}

$tax = round($subtotal * $taxRate, 2);
$total = round($subtotal + $tax, 2);

/*
|--------------------------------------------------------------------------
| Form Defaults
|--------------------------------------------------------------------------
*/

$tableNumber = trim((string)($_POST['table_number'] ?? ''));
$qrIdentifier = trim(
    (string)($_POST['qr_identifier'] ?? $_GET['qr'] ?? '')
);
$allergies = trim((string)($_POST['allergies'] ?? ''));
$specialNotes = trim((string)($_POST['special_notes'] ?? ''));

/*
|--------------------------------------------------------------------------
| Place Order
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['csrf_token'] ?? '');

        // Reload the cart and menu data at submission time.
        $cart = checkoutGetCart();

        if (empty($cart)) {
            throw new RuntimeException(
                'Your cart is empty. Please add items before checkout.'
            );
        }

        $items = checkoutLoadItems($pdo, $cart);

        if (count($items) !== count($cart)) {
            throw new RuntimeException(
                'Some items are no longer available. Please review your cart.'
            );
        }

        foreach ($cart as $itemId => $quantity) {
            if (!isset($items[$itemId])) {
                throw new RuntimeException(
                    'One or more items are unavailable. Please update your cart.'
                );
            }
        }

        if (count($cart) > 100) {
            throw new RuntimeException(
                'Your cart contains too many different items.'
            );
        }

        foreach ($cart as $quantity) {
            if ($quantity > 99) {
                throw new RuntimeException(
                    'An item quantity exceeds the allowed limit of 99.'
                );
            }
        }

        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float)$item['price'] * (int)$item['quantity'];
        }

        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax, 2);

        if ($subtotal <= 0 || $total <= 0) {
            throw new RuntimeException(
                'Your order total is invalid. Please review your cart.'
            );
        }

        if (strlen($tableNumber) > 30) {
            throw new RuntimeException(
                'The table number is too long.'
            );
        }

        if (strlen($qrIdentifier) > 255) {
            throw new RuntimeException(
                'The QR identifier is too long.'
            );
        }

        if (strlen($allergies) > 2000 ||
            strlen($specialNotes) > 2000) {
            throw new RuntimeException(
                'Allergies and special notes must not exceed 2000 characters each.'
            );
        }

        $tableNumberValue = $tableNumber !== ''
            ? $tableNumber
            : null;

        $qrIdentifierValue = $qrIdentifier !== ''
            ? $qrIdentifier
            : null;

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Recheck customer status
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM customers
            WHERE id = ?
              AND status = 'active'
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$customerId]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Your customer account is not active.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Insert Order
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO orders (
                table_number,
                qr_identifier,
                allergies,
                special_notes,
                status,
                subtotal,
                tax,
                total,
                customer_id
            ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)
        ");

        $stmt->execute([
            $tableNumberValue,
            $qrIdentifierValue,
            $allergies !== '' ? $allergies : null,
            $specialNotes !== '' ? $specialNotes : null,
            $subtotal,
            $tax,
            $total,
            $customerId
        ]);

        $orderId = (int)$pdo->lastInsertId();

        /*
        |--------------------------------------------------------------------------
        | Insert Order Items
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id,
                menu_item_id,
                quantity,
                unit_price
            ) VALUES (?, ?, ?, ?)
        ");

        foreach ($items as $itemId => $item) {
            $stmt->execute([
                $orderId,
                (int)$itemId,
                (int)$item['quantity'],
                (float)$item['price']
            ]);
        }

        $pdo->commit();

        /*
        |--------------------------------------------------------------------------
        | Clear Cart After Successful Commit
        |--------------------------------------------------------------------------
        */

        $_SESSION['cart'] = [];

        $orderPlaced = true;
        $cart = [];
        $items = [];

        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'NIVARRA checkout error: ' . $e->getMessage()
        );

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to place your order. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/customer-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-0 nivarra-title">
            Checkout
        </h2>

        <a href="dashboard.php"
           class="btn btn-secondary d-flex align-items-center justify-content-center"
           style="width:58.66px;height:38px;padding:0;">
            Back
        </a>

    </div>

    <?php if ($orderPlaced): ?>

        <div class="card shadow-sm">
            <div class="card-body text-center py-5">

                <div class="text-success mb-3">
                    <i class="bi bi-check-circle-fill"
                       style="font-size:3rem;"></i>
                </div>

                <h3 class="mb-2">Order Placed Successfully!</h3>

                <p class="text-muted">
                    Thank you for ordering from NIVARRA.
                </p>

                <p class="mb-4">
                    Your order number is
                    <strong>#<?= (int)$orderId ?></strong>.
                </p>

                <div class="d-flex justify-content-center gap-2 flex-wrap">

                    <a href="dashboard.php" class="btn btn-maroon">
                        Go to Dashboard
                    </a>

                    <a href="menu.php" class="btn btn-outline-secondary">
                        Continue Browsing
                    </a>

                </div>

            </div>
        </div>

    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cart)): ?>

            <div class="card shadow-sm">
                <div class="card-body text-center py-5">

                    <h5 class="mb-2">Your Cart Is Empty</h5>

                    <p class="text-muted">
                        Add some items from our menu before checking out.
                    </p>

                    <a href="menu.php" class="btn btn-maroon">
                        Browse Menu
                    </a>

                </div>
            </div>

        <?php else: ?>

            <?php if (!empty($unavailableItems)): ?>
                <div class="alert alert-warning">
                    Some items in your cart are no longer available.
                    Please update your cart before placing your order.
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <?= csrf_input() ?>

                <input type="hidden"
                       name="qr_identifier"
                       value="<?= e($qrIdentifier) ?>">

                <div class="row g-4">

                    <!-- Customer and Order Details -->
                    <div class="col-lg-7">

                        <div class="card shadow-sm mb-4">

                            <div class="card-header bg-white">
                                <h5 class="mb-0">Customer Details</h5>
                            </div>

                            <div class="card-body">

                                <div class="mb-3">
                                    <label class="form-label">
                                        Name
                                    </label>

                                    <input type="text"
                                           class="form-control"
                                           value="<?= e($customerName) ?>"
                                           readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        Email Address
                                    </label>

                                    <input type="email"
                                           class="form-control"
                                           value="<?= e((string)$customer['email']) ?>"
                                           readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="table_number"
                                           class="form-label">
                                        Table Number
                                        <span class="text-muted">(optional)</span>
                                    </label>

                                    <input type="text"
                                           class="form-control"
                                           id="table_number"
                                           name="table_number"
                                           maxlength="30"
                                           value="<?= e($tableNumber) ?>"
                                           placeholder="Enter your table number">
                                </div>

                            </div>

                        </div>

                        <div class="card shadow-sm">

                            <div class="card-header bg-white">
                                <h5 class="mb-0">Order Notes</h5>
                            </div>

                            <div class="card-body">

                                <div class="mb-3">
                                    <label for="allergies"
                                           class="form-label">
                                        Allergies or Dietary Requirements
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="allergies"
                                        name="allergies"
                                        rows="3"
                                        maxlength="2000"
                                        placeholder="Tell us about any food allergies or dietary requirements"
                                    ><?= e($allergies) ?></textarea>
                                </div>

                                <div class="mb-0">
                                    <label for="special_notes"
                                           class="form-label">
                                        Special Instructions
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="special_notes"
                                        name="special_notes"
                                        rows="3"
                                        maxlength="2000"
                                        placeholder="Any additional instructions for your order"
                                    ><?= e($specialNotes) ?></textarea>
                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- Order Summary -->
                    <div class="col-lg-5">

                        <div class="card shadow-sm">

                            <div class="card-header bg-white">
                                <h5 class="mb-0">Order Summary</h5>
                            </div>

                            <div class="card-body">

                                <?php if (empty($items)): ?>

                                    <p class="text-muted mb-0">
                                        No available items to display.
                                    </p>

                                <?php else: ?>

                                    <?php foreach ($items as $item): ?>

                                        <div class="d-flex justify-content-between gap-3 mb-3">

                                            <div>
                                                <div class="fw-semibold">
                                                    <?= e((string)$item['name']) ?>
                                                </div>

                                                <div class="small text-muted">
                                                    <?= (int)$item['quantity'] ?>
                                                    × ₹<?= number_format(
                                                        (float)$item['price'],
                                                        2
                                                    ) ?>
                                                </div>
                                            </div>

                                            <div class="text-end fw-semibold">
                                                ₹<?= number_format(
                                                    (float)$item['line_total'],
                                                    2
                                                ) ?>
                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                    <hr>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal</span>

                                        <span>
                                            ₹<?= number_format($subtotal, 2) ?>
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax</span>

                                        <span>
                                            ₹<?= number_format($tax, 2) ?>
                                        </span>
                                    </div>

                                    <hr>

                                    <div class="d-flex justify-content-between mb-3">
                                        <strong>Total</strong>

                                        <strong class="text-danger fs-5">
                                            ₹<?= number_format($total, 2) ?>
                                        </strong>
                                    </div>

                                    <p class="small text-muted">
                                        Payment is not collected on this page.
                                        Payment processing can be connected
                                        separately.
                                    </p>

                                    <button type="submit"
                                            class="btn btn-maroon w-100"
                                            <?= !empty($unavailableItems) ? 'disabled' : '' ?>>
                                        <i class="bi bi-bag-check me-1"></i>
                                        Place Order
                                    </button>

                                    <a href="menu.php"
                                       class="btn btn-outline-secondary w-100 mt-2">
                                        Continue Shopping
                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            </form>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>