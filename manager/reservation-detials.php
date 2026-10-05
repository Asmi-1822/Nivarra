<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Reservation Details';

$reservationId = (int)($_GET['id'] ?? 0);

if ($reservationId <= 0) {
    redirect('manage-reservations.php');
}

/*
|--------------------------------------------------------------------------
| Reservation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM reservations
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$reservationId]);

$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {

    flash(
        'error',
        'Reservation not found.'
    );

    redirect('manage-reservations.php');
}

/*
|--------------------------------------------------------------------------
| Status Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    $allowedActions = [
        'confirm',
        'cancel',
        'complete'
    ];

    if (in_array($action, $allowedActions, true)) {

        $statusMap = [
            'confirm' => 'confirmed',
            'cancel' => 'cancelled',
            'complete' => 'completed'
        ];

        $newStatus = $statusMap[$action];

        $update = $pdo->prepare("
            UPDATE reservations
            SET status = ?
            WHERE id = ?
        ");

        $update->execute([
            $newStatus,
            $reservationId
        ]);

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */

        if (function_exists('auditLog')) {

            auditLog(
                (int)($_SESSION['employee_id'] ?? 0),
                'reservation_status_changed',
                'Reservation #' .
                $reservationId .
                ' changed to ' .
                $newStatus
            );
        }

        flash(
            'success',
            'Reservation updated.'
        );

        redirect(
            'reservation-details.php?id=' . $reservationId
        );
    }
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<style>

    

</style>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="nivarra-title mb-1">

                Reservation #<?= (int)$reservation['id'] ?>

            </h2>

            <p class="text-muted mb-0">

                Reservation Details

            </p>

        </div>

        <div class="d-flex gap-2">

            <button
                type="button"
                onclick="window.print();"
                class="btn btn-outline-dark">

                Print

            </button>

            <a
                href="manage-reservations.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>

    <!-- Success Message -->

    <?php if ($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <!-- Error Message -->

    <?php if ($msg = flashMessage('error')): ?>

        <div class="alert alert-danger">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <div class="row g-4">

        <!-- Main Reservation Information -->

        <div class="col-lg-8">

            <div class="card nivarra-card mb-4">

                <div class="card-header nivarra-card-header">

                    Reservation Information

                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-6">

                            <div class="detail-label">
                                Customer Name
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $reservation['full_name'] ?? ''
                                ) ?>

                            </div>

                            <div class="detail-label">
                                Email
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $reservation['email'] ?? ''
                                ) ?>

                            </div>

                            <div class="detail-label">
                                Phone
                            </div>

                            <div class="detail-value mb-md-0">

                                <?= e(
                                    $reservation['phone'] ?? ''
                                ) ?>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="detail-label">
                                Reservation Date
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $reservation['reservation_date'] ?? ''
                                ) ?>

                            </div>

                            <div class="detail-label">
                                Reservation Time
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $reservation['reservation_time'] ?? ''
                                ) ?>

                            </div>

                            <div class="detail-label">
                                Number of Guests
                            </div>

                            <div class="detail-value mb-md-0">

                                <?= (int)(
                                    $reservation['guests'] ?? 0
                                ) ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Customer Notes -->

            <div class="card nivarra-card">

                <div class="card-header nivarra-card-header">

                    Customer Notes

                </div>

                <div class="card-body">

                    <?php if (
                        trim(
                            (string)(
                                $reservation['notes'] ?? ''
                            )
                        ) !== ''
                    ): ?>

                        <div class="mb-0">

                            <?= nl2br(
                                e(
                                    $reservation['notes']
                                )
                            ) ?>

                        </div>

                    <?php else: ?>

                        <p class="text-muted mb-0">

                            No notes provided.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- Right Side -->

        <div class="col-lg-4">

            <!-- Reservation Status -->

            <div class="card nivarra-card mb-4">

                <div class="card-header nivarra-card-header">

                    Reservation Status

                </div>

                <div class="card-body">

                    <?php

                    $badgeClass = match (
                        $reservation['status']
                    ) {

                        'confirmed'
                            => 'success',

                        'cancelled'
                            => 'danger',

                        'completed'
                            => 'primary',

                        default
                            => 'warning'
                    };

                    ?>

                    <div class="status-box text-center">

                        <div class="text-muted mb-2">

                            Current Status

                        </div>

                        <span
                            class="badge bg-<?= $badgeClass ?> fs-6">

                            <?= e(
                                ucfirst(
                                    (string)$reservation['status']
                                )
                            ) ?>

                        </span>

                    </div>

                    <hr>

                    <?php if (
                        $reservation['status'] === 'pending'
                    ): ?>

                        <form method="POST" class="mb-2">

                            <?= csrf_input() ?>

                            <button
                                type="submit"
                                name="action"
                                value="confirm"
                                class="btn btn-success w-100">

                                Confirm Reservation

                            </button>

                        </form>

                        <form method="POST">

                            <?= csrf_input() ?>

                            <button
                                type="submit"
                                name="action"
                                value="cancel"
                                class="btn btn-danger w-100">

                                Cancel Reservation

                            </button>

                        </form>

                    <?php elseif (
                        $reservation['status'] === 'confirmed'
                    ): ?>

                        <form method="POST">

                            <?= csrf_input() ?>

                            <button
                                type="submit"
                                name="action"
                                value="complete"
                                class="btn btn-primary w-100">

                                Mark Completed

                            </button>

                        </form>

                    <?php else: ?>

                        <p class="text-muted text-center mb-0">

                            No further actions available.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

            <!-- Reservation Metadata -->

            <div class="card nivarra-card">

                <div class="card-header nivarra-card-header">

                    Reservation Metadata

                </div>

                <div class="card-body">

                    <div class="detail-label">
                        Reservation ID
                    </div>

                    <div class="detail-value">

                        #<?= (int)$reservation['id'] ?>

                    </div>

                    <div class="detail-label">
                        Created
                    </div>

                    <div class="detail-value mb-0">

                        <?= e(
                            $reservation['created_at'] ?? ''
                        ) ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>