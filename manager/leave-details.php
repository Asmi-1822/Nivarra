<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$leaveId = (int)($_GET['id'] ?? 0);

if ($leaveId <= 0) {
    header('Location: leave-management.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Leave Status Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = 'Invalid security token. Please try again.';
    header('Location: leave-details.php?id=' . $leaveId);
    exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    if (!in_array($action, ['approve', 'reject'], true)) {
        $_SESSION['error'] = 'Invalid leave action.';
        header('Location: leave-details.php?id=' . $leaveId);
        exit;
    }

    $newStatus = $action === 'approve'
        ? 'approved'
        : 'rejected';

    try {

        /*
        |--------------------------------------------------------------------------
        | Get Current Leave Request
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                employee_id,
                status
            FROM leave_requests
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$leaveId]);

        $currentLeave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentLeave) {
            $_SESSION['error'] = 'Leave request not found.';
            header('Location: leave-management.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Only Pending Requests Can Be Approved/Rejected
        |--------------------------------------------------------------------------
        */

        if (($currentLeave['status'] ?? '') !== 'pending') {
            $_SESSION['error'] = 'Only pending leave requests can be approved or rejected.';
            header('Location: leave-details.php?id=' . $leaveId);
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Update Leave Request
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE leave_requests
            SET
                status = ?,
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $newStatus,
            (int)($_SESSION['employee_id'] ?? 0),
            $leaveId
        ]);

        $_SESSION['success'] = $newStatus === 'approved'
            ? 'Leave request approved successfully.'
            : 'Leave request rejected successfully.';

    } catch (PDOException $e) {

        $_SESSION['error'] = 'Unable to update the leave request.';
    }

    header('Location: leave-details.php?id=' . $leaveId);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Leave Request Details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        lr.*,
        e.full_name,
        e.employee_code,
        e.email,
        e.phone,
        e.role,
        e.position,
        ep.leave_allowance,
        ce.full_name AS covering_employee_name,
        ce.email AS covering_employee_email
    FROM leave_requests lr
    JOIN employees e
        ON e.id = lr.employee_id
    LEFT JOIN employee_profiles ep
        ON ep.employee_id = e.id
    LEFT JOIN employees ce
        ON ce.id = lr.covering_employee_id
    WHERE lr.id = ?
    LIMIT 1
");

$stmt->execute([$leaveId]);

$leave = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$leave) {
    $_SESSION['error'] = 'Leave request not found.';
    header('Location: leave-management.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Calculate Leave Balance
|--------------------------------------------------------------------------
|
| Remaining Balance =
| Leave Allowance - Approved Leave Days
|
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'approved'
                    THEN DATEDIFF(end_date, start_date) + 1
                    ELSE 0
                END
            ),
            0
        ) AS approved_leave_days
    FROM leave_requests
    WHERE employee_id = ?
");

$stmt->execute([
    (int)$leave['employee_id']
]);

$approvedLeaveDays = (int)$stmt->fetchColumn();

$leaveAllowance = (int)($leave['leave_allowance'] ?? 0);

$leaveBalance = max(
    0,
    $leaveAllowance - $approvedLeaveDays
);

/*
|--------------------------------------------------------------------------
| Calculate Requested Leave Days
|--------------------------------------------------------------------------
*/

$startDate = $leave['start_date'] ?? null;
$endDate = $leave['end_date'] ?? null;

$requestedLeaveDays = 0;

if ($startDate && $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);

    if ($end >= $start) {
        $requestedLeaveDays =
            $start->diff($end)->days + 1;
    }
}

/*
|--------------------------------------------------------------------------
| Get CSRF Token
|--------------------------------------------------------------------------
*/

$csrfToken = generateCsrfToken();

$pageTitle = 'Leave Request Details';

require_once '../includes/header.php';
require_once '../includes/manager-navbar.php';

?>

<div class="container">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">

                Leave Request Details

            </h2>
            <p class="text-muted mb-0">

                Review employee leave request

            </p>

        </div>

        <div class="d-flex align-items-center justify-content-center">
            <a
                href="manage-leaves.php"
                class="btn btn-secondary"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>


    <!-- Success Message -->
    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success alert-dismissible fade show" role="alert">

            <?= e((string)$_SESSION['success']) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>


    <!-- Error Message -->
    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <?= e((string)$_SESSION['error']) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>


    <!-- Employee Information -->
    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <h5 class="mb-0">Employee Information</h5>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-6">
                    <strong>Employee Name</strong>
                    <div>
                        <?= e((string)($leave['full_name'] ?? '')) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Employee Code</strong>
                    <div>
                        <?= e((string)($leave['employee_code'] ?? '')) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Email</strong>
                    <div>
                        <?= e((string)($leave['email'] ?? '')) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Phone</strong>
                    <div>
                        <?= e((string)($leave['phone'] ?? '')) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Position</strong>
                    <div>
                        <?= e((string)($leave['position'] ?? '')) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <strong>Role</strong>
                    <div>
                        <?= e(ucfirst((string)($leave['role'] ?? ''))) ?>
                    </div>
                </div>

            </div>

        </div>

    </div>


    <!-- Leave Balance -->
    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <h5 class="mb-0">Leave Balance</h5>
        </div>

        <div class="card-body">

            <div class="row text-center">

                <div class="col-md-4">

                    <h4>
                        <?= e((string)$leaveAllowance) ?>
                    </h4>

                    <div class="text-muted">
                        Annual Allowance
                    </div>

                </div>

                <div class="col-md-4">

                    <h4>
                        <?= e((string)$approvedLeaveDays) ?>
                    </h4>

                    <div class="text-muted">
                        Approved Leave Days
                    </div>

                </div>

                <div class="col-md-4">

                    <h4>
                        <?= e((string)$leaveBalance) ?>
                    </h4>

                    <div class="text-muted">
                        Remaining Balance
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Leave Request -->
    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <h5 class="mb-0">Leave Request</h5>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-6">

                    <strong>Leave Type</strong>

                    <div>
                        <?= e((string)($leave['leave_type'] ?? '')) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Status</strong>

                    <div class="mt-1">

                        <?php
                        $status = strtolower(
                            trim((string)($leave['status'] ?? ''))
                        );

                        $statusClass = match ($status) {
                            'approved' => 'bg-success',
                            'rejected' => 'bg-danger',
                            'cancelled' => 'bg-secondary',
                            default => 'bg-warning text-dark'
                        };
                        ?>

                        <span class="badge <?= $statusClass ?>">
                            <?= e(ucfirst($status)) ?>
                        </span>

                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Start Date</strong>

                    <div>
                        <?= e((string)($leave['start_date'] ?? '')) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <strong>End Date</strong>

                    <div>
                        <?= e((string)($leave['end_date'] ?? '')) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Requested Days</strong>

                    <div>
                        <?= e((string)$requestedLeaveDays) ?>
                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Submitted On</strong>

                    <div>
                        <?= e((string)($leave['created_at'] ?? '')) ?>
                    </div>

                </div>


                <div class="col-12">

                    <strong>Reason</strong>

                    <div class="mt-2 p-3 bg-light rounded">

                        <?= nl2br(
                            e((string)($leave['reason'] ?? ''))
                        ) ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Covering Employee -->
    <?php if (!empty($leave['covering_employee_name'])): ?>

        <div class="card shadow-sm mb-4">

            <div class="card-header">
                <h5 class="mb-0">Covering Employee</h5>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-6">

                        <strong>Name</strong>

                        <div>
                            <?= e((string)$leave['covering_employee_name']) ?>
                        </div>

                    </div>

                    <div class="col-md-6">

                        <strong>Email</strong>

                        <div>
                            <?= e((string)($leave['covering_employee_email'] ?? '')) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- Actions -->
    <?php if (($leave['status'] ?? '') === 'pending'): ?>

        <div class="card shadow-sm mb-4">

            <div class="card-header">
                <h5 class="mb-0">Manager Actions</h5>
            </div>

            <div class="card-body">

                <div class="d-flex gap-2">

                    <!-- Approve -->
                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="approve"
                        >

                        <button
                            type="submit"
                            class="btn btn-success"
                            onclick="return confirm('Are you sure you want to approve this leave request?');"
                        >
                            Approve
                        </button>

                    </form>


                    <!-- Reject -->
                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="reject"
                        >

                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to reject this leave request?');"
                        >
                            Reject
                        </button>

                    </form>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>