<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer-only.php';

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Load current preferences
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        vegetarian,
        vegan,
        gluten_free,
        halal,
        allergies,
        email_notifications,
        sms_notifications,
        marketing_opt_in
    FROM customer_preferences
    WHERE customer_id = ?
    LIMIT 1
");

$stmt->execute([$customerId]);

$preferences = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Default values
|--------------------------------------------------------------------------
*/

if (!$preferences) {
    $preferences = [
        'id' => null,
        'vegetarian' => 0,
        'vegan' => 0,
        'gluten_free' => 0,
        'halal' => 0,
        'allergies' => '',
        'email_notifications' => 1,
        'sms_notifications' => 0,
        'marketing_opt_in' => 0
    ];
}

/*
|--------------------------------------------------------------------------
| Handle form submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($csrfToken)) {

        $errorMessage = 'Invalid security token. Please try again.';

    } else {

        $vegetarian = isset($_POST['vegetarian']) ? 1 : 0;
        $vegan = isset($_POST['vegan']) ? 1 : 0;
        $glutenFree = isset($_POST['gluten_free']) ? 1 : 0;
        $halal = isset($_POST['halal']) ? 1 : 0;

        $allergies = trim(
            (string)($_POST['allergies'] ?? '')
        );

        $emailNotifications = isset($_POST['email_notifications'])
            ? 1
            : 0;

        $smsNotifications = isset($_POST['sms_notifications'])
            ? 1
            : 0;

        $marketingOptIn = isset($_POST['marketing_opt_in'])
            ? 1
            : 0;

        try {

            /*
            |--------------------------------------------------------------------------
            | Check whether preferences already exist
            |--------------------------------------------------------------------------
            */

            $checkStmt = $pdo->prepare("
                SELECT id
                FROM customer_preferences
                WHERE customer_id = ?
                LIMIT 1
            ");

            $checkStmt->execute([$customerId]);

            $existingId = $checkStmt->fetchColumn();

            /*
            |--------------------------------------------------------------------------
            | Update existing preferences
            |--------------------------------------------------------------------------
            */

            if ($existingId !== false) {

                $updateStmt = $pdo->prepare("
                    UPDATE customer_preferences
                    SET
                        vegetarian = ?,
                        vegan = ?,
                        gluten_free = ?,
                        halal = ?,
                        allergies = ?,
                        email_notifications = ?,
                        sms_notifications = ?,
                        marketing_opt_in = ?
                    WHERE customer_id = ?
                ");

                $updateStmt->execute([
                    $vegetarian,
                    $vegan,
                    $glutenFree,
                    $halal,
                    $allergies,
                    $emailNotifications,
                    $smsNotifications,
                    $marketingOptIn,
                    $customerId
                ]);

            /*
            |--------------------------------------------------------------------------
            | Create preferences if they do not exist
            |--------------------------------------------------------------------------
            */

            } else {

                $insertStmt = $pdo->prepare("
                    INSERT INTO customer_preferences (
                        customer_id,
                        vegetarian,
                        vegan,
                        gluten_free,
                        halal,
                        allergies,
                        email_notifications,
                        sms_notifications,
                        marketing_opt_in
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $insertStmt->execute([
                    $customerId,
                    $vegetarian,
                    $vegan,
                    $glutenFree,
                    $halal,
                    $allergies,
                    $emailNotifications,
                    $smsNotifications,
                    $marketingOptIn
                ]);
            }

            $successMessage = 'Your preferences have been saved successfully.';

            /*
            |--------------------------------------------------------------------------
            | Refresh displayed values
            |--------------------------------------------------------------------------
            */

            $preferences['vegetarian'] = $vegetarian;
            $preferences['vegan'] = $vegan;
            $preferences['gluten_free'] = $glutenFree;
            $preferences['halal'] = $halal;
            $preferences['allergies'] = $allergies;
            $preferences['email_notifications'] = $emailNotifications;
            $preferences['sms_notifications'] = $smsNotifications;
            $preferences['marketing_opt_in'] = $marketingOptIn;

        } catch (PDOException $e) {

            $errorMessage = 'Unable to save your preferences. Please try again.';
        }
    }
}

include '../includes/header.php';
include '../includes/customer-navbar.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-0">
                
                Preferences

            </h2>

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


    <?php if ($successMessage !== ''): ?>

        <div class="alert alert-success">

            <?= e($successMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">

            <?= e($errorMessage) ?>

        </div>

    <?php endif; ?>


    <div class="card shadow-sm">

        <div class="card-header bg-white">

            <h5 class="mb-0">
                Food & Communication Preferences
            </h5>

        </div>

        <div class="card-body">

            <form
                method="POST"
                action="preferences.php">

                <?= csrf_input() ?>


                <!-- Dietary Preferences -->

                <h5 class="mb-3">
                    Dietary Preferences
                </h5>

                <div class="row g-3 mb-4">

                    <div class="col-md-6">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="vegetarian"
                                id="vegetarian"
                                value="1"
                                <?= (int)$preferences['vegetarian'] === 1 ? 'checked' : '' ?>>

                            <label
                                class="form-check-label"
                                for="vegetarian">

                                Vegetarian

                            </label>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="vegan"
                                id="vegan"
                                value="1"
                                <?= (int)$preferences['vegan'] === 1 ? 'checked' : '' ?>>

                            <label
                                class="form-check-label"
                                for="vegan">

                                Vegan

                            </label>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="gluten_free"
                                id="gluten_free"
                                value="1"
                                <?= (int)$preferences['gluten_free'] === 1 ? 'checked' : '' ?>>

                            <label
                                class="form-check-label"
                                for="gluten_free">

                                Gluten Free

                            </label>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="halal"
                                id="halal"
                                value="1"
                                <?= (int)$preferences['halal'] === 1 ? 'checked' : '' ?>>

                            <label
                                class="form-check-label"
                                for="halal">

                                Halal

                            </label>

                        </div>

                    </div>

                </div>


                <!-- Allergies -->

                <h5 class="mb-3">
                    Allergies
                </h5>

                <div class="mb-4">

                    <label
                        for="allergies"
                        class="form-label">

                        Allergies or Dietary Notes

                    </label>

                    <textarea
                        class="form-control"
                        name="allergies"
                        id="allergies"
                        rows="4"
                        maxlength="1000"
                        placeholder="Example: peanuts, shellfish, lactose"><?= e((string)$preferences['allergies']) ?></textarea>

                    <div class="form-text">

                        Enter any food allergies or dietary information that may be important when ordering.

                    </div>

                </div>


                <!-- Notifications -->

                <h5 class="mb-3">
                    Communication Preferences
                </h5>

                <div class="mb-4">

                    <div class="form-check mb-3">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="email_notifications"
                            id="email_notifications"
                            value="1"
                            <?= (int)$preferences['email_notifications'] === 1 ? 'checked' : '' ?>>

                        <label
                            class="form-check-label"
                            for="email_notifications">

                            Receive email notifications

                        </label>

                    </div>


                    <div class="form-check mb-3">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="sms_notifications"
                            id="sms_notifications"
                            value="1"
                            <?= (int)$preferences['sms_notifications'] === 1 ? 'checked' : '' ?>>

                        <label
                            class="form-check-label"
                            for="sms_notifications">

                            Receive SMS notifications

                        </label>

                    </div>


                    <div class="form-check">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="marketing_opt_in"
                            id="marketing_opt_in"
                            value="1"
                            <?= (int)$preferences['marketing_opt_in'] === 1 ? 'checked' : '' ?>>

                        <label
                            class="form-check-label"
                            for="marketing_opt_in">

                            Receive promotional and marketing communications

                        </label>

                    </div>

                </div>


                <div class="d-flex justify-content-end">

                    <button
                        type="submit"
                        class="btn btn-maroon">

                        Save Preferences

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>