<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Review Moderation';

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $type = $_POST['type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $allowedActions = [
        'approve',
        'reject',
        'delete'
    ];

    if (
        $id > 0 &&
        in_array($action, $allowedActions, true)
    ) {

        $table =
            $type === 'employee'
                ? 'employee_ratings'
                : 'reviews';

        if ($action === 'delete') {

            $stmt = $pdo->prepare("
                DELETE FROM {$table}
                WHERE id = ?
            ");

            $stmt->execute([$id]);

        } else {

            $status =
                $action === 'approve'
                    ? 'approved'
                    : 'rejected';

            $stmt = $pdo->prepare("
                UPDATE {$table}
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $status,
                $id
            ]);
        }

        if (function_exists('auditLog')) {

            auditLog(
                $_SESSION['employee_id'],
                'review_moderation',
                "{$table} #{$id} {$action}"
            );
        }

        flash(
            'success',
            'Review updated successfully.'
        );

        redirect('manage-reviews.php');
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$status =
    trim($_GET['status'] ?? '');

$search =
    trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($status !== '') {

    $where[] = 'status = ?';
    $params[] = $status;
}

if ($search !== '') {

    $where[] = '
        (
            customer_name LIKE ?
            OR review_text LIKE ?
        )
    ';

    $term = '%' . $search . '%';

    $params[] = $term;
    $params[] = $term;
}

$whereSql = '';

if ($where) {

    $whereSql =
        'WHERE ' .
        implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| Restaurant Reviews
|--------------------------------------------------------------------------
*/

$reviewStmt = $pdo->prepare("
    SELECT *
    FROM reviews
    $whereSql
    ORDER BY created_at DESC
");

$reviewStmt->execute($params);

$reviews =
    $reviewStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| Employee Ratings
|--------------------------------------------------------------------------
*/

$employeeStmt = $pdo->prepare("
    SELECT
        er.*,
        e.full_name
    FROM employee_ratings er
    LEFT JOIN employees e
        ON e.id = er.employee_id
    $whereSql
    ORDER BY er.created_at DESC
");

$employeeStmt->execute($params);

$employeeRatings =
    $employeeStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="mb-4">

                Review Moderation

            </h2>

            <p class="text-muted mb-0">
                Manage review moderation
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

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-md-5">

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            class="form-control"
                            placeholder="Search reviews">

                    </div>

                    <div class="col-md-4">

                        <select
                            name="status"
                            class="form-select">

                            <option value="">
                                All Statuses
                            </option>

                            <option value="pending">
                                Pending
                            </option>

                            <option value="approved">
                                Approved
                            </option>

                            <option value="rejected">
                                Rejected
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3">

                        <button
                            class="btn btn-maroon w-100">

                            Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- Restaurant Reviews -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Restaurant Reviews

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Customer</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($reviews as $review): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $review['customer_name']
                                ) ?>

                            </td>

                            <td>

                                <?= (int)$review['rating'] ?>

                                ★

                            </td>

                            <td>

                                <?= e(
                                    mb_strimwidth(
                                        $review['review_text'],
                                        0,
                                        80,
                                        '...'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    ucfirst(
                                        $review['status']
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?php include 'partials/review-actions.php'; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Employee Ratings -->

    <div class="card shadow-sm">

        <div class="card-header">

            Employee Ratings

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Employee</th>
                        <th>Customer</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $employeeRatings
                        as $rating
                    ): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $rating['full_name']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $rating['customer_name']
                                ) ?>

                            </td>

                            <td>

                                <?= (int)$rating['rating'] ?>

                                ★

                            </td>

                            <td>

                                <?= e(
                                    mb_strimwidth(
                                        $rating['comment'],
                                        0,
                                        80,
                                        '...'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    ucfirst(
                                        $rating['status']
                                    )
                                ) ?>

                            </td>

                            <td>

                                <form method="POST">

                                    <?= csrf_input() ?>

                                    <input
                                        type="hidden"
                                        name="type"
                                        value="employee">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$rating['id'] ?>">

                                    <button
                                        name="action"
                                        value="approve"
                                        class="btn btn-success btn-sm">

                                        Approve

                                    </button>

                                    <button
                                        name="action"
                                        value="reject"
                                        class="btn btn-warning btn-sm">

                                        Reject

                                    </button>

                                    <button
                                        name="action"
                                        value="delete"
                                        class="btn btn-danger btn-sm">

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

</div>

<?php include '../includes/footer.php'; ?> 