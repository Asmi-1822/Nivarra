<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Manage Offers';

/*
|--------------------------------------------------------------------------
| Auto Expire Offers
|--------------------------------------------------------------------------
*/

$pdo->exec("
    UPDATE offers
    SET status = 'active'
    WHERE end_date < CURDATE()
");

/*
|--------------------------------------------------------------------------
| Toggle Offer Status
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $offerId = (int)($_POST['offer_id'] ?? 0);

    if (
        isset($_POST['toggle_offer'])
        && $offerId > 0
    ) {

        $stmt = $pdo->prepare("
            UPDATE offers
            SET is_active =
                CASE
                    WHEN is_active = 1 THEN 0
                    ELSE 1
                END
            WHERE id = ?
        ");

        $stmt->execute([$offerId]);

        flash(
            'success',
            'Offer status updated.'
        );

        redirect('manage-offers.php');
    }
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$params = [];
$where = '';

if ($search !== '') {

    $where = "
        WHERE
            title LIKE ?
            OR description LIKE ?
    ";

    $term = '%' . $search . '%';

    $params = [
        $term,
        $term
    ];
}

$stmt = $pdo->prepare("
    SELECT *
    FROM offers
    $where
    ORDER BY start_date DESC
");

$stmt->execute($params);

$offers = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2>Offer Management</h2>

            <p class="text-muted mb-0">
                Create and manage restaurant promotions
            </p>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="offer-form.php"
                class="btn btn-maroon">

                Add Offer

            </a>

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>

    <?php if($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <div class="col-md-10">

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            class="form-control"
                            placeholder="Search offers">

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

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover">

                    <thead>

                    <tr>

                        <th>Offer</th>
                        <th>Discount</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach($offers as $offer): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= e($offer['title']) ?>

                                </strong>

                                <br>

                                <small>

                                    <?= e($offer['description']) ?>

                                </small>

                            </td>

                            <td>

                                <?php if(
                                    $offer['discount_type']
                                    === 'percentage'
                                ): ?>

                                    <?= e(
                                        $offer['discount_value']
                                    ) ?>%

                                <?php else: ?>

                                    Rs.
                                    <?= number_format(
                                        (float)$offer['discount_value'],
                                        2
                                    ) ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= e(
                                    $offer['start_date']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $offer['end_date']
                                ) ?>

                            </td>

                            <td>

                                <?php if(
                                    (int)$offer['is_active'] === 1
                                ): ?>

                                    <span class="badge bg-success">

                                        Active

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">

                                        Inactive

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="btn-group">

                                    <a
                                        href="offer-form.php?id=<?= (int)$offer['id'] ?>"
                                        class="btn btn-sm btn-warning">

                                        Edit

                                    </a>

                                    <form method="POST">

                                        <?= csrf_input() ?>

                                        <input
                                            type="hidden"
                                            name="offer_id"
                                            value="<?= (int)$offer['id'] ?>">

                                        <button
                                            type="submit"
                                            name="toggle_offer"
                                            class="btn btn-sm btn-danger">

                                            <?= (int)$offer['is_active']
                                                ? 'Disable'
                                                : 'Enable' ?>

                                        </button>

                                    </form>

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