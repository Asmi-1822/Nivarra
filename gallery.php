<?php

declare(strict_types=1);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';

/*
|--------------------------------------------------------------------------
| Gallery Images
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        image,
        description
    FROM gallery
    WHERE status = 'active'
    ORDER BY id ASC
");

$stmt->execute();

$galleryImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/navbar.php';

?>

<div class="container py-5">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h1 class="fw-bold">
                Gallery
            </h1>

            <p class="text-muted mb-0">
                Take a look at NIVARRA and our dining experience.
            </p>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="index.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;"
            >
                Back
            </a>

        </div>

    </div>


    <!-- Gallery -->

    <div class="row g-4">

        <?php if (!$galleryImages): ?>

            <div class="col-12">

                <div class="alert alert-info">
                    No gallery images are available at the moment.
                </div>

            </div>

        <?php else: ?>

            <?php foreach ($galleryImages as $gallery): ?>

                <div class="col-12 col-sm-6 col-md-4 col-lg-3">

                    <div class="card shadow-sm h-100">

                        <img
                            src="assets/images/gallery/<?= e($gallery['image']) ?>"
                            class="card-img-top"
                            alt="<?= e($gallery['title']) ?>"
                            style="height: 220px; object-fit: cover;"
                        >

                        <?php if (
                            !empty($gallery['title']) ||
                            !empty($gallery['description'])
                        ): ?>

                            <div class="card-body">

                                <?php if (!empty($gallery['title'])): ?>

                                    <h5 class="card-title mb-2">
                                        <?= e($gallery['title']) ?>
                                    </h5>

                                <?php endif; ?>

                                <?php if (!empty($gallery['description'])): ?>

                                    <p class="card-text text-muted mb-0">
                                        <?= e($gallery['description']) ?>
                                    </p>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <!-- Location -->

    <div class="card shadow-sm mt-5">

        <div class="card-body p-4">

            <h2 class="fw-bold mb-4">
                Location
            </h2>

            <div class="ratio ratio-16x9">

                <iframe
                    src="https://maps.google.com/maps?q=restaurant&t=&z=13&ie=UTF8&iwloc=&output=embed"
                    title="NIVARRA Location"
                    loading="lazy">
                </iframe>

            </div>

            <div class="mt-4">

                <h4 class="fw-bold">
                    Directions
                </h4>

                <p class="mb-0">
                    Located near the city center.
                    Parking available.
                </p>

            </div>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>