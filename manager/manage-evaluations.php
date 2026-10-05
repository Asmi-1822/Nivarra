<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Employee Evaluations';

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($search !== '') {

    $where[] =
        '(e.full_name LIKE ? OR e.employee_code LIKE ?)';

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
| Statistics
|--------------------------------------------------------------------------
*/

$stats = $pdo->query("
    SELECT
        COUNT(*) total_evaluations,

        ROUND(
            AVG(
                (
                    punctuality_score +
                    teamwork_score +
                    customer_service_score +
                    productivity_score
                ) / 4
            ),
            2
        ) average_score

    FROM evaluations
")->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Evaluation List
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        ev.*,

        e.full_name,
        e.employee_code,

        m.full_name
            AS evaluator_name,

        ROUND(
            (
                punctuality_score +
                teamwork_score +
                customer_service_score +
                productivity_score
            ) / 4,
            2
        ) AS overall_score

    FROM evaluations ev

    INNER JOIN employees e
        ON e.id = ev.employee_id

    INNER JOIN employees m
        ON m.id = ev.evaluator_id

    $whereSql

    ORDER BY
        ev.evaluation_date DESC
");

$stmt->execute($params);

$evaluations =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Top Employees
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT

        e.full_name,

        ROUND(
            AVG(
                (
                    punctuality_score +
                    teamwork_score +
                    customer_service_score +
                    productivity_score
                ) / 4
            ),
            2
        ) avg_score

    FROM evaluations ev

    INNER JOIN employees e
        ON e.id = ev.employee_id

    GROUP BY ev.employee_id

    ORDER BY avg_score DESC

    LIMIT 5
");

$topEmployees =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2>
                Employee Evaluations
            </h2>

            <p class="text-muted mb-0">
                Performance management dashboard
            </p>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="evaluation-form.php"
                class="btn btn-maroon">

                New Evaluation

            </a>

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>

    <!-- Statistics -->

    <div class="row g-3 mb-4">

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>

                        <?= (int)$stats['total_evaluations'] ?>

                    </h3>

                    <p class="mb-0">

                        Total Evaluations

                    </p>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <h3>

                        <?= e($stats['average_score'] ?? '0.00') ?>

                    </h3>

                    <p class="mb-0">

                        Average Score

                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- Search -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <div class="col-md-10">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Search employee">

                    </div>

                    <div class="col-md-2">

                        <button
                            class="btn btn-maroon w-100">

                            Search

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- Evaluation Table -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Evaluation History

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>

                    <tr>

                        <th>Employee</th>
                        <th>Date</th>
                        <th>Evaluator</th>
                        <th>Score</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach($evaluations as $evaluation): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $evaluation['full_name']
                                ) ?>

                                <br>

                                <small>

                                    <?= e(
                                        $evaluation['employee_code']
                                    ) ?>

                                </small>

                            </td>

                            <td>

                                <?= e(
                                    $evaluation['evaluation_date']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $evaluation['evaluator_name']
                                ) ?>

                            </td>

                            <td>

                                <span class="badge bg-primary">

                                    <?= e(
                                        $evaluation['overall_score']
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <a
                                    href="evaluation-form.php?id=<?= (int)$evaluation['id'] ?>"
                                    class="btn btn-warning btn-sm">

                                    Edit

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Top Employees -->

    <div class="card shadow-sm">

        <div class="card-header">

            Top Rated Employees

        </div>

        <div class="card-body">

            <table class="table">

                <thead>

                <tr>

                    <th>Employee</th>
                    <th>Average Score</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach($topEmployees as $employee): ?>

                    <tr>

                        <td>

                            <?= e(
                                $employee['full_name']
                            ) ?>

                        </td>

                        <td>

                            <?= e($employee['avg_score'] ?? '0.00') ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>