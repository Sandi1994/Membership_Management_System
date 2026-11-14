<!-- 
<?php

require_once __DIR__ . '/config/config.php';

echo "Database connected successfully!";

?> -->

<?php
session_start();

require_once __DIR__ . '/config/config.php';

$baseUrl = '';
$currentPage = 'home';

$isLoggedIn = isset($_SESSION['user_id']);
$accountUser = null;

if ($isLoggedIn) {
    $userId = (int) $_SESSION['user_id'];

    $profileSql = "SELECT u.f_name, u.l_name, u.email, u.user_no, m.membership_no
                   FROM users u
                   LEFT JOIN members m ON m.user_id = u.user_id
                   WHERE u.user_id = ?
                   LIMIT 1";

    $profileStmt = $conn->prepare($profileSql);
    $profileStmt->bind_param("i", $userId);
    $profileStmt->execute();
    $profileResult = $profileStmt->get_result();
    $accountUser = $profileResult->fetch_assoc();
    $profileStmt->close();
}

$firstName = isset($accountUser['f_name'])
    ? $accountUser['f_name'] : '';

$lastName = isset($accountUser['l_name'])
    ? $accountUser['l_name'] : '';

$fullName = trim($firstName . ' ' . $lastName);

$email = isset($accountUser['email'])
    ? $accountUser['email']
    : (isset($_SESSION['email']) ? $_SESSION['email'] : '');

$userNo = isset($accountUser['user_no'])
    ? $accountUser['user_no'] : '';

$membershipNo = isset($accountUser['membership_no'])
    ? $accountUser['membership_no'] : '';

$initials = '';

if ($firstName !== '') {
    $initials .= strtoupper(substr($firstName, 0, 1));
}

if ($lastName !== '') {
    $initials .= strtoupper(substr($lastName, 0, 1));
}

if ($initials === '') {
    $initials = 'U';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Membership Management System</title>

    <!-- Local Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

<style>
    .account-avatar {
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        color: #087f7d;
        font-size: 13px;
        font-weight: 700;
    }

    .account-avatar-large {
        width: 48px;
        height: 48px;
        min-width: 48px;
        background: #e6f5f4;
        font-size: 16px;
    }

    .account-dropdown {
        width: 290px;
        border-radius: 14px;
    }

    .account-detail {
        overflow-wrap: anywhere;
        font-size: 14px;
    }
</style>

</head>

<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg home-navbar">
    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-mortarboard-fill me-2"></i>
            Membership System
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse d-flex align-items-center" id="navbarMenu">

            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="#home">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#about">About</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#categories">Membership</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#benefits">Benefits</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#contact">Contact</a>
                </li>
            </ul>

            <div class="d-flex gap-2" style="margin-left: auto !important; flex-shrink: 0;">
                <?php if ($isLoggedIn): ?>
                    <div class="dropdown">
                        <button class="btn btn-home-primary dropdown-toggle d-flex align-items-center"
                                type="button"
                                id="accountDropdown"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            <span class="account-avatar me-2"><?= htmlspecialchars($initials) ?></span>
                            <span>My Account</span>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end account-dropdown shadow border-0 p-3"
                             aria-labelledby="accountDropdown">
                            <div class="d-flex align-items-center mb-3">
                                <span class="account-avatar account-avatar-large me-3">
                                    <?= htmlspecialchars($initials) ?>
                                </span>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($fullName ?: 'Account') ?></div>
                                    <small class="text-muted">My Account</small>
                                </div>
                            </div>

                            <div class="account-detail">
                                <small class="text-muted d-block">User Number</small>
                                <span><?= htmlspecialchars((string) $userNo) ?></span>
                            </div>
                            <?php if ($membershipNo !== ''): ?>
                                <div class="account-detail mt-2">
                                    <small class="text-muted d-block">Membership Number</small>
                                    <span><?= htmlspecialchars((string) $membershipNo) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="account-detail mt-2">
                                <small class="text-muted d-block">Email Address</small>
                                <span><?= htmlspecialchars($email) ?></span>
                            </div>

                            <hr>
                            <a class="dropdown-item rounded py-2" href="dashboard.php">
                                <i class="bi bi-grid me-2"></i> My Dashboard
                            </a>
                            <a class="dropdown-item rounded py-2 text-success" href="auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-home-outline">
                        Sign In
                    </a>

                    <a href="auth/register.php" class="btn btn-home-primary">
                        Register
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>


<!-- Hero Section -->
<section class="hero-section" id="home">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-6">

                <span class="hero-label">
                    <i class="bi bi-stars me-2"></i>
                    Membership & Subscription Management
                </span>

                <h1 class="hero-title mt-4">
                    Build Your Future.
                    <span>Grow Together.</span>
                </h1>

                <p class="hero-description mt-3">
                    Join a professional community dedicated to knowledge,
                    development and excellence in the finance industry.
                    Manage your membership journey with ease.
                </p>

                <div class="d-flex flex-wrap gap-3 mt-4">

                    <a href="auth/register.php"
                       class="btn btn-home-primary btn-lg">
                        Get Started
                        <i class="bi bi-arrow-right ms-2"></i>
                    </a>

                    <a href="#categories"
                       class="btn btn-home-outline btn-lg">
                        Explore Membership
                    </a>

                </div>

                <div class="hero-note mt-4">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Your professional journey starts here.
                </div>

            </div>

            <div class="col-lg-6">

                <div class="hero-visual">

                    <div class="hero-icon">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>

                    <div class="hero-floating-card floating-card-one">
                        <i class="bi bi-person-check-fill"></i>
                        Professional Growth
                    </div>

                    <div class="hero-floating-card floating-card-two">
                        <i class="bi bi-award-fill"></i>
                        Recognise Your Potential
                    </div>

                    <div class="hero-visual-text">
                        <h3>Connect. Learn. Achieve.</h3>
                        <p>Be part of a growing professional community.</p>
                    </div>

                </div>

            </div>

        </div>
    </div>
</section>


<!-- About Section -->
<section class="about-section py-5" id="about">
    <div class="container">

        <div class="section-heading text-center mb-5">
            <span class="section-label">ABOUT US</span>

            <h2>Supporting Your Professional Journey</h2>

            <p>
                A platform designed to simplify membership
                and subscription management.
            </p>
        </div>

        <div class="row align-items-center g-4">

            <div class="col-lg-6">
                <div class="about-illustration">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>

            <div class="col-lg-6">

                <h3>A Community for Professional Excellence</h3>

                <p>
                    The Membership and Subscription Management System
                    provides a convenient way to register, apply for
                    membership, manage subscriptions and track payments.
                </p>

                <p>
                    Whether you are starting your professional journey
                    or looking to advance your career, explore the
                    membership opportunities available.
                </p>

                <a href="#categories" class="text-link">
                    Explore membership categories
                    <i class="bi bi-arrow-right ms-2"></i>
                </a>

            </div>

        </div>
    </div>
</section>


<!-- Membership Categories -->
<section class="categories-section py-5" id="categories">
    <div class="container">

        <div class="section-heading text-center mb-5">
            <span class="section-label">MEMBERSHIP</span>

            <h2>Find Your Membership Category</h2>

            <p>
                Explore the available membership categories
                and find the right path for your career.
            </p>
        </div>

        <div class="row g-4">

            <div class="col-md-6 col-lg-4">
                <div class="membership-card">
                    <div class="membership-icon green-icon">
                        <i class="bi bi-book-fill"></i>
                    </div>

                    <h4>Student</h4>

                    <p>
                        For students interested in developing
                        their knowledge and skills in finance.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="membership-card">
                    <div class="membership-icon yellow-icon">
                        <i class="bi bi-person-fill"></i>
                    </div>

                    <h4>Affiliate</h4>

                    <p>
                        For individuals interested in becoming
                        part of the professional community.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="membership-card">
                    <div class="membership-icon green-icon">
                        <i class="bi bi-person-check-fill"></i>
                    </div>

                    <h4>Associate</h4>

                    <p>
                        For professionals looking to strengthen
                        their professional development.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-6">
                <div class="membership-card">
                    <div class="membership-icon yellow-icon">
                        <i class="bi bi-award-fill"></i>
                    </div>

                    <h4>Member</h4>

                    <p>
                        For qualified professionals who meet
                        the relevant membership requirements.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-6">
                <div class="membership-card">
                    <div class="membership-icon green-icon">
                        <i class="bi bi-trophy-fill"></i>
                    </div>

                    <h4>Fellow</h4>

                    <p>
                        For experienced professionals who meet
                        the relevant fellowship requirements.
                    </p>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- Benefits Section -->
<section class="benefits-section py-5" id="benefits">
    <div class="container">

        <div class="section-heading text-center mb-5">
            <span class="section-label">WHY JOIN US?</span>

            <h2>Make Your Membership Journey Easier</h2>
        </div>

        <div class="row g-4">

            <div class="col-md-4">
                <div class="benefit-card">
                    <i class="bi bi-laptop"></i>

                    <h4>Easy Online Access</h4>

                    <p>
                        Access your membership information
                        through one convenient platform.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="benefit-card">
                    <i class="bi bi-file-earmark-check"></i>

                    <h4>Simple Applications</h4>

                    <p>
                        Submit membership applications and
                        keep track of their progress.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="benefit-card">
                    <i class="bi bi-credit-card"></i>

                    <h4>Payment Management</h4>

                    <p>
                        Manage subscription payments and
                        access your payment information.
                    </p>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- Call To Action -->
<section class="cta-section py-5">
    <div class="container">

        <div class="cta-content text-center">

            <span class="section-label">GET STARTED</span>

            <h2>Ready to Begin Your Professional Journey?</h2>

            <p>
                Create your account today and explore
                the membership opportunities available.
            </p>

            <a href="auth/register.php"
               class="btn btn-home-primary btn-lg mt-3">
                Create Your Account
                <i class="bi bi-arrow-right ms-2"></i>
            </a>

            <p class="cta-note mt-3">
                Already have an account?
                <a href="auth/login.php">Sign in here</a>
            </p>

        </div>

    </div>
</section>


<!-- Footer -->
<footer class="home-footer" id="contact">
    <div class="container">

        <div class="row g-4">

            <div class="col-md-6">
                <h5>
                    <i class="bi bi-mortarboard-fill me-2"></i>
                    Membership System
                </h5>

                <p>
                    Simplifying membership and subscription
                    management for finance professionals.
                </p>
            </div>

            <div class="col-md-3">
                <h6>Quick Links</h6>

                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#categories">Membership</a></li>
                    <li><a href="#benefits">Benefits</a></li>
                </ul>
            </div>

            <div class="col-md-3">
                <h6>Account</h6>

                <ul class="footer-links">
                    <li><a href="auth/login.php">Sign In</a></li>
                    <li><a href="auth/register.php">Register</a></li>
                </ul>
            </div>

        </div>

        <hr>

        <div class="text-center footer-bottom">
            <p>
                &copy; <?php echo date('Y'); ?>
                Membership and Subscription Management System.
                All rights reserved.
            </p>
        </div>

    </div>
</footer>


<script src="assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>
