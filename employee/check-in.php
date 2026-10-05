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
| Check In
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['check_in'])
) {
    verify_csrf();

    if ($attendance) {

        flash(
            'error',
            'You have already checked in today.'
        );

        redirect('check-in.php');
    }

    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs
        (
            employee_id,
            attendance_date,
            check_in_time,
            status
        )
        VALUES
        (
            ?, ?, NOW(), 'present'
        )
    ");

    $stmt->execute([
        $employeeId,
        $today
    ]);

    flash(
        'success',
        'Check-in successful.'
    );

    redirect('check-in.php');
}

$pageTitle = 'Check In';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="mb-4">

                Employee Check-In

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

            <?php if($attendance): ?>

                <div class="alert alert-success mb-0">

                    <strong>

                        Checked In:

                    </strong>

                    <?= e(
                        $attendance['check_in_time']
                    ) ?>

                </div>

            <?php else: ?>

                <p>

                    Click the button below to
                    record your attendance.

                </p>

                <form method="POST">

                    <?= csrf_input() ?>

                    <button
                        type="submit"
                        name="check_in"
                        class="btn btn-success">

                        Check In

                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>