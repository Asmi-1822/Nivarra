<?php

    declare(strict_types=1);

    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';

    $pageTitle = 'NIVARRA - Restaurant & Dining';

    /*
    |--------------------------------------------------------------------------
    | Featured Menu Items
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            category,
            name,
            description,
            image,
            price
        FROM menu_items
        WHERE active = 1
        ORDER BY id DESC
        LIMIT 6
    ");

    $featuredItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Active Offers
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            title,
            description,
            discount_type,
            discount_value,
            banner_image,
            start_date,
            end_date
        FROM offers
        WHERE status = 'active'
        AND(
            start_date IS NULL
            OR start_date <= CURDATE()
        )
        AND(
            end_date IS NULL
            OR end_date >= CURDATE()
        )
        ORDER BY id DESC
        LIMIT 3
    ");

    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    include 'includes/header.php';
?>

    <style>
        .customer-hero {
            min-height: 520px;
            display: flex;
            align-items: center;
            background:
                linear-gradient(
                    rgba(119, 8, 8, 0.72),
                    rgba(180, 023, 023, 0.72)
                ),
                url('assets/images/restaurant.jpg') center/cover no-repeat;
            color: #fff;
        }
    </style>

    <!-- =========================================================
        Public Header
    ========================================================= -->

    <nav
        class="navbar"
        style="background-color:#8B1E1E;">

        <div class="container">

            <a
                class="navbar-brand d-flex align-items-center text-white"
                href="index.php">

                <img
                    src="assets/images/logo.png"
                    alt="NIVARAA Logo"
                    height="45"
                    class="me-3">

                <span class="fw-bold">
                    NIVARAA
                </span>

            </a>
            <div>

                <a 
                    class="btn btn-info"
                    href="about.php">

                    About

                </a>

                <a 
                    class="btn btn-info"
                    href="contact.php">

                    Contact

                </a>

                <a 
                    class="btn btn-info"
                    href="gallery.php">

                    Gallery

                </a>

            </div>

            <div class="ms-auto d-flex gap-2">

                <a
                    class="btn btn-warning"
                    href="auth/login.php">

                    Login

                </a>

                <a
                    class="btn btn-light"
                    href="auth/register.php">

                    Create Account

                </a>

            </div>

        </div>

    </nav>

    <!-- =========================================================
        Hero Section
    ========================================================= -->

    <section class="customer-hero">

        <div class="container">

            <div class="customer-hero-content">

                <span class="badge bg-secondary mb-3">
                    Welcome to NIVARRA
                </span>

                <h1 class="display-4 fw-bold mb-3">
                    Exceptional Food.
                    Memorable Dining.
                </h1>

                <p class="lead mb-4 text-muted">
                    Discover delicious dishes, exclusive offers,
                    easy reservations and a dining experience designed
                    around you.
                </p>

                <div class="d-flex flex-wrap gap-3">

                    <a
                        href="customer/menu.php"
                        class="btn btn-maroon btn-lg">

                        Explore Menu

                    </a>

                    <a
                        href="auth/register.php"
                        class="btn btn-outline-secondary btn-lg">

                        <font color="black">Create Your Account</font>

                    </a>

                </div>

            </div>

        </div>

    </section>

    <!-- =========================================================
        NIVARRA Experience
    ========================================================= -->

    <section class="customer-section">

        <div class="container">

            <div class="row align-items-center g-5">

                <div class="col-lg-6">

                    <span class="text-muted">
                        THE NIVARRA EXPERIENCE
                    </span>

                    <h2 class="mt-2 mb-3">
                        A better way to enjoy your dining experience
                    </h2>

                    <p class="text-muted">
                        NIVARRA brings together great food, convenient
                        ordering, reservations and personalized customer
                        services in one place.
                    </p>

                    <div class="row g-3 mt-3">

                        <div class="col-sm-6">

                            <div class="border rounded p-3 h-100">

                                <h5>
                                    Fresh Menu
                                </h5>

                                <p class="text-muted mb-0">
                                    Explore our selection of dishes
                                    and beverages.
                                </p>

                            </div>

                        </div>

                        <div class="col-sm-6">

                            <div class="border rounded p-3 h-100">

                                <h5>
                                    Easy Reservations
                                </h5>

                                <p class="text-muted mb-0">
                                    Reserve your table quickly
                                    and conveniently.
                                </p>

                            </div>

                        </div>

                        <div class="col-sm-6">

                            <div class="border rounded p-3 h-100">

                                <h5>
                                    Exclusive Offers
                                </h5>

                                <p class="text-muted mb-0">
                                    Discover current promotions
                                    and discounts.
                                </p>

                            </div>

                        </div>

                        <div class="col-sm-6">

                            <div class="border rounded p-3 h-100">

                                <h5>
                                    Loyalty Rewards
                                </h5>

                                <p class="text-muted mb-0">
                                    Earn points through your
                                    NIVARRA account.
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-6">

                    <div class="bg-light rounded p-5 text-center">

                        <img
                            src="assets/images/logo.png"
                            alt="NIVARAA"
                            class="img-fluid mb-4"
                            style="max-height:120px;">

                        <h3>
                            Your table is waiting.
                        </h3>

                        <p class="text-muted">
                            Create your free customer account to manage
                            your orders, reservations, favorites and rewards.
                        </p>

                        <a
                            href="auth/register.php"
                            class="btn btn-maroon">

                            Join NIVARRA

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>

    <!-- =========================================================
        Featured Menu
    ========================================================= -->

    <section class="customer-section bg-light">

        <div class="container">

            <div class="text-center mb-5">

                <span class="text-muted">
                    OUR MENU
                </span>

                <h2 class="mt-2">
                    Featured Dishes
                </h2>

                <p class="text-muted">
                    Discover some of the dishes available at NIVARRA.
                </p>

            </div>

            <div class="row g-4">

                <?php if (!$featuredItems): ?>

                    <div class="col-12">

                        <div class="text-center text-muted">

                            Menu items will be available soon.

                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($featuredItems as $item): ?>

                        <div class="col-md-6 col-lg-4">

                            <div class="card menu-card shadow-sm border-0">

                                <?php

                                $image = trim(
                                    (string)($item['image'] ?? '')
                                );

                                ?>

                                <?php if ($image !== ''): ?>

                                    <img
                                        src="<?= htmlspecialchars(
                                            $image,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            (string)$item['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>">

                                <?php else: ?>

                                    <div
                                        class="bg-secondary text-white d-flex
                                        align-items-center justify-content-center"
                                        style="height:210px;">

                                        No Image

                                    </div>

                                <?php endif; ?>

                                <div class="card-body">

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                (string)$item['category']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </small>

                                    <h5 class="card-title mt-1">

                                        <?= htmlspecialchars(
                                            (string)$item['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                    ) ?>

                                    </h5>

                                    <p class="card-text text-muted">

                                        <?= htmlspecialchars(
                                            (string)(
                                                $item['description'] ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </p>

                                    <div
                                        class="d-flex justify-content-between
                                        align-items-center">

                                        <strong>

                                            ₹<?= number_format(
                                                (float)$item['price'],
                                                2
                                            ) ?>

                                        </strong>

                                        <a
                                            href="customer/menu.php"
                                            class="btn btn-sm btn-maroon">

                                            View Menu

                                        </a>

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <!-- =========================================================
        Current Offers
    ========================================================= -->

    <section class="customer-section">

        <div class="container">

            <div
                class="d-flex justify-content-between
                align-items-center mb-4">

                <div>

                    <span class="text-muted">
                        SPECIAL OFFERS
                    </span>

                    <h2 class="mt-2 mb-0">
                        Current Offers
                    </h2>

                </div>

                <a
                    href="customer/offers.php"
                    class="btn btn-maroon">

                    View All

                </a>

            </div>

            <div class="row g-4">

                <?php if (!$offers): ?>

                    <div class="col-12">

                        <div class="text-center text-muted">

                            No current offers are available.

                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($offers as $offer): ?>

                        <div class="col-md-4">

                            <div class="card offer-card shadow-sm border-0">

                                <?php

                                $bannerImage = trim(
                                    (string)(
                                        $offer['banner_image'] ?? ''
                                    )
                                );

                                ?>

                                <?php if ($bannerImage !== ''): ?>

                                    <img
                                        src="<?= htmlspecialchars(
                                            $bannerImage,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            (string)$offer['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>">

                                <?php endif; ?>

                                <div class="card-body">

                                    <h5>

                                        <?= htmlspecialchars(
                                            (string)$offer['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </h5>

                                    <p class="text-muted">

                                        <?= htmlspecialchars(
                                            (string)(
                                                $offer['description'] ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </p>

                                    <strong>

                                        <?php if (
                                            $offer['discount_type']
                                            === 'percentage'
                                        ): ?>

                                            <?= number_format(
                                                (float)$offer[
                                                    'discount_value'
                                                ],
                                                2
                                            ) ?>% OFF

                                        <?php else: ?>

                                            ₹<?= number_format(
                                                (float)$offer[
                                                    'discount_value'
                                                ],
                                                2
                                            ) ?> OFF

                                        <?php endif; ?>

                                    </strong>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <!-- =========================================================
        Customer Call To Action
    ========================================================= -->

    <section class="customer-section customer-cta">

        <div class="container text-center">

            <h2 class="mb-3">
                Ready to experience NIVARRA?
            </h2>

            <p class="lead mb-4">
                Create your account and start exploring everything
                NIVARRA has to offer.
            </p>

            <div
                class="d-flex justify-content-center
                gap-3 flex-wrap">

                <a
                    href="auth/register.php"
                    class="btn btn-light btn-lg">

                    Create Account

                </a>

                <a
                    href="auth/login.php"
                    class="btn btn-outline-light btn-lg">

                    Customer Login

                </a>

            </div>

        </div>

    </section>

    <!-- =========================================================
        Footer
    ========================================================= -->

<?php include 'includes/footer.php'; ?>