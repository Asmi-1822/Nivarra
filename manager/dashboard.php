<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Manager Dashboard';

/*
|--------------------------------------------------------------------------
| Dashboard Metrics
|--------------------------------------------------------------------------
*/

$totalEmployees = (int)$pdo
    ->query("SELECT COUNT(*) FROM employees")
    ->fetchColumn();

$totalReservations = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM reservations
    ")
    ->fetchColumn();

$pendingReservations = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM reservations
        WHERE status='pending'
    ")
    ->fetchColumn();

$pendingLeaves = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM leave_requests
        WHERE status='pending'
    ")
    ->fetchColumn();

$pendingReviews = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM reviews
        WHERE status = 'approved'
    ")
    ->fetchColumn();

$totalMenuItems = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM menu_items
    ")
    ->fetchColumn();

$todayOrders = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM orders
        WHERE DATE(created_at)=CURDATE()
    ")
    ->fetchColumn();

$recentReservations = $pdo
    ->query("
        SELECT *
        FROM reservations
        ORDER BY reservation_date DESC
        LIMIT 5
    ")
    ->fetchAll(PDO::FETCH_ASSOC);

$recentLeaves = $pdo
    ->query("
        SELECT
            lr.*,
            e.full_name
        FROM leave_requests lr
        JOIN employees e
            ON e.id = lr.employee_id
        ORDER BY lr.id DESC
        LIMIT 5
    ")
    ->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="mb-4">

        <h2 class="fw-bold">
            Manager Dashboard
        </h2>

        <p class="text-muted">
            Welcome back,
            <?= e($_SESSION['employee_name']) ?>
        </p>

    </div>

    <div class="row g-4">

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card bg-primary text-white">

                <div class="card-body">

                    <h6>Total Employees</h6>

                    <h2>
                        <?= $totalEmployees ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card bg-success text-white">

                <div class="card-body">

                    <h6>Today's Orders</h6>

                    <h2>
                        <?= $todayOrders ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card bg-warning text-dark">

                <div class="card-body">

                    <h6>Reservations</h6>

                    <h2>
                        <?= $totalReservations ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card bg-warning text-dark">

                <div class="card-body">

                    <h6>Pending Reservations</h6>

                    <h2>
                        <?= $pendingReservations ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card bg-danger text-white">

                <div class="card-body">

                    <h6>Pending Leaves</h6>

                    <h2>
                        <?= $pendingLeaves ?>
                    </h2>

                </div>

            </div>

        </div>

    </div>

    <div class="row mt-4 g-4">

        <div class="col-lg-6">

            <div class="card shadow-sm">

                <div class="card-header">

                    Recent Reservations

                </div>

                <div class="card-body">

                    <table class="table table-striped">

                        <thead>

                        <tr>

                            <th>Name</th>
                            <th>Date</th>
                            <th>Guests</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach($recentReservations as $row): ?>

                            <tr>

                                <td>
                                    <?= e($row['full_name']) ?>
                                </td>

                                <td>
                                    <?= e($row['reservation_date']) ?>
                                </td>

                                <td>
                                    <?= e($row['guests']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

        <div class="col-lg-6">

            <div class="card shadow-sm">

                <div class="card-header">

                    Recent Leave Requests

                </div>

                <div class="card-body">

                    <table class="table table-striped">

                        <thead>

                        <tr>

                            <th>Employee</th>
                            <th>Dates</th>
                            <th>Status</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach($recentLeaves as $leave): ?>

                            <tr>

                                <td>

                                    <?= e($leave['full_name']) ?>

                                </td>

                                <td>

                                    <?= e($leave['start_date']) ?>

                                    -

                                    <?= e($leave['end_date']) ?>

                                </td>

                                <td>

                                    <span class="badge bg-secondary">

                                        <?= e($leave['status']) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

    <div class="row mt-4 g-4">

        <div class="col-lg-3">

            <a
                href="employees.php"
                class="btn btn-maroon w-100">

                Employee Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="manage-shift.php"
                class="btn btn-maroon w-100">

                Schedule Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="manage-leaves.php"
                class="btn btn-maroon w-100">

                Leave Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="manage-evaluations.php"
                class="btn btn-maroon w-100">

                Evaluation Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="manage-menu.php"
                class="btn btn-maroon w-100">

                Menu Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="manage-reservations.php"
                class="btn btn-maroon w-100">

                Reservation Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="manage-reviews.php"
                class="btn btn-maroon w-100">

                Review Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="manage-offers.php"
                class="btn btn-maroon w-100">

                Offer Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="messages.php"
                class="btn btn-maroon w-100">

                Message Management

            </a>

        </div>

        <div class="col-lg-3">

            <a
                href="../employee/dashboard.php"
                class="btn btn-maroon w-100">

                Employee dashboard

            </a>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>