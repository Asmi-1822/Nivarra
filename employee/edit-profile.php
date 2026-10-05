<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';
require_once '../includes/csrf.php';

$employeeId = (int)$_SESSION['employee_id'];

/*
|--------------------------------------------------------------------------
| Update Profile
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_profile'])
)
{
    verify_csrf();

    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergencyContact =
        trim($_POST['emergency_contact'] ?? '');

    $stmt = $pdo->prepare("
        SELECT id
        FROM employee_profiles
        WHERE employee_id = ?
        LIMIT 1
    ");

    $stmt->execute([$employeeId]);

    $profileExists = $stmt->fetch();

    if ($profileExists) {

        $stmt = $pdo->prepare("
            UPDATE employee_profiles
            SET
                phone = ?,
                address = ?,
                emergency_contact = ?
            WHERE employee_id = ?
        ");

        $stmt->execute([
            $phone,
            $address,
            $emergencyContact,
            $employeeId
        ]);

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO employee_profiles
            (
                employee_id,
                phone,
                address,
                emergency_contact
            )
            VALUES
            (
                ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $employeeId,
            $phone,
            $address,
            $emergencyContact
        ]);
    }

    flash(
        'success',
        'Profile updated successfully.'
    );

    redirect('edit-profile.php');
}

/*
|--------------------------------------------------------------------------
| Employee Data
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.*,
        ep.phone,
        ep.address,
        ep.emergency_contact
    FROM employees e

    LEFT JOIN employee_profiles ep
        ON ep.employee_id = e.id

    WHERE e.id = ?
    LIMIT 1
");

$stmt->execute([$employeeId]);

$employee = $stmt->fetch();

$pageTitle = 'Edit Profile';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="mb-4">

                Edit Profile

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

    <?php if($msg = flashMessage('success')): ?>

        <div class="alert alert-success">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-body">

            <form method="POST">

                <?= csrf_input() ?>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Full Name

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= e($employee['full_name']) ?>"
                            disabled>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Email

                        </label>

                        <input
                            type="email"
                            class="form-control"
                            value="<?= e($employee['email']) ?>"
                            disabled>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Phone Number

                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= e($employee['phone'] ?? '') ?>">

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">

                            Emergency Contact

                        </label>

                        <input
                            type="text"
                            name="emergency_contact"
                            class="form-control"
                            value="<?= e($employee['emergency_contact'] ?? '') ?>">

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="form-label">

                            Address

                        </label>

                        <textarea
                            name="address"
                            rows="4"
                            class="form-control"><?= e($employee['address'] ?? '') ?></textarea>

                    </div>

                </div>

                <button
                    type="submit"
                    name="update_profile"
                    class="btn btn-primary">

                    Save Changes

                </button>

            </form>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>