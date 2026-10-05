<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';
require_once '../includes/csrf.php';

$employeeId = (int)$_SESSION['employee_id'];

/*
|--------------------------------------------------------------------------
| Submit Leave Request
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_leave'])
)
{
    verify_csrf();

    $leaveType = trim($_POST['leave_type'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    $coveringEmployeeId =
        !empty($_POST['covering_employee_id'])
        ? (int)$_POST['covering_employee_id']
        : null;

    if (
        empty($leaveType) ||
        empty($startDate) ||
        empty($endDate)
    ) {
        flash(
            'error',
            'Please fill all required fields.'
        );

        redirect('apply-leave.php');
    }

    if ($startDate > $endDate) {

        flash(
            'error',
            'End date must be after start date.'
        );

        redirect('apply-leave.php');
    }

    $stmt = $pdo->prepare("
        INSERT INTO leave_requests
        (
            employee_id,
            covering_employee_id,
            leave_type,
            start_date,
            end_date,
            reason,
            status
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, 'pending'
        )
    ");

    $stmt->execute([
        $employeeId,
        $coveringEmployeeId,
        $leaveType,
        $startDate,
        $endDate,
        $reason
    ]);

    flash(
        'success',
        'Leave request submitted.'
    );

    redirect('apply-leave.php');
}

/*
|--------------------------------------------------------------------------
| Cancel Pending Leave
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['cancel_leave'])
)
{
    verify_csrf();

    $leaveId =
        (int)($_POST['leave_id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE leave_requests
        SET status='cancelled'
        WHERE id=?
        AND employee_id=?
        AND status='pending'
    ");

    $stmt->execute([
        $leaveId,
        $employeeId
    ]);

    flash(
        'success',
        'Leave request cancelled.'
    );

    redirect('apply-leave.php');
}

/*
|--------------------------------------------------------------------------
| Covering Employees
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name
    FROM employees
    WHERE active = 1
      AND id <> ?
    ORDER BY full_name
");

$stmt->execute([$employeeId]);

$employees =
    $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Leave History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        lr.*,
        e.full_name AS covering_name
    FROM leave_requests lr

    LEFT JOIN employees e
        ON e.id = lr.covering_employee_id

    WHERE lr.employee_id = ?

    ORDER BY lr.created_at DESC
");

$stmt->execute([
    $employeeId
]);

$leaveHistory =
    $stmt->fetchAll();

$pageTitle = 'Apply Leave';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-0">
            Leave Management
        </h2>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

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
        
        <!-- Apply Leave -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-warning">

                Apply For Leave

            </div>

            <div class="card-body">

                <form method="POST">

                    <?= csrf_input() ?>

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Leave Type

                            </label>

                            <select
                                name="leave_type"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Type
                                </option>

                                <option value="annual">
                                    Annual Leave
                                </option>

                                <option value="medical">
                                    Medical Leave
                                </option>

                                <option value="casual">
                                    Casual Leave
                                </option>

                                <option value="unpaid">
                                    Unpaid Leave
                                </option>

                            </select>

                        </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Covering Employee

                        </label>

                        <select
                            name="covering_employee_id"
                            class="form-select">

                            <option value="">
                                None
                            </option>

                            <?php foreach(
                                $employees
                                as $employee
                            ): ?>

                                <option
                                    value="<?= (int)$employee['id'] ?>">

                                    <?= e(
                                        $employee['full_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Start Date

                        </label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            required>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            End Date

                        </label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            required>

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="form-label">

                            Reason

                        </label>

                        <textarea
                            name="reason"
                            rows="4"
                            class="form-control"></textarea>

                    </div>

                </div>

                <button
                    type="submit"
                    name="submit_leave"
                    class="btn btn-warning">

                    Submit Leave Request

                </button>

            </form>

        </div>

    </div>

    <!-- Leave History -->

    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">

            Leave History

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Type</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Covering Employee</th>
                        <th>Status</th>
                        <th>Action</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach(
                        $leaveHistory
                        as $leave
                    ): ?>

                        <?php

                        $badge = match(
                            $leave['status']
                        ) {

                            'approved'
                                => 'bg-success',

                            'rejected'
                                => 'bg-danger',

                            'pending'
                                => 'bg-warning text-dark',

                            'cancelled'
                                => 'bg-secondary',

                            default
                                => 'bg-info'
                        };

                        ?>

                        <tr>

                            <td>
                                <?= e(
                                    ucfirst(
                                        $leave['leave_type']
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $leave['start_date']
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $leave['end_date']
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $leave['covering_name']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="badge <?= $badge ?>">

                                    <?= ucfirst(
                                        e(
                                            $leave['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?php if(
                                    $leave['status']
                                    === 'pending'
                                ): ?>

                                    <form
                                        method="POST">

                                        <?= csrf_input() ?>

                                        <input
                                            type="hidden"
                                            name="leave_id"
                                            value="<?= (int)$leave['id'] ?>">

                                        <button
                                            type="submit"
                                            name="cancel_leave"
                                            class="btn btn-sm btn-danger">

                                            Cancel

                                        </button>

                                    </form>

                                <?php endif; ?>

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