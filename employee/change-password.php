<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/employee-only.php';
require_once '../includes/csrf.php';

$employeeId = (int)$_SESSION['employee_id'];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['change_password'])
) {
    verify_csrf();

    $currentPassword =
        trim($_POST['current_password'] ?? '');

    $newPassword =
        trim($_POST['new_password'] ?? '');

    $confirmPassword =
        trim($_POST['confirm_password'] ?? '');

    if (
        empty($currentPassword) ||
        empty($newPassword) ||
        empty($confirmPassword)
    ) {
        flash(
            'error',
            'All fields are required.'
        );

        redirect('change-password.php');
    }

    if ($newPassword !== $confirmPassword) {

        flash(
            'error',
            'New passwords do not match.'
        );

        redirect('change-password.php');
    }

    if (strlen($newPassword) < 8) {

        flash(
            'error',
            'Password must be at least 8 characters.'
        );

        redirect('change-password.php');
    }

    $stmt = $pdo->prepare("
        SELECT password_hash
        FROM employees
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$employeeId]);

    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$employee ||
        !password_verify(
            $currentPassword,
            $employee['password_hash']
        )
    ) {
        flash(
            'error',
            'Current password is incorrect.'
        );

        redirect('change-password.php');
    }

    $newHash =
        password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

    $stmt = $pdo->prepare("
        UPDATE employees
        SET password_hash = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $newHash,
        $employeeId
    ]);

    /*
    Optional Activity Log

    $stmt = $pdo->prepare("
        INSERT INTO employee_activity_logs
        (
            employee_id,
            action,
            description,
            ip_address
        )
        VALUES
        (
            ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $employeeId,
        'password_change',
        'Employee changed password',
        $_SERVER['REMOTE_ADDR']
    ]);
    */

    flash(
        'success',
        'Password updated successfully.'
    );

    redirect('change-password.php');
}

$pageTitle = 'Change Password';

include '../includes/header.php';
include '../includes/employee-navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="mb-4">
            
                Change Password

            </h2>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="profile.php"
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

    <?php if($msg = flashMessage('error')): ?>

        <div class="alert alert-danger">

            <?= e($msg) ?>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-body">

            <form method="POST">

                <?= csrf_input() ?>

                <div class="mb-3">

                    <label class="form-label">
                        Current Password
                    </label>

                    <input
                        type="password"
                        name="current_password"
                        class="form-control"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        New Password
                    </label>

                    <input
                        type="password"
                        name="new_password"
                        class="form-control"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        name="confirm_password"
                        class="form-control"
                        required>

                </div>

                <button
                    type="submit"
                    name="change_password"
                    class="btn btn-primary">

                    Change Password

                </button>

            </form>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>