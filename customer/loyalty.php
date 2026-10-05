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
| Loyalty Account
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        customer_id,
        points_balance,
        tier
    FROM loyalty_accounts
    WHERE customer_id = ?
    LIMIT 1
");

$stmt->execute([$customerId]);

$loyaltyAccount = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Loyalty Transactions
|--------------------------------------------------------------------------
*/

$transactions = [];

if ($loyaltyAccount) {

    $loyaltyAccountId = (int)$loyaltyAccount['id'];

    $stmt = $pdo->prepare("
        SELECT
            id,
            transaction_type,
            points,
            reference_type,
            reference_id,
            notes,
            created_at
        FROM loyalty_transactions
        WHERE loyalty_account_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");

    $stmt->execute([$loyaltyAccountId]);

    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Tier Display
|--------------------------------------------------------------------------
*/

function loyaltyTierClass(string $tier): string
{
    return match (strtolower(trim($tier))) {
        'bronze' => 'bg-warning text-dark',
        'silver' => 'bg-secondary',
        'gold' => 'bg-warning text-dark',
        'platinum' => 'bg-dark',
        default => 'bg-secondary'
    };
}

function loyaltyTierLabel(string $tier): string
{
    return ucfirst(strtolower(trim($tier)));
}

/*
|--------------------------------------------------------------------------
| Transaction Display
|--------------------------------------------------------------------------
*/

function loyaltyTransactionClass(string $type): string
{
    return match (strtolower(trim($type))) {
        'earn' => 'text-success',
        'redeem' => 'text-danger',
        'adjustment' => 'text-primary',
        default => 'text-muted'
    };
}

function loyaltyTransactionSign(string $type): string
{
    return match (strtolower(trim($type))) {
        'earn' => '+',
        'redeem' => '-',
        'adjustment' => '',
        default => ''
    };
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

        <div class="d-flex align-items-center gap-3">

            <h2 class="mb-0">
            Loyalty Rewards
            </h2>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">
                Back
            </a>

        </div>

    </div>


    <?php if (!$loyaltyAccount): ?>

        <!-- No Loyalty Account -->

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <h5 class="mb-2">
                    Loyalty Account Not Available
                </h5>

                <p class="text-muted mb-0">
                    A loyalty account has not been created for your account yet.
                </p>

            </div>

        </div>

    <?php else: ?>

        <?php
        $pointsBalance = (int)(
            $loyaltyAccount['points_balance'] ?? 0
        );

        $tier = (string)(
            $loyaltyAccount['tier'] ?? 'bronze'
        );
        ?>


        <!-- =================================================
             Loyalty Summary
        ================================================== -->

        <div class="card loyalty-card shadow-sm mb-4">

            <div class="card-body p-4">

                <div class="row align-items-center">

                    <div class="col-md-8">

                        <div class="small opacity-75 mb-1">
                            Available Loyalty Points
                        </div>

                        <div class="points-value">
                            <?= number_format($pointsBalance) ?>
                        </div>

                    </div>

                    <div class="col-md-4 text-md-end mt-3 mt-md-0">

                        <div class="small opacity-75 mb-2">
                            Current Tier
                        </div>

                        <span
                            class="badge tier-badge <?= e(
                                loyaltyTierClass($tier)
                            ) ?>">

                            <?= e(
                                loyaltyTierLabel($tier)
                            ) ?>

                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             Loyalty Information
        ================================================== -->

        <div class="row g-4 mb-4">

            <div class="col-md-4">

                <div class="card shadow-sm h-100">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Current Points
                        </h6>

                        <h3 class="mb-0">
                            <?= number_format($pointsBalance) ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card shadow-sm h-100">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Loyalty Tier
                        </h6>

                        <h3 class="mb-0 text-capitalize">
                            <?= e($tier) ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card shadow-sm h-100">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Transactions
                        </h6>

                        <h3 class="mb-0">
                            <?= count($transactions) ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             Transaction History
        ================================================== -->

        <div class="card shadow-sm">

            <div class="card-header bg-white">

                <h5 class="mb-0">
                    Loyalty Transaction History
                </h5>

            </div>

            <div class="card-body p-0">

                <?php if (empty($transactions)): ?>

                    <div class="text-center py-5">

                        <h6 class="mb-2">
                            No Transactions
                        </h6>

                        <p class="text-muted mb-0">
                            Your loyalty transactions will appear here.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Type
                                    </th>

                                    <th>
                                        Points
                                    </th>

                                    <th>
                                        Reference
                                    </th>

                                    <th>
                                        Notes
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($transactions as $transaction): ?>

                                    <?php

                                    $transactionType = (string)(
                                        $transaction['transaction_type'] ?? ''
                                    );

                                    $points = (int)(
                                        $transaction['points'] ?? 0
                                    );

                                    $referenceType = (string)(
                                        $transaction['reference_type'] ?? ''
                                    );

                                    $referenceId = $transaction['reference_id'] ?? null;

                                    $notes = (string)(
                                        $transaction['notes'] ?? ''
                                    );

                                    $createdAt = (string)(
                                        $transaction['created_at'] ?? ''
                                    );

                                    $formattedDate = '';

                                    if ($createdAt !== '') {

                                        $timestamp = strtotime($createdAt);

                                        if ($timestamp !== false) {

                                            $formattedDate = date(
                                                'd M Y, h:i A',
                                                $timestamp
                                            );
                                        }
                                    }

                                    ?>

                                    <tr>

                                        <td>

                                            <div class="small">
                                                <?= e($formattedDate) ?>
                                            </div>

                                        </td>


                                        <td>

                                            <span
                                                class="text-capitalize fw-semibold <?= e(
                                                    loyaltyTransactionClass(
                                                        $transactionType
                                                    )
                                                ) ?>">

                                                <?= e(
                                                    $transactionType
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <strong
                                                class="<?= e(
                                                    loyaltyTransactionClass(
                                                        $transactionType
                                                    )
                                                ) ?>">

                                                <?= e(
                                                    loyaltyTransactionSign(
                                                        $transactionType
                                                    )
                                                ) ?><?= number_format(
                                                    $points
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?php if (
                                                trim($referenceType) !== ''
                                            ): ?>

                                                <?= e($referenceType) ?>

                                                <?php if (
                                                    $referenceId !== null &&
                                                    $referenceId !== ''
                                                ): ?>

                                                    #<?= e(
                                                        (string)$referenceId
                                                    ) ?>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?php if (
                                                trim($notes) !== ''
                                            ): ?>

                                                <?= nl2br(
                                                    e($notes)
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


</div>

<?php include '../includes/footer.php'; ?>