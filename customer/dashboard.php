<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/customer-only.php';

$pageTitle = 'Customer Dashboard';

$customerId = (int)$_SESSION['customer_id'];

/*
|--------------------------------------------------------------------------
| Customer Profile
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.customer_id,
        c.email,
        c.username,
        c.email_verified,
        c.status,
        cp.first_name,
        cp.last_name,
        cp.phone,
        cp.profile_image
    FROM customers c
    LEFT JOIN customer_profiles cp
        ON cp.customer_id = c.id
    WHERE c.id = ?
    LIMIT 1
");

$stmt->execute([$customerId]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {

    header('Location: ../auth/login.php');
    exit;
}

$firstName = trim(
    (string)($customer['first_name'] ?? '')
);

$lastName = trim(
    (string)($customer['last_name'] ?? '')
);

$displayName = trim(
    $firstName . ' ' . $lastName
);

if ($displayName === '') {

    $displayName = trim(
        (string)($customer['username'] ?? 'Customer')
    );
}

/*
|--------------------------------------------------------------------------
| Recent Orders
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        table_number,
        status,
        total,
        created_at
    FROM orders
    WHERE customer_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([$customerId]);

$recentOrders = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

/*
|--------------------------------------------------------------------------
| Upcoming Reservation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        reservation_date,
        reservation_time,
        guests,
        status
    FROM reservations
    WHERE customer_id = ?
      AND reservation_date >= CURDATE()
      AND status IN ('pending', 'confirmed')
    ORDER BY
        reservation_date ASC,
        reservation_time ASC
    LIMIT 1
");

$stmt->execute([$customerId]);

$upcomingReservation = $stmt->fetch(
    PDO::FETCH_ASSOC
);

/*
|--------------------------------------------------------------------------
| Loyalty
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        points_balance,
        tier
    FROM loyalty_accounts
    WHERE customer_id = ?
    LIMIT 1
");

$stmt->execute([$customerId]);

$loyalty = $stmt->fetch(
    PDO::FETCH_ASSOC
);

$pointsBalance = (int)(
    $loyalty['points_balance'] ?? 0
);

$loyaltyTier = ucfirst(
    strtolower(
        (string)(
            $loyalty['tier'] ?? 'bronze'
        )
    )
);

/*
|--------------------------------------------------------------------------
| Unread Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM customer_notifications
    WHERE customer_id = ?
      AND is_read = 0
");

$stmt->execute([$customerId]);

$unreadNotifications = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Active Offers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        description,
        discount_type,
        discount_value,
        start_date,
        end_date
    FROM offers
    WHERE status = 'active'
      AND (
            start_date IS NULL
            OR start_date <= CURDATE()
          )
      AND (
            end_date IS NULL
            OR end_date >= CURDATE()
          )
    ORDER BY id DESC
    LIMIT 3
");

$activeOffers = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

include '../includes/header.php';
include '../includes/customer-navbar.php';

?>

<div class="container py-4">

    <!-- Header -->

    <div class="mb-4">

        <h2 class="mb-1">

            Welcome,
            <?= htmlspecialchars(
                $displayName,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </h2>

        <p class="text-muted mb-0">

            Welcome to NIVARRA. Manage your orders,
            reservations, and account.

        </p>

    </div>


    <!-- Quick Actions -->

    <!-- Quick Actions -->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <h5 class="mb-0">
            Quick Actions
        </h5>

    </div>

    <div class="card-body">

        <div class="d-flex flex-wrap gap-2">

            <a
                href="menu.php"
                class="btn btn-maroon">

                Browse Menu

            </a>

            <a
                href="cart.php"
                class="btn btn-outline-primary">

                Cart

            </a>

            <a
                href="orders.php"
                class="btn btn-outline-success">

                My Orders

            </a>

            <a
                href="reservations.php"
                class="btn btn-outline-warning">

                Reservations

            </a>

            <a
                href="favorites.php"
                class="btn btn-outline-danger">

                Favorites

            </a>

            <a
                href="offers.php"
                class="btn btn-outline-info">

                Offers

            </a>

            <a
                href="loyalty.php"
                class="btn btn-outline-secondary">

                Loyalty Rewards

            </a>

            <a
                href="notifications.php"
                class="btn btn-outline-info">

                Notifications

                <?php if ($unreadNotifications > 0): ?>

                    <span class="badge bg-danger ms-1">

                        <?= e(
                            (string)$unreadNotifications
                        ) ?>

                    </span>

                <?php endif; ?>

            </a>

            <a
                href="addresses.php"
                class="btn btn-outline-primary">

                Addresses

            </a>

            <a
                href="preferences.php"
                class="btn btn-outline-warning">

                Preferences

            </a>

            <a
                href="reviews.php"
                class="btn btn-outline-secondary">

                My Reviews

            </a>

        </div>

    </div>

</div>


    <!-- Summary Cards -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h6 class="text-muted">
                        Loyalty Points
                    </h6>

                    <h3 class="mb-1">

                        <?= number_format(
                            $pointsBalance
                        ) ?>

                    </h3>

                    <span class="badge bg-warning text-dark">

                        <?= htmlspecialchars(
                            $loyaltyTier,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h6 class="text-muted">
                        Recent Orders
                    </h6>

                    <h3 class="mb-1">

                        <?= count(
                            $recentOrders
                        ) ?>

                    </h3>

                    <a
                        href="orders.php"
                        class="text-decoration-none">

                        View My Orders

                    </a>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h6 class="text-muted">
                        Notifications
                    </h6>

                    <h3 class="mb-1">

                        <?= $unreadNotifications ?>

                    </h3>

                    <a
                        href="notifications.php"
                        class="text-decoration-none">

                        Unread Notifications

                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- Recent Orders -->

    <div class="card shadow-sm mb-4">

        <div
            class="card-header
                   d-flex
                   justify-content-between
                   align-items-center">

            <strong>
                Recent Orders
            </strong>

            <a
                href="orders.php"
                class="btn btn-sm btn-maroon">

                View All

            </a>

        </div>


        <div class="card-body p-0">

            <?php if (!$recentOrders): ?>

                <div
                    class="p-4
                           text-center
                           text-muted">

                    You have not placed any orders yet.

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Order
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $recentOrders
                                as $order
                            ): ?>

                                <tr>

                                    <td>

                                        #<?= (int)(
                                            $order['id']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string)(
                                                        $order[
                                                            'created_at'
                                                        ]
                                                    )
                                                )
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <span
                                            class="badge bg-secondary">

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    (string)(
                                                        $order[
                                                            'status'
                                                        ]
                                                    )
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        ₹<?= number_format(
                                            (float)(
                                                $order[
                                                    'total'
                                                ] ?? 0
                                            ),
                                            2
                                        ) ?>

                                    </td>


                                    <td class="text-end">

                                        <a
                                            href="order-details.php?id=<?= (int)$order['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary">

                                            View

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Upcoming Reservation -->

    <div class="card shadow-sm mb-4">

        <div
            class="card-header
                   d-flex
                   justify-content-between
                   align-items-center">

            <strong>
                Upcoming Reservation
            </strong>

            <a
                href="reservations.php"
                class="btn btn-sm btn-maroon">

                View Reservations

            </a>

        </div>


        <div class="card-body">

            <?php if (!$upcomingReservation): ?>

                <p class="text-muted mb-0">

                    You do not have any upcoming
                    reservations.

                </p>

            <?php else: ?>

                <div class="row g-3">

                    <div class="col-md-3">

                        <small class="text-muted d-block">
                            Date
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                date(
                                    'd M Y',
                                    strtotime(
                                        (string)(
                                            $upcomingReservation[
                                                'reservation_date'
                                            ]
                                        )
                                    )
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>


                    <div class="col-md-3">

                        <small class="text-muted d-block">
                            Time
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                date(
                                    'h:i A',
                                    strtotime(
                                        (string)(
                                            $upcomingReservation[
                                                'reservation_time'
                                            ]
                                        )
                                    )
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>


                    <div class="col-md-3">

                        <small class="text-muted d-block">
                            Guests
                        </small>

                        <strong>

                            <?= (int)(
                                $upcomingReservation[
                                    'guests'
                                ]
                            ) ?>

                        </strong>

                    </div>


                    <div class="col-md-3">

                        <small class="text-muted d-block">
                            Status
                        </small>

                        <span
                            class="badge bg-secondary">

                            <?= htmlspecialchars(
                                ucfirst(
                                    (string)(
                                        $upcomingReservation[
                                            'status'
                                        ]
                                    )
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Offers -->

    <div class="card shadow-sm mb-4">

        <div
            class="card-header
                   d-flex
                   justify-content-between
                   align-items-center">

            <strong>
                Current Offers
            </strong>

            <a
                href="offers.php"
                class="btn btn-sm btn-maroon">

                View All

            </a>

        </div>


        <div class="card-body">

            <?php if (!$activeOffers): ?>

                <p class="text-muted mb-0">

                    No active offers are available
                    at the moment.

                </p>

            <?php else: ?>

                <div class="row g-3">

                    <?php foreach (
                        $activeOffers
                        as $offer
                    ): ?>

                        <div class="col-md-4">

                            <div
                                class="border
                                       rounded
                                       p-3
                                       h-100">

                                <h5>

                                    <?= htmlspecialchars(
                                        (string)(
                                            $offer[
                                                'title'
                                            ] ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </h5>


                                <p class="text-muted">

                                    <?= htmlspecialchars(
                                        (string)(
                                            $offer[
                                                'description'
                                            ] ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </p>


                                <strong>

                                    <?php if (
                                        $offer[
                                            'discount_type'
                                        ] === 'percentage'
                                    ): ?>

                                        <?= number_format(
                                            (float)(
                                                $offer[
                                                    'discount_value'
                                                ]
                                            ),
                                            2
                                        ) ?>% OFF

                                    <?php else: ?>

                                        ₹<?= number_format(
                                            (float)(
                                                $offer[
                                                    'discount_value'
                                                ]
                                            ),
                                            2
                                        ) ?> OFF

                                    <?php endif; ?>

                                </strong>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<?php include '../includes/footer.php'; ?>