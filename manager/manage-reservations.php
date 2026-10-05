<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Manage Reservations';

/*
|--------------------------------------------------------------------------
| Update Status
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $reservationId =
        (int)($_POST['reservation_id'] ?? 0);

    $action =
        $_POST['action'] ?? '';

    $validActions = [
        'confirm',
        'cancel',
        'complete'
    ];

    if (
        $reservationId > 0 &&
        in_array(
            $action,
            $validActions,
            true
        )
    ) {

        $statusMap = [
            'confirm' => 'confirmed',
            'cancel' => 'cancelled',
            'complete' => 'completed'
        ];

        $stmt = $pdo->prepare("
            UPDATE reservations
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $statusMap[$action],
            $reservationId
        ]);

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */

        if (function_exists('auditLog')) {

            auditLog(
                $_SESSION['employee_id'],
                'reservation_status_updated',
                'Reservation #' . $reservationId .
                ' changed to ' .
                $statusMap[$action]
            );
        }

        flash(
            'success',
            'Reservation updated.'
        );

        redirect(
            'manage-reservations.php'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');

$status =
    trim($_GET['status'] ?? '');

$page =
    max(
        1,
        (int)($_GET['page'] ?? 1)
    );

$perPage = 20;
$offset =
    ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            full_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
        )
    ";

    $term =
        '%' . $search . '%';

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($status !== '') {

    $where[] = 'status = ?';

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
| Count
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    $whereSql
");

$countStmt->execute($params);

$totalRows =
    (int)$countStmt->fetchColumn();

$totalPages =
    max(
        1,
        (int)ceil(
            $totalRows / $perPage
        )
    );

/*
|--------------------------------------------------------------------------
| Reservations
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM reservations
    $whereSql
    ORDER BY reservation_date DESC,
             reservation_time DESC
    LIMIT $perPage
    OFFSET $offset
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$reservations =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2>

                Reservation Management

            </h2>

            <p class="text-muted mb-0">

                Manage customer reservations

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

    <!-- Filters -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-md-6">

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            class="form-control"
                            placeholder="Search customer">

                    </div>

                    <div class="col-md-4">

                        <select
                            name="status"
                            class="form-select">

                            <option value="">
                                All Statuses
                            </option>

                            <?php
                            $statuses = [
                                'pending',
                                'confirmed',
                                'completed',
                                'cancelled'
                            ];

                            foreach (
                                $statuses
                                as $s
                            ):
                            ?>

                                <option
                                    value="<?= e($s) ?>"
                                    <?= $status === $s
                                        ? 'selected'
                                        : '' ?>>

                                    <?= ucfirst($s) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-2">

                        <button
                            class="btn btn-maroon w-100">

                            Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- Reservations -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th>ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Guests</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $reservations
                        as $reservation
                    ): ?>

                        <tr>

                            <td>

                                #<?= (int)$reservation['id'] ?>

                            </td>

                            <td>

                                <strong>

                                    <?= e(
                                        $reservation['full_name']
                                    ) ?>

                                </strong>

                                <br>

                                <small>

                                    <?= e(
                                        $reservation['phone']
                                    ) ?>

                                </small>

                            </td>

                            <td>

                                <?= e(
                                    $reservation['reservation_date']
                                ) ?>

                                <br>

                                <small>

                                    <?= e(
                                        $reservation['reservation_time']
                                    ) ?>

                                </small>

                            </td>

                            <td>

                                <?= (int)$reservation['guests'] ?>

                            </td>

                            <td>

                                <?php

                                $badge = match (
                                    $reservation['status']
                                ) {

                                    'confirmed'
                                        => 'success',

                                    'cancelled'
                                        => 'danger',

                                    'completed'
                                        => 'primary',

                                    default
                                        => 'warning'
                                };

                                ?>

                                <span
                                    class="badge bg-<?= $badge ?>">

                                    <?= e(
                                        ucfirst(
                                            $reservation['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <div class="btn-group">

                                    <a
                                        href="reservation-details.php?id=<?= (int)$reservation['id'] ?>"
                                        class="btn btn-sm btn-info">

                                        View

                                    </a>

                                    <?php if (
                                        $reservation['status']
                                        === 'pending'
                                    ): ?>

                                        <form method="POST">

                                            <?= csrf_input() ?>

                                            <input
                                                type="hidden"
                                                name="reservation_id"
                                                value="<?= (int)$reservation['id'] ?>">

                                            <button
                                                type="submit"
                                                name="action"
                                                value="confirm"
                                                class="btn btn-sm btn-success">

                                                Confirm

                                            </button>

                                        </form>

                                        <form method="POST">

                                            <?= csrf_input() ?>

                                            <input
                                                type="hidden"
                                                name="reservation_id"
                                                value="<?= (int)$reservation['id'] ?>">

                                            <button
                                                type="submit"
                                                name="action"
                                                value="cancel"
                                                class="btn btn-sm btn-danger">

                                                Cancel

                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

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