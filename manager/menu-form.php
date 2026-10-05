<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$originalFilename = '';
$uploadDirectory = '';
$destination = '';

$pageTitle = 'Menu Item';

$id = (int)($_GET['id'] ?? 0);

$isEdit = $id > 0;

$item = [
    'name' => '',
    'description' => '',
    'price' => '',
    'category' => '',
    'dietary_badges' => '',
    'image' => '',
    'active' => 1
];

/*
|--------------------------------------------------------------------------
| Load Existing Menu Item
|--------------------------------------------------------------------------
*/

if ($isEdit) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            category,
            name,
            description,
            image,
            price,
            dietary_badges,
            active
        FROM menu_items
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingItem) {

        flash(
            'error',
            'Menu item not found.'
        );

        redirect('manage-menu.php');
    }

    $item = $existingItem;
}

$errors = [];

/*
|--------------------------------------------------------------------------
| Save Menu Item
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $name = trim(
        (string)($_POST['name'] ?? '')
    );

    $description = trim(
        (string)($_POST['description'] ?? '')
    );

    $price = trim(
        (string)($_POST['price'] ?? '')
    );

    $category = trim(
        (string)($_POST['category'] ?? '')
    );

    $dietaryBadgeValues = $_POST['dietary_badges'] ?? [];

    if (!is_array($dietaryBadgeValues)) {
        $dietaryBadgeValues = [];
    }

    $allowedDietaryBadges = [
        'V',
        'NV',
        'D',
        'G',
        'N',
        'S',
        'E',
        'SP',
        'L'
    ];

    $dietaryBadgeValues = array_values(
        array_intersect(
            $dietaryBadgeValues,
            $allowedDietaryBadges
        )
    );

    $dietaryBadges = implode(
        ', ',
        $dietaryBadgeValues
    );

    $isAvailable =
        isset($_POST['is_available'])
            ? 1
            : 0;

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $errors[] =
            'Name is required.';
    }

    if (
        $price === ''
        || !is_numeric($price)
        || (float)$price < 0
    ) {

        $errors[] =
            'Price must be a valid number.';
    }

    $validCategories = [
        'Soups',
        'Starters',
        'Main Courses',
        'Desserts',
        'Beverages'
    ];

    if (
        !in_array(
            $category,
            $validCategories,
            true
        )
    ) {

        $errors[] =
            'Invalid category.';
    }

    /*
    |--------------------------------------------------------------------------
    | Keep Existing Image
    |--------------------------------------------------------------------------
    */

    $imageName = trim(
        (string)($item['image'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | Image Upload
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES['image'])
        && $_FILES['image']['error']
            !== UPLOAD_ERR_NO_FILE
    ) {

        $uploadError =
            (int)$_FILES['image']['error'];

        /*
        |--------------------------------------------------------------------------
        | Upload Error
        |--------------------------------------------------------------------------
        */

        if (
            $uploadError !== UPLOAD_ERR_OK
        ) {

            $errors[] =
                'Image upload failed.';
        }

        /*
        |--------------------------------------------------------------------------
        | File Size
        |--------------------------------------------------------------------------
        */

        if (
            !$errors
            && (int)$_FILES['image']['size']
                > 5 * 1024 * 1024
        ) {

            $errors[] =
                'Maximum image size is 5MB.';
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Actual Image
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $imageInfo = @getimagesize(
                $_FILES['image']['tmp_name']
            );

            if ($imageInfo === false) {

                $errors[] =
                    'The uploaded file is not a valid image.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate MIME Type
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $finfo =
                new finfo(FILEINFO_MIME_TYPE);

            $mime =
                $finfo->file(
                    $_FILES['image']['tmp_name']
                );

            $allowedMimes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (
                !in_array(
                    $mime,
                    $allowedMimes,
                    true
                )
            ) {

                $errors[] =
                    'Invalid image format. Please upload JPG, JPEG, PNG, or WEBP.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Preserve Uploaded Filename
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $originalFilename =
                basename(
                    (string)$_FILES['image']['name']
                );

            /*
            |--------------------------------------------------------------------------
            | Remove Unsafe Characters
            |--------------------------------------------------------------------------
            */

            $originalFilename =
                preg_replace(
                    '/[^A-Za-z0-9._-]/',
                    '_',
                    $originalFilename
                );

            $originalFilename =
                trim(
                    (string)$originalFilename,
                    '._'
                );

            if ($originalFilename === '') {

                $errors[] =
                    'Invalid image filename.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Extension
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $extension =
                strtolower(
                    (string)pathinfo(
                        $originalFilename,
                        PATHINFO_EXTENSION
                    )
                );

            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            if (
                !in_array(
                    $extension,
                    $allowedExtensions,
                    true
                )
            ) {

                $errors[] =
                    'Invalid image file extension.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Upload Directory
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $uploadDirectory =
                __DIR__ .
                '/../menu_photos/';

            if (
                !is_dir($uploadDirectory)
            ) {

                if (
                    !mkdir(
                        $uploadDirectory,
                        0755,
                        true
                    )
                ) {

                    $errors[] =
                        'Unable to create the menu image folder.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Save Uploaded File
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $destination =
                $uploadDirectory .
                $originalFilename;

            /*
            |--------------------------------------------------------------------------
            | If Same Filename Exists, Replace It
            |--------------------------------------------------------------------------
            */

            if (
                file_exists($destination)
            ) {

                if (
                    !unlink($destination)
                ) {

                    $errors[] =
                        'Unable to replace the existing image.';
                }
            }
        }

        if (!$errors) {

            if (
                !move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $destination
                )
            ) {

                $errors[] =
                    'Unable to save the uploaded image.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Store Directory + Filename
                |--------------------------------------------------------------------------
                */

                $imageName =
                    'menu_photos/' .
                    $originalFilename;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Database Record
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        if ($isEdit) {

            $stmt = $pdo->prepare("
                UPDATE menu_items
                SET
                    category = ?,
                    name = ?,
                    description = ?,
                    image = ?,
                    price = ?,
                    dietary_badges = ?,
                    active = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $category,
                $name,
                $description,
                $imageName,
                $price,
                $dietaryBadges,
                $isAvailable,
                $id
            ]);

            flash(
                'success',
                'Menu item updated.'
            );

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO menu_items
                (
                    category,
                    name,
                    description,
                    image,
                    price,
                    dietary_badges,
                    active
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $category,
                $name,
                $description,
                $imageName,
                $price,
                $dietaryBadges,
                $isAvailable
            ]);

            flash(
                'success',
                'Menu item created.'
            );
        }

        redirect(
            'manage-menu.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Preserve Submitted Values
    |--------------------------------------------------------------------------
    */

    $item['name'] =
        $name;

    $item['description'] =
        $description;

    $item['price'] =
        $price;

    $item['category'] =
        $category;

    $item['dietary_badges'] =
        $dietaryBadges;

    $item['image'] =
        $imageName;

    $item['active'] =
        $isAvailable;
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2>

                <?= $isEdit
                    ? 'Edit Menu Item'
                    : 'Add Menu Item' ?>

            </h2>

            <p class="text-muted mb-0">

                <?= $isEdit
                    ? 'Update menu item details'
                    : 'Add a new restaurant menu item' ?>

            </p>

        </div>

        <a
            href="manage-menu.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">

            Back

        </a>

    </div>


    <!-- Errors -->

    <?php if ($errors): ?>

        <div class="alert alert-danger">

            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li>

                        <?= e((string)$error) ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- Form -->

    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                enctype="multipart/form-data">

                <?= csrf_input() ?>

                <div class="row g-3">

                    <!-- Item Name -->

                    <div class="col-md-6">

                        <label class="form-label">

                            Item Name

                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="<?= e(
                                (string)($item['name'] ?? '')
                            ) ?>"
                            required>

                    </div>


                    <!-- Price -->

                    <div class="col-md-6">

                        <label class="form-label">

                            Price

                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            class="form-control"
                            value="<?= e(
                                (string)($item['price'] ?? '')
                            ) ?>"
                            required>

                    </div>


                    <!-- Description -->

                    <div class="col-md-12">

                        <label class="form-label">

                            Description

                        </label>

                        <textarea
                            name="description"
                            rows="4"
                            class="form-control"><?= e(
                                (string)($item['description'] ?? '')
                            ) ?></textarea>

                    </div>


                    <!-- Category -->

                    <div class="col-md-6">

                        <label class="form-label">

                            Category

                        </label>

                        <select
                            name="category"
                            class="form-select"
                            required>

                            <option value="">

                                Select Category

                            </option>

                            <option
                                value="Soups"
                                <?= ($item['category'] ?? '') === 'Soups'
                                    ? 'selected'
                                    : '' ?>>

                                Soups

                            </option>

                            <option
                                value="Starters"
                                <?= ($item['category'] ?? '') === 'Starters'
                                    ? 'selected'
                                    : '' ?>>

                                Starters

                            </option>

                            <option
                                value="Main Courses"
                                <?= ($item['category'] ?? '') === 'Main Courses'
                                    ? 'selected'
                                    : '' ?>>

                                Main Courses

                            </option>

                            <option
                                value="Desserts"
                                <?= ($item['category'] ?? '') === 'Desserts'
                                    ? 'selected'
                                    : '' ?>>

                                Desserts

                            </option>

                            <option
                                value="Beverages"
                                <?= ($item['category'] ?? '') === 'Beverages'
                                    ? 'selected'
                                    : '' ?>>

                                Beverages

                            </option>

                        </select>

                    </div>


                    <!-- Dietary Badges -->

                    <div class="col-md-12">

                        <label class="form-label">

                            Dietary Restrictions

                        </label>

                        <?php

                            $selectedBadges = array_filter(
                                array_map(
                                    'trim',
                                    explode(
                                        ',',
                                        (string)($item['dietary_badges'] ?? '')
                                    )
                                )
                            );

                        ?>

                        <div class="row g-2">

                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="V"
                                        class="form-check-input"
                                        id="badge_v"
                                        <?= in_array(
                                            'V',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_v">

                                        🟢 V — Vegetarian

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="NV"
                                        class="form-check-input"
                                        id="badge_nv"
                                        <?= in_array(
                                            'NV',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_nv">

                                        🔴 NV — Non-vegetarian

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="D"
                                        class="form-check-input"
                                        id="badge_d"
                                        <?= in_array(
                                            'D',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_d">

                                        🥛 D — Contains dairy

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="G"
                                        class="form-check-input"
                                        id="badge_g"
                                        <?= in_array(
                                            'G',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_g">

                                        🌾 G — Contains gluten

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="N"
                                        class="form-check-input"
                                        id="badge_n"
                                        <?= in_array(
                                            'N',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_n">

                                        🥜 N — Contains nuts

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="S"
                                        class="form-check-input"
                                        id="badge_s"
                                        <?= in_array(
                                            'S',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_s">

                                        🌱 S — Contains soy

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="E"
                                        class="form-check-input"
                                        id="badge_e"
                                        <?= in_array(
                                            'E',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_e">

                                        🥚 E — Contains egg

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="SP"
                                        class="form-check-input"
                                        id="badge_sp"
                                        <?= in_array(
                                            'SP',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_sp">

                                        🌶️ SP — Spicy

                                    </label>

                                </div>

                            </div>


                            <div class="col-md-4">

                                <div class="form-check">

                                    <input
                                        type="checkbox"
                                        name="dietary_badges[]"
                                        value="L"
                                        class="form-check-input"
                                        id="badge_l"
                                        <?= in_array(
                                            'L',
                                            $selectedBadges,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="badge_l">

                                        L — Contains Lactose

                                    </label>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Image -->

                    <div class="col-md-6">

                        <label class="form-label">

                            Image

                        </label>

                        <input
                            type="file"
                            name="image"
                            accept=".jpg,.jpeg,.png,.webp"
                            class="form-control">

                        <small class="text-muted">

                            Maximum size: 5MB.
                            The uploaded filename will be preserved.

                        </small>

                    </div>


                    <!-- Availability -->

                    <div class="col-md-6">

                        <div class="form-check mt-4">

                            <input
                                type="checkbox"
                                name="is_available"
                                class="form-check-input"
                                id="available"
                                <?= (int)($item['active'] ?? 0) === 1
                                    ? 'checked'
                                    : '' ?>>

                            <label
                                class="form-check-label"
                                for="available">

                                Available

                            </label>

                        </div>

                    </div>


                    <!-- Current Image -->

                    <?php
                    $currentImage =
                        trim(
                            (string)($item['image'] ?? '')
                        );
                    ?>

                    <?php if ($currentImage !== ''): ?>

                        <div class="col-md-12">

                            <label class="form-label">

                                Current Image

                            </label>

                            <div>

                                <img
                                    src="../menu_photos/<?= e($currentImage) ?>"
                                    width="120"
                                    height="120"
                                    class="rounded border"
                                    style="object-fit: cover;"
                                    alt="<?= e(
                                        (string)($item['name'] ?? 'Menu item')
                                    ) ?>">

                            </div>

                            <small class="text-muted">

                                Current file:
                                <?= e($currentImage) ?>

                            </small>

                        </div>

                    <?php endif; ?>

                </div>


                <hr>


                <button
                    type="submit"
                    class="btn btn-maroon">

                    Save Menu Item

                </button>

            </form>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>