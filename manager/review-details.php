<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$reviewId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$reviewId) {

    flash(
        'error',
        'Invalid review.'
    );

    redirect('manage-reviews.php');
}

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {

        $stmt = $pdo->prepare("
            UPDATE reviews
            SET status = 'approved'
            WHERE id = ?
        ");

        $stmt->execute([$reviewId]);

        flash(
            'success',
            'Review approved.'
        );

        redirect(
            "review-details.php?id={$reviewId}"
        );
    }

    if ($action === 'reject') {

        $stmt = $pdo->prepare("
            UPDATE reviews
            SET status = 'rejected'
            WHERE id = ?
        ");

        $stmt->execute([$reviewId]);

        flash(
            'success',
            'Review rejected.'
        );

        redirect(
            "review-details.php?id={$reviewId}"
        );
    }

    if ($action === 'delete') {

        $stmt = $pdo->prepare("
            DELETE FROM reviews
            WHERE id = ?
        ");

        $stmt->execute([$reviewId]);

        flash(
            'success',
            'Review deleted.'
        );

        redirect('manage-reviews.php');
    }
}

/*
|--------------------------------------------------------------------------
| Review
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM reviews
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$reviewId]);

$review = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$review) {

    flash(
        'error',
        'Review not found.'
    );

    redirect('manage-reviews.php');
}

/*
|--------------------------------------------------------------------------
| Employee Rating
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        er.*,
        e.full_name
    FROM employee_ratings er
    INNER JOIN employees e
        ON e.id = er.employee_id
    WHERE er.review_id = ?
    LIMIT 1
");

$stmt->execute([$reviewId]);

$employeeRating = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

$statusClass = match (
    $review['status']
) {

    'approved' => 'bg-success',

    'rejected' => 'bg-danger',

    'pending' => 'bg-warning text-dark',

    default => 'bg-secondary'
};

$pageTitle = 'Review Details';

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<style>

    

</style>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="nivarra-title mb-1">

                Review Details

            </h2>

            <p class="text-muted mb-0">

                Review #<?= (int)$review['id'] ?>

            </p>

        </div>

        <a
            href="manage-reviews.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>

    <!-- Messages -->

    <?php if ($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <?php if ($msg = flashMessage('error')): ?>

        <div class="alert alert-danger">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <div class="row g-4">

        <!-- Review Information -->

        <div class="col-lg-8">

            <div class="card nivarra-card mb-4">

                <div class="card-header nivarra-card-header">

                    Review Information

                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-6">

                            <div class="detail-label">
                                Customer
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $review['customer_name'] ?? ''
                                ) ?>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="detail-label">
                                Email
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $review['customer_email'] ?? ''
                                ) ?>

                            </div>

                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-4">

                            <div class="detail-label">
                                Rating
                            </div>

                            <div class="detail-value">

                                <span class="rating-stars">

                                    <?= (int)(
                                        $review['rating'] ?? 0
                                    ) ?> / 5 ★

                                </span>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="detail-label">
                                Status
                            </div>

                            <div class="detail-value">

                                <span
                                    class="badge <?= $statusClass ?>">

                                    <?= e(
                                        ucfirst(
                                            (string)$review['status']
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="detail-label">
                                Date
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $review['created_at'] ?? ''
                                ) ?>

                            </div>

                        </div>

                    </div>

                    <div class="detail-label">
                        Review Title
                    </div>

                    <div class="detail-value">

                        <?= e(
                            $review['title'] ?? ''
                        ) ?>

                    </div>

                    <div class="detail-label">
                        Review
                    </div>

                    <div class="review-content">

                        <?= nl2br(
                            e(
                                $review['review_text'] ?? ''
                            )
                        ) ?>

                    </div>

                </div>

            </div>

            <!-- Employee Rating -->

            <?php if ($employeeRating): ?>

                <div class="card employee-rating-card mb-4">

                    <div class="card-header bg-info text-white">

                        Employee Rating

                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="col-md-6">

                                <div class="detail-label">
                                    Employee
                                </div>

                                <div class="detail-value">

                                    <?= e(
                                        $employeeRating['full_name'] ?? ''
                                    ) ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="detail-label">
                                    Rating
                                </div>

                                <div class="detail-value">

                                    <span class="rating-stars">

                                        <?= (int)(
                                            $employeeRating['rating'] ?? 0
                                        ) ?> / 5 ★

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="detail-label">
                            Comment
                        </div>

                        <div class="review-content">

                            <?php if (
                                trim(
                                    (string)(
                                        $employeeRating['comment']
                                        ?? ''
                                    )
                                ) !== ''
                            ): ?>

                                <?= nl2br(
                                    e(
                                        $employeeRating['comment']
                                    )
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">

                                    No comment provided.

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

        <!-- Review Status / Actions -->

        <div class="col-lg-4">

            <div class="card nivarra-card mb-4">

                <div class="card-header nivarra-card-header">

                    Review Status

                </div>

                <div class="card-body text-center">

                    <div class="mb-3">

                        <span
                            class="badge <?= $statusClass ?> fs-6">

                            <?= e(
                                ucfirst(
                                    (string)$review['status']
                                )
                            ) ?>

                        </span>

                    </div>

                    <hr>

                    <?php if (
                        $review['status'] === 'pending'
                    ): ?>

                        <form
                            method="POST"
                            class="mb-2">

                            <?= csrf_input() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="approve">

                            <button
                                type="submit"
                                class="btn btn-success w-100">

                                Approve Review

                            </button>

                        </form>

                        <form
                            method="POST"
                            class="mb-2">

                            <?= csrf_input() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="reject">

                            <button
                                type="submit"
                                class="btn btn-warning w-100">

                                Reject Review

                            </button>

                        </form>

                    <?php endif; ?>

                    <form method="POST">

                        <?= csrf_input() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="delete">

                        <button
                            type="submit"
                            class="btn btn-danger w-100"
                            onclick="return confirm('Delete review?')">

                            Delete Review

                        </button>

                    </form>

                </div>

            </div>

            <!-- Review Metadata -->

            <div class="card nivarra-card">

                <div class="card-header nivarra-card-header">

                    Review Metadata

                </div>

                <div class="card-body">

                    <div class="detail-label">
                        Review ID
                    </div>

                    <div class="detail-value">

                        #<?= (int)$review['id'] ?>

                    </div>

                    <div class="detail-label">
                        Customer
                    </div>

                    <div class="detail-value">

                        <?= e(
                            $review['customer_name'] ?? ''
                        ) ?>

                    </div>

                    <div class="detail-label">
                        Submitted
                    </div>

                    <div class="detail-value mb-0">

                        <?= e(
                            $review['created_at'] ?? ''
                        ) ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>