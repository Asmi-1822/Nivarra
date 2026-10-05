<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/customer-only.php';

$pageTitle = 'My Profile';

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
        c.last_login,
        c.created_at,

        cp.first_name,
        cp.last_name,
        cp.phone,
        cp.date_of_birth,
        cp.gender,
        cp.profile_image,

        ca.points_balance,
        ca.tier

    FROM customers c

    LEFT JOIN customer_profiles cp
        ON cp.customer_id = c.id

    LEFT JOIN loyalty_accounts ca
        ON ca.customer_id = c.id

    WHERE c.id = ?

    LIMIT 1
");

$stmt->execute([$customerId]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    session_unset();
    session_destroy();

    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Display Values
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string)($customer['first_name'] ?? '')
);

$lastName = trim(
    (string)($customer['last_name'] ?? '')
);

$fullName = trim(
    $firstName . ' ' . $lastName
);

if ($fullName === '') {
    $fullName = trim(
        (string)($customer['username'] ?? 'Customer')
    );
}

$profileImage = trim(
    (string)($customer['profile_image'] ?? '')
);

$loyaltyPoints = (int)(
    $customer['points_balance'] ?? 0
);

$loyaltyTier = ucfirst(
    strtolower(
        (string)($customer['tier'] ?? 'bronze')
    )
);

$emailVerified = (int)(
    $customer['email_verified'] ?? 0
);

include '../includes/header.php';
?>

<nav class="navbar customer-navbar">

    <div class="container">

        <a
            class="navbar-brand d-flex align-items-center text-white"
            href="dashboard.php">

            <img
                src="../assets/images/logo.png"
                alt="NIVARAA Logo"
                height="45"
                class="me-3">

            <span class="fw-bold">
                NIVARRA
            </span>

        </a>

        <div class="ms-auto d-flex gap-2">

            <a
                class="btn btn-light"
                href="dashboard.php">
                Dashboard
            </a>

            <a
                class="btn btn-warning"
                href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>

</nav>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-1">
                My Profile
            </h2>

            <p class="text-muted mb-0">
                View your NIVARRA account information.
            </p>

        </div>

    </div>

    <!-- Profile Overview -->

    <div class="row g-4">

        <!-- Main Profile -->

        <div class="col-lg-8">

            <div class="card shadow-sm">

                <div class="card-header">

                    <strong>
                        Personal Information
                    </strong>

                </div>

                <div class="card-body">

                    <div class="row g-4">

                        <!-- Profile Image -->

                        <div class="col-md-4 text-center">

                            <?php if ($profileImage !== ''): ?>

                                <img
                                    src="<?= htmlspecialchars(
                                        $profileImage,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="Profile Image"
                                    class="rounded-circle img-fluid"
                                    style="
                                        width: 150px;
                                        height: 150px;
                                        object-fit: cover;
                                    ">

                            <?php else: ?>

                                <div
                                    class="rounded-circle bg-light
                                           d-flex align-items-center
                                           justify-content-center
                                           mx-auto"
                                    style="
                                        width: 150px;
                                        height: 150px;
                                    ">

                                    <span
                                        class="text-muted"
                                        style="font-size: 48px;">

                                        <?= htmlspecialchars(
                                            strtoupper(
                                                substr(
                                                    $fullName,
                                                    0,
                                                    1
                                                )
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>

                                </div>

                            <?php endif; ?>

                            <h5 class="mt-3 mb-1">

                                <?= htmlspecialchars(
                                    $fullName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h5>

                            <span class="text-muted">

                                <?= htmlspecialchars(
                                    (string)(
                                        $customer['customer_id'] ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>

                        <!-- Details -->

                        <div class="col-md-8">

                            <div class="row g-3">

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        First Name
                                    </small>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $firstName !== ''
                                                ? $firstName
                                                : 'Not provided',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        Last Name
                                    </small>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $lastName !== ''
                                                ? $lastName
                                                : 'Not provided',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        Username
                                    </small>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string)(
                                                $customer['username']
                                                ?? 'Not provided'
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        Email
                                    </small>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string)$customer['email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        Phone
                                    </small>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string)(
                                                $customer['phone']
                                                ?? 'Not provided'
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        Date of Birth
                                    </small>

                                    <strong>

                                        <?php if (
                                            !empty(
                                                $customer['date_of_birth']
                                            )
                                        ): ?>

                                            <?= htmlspecialchars(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        (string)$customer[
                                                            'date_of_birth'
                                                        ]
                                                    )
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        <?php else: ?>

                                            Not provided

                                        <?php endif; ?>

                                    </strong>

                                </div>

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        Gender
                                    </small>

                                    <strong>
                                        <?= htmlspecialchars(
                                            ucfirst(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    (string)(
                                                        $customer['gender']
                                                        ?? 'Not provided'
                                                    )
                                                )
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="col-sm-6">

                                    <small class="text-muted d-block">
                                        Email Status
                                    </small>

                                    <?php if ($emailVerified === 1): ?>

                                        <span class="badge bg-success">
                                            Verified
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-warning text-dark">
                                            Not Verified
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="card-footer bg-white">

                    <div class="d-flex gap-2 flex-wrap">

                        <a
                            href="edit-profile.php"
                            class="btn btn-maroon">
                            Edit Profile
                        </a>

                        <a
                            href="change-password.php"
                            class="btn btn-outline-secondary">
                            Change Password
                        </a>

                    </div>

                </div>

            </div>

        </div>

        <!-- Account Summary -->

        <div class="col-lg-4">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <strong>
                        Account
                    </strong>

                </div>

                <div class="card-body">

                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Customer ID
                        </small>

                        <strong>
                            <?= htmlspecialchars(
                                (string)(
                                    $customer['customer_id'] ?? ''
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                    </div>

                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Account Status
                        </small>

                        <span class="badge bg-success">

                            <?= htmlspecialchars(
                                ucfirst(
                                    (string)$customer['status']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </div>

                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Member Since
                        </small>

                        <strong>

                            <?php if (
                                !empty($customer['created_at'])
                            ): ?>

                                <?= htmlspecialchars(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            (string)$customer['created_at']
                                        )
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                Not available

                            <?php endif; ?>

                        </strong>

                    </div>

                    <div>

                        <small class="text-muted d-block">
                            Last Login
                        </small>

                        <strong>

                            <?php if (
                                !empty($customer['last_login'])
                            ): ?>

                                <?= htmlspecialchars(
                                    date(
                                        'd M Y, h:i A',
                                        strtotime(
                                            (string)$customer['last_login']
                                        )
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                Not available

                            <?php endif; ?>

                        </strong>

                    </div>

                </div>

            </div>

            <!-- Loyalty -->

            <div class="card shadow-sm">

                <div class="card-header">

                    <strong>
                        Loyalty Rewards
                    </strong>

                </div>

                <div class="card-body text-center">

                    <div class="display-5 fw-bold text-maroon">
                        <?= number_format($loyaltyPoints) ?>
                    </div>

                    <div class="text-muted mb-3">
                        Loyalty Points
                    </div>

                    <span class="badge bg-warning text-dark">

                        <?= htmlspecialchars(
                            $loyaltyTier,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                    <div class="mt-3">

                        <a
                            href="loyalty.php"
                            class="btn btn-outline-secondary btn-sm">
                            View Loyalty
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>