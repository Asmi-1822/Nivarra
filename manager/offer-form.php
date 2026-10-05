<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Offer Form';

$id = (int)($_GET['id'] ?? 0);

$isEdit = $id > 0;

$offer = [
    'title' => '',
    'description' => '',
    'discount_type' => 'percentage',
    'discount_value' => '',
    'start_date' => '',
    'end_date' => '',
    'banner_image' => '',
    'is_active' => 1
];

if ($isEdit) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM offers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $offer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$offer) {

        flash('error', 'Offer not found');

        redirect('manage-offers.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discountType = trim($_POST['discount_type'] ?? '');
    $discountValue = trim($_POST['discount_value'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');

    $isActive =
        isset($_POST['is_active'])
            ? 1
            : 0;

    if ($title === '') {
        $errors[] = 'Offer title is required.';
    }

    if (
        !in_array(
            $discountType,
            ['percentage','fixed'],
            true
        )
    ) {
        $errors[] = 'Invalid discount type.';
    }

    if (
        !is_numeric($discountValue)
    ) {
        $errors[] =
            'Discount value must be numeric.';
    }

    if (
        strtotime($startDate)
        >
        strtotime($endDate)
    ) {
        $errors[] =
            'End date must be after start date.';
    }

    $bannerImage =
        $offer['banner_image'];

    /*
    |--------------------------------------------------------------------------
    | Upload Banner
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES['banner'])
        &&
        $_FILES['banner']['error']
        !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['banner']['size']
            > 5 * 1024 * 1024
        ) {

            $errors[] =
                'Maximum image size is 5MB.';
        }

        if (!$errors) {

            $finfo =
                new finfo(FILEINFO_MIME_TYPE);

            $mime =
                $finfo->file(
                    $_FILES['banner']['tmp_name']
                );

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($allowed[$mime])) {

                $errors[] =
                    'Invalid image format.';
            }

            if (!$errors) {

                $filename =
                    bin2hex(
                        random_bytes(16)
                    ) .
                    '.' .
                    $allowed[$mime];

                $directory =
                    '../assets/uploads/offers/';

                if (!is_dir($directory)) {

                    mkdir(
                        $directory,
                        0755,
                        true
                    );
                }

                $destination =
                    $directory .
                    $filename;

                move_uploaded_file(
                    $_FILES['banner']['tmp_name'],
                    $destination
                );

                $bannerImage =
                    'assets/uploads/offers/' .
                    $filename;
            }
        }
    }

    if (!$errors) {

        if ($isEdit) {

            $stmt = $pdo->prepare("
                UPDATE offers
                SET
                    title = ?,
                    description = ?,
                    discount_type = ?,
                    discount_value = ?,
                    start_date = ?,
                    end_date = ?,
                    banner_image = ?,
                    is_active = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $title,
                $description,
                $discountType,
                $discountValue,
                $startDate,
                $endDate,
                $bannerImage,
                $isActive,
                $id
            ]);

            flash(
                'success',
                'Offer updated.'
            );

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO offers
                (
                    title,
                    description,
                    discount_type,
                    discount_value,
                    start_date,
                    end_date,
                    banner_image,
                    is_active
                )
                VALUES
                (
                    ?,?,?,?,?,?,?,?
                )
            ");

            $stmt->execute([
                $title,
                $description,
                $discountType,
                $discountValue,
                $startDate,
                $endDate,
                $bannerImage,
                $isActive
            ]);

            flash(
                'success',
                'Offer created.'
            );
        }

        redirect(
            'manage-offers.php'
        );
    }
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>

            <?= $isEdit
                ? 'Edit Offer'
                : 'Create Offer' ?>

        </h2>

        <a
            href="manage-offers.php"
            class="btn btn-secondary">

            Back

        </a>

    </div>

    <?php if ($errors): ?>

        <div class="alert alert-danger">

            <ul>

                <?php foreach($errors as $error): ?>

                    <li><?= e($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                enctype="multipart/form-data">

                <?= csrf_input() ?>

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">

                            Offer Title

                        </label>

                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            value="<?= e($offer['title']) ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">

                            Discount Value

                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="discount_value"
                            class="form-control"
                            value="<?= e((string)$offer['discount_value']) ?>"
                            required>

                    </div>

                    <div class="col-md-12">

                        <label class="form-label">

                            Description

                        </label>

                        <textarea
                            name="description"
                            rows="4"
                            class="form-control"><?= e($offer['description']) ?></textarea>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">

                            Discount Type

                        </label>

                        <select
                            name="discount_type"
                            class="form-select">

                            <option value="percentage">
                                Percentage
                            </option>

                            <option value="fixed">
                                Fixed Amount
                            </option>

                        </select>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">

                            Start Date

                        </label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="<?= e($offer['start_date']) ?>"
                            required>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">

                            End Date

                        </label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="<?= e($offer['end_date']) ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">

                            Banner Image

                        </label>

                        <input
                            type="file"
                            name="banner"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp">

                    </div>

                    <div class="col-md-6">

                        <div class="form-check mt-4">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="is_active"
                                id="active"
                                <?= (int)$offer['is_active'] === 1 ? 'checked' : '' ?>>

                            <label
                                class="form-check-label"
                                for="active">

                                Active Offer

                            </label>

                        </div>

                    </div>

                </div>

                <hr>

                <button
                    class="btn btn-maroon">

                    Save Offer

                </button>

            </form>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>