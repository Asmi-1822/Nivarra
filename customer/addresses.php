<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer-only.php';

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim(
        (string)($_POST['action'] ?? '')
    );


    /*
    |--------------------------------------------------------------------------
    | Add Address
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        $label = cleanAddressValue(
            $_POST['label'] ?? ''
        );

        $addressLine1 = cleanAddressValue(
            $_POST['address_line_1'] ?? ''
        );

        $addressLine2 = cleanAddressValue(
            $_POST['address_line_2'] ?? ''
        );

        $city = cleanAddressValue(
            $_POST['city'] ?? ''
        );

        $state = cleanAddressValue(
            $_POST['state'] ?? ''
        );

        $postalCode = cleanAddressValue(
            $_POST['postal_code'] ?? ''
        );

        $country = cleanAddressValue(
            $_POST['country'] ?? ''
        );

        $isDefault = isset(
            $_POST['is_default']
        ) ? 1 : 0;


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $errors = [];

        if ($addressLine1 === '') {
            $errors[] = 'Address Line 1 is required.';
        }

        if ($city === '') {
            $errors[] = 'City is required.';
        }

        if ($state === '') {
            $errors[] = 'State is required.';
        }

        if ($postalCode === '') {
            $errors[] = 'Postal Code is required.';
        }

        if ($country === '') {
            $errors[] = 'Country is required.';
        }


        if (empty($errors)) {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | If This Is The First Address
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM customer_addresses
                    WHERE customer_id = ?
                ");

                $stmt->execute([
                    $customerId
                ]);

                $addressCount = (int)$stmt->fetchColumn();


                if ($addressCount === 0) {
                    $isDefault = 1;
                }


                /*
                |--------------------------------------------------------------------------
                | Remove Existing Default
                |--------------------------------------------------------------------------
                */

                if ($isDefault === 1) {

                    $stmt = $pdo->prepare("
                        UPDATE customer_addresses
                        SET is_default = 0
                        WHERE customer_id = ?
                    ");

                    $stmt->execute([
                        $customerId
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Insert Address
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO customer_addresses (
                        customer_id,
                        label,
                        address_line_1,
                        address_line_2,
                        city,
                        state,
                        postal_code,
                        country,
                        is_default
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $customerId,
                    $label !== '' ? $label : null,
                    $addressLine1,
                    $addressLine2 !== ''
                        ? $addressLine2
                        : null,
                    $city,
                    $state,
                    $postalCode,
                    $country,
                    $isDefault
                ]);


                $pdo->commit();

                redirectToAddresses();

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = 'Unable to save the address.';
            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Update Address
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'update') {

        $addressId = (int)(
            $_POST['address_id'] ?? 0
        );

        $label = cleanAddressValue(
            $_POST['label'] ?? ''
        );

        $addressLine1 = cleanAddressValue(
            $_POST['address_line_1'] ?? ''
        );

        $addressLine2 = cleanAddressValue(
            $_POST['address_line_2'] ?? ''
        );

        $city = cleanAddressValue(
            $_POST['city'] ?? ''
        );

        $state = cleanAddressValue(
            $_POST['state'] ?? ''
        );

        $postalCode = cleanAddressValue(
            $_POST['postal_code'] ?? ''
        );

        $country = cleanAddressValue(
            $_POST['country'] ?? ''
        );

        $isDefault = isset(
            $_POST['is_default']
        ) ? 1 : 0;


        $errors = [];

        if ($addressId <= 0) {
            $errors[] = 'Invalid address.';
        }

        if ($addressLine1 === '') {
            $errors[] = 'Address Line 1 is required.';
        }

        if ($city === '') {
            $errors[] = 'City is required.';
        }

        if ($state === '') {
            $errors[] = 'State is required.';
        }

        if ($postalCode === '') {
            $errors[] = 'Postal Code is required.';
        }

        if ($country === '') {
            $errors[] = 'Country is required.';
        }


        /*
        |--------------------------------------------------------------------------
        | Verify Ownership
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $stmt = $pdo->prepare("
                SELECT id
                FROM customer_addresses
                WHERE id = ?
                  AND customer_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $addressId,
                $customerId
            ]);

            if (!$stmt->fetchColumn()) {
                $errors[] = 'Address not found.';
            }
        }


        if (empty($errors)) {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | If Setting As Default
                |--------------------------------------------------------------------------
                */

                if ($isDefault === 1) {

                    $stmt = $pdo->prepare("
                        UPDATE customer_addresses
                        SET is_default = 0
                        WHERE customer_id = ?
                    ");

                    $stmt->execute([
                        $customerId
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Update Address
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE customer_addresses
                    SET
                        label = ?,
                        address_line_1 = ?,
                        address_line_2 = ?,
                        city = ?,
                        state = ?,
                        postal_code = ?,
                        country = ?,
                        is_default = ?
                    WHERE id = ?
                      AND customer_id = ?
                ");

                $stmt->execute([
                    $label !== '' ? $label : null,
                    $addressLine1,
                    $addressLine2 !== ''
                        ? $addressLine2
                        : null,
                    $city,
                    $state,
                    $postalCode,
                    $country,
                    $isDefault,
                    $addressId,
                    $customerId
                ]);


                $pdo->commit();

                redirectToAddresses();

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = 'Unable to update the address.';
            }
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Set Default Address
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'default') {

        $addressId = (int)(
            $_POST['address_id'] ?? 0
        );

        if ($addressId > 0) {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Confirm Ownership
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM customer_addresses
                    WHERE id = ?
                      AND customer_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $addressId,
                    $customerId
                ]);

                if ($stmt->fetchColumn()) {

                    $stmt = $pdo->prepare("
                        UPDATE customer_addresses
                        SET is_default = 0
                        WHERE customer_id = ?
                    ");

                    $stmt->execute([
                        $customerId
                    ]);


                    $stmt = $pdo->prepare("
                        UPDATE customer_addresses
                        SET is_default = 1
                        WHERE id = ?
                          AND customer_id = ?
                    ");

                    $stmt->execute([
                        $addressId,
                        $customerId
                    ]);
                }


                $pdo->commit();

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }

        redirectToAddresses();
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Address
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete') {

        $addressId = (int)(
            $_POST['address_id'] ?? 0
        );

        if ($addressId > 0) {

            $stmt = $pdo->prepare("
                DELETE FROM customer_addresses
                WHERE id = ?
                  AND customer_id = ?
            ");

            $stmt->execute([
                $addressId,
                $customerId
            ]);
        }

        redirectToAddresses();
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Customer Addresses
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        customer_id,
        label,
        address_line_1,
        address_line_2,
        city,
        state,
        postal_code,
        country,
        is_default,
        created_at
    FROM customer_addresses
    WHERE customer_id = ?
    ORDER BY
        is_default DESC,
        created_at DESC
");

$stmt->execute([
    $customerId
]);

$addresses = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

include '../includes/header.php';
include '../includes/customer-navbar.php';
?>

<!-- =========================================================
     Main Content
========================================================= -->

<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-0">
            My Addresses
        </h2>

        <div class="d-flex flex-column align-items-end gap-2">

            <button
                type="button"
                class="btn btn-danger"
                data-bs-toggle="collapse"
                data-bs-target="#addAddressForm">

                Add Address

            </button>


            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">
                Back
            </a>

        </div>

    </div>

    <!-- =====================================================
         Errors
    ====================================================== -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         Add Address
    ====================================================== -->

    <div
        id="addAddressForm"
        class="collapse mb-4">

        <div class="card shadow-sm">

            <div class="card-header bg-white">

                <h5 class="mb-0">
                    Add New Address
                </h5>

            </div>

            <div class="card-body">

                <form
                    method="post"
                    action="addresses.php">

                    <input
                        type="hidden"
                        name="action"
                        value="add">


                    <div class="row g-3">

                        <div class="col-md-4">

                            <label
                                for="add_label"
                                class="form-label">

                                Label

                            </label>

                            <input
                                type="text"
                                id="add_label"
                                name="label"
                                class="form-control"
                                maxlength="100"
                                placeholder="Home, Work, etc.">

                        </div>


                        <div class="col-md-8">

                            <label
                                for="add_address_line_1"
                                class="form-label">

                                Address Line 1
                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                id="add_address_line_1"
                                name="address_line_1"
                                class="form-control"
                                maxlength="255"
                                required>

                        </div>


                        <div class="col-md-12">

                            <label
                                for="add_address_line_2"
                                class="form-label">

                                Address Line 2

                            </label>

                            <input
                                type="text"
                                id="add_address_line_2"
                                name="address_line_2"
                                class="form-control"
                                maxlength="255">

                        </div>


                        <div class="col-md-4">

                            <label
                                for="add_city"
                                class="form-label">

                                City
                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                id="add_city"
                                name="city"
                                class="form-control"
                                maxlength="100"
                                required>

                        </div>


                        <div class="col-md-4">

                            <label
                                for="add_state"
                                class="form-label">

                                State
                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                id="add_state"
                                name="state"
                                class="form-control"
                                maxlength="100"
                                required>

                        </div>


                        <div class="col-md-4">

                            <label
                                for="add_postal_code"
                                class="form-label">

                                Postal Code
                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                id="add_postal_code"
                                name="postal_code"
                                class="form-control"
                                maxlength="30"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label
                                for="add_country"
                                class="form-label">

                                Country
                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                id="add_country"
                                name="country"
                                class="form-control"
                                maxlength="100"
                                value="India"
                                required>

                        </div>


                        <div class="col-md-6 d-flex align-items-end">

                            <div class="form-check mb-2">

                                <input
                                    type="checkbox"
                                    id="add_is_default"
                                    name="is_default"
                                    value="1"
                                    class="form-check-input">

                                <label
                                    for="add_is_default"
                                    class="form-check-label">

                                    Set as default address

                                </label>

                            </div>

                        </div>


                        <div class="col-12">

                            <button
                                type="submit"
                                class="btn btn-danger">

                                Save Address

                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <!-- =====================================================
         Address List
    ====================================================== -->

    <?php if (empty($addresses)): ?>

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <h5 class="mb-2">
                    No Saved Addresses
                </h5>

                <p class="text-muted mb-4">
                    Add an address to make checkout faster.
                </p>

                <button
                    type="button"
                    class="btn btn-danger"
                    data-bs-toggle="collapse"
                    data-bs-target="#addAddressForm">

                    Add Address

                </button>

            </div>

        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($addresses as $address): ?>

                <?php
                $addressId = (int)$address['id'];

                $label = trim(
                    (string)($address['label'] ?? '')
                );

                $addressLine1 = (string)(
                    $address['address_line_1'] ?? ''
                );

                $addressLine2 = trim(
                    (string)($address['address_line_2'] ?? '')
                );

                $city = (string)(
                    $address['city'] ?? ''
                );

                $state = (string)(
                    $address['state'] ?? ''
                );

                $postalCode = (string)(
                    $address['postal_code'] ?? ''
                );

                $country = (string)(
                    $address['country'] ?? ''
                );

                $isDefault = (int)(
                    $address['is_default'] ?? 0
                ) === 1;
                ?>

                <div class="col-md-6">

                    <div class="card shadow-sm address-card">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-start mb-3">

                                <div>

                                    <?php if ($label !== ''): ?>

                                        <div class="address-label">

                                            <?= e($label) ?>

                                        </div>

                                    <?php else: ?>

                                        <div class="address-label">
                                            Address
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <?php if ($isDefault): ?>

                                    <span class="badge bg-success">

                                        Default

                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="mb-3">

                                <div>
                                    <?= e($addressLine1) ?>
                                </div>

                                <?php if ($addressLine2 !== ''): ?>

                                    <div>
                                        <?= e($addressLine2) ?>
                                    </div>

                                <?php endif; ?>

                                <div>
                                    <?= e($city) ?>,
                                    <?= e($state) ?>
                                    <?= e($postalCode) ?>
                                </div>

                                <div>
                                    <?= e($country) ?>
                                </div>

                            </div>


                            <div
                                class="d-flex flex-wrap gap-2">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editAddressModal<?= $addressId ?>">

                                    Edit

                                </button>


                                <?php if (!$isDefault): ?>

                                    <form
                                        method="post"
                                        action="addresses.php"
                                        class="mb-0">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="default">

                                        <input
                                            type="hidden"
                                            name="address_id"
                                            value="<?= $addressId ?>">

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-success">

                                            Set Default

                                        </button>

                                    </form>

                                <?php endif; ?>


                                <form
                                    method="post"
                                    action="addresses.php"
                                    class="mb-0"
                                    onsubmit="return confirm('Delete this address?');">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete">

                                    <input
                                        type="hidden"
                                        name="address_id"
                                        value="<?= $addressId ?>">

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-danger">

                                        Delete

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     Edit Address Modal
                ================================================== -->

                <div
                    class="modal fade"
                    id="editAddressModal<?= $addressId ?>"
                    tabindex="-1"
                    aria-hidden="true">

                    <div class="modal-dialog modal-lg">

                        <div class="modal-content">

                            <div class="modal-header">

                                <h5 class="modal-title">
                                    Edit Address
                                </h5>

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close">
                                </button>

                            </div>


                            <form
                                method="post"
                                action="addresses.php">

                                <div class="modal-body">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update">

                                    <input
                                        type="hidden"
                                        name="address_id"
                                        value="<?= $addressId ?>">


                                    <div class="row g-3">

                                        <div class="col-md-4">

                                            <label
                                                class="form-label">

                                                Label

                                            </label>

                                            <input
                                                type="text"
                                                name="label"
                                                class="form-control"
                                                maxlength="100"
                                                value="<?= e($label) ?>">

                                        </div>


                                        <div class="col-md-8">

                                            <label
                                                class="form-label">

                                                Address Line 1
                                                <span class="text-danger">*</span>

                                            </label>

                                            <input
                                                type="text"
                                                name="address_line_1"
                                                class="form-control"
                                                maxlength="255"
                                                value="<?= e($addressLine1) ?>"
                                                required>

                                        </div>


                                        <div class="col-12">

                                            <label
                                                class="form-label">

                                                Address Line 2

                                            </label>

                                            <input
                                                type="text"
                                                name="address_line_2"
                                                class="form-control"
                                                maxlength="255"
                                                value="<?= e($addressLine2) ?>">

                                        </div>


                                        <div class="col-md-4">

                                            <label
                                                class="form-label">

                                                City
                                                <span class="text-danger">*</span>

                                            </label>

                                            <input
                                                type="text"
                                                name="city"
                                                class="form-control"
                                                maxlength="100"
                                                value="<?= e($city) ?>"
                                                required>

                                        </div>


                                        <div class="col-md-4">

                                            <label
                                                class="form-label">

                                                State
                                                <span class="text-danger">*</span>

                                            </label>

                                            <input
                                                type="text"
                                                name="state"
                                                class="form-control"
                                                maxlength="100"
                                                value="<?= e($state) ?>"
                                                required>

                                        </div>


                                        <div class="col-md-4">

                                            <label
                                                class="form-label">

                                                Postal Code
                                                <span class="text-danger">*</span>

                                            </label>

                                            <input
                                                type="text"
                                                name="postal_code"
                                                class="form-control"
                                                maxlength="30"
                                                value="<?= e($postalCode) ?>"
                                                required>

                                        </div>


                                        <div class="col-md-6">

                                            <label
                                                class="form-label">

                                                Country
                                                <span class="text-danger">*</span>

                                            </label>

                                            <input
                                                type="text"
                                                name="country"
                                                class="form-control"
                                                maxlength="100"
                                                value="<?= e($country) ?>"
                                                required>

                                        </div>


                                        <div class="col-md-6 d-flex align-items-end">

                                            <div class="form-check mb-2">

                                                <input
                                                    type="checkbox"
                                                    name="is_default"
                                                    value="1"
                                                    class="form-check-input"
                                                    id="edit_default_<?= $addressId ?>"
                                                    <?= $isDefault
                                                        ? 'checked'
                                                        : '' ?>>

                                                <label
                                                    class="form-check-label"
                                                    for="edit_default_<?= $addressId ?>">

                                                    Set as default address

                                                </label>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <div class="modal-footer">

                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        data-bs-dismiss="modal">

                                        Cancel

                                    </button>

                                    <button
                                        type="submit"
                                        class="btn btn-danger">

                                        Save Changes

                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


</div>
<?php include '../includes/footer.php'; ?>