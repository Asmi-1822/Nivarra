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
| Accept Request
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['accept_request'])
) {
    verify_csrf();

    $requestId =
        (int)($_POST['request_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT *
        FROM shift_coverage_requests
        WHERE id = ?
        AND covering_employee_id = ?
        AND status = 'pending'
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $employeeId
    ]);

    $request = $stmt->fetch();

    if ($request) {

        try {

            $pdo->beginTransaction();

            /*
            | Transfer shift ownership
            */

            $stmt = $pdo->prepare("
                UPDATE shifts
                SET employee_id = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $employeeId,
                $request['shift_id']
            ]);

            /*
            | Mark accepted
            */

            $stmt = $pdo->prepare("
                UPDATE shift_coverage_requests
                SET status = 'accepted'
                WHERE id = ?
            ");

            $stmt->execute([
                $requestId
            ]);

            $pdo->commit();

            flash(
                'success',
                'Coverage request accepted.'
            );

        } catch(Throwable $e) {

            $pdo->rollBack();

            flash(
                'error',
                'Unable to process request.'
            );
        }
    }

    redirect(
        'coverage-requests.php'
    );
}

/*
|--------------------------------------------------------------------------
| Decline Request
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['decline_request'])
) {
    verify_csrf();

    $requestId =
        (int)($_POST['request_id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE shift_coverage_requests
        SET status='declined'
        WHERE id=?
        AND covering_employee_id=?
        AND status='pending'
    ");

    $stmt->execute([
        $requestId,
        $employeeId
    ]);

    flash(
        'success',
        'Coverage request declined.'
    );

    redirect(
        'coverage-requests.php'
    );
}

/*
|--------------------------------------------------------------------------
| Incoming Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        scr.*,

        s.shift_date,
        s.start_time,
        s.end_time,
        s.position,

        e.full_name AS requester_name

    FROM shift_coverage_requests scr

    INNER JOIN shifts s
        ON s.id = scr.shift_id

    INNER JOIN employees e
        ON e.id = scr.requesting_employee_id

    WHERE scr.covering_employee_id = ?

    ORDER BY scr.created_at DESC
");

$stmt->execute([
    $employeeId
]);

$requests =
    $stmt->fetchAll();

$pageTitle =
    'Coverage Requests';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-4">
            Coverage Requests
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

    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">

            Incoming Requests

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Requester</th>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Action</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if(empty($requests)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center">

                                No requests found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    <?php foreach(
                        $requests
                        as $request
                    ): ?>

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
                                    $request['requester_name']
                                ) ?>

                            </td>

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
                                    $request['position']
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

                                    <div
                                        class="d-flex gap-2">

                                        <form
                                            method="POST">

                                            <?= csrf_input() ?>

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int)$request['id'] ?>">

                                            <button
                                                type="submit"
                                                name="accept_request"
                                                class="btn btn-sm btn-success">

                                                Accept

                                            </button>

                                        </form>

                                        <form
                                            method="POST">

                                            <?= csrf_input() ?>

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int)$request['id'] ?>">

                                            <button
                                                type="submit"
                                                name="decline_request"
                                                class="btn btn-sm btn-danger">

                                                Decline

                                            </button>

                                        </form>

                                    </div>

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