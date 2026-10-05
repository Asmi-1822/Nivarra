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
| Rating Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_ratings,
        AVG(rating) AS average_rating,
        MAX(rating) AS highest_rating,
        MIN(rating) AS lowest_rating
    FROM employee_ratings
    WHERE employee_id = ?
");

$stmt->execute([$employeeId]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Rating History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        er.*
    FROM employee_ratings er
    LEFT JOIN customers c
        ON c.id = er.customer_id
    WHERE er.employee_id = ?
    ORDER BY er.created_at DESC
");

$stmt->execute([$employeeId]);

$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Ratings';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    
    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-4">
            My Ratings
        </h2>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>

    <div class="row mb-4">

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h3><?= (int)($stats['total_ratings'] ?? 0) ?></h3>
                    <small>Total Ratings</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h3>
                        <?= number_format(
                            (float)($stats['average_rating'] ?? 0),
                            1
                        ) ?>
                    </h3>
                    <small>Average Rating</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h3><?= (float)($stats['highest_rating'] ?? 0) ?></h3>
                    <small>Highest Rating</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h3><?= (float)($stats['lowest_rating'] ?? 0) ?></h3>
                    <small>Lowest Rating</small>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">
            Rating History
        </div>

        <div class="card-body">

            <?php if(empty($ratings)): ?>

                <div class="alert alert-info mb-0">
                    No ratings available.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-striped">

                        <thead>

                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Review</th>
                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach($ratings as $rating): ?>

                            <tr>

                                <td>
                                    <?= e($rating['created_at']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $rating['customer_name']
                                        ?? 'Anonymous'
                                    ) ?>
                                </td>

                                <td>

                                    <span class="badge bg-success">

                                        <?= e(
                                            (string)$rating['rating']
                                        ) ?>

                                        / 5

                                    </span>

                                </td>

                                <td>

                                    <?= nl2br(
                                        e(
                                            $rating['review']
                                            ?? ''
                                        )
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>