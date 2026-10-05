<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Staffing Overview';

/*
|--------------------------------------------------------------------------
| Employees Currently On Leave
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.full_name,
        e.employee_code,
        lr.leave_type,
        lr.start_date,
        lr.end_date
    FROM leave_requests lr
    INNER JOIN employees e
        ON e.id = lr.employee_id
    WHERE lr.status = 'approved'
      AND CURDATE() BETWEEN lr.start_date AND lr.end_date
    ORDER BY lr.end_date ASC
");

$currentLeave = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Upcoming Leave (30 Days)
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.full_name,
        lr.leave_type,
        lr.start_date,
        lr.end_date,
        ce.full_name AS covering_employee
    FROM leave_requests lr
    INNER JOIN employees e
        ON e.id = lr.employee_id
    LEFT JOIN employees ce
        ON ce.id = lr.covering_employee_id
    WHERE lr.status = 'approved'
      AND lr.start_date BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY lr.start_date
");

$upcomingLeave = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Leave Requests Without Coverage
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*) total
    FROM leave_requests
    WHERE status = 'approved'
      AND covering_employee_id IS NULL
");

$uncoveredLeaves = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Leave Overlap Detection
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        a.id AS leave_a,
        b.id AS leave_b
    FROM leave_requests a
    JOIN leave_requests b
      ON a.employee_id <> b.employee_id
     AND a.status = 'approved'
     AND b.status = 'approved'
     AND a.start_date <= b.end_date
     AND a.end_date >= b.start_date
");

$overlaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$overlapCount = count($overlaps);

/*
|--------------------------------------------------------------------------
| Open Shift Coverage Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*) total
    FROM shift_coverage_requests
    WHERE status = 'pending'
");

$pendingCoverage = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Employees Scheduled Next 7 Days
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT employee_id)
    FROM shifts
    WHERE shift_date BETWEEN CURDATE()
        AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");

$scheduledEmployees = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Workforce Forecast
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        MONTH(start_date) AS month_no,
        COUNT(*) AS total_leave
    FROM leave_requests
    WHERE status = 'approved'
    GROUP BY MONTH(start_date)
");

$forecast = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="nivarra-title mb-1">
                Staffing Overview
            </h2>

            <p class="nivarra-subtitle">
                Monitor employee leave, coverage, scheduling, and workforce requirements.
            </p>

        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">
            Back
        </a>

    </div>


    <!-- Staffing Statistics -->

    <div class="row g-3 mb-4">

        <!-- Uncovered Leave -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card border-start border-danger border-4">

                <div class="card-body text-center">

                    <div class="stat-number text-danger">
                        <?= $uncoveredLeaves ?>
                    </div>

                    <div class="stat-label">
                        Leave Requests Without Coverage
                    </div>

                </div>

            </div>

        </div>


        <!-- Leave Overlaps -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card border-start border-warning border-4">

                <div class="card-body text-center">

                    <div class="stat-number text-warning">
                        <?= $overlapCount ?>
                    </div>

                    <div class="stat-label">
                        Leave Overlaps
                    </div>

                </div>

            </div>

        </div>


        <!-- Pending Coverage -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card border-start border-primary border-4">

                <div class="card-body text-center">

                    <div class="stat-number text-primary">
                        <?= $pendingCoverage ?>
                    </div>

                    <div class="stat-label">
                        Pending Coverage Requests
                    </div>

                </div>

            </div>

        </div>


        <!-- Scheduled Employees -->

        <div class="col-md-6 col-xl-3">

            <div class="card stat-card border-start border-success border-4">

                <div class="card-body text-center">

                    <div class="stat-number text-success">
                        <?= $scheduledEmployees ?>
                    </div>

                    <div class="stat-label">
                        Scheduled Employees (7 Days)
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Employees Currently On Leave -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Employees Currently On Leave
            </span>

            <span class="badge bg-light text-dark">
                <?= count($currentLeave) ?>
            </span>

        </div>

        <div class="card-body">

            <?php if (!$currentLeave): ?>

                <div class="alert alert-success mb-0">
                    No employees currently on leave.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover nivarra-table mb-0">

                        <thead>

                        <tr>

                            <th>Employee</th>
                            <th>Employee Code</th>
                            <th>Leave Type</th>
                            <th>Until</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($currentLeave as $row): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($row['full_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($row['employee_code']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        ucfirst(
                                            (string)$row['leave_type']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($row['end_date']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Upcoming Approved Leave -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Upcoming Approved Leave
            </span>

            <span class="badge bg-light text-dark">
                <?= count($upcomingLeave) ?>
            </span>

        </div>

        <div class="card-body">

            <?php if (!$upcomingLeave): ?>

                <div class="alert alert-success mb-0">
                    No upcoming approved leave in the next 30 days.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover nivarra-table mb-0">

                        <thead>

                        <tr>

                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Covering Employee</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($upcomingLeave as $row): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= e($row['full_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e(
                                        ucfirst(
                                            (string)$row['leave_type']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($row['start_date']) ?>
                                </td>

                                <td>
                                    <?= e($row['end_date']) ?>
                                </td>

                                <td>

                                    <?php if (
                                        !empty($row['covering_employee'])
                                    ): ?>

                                        <span class="text-success">
                                            <?= e(
                                                $row['covering_employee']
                                            ) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            Unassigned
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


    <!-- Workforce Forecast -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Workforce Forecast
            </span>

            <span class="badge bg-light text-dark">
                <?= count($forecast) ?>
            </span>

        </div>

        <div class="card-body">

            <?php if (!$forecast): ?>

                <div class="alert alert-info mb-0">
                    No approved leave forecast data is available.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover nivarra-table mb-0">

                        <thead>

                        <tr>

                            <th>Month</th>
                            <th>Approved Leave Requests</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($forecast as $row): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= e(
                                            date(
                                                'F',
                                                mktime(
                                                    0,
                                                    0,
                                                    0,
                                                    (int)$row['month_no'],
                                                    1
                                                )
                                            )
                                        ) ?>
                                    </strong>

                                </td>

                                <td>

                                    <span class="badge bg-primary">
                                        <?= (int)$row['total_leave'] ?>
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