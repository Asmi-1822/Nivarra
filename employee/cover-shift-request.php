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
| Submit Request
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_request'])
) {
    verify_csrf();

    $shiftId =
        (int)($_POST['shift_id'] ?? 0);

    $coveringEmployeeId =
        (int)($_POST['covering_employee_id'] ?? 0);

    /*
    | Verify shift belongs to employee
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM shifts
        WHERE id = ?
        AND employee_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $shiftId,
        $employeeId
    ]);

    if (!$stmt->fetch()) {

        flash(
            'error',
            'Invalid shift selected.'
        );

        redirect(
            'cover-shift-request.php'
        );
    }

    /*
    | Prevent duplicate pending request
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM shift_coverage_requests
        WHERE shift_id = ?
        AND status = 'pending'
        LIMIT 1
    ");

    $stmt->execute([$shiftId]);

    if ($stmt->fetch()) {

        flash(
            'error',
            'A coverage request already exists for this shift.'
        );

        redirect(
            'cover-shift-request.php'
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO shift_coverage_requests
        (
            shift_id,
            requesting_employee_id,
            covering_employee_id,
            status
        )
        VALUES
        (
            ?, ?, ?, 'pending'
        )
    ");

    $stmt->execute([
        $shiftId,
        $employeeId,
        $coveringEmployeeId
    ]);

    flash(
        'success',
        'Coverage request submitted.'
    );

    redirect(
        'cover-shift-request.php'
    );
}

/*
|--------------------------------------------------------------------------
| Cancel Request
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['cancel_request'])
) {
    verify_csrf();

    $requestId =
        (int)($_POST['request_id'] ?? 0);

    $stmt = $pdo->prepare("
        DELETE FROM shift_coverage_requests
        WHERE id = ?
        AND requesting_employee_id = ?
        AND status = 'pending'
    ");

    $stmt->execute([
        $requestId,
        $employeeId
    ]);

    flash(
        'success',
        'Coverage request cancelled.'
    );

    redirect(
        'cover-shift-request.php'
    );
}

/*
|--------------------------------------------------------------------------
| Employee Shifts
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

$shifts =
    $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Available Employees
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
| Existing Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        scr.*,

        s.shift_date,
        s.start_time,
        s.end_time,

        e.full_name AS covering_name

    FROM shift_coverage_requests scr

    INNER JOIN shifts s
        ON s.id = scr.shift_id

    LEFT JOIN employees e
        ON e.id = scr.covering_employee_id

    WHERE scr.requesting_employee_id = ?

    ORDER BY scr.created_at DESC
");

$stmt->execute([$employeeId]);

$requests =
    $stmt->fetchAll();

$pageTitle =
    'Shift Coverage Requests';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-4">
            Shift Coverage Request
        </h2>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center">

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

    <!-- Request Form -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            Request Shift Coverage

        </div>

        <div class="card-body">

            <form method="POST">

                <?= csrf_input() ?>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Shift

                        </label>

                        <select
                            name="shift_id"
                            class="form-select"
                            required>

                            <option value="">
                                Select Shift
                            </option>

                            <?php foreach($shifts as $shift): ?>

                                <option
                                    value="<?= (int)$shift['id'] ?>">

                                    <?= e($shift['shift_date']) ?>

                                    |

                                    <?= e($shift['start_time']) ?>

                                    -

                                    <?= e($shift['end_time']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Covering Employee

                        </label>

                        <select
                            name="covering_employee_id"
                            class="form-select"
                            required>

                            <option value="">
                                Select Employee
                            </option>

                            <?php foreach($employees as $employee): ?>

                                <option
                                    value="<?= (int)$employee['id'] ?>">

                                    <?= e($employee['full_name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <button
                    type="submit"
                    name="submit_request"
                    class="btn btn-success">

                    Submit Request

                </button>

            </form>

        </div>

    </div>

    <!-- Existing Requests -->

    <div class="card shadow-sm">

        <div class="card-header bg-secondary text-white">

            My Coverage Requests

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Date</th>
                        <th>Shift</th>
                        <th>Covering Employee</th>
                        <th>Status</th>
                        <th>Action</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach($requests as $request): ?>

                        <?php

                        $badge = match(
                            $request['status']
                        ) {

                            'accepted'
                                => 'bg-success',

                            'declined'
                                => 'bg-danger',

                            'completed'
                                => 'bg-primary',

                            default
                                => 'bg-warning text-dark'
                        };

                        ?>

                        <tr>

                            <td>

                                <?= e(
                                    $request['shift_date']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $request['start_time']
                                ) ?>

                                -

                                <?= e(
                                    $request['end_time']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $request['covering_name']
                                ) ?>

                            </td>

                            <td>

                                <span
                                    class="badge <?= $badge ?>">

                                    <?= ucfirst(
                                        e(
                                            $request['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?php if(
                                    $request['status']
                                    === 'pending'
                                ): ?>

                                    <form method="POST">

                                        <?= csrf_input() ?>

                                        <input
                                            type="hidden"
                                            name="request_id"
                                            value="<?= (int)$request['id'] ?>">

                                        <button
                                            type="submit"
                                            name="cancel_request"
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