<?php

    declare(strict_types=1);

    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
    require_once 'includes/csrf.php';
    require_once 'includes/rate-limit.php';


    /*
    |--------------------------------------------------------------------------
    | Form Submission
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )) {

            http_response_code(403);

            exit('Invalid CSRF token.');
        }


        /*
        |--------------------------------------------------------------------------
        | Rate Limit
        |--------------------------------------------------------------------------
        */

        rateLimit(
            'contact',
            5,
            300
        );


        /*
        |--------------------------------------------------------------------------
        | Form Values
        |--------------------------------------------------------------------------
        */

        $name = post('name');

        $email = post('email');

        $subject = post('subject');

        $message = post('message');


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $errors = [];


        if ($name === '') {

            $errors[] = 'Please enter your name.';

        }


        if (

            $email === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        
        ) {

            $errors[] = 'Please enter a valid email address.';
        }


        if ($subject === '') {

            $errors[] = 'Please enter a subject.';
    
        }


        if ($message === '') {

            $errors[] = 'Please enter your message.';
    
        }


        if (empty($errors)) {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO contact_messages (
                        name,
                        email,
                        subject,
                        message
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $subject,
                    $message
                ]);


                flash(
                    'success',
                    'Your message has been sent successfully.'
                );

                redirect('contact.php');

            } catch (PDOException $e) {

                error_log(
                    'Contact Message Error: ' .
                    $e->getMessage()
                );

                flash(
                    'error',
                    'Unable to send your message. Please try again.'
                );

                redirect('contact.php');
            }

        } else {

            flash(
                'error',
                implode(' ', $errors)
            );

            redirect('contact.php');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Flash Messages
    |--------------------------------------------------------------------------
    */

    $successMessage = flashMessage('success');

    $errorMessage = flashMessage('error');


    /*
    |--------------------------------------------------------------------------
    | Public Layout
    |--------------------------------------------------------------------------
    */

    include 'includes/header.php';
    include 'includes/navbar.php';
?>

    <div class="container py-5">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-start mb-4">

            <div>

                <h1 class="fw-bold">
                    Contact Us
                </h1>

                <p class="text-muted mb-0">
                    Have a question or need assistance?
                    Get in touch with NIVARRA.
                </p>

            </div>


            <div class="d-flex flex-column align-items-end gap-2">

                <a
                    href="index.php"
                    class="btn btn-secondary d-flex align-items-center justify-content-center"
                    style="width: 58.66px; height: 38px; padding: 0;">

                    Back

                </a>

            </div>

        </div>


        <!-- Messages -->

        <?php if ($successMessage !== null): ?>

            <div class="alert alert-success">

                <?= e($successMessage) ?>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage !== null): ?>

            <div class="alert alert-danger">

                <?= e($errorMessage) ?>

            </div>

        <?php endif; ?>


        <div class="row g-4">


            <!-- Contact Form -->

            <div class="col-lg-8">

                <div class="card shadow-sm h-100">

                    <div class="card-body p-4">

                        <h3 class="fw-bold mb-4">
                            Send Us a Message
                        </h3>

                        <form
                            method="POST"
                            action="contact.php">

                            <?= csrf_input() ?>


                            <div class="mb-3">

                                <label
                                    for="name"
                                    class="form-label">

                                    Name

                                </label>

                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    class="form-control"
                                    maxlength="150"
                                    required>

                            </div>


                            <div class="mb-3">

                                <label
                                    for="email"
                                    class="form-label">

                                    Email

                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    maxlength="150"
                                    required>

                            </div>


                            <div class="mb-3">

                                <label
                                    for="subject"
                                    class="form-label">

                                    Subject

                                </label>

                                <input
                                    type="text"
                                    name="subject"
                                    id="subject"
                                    class="form-control"
                                    maxlength="255"
                                    required>

                            </div>


                            <div class="mb-3">

                                <label
                                    for="message"
                                    class="form-label">

                                    Message

                                </label>

                                <textarea
                                    name="message"
                                    id="message"
                                    class="form-control"
                                    rows="6"
                                    required></textarea>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-maroon">

                                Send Message

                            </button>

                        </form>

                    </div>

                </div>

            </div>


            <!-- Contact Information -->

            <div class="col-lg-4">

                <div class="card shadow-sm h-100">

                    <div class="card-body p-4">

                        <h3 class="fw-bold mb-4">
                            Contact Information
                        </h3>


                        <div class="mb-4">

                            <h5 class="fw-bold">
                                Email
                            </h5>

                            <p class="mb-0">
                                info@nivaraa.com
                            </p>

                        </div>


                        <div class="mb-4">

                            <h5 class="fw-bold">
                                Phone
                            </h5>

                            <p class="mb-0">
                                +1 555 123 456
                            </p>

                        </div>


                        <div>

                            <h5 class="fw-bold">
                                Location
                            </h5>

                            <p class="mb-0">
                                Located near the city center.
                                Parking available.
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

<?php include 'includes/footer.php'; ?>