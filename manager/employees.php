<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Employee Management';

/*
|--------------------------------------------------------------------------
| Activate / Deactivate Employee
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $employeeId = (int)($_POST['employee_id'] ?? 0);

    if (isset($_POST['toggle_status'])) {

        $stmt = $pdo->prepare("
            UPDATE employees
            SET active =
                CASE
                    WHEN active = 1 THEN 0
                    ELSE 1
                END
            WHERE id = ?
        ");

        $stmt->execute([$employeeId]);

        flash(
            'success',
            'Employee status updated.'
        );

        redirect('employees.php');
    }
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];

if ($search !== '') {

    $where = "
        WHERE
            full_name LIKE ?
            OR username LIKE ?
            OR email LIKE ?
    ";

    $term = '%' . $search . '%';

    $params = [
        $term,
        $term,
        $term
    ];
}

/*
|--------------------------------------------------------------------------
| Total Rows
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM employees
    $where
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);

$totalRows = (int)$countStmt->fetchColumn();

$totalPages = max(
    1,
    (int)ceil(
        $totalRows / $perPage
    )
);

/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        employee_code,
        full_name,
        username,
        email,
        role,
        active,
        hire_date
    FROM employees
    $where
    ORDER BY full_name ASC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$employees = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between mb-4">

        <div>

            <h2 class="fw-bold">
                Employee Management
            </h2>

            <p class="text-muted mb-0">
                Manage staff accounts
            </p>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="add-employee.php"
                class="btn btn-maroon">

                Add Employee

            </a>

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

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-lg-10">

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            class="form-control"
                            placeholder="Search employee...">

                    </div>

                    <div class="col-lg-2">

                        <button
                            class="btn btn-maroon w-100">

                            Search

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th>Code</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th>Actions</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach($employees as $employee): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $employee['employee_code']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $employee['full_name']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $employee['username']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $employee['email']
                                ) ?>

                            </td>

                            <td>

                                <?php if(
                                    $employee['role']
                                    === 'manager'
                                ): ?>

                                    <span
                                        class="badge bg-danger">

                                        Manager

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge bg-primary">

                                        Employee

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if(
                                    (int)$employee['active'] === 1
                                ): ?>

                                    <span
                                        class="badge bg-success">

                                        Active

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge bg-secondary">

                                        Disabled

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= e(
                                    $employee['hire_date']
                                ) ?>

                            </td>

                            <td>

                               <div class="d-flex gap-2">

                                    <div class="buttonform">
                                        <a href="employee-details.php?id=<?= (int)$employee['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </div>

                                    <div class="buttonform">
                                        <a href="edit-employee.php?id=<?= (int)$employee['id'] ?>"
                                           class="btn btn-sm btn-outline-warning">
                                            Edit
                                        </a>
                                    </div>

                                    <div class="buttonform">
                                        <form method="POST">

                                            <?= csrf_input() ?>

                                            <input
                                                type="hidden"
                                                name="employee_id"
                                                value="<?= (int)$employee['id'] ?>">

                                            <button
                                                type="submit"
                                                name="toggle_status"
                                                class="btn btn-sm btn-outline-danger">

                                                <?= (int)$employee['active'] === 1
                                                    ? 'Disable'
                                                    : 'Enable' ?>

                                            </button>

                                        </form>

                                    </div>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>