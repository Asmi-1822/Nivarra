<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Menu';

/*
|--------------------------------------------------------------------------
| Menu Categories
|--------------------------------------------------------------------------
*/

$categories = [
    'starter' => 'Starters',
    'main' => 'Main Courses',
    'dessert' => 'Desserts',
    'beverage' => 'Beverages'
];

/*
|--------------------------------------------------------------------------
| Menu Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        category,
        name,
        description,
        image,
        price,
        dietary_badges
    FROM menu_items
    WHERE active = 1
    ORDER BY
        FIELD(
            category,
            'Soups'
            'Starters',
            'Main Courses',
            'Desserts',
            'Beverages'
        ),
        name ASC
");

$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Group Items By Category
|--------------------------------------------------------------------------
*/

$groupedMenu = [];

foreach ($categories as $category => $label) {
    $groupedMenu[$category] = [];
}

foreach ($menuItems as $item) {

    $category = strtolower(
        trim(
            (string)($item['category'] ?? '')
        )
    );

    if (
        $category !== '' &&
        isset($groupedMenu[$category])
    ) {

        $groupedMenu[$category][] = $item;
    }
}

include '../includes/header.php';
include '../includes/customer-navbar.php';

?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Our Menu
            </h2>

            <p class="text-muted mb-0">
                Explore our selection of food and beverages.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>


    <?php if (!$menuItems): ?>

        <div class="alert alert-info">

            No menu items are currently available.

        </div>

    <?php else: ?>

        <?php foreach ($categories as $category => $categoryName): ?>

            <?php

            if (
                empty(
                    $groupedMenu[$category]
                )
            ) {
                continue;
            }

            ?>


            <section class="mb-5">

                <h3 class="mb-3">

                    <?= htmlspecialchars(
                        $categoryName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </h3>


                <div class="row g-4">

                    <?php foreach (
                        $groupedMenu[$category]
                        as $item
                    ): ?>

                        <?php

                        $image = trim(
                            (string)(
                                $item['image'] ?? ''
                            )
                        );

                        $dietaryBadges = trim(
                            (string)(
                                $item['dietary_badges'] ?? ''
                            )
                        );

                        $description = trim(
                            (string)(
                                $item['description'] ?? ''
                            )
                        );

                        ?>


                        <div
                            class="col-12 col-md-6 col-lg-4">

                            <div
                                class="card h-100 shadow-sm">


                                <!-- Image -->

                                <?php if ($image !== ''): ?>

                                    <img
                                        src="../<?= htmlspecialchars(
                                            ltrim(
                                                $image,
                                                '/'
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        class="card-img-top"
                                        alt="<?= htmlspecialchars(
                                            (string)$item['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        style="
                                            height:220px;
                                            object-fit:cover;
                                        ">

                                <?php else: ?>

                                    <div
                                        class="d-flex
                                               align-items-center
                                               justify-content-center
                                               bg-light
                                               text-muted"
                                        style="
                                            height:220px;
                                        ">

                                        No Image Available

                                    </div>

                                <?php endif; ?>


                                <!-- Card Body -->

                                <div
                                    class="card-body d-flex flex-column">


                                    <div
                                        class="d-flex
                                               justify-content-between
                                               align-items-start
                                               gap-2">

                                        <h5
                                            class="card-title mb-2">

                                            <?= htmlspecialchars(
                                                (string)$item['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h5>


                                        <span
                                            class="fw-bold text-nowrap">

                                            ₹<?= number_format(
                                                (float)$item['price'],
                                                2
                                            ) ?>

                                        </span>

                                    </div>


                                    <?php if ($description !== ''): ?>

                                        <p
                                            class="card-text text-muted">

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $description,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                            ) ?>

                                        </p>

                                    <?php endif; ?>


                                    <?php if (
                                        $dietaryBadges !== ''
                                    ): ?>

                                        <div
                                            class="mt-auto pt-2">

                                            <?php

                                            $badges = preg_split(
                                                '/[,|]+/',
                                                $dietaryBadges
                                            );

                                            ?>


                                            <?php foreach (
                                                $badges
                                                as $badge
                                            ): ?>

                                                <?php

                                                $badge = trim(
                                                    $badge
                                                );

                                                if (
                                                    $badge === ''
                                                ) {
                                                    continue;
                                                }

                                                ?>


                                                <span
                                                    class="badge bg-success me-1 mb-1">

                                                    <?= htmlspecialchars(
                                                        $badge,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </span>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</div>


<?php include '../includes/footer.php'; ?>