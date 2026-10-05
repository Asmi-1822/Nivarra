<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Review Analytics';

/*
|--------------------------------------------------------------------------
| Restaurant Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        AVG(rating) AS avg_rating,
        COUNT(*) AS total_reviews
    FROM reviews
    WHERE status = 'approved'
");

$restaurantStats = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Rating Distribution
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        rating,
        COUNT(*) AS total
    FROM reviews
    WHERE status = 'approved'
    GROUP BY rating
    ORDER BY rating
");

$distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ratingLabels = [];
$ratingData = [];

foreach ($distribution as $row) {

    $ratingLabels[] = $row['rating'] . '★';
    $ratingData[] = (int)$row['total'];
}

/*
|--------------------------------------------------------------------------
| Employee Leaderboard
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.full_name,
        ROUND(AVG(er.rating), 2) AS avg_rating,
        COUNT(*) AS total_ratings
    FROM employee_ratings er
    JOIN employees e
        ON e.id = er.employee_id
    WHERE er.status = 'approved'
    GROUP BY e.id
    ORDER BY avg_rating DESC
    LIMIT 10
");

$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Monthly Trends
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS review_month,
        COUNT(*) AS total_reviews
    FROM reviews
    WHERE status = 'approved'
    GROUP BY review_month
    ORDER BY review_month ASC
");

$monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthLabels = [];
$monthData = [];

foreach ($monthly as $row) {

    $monthLabels[] = $row['review_month'];
    $monthData[] = (int)$row['total_reviews'];
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="nivarra-title mb-0">

            Review Analytics

        </h2>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>

    <!-- Statistics -->

    <div class="row g-3 mb-4">

        <div class="col-md-6">

            <div class="card stat-card text-center h-100">

                <div class="card-body">

                    <div class="stat-number rating-number">

                        <?= number_format(
                            (float)(
                                $restaurantStats['avg_rating'] ?? 0
                            ),
                            2
                        ) ?>

                        <span class="star-rating">
                            ★
                        </span>

                    </div>

                    <div class="text-muted">

                        Average Rating

                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card stat-card text-center h-100">

                <div class="card-body">

                    <div class="stat-number">

                        <?= (int)(
                            $restaurantStats['total_reviews'] ?? 0
                        ) ?>

                    </div>

                    <div class="text-muted">

                        Approved Reviews

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Analytics Charts -->

    <div class="row g-4 mb-4">

        <!-- Rating Distribution -->

        <div class="col-lg-6">

            <div class="card nivarra-card h-100">

                <div class="card-header nivarra-card-header">

                    Rating Distribution

                </div>

                <div class="card-body">

                    <?php if (empty($ratingData)): ?>

                        <div class="text-center text-muted py-5">

                            No approved review ratings available.

                        </div>

                    <?php else: ?>

                        <div class="chart-container">

                            <canvas id="ratingChart"></canvas>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- Monthly Review Trend -->

        <div class="col-lg-6">

            <div class="card nivarra-card h-100">

                <div class="card-header nivarra-card-header">

                    Monthly Review Trend

                </div>

                <div class="card-body">

                    <?php if (empty($monthData)): ?>

                        <div class="text-center text-muted py-5">

                            No monthly review data available.

                        </div>

                    <?php else: ?>

                        <div class="chart-container">

                            <canvas id="monthlyChart"></canvas>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

    <!-- Employee Leaderboard -->

    <div class="card nivarra-card">

        <div class="card-header nivarra-card-header">

            Top Rated Employees

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                    <tr>

                        <th>Employee</th>

                        <th>Average Rating</th>

                        <th>Total Ratings</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($leaderboard)): ?>

                        <tr>

                            <td
                                colspan="3"
                                class="text-center text-muted py-4">

                                No employee ratings available.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach (
                            $leaderboard as $employee
                        ): ?>

                            <tr>

                                <td class="fw-semibold">

                                    <?= e(
                                        $employee['full_name']
                                    ) ?>

                                </td>

                                <td>

                                    <span class="star-rating">

                                        <?= e(
                                            $employee['avg_rating']
                                        ) ?>

                                        ★

                                    </span>

                                </td>

                                <td>

                                    <?= (int)(
                                        $employee['total_ratings']
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- Chart.js -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ratingCanvas =
    document.getElementById('ratingChart');

if (ratingCanvas) {

    new Chart(
        ratingCanvas,
        {
            type: 'bar',

            data: {
                labels:
                    <?= json_encode(
                        $ratingLabels,
                        JSON_UNESCAPED_UNICODE
                    ) ?>,

                datasets: [
                    {
                        label: 'Reviews',

                        data:
                            <?= json_encode(
                                $ratingData
                            ) ?>,

                        backgroundColor: '#8B1E1E',

                        borderColor: '#8B1E1E',

                        borderWidth: 1
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                scales: {
                    y: {
                        beginAtZero: true,

                        ticks: {
                            precision: 0
                        }
                    }
                },

                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        }
    );

}

const monthlyCanvas =
    document.getElementById('monthlyChart');

if (monthlyCanvas) {

    new Chart(
        monthlyCanvas,
        {
            type: 'line',

            data: {
                labels:
                    <?= json_encode(
                        $monthLabels
                    ) ?>,

                datasets: [
                    {
                        label: 'Reviews',

                        data:
                            <?= json_encode(
                                $monthData
                            ) ?>,

                        borderColor: '#D4A373',

                        backgroundColor:
                            'rgba(212, 163, 115, 0.15)',

                        borderWidth: 3,

                        fill: true,

                        tension: 0.3,

                        pointBackgroundColor: '#8B1E1E',

                        pointBorderColor: '#8B1E1E'
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                scales: {
                    y: {
                        beginAtZero: true,

                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        }
    );

}

</script>

<?php include '../includes/footer.php'; ?>