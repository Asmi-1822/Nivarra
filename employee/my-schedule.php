<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';

$employeeId = (int)$_SESSION['employee_id'];

/*
|--------------------------------------------------------------------------
| Current Week Hours
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        SUM(
            TIMESTAMPDIFF(
                MINUTE,
                start_time,
                end_time
            )
        ) AS total_minutes
    FROM shifts
    WHERE employee_id = ?
    AND YEARWEEK(shift_date,1) = YEARWEEK(CURDATE(),1)
");

$stmt->execute([$employeeId]);

$weeklyMinutes =
    (int)($stmt->fetchColumn() ?? 0);

$weeklyHours =
    round($weeklyMinutes / 60, 2);

/*
|--------------------------------------------------------------------------
| Upcoming Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM shifts
    WHERE employee_id = ?
    AND shift_date >= CURDATE()
    ORDER BY shift_date ASC
");

$stmt->execute([$employeeId]);

$upcomingShifts =
    $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Previous Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM shifts
    WHERE employee_id = ?
    AND shift_date < CURDATE()
    ORDER BY shift_date DESC
    LIMIT 20
");

$stmt->execute([$employeeId]);

$pastShifts =
    $stmt->fetchAll();

$pageTitle = 'My Schedule';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-0">
            My Schedule
        </h2>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>

    <!-- Weekly Summary -->

    <div class="row mb-4">

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>
                        <?= $weeklyHours ?>
                    </h3>

                    <small>
                        Hours This Week
                    </small>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>
                        48
                    </h3>

                    <small>
                        Weekly Limit
                    </small>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>
                        <?= count($upcomingShifts) ?>
                    </h3>

                    <small>
                        Upcoming Shifts
                    </small>

                </div>

            </div>

        </div>

    </div>

    <?php if($weeklyHours > 48): ?>

        <div class="alert alert-danger">

            Warning:
            Weekly hours exceed 48-hour limit.

        </div>

    <?php elseif($weeklyHours >= 40): ?>

        <div class="alert alert-warning">

            You are approaching the 48-hour limit.

        </div>

    <?php endif; ?>

    <!-- Upcoming Shifts -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            Upcoming Shifts

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Position</th>
                        <th>Status</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if(empty($upcomingShifts)): ?>

                        <tr>

                            <td colspan="5" class="text-center">

                                No upcoming shifts.

                            </td>

                        </tr>

                    <?php endif; ?>

                    <?php foreach($upcomingShifts as $shift): ?>

                        <tr>

                            <td>
                                <?= e($shift['shift_date']) ?>
                            </td>

                            <td>
                                <?= e($shift['start_time']) ?>
                            </td>

                            <td>
                                <?= e($shift['end_time']) ?>
                            </td>

                            <td>
                                <?= e($shift['position']) ?>
                            </td>

                            <td>
                                <?= e($shift['status']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Past Shifts -->

    <div class="card shadow-sm">

        <div class="card-header bg-secondary text-white">

            Shift History

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Position</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if(empty($pastShifts)): ?>

                        <tr>

                            <td colspan="4" class="text-center">

                                No shift history.

                            </td>

                        </tr>

                    <?php endif; ?>

                    <?php foreach($pastShifts as $shift): ?>

                        <tr>

                            <td>
                                <?= e($shift['shift_date']) ?>
                            </td>

                            <td>
                                <?= e($shift['start_time']) ?>
                            </td>

                            <td>
                                <?= e($shift['end_time']) ?>
                            </td>

                            <td>
                                <?= e($shift['position']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>