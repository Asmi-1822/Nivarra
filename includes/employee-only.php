<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Employee Access Guard
|--------------------------------------------------------------------------
|
| This guard allows both:
|
| - employees
| - managers
|
| to access employee functionality.
|
| A manager is also an employee in the employees table, so managers
| must be able to access their own schedule, leave, attendance,
| evaluations, ratings and notifications.
|
| Manager-only pages must continue to use manager-only.php.
|
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Require Authenticated Employee Session
|--------------------------------------------------------------------------
*/

$employeeId = (int)($_SESSION['employee_id'] ?? 0);

if ($employeeId <= 0) {

    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Employee Exists and Is Active
|--------------------------------------------------------------------------
|
| Do not rely only on $_SESSION['role'].
|
| The employee record is checked against the database so that:
|
| - deleted/non-existent users cannot continue using the session
| - inactive accounts cannot access employee pages
| - managers remain valid employee users
|
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {

    require_once __DIR__ . '/db.php';
}

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        username,
        email,
        role,
        position,
        active
    FROM employees
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $employeeId
]);

$currentEmployee = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Invalid Employee
|--------------------------------------------------------------------------
*/

if (!$currentEmployee) {

    session_unset();
    session_destroy();

    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Inactive Employee
|--------------------------------------------------------------------------
*/

if ((int)($currentEmployee['active'] ?? 0) !== 1) {

    session_unset();
    session_destroy();

    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Role
|--------------------------------------------------------------------------
|
| Both roles are allowed here.
|
|--------------------------------------------------------------------------
*/

$currentRole = strtolower(
    trim((string)($currentEmployee['role'] ?? ''))
);

if (!in_array(
    $currentRole,
    ['employee', 'manager'],
    true
)) {

    session_unset();
    session_destroy();

    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Synchronize Session Information
|--------------------------------------------------------------------------
|
| Keep the session information consistent with the database.
|
|--------------------------------------------------------------------------
*/

$_SESSION['employee_id'] = (int)$currentEmployee['id'];
$_SESSION['employee_name'] = (string)$currentEmployee['full_name'];
$_SESSION['role'] = $currentRole;


/*
|--------------------------------------------------------------------------
| Make Current Employee Available
|--------------------------------------------------------------------------
|
| Employee pages can use:
|
| $currentEmployee['id']
| $currentEmployee['full_name']
| $currentEmployee['role']
| $currentEmployee['position']
|
|--------------------------------------------------------------------------
*/