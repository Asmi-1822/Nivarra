<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Offer Analytics';

/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

$totalOffers = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM offers
    ")
    ->fetchColumn();

$activeOffers = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM offers
        WHERE status = 'active'
    ")
    ->fetchColumn();

$inactiveOffers = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM offers
        WHERE status = 'inactive'
    ")
    ->fetchColumn();

$totalUsage = (int)$pdo
    ->query("
        SELECT COALESCE(SUM(usage_count), 0)
        FROM offers
    ")
    ->fetchColumn();

$currentOffers = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM offers
        WHERE CURDATE() BETWEEN start_date AND end_date
        AND status = 'active'
    ")
    ->fetchColumn();

$expiredOffers = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM offers
        WHERE end_date < CURDATE()
    ")
    ->fetchColumn();

/*
|--------------------------------------------------------------------------
| Top Performing Offers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM offers
    ORDER BY usage_count DESC
    LIMIT 10
");

$topOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Active Offers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM offers
    WHERE status = 'active'
    ORDER BY end_date ASC
");

$activeList = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<style>
    
</style>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="nivarra-page-title mb-0">
            Offer Analytics
        </h2>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">
            Back
        </a>

    </div>

    <!-- Statistics -->

    <div class="row g-3 mb-4">

        <div class="col-6 col-md-4 col-lg-2">

            <div class="card stat-card text-center">

                <div class="card-body">

                    <div class="stat-number">
                        <?= $totalOffers ?>
                    </div>

                    <div class="text-muted">
                        Total Offers
                    </div>

                </div>

            </div>

        </div>

        <div class="col-6 col-md-4 col-lg-2">

            <div class="card stat-card text-center border-success">

                <div class="card-body">

                    <div class="stat-number text-success">
                        <?= $activeOffers ?>
                    </div>

                    <div class="text-muted">
                        Active
                    </div>

                </div>

            </div>

        </div>

        <div class="col-6 col-md-4 col-lg-2">

            <div class="card stat-card text-center border-secondary">

                <div class="card-body">

                    <div class="stat-number text-secondary">
                        <?= $inactiveOffers ?>
                    </div>

                    <div class="text-muted">
                        Inactive
                    </div>

                </div>

            </div>

        </div>

        <div class="col-6 col-md-4 col-lg-2">

            <div class="card stat-card text-center border-primary">

                <div class="card-body">

                    <div class="stat-number text-primary">
                        <?= $totalUsage ?>
                    </div>

                    <div class="text-muted">
                        Total Usage
                    </div>

                </div>

            </div>

        </div>

        <div class="col-6 col-md-4 col-lg-2">

            <div class="card stat-card text-center border-info">

                <div class="card-body">

                    <div class="stat-number text-info">
                        <?= $currentOffers ?>
                    </div>

                    <div class="text-muted">
                        Running Now
                    </div>

                </div>

            </div>

        </div>

        <div class="col-6 col-md-4 col-lg-2">

            <div class="card stat-card text-center border-danger">

                <div class="card-body">

                    <div class="stat-number text-danger">
                        <?= $expiredOffers ?>
                    </div>

                    <div class="text-muted">
                        Expired
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Most Used Offers -->

    <div class="card nivarra-card mb-4">

        <div class="card-header nivarra-card-header">
            Most Used Offers
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                    <tr>
                        <th>Banner</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Discount</th>
                        <th>Usage</th>
                        <th>Status</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($topOffers)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-4">

                                No offers available.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($topOffers as $offer): ?>

                            <tr>

                                <td>

                                    <?php if (!empty($offer['banner_image'])): ?>

                                        <img
                                            src="../uploads/offers/<?= e($offer['banner_image']) ?>"
                                            alt="Offer Banner"
                                            class="offer-image">

                                    <?php else: ?>

                                        <span class="text-muted">
                                            No Image
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="fw-semibold">

                                    <?= e($offer['title']) ?>

                                </td>

                                <td>

                                    <?= e(ucfirst(
                                        (string)$offer['discount_type']
                                    )) ?>

                                </td>

                                <td>

                                    <?php if (
                                        $offer['discount_type'] === 'percentage'
                                    ): ?>

                                        <?= e($offer['discount_value']) ?>%

                                    <?php else: ?>

                                        <?= e($offer['discount_value']) ?>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <strong>
                                        <?= (int)$offer['usage_count'] ?>
                                    </strong>

                                </td>

                                <td>

                                    <?php if (
                                        $offer['status'] === 'active'
                                    ): ?>

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Currently Running Offers -->

    <div class="card nivarra-card">

        <div class="card-header nivarra-card-header">
            Currently Running Offers
        </div>

        <div class="card-body">

            <div class="row g-4">

                <?php if (empty($activeList)): ?>

                    <div class="col-12">

                        <div class="alert alert-info mb-0">

                            No active offers.

                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($activeList as $offer): ?>

                        <div class="col-md-6 col-lg-4">

                            <div class="card offer-card">

                                <?php if (!empty($offer['banner_image'])): ?>

                                    <img
                                        src="../uploads/offers/<?= e($offer['banner_image']) ?>"
                                        class="offer-banner"
                                        alt="Offer Banner">

                                <?php endif; ?>

                                <div class="card-body">

                                    <h5 class="offer-card-title">

                                        <?= e($offer['title']) ?>

                                    </h5>

                                    <p class="text-muted">

                                        <?= e($offer['description']) ?>

                                    </p>

                                    <hr>

                                    <p class="mb-2">

                                        <strong>
                                            Discount:
                                        </strong>

                                        <?php if (
                                            $offer['discount_type']
                                            === 'percentage'
                                        ): ?>

                                            <?= e(
                                                $offer['discount_value']
                                            ) ?>%

                                        <?php else: ?>

                                            <?= e(
                                                $offer['discount_value']
                                            ) ?>

                                        <?php endif; ?>

                                    </p>

                                    <p class="mb-2">

                                        <strong>
                                            Usage:
                                        </strong>

                                        <?= (int)$offer['usage_count'] ?>

                                    </p>

                                    <p class="mb-0">

                                        <strong>
                                            Ends:
                                        </strong>

                                        <?= e($offer['end_date']) ?>

                                    </p>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>