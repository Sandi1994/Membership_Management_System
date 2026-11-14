
<?php
session_start();

require_once __DIR__ . '/../config/config.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Invalid request. Please try again.";
    } else {

        $loginIdentifier = isset($_POST['login_identifier'])
        ? trim($_POST['login_identifier'])
        : '';

        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($loginIdentifier === '' || $password === '') {
            $error = "Please enter your user number or email address and password.";
        } else {

            // Users can sign in with their six-digit user number or email address.
            if (ctype_digit($loginIdentifier)) {
                $sql = "SELECT user_id, user_no, email, password_hash, role_id
                        FROM users
                        WHERE user_no = ?
                        LIMIT 1";
                $stmt = $conn->prepare($sql);
                $userNumber = (int) $loginIdentifier;
                $stmt->bind_param("i", $userNumber);
            } else {
                $sql = "SELECT user_id, user_no, email, password_hash, role_id
                        FROM users
                        WHERE email = ?
                        LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $loginIdentifier);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_no'] = $user['user_no'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = $user['role_id'];

                    // Generate a new CSRF token.
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: ../index.php");
                    exit();
                } else {
                    $error = "Invalid user number, email or password.";
                }
            } else {
                $error = "Invalid user number, email or password.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | Membership Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="login-wrapper">

    <!-- LEFT SIDE: LOGIN FORM -->
    <section class="login-section">

        <div class="back-link">
            <a href="../index.php">
                <i class="bi bi-arrow-left"></i> Back to website
            </a>
        </div>

        <div class="login-content">

            <div class="login-heading">
                <h2>Welcome Back!</h2>
                <p>Enter your user number or email address and password to sign in.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- EMAIL ADDRESS / MEMBERSHIP NUMBER -->
                <div class="mb-4">
                    <label for="login_identifier" class="form-label">
                        User Number / Email Address<span class="required">*</span>
                    </label>

                    <div class="input-group custom-input">
                        <span class="input-group-text">
                            <i class="bi bi-person-badge"></i>
                        </span>

                        <input type="text"
                               class="form-control"
                               id="login_identifier"
                               name="login_identifier"
                               placeholder="Enter your user number or email address"
                               value="<?= htmlspecialchars(isset($_POST['login_identifier']) ? $_POST['login_identifier'] : '') ?>"
                               autocomplete="username"
                               required>
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="mb-3">
                    <label for="password" class="form-label">
                        Password<span class="required">*</span>
                    </label>

                    <div class="input-group custom-input">
                        <span class="input-group-text">
                            <i class="bi bi-lock"></i>
                        </span>

                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               placeholder="Enter your password"
                               autocomplete="current-password"
                               required>

                        <button class="input-group-text password-toggle"
                                type="button"
                                id="togglePassword"
                                aria-label="Show password">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- REMEMBER / FORGOT PASSWORD -->
                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               id="remember">

                        <label class="form-check-label" for="remember">
                            Remember me
                        </label>
                    </div>

                    <a href="forgot_password.php" class="forgot-link">
                        Forgot password?
                    </a>

                </div>

                <!-- SIGN IN BUTTON -->
                <button type="submit" class="btn login-btn w-100">
                    Sign In <i class="bi bi-arrow-right ms-2"></i>
                </button>

            </form>

            <!-- Register Link -->
            <div class="register-link">
                <p>
                    Don't have an account?
                    <a href="register.php">Create an Account</a>
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
                    YOUR PROFESSIONAL JOURNEY
                </span>

                <h1>
                    Grow your<br>
                    <span>professional future.</span>
                </h1>

                <p>
                    Welcome to your membership portal. Manage your
                    membership, subscriptions and professional
                    development in one convenient place.
                </p>

                <div class="welcome-features">

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Manage your membership
                    </div>

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Track subscriptions and renewals
                    </div>

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Access your certificates and receipts
                    </div>

                    <div>
                        <i class="bi bi-check-circle-fill"></i>
                        Stay updated with important notifications
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


<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js"></script>

<!-- Show / Hide Password -->
<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePassword.addEventListener('click', function () {

        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';

        eyeIcon.classList.toggle('bi-eye', !isPassword);
        eyeIcon.classList.toggle('bi-eye-slash', isPassword);

        togglePassword.setAttribute(
            'aria-label',
            isPassword ? 'Hide password' : 'Show password'
        );
    });
</script>

</body>
</html>
