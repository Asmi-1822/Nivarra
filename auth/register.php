<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

$pageTitle = 'Create Customer Account';

$errors = [];

$firstName = '';
$lastName = '';
$username = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must contain 3-50 letters, numbers, or underscores.';
    }

    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    /*
    |--------------------------------------------------------------------------
    | Check Existing Customer
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                email
            FROM customers
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $username,
            $email
        ]);

        $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCustomer) {

            if (
                strtolower((string)$existingCustomer['username'])
                === strtolower($username)
            ) {
                $errors[] = 'That username is already registered.';
            }

            if (
                strtolower((string)$existingCustomer['email'])
                === strtolower($email)
            ) {
                $errors[] = 'That email address is already registered.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Customer Account
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Generate Customer Code
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->query("
                SELECT customer_id
                FROM customers
                ORDER BY id DESC
                LIMIT 1
            ");

            $lastCustomerId = $stmt->fetchColumn();

            $nextNumber = 1;

            if (
                is_string($lastCustomerId)
                && preg_match(
                    '/^CUST(\d+)$/i',
                    $lastCustomerId,
                    $matches
                )
            ) {
                $nextNumber = ((int)$matches[1]) + 1;
            }

            $customerCode = 'CUST' . str_pad(
                (string)$nextNumber,
                6,
                '0',
                STR_PAD_LEFT
            );

            /*
            |--------------------------------------------------------------------------
            | Insert Customer
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO customers (
                    customer_id,
                    email,
                    username,
                    password_hash,
                    email_verified,
                    status
                )
                VALUES (?, ?, ?, ?, 0, 'active')
            ");

            $stmt->execute([
                $customerCode,
                $email,
                $username,
                $passwordHash
            ]);

            $newCustomerId = (int)$pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Customer Profile
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO customer_profiles (
                    customer_id,
                    first_name,
                    last_name,
                    phone
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $newCustomerId,
                $firstName,
                $lastName,
                $phone !== '' ? $phone : null
            ]);

            /*
            |--------------------------------------------------------------------------
            | Customer Preferences
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO customer_preferences (
                    customer_id
                )
                VALUES (?)
            ");

            $stmt->execute([
                $newCustomerId
            ]);

            /*
            |--------------------------------------------------------------------------
            | Loyalty Account
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO loyalty_accounts (
                    customer_id,
                    points_balance,
                    tier
                )
                VALUES (?, 0, 'bronze')
            ");

            $stmt->execute([
                $newCustomerId
            ]);

            $pdo->commit();

            header('Location: login.php?registered=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Unable to create your account right now. Please try again.';
        }
    }
}

include '../includes/header.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-12 col-md-8 col-lg-6">

            <div class="card shadow-sm">

                <div class="card-body p-4 p-md-5">

                    <div class="text-center mb-4">

                        <img
                            src="../assets/images/logo.png"
                            alt="NIVARRA"
                            height="55"
                            class="mb-3">

                        <h2 class="mb-1">
                            Create Account
                        </h2>

                        <p class="text-muted mb-0">
                            Create your NIVARRA customer account
                        </p>

                    </div>

                    <?php if ($errors): ?>

                        <div class="alert alert-danger">

                            <ul class="mb-0">

                                <?php foreach ($errors as $error): ?>

                                    <li>
                                        <?= htmlspecialchars(
                                            $error,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>

                    <form
                        method="POST"
                        action="register.php"
                        novalidate>

                        <?= csrf_input() ?>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label
                                    for="first_name"
                                    class="form-label">
                                    First Name
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="first_name"
                                    name="first_name"
                                    value="<?= htmlspecialchars(
                                        $firstName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label
                                    for="last_name"
                                    class="form-label">
                                    Last Name
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="last_name"
                                    name="last_name"
                                    value="<?= htmlspecialchars(
                                        $lastName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label
                                for="email"
                                class="form-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars(
                                    $email,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required>

                        </div>

                        <div class="mb-3">

                            <label
                                for="phone"
                                class="form-label">
                                Phone Number
                            </label>

                            <input
                                type="tel"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars(
                                    $phone,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">

                        </div>

                        <div class="mb-3">

                            <label
                                for="username"
                                class="form-label">
                                Username
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="username"
                                name="username"
                                value="<?= htmlspecialchars(
                                    $username,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                autocomplete="username"
                                required>

                            <div class="form-text">
                                3-50 letters, numbers, or underscores.
                            </div>

                        </div>

                        <div class="mb-3">

                            <label
                                for="password"
                                class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                required>

                            <div class="form-text">
                                Minimum 8 characters.
                            </div>

                        </div>

                        <div class="mb-4">

                            <label
                                for="confirm_password"
                                class="form-label">
                                Confirm Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                required>

                        </div>

                        <div class="d-grid">

                            <button
                                type="submit"
                                class="btn btn-maroon">
                                Create Account
                            </button>

                        </div>

                    </form>

                    <div class="text-center mt-4">

                        <span class="text-muted">
                            Already have an account?
                        </span>

                        <a href="login.php">
                            Login
                        </a>

                    </div>

                    <div class="text-center mt-3">

                        <a
                            href="../index.php"
                            class="text-decoration-none">
                            Back to Home
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>