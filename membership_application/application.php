
<?php
session_start();

require_once __DIR__ . '/../config/config.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

$f_name = '';
$l_name = '';
$email = '';

// Registration success message
$registrationSuccess = false;

// Process registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Invalid request. Please try again.";

    } else {

        $f_name = trim(isset($_POST['f_name']) ? $_POST['f_name'] : '');
        $l_name = trim(isset($_POST['l_name']) ? $_POST['l_name'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        // Validate required fields
        if (
            $f_name === '' ||
            $l_name === '' ||
            $email === '' ||
            $password === '' ||
            $confirm_password === ''
        ) {
            $error = "Please complete all required fields.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";

        } elseif (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $error = "Please choose a password that meets all the listed requirements.";

        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";

        } else {

            // Find the Non-member role automatically
            $roleSql = "SELECT role_id
                        FROM user_role
                        WHERE LOWER(REPLACE(REPLACE(role_name, ' ', ''), '-', '')) = 'nonmember'
                        LIMIT 1";

            $roleStmt = $conn->prepare($roleSql);
            $roleStmt->execute();

            $roleResult = $roleStmt->get_result();

            if ($roleResult->num_rows !== 1) {

                $error = "The Non-member role is not configured. Please contact the administrator.";

            } else {

                $roleData = $roleResult->fetch_assoc();
                $nonMemberRoleId = (int) $roleData['role_id'];

                // Check whether email already exists
                $checkSql = "SELECT user_id
                             FROM users
                             WHERE email = ?
                             LIMIT 1";

                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();

                $existingUser = $checkStmt->get_result();

                if ($existingUser->num_rows > 0) {

                    $error = "This email address is already registered.";

                } else {

                    // Hash password
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Insert new Non-member account
                    $insertSql = "INSERT INTO users
                        (f_name, l_name, email, password_hash,
                         is_active, created_date, role_id)
                        VALUES (?, ?, ?, ?, 1, NOW(), ?)";

                    $insertStmt = $conn->prepare($insertSql);

                    $insertStmt->bind_param(
                        "ssssi",
                        $f_name,
                        $l_name,
                        $email,
                        $passwordHash,
                        $nonMemberRoleId
                    );

                    if ($insertStmt->execute()) {

                        $newUserId = $conn->insert_id;

                        // Automatically log in the newly registered user
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['email'] = $email;
                        $_SESSION['role_id'] = $nonMemberRoleId;

                        // Generate a fresh CSRF token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        // Redirect to prevent duplicate form submission
                        $_SESSION['registration_success'] = true;

                        header("Location: register.php");
                        exit();

                    } else {
                        $error = "Registration failed. Please try again.";
                    }

                    $insertStmt->close();
                }

                $checkStmt->close();
            }

            $roleStmt->close();
        }
    }
}

// Display success popup after redirect
if (isset($_SESSION['registration_success'])) {
    $registrationSuccess = true;
    unset($_SESSION['registration_success']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | Membership Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .register-content {
            max-width: 440px;
        }

        .register-content .login-heading {
            margin-bottom: 25px;
        }

        .register-content .mb-3 {
            margin-bottom: 17px !important;
        }

        .register-content .login-btn {
            margin-top: 8px;
        }

        /* Registration success modal */

        .success-modal {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .success-modal .modal-body {
            padding: 35px;
        }

        .success-icon {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #dcefe3;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin: 0 auto 20px;
        }

        .application-message {
            background: #edf7f5;
            border-radius: 12px;
            padding: 18px;
            margin: 22px 0;
            color: #465d59;
            font-size: 14px;
        }

        .membership-options {
            display: flex;
            gap: 15px;
            text-align: left;
            margin-top: 22px;
        }

        .membership-option {
            flex: 1;
            min-width: 0;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 14px;
            background: #fff;
        }

        .membership-option.apply-option {
            border: 2px solid #75a987;
            background: #f7fff9;
        }

        .membership-option h6 {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .membership-option p {
            color: #6c757d;
            font-size: 13px;
            min-height: 40px;
        }

        .apply-btn,
        .continue-btn {
            display: block;
            width: 100%;
            border-radius: 9px;
            padding: 11px 12px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
        }

        .apply-btn {
            background: #198754;
            color: #fff;
            border: 1px solid #198754;
        }

        .apply-btn:hover {
            background: #146c43;
            border-color: #146c43;
            color: #fff;
        }

        .continue-btn {
            background: #fff;
            color: #2871b8;
            border: 1px solid #2871b8;
        }

        .continue-btn:hover {
            background: #2871b8;
            color: #fff;
        }

        @media (max-width: 575.98px) {
            .success-modal .modal-body {
                padding: 25px 18px;
            }

            .membership-options {
                flex-direction: column;
            }

            .membership-option p {
                min-height: 0;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <!-- LEFT SIDE: REGISTRATION FORM -->
    <section class="login-section">

        <div class="back-link">
            <a href="login.php">
                <i class="bi bi-arrow-left"></i> Back to login
            </a>
        </div>

        <div class="login-content register-content">

            <div class="login-heading">
                <h2>Membership Application</h2>
                <p>Join us and start your professional journey.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

                <p class="small mb-3" style="color: #6c757d; font-style: italic;">
                    Fields marked with
                    <span style="color: #dc3545; font-weight: bold;">*</span>
                    are required.
                </p>

            <form method="POST" action="">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- FIRST NAME -->
                <div class="mb-3">
                    <label for="f_name" class="form-label">
                        First Name<span class="text-danger">*</span>
                    </label>

                    <input type="text"
                           class="form-control"
                           id="f_name"
                           name="f_name"
                           placeholder="Enter your first name"
                           value="<?= htmlspecialchars($f_name) ?>"
                           maxlength="255"
                           autocomplete="given-name"
                           required>
                </div>

                <!-- LAST NAME -->
                <div class="mb-3">
                    <label for="l_name" class="form-label">
                        Last Name<span class="text-danger">*</span>
                    </label>

                    <input type="text"
                           class="form-control"
                           id="l_name"
                           name="l_name"
                           placeholder="Enter your last name"
                           value="<?= htmlspecialchars($l_name) ?>"
                           maxlength="255"
                           autocomplete="family-name"
                           required>
                </div>

                <!-- EMAIL -->
                <div class="mb-3">
                    <label for="email" class="form-label">
                        Email Address<span class="text-danger">*</span>
                    </label>

                    <input type="email"
                           class="form-control"
                           id="email"
                           name="email"
                           placeholder="Enter your email"
                           value="<?= htmlspecialchars($email) ?>"
                           maxlength="255"
                           autocomplete="email"
                           required>
                </div>

                <!-- NEW PASSWORD -->
                <div class="mb-3">
                    <label for="password" class="form-label">
                        Choose a New Password<span class="text-danger">*</span>
                    </label>

                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="Choose a new password"
                           minlength="8"
                           autocomplete="new-password"
                           aria-describedby="passwordRequirements"
                           required>

                    <div class="form-text mt-2" id="passwordRequirements">
                        Your password must include:
                    </div>

                    <ul class="list-unstyled small mt-2 mb-0" id="passwordRules">
                        <li class="password-rule text-muted" data-rule="length">
                            <i class="bi bi-circle me-1"></i> At least 8 characters
                        </li>
                        <li class="password-rule text-muted" data-rule="uppercase">
                            <i class="bi bi-circle me-1"></i> At least one uppercase letter (A–Z)
                        </li>
                        <li class="password-rule text-muted" data-rule="lowercase">
                            <i class="bi bi-circle me-1"></i> At least one lowercase letter (a–z)
                        </li>
                        <li class="password-rule text-muted" data-rule="number">
                            <i class="bi bi-circle me-1"></i> At least one number (0–9)
                        </li>
                        <li class="password-rule text-muted" data-rule="special">
                            <i class="bi bi-circle me-1"></i> At least one special character (e.g. !@#$%)
                        </li>
                    </ul>
                </div>

                <!-- CONFIRM NEW PASSWORD -->
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">
                        Confirm New Password<span class="text-danger">*</span>
                    </label>

                    <input type="password"
                           class="form-control"
                           id="confirm_password"
                           name="confirm_password"
                           placeholder="Enter your new password again"
                           minlength="8"
                           autocomplete="new-password"
                           aria-describedby="passwordMatchMessage"
                           required>

                    <div class="small mt-2" id="passwordMatchMessage" aria-live="polite"></div>
                </div>

                <!-- REGISTER BUTTON -->
                <button type="submit" class="btn login-btn w-100">
                    Create Account
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>

            </form>

            <div class="register-link">
                <p>
                    Already have an account?
                    <a href="login.php">Sign In</a>
                </p>
            </div>

            <div class="secure-note">
                <i class="bi bi-shield-check"></i>
                Secure Membership Portal
            </div>

        </div>

        <footer class="login-footer">
            © 2026 Membership and Subscription Management System.
            All rights reserved.
        </footer>

    </section>


    <!-- RIGHT SIDE: WELCOME PANEL -->
    <section class="welcome-section">

        <div class="welcome-decoration decoration-one"></div>
        <div class="welcome-decoration decoration-two"></div>

        <div class="welcome-content">

            <div class="brand-logo">
                <div class="brand-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>

                <span>Member<span class="brand-highlight">Connect</span></span>
            </div>

            <div class="welcome-message">

                <span class="welcome-tag">
                    BECOME A PART OF OUR COMMUNITY
                </span>

                <h1>
                    Your journey<br>
                    <span>begins here.</span>
                </h1>

                <p>
                    Create your account to explore membership
                    opportunities, submit applications and manage
                    your professional membership.
                </p>

                <div class="welcome-features">

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Simple account registration
                    </div>

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Apply for membership online
                    </div>

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Manage your membership journey
                    </div>

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Access your account anytime
                    </div>

                </div>

            </div>

            <div class="welcome-bottom">
                <span>MEMBERSHIP</span>
                <span class="bottom-dot"></span>
                <span>DEVELOPMENT</span>
                <span class="bottom-dot"></span>
                <span>EXCELLENCE</span>
            </div>

        </div>

    </section>

</div>


<!-- REGISTRATION SUCCESS MODAL -->
<div class="modal fade"
     id="registrationSuccessModal"
     tabindex="-1"
     aria-labelledby="registrationSuccessTitle"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content success-modal">

            <div class="modal-body text-center">

                <div class="success-icon">
                    <i class="bi bi-check-lg"></i>
                </div>

                <h4 class="fw-bold mb-2" id="registrationSuccessTitle">
                    Registration Successful!
                </h4>

                <p class="text-muted">
                    Your account has been created successfully.
                </p>

                <div class="application-message">
                    <h5 class="fw-bold mb-2">
                        Would you like to apply for membership now?
                    </h5>

                    <p class="mb-0">
                        You can submit a membership application now or
                        continue using the system as a non-member.
                    </p>
                </div>

                <div class="membership-options">

                    <div class="membership-option apply-option">
                        <div class="mb-3">
                            <i class="bi bi-person-vcard fs-2 text-success"></i>
                        </div>

                        <h6>Apply for Membership</h6>

                        <p>
                            Proceed to the membership application form
                            and explore the available membership categories.
                        </p>

                        <!-- Keep or update this URL to your membership application page -->
                        <a href="../membership_application/index.php"
                           class="apply-btn">
                            Apply Now
                            <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>

                    <div class="membership-option">
                        <div class="mb-3">
                            <i class="bi bi-people fs-2 text-primary"></i>
                        </div>

                        <h6>Continue as Non-Member</h6>

                        <p>
                            Continue to the system and use your account
                            as a non-member. You can apply for membership
                            later whenever you're ready.
                        </p>

                        <!-- Keep or update this URL to your non-member landing page -->
                        <a href="../index.php" class="continue-btn">
                            Continue
                            <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- Live password validation -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('passwordMatchMessage');

    if (!passwordInput || !confirmInput) return;

    const rules = {
        length: value => value.length >= 8,
        uppercase: value => /[A-Z]/.test(value),
        lowercase: value => /[a-z]/.test(value),
        number: value => /[0-9]/.test(value),
        special: value => /[^A-Za-z0-9]/.test(value)
    };

    function validatePassword() {
        const value = passwordInput.value;

        Object.entries(rules).forEach(([ruleName, check]) => {
            const ruleElement = document.querySelector(
                '.password-rule[data-rule="' + ruleName + '"]'
            );
            if (!ruleElement) return;

            const icon = ruleElement.querySelector('i');
            const isValid = check(value);

            ruleElement.classList.toggle('text-success', value.length > 0 && isValid);
            ruleElement.classList.toggle('text-muted', value.length === 0 || !isValid);

            if (icon) {
                icon.className = value.length > 0 && isValid
                    ? 'bi bi-check-circle-fill me-1'
                    : 'bi bi-circle me-1';
            }
        });

        passwordInput.setCustomValidity(
            Object.values(rules).every(check => check(value))
                ? ''
                : 'Please meet all password requirements.'
        );

        validatePasswordMatch();
    }

    function validatePasswordMatch() {
        const password = passwordInput.value;
        const confirmation = confirmInput.value;

        if (confirmation.length === 0) {
            matchMessage.textContent = '';
            matchMessage.className = 'small mt-2';
            confirmInput.setCustomValidity('');
            return;
        }

        if (password === confirmation) {
            matchMessage.textContent = 'Passwords match.';
            matchMessage.className = 'small mt-2 text-success';
            confirmInput.setCustomValidity('');
        } else {
            matchMessage.textContent = 'Passwords do not match.';
            matchMessage.className = 'small mt-2 text-danger';
            confirmInput.setCustomValidity('Passwords do not match.');
        }
    }

    passwordInput.addEventListener('input', validatePassword);
    confirmInput.addEventListener('input', validatePasswordMatch);
});
</script>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js"></script>

<?php
// The success flag was already read and cleared after the redirect above.
$showSuccessModal = $registrationSuccess;
?>

<?php if ($showSuccessModal): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('registrationSuccessModal');

        if (modalElement) {
            const successModal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });

            successModal.show();
        }
    });
</script>
<?php endif; ?>

</body>
</html>
    