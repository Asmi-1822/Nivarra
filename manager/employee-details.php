<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Employee Details';

$employeeId = (int)($_GET['id'] ?? 0);

if ($employeeId <= 0) {
    redirect('employees.php');
}

/*
|--------------------------------------------------------------------------
| Employee Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.*,
        ep.*
    FROM employees e
    LEFT JOIN employee_profiles ep
        ON ep.employee_id = e.id
    WHERE e.id = ?
    LIMIT 1
");

$stmt->execute([$employeeId]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {

    flash(
        'error',
        'Employee not found.'
    );

    redirect('employees.php');
}

/*
|--------------------------------------------------------------------------
| Customer Ratings
|--------------------------------------------------------------------------
*/

$ratingStmt = $pdo->prepare("
    SELECT
        AVG(rating) AS average_rating,
        COUNT(*) AS total_ratings
    FROM employee_ratings
    WHERE employee_id = ?
");

$ratingStmt->execute([$employeeId]);

$ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);

$averageRating =
    round(
        (float)($ratingData['average_rating'] ?? 0),
        1
    );

$totalRatings =
    (int)($ratingData['total_ratings'] ?? 0);

$ratingBreakdownStmt = $pdo->prepare("
    SELECT
        rating,
        COUNT(*) AS total
    FROM employee_ratings
    WHERE employee_id = ?
    GROUP BY rating
    ORDER BY rating DESC
");

$ratingBreakdownStmt->execute([
    $employeeId
]);

$ratingBreakdown =
    $ratingBreakdownStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| Evaluations
|--------------------------------------------------------------------------
*/

$evaluationStmt = $pdo->prepare("
    SELECT *
    FROM evaluations
    WHERE employee_id = ?
    ORDER BY evaluation_date DESC
");

$evaluationStmt->execute([
    $employeeId
]);

$evaluations =
    $evaluationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| Leave Summary
|--------------------------------------------------------------------------
*/

$leaveStmt = $pdo->prepare("
    SELECT *
    FROM leave_requests
    WHERE employee_id = ?
    ORDER BY start_date DESC
    LIMIT 10
");

$leaveStmt->execute([
    $employeeId
]);

$leaveRequests =
    $leaveStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/*
|--------------------------------------------------------------------------
| Upcoming Shifts
|--------------------------------------------------------------------------
*/

$shiftStmt = $pdo->prepare("
    SELECT *
    FROM shifts
    WHERE employee_id = ?
    AND shift_date >= CURDATE()
    ORDER BY shift_date ASC
    LIMIT 10
");

$shiftStmt->execute([
    $employeeId
]);

$shifts =
    $shiftStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <h2>

            Employee Details

        </h2>

        <div>

            <a
                href="edit-employee.php?id=<?= $employeeId ?>"
                class="btn btn-warning">

                Edit Employee

            </a>

            <a
                href="employees.php"
                class="btn btn-secondary">

                Back

            </a>

        </div>

    </div>

    <?php if ($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <!-- Employee Information -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Employee Information

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <p>

                        <strong>Employee Code:</strong>

                        <?= e($employee['employee_code']) ?>

                    </p>

                    <p>

                        <strong>Name:</strong>

                        <?= e($employee['full_name']) ?>

                    </p>

                    <p>

                        <strong>Username:</strong>

                        <?= e($employee['username']) ?>

                    </p>

                    <p>

                        <strong>Email:</strong>

                        <?= e($employee['email']) ?>

                    </p>

                </div>

                <div class="col-md-6">

                    <p>

                        <strong>Phone:</strong>

                        <?= e($employee['phone']) ?>

                    </p>

                    <p>

                        <strong>Role:</strong>

                        <?= e(ucfirst($employee['role'])) ?>

                    </p>

                    <p>

                        <strong>Hire Date:</strong>

                        <?= e($employee['hire_date']) ?>

                    </p>

                    <p>

                        <strong>Status:</strong>

                        <?php if ((int)$employee['active'] === 1): ?>

                            <span class="badge bg-success">

                                Active

                            </span>

                        <?php else: ?>

                            <span class="badge bg-danger">

                                Disabled

                            </span>

                        <?php endif; ?>

                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- Ratings -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Customer Ratings

        </div>

        <div class="card-body">

            <h4>

                <?= $averageRating ?>

                / 5

            </h4>

            <p>

                Total Ratings:

                <?= $totalRatings ?>

            </p>

            <table class="table">

                <thead>

                <tr>

                    <th>Rating</th>
                    <th>Count</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($ratingBreakdown as $row): ?>

                    <tr>

                        <td>

                            <?= e($row['rating']) ?>

                            ★

                        </td>

                        <td>

                            <?= e($row['total']) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Evaluations -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Evaluation History

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                    <tr>

                        <th>Date</th>
                        <th>Score</th>
                        <th>Strengths</th>
                        <th>Improvements</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($evaluations as $evaluation): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $evaluation['evaluation_date']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $evaluation['score']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $evaluation['strengths']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $evaluation['improvements']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Leave Requests -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Leave Requests

        </div>

        <div class="card-body">

            <table class="table table-striped">

                <thead>

                <tr>

                    <th>Type</th>
                    <th>Dates</th>
                    <th>Status</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($leaveRequests as $leave): ?>

                    <tr>

                        <td>

                            <?= e(
                                $leave['leave_type']
                            ) ?>

                        </td>

                        <td>

                            <?= e(
                                $leave['start_date']
                            ) ?>

                            -

                            <?= e(
                                $leave['end_date']
                            ) ?>

                        </td>

                        <td>

                            <?= e(
                                ucfirst(
                                    $leave['status']
                                )
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Shifts -->

    <div class="card shadow-sm">

        <div class="card-header">

            Upcoming Shifts

        </div>

        <div class="card-body">

            <table class="table table-striped">

                <thead>

                <tr>

                    <th>Date</th>
                    <th>Start</th>
                    <th>End</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($shifts as $shift): ?>

                    <tr>

                        <td>

                            <?= e(
                                $shift['shift_date']
                            ) ?>

                        </td>

                        <td>

                            <?= e(
                                $shift['start_time']
                            ) ?>

                        </td>

                        <td>

                            <?= e(
                                $shift['end_time']
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>