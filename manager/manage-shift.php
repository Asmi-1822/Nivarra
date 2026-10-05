<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Shift Management';

/*
|--------------------------------------------------------------------------
| Delete Shift
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    if (
        ($_POST['action'] ?? '') === 'delete'
    ) {

        $shiftId =
            (int)($_POST['shift_id'] ?? 0);

        if ($shiftId > 0) {

            $stmt = $pdo->prepare("
                DELETE FROM shifts
                WHERE id = ?
            ");

            $stmt->execute([$shiftId]);

            flash(
                'success',
                'Shift deleted.'
            );

            redirect(
                'manage-shifts.php'
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$employeeId =
    (int)($_GET['employee_id'] ?? 0);

$startDate =
    trim($_GET['start_date'] ?? '');

$endDate =
    trim($_GET['end_date'] ?? '');

$where = [];
$params = [];

if ($employeeId > 0) {

    $where[] =
        's.employee_id = ?';

    $params[] =
        $employeeId;
}

if ($startDate !== '') {

    $where[] =
        's.shift_date >= ?';

    $params[] =
        $startDate;
}

if ($endDate !== '') {

    $where[] =
        's.shift_date <= ?';

    $params[] =
        $endDate;
}

$whereSql = '';

if ($where) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}

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
    WHERE active = 1
    ORDER BY full_name
");

$employees =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Shifts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        s.*,

        e.full_name,

        (
            SELECT COUNT(*)
            FROM leave_requests lr
            WHERE lr.employee_id = s.employee_id
            AND lr.status = 'approved'
            AND s.shift_date
                BETWEEN lr.start_date
                AND lr.end_date
        ) leave_conflict,

        (
            SELECT COUNT(*)
            FROM shift_coverage_requests scr
            WHERE scr.shift_id = s.id
            AND scr.status='pending'
        ) coverage_requests

    FROM shifts s

    INNER JOIN employees e
        ON e.id = s.employee_id

    $whereSql

    ORDER BY
        s.shift_date,
        s.start_time
");

$stmt->execute($params);

$shifts =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="fw-bold">
                Shift Management
            </h2>

            <p class="text-muted mb-0">
                Manage shift assignment
            </p>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="assign-shift.php"
                class="btn btn-maroon">

                Assign Shift

            </a>

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

    <!-- Filters -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-md-4">

                        <select
                            name="employee_id"
                            class="form-select">

                            <option value="">

                                All Employees

                            </option>

                            <?php foreach($employees as $employee): ?>

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

                    <div class="col-md-3">

                        <input
                            type="date"
                            name="start_date"
                            value="<?= e($startDate) ?>"
                            class="form-control">

                    </div>

                    <div class="col-md-3">

                        <input
                            type="date"
                            name="end_date"
                            value="<?= e($endDate) ?>"
                            class="form-control">

                    </div>

                    <div class="col-md-2">

                        <button
                            class="btn btn-maroon w-100">

                            Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- Shift Table -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>

                    <tr>

                        <th>Date</th>
                        <th>Employee</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach($shifts as $shift): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $shift['shift_date']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $shift['full_name']
                                ) ?>

                            </td>

                            <td>

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

                            </td>

                            <td>

                                <?= e(
                                    ucfirst(
                                        $shift['shift_type']
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?php if(
                                    (int)$shift['leave_conflict'] > 0
                                ): ?>

                                    <span class="badge bg-danger">

                                        Leave Conflict

                                    </span>

                                <?php elseif(
                                    (int)$shift['coverage_requests'] > 0
                                ): ?>

                                    <span class="badge bg-warning">

                                        Coverage Requested

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-success">

                                        Scheduled

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <form
                                    method="POST"
                                    class="d-inline">

                                    <?= csrf_input() ?>

                                    <input
                                        type="hidden"
                                        name="shift_id"
                                        value="<?= (int)$shift['id'] ?>">

                                    <button
                                        type="submit"
                                        name="action"
                                        value="delete"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete shift?')">

                                        Delete

                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <p>
        
    </p>

    <a href="dashboard.php" class="btn btn-secondary">
        Back
    </a>

</div>

<?php include '../includes/footer.php'; ?>