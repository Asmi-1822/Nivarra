<?php

    declare(strict_types=1);

    require_once '../includes/config.php';
    require_once '../includes/db.php';
    require_once '../includes/functions.php';
    require_once '../includes/csrf.php';
    require_once '../includes/rate-limit.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        verify_csrf();

        rateLimit(
            'login',
            10,
            300
        );

        $identifier = trim(
            (string)post('identifier')
        );

        $password = (string)post('password');

        /*
        |--------------------------------------------------------------------------
        | Basic Validation
        |--------------------------------------------------------------------------
        */

        if (
            $identifier === '' ||
            $password === ''
        ) {

            flash(
                'error',
                'Invalid credentials.'
            );

            redirect('login.php');
        }

        if (
            strlen($identifier) > 150 ||
            strlen($password) > 255
        ) {

            flash(
                'error',
                'Invalid credentials.'
            );

            redirect('login.php');
        }

        /*
        |--------------------------------------------------------------------------
        | Customer Login
        |--------------------------------------------------------------------------
        */

        $customerStmt = $pdo->prepare("
            SELECT
                id,
                customer_id,
                email,
                username,
                password_hash,
                status
            FROM customers
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ");

        $customerStmt->execute([
            $identifier,
            $identifier
        ]);

        $customer = $customerStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if ($customer) {

            /*
            |--------------------------------------------------------------------------
            | Verify Customer Password
            |--------------------------------------------------------------------------
            */

            if (
                password_verify(
                    $password,
                    (string)$customer['password_hash']
                )
            ) {

                /*
                |--------------------------------------------------------------------------
                | Check Customer Status
                |--------------------------------------------------------------------------
                */

                $customerStatus = strtolower(
                    trim(
                        (string)($customer['status'] ?? '')
                    )
                );

                if ($customerStatus !== 'active') {

                    flash(
                        'error',
                        'Your customer account is not active.'
                    );

                    redirect('login.php');
                }

                /*
                |--------------------------------------------------------------------------
                | Regenerate Session
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                /*
                |--------------------------------------------------------------------------
                | Customer Session
                |--------------------------------------------------------------------------
                */

                $_SESSION['customer_id'] =
                    (int)$customer['id'];

                $_SESSION['customer_code'] =
                    (string)($customer['customer_id'] ?? '');

                $_SESSION['customer_username'] =
                    (string)($customer['username'] ?? '');

                $_SESSION['customer_email'] =
                    (string)($customer['email'] ?? '');

                $_SESSION['last_activity'] =
                    time();

                /*
                |--------------------------------------------------------------------------
                | Update Last Login
                |--------------------------------------------------------------------------
                */

                $updateCustomer = $pdo->prepare("
                    UPDATE customers
                    SET last_login = NOW()
                    WHERE id = ?
                ");

                $updateCustomer->execute([
                    (int)$customer['id']
                ]); 

                /*
                |--------------------------------------------------------------------------
                | Password Rehash
                |--------------------------------------------------------------------------
                */

                if (
                    password_needs_rehash(
                        (string)$customer['password_hash'],
                        PASSWORD_DEFAULT
                    )
                ) {

                    $newHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $rehashStmt = $pdo->prepare("
                        UPDATE customers
                        SET password_hash = ?
                        WHERE id = ?
                    "); 

                    $rehashStmt->execute([
                        $newHash,
                        (int)$customer['id']
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Customer Dashboard
                |--------------------------------------------------------------------------
                */

                redirect(
                    '../customer/dashboard.php'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Employee / Manager Login
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                full_name,
                username,
                email,
                password_hash,
                role,
               active
            FROM employees
            WHERE
                username = ?
                OR email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $identifier,
            $identifier
        ]);

        $user = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        /*
        |--------------------------------------------------------------------------
        | Dummy Password Verification
        | Prevents Username Enumeration
        |--------------------------------------------------------------------------
        */

        $dummyHash =
            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/.AT4JtM6T4eW2';

        if (!$user) {

            password_verify(
                $password,
                $dummyHash
            );

            flash(
                'error',
                'Invalid credentials.'
            );

            redirect('login.php');
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Employee Password
        |--------------------------------------------------------------------------
        */

        if (
            !password_verify(
                $password,
                (string)$user['password_hash']
            )
        ) {

            flash(
                'error',
                'Invalid credentials.'
            );

            redirect('login.php');
        }

        /*
        |--------------------------------------------------------------------------
        | Check Employee Status
        |--------------------------------------------------------------------------
        */

        if (
            isset($user['active']) &&
            (int)$user['active'] !== 1
        ) {

            flash(
                'error',
                'Account disabled.'
            );

            redirect(
                'account-disabled.php'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Regenerate Session
        |--------------------------------------------------------------------------
        */

        session_regenerate_id(true);

        /*
        |--------------------------------------------------------------------------
        | Employee Session
        |--------------------------------------------------------------------------
        */

        $_SESSION['employee_id'] =
            (int)$user['id'];

        $_SESSION['employee_name'] =
            $user['full_name'];

        $_SESSION['username'] =
            $user['username'];

        $_SESSION['role'] =
            $user['role'];

        $_SESSION['last_activity'] =
            time();

        /*
        |--------------------------------------------------------------------------
        | Upgrade Password Hash Automatically
        |--------------------------------------------------------------------------
        */

        if (
            password_needs_rehash(
                (string)$user['password_hash'],
                PASSWORD_DEFAULT
            )
        ) {

            $newHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $update = $pdo->prepare("
                UPDATE employees
                SET password_hash = ?
                WHERE id = ?
            ");

            $update->execute([
                $newHash,
                (int)$user['id']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Employee / Manager Redirect
        |--------------------------------------------------------------------------
        */

        if (
            $user['role'] === 'manager'
        ) {

            redirect(
                '../manager/dashboard.php'
            );

        } elseif (
            $user['role'] === 'employee'
        ) {

            redirect(
            '../employee/dashboard.php'
            );

        } else {

            flash(
                'error',
                'Invalid account role.'
            );

            redirect(
                'login.php'
            );
        }
    }

?>

<!DOCTYPE html>
<html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1">

        <title>NIVARAA Account Login</title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet">

        <link
            rel="stylesheet"
            href="../assets/css/nivaraa.css">

    </head>

    <body class="login-bg">

        <div class="container">

            <div class="row justify-content-center">

                <div class="col-lg-5 col-md-7">

                    <div class="card shadow-lg border-0 mt-5">

                        <div class="card-body p-4">

                            <div class="text-center mb-4">

                                <img
                                    src="../assets/images/logo.png"
                                    alt="NIVARAA"
                                    class="logo-login">

                                <h3 class="mt-3">
                                    NIVARAA Account Login
                                </h3>

                                <p class="text-muted">
                                    Employee • Manager • Customer
                                </p>

                            </div>

                            <?php if ($msg = flashMessage('error')): ?>

                                <div class="alert alert-danger">

                                    <?= e($msg) ?>

                                </div>

                            <?php endif; ?>

                            <form method="POST">

                                <?= csrf_input(); ?>

                                <div class="mb-3">

                                    <label class="form-label">
                                        Username or Email
                                    </label>

                                    <input
                                        type="text"
                                        name="identifier"
                                        class="form-control"
                                        maxlength="150"
                                        required>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">
                                        Password
                                    </label>

                                    <input
                                        type="password"
                                        name="password"
                                        class="form-control"
                                        maxlength="255"
                                        required>

                                </div>

                                <div class="d-flex justify-content-between mb-3">

                                    <a
                                        href="../auth/forgot-password.php"
                                        class="text-decoration-none">
                                        Forgot Password?
                                    </a>

                                    <a
                                        href="../auth/register.php"
                                        class="text-decoration-none">
                                        Create Account
                                    </a>

                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-maroon w-100">
                                    Login
                                </button>

                            </form>

                            <hr>

                            <div class="text-center">

                                <a
                                    href="../index.php"
                                    class="text-decoration-none">
                                        ← Back to Website
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </body>

</html>
```
