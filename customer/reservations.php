<?php

declare(strict_types=1);

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/csrf.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/customer-only.php';

$pageTitle = 'Make a Reservation';
$reservationDate = '';
$reservationTime = '';
$guests = '';
$notes = '';

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Customer Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.email,
        cp.first_name,
        cp.last_name,
        cp.phone
    FROM customers c
    LEFT JOIN customer_profiles cp
        ON cp.customer_id = c.id
    WHERE c.id = ?
    LIMIT 1
");

$stmt->execute([
    $customerId
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: ../login.php');
    exit;
}

$firstName = trim(
    (string)($customer['first_name'] ?? '')
);

$lastName = trim(
    (string)($customer['last_name'] ?? '')
);

$defaultName = trim(
    $firstName . ' ' . $lastName
);

$defaultEmail = trim(
    (string)($customer['email'] ?? '')
);

$defaultPhone = trim(
    (string)($customer['phone'] ?? '')
);

/*
|--------------------------------------------------------------------------
| Handle Reservation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {

        $errorMessage = 'Invalid security token. Please refresh the page and try again.';

    } else {

        $fullName = trim(
            (string)($_POST['full_name'] ?? '')
        );

        $phone = trim(
            (string)($_POST['phone'] ?? '')
        );

        $email = trim(
            (string)($_POST['email'] ?? '')
        );

        $reservationDate = trim(
            (string)($_POST['reservation_date'] ?? '')
        );

        $reservationTime = trim(
            (string)($_POST['reservation_time'] ?? '')
        );

        $guests = (int)(
            $_POST['guests'] ?? 0
        );

        $notes = trim(
            (string)($_POST['notes'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($fullName === '') {

            $errorMessage = 'Please enter your full name.';

        } elseif ($phone === '') {

            $errorMessage = 'Please enter your phone number.';

        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $errorMessage = 'Please enter a valid email address.';

        } elseif ($reservationDate === '') {

            $errorMessage = 'Please select a reservation date.';

        } elseif ($reservationTime === '') {

            $errorMessage = 'Please select a reservation time.';

        } elseif ($guests < 1 || $guests > 50) {

            $errorMessage = 'The number of guests must be between 1 and 50.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Validate Date
            |--------------------------------------------------------------------------
            */

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $reservationDate
            );

            $dateErrors = DateTime::getLastErrors();

            $validDate =
                $dateObject !== false
                &&
                (
                    $dateErrors === false
                    ||
                    (
                        $dateErrors['warning_count'] === 0
                        &&
                        $dateErrors['error_count'] === 0
                    )
                )
                &&
                $dateObject->format('Y-m-d') === $reservationDate;

            if (!$validDate) {

                $errorMessage = 'Please select a valid reservation date.';

            } elseif ($reservationDate < date('Y-m-d')) {

                $errorMessage = 'Reservation date cannot be in the past.';

            }

        }

        /*
        |--------------------------------------------------------------------------
        | Validate Time
        |--------------------------------------------------------------------------
        */

        if ($errorMessage === '') {

            $timeObject = DateTime::createFromFormat(
                'H:i',
                $reservationTime
            );

            $timeErrors = DateTime::getLastErrors();

            $validTime =
                $timeObject !== false
                &&
                (
                    $timeErrors === false
                    ||
                    (
                        $timeErrors['warning_count'] === 0
                        &&
                        $timeErrors['error_count'] === 0
                    )
                )
                &&
                $timeObject->format('H:i') === $reservationTime;

            if (!$validTime) {

                $errorMessage = 'Please select a valid reservation time.';

            }

        }

        /*
        |--------------------------------------------------------------------------
        | Create Reservation
        |--------------------------------------------------------------------------
        */

        if ($errorMessage === '') {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO reservations (
                        customer_id,
                        full_name,
                        phone,
                        email,
                        reservation_date,
                        reservation_time,
                        guests,
                        notes,
                        status
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'pending'
                    )
                ");

                $stmt->execute([
                    $customerId,
                    $fullName,
                    $phone,
                    $email,
                    $reservationDate,
                    $reservationTime,
                    $guests,
                    $notes !== '' ? $notes : null
                ]);

                $successMessage =
                    'Your reservation request has been submitted successfully.';

                /*
                | Reset form values after successful submission.
                */

                $defaultName = $fullName;
                $defaultEmail = $email;
                $defaultPhone = $phone;

                $reservationDate = '';
                $reservationTime = '';
                $guests = 2;
                $notes = '';

            } catch (PDOException $e) {

                $errorMessage =
                    'Unable to submit your reservation at this time. Please try again.';

                if (
                    defined('APP_ENV')
                    &&
                    APP_ENV === 'development'
                ) {
                    $errorMessage .=
                        ' Database error: ' . $e->getMessage();
                }

            }

        }

    }

} else {

    $reservationDate = '';
    $reservationTime = '';
    $guests = 2;
    $notes = '';

}

/*
|--------------------------------------------------------------------------
| Customer Reservations
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        reservation_date,
        reservation_time,
        guests,
        notes,
        status,
        created_at
    FROM reservations
    WHERE customer_id = ?
    ORDER BY reservation_date DESC, reservation_time DESC
    LIMIT 10
");

$stmt->execute([
    $customerId
]);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

include '../includes/header.php';
include '../includes/customer-navbar.php';
?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-1">
                Make a Reservation
            </h2>

            <p class="text-muted mb-0">
                Reserve a table at NIVARRA.
            </p>

        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary d-flex align-items-center justify-content-center"
            style="width: 58.66px; height: 38px; padding: 0;">
            Back
        </a>

    </div>

    <!-- Messages -->

    <?php if ($successMessage !== ''): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert">

            <?= e($successMessage) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>

        </div>

    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert">

            <?= e($errorMessage) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>

        </div>

    <?php endif; ?>

    <div class="row g-4">

        <!-- Reservation Form -->

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header">

                    <h5 class="mb-0">
                        Reservation Details
                    </h5>

                </div>

                <div class="card-body">

                    <form
                        method="post"
                        action="reservation.php">

                        <?= csrf_input() ?>

                        <div class="row g-3">

                            <!-- Name -->

                            <div class="col-md-6">

                                <label
                                    for="full_name"
                                    class="form-label">

                                    Full Name

                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="full_name"
                                    name="full_name"
                                    value="<?= e($defaultName) ?>"
                                    maxlength="100"
                                    required>

                            </div>

                            <!-- Phone -->

                            <div class="col-md-6">

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
                                    value="<?= e($defaultPhone) ?>"
                                    maxlength="30"
                                    required>

                            </div>

                            <!-- Email -->

                            <div class="col-12">

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
                                    value="<?= e($defaultEmail) ?>"
                                    maxlength="150"
                                    required>

                            </div>

                            <!-- Date -->

                            <div class="col-md-6">

                                <label
                                    for="reservation_date"
                                    class="form-label">

                                    Reservation Date

                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    id="reservation_date"
                                    name="reservation_date"
                                    value="<?= e($reservationDate) ?>"
                                    min="<?= date('Y-m-d') ?>"
                                    required>

                            </div>

                            <!-- Time -->

                            <div class="col-md-6">

                                <label
                                    for="reservation_time"
                                    class="form-label">

                                    Reservation Time

                                </label>

                                <input
                                    type="time"
                                    class="form-control"
                                    id="reservation_time"
                                    name="reservation_time"
                                    value="<?= e($reservationTime) ?>"
                                    required>

                            </div>

                            <!-- Guests -->

                            <div class="col-md-6">

                                <label
                                    for="guests"
                                    class="form-label">

                                    Number of Guests

                                </label>

                                <input
                                    type="number"
                                    class="form-control"
                                    id="guests"
                                    name="guests"
                                    value="<?= e((string)$guests) ?>"
                                    min="1"
                                    max="50"
                                    required>

                            </div>

                            <!-- Notes -->

                            <div class="col-12">

                                <label
                                    for="notes"
                                    class="form-label">

                                    Special Notes

                                </label>

                                <textarea
                                    class="form-control"
                                    id="notes"
                                    name="notes"
                                    rows="4"
                                    maxlength="1000"
                                    placeholder="Any special requests or requirements?"><?= e($notes) ?></textarea>

                            </div>

                            <!-- Submit -->

                            <div class="col-12">

                                <button
                                    type="submit"
                                    class="btn btn-maroon">

                                    Submit Reservation

                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

        <!-- Information -->

        <div class="col-lg-5">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Reservation Information
                    </h5>

                </div>

                <div class="card-body">

                    <p class="mb-3">

                        Submit your preferred date and time.
                        Your reservation will remain pending until
                        it is reviewed and confirmed.

                    </p>

                    <div class="alert alert-info mb-0">

                        <strong>
                            Please note:
                        </strong>

                        <br>

                        Submitting a reservation does not guarantee
                        confirmation. Check your reservations for
                        the latest status.

                    </div>

                </div>

            </div>

            <!-- Status Legend -->

            <div class="card shadow-sm">

                <div class="card-header">

                    <h5 class="mb-0">
                        Reservation Status
                    </h5>

                </div>

                <div class="card-body">

                    <div class="mb-2">

                        <span class="badge bg-warning text-dark">
                            Pending
                        </span>

                        <span class="text-muted ms-2">
                            Awaiting confirmation
                        </span>

                    </div>

                    <div class="mb-2">

                        <span class="badge bg-success">
                            Confirmed
                        </span>

                        <span class="text-muted ms-2">
                            Reservation confirmed
                        </span>

                    </div>

                    <div class="mb-2">

                        <span class="badge bg-danger">
                            Cancelled
                        </span>

                        <span class="text-muted ms-2">
                            Reservation cancelled
                        </span>

                    </div>

                    <div>

                        <span class="badge bg-secondary">
                            Completed
                        </span>

                        <span class="text-muted ms-2">
                            Reservation completed
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Recent Reservations -->

    <div class="card shadow-sm mt-4">

        <div class="card-header">

            <h5 class="mb-0">
                My Recent Reservations
            </h5>

        </div>

        <div class="card-body">

            <?php if ($reservations): ?>

                <div class="table-responsive">

                    <table class="table table-striped align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Time
                                </th>

                                <th>
                                    Guests
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Notes
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($reservations as $reservation): ?>

                                <?php

                                $reservationStatus = strtolower(
                                    trim(
                                        (string)(
                                            $reservation['status'] ?? ''
                                        )
                                    )
                                );

                                $statusBadge = match (
                                    $reservationStatus
                                ) {

                                    'confirmed'
                                        => 'bg-success',

                                    'cancelled'
                                        => 'bg-danger',

                                    'completed'
                                        => 'bg-secondary',

                                    default
                                        => 'bg-warning text-dark'
                                };

                                ?>

                                <tr>

                                    <td>

                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string)(
                                                        $reservation[
                                                            'reservation_date'
                                                        ]
                                                    )
                                                )
                                            )
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= e(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    (string)(
                                                        $reservation[
                                                            'reservation_time'
                                                        ]
                                                    )
                                                )
                                            )
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= e(
                                            (string)(
                                                $reservation['guests']
                                                ?? 0
                                            )
                                        ) ?>

                                    </td>

                                    <td>

                                        <span
                                            class="badge <?= $statusBadge ?>">

                                            <?= e(
                                                ucfirst(
                                                    $reservationStatus
                                                )
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?php
                                        $reservationNotes = trim(
                                            (string)(
                                                $reservation['notes'] ?? ''
                                            )
                                        );
                                        ?>

                                        <?php if ($reservationNotes !== ''): ?>

                                            <?= e($reservationNotes) ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-info mb-0">

                    You do not have any reservations yet.

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>