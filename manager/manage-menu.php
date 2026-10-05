<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Manage Menu';

/*
|--------------------------------------------------------------------------
| Toggle Availability
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $itemId =
        (int)($_POST['item_id'] ?? 0);

    if (
        isset($_POST['toggle_availability'])
        && $itemId > 0
    ) {

        $stmt = $pdo->prepare("
            UPDATE menu_items
            SET active =
                CASE
                    WHEN active = 1 THEN 0
                    ELSE 1
                END
            WHERE id = ?
        ");

        $stmt->execute([
            $itemId
        ]);

        flash(
            'success',
            'Menu item updated.'
        );

        redirect(
            'manage-menu.php'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Search & Filters
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string)($_GET['search'] ?? '')
    );

$category =
    trim(
        (string)($_GET['category'] ?? '')
    );

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

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            name LIKE ?
            OR description LIKE ?
        )
    ";

    $term =
        '%' .
        $search .
        '%';

    $params[] =
        $term;

    $params[] =
        $term;
}

/*
|--------------------------------------------------------------------------
| Category
|--------------------------------------------------------------------------
*/

if ($category !== '') {

    $where[] =
        "category = ?";

    $params[] =
        $category;
}

/*
|--------------------------------------------------------------------------
| WHERE Clause
|--------------------------------------------------------------------------
*/

$whereSql = '';

if ($where) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM menu_items
    $whereSql
");

$countStmt->execute(
    $params
);

$totalRows =
    (int)$countStmt->fetchColumn();

$totalPages =
    max(
        1,
        (int)ceil(
            $totalRows /
            $perPage
        )
    );

/*
|--------------------------------------------------------------------------
| Correct Page
|--------------------------------------------------------------------------
*/

if (
    $page > $totalPages
) {

    $page =
        $totalPages;

    $offset =
        ($page - 1) *
        $perPage;
}

/*
|--------------------------------------------------------------------------
| Menu Items
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        category,
        name,
        description,
        image,
        price,
        dietary_badges,
        active,
        created_at,
        updated_at
    FROM menu_items
    $whereSql
    ORDER BY category, name
    LIMIT $perPage
    OFFSET $offset
";

$stmt =
    $pdo->prepare($sql);

$stmt->execute(
    $params
);

$items =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    include '../includes/header.php';
    include '../includes/manager-navbar.php';
    
?>

<div class="container-fluid py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2>
                Menu Management
            </h2>

            <p class="text-muted mb-0">
                Manage restaurant menu items
            </p>

        </div>


        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="menu-form.php"
                class="btn btn-maroon">

                Add Menu Item

            </a>


            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">

                Back

            </a>

        </div>

    </div>


    <!-- Success Message -->

    <?php if ($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e((string)$msg) ?>

        </div>

    <?php endif; ?>


    <!-- Search and Filters -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-md-5">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search menu..."
                            value="<?= e($search) ?>">

                    </div>


                    <div class="col-md-4">

                        <select
                            name="category"
                            class="form-select">

                            <option value="">

                                All Categories

                            </option>

                            <option
                                value="Starters"
                                <?= $category === 'Starters'
                                    ? 'selected'
                                    : '' ?>>

                                Starters

                            </option>

                            <option
                                value="Main Courses"
                                <?= $category === 'Main Courses'
                                    ? 'selected'
                                    : '' ?>>

                                Main Courses

                            </option>

                            <option
                                value="Desserts"
                                <?= $category === 'Desserts'
                                    ? 'selected'
                                    : '' ?>>

                                Desserts

                            </option>

                            <option
                                value="Beverages"
                                <?= $category === 'Beverages'
                                    ? 'selected'
                                    : '' ?>>

                                Beverages

                            </option>

                        </select>

                    </div>


                    <div class="col-md-3">

                        <button
                            type="submit"
                            class="btn btn-maroon w-100">

                            Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- Menu Table -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>

                            <th>
                                Image
                            </th>

                            <th>
                                Name
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Badges
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (!$items): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="text-center text-muted py-4">

                                    No menu items found.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($items as $item): ?>

                                <?php
                                $imageName =
                                    trim(
                                        (string)($item['image'] ?? '')
                                    );
                                ?>

                                <tr>

                                    <!-- Image -->

                                    <td>

                                        <?php if ($imageName !== ''): ?>

                                            <img
                                                src="../<?= e($imageName) ?>"
                                                width="60"
                                                height="60"
                                                class="rounded"
                                                style="object-fit: cover;"
                                                alt="<?= e(
                                                    (string)($item['name'] ?? 'Menu item')
                                                ) ?>">

                                        <?php else: ?>

                                            <div
                                                class="bg-light border rounded d-flex align-items-center justify-content-center"
                                                style="width: 60px; height: 60px;">

                                                <span
                                                    class="text-muted small">

                                                    No Image

                                                </span>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Name -->

                                    <td>

                                        <?= e(
                                            (string)($item['name'] ?? '')
                                        ) ?>

                                    </td>


                                    <!-- Category -->

                                    <td>

                                        <?= e(
                                            (string)($item['category'] ?? '')
                                        ) ?>

                                    </td>


                                    <!-- Price -->

                                    <td>

                                        Rs.

                                        <?= number_format(
                                            (float)($item['price'] ?? 0),
                                            2
                                        ) ?>

                                    </td>


                                    <!-- Badges -->

                                    <td>

                                        <?= e(
                                            (string)($item['dietary_badges'] ?? '')
                                        ) ?>

                                    </td>


                                    <!-- Status -->

                                    <td>

                                        <?php if (
                                            (int)($item['active'] ?? 0) === 1
                                        ): ?>

                                            <span
                                                class="badge bg-success">

                                                Available

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge bg-danger">

                                                Disabled

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Actions -->

                                    <td>

                                        <div class="btn-group">

                                            <a
                                                href="menu-form.php?id=<?= (int)$item['id'] ?>"
                                                class="btn btn-sm btn-warning">

                                                Edit

                                            </a>

                                            <t>
                                                
                                            </t>

                                            <form
                                                method="POST"
                                                class="d-inline">

                                                <?= csrf_input() ?>

                                                <input
                                                    type="hidden"
                                                    name="item_id"
                                                    value="<?= (int)$item['id'] ?>">

                                                <button
                                                    type="submit"
                                                    name="toggle_availability"
                                                    class="btn btn-sm btn-danger">

                                                    <?= (int)($item['active'] ?? 0)
                                                        === 1
                                                        ? 'Disable'
                                                        : 'Enable' ?>

                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- Pagination -->

    <?php if ($totalPages > 1): ?>

        <nav
            class="mt-4"
            aria-label="Menu pagination">

            <ul class="pagination">

                <?php for (
                    $i = 1;
                    $i <= $totalPages;
                    $i++
                ): ?>

                    <li
                        class="page-item <?= $i === $page
                            ? 'active'
                            : '' ?>">

                        <a
                            class="page-link"
                            href="?search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&page=<?= $i ?>">

                            <?= $i ?>

                        </a>

                    </li>

                <?php endfor; ?>

            </ul>

        </nav>

    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>