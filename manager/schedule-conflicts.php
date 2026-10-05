<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Schedule Conflicts';

$leaveConflicts = [];
$overlaps = [];
$uncoveredShifts = [];
$weeklyHours = [];

/*
|--------------------------------------------------------------------------
| Leave Conflicts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        s.id AS shift_id,
        s.shift_date,
        s.start_time,
        s.end_time,
        e.full_name,
        lr.start_date AS leave_start,
        lr.end_date AS leave_end
    FROM shifts s
    INNER JOIN employees e
        ON e.id = s.employee_id
    INNER JOIN leave_requests lr
        ON lr.employee_id = s.employee_id
    WHERE lr.status = 'approved'
      AND s.shift_date BETWEEN lr.start_date AND lr.end_date
    ORDER BY s.shift_date
");

$leaveConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Overlapping Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        s1.id AS shift1_id,
        s2.id AS shift2_id,
        s1.shift_date,
        e.full_name,
        s1.start_time AS start1,
        s1.end_time AS end1,
        s2.start_time AS start2,
        s2.end_time AS end2
    FROM shifts s1
    INNER JOIN shifts s2
        ON s1.employee_id = s2.employee_id
        AND s1.id < s2.id
        AND s1.shift_date = s2.shift_date
        AND (
            s1.start_time < s2.end_time
            AND s1.end_time > s2.start_time
        )
    INNER JOIN employees e
        ON e.id = s1.employee_id
    ORDER BY s1.shift_date
");

$overlaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Uncovered Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        scr.id,
        s.shift_date,
        s.start_time,
        s.end_time,
        e.full_name
    FROM shift_coverage_requests scr
    INNER JOIN shifts s
        ON s.id = scr.shift_id
    INNER JOIN employees e
        ON e.id = s.employee_id
    WHERE scr.status = 'pending'
    ORDER BY s.shift_date
");

$uncoveredShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Weekly Hours
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.id,
        e.full_name,
        YEARWEEK(s.shift_date, 1) AS week_number,
        ROUND(
            SUM(
                TIME_TO_SEC(
                    TIMEDIFF(
                        s.end_time,
                        s.start_time
                    )
                ) / 3600
            ),
            2
        ) AS total_hours
    FROM shifts s
    INNER JOIN employees e
        ON e.id = s.employee_id
    GROUP BY
        e.id,
        YEARWEEK(s.shift_date, 1)
    HAVING total_hours > 48
    ORDER BY total_hours DESC
");

$weeklyHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="nivarra-title mb-1">
                Schedule Conflict Dashboard
            </h2>

            <p class="text-muted mb-0">
                Review leave conflicts, overlapping shifts, uncovered shifts,
                and excessive weekly working hours.
            </p>
        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">
            Back
        </a>

    </div>


    <!-- Leave Conflicts -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Employees Scheduled During Leave
            </span>

            <span class="badge bg-light text-dark conflict-count">
                <?= count($leaveConflicts) ?>
            </span>

        </div>

        <div class="card-body">

            <?php if (!$leaveConflicts): ?>

                <div class="alert alert-success mb-0">
                    <span class="status-icon">✓</span>
                    No leave conflicts found.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover nivarra-table mb-0">

                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Leave Period</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($leaveConflicts as $row): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($row['full_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($row['shift_date']) ?>
                                </td>

                                <td>
                                    <?= e($row['start_time']) ?>
                                    -
                                    <?= e($row['end_time']) ?>
                                </td>

                                <td>
                                    <?= e($row['leave_start']) ?>
                                    →
                                    <?= e($row['leave_end']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Overlapping Shifts -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Overlapping Shifts
            </span>

            <span class="badge bg-light text-dark conflict-count">
                <?= count($overlaps) ?>
            </span>

        </div>

        <div class="card-body">

            <?php if (!$overlaps): ?>

                <div class="alert alert-success mb-0">
                    <span class="status-icon">✓</span>
                    No overlaps detected.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover nivarra-table mb-0">

                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Shift A</th>
                                <th>Shift B</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($overlaps as $row): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($row['full_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($row['shift_date']) ?>
                                </td>

                                <td>
                                    <?= e($row['start1']) ?>
                                    -
                                    <?= e($row['end1']) ?>
                                </td>

                                <td>
                                    <?= e($row['start2']) ?>
                                    -
                                    <?= e($row['end2']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Uncovered Shifts -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Uncovered Shifts
            </span>

            <span class="badge bg-light text-dark conflict-count">
                <?= count($uncoveredShifts) ?>
            </span>

        </div>

        <div class="card-body">

            <?php if (!$uncoveredShifts): ?>

                <div class="alert alert-success mb-0">
                    <span class="status-icon">✓</span>
                    No uncovered shifts.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover nivarra-table mb-0">

                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Time</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($uncoveredShifts as $row): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($row['full_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($row['shift_date']) ?>
                                </td>

                                <td>
                                    <?= e($row['start_time']) ?>
                                    -
                                    <?= e($row['end_time']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Weekly Hours -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Employees Above 48 Hours / Week
            </span>

            <span class="badge bg-light text-dark conflict-count">
                <?= count($weeklyHours) ?>
            </span>

        </div>

        <div class="card-body">

            <?php if (!$weeklyHours): ?>

                <div class="alert alert-success mb-0">
                    <span class="status-icon">✓</span>
                    No excessive schedules found.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover nivarra-table mb-0">

                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Week</th>
                                <th>Total Hours</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($weeklyHours as $row): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($row['full_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($row['week_number']) ?>
                                </td>

                                <td>

                                    <span class="badge bg-danger">
                                        <?= e($row['total_hours']) ?> hrs
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>