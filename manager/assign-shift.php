<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/manager-only.php';
require_once '../includes/csrf.php';

$pageTitle = 'Assign Shift';

$errors = [];

/*
|--------------------------------------------------------------------------
| Employees and Managers
|--------------------------------------------------------------------------
|
| Managers are also employees in the system and can have shifts.
|
*/

$stmt = $pdo->query("
    SELECT
        id,
        employee_code,
        full_name,
        role,
        position
    FROM employees
    WHERE active = 1
    ORDER BY
        CASE
            WHEN role = 'manager' THEN 1
            ELSE 2
        END,
        full_name
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Save Shift
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $employeeId =
        (int)($_POST['employee_id'] ?? 0);

    $shiftDate =
        trim((string)($_POST['shift_date'] ?? ''));

    $startTime =
        trim((string)($_POST['start_time'] ?? ''));

    $endTime =
        trim((string)($_POST['end_time'] ?? ''));

    $shiftType =
        trim((string)($_POST['shift_type'] ?? ''));

    $notes =
        trim((string)($_POST['notes'] ?? ''));

    $repeatWeeks =
        (int)($_POST['repeat_weeks'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Employee Exists
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            role
        FROM employees
        WHERE id = ?
          AND active = 1
        LIMIT 1
    ");

    $stmt->execute([
        $employeeId
    ]);

    $selectedEmployee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedEmployee) {

        $errors[] =
            'Invalid employee selected.';
    }


    /*
    |--------------------------------------------------------------------------
    | Date Validation
    |--------------------------------------------------------------------------
    */

    if ($shiftDate === '') {

        $errors[] =
            'Shift date is required.';

    } else {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $shiftDate
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $shiftDate
        ) {

            $errors[] =
                'Invalid shift date.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Time Validation
    |--------------------------------------------------------------------------
    */

    if (
        $startTime === '' ||
        $endTime === ''
    ) {

        $errors[] =
            'Start time and end time are required.';

    } elseif (
        strtotime($startTime) >=
        strtotime($endTime)
    ) {

        $errors[] =
            'End time must be after start time.';
    }


    /*
    |--------------------------------------------------------------------------
    | Shift Type Validation
    |--------------------------------------------------------------------------
    */

    $allowedTypes = [
        'morning',
        'afternoon',
        'evening',
        'full_day'
    ];

    if (
        !in_array(
            $shiftType,
            $allowedTypes,
            true
        )
    ) {

        $errors[] =
            'Invalid shift type.';
    }


    /*
    |--------------------------------------------------------------------------
    | Repeat Weeks Validation
    |--------------------------------------------------------------------------
    */

    $repeatWeeks =
        max(
            0,
            min(
                $repeatWeeks,
                52
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Generate Shift Dates
    |--------------------------------------------------------------------------
    */

    $dates = [];

    if (
        $shiftDate !== '' &&
        empty($errors)
    ) {

        for (
            $i = 0;
            $i <= $repeatWeeks;
            $i++
        ) {

            $dates[] =
                date(
                    'Y-m-d',
                    strtotime(
                        $shiftDate .
                        " +{$i} week"
                    )
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Each Date
    |--------------------------------------------------------------------------
    */

    foreach ($dates as $date) {

        /*
        |--------------------------------------------------------------------------
        | Approved Leave Conflict
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM leave_requests
            WHERE employee_id = ?
              AND status = 'approved'
              AND ? BETWEEN start_date AND end_date
        ");

        $stmt->execute([
            $employeeId,
            $date
        ]);

        if (
            (int)$stmt->fetchColumn() > 0
        ) {

            $errors[] =
                "Employee is on approved leave ({$date}).";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Shift Overlap
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM shifts
            WHERE employee_id = ?
              AND shift_date = ?
              AND (
                    start_time < ?
                AND end_time > ?
              )
        ");

        $stmt->execute([
            $employeeId,
            $date,
            $endTime,
            $startTime
        ]);

        if (
            (int)$stmt->fetchColumn() > 0
        ) {

            $errors[] =
                "Shift overlap detected ({$date}).";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Shifts
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $pdo->beginTransaction();

        try {

            $stmt = $pdo->prepare("
                INSERT INTO shifts (
                    employee_id,
                    shift_date,
                    start_time,
                    end_time,
                    shift_type,
                    notes
                ) VALUES (
                    ?, ?, ?, ?, ?, ?
                )
            ");

            foreach ($dates as $date) {

                $stmt->execute([
                    $employeeId,
                    $date,
                    $startTime,
                    $endTime,
                    $shiftType,
                    $notes
                ]);
            }

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            if (
                function_exists('auditLog')
            ) {

                auditLog(
                    (int)($_SESSION['employee_id'] ?? 0),
                    'shift_created',
                    'Employee #' .
                    $employeeId .
                    ' (' .
                    count($dates) .
                    ' shifts)'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            flash(
                'success',
                count($dates) .
                ' shift(s) created.'
            );

            redirect(
                'manage-shifts.php'
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Unable to create the shift. Please try again.';
        }
    }
}


include '../includes/header.php';
include '../includes/manager-navbar.php';

?>

<div class="container py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Assign Shift
            </h2>

            <p class="text-muted mb-0">
                Assign a shift to an employee or manager.
            </p>

        </div>

        <a
            href="manage-shifts.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">
            Back
        </a>

    </div>


    <!-- Errors -->
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


    <!-- Assign Shift Form -->
    <form method="POST">

        <?= csrf_input() ?>

        <div class="card shadow-sm">

            <div class="card-body">

                <div class="row g-3">


                    <!-- Employee -->
                    <div class="col-md-6">

                        <label
                            for="employee_id"
                            class="form-label"
                        >
                            Employee
                        </label>

                        <select
                            id="employee_id"
                            name="employee_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select Employee
                            </option>

                            <?php foreach (
                                $employees
                                as $employee
                            ): ?>

                                <option
                                    value="<?= (int)$employee['id'] ?>"
                                    <?= (
                                        (int)($_POST['employee_id'] ?? 0)
                                        === (int)$employee['id']
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e(
                                        (string)$employee['employee_code']
                                    ) ?>

                                    -
                                    <?= e(
                                        (string)$employee['full_name']
                                    ) ?>

                                    (<?= e(
                                        ucfirst(
                                            (string)$employee['role']
                                        )
                                    ) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small class="text-muted">
                            Managers can also be assigned shifts.
                        </small>

                    </div>


                    <!-- Shift Type -->
                    <div class="col-md-6">

                        <label
                            for="shift_type"
                            class="form-label"
                        >
                            Shift Type
                        </label>

                        <select
                            id="shift_type"
                            name="shift_type"
                            class="form-select"
                            required
                        >

                            <option
                                value="morning"
                                <?= (
                                    ($_POST['shift_type'] ?? 'morning')
                                    === 'morning'
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Morning
                            </option>

                            <option
                                value="afternoon"
                                <?= (
                                    ($_POST['shift_type'] ?? '')
                                    === 'afternoon'
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Afternoon
                            </option>

                            <option
                                value="evening"
                                <?= (
                                    ($_POST['shift_type'] ?? '')
                                    === 'evening'
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Evening
                            </option>

                            <option
                                value="full_day"
                                <?= (
                                    ($_POST['shift_type'] ?? '')
                                    === 'full_day'
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Full Day
                            </option>

                        </select>

                    </div>


                    <!-- Date -->
                    <div class="col-md-4">

                        <label
                            for="shift_date"
                            class="form-label"
                        >
                            Date
                        </label>

                        <input
                            type="date"
                            id="shift_date"
                            name="shift_date"
                            class="form-control"
                            value="<?= e(
                                (string)(
                                    $_POST['shift_date'] ?? ''
                                )
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- Start Time -->
                    <div class="col-md-4">

                        <label
                            for="start_time"
                            class="form-label"
                        >
                            Start Time
                        </label>

                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            class="form-control"
                            value="<?= e(
                                (string)(
                                    $_POST['start_time'] ?? ''
                                )
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- End Time -->
                    <div class="col-md-4">

                        <label
                            for="end_time"
                            class="form-label"
                        >
                            End Time
                        </label>

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            class="form-control"
                            value="<?= e(
                                (string)(
                                    $_POST['end_time'] ?? ''
                                )
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- Repeat -->
                    <div class="col-md-6">

                        <label
                            for="repeat_weeks"
                            class="form-label"
                        >
                            Repeat Weekly
                        </label>

                        <input
                            type="number"
                            id="repeat_weeks"
                            name="repeat_weeks"
                            min="0"
                            max="52"
                            value="<?= e(
                                (string)(
                                    $_POST['repeat_weeks'] ?? '0'
                                )
                            ) ?>"
                            class="form-control"
                        >

                        <small class="text-muted">
                            0 = single shift
                        </small>

                    </div>


                    <!-- Notes -->
                    <div class="col-md-12">

                        <label
                            for="notes"
                            class="form-label"
                        >
                            Notes
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            class="form-control"
                        ><?= e(
                            (string)(
                                $_POST['notes'] ?? ''
                            )
                        ) ?></textarea>

                    </div>

                </div>

            </div>

        </div>


        <!-- Actions -->
        <div class="mt-3">

            <button
                type="submit"
                class="btn btn-maroon"
            >
                Create Shift
            </button>

        </div>

    </form>

</div>

<?php include '../includes/footer.php'; ?>