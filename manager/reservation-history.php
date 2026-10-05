<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';

$pageTitle = 'Reservation History';

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT *
    FROM reservations
    WHERE 1
";

$params = [];

if ($status !== '') {

    $sql .= "
        AND status = ?
    ";

    $params[] = $status;
}

if ($search !== '') {

    $sql .= "
        AND (
            full_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
        )
    ";

    $term = "%{$search}%";

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= "
    ORDER BY
        reservation_date DESC,
        reservation_time DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<style>

    .nivarra-title {
        color: #8B1E1E;
        font-weight: 700;
    }

    .nivarra-card {
        border: 0;
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.08);
    }

    .nivarra-card-header {
        background-color: #8B1E1E;
        color: #ffffff;
        font-weight: 600;
    }

    .table thead th {
        white-space: nowrap;
    }

</style>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="nivarra-title mb-0">

            Reservation History

        </h2>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>

    <!-- Reservation History -->

    <div class="card nivarra-card">

        <div class="card-header nivarra-card-header">

            Reservation History

        </div>

        <div class="card-body">

            <!-- Filters -->

            <form
                method="GET"
                class="row g-3 mb-4">

                <div class="col-md-5">

                    <label
                        for="search"
                        class="form-label">

                        Search Customer

                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        class="form-control"
                        placeholder="Name, email or phone">

                </div>

                <div class="col-md-3">

                    <label
                        for="status"
                        class="form-label">

                        Status

                    </label>

                    <select
                        id="status"
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
                            value="confirmed"
                            <?= $status === 'confirmed' ? 'selected' : '' ?>>

                            Confirmed

                        </option>

                        <option
                            value="cancelled"
                            <?= $status === 'cancelled' ? 'selected' : '' ?>>

                            Cancelled

                        </option>

                        <option
                            value="completed"
                            <?= $status === 'completed' ? 'selected' : '' ?>>

                            Completed

                        </option>

                    </select>

                </div>

                <div class="col-md-2 d-flex align-items-end">

                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        Filter

                    </button>

                </div>

                <div class="col-md-2 d-flex align-items-end">

                    <a
                        href="reservation-history.php"
                        class="btn btn-secondary w-100">

                        Reset

                    </a>

                </div>

            </form>

            <!-- Reservations Table -->

            <div class="table-responsive">

                <table
                    class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                    <tr>

                        <th>ID</th>

                        <th>Name</th>

                        <th>Date</th>

                        <th>Time</th>

                        <th>Guests</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($reservations)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center text-muted py-4">

                                No reservations found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach (
                            $reservations as $reservation
                        ): ?>

                            <?php

                            $badgeClass = match (
                                $reservation['status']
                            ) {

                                'confirmed'
                                    => 'bg-success',

                                'pending'
                                    => 'bg-warning text-dark',

                                'cancelled'
                                    => 'bg-danger',

                                'completed'
                                    => 'bg-primary',

                                default
                                    => 'bg-secondary'
                            };

                            ?>

                            <tr>

                                <td>

                                    <strong>
                                        #<?= (int)$reservation['id'] ?>
                                    </strong>

                                </td>

                                <td>

                                    <?= e(
                                        $reservation['full_name']
                                    ) ?>

                                </td>

                                <td>

                                    <?= e(
                                        $reservation['reservation_date']
                                    ) ?>

                                </td>

                                <td>

                                    <?= e(
                                        $reservation['reservation_time']
                                    ) ?>

                                </td>

                                <td>

                                    <?= (int)$reservation['guests'] ?>

                                </td>

                                <td>

                                    <span
                                        class="badge <?= $badgeClass ?>">

                                        <?= e(
                                            ucfirst(
                                                (string)$reservation['status']
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <a
                                        href="reservation-details.php?id=<?= (int)$reservation['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">

                                        View

                                    </a>

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

<?php include '../includes/footer.php'; ?>