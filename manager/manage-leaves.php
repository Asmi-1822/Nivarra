<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Leave Management';

/*
|--------------------------------------------------------------------------
| Approve / Reject Leave
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $leaveId = (int)($_POST['leave_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (
        $leaveId > 0 &&
        in_array(
            $action,
            ['approve', 'reject'],
            true
        )
    ) {

        $status =
            $action === 'approve'
                ? 'approved'
                : 'rejected';

        $stmt = $pdo->prepare("
            UPDATE leave_requests
            SET
                status = ?,
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $_SESSION['employee_id'],
            $leaveId
        ]);

        if (function_exists('auditLog')) {

            auditLog(
                $_SESSION['employee_id'],
                'leave_request_updated',
                sprintf(
                    'Leave #%d changed to %s',
                    $leaveId,
                    $status
                )
            );
        }

        flash(
            'success',
            'Leave request updated successfully.'
        );

        redirect('manage-leaves.php');
    }
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stats = $pdo->query("
    SELECT
        COUNT(*) total,
        SUM(status='pending') pending_count,
        SUM(status='approved') approved_count,
        SUM(status='rejected') rejected_count
    FROM leave_requests
")->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            e.full_name LIKE ?
            OR e.employee_code LIKE ?
            OR ce.full_name LIKE ?
        )
    ";

    $term = '%' . $search . '%';

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($status !== '') {

    $where[] = "lr.status = ?";
    $params[] = $status;
}

$whereSql = '';

if ($where) {

    $whereSql =
        'WHERE ' .
        implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| Leave Requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        lr.*,

        e.full_name,
        e.employee_code,

        ce.full_name
            AS covering_employee_name,

        approver.full_name
            AS approved_by_name

    FROM leave_requests lr

    INNER JOIN employees e
        ON e.id = lr.employee_id

    LEFT JOIN employees ce
        ON ce.id = lr.covering_employee_id

    LEFT JOIN employees approver
        ON approver.id = lr.approved_by

    $whereSql

    ORDER BY
        lr.created_at DESC
");

$stmt->execute($params);

$leaveRequests =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2>Leave Management</h2>

            <p class="text-muted mb-0">
                Review and manage employee leave requests
            </p>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>

    <?php if ($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <!-- Statistics -->

    <div class="row g-3 mb-4">

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>
                        <?= (int)$stats['total'] ?>
                    </h3>

                    <p class="mb-0">
                        Total Requests
                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3 class="text-warning">

                        <?= (int)$stats['pending_count'] ?>

                    </h3>

                    <p class="mb-0">
                        Pending
                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3 class="text-success">

                        <?= (int)$stats['approved_count'] ?>

                    </h3>

                    <p class="mb-0">
                        Approved
                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3 class="text-danger">

                        <?= (int)$stats['rejected_count'] ?>

                    </h3>

                    <p class="mb-0">
                        Rejected
                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- Filters -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-md-5">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Search employee">

                    </div>

                    <div class="col-md-5">

                        <select
                            name="status"
                            class="form-select">

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="pending"
                                <?= $status === 'pending' ? 'selected' : '' ?>>

                                Pending

                            </option>

                            <option
                                value="approved"
                                <?= $status === 'approved' ? 'selected' : '' ?>>

                                Approved

                            </option>

                            <option
                                value="rejected"
                                <?= $status === 'rejected' ? 'selected' : '' ?>>

                                Rejected

                            </option>

                        </select>

                    </div>

                    <div class="col-md-2">

                        <button
                            type="submit"
                            class="btn btn-maroon w-100">

                            Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- Leave Requests -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Covering Employee</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($leaveRequests as $leave): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= e($leave['full_name']) ?>

                                </strong>

                                <br>

                                <small>

                                    <?= e($leave['employee_code']) ?>

                                </small>

                            </td>

                            <td>

                                <?= e(
                                    ucfirst(
                                        $leave['leave_type']
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= e($leave['start_date']) ?>

                                <br>

                                <small>

                                    to

                                    <?= e($leave['end_date']) ?>

                                </small>

                            </td>

                            <td>

                                <?php if (
                                    !empty(
                                        $leave['covering_employee_name']
                                    )
                                ): ?>

                                    <?= e(
                                        $leave['covering_employee_name']
                                    ) ?>

                                <?php else: ?>

                                    <span class="text-muted">

                                        Not Assigned

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span
                                    class="badge bg-<?=
                                        match ($leave['status']) {

                                            'approved'
                                                => 'success',

                                            'rejected'
                                                => 'danger',

                                            default
                                                => 'warning'
                                        }
                                    ?>">

                                    <?= e(
                                        ucfirst(
                                            $leave['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <a
                                    href="leave-details.php?id=<?= (int)$leave['id'] ?>"
                                    class="btn btn-info btn-sm">

                                    View

                                </a>

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