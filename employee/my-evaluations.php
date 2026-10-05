<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';

$employeeId = (int)$_SESSION['employee_id'];

/*
|--------------------------------------------------------------------------
| Evaluation Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_evaluations,

        AVG(
            (
                punctuality_score
                + teamwork_score
                + customer_service_score
                + productivity_score
            ) / 4
        ) AS average_score,

        MAX(
            (
                punctuality_score
                + teamwork_score
                + customer_service_score
                + productivity_score
            ) / 4
        ) AS highest_score,

        MIN(
            (
                punctuality_score
                + teamwork_score
                + customer_service_score
                + productivity_score
            ) / 4
        ) AS lowest_score

    FROM evaluations
    WHERE employee_id = ?
");

$stmt->execute([$employeeId]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Evaluation History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ev.*,
        m.full_name AS evaluator_name
    FROM evaluations ev
    LEFT JOIN employees m
        ON m.id = ev.evaluator_id
    WHERE ev.employee_id = ?
    ORDER BY ev.created_at DESC
");

$stmt->execute([$employeeId]);

$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Evaluations';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="mb-4">
                
                My Evaluations
            </h2>

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

    <!-- Statistics -->

    <div class="row mb-4">

        <div class="col-md-3">

            <div class="card shadow-sm">
                <div class="card-body text-center">

                    <h3>
                        <?= (int)($stats['total_evaluations'] ?? 0) ?>
                    </h3>

                    <small>Total Evaluations</small>

                </div>
            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">
                <div class="card-body text-center">

                    <h3>
                        <?= number_format(
                            (float)($stats['average_score'] ?? 0),
                            1
                        ) ?>
                    </h3>

                    <small>Average Score</small>

                </div>
            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">
                <div class="card-body text-center">

                    <h3>
                        <?= number_format(
                            (float)($stats['highest_score'] ?? 0),
                            1
                        ) ?>
                    </h3>

                    <small>Highest Score</small>

                </div>
            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">
                <div class="card-body text-center">

                    <h3>
                        <?= number_format(
                            (float)($stats['lowest_score'] ?? 0),
                            1
                        ) ?>
                    </h3>

                    <small>Lowest Score</small>

                </div>
            </div>

        </div>

    </div>

    <!-- Evaluation List -->

    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">

            Evaluation History

        </div>

        <div class="card-body">

            <?php if(empty($evaluations)): ?>

                <div class="alert alert-info mb-0">

                    No evaluations available.

                </div>

            <?php else: ?>

                <?php foreach($evaluations as $evaluation): ?>

                    <?php

                    $score =
                        (
                            (float)($evaluation['punctuality_score'] ?? 0)
                            +
                            (float)($evaluation['teamwork_score'] ?? 0)
                            +
                            (float)($evaluation['customer_service_score'] ?? 0)
                            +
                            (float)($evaluation['productivity_score'] ?? 0)
                        ) / 4;

                    $badge =
                        $score >= 8
                        ? 'success'
                        : ($score >= 5
                            ? 'warning'
                            : 'danger');

                    ?>

                    <div class="card mb-3">

                        <div class="card-body">

                            <div class="d-flex justify-content-between">

                                <div>

                                    <strong>

                                        Evaluated By:

                                    </strong>

                                    <?= e(
                                        $evaluation['evaluator_name']
                                        ?? 'Manager'
                                    ) ?>

                                </div>

                                <div>

                                    <span
                                        class="badge bg-<?= $badge ?>">

                                        Score:
                                        <?= number_format(
                                            $score,
                                            1
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                            <hr>

                            <p>

                                <strong>
                                    Strengths
                                </strong>

                                <br>

                                <?= nl2br(
                                    e(
                                        $evaluation['strengths']
                                        ?? ''
                                    )
                                ) ?>

                            </p>

                            <p>

                                <strong>
                                    Areas for Improvement
                                </strong>

                                <br>

                                <?= nl2br(
                                    e(
                                        $evaluation['improvements']
                                        ?? ''
                                    )
                                ) ?>

                            </p>

                            <p>

                                <strong>
                                    Comments
                                </strong>

                                <br>

                                <?= nl2br(
                                    e(
                                        $evaluation['comments']
                                        ?? ''
                                    )
                                ) ?>

                            </p>

                            <small
                                class="text-muted">

                                <?= e(
                                    $evaluation['created_at']
                                ) ?>

                            </small>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>