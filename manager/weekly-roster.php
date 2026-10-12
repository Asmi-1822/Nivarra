<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Weekly Roster';

/*
|--------------------------------------------------------------------------
| Week Selection
|--------------------------------------------------------------------------
*/

$weekStart = $_GET['week']
    ?? date('Y-m-d', strtotime('monday this week'));

if (
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $weekStart
    )
) {
    $weekStart = date(
        'Y-m-d',
        strtotime('monday this week')
    );
}

$days = [];

for ($i = 0; $i < 7; $i++) {
    $days[] = date(
        'Y-m-d',
        strtotime($weekStart . " +{$i} day")
    );
}

$weekEnd = end($days);

/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        employee_code,
        full_name,
        role
    FROM employees
    WHERE active = 1
    ORDER BY
        role DESC,
        full_name
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.*,
        e.full_name
    FROM shifts s
    INNER JOIN employees e
        ON e.id = s.employee_id
    WHERE s.shift_date BETWEEN ? AND ?
    ORDER BY
        s.shift_date,
        s.start_time
");

$stmt->execute([
    $weekStart,
    $weekEnd
]);

$shiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$shifts = [];

foreach ($shiftRows as $shift) {
    $shifts[
        $shift['employee_id']
    ][
        $shift['shift_date']
    ][] = $shift;
}

/*
|--------------------------------------------------------------------------
| Leave Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        employee_id,
        start_date,
        end_date
    FROM leave_requests
    WHERE status = 'approved'
      AND start_date <= ?
      AND end_date >= ?
");

$stmt->execute([
    $weekEnd,
    $weekStart
]);

$leaveRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$leaveMap = [];

foreach ($leaveRows as $leave) {

    $current = strtotime($leave['start_date']);
    $end = strtotime($leave['end_date']);

    while ($current <= $end) {

        $date = date(
            'Y-m-d',
            $current
        );

        $leaveMap[
            $leave['employee_id']
        ][
            $date
        ] = true;

        $current = strtotime(
            '+1 day',
            $current
        );
    }
}

/*
|--------------------------------------------------------------------------
| Coverage Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        scr.shift_id,
        scr.status,
        s.employee_id,
        s.shift_date
    FROM shift_coverage_requests scr
    INNER JOIN shifts s
        ON s.id = scr.shift_id
    WHERE scr.status = 'pending'
");

$coverageRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$coverageMap = [];

foreach ($coverageRows as $row) {

    $coverageMap[
        $row['employee_id']
    ][
        $row['shift_date']
    ] = true;
}

/*
|--------------------------------------------------------------------------
| Staffing Count
|--------------------------------------------------------------------------
*/

$staffing = [];

foreach ($days as $date) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM shifts
        WHERE shift_date = ?
    ");

    $stmt->execute([$date]);

    $staffing[$date] = (int)$stmt->fetchColumn();
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="nivarra-title mb-1">
                Weekly Roster
            </h2>

            <p class="nivarra-subtitle">
                <?= e(date('d M Y', strtotime($weekStart))) ?>
                -
                <?= e(date('d M Y', strtotime($weekEnd))) ?>
            </p>
        </div>

        <div class="d-flex gap-2">

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">
                Back
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-maroon">
                Print Roster
            </button>

        </div>

    </div>

    <!-- Week Selector -->
    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header">
            Select Roster Week
        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row g-3 align-items-end">

                    <div class="col-md-4">

                        <label
                            for="week"
                            class="form-label fw-semibold">
                            Week Start
                        </label>

                        <input
                            type="date"
                            id="week"
                            name="week"
                            value="<?= e($weekStart) ?>"
                            class="form-control">

                    </div>

                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-maroon w-100">
                            Load
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- Staffing Summary -->
    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header">
            Weekly Staffing Summary
        </div>

        <div class="card-body">

            <div class="row g-3">

                <?php foreach ($days as $date): ?>

                    <div class="col-6 col-md-4 col-lg">

                        <div class="card staffing-card">

                            <div class="card-body text-center">

                                <div class="staffing-day">
                                    <?= e(
                                        date(
                                            'D',
                                            strtotime($date)
                                        )
                                    ) ?>
                                </div>

                                <div class="staffing-date mb-2">
                                    <?= e(
                                        date(
                                            'd M',
                                            strtotime($date)
                                        )
                                    ) ?>
                                </div>

                                <div class="staffing-count">
                                    <?= $staffing[$date] ?>
                                </div>

                                <small class="text-muted">
                                    Staff Scheduled
                                </small>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

    <!-- Roster -->
    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                Weekly Employee Roster
            </span>

            <span class="small fw-normal">
                <?= count($employees) ?> Active Employees
            </span>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table
                    class="table table-bordered nivarra-table">

                    <thead>

                        <tr>

                            <th
                                class="text-start"
                                style="min-width:210px;">
                                Employee
                            </th>

                            <?php foreach ($days as $date): ?>

                                <th>

                                    <?= e(
                                        date(
                                            'D d',
                                            strtotime($date)
                                        )
                                    ) ?>

                                    <br>

                                    <small class="text-muted">
                                        <?= e(
                                            date(
                                                'M',
                                                strtotime($date)
                                            )
                                        ) ?>
                                    </small>

                                </th>

                            <?php endforeach; ?>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($employees)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center text-muted py-4">

                                    No active employees found.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($employees as $employee): ?>

                                <tr>

                                    <td class="employee-cell">

                                        <strong>
                                            <?= e(
                                                $employee['full_name']
                                            ) ?>
                                        </strong>

                                        <br>

                                        <small class="text-muted">
                                            <?= e(
                                                $employee['employee_code']
                                            ) ?>
                                        </small>

                                        <br>

                                        <span class="badge bg-secondary role-badge mt-1">
                                            <?= e(
                                                ucfirst(
                                                    (string)$employee['role']
                                                )
                                            ) ?>
                                        </span>

                                    </td>

                                    <?php foreach ($days as $date): ?>

                                        <td class="roster-day-cell">

                                            <?php if (
                                                isset(
                                                    $leaveMap[
                                                        $employee['id']
                                                    ][
                                                        $date
                                                    ]
                                                )
                                            ): ?>

                                                <div class="leave-box">

                                                    Approved Leave

                                                </div>

                                            <?php endif; ?>

                                            <?php if (
                                                isset(
                                                    $shifts[
                                                        $employee['id']
                                                    ][
                                                        $date
                                                    ]
                                                )
                                            ): ?>

                                                <?php foreach (
                                                    $shifts[
                                                        $employee['id']
                                                    ][
                                                        $date
                                                    ]
                                                    as $shift
                                                ): ?>

                                                    <div class="shift-box">

                                                        <?= e(
                                                            substr(
                                                                (string)$shift['start_time'],
                                                                0,
                                                                5
                                                            )
                                                        ) ?>

                                                        -

                                                        <?= e(
                                                            substr(
                                                                (string)$shift['end_time'],
                                                                0,
                                                                5
                                                            )
                                                        ) ?>

                                                        <br>

                                                        <small>
                                                            <?= e(
                                                                ucfirst(
                                                                    (string)$shift['shift_type']
                                                                )
                                                            ) ?>
                                                        </small>

                                                    </div>

                                                <?php endforeach; ?>

                                            <?php endif; ?>

                                            <?php if (
                                                isset(
                                                    $coverageMap[
                                                        $employee['id']
                                                    ][
                                                        $date
                                                    ]
                                                )
                                            ): ?>

                                                <div class="coverage-box">

                                                    Coverage Requested

                                                </div>

                                            <?php endif; ?>

                                            <?php if (
                                                !isset(
                                                    $leaveMap[
                                                        $employee['id']
                                                    ][
                                                        $date
                                                    ]
                                                )
                                                &&
                                                !isset(
                                                    $shifts[
                                                        $employee['id']
                                                    ][
                                                        $date
                                                    ]
                                                )
                                                &&
                                                !isset(
                                                    $coverageMap[
                                                        $employee['id']
                                                    ][
                                                        $date
                                                    ]
                                                )
                                            ): ?>

                                                <span class="text-muted small">
                                                    No shift
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    <?php endforeach; ?>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Legend -->
    <div class="card nivarra-card no-print">

        <div class="card-body">

            <div class="fw-semibold mb-3">
                Roster Legend
            </div>

            <div class="d-flex flex-wrap">

                <div class="legend-box">
                    <span class="legend-color legend-shift"></span>
                    Scheduled Shift
                </div>

                <div class="legend-box">
                    <span class="legend-color legend-leave"></span>
                    Approved Leave
                </div>

                <div class="legend-box">
                    <span class="legend-color legend-coverage"></span>
                    Coverage Requested
                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>