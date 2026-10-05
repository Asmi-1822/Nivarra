<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Shift Calendar';

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$employeeId = (int)($_GET['employee_id'] ?? 0);

$month = (int)(
    $_GET['month'] ?? date('n')
);

$year = (int)(
    $_GET['year'] ?? date('Y')
);

$month = max(1, min(12, $month));

$year = max(
    date('Y') - 2,
    min(
        date('Y') + 5,
        $year
    )
);

$firstDay = sprintf(
    '%04d-%02d-01',
    $year,
    $month
);

$daysInMonth = (int)date(
    't',
    strtotime($firstDay)
);

$where = [];
$params = [];

$where[] = 'MONTH(s.shift_date)=?';
$params[] = $month;

$where[] = 'YEAR(s.shift_date)=?';
$params[] = $year;

if ($employeeId > 0) {

    $where[] = 's.employee_id=?';
    $params[] = $employeeId;
}

$whereSql = implode(
    ' AND ',
    $where
);

/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        full_name
    FROM employees
    WHERE active=1
    ORDER BY full_name
");

$employees = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

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
        ON e.id=s.employee_id
    WHERE $whereSql
    ORDER BY
        shift_date,
        start_time
");

$stmt->execute($params);

$shiftRows = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

$calendar = [];

foreach ($shiftRows as $shift) {

    $calendar[
        $shift['shift_date']
    ][] = $shift;
}

/*
|--------------------------------------------------------------------------
| Leave Days
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        lr.*,
        e.full_name
    FROM leave_requests lr
    INNER JOIN employees e
        ON e.id = lr.employee_id
    WHERE lr.status='approved'
      AND MONTH(lr.start_date)<=?
      AND MONTH(lr.end_date)>=?
      AND YEAR(lr.start_date)<=?
      AND YEAR(lr.end_date)>=?
");

$stmt->execute([
    $month,
    $month,
    $year,
    $year
]);

$leaveRows = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

$leaveMap = [];

foreach ($leaveRows as $leave) {

    $start = strtotime(
        $leave['start_date']
    );

    $end = strtotime(
        $leave['end_date']
    );

    while ($start <= $end) {

        $date = date(
            'Y-m-d',
            $start
        );

        $leaveMap[$date][] = $leave;

        $start = strtotime(
            '+1 day',
            $start
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
        s.shift_date,
        e.full_name
    FROM shift_coverage_requests scr
    INNER JOIN shifts s
        ON s.id=scr.shift_id
    INNER JOIN employees e
        ON e.id=s.employee_id
    WHERE scr.status='pending'
");

$coverageRows = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

$coverageMap = [];

foreach ($coverageRows as $row) {

    $coverageMap[
        $row['shift_date']
    ][] = $row;
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="nivarra-title mb-1">
                Shift Calendar
            </h2>

            <p class="nivarra-subtitle">
                View scheduled shifts, approved leave, and pending coverage requests.
            </p>

        </div>

        <div class="d-flex gap-2">

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">
                Back
            </a>

            <a
                href="assign-shift.php"
                class="btn btn-maroon d-flex align-items-center">

                Assign Shift

            </a>

        </div>

    </div>


    <!-- Filters -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header">
            Calendar Filters
        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row g-3 align-items-end">

                    <!-- Employee -->

                    <div class="col-md-4">

                        <label
                            for="employee_id"
                            class="form-label fw-semibold">

                            Employee

                        </label>

                        <select
                            id="employee_id"
                            name="employee_id"
                            class="form-select">

                            <option value="0">
                                All Employees
                            </option>

                            <?php foreach ($employees as $employee): ?>

                                <option
                                    value="<?= (int)$employee['id'] ?>"
                                    <?= $employeeId === (int)$employee['id']
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e(
                                        $employee['full_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Month -->

                    <div class="col-md-3">

                        <label
                            for="month"
                            class="form-label fw-semibold">

                            Month

                        </label>

                        <select
                            id="month"
                            name="month"
                            class="form-select">

                            <?php for ($m = 1; $m <= 12; $m++): ?>

                                <option
                                    value="<?= $m ?>"
                                    <?= $month === $m
                                        ? 'selected'
                                        : '' ?>>

                                    <?= date(
                                        'F',
                                        mktime(
                                            0,
                                            0,
                                            0,
                                            $m,
                                            1
                                        )
                                    ) ?>

                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>


                    <!-- Year -->

                    <div class="col-md-3">

                        <label
                            for="year"
                            class="form-label fw-semibold">

                            Year

                        </label>

                        <input
                            type="number"
                            id="year"
                            name="year"
                            value="<?= $year ?>"
                            class="form-control">

                    </div>


                    <!-- View -->

                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-maroon w-100">

                            View

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- Calendar -->

    <div class="card nivarra-card mb-4">

        <div class="nivarra-card-header d-flex justify-content-between align-items-center">

            <span>
                <?= e(
                    date(
                        'F Y',
                        strtotime($firstDay)
                    )
                ) ?>
            </span>

            <span class="badge bg-light text-dark">
                <?= $daysInMonth ?> Days
            </span>

        </div>

        <div class="card-body p-2">

            <div class="calendar-container">

                <div class="row g-0">

                    <?php for (
                        $day = 1;
                        $day <= $daysInMonth;
                        $day++
                    ): ?>

                        <?php

                        $date = sprintf(
                            '%04d-%02d-%02d',
                            $year,
                            $month,
                            $day
                        );

                        ?>

                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 calendar-day-wrapper">

                            <div class="calendar-day">

                                <div class="day-number">

                                    <?= $day ?>

                                    <span class="text-muted small fw-normal">

                                        <?= date(
                                            'D',
                                            strtotime($date)
                                        ) ?>

                                    </span>

                                </div>


                                <!-- Scheduled Shifts -->

                                <?php if (
                                    isset($calendar[$date])
                                ): ?>

                                    <?php foreach (
                                        $calendar[$date]
                                        as $shift
                                    ): ?>

                                        <a
                                            href="assign-shift.php?id=<?= (int)$shift['id'] ?>"
                                            class="badge bg-success shift-badge text-decoration-none">

                                            <?= e(
                                                $shift['full_name']
                                            ) ?>

                                            <br>

                                            <small>

                                                <?= e(
                                                    substr(
                                                        $shift['start_time'],
                                                        0,
                                                        5
                                                    )
                                                ) ?>

                                                -

                                                <?= e(
                                                    substr(
                                                        $shift['end_time'],
                                                        0,
                                                        5
                                                    )
                                                ) ?>

                                            </small>

                                        </a>

                                    <?php endforeach; ?>

                                <?php endif; ?>


                                <!-- Leave -->

                                <?php if (
                                    isset($leaveMap[$date])
                                ): ?>

                                    <?php foreach (
                                        $leaveMap[$date]
                                        as $leave
                                    ): ?>

                                        <span
                                            class="badge bg-danger shift-badge">

                                            Leave:
                                            <?= e(
                                                $leave['full_name']
                                            ) ?>

                                        </span>

                                    <?php endforeach; ?>

                                <?php endif; ?>


                                <!-- Coverage -->

                                <?php if (
                                    isset($coverageMap[$date])
                                ): ?>

                                    <?php foreach (
                                        $coverageMap[$date]
                                        as $coverage
                                    ): ?>

                                        <span
                                            class="badge bg-warning text-dark shift-badge">

                                            Coverage:
                                            <?= e(
                                                $coverage['full_name']
                                            ) ?>

                                        </span>

                                    <?php endforeach; ?>

                                <?php endif; ?>


                                <?php if (
                                    !isset($calendar[$date]) &&
                                    !isset($leaveMap[$date]) &&
                                    !isset($coverageMap[$date])
                                ): ?>

                                    <span class="text-muted small">
                                        No activity
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endfor; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- Legend -->

    <div class="card nivarra-card">

        <div class="card-body">

            <div class="calendar-legend">

                <span class="legend-item">

                    <span class="legend-color legend-shift"></span>

                    Scheduled Shift

                </span>

                <span class="legend-item">

                    <span class="legend-color legend-leave"></span>

                    Approved Leave

                </span>

                <span class="legend-item">

                    <span class="legend-color legend-coverage"></span>

                    Pending Coverage

                </span>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>