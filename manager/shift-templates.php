<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Shift Templates';

$errors = [];

/*
|--------------------------------------------------------------------------
| Create / Delete / Toggle Template
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        $templateName = trim(
            $_POST['template_name'] ?? ''
        );

        $shiftType = trim(
            $_POST['shift_type'] ?? ''
        );

        $startTime = trim(
            $_POST['start_time'] ?? ''
        );

        $endTime = trim(
            $_POST['end_time'] ?? ''
        );

        $notes = trim(
            $_POST['notes'] ?? ''
        );

        if ($templateName === '') {
            $errors[] = 'Template name required.';
        }

        $allowedTypes = [
            'morning',
            'afternoon',
            'evening',
            'full_day'
        ];

        if (!in_array($shiftType, $allowedTypes, true)) {
            $errors[] = 'Invalid shift type.';
        }

        if (
            $startTime !== '' &&
            $endTime !== '' &&
            strtotime($startTime) >= strtotime($endTime)
        ) {
            $errors[] = 'End time must be after start time.';
        }

        if (!$errors) {

            $stmt = $pdo->prepare("
                INSERT INTO shift_templates (
                    template_name,
                    shift_type,
                    start_time,
                    end_time,
                    notes,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $templateName,
                $shiftType,
                $startTime,
                $endTime,
                $notes,
                $_SESSION['employee_id']
            ]);

            if (function_exists('auditLog')) {
                auditLog(
                    $_SESSION['employee_id'],
                    'shift_template_created',
                    $templateName
                );
            }

            flash(
                'success',
                'Template created.'
            );

            redirect('shift-templates.php');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        $templateId = (int)(
            $_POST['template_id'] ?? 0
        );

        if ($templateId > 0) {

            $stmt = $pdo->prepare("
                DELETE FROM shift_templates
                WHERE id = ?
            ");

            $stmt->execute([
                $templateId
            ]);

            flash(
                'success',
                'Template deleted.'
            );

            redirect('shift-templates.php');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Active
    |--------------------------------------------------------------------------
    */

    if ($action === 'toggle') {

        $templateId = (int)(
            $_POST['template_id'] ?? 0
        );

        if ($templateId > 0) {

            $stmt = $pdo->prepare("
                UPDATE shift_templates
                SET active = IF(active = 1, 0, 1)
                WHERE id = ?
            ");

            $stmt->execute([
                $templateId
            ]);

            flash(
                'success',
                'Template updated.'
            );

            redirect('shift-templates.php');
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Templates
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        st.*,
        e.full_name
    FROM shift_templates st
    INNER JOIN employees e
        ON e.id = st.created_by
    ORDER BY st.template_name
");

$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="nivarra-title mb-1">
                Shift Templates
            </h2>

            <p class="text-muted mb-0">
                Create and manage reusable employee shift templates.
            </p>
        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">
            Back
        </a>

    </div>


    <div class="row g-4">

        <!-- Create Template -->

        <div class="col-lg-4">

            <div class="card nivarra-card h-100">

                <div class="nivarra-card-header">
                    Create Template
                </div>

                <div class="card-body">

                    <?php if ($errors): ?>

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


                    <form method="POST">

                        <?= csrf_input() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="create">


                        <!-- Template Name -->

                        <div class="mb-3">

                            <label
                                for="template_name"
                                class="form-label">

                                Template Name

                            </label>

                            <input
                                type="text"
                                id="template_name"
                                name="template_name"
                                class="form-control"
                                required>

                        </div>


                        <!-- Shift Type -->

                        <div class="mb-3">

                            <label
                                for="shift_type"
                                class="form-label">

                                Shift Type

                            </label>

                            <select
                                id="shift_type"
                                name="shift_type"
                                class="form-select"
                                required>

                                <option value="morning">
                                    Morning
                                </option>

                                <option value="afternoon">
                                    Afternoon
                                </option>

                                <option value="evening">
                                    Evening
                                </option>

                                <option value="full_day">
                                    Full Day
                                </option>

                            </select>

                        </div>


                        <!-- Start Time -->

                        <div class="mb-3">

                            <label
                                for="start_time"
                                class="form-label">

                                Start Time

                            </label>

                            <input
                                type="time"
                                id="start_time"
                                name="start_time"
                                class="form-control"
                                required>

                        </div>


                        <!-- End Time -->

                        <div class="mb-3">

                            <label
                                for="end_time"
                                class="form-label">

                                End Time

                            </label>

                            <input
                                type="time"
                                id="end_time"
                                name="end_time"
                                class="form-control"
                                required>

                        </div>


                        <!-- Notes -->

                        <div class="mb-3">

                            <label
                                for="notes"
                                class="form-label">

                                Notes

                            </label>

                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                class="form-control"></textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-maroon">

                            Create Template

                        </button>

                    </form>

                </div>

            </div>

        </div>


        <!-- Template List -->

        <div class="col-lg-8">

            <div class="card nivarra-card">

                <div class="nivarra-card-header d-flex justify-content-between align-items-center">

                    <span>
                        Shift Templates
                    </span>

                    <span class="badge bg-light text-dark">
                        <?= count($templates) ?>
                    </span>

                </div>

                <div class="card-body">

                    <?php if (!$templates): ?>

                        <div class="alert alert-info mb-0">
                            No shift templates have been created yet.
                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover nivarra-table mb-0">

                                <thead>

                                <tr>

                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>

                                </tr>

                                </thead>

                                <tbody>

                                <?php foreach ($templates as $template): ?>

                                    <tr>

                                        <td>

                                            <strong>
                                                <?= e(
                                                    $template['template_name']
                                                ) ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <?= e(
                                                ucfirst(
                                                    $template['shift_type']
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= e(
                                                substr(
                                                    $template['start_time'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                            -

                                            <?= e(
                                                substr(
                                                    $template['end_time'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php if (
                                                (int)$template['active'] === 1
                                            ): ?>

                                                <span class="badge bg-success">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-secondary">
                                                    Disabled
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= e(
                                                $template['full_name']
                                            ) ?>

                                        </td>


                                        <td>

                                            <div class="d-flex flex-wrap gap-2">

                                                <!-- Toggle -->

                                                <form
                                                    method="POST"
                                                    class="m-0">

                                                    <?= csrf_input() ?>

                                                    <input
                                                        type="hidden"
                                                        name="template_id"
                                                        value="<?= (int)$template['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="toggle"
                                                        class="btn btn-warning btn-sm">

                                                        Toggle

                                                    </button>

                                                </form>


                                                <!-- Delete -->

                                                <form
                                                    method="POST"
                                                    class="m-0">

                                                    <?= csrf_input() ?>

                                                    <input
                                                        type="hidden"
                                                        name="template_id"
                                                        value="<?= (int)$template['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="delete"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Delete template?')">

                                                        Delete

                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>