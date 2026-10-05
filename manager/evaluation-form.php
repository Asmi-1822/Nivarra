<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Employee Evaluation';

$evaluationId =
    (int)($_GET['id'] ?? 0);

$isEdit =
    $evaluationId > 0;

/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        employee_code,
        full_name
    FROM employees
    WHERE active = 1
    ORDER BY full_name
");

$employees =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Defaults
|--------------------------------------------------------------------------
*/

$data = [

    'employee_id' => '',
    'evaluation_date' => date('Y-m-d'),

    'punctuality_score' => 5,
    'teamwork_score' => 5,
    'customer_service_score' => 5,
    'productivity_score' => 5,

    'strengths' => '',
    'improvements' => '',
    'comments' => ''
];

/*
|--------------------------------------------------------------------------
| Load Existing Evaluation
|--------------------------------------------------------------------------
*/

if ($isEdit) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM evaluations
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $evaluationId
    ]);

    $evaluation =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evaluation) {

        flash(
            'error',
            'Evaluation not found.'
        );

        redirect(
            'manage-evaluations.php'
        );
    }

    $data = $evaluation;
}

/*
|--------------------------------------------------------------------------
| Save Evaluation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $employeeId =
        (int)($_POST['employee_id'] ?? 0);

    $evaluationDate =
        trim($_POST['evaluation_date'] ?? '');

    $punctuality =
        (int)($_POST['punctuality_score'] ?? 0);

    $teamwork =
        (int)($_POST['teamwork_score'] ?? 0);

    $customerService =
        (int)($_POST['customer_service_score'] ?? 0);

    $productivity =
        (int)($_POST['productivity_score'] ?? 0);

    $strengths =
        trim($_POST['strengths'] ?? '');

    $improvements =
        trim($_POST['improvements'] ?? '');

    $comments =
        trim($_POST['comments'] ?? '');

    $errors = [];

    if ($employeeId <= 0) {

        $errors[] =
            'Employee is required.';
    }

    foreach (
        [
            $punctuality,
            $teamwork,
            $customerService,
            $productivity
        ]
        as $score
    ) {

        if (
            $score < 1 ||
            $score > 10
        ) {

            $errors[] =
                'Scores must be between 1 and 10.';
            break;
        }
    }

    if (!$errors) {

        if ($isEdit) {

            $stmt = $pdo->prepare("
                UPDATE evaluations
                SET
                    employee_id=?,
                    evaluation_date=?,

                    punctuality_score=?,
                    teamwork_score=?,
                    customer_service_score=?,
                    productivity_score=?,

                    strengths=?,
                    improvements=?,
                    comments=?

                WHERE id=?
            ");

            $stmt->execute([

                $employeeId,
                $evaluationDate,

                $punctuality,
                $teamwork,
                $customerService,
                $productivity,

                $strengths,
                $improvements,
                $comments,

                $evaluationId
            ]);

            auditLog(
                $_SESSION['employee_id'],
                'evaluation_updated',
                'Evaluation #' .
                $evaluationId
            );

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO evaluations (

                    employee_id,
                    evaluator_id,
                    evaluation_date,

                    punctuality_score,
                    teamwork_score,
                    customer_service_score,
                    productivity_score,

                    strengths,
                    improvements,
                    comments

                ) VALUES (

                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?
                )
            ");

            $stmt->execute([

                $employeeId,
                $_SESSION['employee_id'],
                $evaluationDate,

                $punctuality,
                $teamwork,
                $customerService,
                $productivity,

                $strengths,
                $improvements,
                $comments
            ]);

            auditLog(
                $_SESSION['employee_id'],
                'evaluation_created',
                'Employee #' .
                $employeeId
            );
        }

        flash(
            'success',
            'Evaluation saved.'
        );

        redirect(
            'manage-evaluations.php'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Historical Evaluations
|--------------------------------------------------------------------------
*/

$history = [];

if (
    !empty($data['employee_id'])
) {

    $stmt = $pdo->prepare("
        SELECT

            evaluation_date,

            ROUND(
                (
                    punctuality_score +
                    teamwork_score +
                    customer_service_score +
                    productivity_score
                ) / 4,
                2
            ) score

        FROM evaluations

        WHERE employee_id = ?

        ORDER BY
            evaluation_date DESC

        LIMIT 10
    ");

    $stmt->execute([
        $data['employee_id']
    ]);

    $history =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/header.php';
include '../includes/manager-navbar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between mb-4">

        <h2>

            <?= $isEdit
                ? 'Edit Evaluation'
                : 'Create Evaluation' ?>

        </h2>

        <a
            href="manage-evaluations.php"
            class="btn btn-secondary">

            Back

        </a>

    </div>

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

    <form method="POST">

        <?= csrf_input() ?>

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">

                            Employee

                        </label>

                        <select
                            name="employee_id"
                            class="form-select"
                            required>

                            <option value="">

                                Select Employee

                            </option>

                            <?php foreach($employees as $employee): ?>

                                <option
                                    value="<?= (int)$employee['id'] ?>"
                                    <?= (
                                        (int)$data['employee_id']
                                        ===
                                        (int)$employee['id']
                                    )
                                    ? 'selected'
                                    : '' ?>>

                                    <?= e(
                                        $employee['employee_code']
                                    ) ?>

                                    -

                                    <?= e(
                                        $employee['full_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">

                            Evaluation Date

                        </label>

                        <input
                            type="date"
                            name="evaluation_date"
                            class="form-control"
                            value="<?= e($data['evaluation_date']) ?>"
                            required>

                    </div>

                </div>

            </div>

        </div>

        <!-- Scores -->

        <div class="card shadow-sm mb-4">

            <div class="card-header">

                Performance Scores

            </div>

            <div class="card-body">

                <div class="row g-3">

                    <?php

                    $fields = [

                        'punctuality_score'
                            => 'Punctuality',

                        'teamwork_score'
                            => 'Teamwork',

                        'customer_service_score'
                            => 'Customer Service',

                        'productivity_score'
                            => 'Productivity'
                    ];

                    foreach(
                        $fields
                        as $field => $label
                    ):
                    ?>

                    <div class="col-md-3">

                        <label>

                            <?= e($label) ?>

                        </label>

                        <input
                            type="number"
                            min="1"
                            max="10"
                            name="<?= e($field) ?>"
                            value="<?= (int)$data[$field] ?>"
                            class="form-control"
                            required>

                    </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

        <!-- Comments -->

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <div class="mb-3">

                    <label>

                        Strengths

                    </label>

                    <textarea
                        name="strengths"
                        rows="4"
                        class="form-control"><?= e($data['strengths']) ?></textarea>

                </div>

                <div class="mb-3">

                    <label>

                        Improvements

                    </label>

                    <textarea
                        name="improvements"
                        rows="4"
                        class="form-control"><?= e($data['improvements']) ?></textarea>

                </div>

                <div>

                    <label>

                        Manager Comments

                    </label>

                    <textarea
                        name="comments"
                        rows="4"
                        class="form-control"><?= e($data['comments']) ?></textarea>

                </div>

            </div>

        </div>

        <button
            type="submit"
            class="btn btn-maroon">

            Save Evaluation

        </button>

    </form>

    <?php if ($history): ?>

        <div class="card shadow-sm mt-5">

            <div class="card-header">

                Evaluation History

            </div>

            <div class="card-body">

                <table class="table">

                    <thead>

                    <tr>

                        <th>Date</th>
                        <th>Score</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach($history as $item): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $item['evaluation_date']
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $item['score']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>