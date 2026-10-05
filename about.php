<?php

    declare(strict_types=1);

    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
    require_once 'includes/csrf.php';

    include 'includes/header.php';
    include 'includes/navbar.php';
?>

    <div class="container py-5">

        <!-- Page Header -->

        <div class="d-flex justify-content-between align-items-start mb-4">

            <div>

                <h1 class="fw-bold">
                   About NIVARRA
                </h1>

                <p class="text-muted mb-0">
                    Discover the story, mission, vision, and values behind NIVARRA.
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

        <!-- History -->

        <div class="card shadow-sm mb-4">

            <div class="card-body p-4">

                <h3 class="fw-bold mb-3">
                    Our History
                </h3>

                <p class="mb-0">
                    Founded in 2025, NIVARRA celebrates
                    traditional cuisine and hospitality.
                </p>

            </div>

        </div>


        <!-- Mission & Vision -->

        <div class="row g-4 mb-4">

            <div class="col-md-6">

                <div class="card shadow-sm h-100">

                    <div class="card-body p-4">

                        <h3 class="fw-bold mb-3">
                            Our Mission
                        </h3>

                        <p class="mb-0">
                            Deliver unforgettable dining experiences.
                        </p>

                    </div>

                </div>

            </div>


            <div class="col-md-6">

                <div class="card shadow-sm h-100">

                    <div class="card-body p-4">

                        <h3 class="fw-bold mb-3">
                            Our Vision
                        </h3>

                        <p class="mb-0">
                            Become the region's most loved restaurant.
                        </p>

                    </div>

                </div>

            </div>

        </div>


        <!-- Awards -->

        <div class="card shadow-sm mb-4">

            <div class="card-body p-4">

                <h3 class="fw-bold mb-3">
                    Awards
                </h3>

                <ul class="mb-0">

                    <li class="mb-2">
                        Best Family Dining 2025
                    </li>

                    <li>
                        Hospitality Excellence Award
                    </li>

                </ul>

            </div>

        </div>


        <!-- Opening Hours -->

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h3 class="fw-bold mb-4">
                    Opening Hours
                </h3>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped mb-0">

                        <tbody>

                            <tr>
                                <td class="fw-semibold">
                                    Monday
                                </td>
                                <td>
                                    10:00 - 22:00
                                </td>
                            </tr>

                            <tr>
                                <td class="fw-semibold">
                                    Tuesday
                                </td>
                                <td>
                                    10:00 - 22:00
                                </td>
                            </tr>

                            <tr>
                                <td class="fw-semibold">
                                    Wednesday
                                </td>
                                <td>
                                    10:00 - 22:00
                                </td>
                            </tr>

                            <tr>
                                <td class="fw-semibold">
                                    Thursday
                                </td>
                                <td>
                                    10:00 - 22:00
                                </td>
                            </tr>

                            <tr>
                                <td class="fw-semibold">
                                    Friday
                                </td>
                                <td>
                                    10:00 - 23:00
                                </td>
                            </tr>

                            <tr>
                                <td class="fw-semibold">
                                    Saturday
                                </td>
                                <td>
                                    10:00 - 23:00
                                </td>
                            </tr>

                            <tr>
                                <td class="fw-semibold">
                                    Sunday
                                </td>
                                <td>
                                    11:00 - 21:00
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

<?php include 'includes/footer.php'; ?>