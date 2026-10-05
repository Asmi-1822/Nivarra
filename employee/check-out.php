<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';
require_once '../includes/csrf.php';

$employeeId = (int)$_SESSION['employee_id'];

$today = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Today's Attendance
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM attendance_logs
    WHERE employee_id = ?
    AND attendance_date = ?
    LIMIT 1
");

$stmt->execute([
    $employeeId,
    $today
]);

$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Check Out
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['check_out'])
) {
    verify_csrf();

    if (!$attendance) {

        flash(
            'error',
            'You must check in first.'
        );

        redirect('check-out.php');
    }

    if (!empty($attendance['check_out_time'])) {

        flash(
            'error',
            'You have already checked out today.'
        );

        redirect('check-out.php');
    }

    $checkInTime =
        strtotime($attendance['check_in_time']);

    $checkOutTime =
        time();

    $hoursWorked =
        round(
            ($checkOutTime - $checkInTime) / 3600,
            2
        );

    $stmt = $pdo->prepare("
        UPDATE attendance_logs
        SET
            check_out_time = NOW(),
            total_hours = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $hoursWorked,
        $attendance['id']
    ]);

    flash(
        'success',
        'Check-out successful.'
    );

    redirect('check-out.php');
}

$pageTitle = 'Check Out';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="mb-4">

                Employee Check-Out

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

    <?php if($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <?php if($msg = flashMessage('error')): ?>

        <div class="alert alert-danger">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-body">

            <?php if(!$attendance): ?>

                <div class="alert alert-warning">

                    You have not checked in today.

                </div>

            <?php elseif(
                !empty(
                    $attendance['check_out_time']
                )
            ): ?>

                <div class="alert alert-success">

                    <strong>Checked In:</strong>

                    <?= e(
                        $attendance['check_in_time']
                    ) ?>

                    <br>

                    <strong>Checked Out:</strong>

                    <?= e(
                        $attendance['check_out_time']
                    ) ?>

                    <br>

                    <strong>Total Hours:</strong>

                    <?= e(
                        (string)$attendance['total_hours']
                    ) ?>

                </div>

            <?php else: ?>

                <p>

                    Click the button below
                    to complete today's shift.

                </p>

                <form method="POST">

                    <?= csrf_input() ?>

                    <button
                        type="submit"
                        name="check_out"
                        class="btn btn-danger">

                        Check Out

                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>