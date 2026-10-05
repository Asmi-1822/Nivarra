<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Edit Employee';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('employees.php');
}

/*
|--------------------------------------------------------------------------
| Employee
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.*,
        ep.phone
    FROM employees e
    LEFT JOIN employee_profiles ep
        ON ep.employee_id = e.id
    WHERE e.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    flash('error', 'Employee not found.');
    redirect('employees.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $employeeCode = trim($_POST['employee_code'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? 'employee');
    $hireDate = trim($_POST['hire_date'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $newPassword = $_POST['new_password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($employeeCode === '') {
        $errors[] = 'Employee code is required.';
    }

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if (!in_array($role, ['employee', 'manager'], true)) {
        $errors[] = 'Invalid role.';
    }

    if (
        $newPassword !== '' &&
        strlen($newPassword) < 8
    ) {
        $errors[] =
            'New password must be at least 8 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Check
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $check = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE (
                username = ?
                OR email = ?
                OR employee_code = ?
            )
            AND id != ?
            LIMIT 1
        ");

        $check->execute([
            $username,
            $email,
            $employeeCode,
            $id
        ]);

        if ($check->fetch()) {

            $errors[] =
                'Username, email or employee code already exists.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $pdo->beginTransaction();

            $employeeUpdate = $pdo->prepare("
                UPDATE employees
                SET
                    employee_code = ?,
                    full_name = ?,
                    username = ?,
                    email = ?,
                    role = ?,
                    hire_date = ?,
                    active = ?
                WHERE id = ?
            ");

            $employeeUpdate->execute([
                $employeeCode,
                $fullName,
                $username,
                $email,
                $role,
                $hireDate,
                $active,
                $id
            ]);

            /*
             * Optional Password Reset
             */

            if ($newPassword !== '') {

                $passwordHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $pwStmt = $pdo->prepare("
                    UPDATE employees
                    SET password_hash = ?
                    WHERE id = ?
                ");

                $pwStmt->execute([
                    $passwordHash,
                    $id
                ]);
            }

            /*
             * Profile
             */

            $profileExists = $pdo->prepare("
                SELECT id
                FROM employee_profiles
                WHERE employee_id = ?
                LIMIT 1
            ");

            $profileExists->execute([$id]);

            if ($profileExists->fetch()) {

                $profileUpdate = $pdo->prepare("
                    UPDATE employee_profiles
                    SET phone = ?
                    WHERE employee_id = ?
                ");

                $profileUpdate->execute([
                    $phone,
                    $id
                ]);

            } else {

                $profileInsert = $pdo->prepare("
                    INSERT INTO employee_profiles
                    (
                        employee_id,
                        phone
                    )
                    VALUES
                    (
                        ?, ?
                    )
                ");

                $profileInsert->execute([
                    $id,
                    $phone
                ]);
            }

            $pdo->commit();

            flash(
                'success',
                'Employee updated successfully.'
            );

            redirect(
                'employee-details.php?id=' . $id
            );

        } catch (Exception $e) {

            $pdo->rollBack();

            $errors[] =
                'Unable to update employee.';
        }
    }
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>Edit Employee</h2>

        <a
            href="employee-details.php?id=<?= $id ?>"
            class="btn btn-secondary">

            Back

        </a>

    </div>

    <?php if ($errors): ?>

        <div class="alert alert-danger">

            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li><?= e($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-body">

            <form method="POST">

                <?= csrf_input(); ?>

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Employee Code
                        </label>

                        <input
                            type="text"
                            name="employee_code"
                            class="form-control"
                            value="<?= e($employee['employee_code']) ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            value="<?= e($employee['full_name']) ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Username
                        </label>

                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            value="<?= e($employee['username']) ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= e($employee['email']) ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= e($employee['phone'] ?? '') ?>">

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Hire Date
                        </label>

                        <input
                            type="date"
                            name="hire_date"
                            class="form-control"
                            value="<?= e($employee['hire_date']) ?>"
                            required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Role
                        </label>

                        <select
                            name="role"
                            class="form-select">

                            <option
                                value="employee"
                                <?= $employee['role'] === 'employee' ? 'selected' : '' ?>>

                                Employee

                            </option>

                            <option
                                value="manager"
                                <?= $employee['role'] === 'manager' ? 'selected' : '' ?>>

                                Manager

                            </option>

                        </select>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            New Password
                        </label>

                        <input
                            type="password"
                            name="new_password"
                            class="form-control">

                        <small class="text-muted">
                            Leave blank to keep current password.
                        </small>

                    </div>

                    <div class="col-md-12">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="active"
                                id="active"
                                <?= (int)$employee['active'] === 1 ? 'checked' : '' ?>>

                            <label
                                class="form-check-label"
                                for="active">

                                Active Account

                            </label>

                        </div>

                    </div>

                </div>

                <hr>

                <button
                    type="submit"
                    class="btn btn-maroon">

                    Save Changes

                </button>

            </form>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>