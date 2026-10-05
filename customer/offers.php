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
| Fetch Active Offers
|--------------------------------------------------------------------------
|
| Existing offers table:
| id
| title
| description
| discount_type
| discount_value
| start_date
| end_date
| status
| usage_count
| created_at
| banner_image
|
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        description,
        discount_type,
        discount_value,
        start_date,
        end_date,
        status,
        usage_count,
        created_at,
        banner_image
    FROM offers
    WHERE status = 'active'
      AND start_date <= CURDATE()
      AND end_date >= CURDATE()
    ORDER BY end_date ASC, created_at DESC
");

$stmt->execute();

$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                Offers
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


    <!-- =====================================================
         Offers
    ====================================================== -->

    <?php if (empty($offers)): ?>

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <h5 class="mb-2">
                    No Active Offers
                </h5>

                <p class="text-muted mb-4">
                    There are currently no active offers available.
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

            <?php foreach ($offers as $offer): ?>

                <?php

                $discountType = (string)(
                    $offer['discount_type'] ?? ''
                );

                $discountValue = (float)(
                    $offer['discount_value'] ?? 0
                );

                $imagePath = offerImagePath(
                    $offer['banner_image'] ?? null
                );

                $startDate = (string)(
                    $offer['start_date'] ?? ''
                );

                $endDate = (string)(
                    $offer['end_date'] ?? ''
                );

                $formattedStartDate = '';

                $formattedEndDate = '';

                if ($startDate !== '') {

                    $timestamp = strtotime($startDate);

                    if ($timestamp !== false) {
                        $formattedStartDate = date(
                            'd M Y',
                            $timestamp
                        );
                    }
                }

                if ($endDate !== '') {

                    $timestamp = strtotime($endDate);

                    if ($timestamp !== false) {
                        $formattedEndDate = date(
                            'd M Y',
                            $timestamp
                        );
                    }
                }

                ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card offer-card shadow-sm">

                        <?php if ($imagePath !== null): ?>

                            <img
                                src="<?= e($imagePath) ?>"
                                alt="<?= e((string)$offer['title']) ?>"
                                class="card-img-top offer-image">

                        <?php else: ?>

                            <div
                                class="offer-image d-flex align-items-center justify-content-center"
                                style="background-color:#f1e4e4;">

                                <span
                                    class="fw-bold text-danger">
                                    NIVARRA
                                </span>

                            </div>

                        <?php endif; ?>


                        <div class="card-body d-flex flex-column">

                            <div class="mb-3">

                                <span class="badge offer-discount">

                                    <?= e(
                                        formatOfferDiscount(
                                            $discountType,
                                            $discountValue
                                        )
                                    ) ?>

                                </span>

                            </div>


                            <h5 class="card-title">

                                <?= e(
                                    (string)$offer['title']
                                ) ?>

                            </h5>


                            <?php if (
                                trim(
                                    (string)($offer['description'] ?? '')
                                ) !== ''
                            ): ?>

                                <p class="card-text text-muted">

                                    <?= nl2br(
                                        e(
                                            (string)$offer['description']
                                        )
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <div class="mt-auto">

                                <?php if (
                                    $formattedStartDate !== '' &&
                                    $formattedEndDate !== ''
                                ): ?>

                                    <div class="small text-muted mb-2">

                                        Valid from
                                        <strong>
                                            <?= e($formattedStartDate) ?>
                                        </strong>

                                        to

                                        <strong>
                                            <?= e($formattedEndDate) ?>
                                        </strong>

                                    </div>

                                <?php elseif (
                                    $formattedEndDate !== ''
                                ): ?>

                                    <div class="small text-muted mb-2">

                                        Valid until
                                        <strong>
                                            <?= e($formattedEndDate) ?>
                                        </strong>

                                    </div>

                                <?php endif; ?>


                                <a
                                    href="menu.php"
                                    class="btn btn-danger w-100">

                                    Browse Menu

                                </a>

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