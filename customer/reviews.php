<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/customer-only.php';

$customerId = (int)($_SESSION['customer_id'] ?? 0);

if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

$editingReview = null;


/*
|--------------------------------------------------------------------------
| Customer information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.email,
        c.username,
        cp.first_name,
        cp.last_name,
        cp.phone
    FROM customers c
    LEFT JOIN customer_profiles cp
        ON cp.customer_id = c.id
    WHERE c.id = ?
    LIMIT 1
");

$stmt->execute([$customerId]);

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

$customerName = trim(
    $firstName . ' ' . $lastName
);

if ($customerName === '') {
    $customerName = trim(
        (string)($customer['username'] ?? '')
    );
}

if ($customerName === '') {
    $customerName = 'Customer';
}

$customerEmail = trim(
    (string)($customer['email'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Handle POST actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($csrfToken)) {

        $errorMessage = 'Invalid security token. Please try again.';

    } else {

        $action = trim(
            (string)($_POST['action'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | Submit review
        |--------------------------------------------------------------------------
        */

        if ($action === 'submit_review') {

            $rating = (int)($_POST['rating'] ?? 0);

            $title = trim(
                (string)($_POST['title'] ?? '')
            );

            $reviewText = trim(
                (string)($_POST['review_text'] ?? '')
            );


            if ($rating < 1 || $rating > 5) {

                $errorMessage = 'Please select a rating between 1 and 5.';

            } elseif ($title === '') {

                $errorMessage = 'Please enter a review title.';

            } elseif (mb_strlen($title) > 50) {

                $errorMessage = 'The review title cannot exceed 50 characters.';

            } elseif ($reviewText === '') {

                $errorMessage = 'Please enter your review.';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO reviews (
                            customer_id,
                            customer_name,
                            customer_email,
                            rating,
                            title,
                            review_text,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->execute([
                        $customerId,
                        $customerName,
                        $customerEmail,
                        $rating,
                        $title,
                        $reviewText
                    ]);

                    $successMessage =
                        'Your review has been submitted and is awaiting approval.';

                } catch (PDOException $e) {

                    error_log(
                        'Customer Review Insert Error: ' .
                        $e->getMessage()
                    );

                    $errorMessage =
                        'Unable to submit your review. Please try again.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Update pending review
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update_review') {

            $reviewId = (int)($_POST['review_id'] ?? 0);

            $rating = (int)($_POST['rating'] ?? 0);

            $title = trim(
                (string)($_POST['title'] ?? '')
            );

            $reviewText = trim(
                (string)($_POST['review_text'] ?? '')
            );


            if ($reviewId <= 0) {

                $errorMessage = 'Invalid review.';

            } elseif ($rating < 1 || $rating > 5) {

                $errorMessage = 'Please select a rating between 1 and 5.';

            } elseif ($title === '') {

                $errorMessage = 'Please enter a review title.';

            } elseif (mb_strlen($title) > 50) {

                $errorMessage = 'The review title cannot exceed 50 characters.';

            } elseif ($reviewText === '') {

                $errorMessage = 'Please enter your review.';

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Only pending reviews can be edited
                    |--------------------------------------------------------------------------
                    */

                    $checkStmt = $pdo->prepare("
                        SELECT id
                        FROM reviews
                        WHERE id = ?
                          AND customer_id = ?
                          AND status = 'pending'
                        LIMIT 1
                    ");

                    $checkStmt->execute([
                        $reviewId,
                        $customerId
                    ]);

                    if (!$checkStmt->fetchColumn()) {

                        $errorMessage =
                            'Only your pending reviews can be edited.';

                    } else {

                        $updateStmt = $pdo->prepare("
                            UPDATE reviews
                            SET
                                customer_name = ?,
                                customer_email = ?,
                                rating = ?,
                                title = ?,
                                review_text = ?,
                                status = 'pending'
                            WHERE id = ?
                              AND customer_id = ?
                              AND status = 'pending'
                        ");

                        $updateStmt->execute([
                            $customerName,
                            $customerEmail,
                            $rating,
                            $title,
                            $reviewText,
                            $reviewId,
                            $customerId
                        ]);

                        $successMessage =
                            'Your review has been updated and returned for approval.';
                    }

                } catch (PDOException $e) {

                    error_log(
                        'Customer Review Update Error: ' .
                        $e->getMessage()
                    );

                    $errorMessage =
                        'Unable to update your review. Please try again.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Delete pending review
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete_review') {

            $reviewId = (int)($_POST['review_id'] ?? 0);

            if ($reviewId <= 0) {

                $errorMessage = 'Invalid review.';

            } else {

                try {

                    $deleteStmt = $pdo->prepare("
                        DELETE FROM reviews
                        WHERE id = ?
                          AND customer_id = ?
                          AND status = 'pending'
                    ");

                    $deleteStmt->execute([
                        $reviewId,
                        $customerId
                    ]);

                    if ($deleteStmt->rowCount() > 0) {

                        $successMessage =
                            'Your pending review has been deleted.';

                    } else {

                        $errorMessage =
                            'Only your pending reviews can be deleted.';
                    }

                } catch (PDOException $e) {

                    error_log(
                        'Customer Review Delete Error: ' .
                        $e->getMessage()
                    );

                    $errorMessage =
                        'Unable to delete your review. Please try again.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Edit review
|--------------------------------------------------------------------------
*/

$editId = (int)($_GET['edit'] ?? 0);

if (
    $editId > 0 &&
    $errorMessage === ''
) {

    $editStmt = $pdo->prepare("
        SELECT
            id,
            rating,
            title,
            review_text,
            status
        FROM reviews
        WHERE id = ?
          AND customer_id = ?
          AND status = 'pending'
        LIMIT 1
    ");

    $editStmt->execute([
        $editId,
        $customerId
    ]);

    $editingReview = $editStmt->fetch(PDO::FETCH_ASSOC);

    if (!$editingReview) {
        $errorMessage =
            'The selected review cannot be edited.';
    }
}


/*
|--------------------------------------------------------------------------
| Load customer reviews
|--------------------------------------------------------------------------
*/

$reviewsStmt = $pdo->prepare("
    SELECT
        id,
        rating,
        title,
        review_text,
        status,
        created_at
    FROM reviews
    WHERE customer_id = ?
    ORDER BY created_at DESC, id DESC
");

$reviewsStmt->execute([$customerId]);

$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);


include ' ../includes/header.php';
include '../includes/customer-navbar.php';

?>

<div class="container py-4">


    <!-- Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div class="d-flex align-items-center gap-3">

            <h2 class="mb-0">
                
                Reviews
            
            </h2>

        </div>

        <div class="d-flex flex-column align-items-end gap-2">

            <a
                href="dashboard.php"
                class="btn btn-secondary d-flex align-items-center justify-content-center"
                style="width: 58.66px; height: 38px; padding: 0;">
                Back
            </a>

        </div>

    </div>


    <!-- Messages -->

    <?php if ($successMessage !== ''): ?>

        <div class="alert alert-success">

            <?= e($successMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">

            <?= e($errorMessage) ?>

        </div>

    <?php endif; ?>


    <!-- Edit Review -->

    <?php if ($editingReview): ?>

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">
                    Edit Review
                </h5>

            </div>

            <div class="card-body">

                <form
                    method="POST"
                    action="reviews.php">

                    <?= csrf_input() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="update_review">

                    <input
                        type="hidden"
                        name="review_id"
                        value="<?= (int)$editingReview['id'] ?>">


                    <!-- Rating -->

                    <div class="mb-3">

                        <label
                            for="edit_rating"
                            class="form-label">

                            Rating

                        </label>

                        <select
                            class="form-select"
                            name="rating"
                            id="edit_rating"
                            required>

                            <option value="">
                                Select rating
                            </option>

                            <?php for ($rating = 5; $rating >= 1; $rating--): ?>

                                <option
                                    value="<?= $rating ?>"
                                    <?= (int)$editingReview['rating'] === $rating ? 'selected' : '' ?>>

                                    <?= $rating ?> Star<?= $rating !== 1 ? 's' : '' ?>

                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>


                    <!-- Title -->

                    <div class="mb-3">

                        <label
                            for="edit_title"
                            class="form-label">

                            Review Title

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="title"
                            id="edit_title"
                            maxlength="50"
                            value="<?= e((string)$editingReview['title']) ?>"
                            required>

                    </div>


                    <!-- Review -->

                    <div class="mb-3">

                        <label
                            for="edit_review_text"
                            class="form-label">

                            Review

                        </label>

                        <textarea
                            class="form-control"
                            name="review_text"
                            id="edit_review_text"
                            rows="5"
                            required><?= e((string)$editingReview['review_text']) ?></textarea>

                    </div>


                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-maroon">

                            Update Review

                        </button>

                        <a
                            href="reviews.php"
                            class="btn btn-secondary">

                            Cancel

                        </a>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>


    <!-- New Review -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white">

            <h5 class="mb-0">
                Write a Review
            </h5>

        </div>

        <div class="card-body">

            <form
                method="POST"
                action="reviews.php">

                <?= csrf_input() ?>

                <input
                    type="hidden"
                    name="action"
                    value="submit_review">


                <div class="row">

                    <!-- Rating -->

                    <div class="col-md-4 mb-3">

                        <label
                            for="rating"
                            class="form-label">

                            Rating

                        </label>

                        <select
                            class="form-select"
                            name="rating"
                            id="rating"
                            required>

                            <option value="">
                                Select rating
                            </option>

                            <option value="5">
                                5 Stars
                            </option>

                            <option value="4">
                                4 Stars
                            </option>

                            <option value="3">
                                3 Stars
                            </option>

                            <option value="2">
                                2 Stars
                            </option>

                            <option value="1">
                                1 Star
                            </option>

                        </select>

                    </div>


                    <!-- Title -->

                    <div class="col-md-8 mb-3">

                        <label
                            for="title"
                            class="form-label">

                            Review Title

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="title"
                            id="title"
                            maxlength="50"
                            placeholder="Summarize your experience"
                            required>

                    </div>

                </div>


                <!-- Review Text -->

                <div class="mb-3">

                    <label
                        for="review_text"
                        class="form-label">

                        Review

                    </label>

                    <textarea
                        class="form-control"
                        name="review_text"
                        id="review_text"
                        rows="5"
                        placeholder="Tell us about your experience at NIVARRA..."
                        required></textarea>

                </div>


                <button
                    type="submit"
                    class="btn btn-maroon">

                    Submit Review

                </button>

            </form>

        </div>

    </div>


    <!-- My Reviews -->

    <div class="card shadow-sm">

        <div class="card-header bg-white">

            <h5 class="mb-0">
                My Reviews
            </h5>

        </div>

        <div class="card-body">

            <?php if (empty($reviews)): ?>

                <div class="text-center py-4">

                    <h5>
                        No reviews yet
                    </h5>

                    <p class="text-muted mb-0">

                        Your submitted reviews will appear here.

                    </p>

                </div>

            <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($reviews as $review): ?>

                        <?php

                        $status = strtolower(
                            trim((string)$review['status'])
                        );

                        $rating = (int)$review['rating'];

                        ?>

                        <div class="col-12">

                            <div class="border rounded p-3">

                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

                                    <div>

                                        <h5 class="mb-1">

                                            <?= e((string)$review['title']) ?>

                                        </h5>

                                        <div class="review-stars">

                                            <?= e(reviewStars($rating)) ?>

                                        </div>

                                    </div>


                                    <span
                                        class="badge <?= e(reviewStatusClass($status)) ?>">

                                        <?= e(reviewStatusLabel($status)) ?>

                                    </span>

                                </div>


                                <p class="mt-3 mb-2">

                                    <?= nl2br(e((string)$review['review_text'])) ?>

                                </p>


                                <div class="text-muted small">

                                    Submitted:

                                    <?= e((string)$review['created_at']) ?>

                                </div>


                                <?php if ($status === 'pending'): ?>

                                    <div class="d-flex gap-2 mt-3">

                                        <a
                                            href="reviews.php?edit=<?= (int)$review['id'] ?>"
                                            class="btn btn-sm btn-outline-primary">

                                            Edit

                                        </a>


                                        <form
                                            method="POST"
                                            action="reviews.php"
                                            onsubmit="return confirm('Are you sure you want to delete this review?');">

                                            <?= csrf_input() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete_review">

                                            <input
                                                type="hidden"
                                                name="review_id"
                                                value="<?= (int)$review['id'] ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger">

                                                Delete

                                            </button>

                                        </form>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>