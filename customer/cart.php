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
| Initialize Cart
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['cart']) ||
    !is_array($_SESSION['cart'])
) {
    $_SESSION['cart'] = [];
}


/*
|--------------------------------------------------------------------------
| Handle Cart Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim(
        (string)($_POST['action'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | Add Item
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        $menuItemId = (int)(
            $_POST['menu_item_id'] ?? 0
        );

        $quantity = (int)(
            $_POST['quantity'] ?? 1
        );

        if ($menuItemId <= 0) {
            header('Location: cart.php');
            exit;
        }

        if ($quantity < 1) {
            $quantity = 1;
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Menu Item
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                description,
                price,
                image,
                category,
                dietary_badges,
                active
            FROM menu_items
            WHERE id = ?
              AND active = 1
            LIMIT 1
        ");

        $stmt->execute([$menuItemId]);

        $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($menuItem) {

            if (
                isset($_SESSION['cart'][$menuItemId]) &&
                is_array($_SESSION['cart'][$menuItemId])
            ) {

                $_SESSION['cart'][$menuItemId]['quantity']
                    += $quantity;

            } else {

                $_SESSION['cart'][$menuItemId] = [
                    'menu_item_id' => (int)$menuItem['id'],
                    'quantity' => $quantity
                ];
            }
        }

        header('Location: cart.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Update Cart
    |--------------------------------------------------------------------------
    */

    if ($action === 'update') {

        $quantities = $_POST['quantity'] ?? [];

        if (is_array($quantities)) {

            foreach ($quantities as $menuItemId => $quantity) {

                $menuItemId = (int)$menuItemId;
                $quantity = (int)$quantity;

                if ($menuItemId <= 0) {
                    continue;
                }

                if ($quantity <= 0) {

                    unset(
                        $_SESSION['cart'][$menuItemId]
                    );

                    continue;
                }

                if (
                    isset($_SESSION['cart'][$menuItemId]) &&
                    is_array($_SESSION['cart'][$menuItemId])
                ) {

                    $_SESSION['cart'][$menuItemId]['quantity']
                        = $quantity;
                }
            }
        }

        header('Location: cart.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove') {

        $menuItemId = (int)(
            $_POST['menu_item_id'] ?? 0
        );

        if ($menuItemId > 0) {

            unset(
                $_SESSION['cart'][$menuItemId]
            );
        }

        header('Location: cart.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Clear Cart
    |--------------------------------------------------------------------------
    */

    if ($action === 'clear') {

        $_SESSION['cart'] = [];

        header('Location: cart.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Load Cart Items From Database
|--------------------------------------------------------------------------
*/

$cartItems = [];

$subtotal = 0.00;

if (!empty($_SESSION['cart'])) {

    $menuItemIds = [];

    foreach ($_SESSION['cart'] as $menuItemId => $cartItem) {

        $menuItemId = (int)$menuItemId;

        if (
            $menuItemId <= 0 ||
            !is_array($cartItem)
        ) {
            continue;
        }

        $menuItemIds[] = $menuItemId;
    }

    $menuItemIds = array_values(
        array_unique($menuItemIds)
    );

    if (!empty($menuItemIds)) {

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($menuItemIds),
                '?'
            )
        );

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                description,
                image,
                price,
                category,
                dietary_badges,
                active
            FROM menu_items
            WHERE id IN ($placeholders)
              AND active = 1
            ORDER BY name ASC
        ");

        $stmt->execute($menuItemIds);

        $menuItems = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        /*
        |--------------------------------------------------------------------------
        | Build Cart
        |--------------------------------------------------------------------------
        */

        $foundIds = [];

        foreach ($menuItems as $menuItem) {

            $menuItemId = (int)$menuItem['id'];

            $foundIds[$menuItemId] = true;

            $quantity = (int)(
                $_SESSION['cart'][$menuItemId]['quantity']
                ?? 0
            );

            if ($quantity <= 0) {
                continue;
            }

            $unitPrice = (float)(
                $menuItem['price'] ?? 0
            );

            $lineTotal = $unitPrice * $quantity;

            $cartItems[] = [
                'id' => $menuItemId,
                'name' => (string)(
                    $menuItem['name'] ?? ''
                ),
                'description' => (string)(
                    $menuItem['description'] ?? ''
                ),
                'image' => (string)(
                    $menuItem['image'] ?? ''
                ),
                'price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'category' => (string)(
                    $menuItem['category'] ?? ''
                ),
                'dietary_badges' => (string)(
                    $menuItem['dietary_badges'] ?? ''
                )
            ];

            $subtotal += $lineTotal;
        }

        /*
        |--------------------------------------------------------------------------
        | Remove Items That Are No Longer Active
        |--------------------------------------------------------------------------
        */

        foreach ($menuItemIds as $menuItemId) {

            if (!isset($foundIds[$menuItemId])) {

                unset(
                    $_SESSION['cart'][$menuItemId]
                );
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Calculate Tax and Total
|--------------------------------------------------------------------------
|
| The existing orders table contains subtotal, tax and total.
| No tax percentage was provided in the schema, so this page
| uses 0% until the application's tax rate is defined.
|
*/

$taxRate = 0.00;

$tax = round(
    $subtotal * ($taxRate / 100),
    2
);

$total = round(
    $subtotal + $tax,
    2
);


/*
|--------------------------------------------------------------------------
| Cart Count
|--------------------------------------------------------------------------
*/

$cartCount = 0;

foreach ($cartItems as $cartItem) {

    $cartCount += (int)(
        $cartItem['quantity'] ?? 0
    );
}

include '../includes/header.php';
include '../includes/customer-navbar.php';

?>

<!-- =========================================================
     Main Content
========================================================= -->

<div class="container py-4">


    <!-- Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <h2 class="mb-0">
                My Cart
            </h2>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <span class="badge bg-danger fs-6">

                <?= $cartCount ?>

                <?= $cartCount === 1 ? 'Item' : 'Items' ?>

            </span>

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">
                Back
            </a>

        </div>

    </div>


    <?php if (empty($cartItems)): ?>

        <!-- =================================================
             Empty Cart
        ================================================== -->

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <h5 class="mb-2">
                    Your Cart is Empty
                </h5>

                <p class="text-muted mb-4">
                    Browse our menu and add your favourite items.
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


            <!-- =================================================
                 Cart Items
            ================================================== -->

            <div class="col-lg-8">

                <form
                    method="post"
                    action="cart.php">

                    <input
                        type="hidden"
                        name="action"
                        value="update">

                    <?php foreach ($cartItems as $item): ?>

                        <?php

                        $itemId = (int)$item['id'];

                        $imagePath = cartImagePath(
                            $item['image']
                        );

                        ?>

                        <div class="card shadow-sm mb-3">

                            <div class="card-body">

                                <div class="row align-items-center g-3">


                                    <!-- Image -->

                                    <div class="col-auto">

                                        <?php if (
                                            $imagePath !== null
                                        ): ?>

                                            <img
                                                src="<?= e($imagePath) ?>"
                                                alt="<?= e(
                                                    $item['name']
                                                ) ?>"
                                                class="cart-image">

                                        <?php else: ?>

                                            <div
                                                class="cart-placeholder">

                                                NIVARRA

                                            </div>

                                        <?php endif; ?>

                                    </div>


                                    <!-- Item Information -->

                                    <div class="col">

                                        <h5 class="mb-1">

                                            <?= e(
                                                $item['name']
                                            ) ?>

                                        </h5>

                                        <?php if (
                                            trim(
                                                $item['description']
                                            ) !== ''
                                        ): ?>

                                            <p class="text-muted small mb-2">

                                                <?= e(
                                                    $item['description']
                                                ) ?>

                                            </p>

                                        <?php endif; ?>


                                        <div class="text-muted small">

                                            ₹<?= number_format(
                                                (float)$item['price'],
                                                2
                                            ) ?>

                                            per item

                                        </div>

                                    </div>


                                    <!-- Quantity -->

                                    <div class="col-auto">

                                        <label
                                            for="quantity_<?= $itemId ?>"
                                            class="form-label small mb-1">

                                            Quantity

                                        </label>

                                        <input
                                            type="number"
                                            id="quantity_<?= $itemId ?>"
                                            name="quantity[<?= $itemId ?>]"
                                            value="<?= (int)$item['quantity'] ?>"
                                            min="1"
                                            max="99"
                                            class="form-control quantity-input">

                                    </div>


                                    <!-- Line Total -->

                                    <div class="col-auto text-end">

                                        <div class="small text-muted">
                                            Total
                                        </div>

                                        <strong>

                                            ₹<?= number_format(
                                                (float)$item['line_total'],
                                                2
                                            ) ?>

                                        </strong>

                                    </div>


                                    <!-- Remove -->

                                    <div class="col-auto">

                                        <button
                                            type="submit"
                                            name="action"
                                            value="remove"
                                            formaction="cart.php"
                                            class="btn btn-sm btn-outline-danger">

                                            Remove

                                        </button>

                                        <input
                                            type="hidden"
                                            name="menu_item_id"
                                            value="<?= $itemId ?>">

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>


                    <!-- Update Cart -->

                    <div
                        class="d-flex flex-wrap justify-content-between gap-2">

                        <a
                            href="menu.php"
                            class="btn btn-outline-danger">

                            Continue Shopping

                        </a>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                name="action"
                                value="update"
                                class="btn btn-primary">

                                Update Cart

                            </button>

                            <button
                                type="submit"
                                name="action"
                                value="clear"
                                formaction="cart.php"
                                class="btn btn-outline-secondary"
                                onclick="return confirm('Clear your entire cart?');">

                                Clear Cart

                            </button>

                        </div>

                    </div>

                </form>

            </div>


            <!-- =================================================
                 Order Summary
            ================================================== -->

            <div class="col-lg-4">

                <div class="card shadow-sm summary-card">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">
                            Order Summary
                        </h5>

                    </div>

                    <div class="card-body">

                        <div
                            class="d-flex justify-content-between mb-2">

                            <span>
                                Subtotal
                            </span>

                            <strong>
                                ₹<?= number_format(
                                    $subtotal,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between mb-2">

                            <span>
                                Tax
                            </span>

                            <strong>
                                ₹<?= number_format(
                                    $tax,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <hr>


                        <div
                            class="d-flex justify-content-between mb-3">

                            <strong>
                                Total
                            </strong>

                            <strong class="fs-5">

                                ₹<?= number_format(
                                    $total,
                                    2
                                ) ?>

                            </strong>

                        </div>


                        <a
                            href="checkout.php"
                            class="btn btn-danger w-100">

                            Proceed to Checkout

                        </a>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>


</div>

<?php include '../includes/footer.php' ?>