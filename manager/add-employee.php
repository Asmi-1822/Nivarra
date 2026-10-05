<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Add Employee';

$errors = [];

/*
|--------------------------------------------------------------------------
| Position List
|--------------------------------------------------------------------------
*/

$positions = [
    'chef' => 'Chef',
    'waiter' => 'Waiter',
    'cashier' => 'Cashier',
    'kitchen_staff' => 'Kitchen Staff',
    'restaurant_staff' => 'Restaurant Staff'
];

/*
|--------------------------------------------------------------------------
| Role List
|--------------------------------------------------------------------------
*/

$roles = [
    'employee' => 'Employee',
    'manager' => 'Manager'
];

/*
|--------------------------------------------------------------------------
| Form Defaults
|--------------------------------------------------------------------------
*/

$position = trim(
    $_POST['position'] ?? ''
);

$fullName = trim(
    $_POST['full_name'] ?? ''
);

$username = trim(
    $_POST['username'] ?? ''
);

$email = trim(
    $_POST['email'] ?? ''
);

$phone = trim(
    $_POST['phone'] ?? ''
);

$role = trim(
    $_POST['role'] ?? 'employee'
);

$hireDate = trim(
    $_POST['hire_date'] ?? ''
);

$password = $_POST['password'] ?? '';

/*
|--------------------------------------------------------------------------
| Employee Code Preview
|--------------------------------------------------------------------------
|
| This is only a preview.
| The actual employee code is generated again during submission.
|--------------------------------------------------------------------------
*/

$employeeCodePreview = '';

if (
    in_array($role, ['employee', 'manager'], true)
) {

    try {

        $employeeCodePreview =
            generateEmployeeCode(
                $pdo,
                $role
            );

    } catch (Throwable $e) {

        $employeeCodePreview = '';
    }
}

/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    /*
    |--------------------------------------------------------------------------
    | Validate Position
    |--------------------------------------------------------------------------
    */

    if (
        $position === ''
        || !array_key_exists(
            $position,
            $positions
        )
    ) {

        $errors[] =
            'Please select a valid position.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Full Name
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {

        $errors[] =
            'Full name is required.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Username
    |--------------------------------------------------------------------------
    */

    if ($username === '') {

        $errors[] =
            'Username is required.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Email
    |--------------------------------------------------------------------------
    */

    if (!filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {

        $errors[] =
            'Valid email is required.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Role
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $role,
            ['employee', 'manager'],
            true
        )
    ) {

        $errors[] =
            'Invalid role selected.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Password
    |--------------------------------------------------------------------------
    */

    if (strlen($password) < 8) {

        $errors[] =
            'Password must contain at least 8 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Hire Date
    |--------------------------------------------------------------------------
    */

    if ($hireDate === '') {

        $errors[] =
            'Hire date is required.';
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Employee Code
    |--------------------------------------------------------------------------
    */

    $employeeCode = '';

    if (!$errors) {

        try {

            $employeeCode =
                generateEmployeeCode(
                    $pdo,
                    $role
                );

        } catch (Throwable $e) {

            $errors[] =
                'Unable to generate employee code.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Username / Email / Employee Code
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $check = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE username = ?
               OR email = ?
               OR employee_code = ?
            LIMIT 1
        ");

        $check->execute([
            $username,
            $email,
            $employeeCode
        ]);

        if ($check->fetch()) {

            $errors[] =
                'Username, email or generated employee code already exists.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert Employee
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Password Hash
            |--------------------------------------------------------------------------
            */

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            /*
            |--------------------------------------------------------------------------
            | Insert Employee
            |--------------------------------------------------------------------------
            |
            | position and role are both stored here.
            |--------------------------------------------------------------------------
            */

            $employeeStmt = $pdo->prepare("
                INSERT INTO employees
                (
                    employee_code,
                    full_name,
                    username,
                    email,
                    phone,
                    password_hash,
                    position,
                    role,
                    hire_date,
                    active
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
                )
            ");

            $employeeStmt->execute([
                $employeeCode,
                $fullName,
                $username,
                $email,
                $phone,
                $passwordHash,
                $position,
                $role,
                $hireDate
            ]);

            /*
            |--------------------------------------------------------------------------
            | Get New Employee ID
            |--------------------------------------------------------------------------
            */

            $employeeId =
                (int)$pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create Employee Profile
            |--------------------------------------------------------------------------
            */

            $profileStmt = $pdo->prepare("
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

            $profileStmt->execute([
                $employeeId,
                $phone !== ''
                    ? $phone
                    : null
            ]);

            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            flash(
                'success',
                'Employee created successfully. Employee code: '
                . $employeeCode
            );

            redirect('employees.php');

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }

            $errors[] =
                'Unable to create employee.';
        }
    }
}

include '../includes/header.php';
include '../includes/manager-navbar.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>
            Add Employee
        </h2>

        <a
            href="employees.php"
            class="btn btn-secondary">

            Back

        </a>

    </div>


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


    <div class="card shadow-sm">

        <div class="card-body">

            <form
                method="POST"
                autocomplete="off">

                <?= csrf_input(); ?>


                <div class="row g-3">


                    <!-- Position -->

                    <div class="col-md-6">

                        <label
                            for="position"
                            class="form-label">

                            Position

                        </label>

                        <select
                            id="position"
                            name="position"
                            class="form-select"
                            required>

                            <option value="">
                                Select Position
                            </option>

                            <?php foreach (
                                $positions
                                as $value => $label
                            ): ?>

                                <option
                                    value="<?= e($value) ?>"
                                    <?= $position === $value
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($label) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Role -->

                    <div class="col-md-6">

                        <label
                            for="role"
                            class="form-label">

                            Role

                        </label>

                        <select
                            id="role"
                            name="role"
                            class="form-select"
                            required>

                            <?php foreach (
                                $roles
                                as $value => $label
                            ): ?>

                                <option
                                    value="<?= e($value) ?>"
                                    <?= $role === $value
                                        ? 'selected'
                                        : '' ?>>

                                    <?= e($label) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Employee Code -->

                    <div class="col-md-6">

                        <label
                            for="employee_code"
                            class="form-label">

                            Employee Code

                        </label>

                        <input
                            type="text"
                            id="employee_code"
                            class="form-control"
                            value="<?= e(
                                $employeeCodePreview
                            ) ?>"
                            readonly>

                        <div class="form-text">

                            Generated automatically from
                            the selected role.

                            <br>

                            Employee:
                            <strong>EMP___</strong>

                            &nbsp; | &nbsp;

                            Manager:
                            <strong>MGR___</strong>

                        </div>

                    </div>


                    <!-- Full Name -->

                    <div class="col-md-6">

                        <label
                            for="full_name"
                            class="form-label">

                            Full Name

                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-control"
                            value="<?= e($fullName) ?>"
                            required>

                    </div>


                    <!-- Username -->

                    <div class="col-md-6">

                        <label
                            for="username"
                            class="form-label">

                            Username

                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            value="<?= e($username) ?>"
                            required>

                    </div>


                    <!-- Email -->

                    <div class="col-md-6">

                        <label
                            for="email"
                            class="form-label">

                            Email

                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="<?= e($email) ?>"
                            required>

                    </div>


                    <!-- Phone -->

                    <div class="col-md-6">

                        <label
                            for="phone"
                            class="form-label">

                            Phone

                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            class="form-control"
                            value="<?= e($phone) ?>">

                    </div>


                    <!-- Hire Date -->

                    <div class="col-md-6">

                        <label
                            for="hire_date"
                            class="form-label">

                            Hire Date

                        </label>

                        <input
                            type="date"
                            id="hire_date"
                            name="hire_date"
                            class="form-control"
                            value="<?= e($hireDate) ?>"
                            required>

                    </div>


                    <!-- Password -->

                    <div class="col-md-6">

                        <label
                            for="password"
                            class="form-label">

                            Password

                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            required>

                        <div class="form-text">

                            Minimum 8 characters.

                        </div>

                    </div>

                </div>


                <hr class="my-4">


                <button
                    type="submit"
                    class="btn btn-maroon">

                    Create Employee

                </button>


                <a
                    href="employees.php"
                    class="btn btn-secondary">

                    Cancel

                </a>

            </form>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const role =
            document.getElementById(
                'role'
            );

        const employeeCode =
            document.getElementById(
                'employee_code'
            );

        function updateEmployeeCodePreview() {

            if (role.value === 'manager') {

                employeeCode.value = 'MGR___';

            } else if (
                role.value === 'employee'
            ) {

                employeeCode.value = 'EMP___';

            } else {

                employeeCode.value = '';

            }
        }

        role.addEventListener(
            'change',
            updateEmployeeCodePreview
        );

        updateEmployeeCodePreview();

    }
);

</script>


<?php include '../includes/footer.php'; ?>