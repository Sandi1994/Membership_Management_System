
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$f_name = '';
$l_name = '';
$email = '';
$registrationSuccess = false;
$registrationUserNo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = isset($_POST['csrf_token'])
        ? (string)$_POST['csrf_token'] : '';

    $sessionToken = isset($_SESSION['csrf_token'])
        ? (string)$_SESSION['csrf_token'] : '';

    if (
        $postedToken === '' ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $postedToken)
    ) {
        $error = 'Invalid request. Please refresh the page and try again.';

    } else {

        $f_name = isset($_POST['f_name'])
            ? trim($_POST['f_name']) : '';

        $l_name = isset($_POST['l_name'])
            ? trim($_POST['l_name']) : '';

        $email = isset($_POST['email'])
            ? trim($_POST['email']) : '';

        $password = isset($_POST['password'])
            ? $_POST['password'] : '';

        $confirm_password = isset($_POST['confirm_password'])
            ? $_POST['confirm_password'] : '';

        if (
            $f_name === '' ||
            $l_name === '' ||
            $email === '' ||
            $password === '' ||
            $confirm_password === ''
        ) {
            $error = 'Please complete all required fields.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        } elseif (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $error = 'Please choose a password that meets all the listed requirements.';

        } elseif ($password !== $confirm_password) {

            $error = 'Passwords do not match.';

        } else {

            $transactionStarted = false;

            $roleStmt = null;
            $checkStmt = null;
            $insertStmt = null;
            $numberStmt = null;
            $verifyStmt = null;
            $columnStmt = null;

            try {

                /*
                 * 1. Find the Non-member role.
                 * Do not hardcode the role ID.
                 */

                $roleSql = "
                    SELECT role_id
                    FROM user_role
                    WHERE LOWER(
                        REPLACE(
                            REPLACE(role_name, ' ', ''),
                            '-',
                            ''
                        )
                    ) = 'nonmember'
                    LIMIT 1
                ";

                $roleStmt = $conn->prepare($roleSql);
                $roleStmt->execute();

                $roleStmt->bind_result($roleIdFromDb);

                $roleFound = $roleStmt->fetch();

                $roleStmt->close();
                $roleStmt = null;

                if (!$roleFound) {
                    throw new Exception(
                        'Non-member role was not found in user_role.'
                    );
                }

                $nonMemberRoleId = (int)$roleIdFromDb;


                /*
                 * 2. Check whether the email already exists.
                 */

                $checkStmt = $conn->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );

                $checkStmt->bind_param('s', $email);
                $checkStmt->execute();
                $checkStmt->store_result();

                $emailExists = ($checkStmt->num_rows > 0);

                $checkStmt->close();
                $checkStmt = null;

                if ($emailExists) {

                    $error = 'This email address is already registered.';

                } else {

                    /*
                     * 3. Hash the password.
                     */

                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    if ($passwordHash === false) {
                        throw new Exception(
                            'Password hashing failed.'
                        );
                    }


                    /*
                     * 4. Start database transaction.
                     */

                    $conn->begin_transaction();
                    $transactionStarted = true;


                    /*
                     * 5. Check the actual user_no column structure.
                     */

                    $columnStmt = $conn->prepare(
                        "SELECT
                            IS_NULLABLE,
                            COLUMN_DEFAULT,
                            EXTRA
                         FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'users'
                           AND COLUMN_NAME = 'user_no'
                         LIMIT 1"
                    );

                    $columnStmt->execute();

                    $columnStmt->bind_result(
                        $userNoNullable,
                        $userNoDefault,
                        $userNoExtra
                    );

                    $columnExists = $columnStmt->fetch();

                    $columnStmt->close();
                    $columnStmt = null;

                    if (!$columnExists) {
                        throw new Exception(
                            'user_no column was not found.'
                        );
                    }

                    $isAutoIncrement = (
                        stripos(
                            (string)$userNoExtra,
                            'auto_increment'
                        ) !== false
                    );

                    $hasDefault = ($userNoDefault !== null);

                    $isNullable = (
                        strtoupper((string)$userNoNullable) === 'YES'
                    );


                    /*
                     * 6. Insert the new user.
                     *
                     * New accounts:
                     * is_active = 1
                     * is_deleted = 0
                     */

                    if ($isAutoIncrement || $hasDefault) {

                        $insertSql = "
                            INSERT INTO users
                            (
                                f_name,
                                l_name,
                                email,
                                password_hash,
                                is_active,
                                created_date,
                                role_id,
                                is_deleted
                            )
                            VALUES (?, ?, ?, ?, 1, NOW(), ?, 0)
                        ";

                        $insertStmt = $conn->prepare($insertSql);

                        $insertStmt->bind_param(
                            'ssssi',
                            $f_name,
                            $l_name,
                            $email,
                            $passwordHash,
                            $nonMemberRoleId
                        );

                    } elseif ($isNullable) {

                        $insertSql = "
                            INSERT INTO users
                            (
                                user_no,
                                f_name,
                                l_name,
                                email,
                                password_hash,
                                is_active,
                                created_date,
                                role_id,
                                is_deleted
                            )
                            VALUES (NULL, ?, ?, ?, ?, 1, NOW(), ?, 0)
                        ";

                        $insertStmt = $conn->prepare($insertSql);

                        $insertStmt->bind_param(
                            'ssssi',
                            $f_name,
                            $l_name,
                            $email,
                            $passwordHash,
                            $nonMemberRoleId
                        );

                    } else {

                        /*
                         * Required user_no with no default.
                         * Generate a temporary number.
                         */

                        $maxResult = $conn->query(
                            "SELECT COALESCE(MAX(user_no), 0) + 1
                             AS temporary_user_no
                             FROM users"
                        );

                        $maxRow = $maxResult->fetch_assoc();

                        $temporaryUserNo = (int)$maxRow['temporary_user_no'];

                        $maxResult->free();

                        $insertSql = "
                            INSERT INTO users
                            (
                                user_no,
                                f_name,
                                l_name,
                                email,
                                password_hash,
                                is_active,
                                created_date,
                                role_id,
                                is_deleted
                            )
                            VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, 0)
                        ";

                        $insertStmt = $conn->prepare($insertSql);

                        $insertStmt->bind_param(
                            'issssi',
                            $temporaryUserNo,
                            $f_name,
                            $l_name,
                            $email,
                            $passwordHash,
                            $nonMemberRoleId
                        );
                    }

                    $insertStmt->execute();

                    $newUserId = (int)$conn->insert_id;

                    if ($newUserId < 1) {
                        throw new Exception(
                            'MySQL did not return a new user ID.'
                        );
                    }


                    /*
                     * 7. Generate user number.
                     *
                     * Existing project convention:
                     * user_no = user_id + 100000
                     */

                    $newUserNo = $newUserId + 100000;

                    $numberStmt = $conn->prepare(
                        "UPDATE users
                         SET user_no = ?
                         WHERE user_id = ?"
                    );

                    $numberStmt->bind_param(
                        'ii',
                        $newUserNo,
                        $newUserId
                    );

                    $numberStmt->execute();

                    $numberStmt->close();
                    $numberStmt = null;


                    /*
                     * 8. Verify generated user number.
                     */

                    $verifyStmt = $conn->prepare(
                        "SELECT user_no
                         FROM users
                         WHERE user_id = ?
                         LIMIT 1"
                    );

                    $verifyStmt->bind_param('i', $newUserId);
                    $verifyStmt->execute();

                    $verifyStmt->bind_result($savedUserNo);

                    $numberSaved = $verifyStmt->fetch();

                    $verifyStmt->close();
                    $verifyStmt = null;

                    if (
                        !$numberSaved ||
                        (int)$savedUserNo !== $newUserNo
                    ) {
                        throw new Exception(
                            'Generated user number could not be saved.'
                        );
                    }


                    /*
                     * 9. Close insert statement.
                     */

                    if ($insertStmt) {
                        $insertStmt->close();
                        $insertStmt = null;
                    }


                    /*
                     * 10. Commit registration.
                     */

                    $conn->commit();
                    $transactionStarted = false;


                    /*
                     * 11. Automatically log in the new user.
                     */

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_no'] = $newUserNo;
                    $_SESSION['email'] = $email;
                    $_SESSION['role_id'] = $nonMemberRoleId;

                    $_SESSION['registration_user_no'] = $newUserNo;
                    $_SESSION['registration_success'] = true;

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: register.php');
                    exit();
                }

            } catch (Throwable $exception) {

                if ($transactionStarted) {
                    try {
                        $conn->rollback();
                    } catch (Throwable $rollbackException) {
                        error_log(
                            'Registration rollback error: ' .
                            $rollbackException->getMessage()
                        );
                    }
                }

                error_log(
                    'Registration Error: ' .
                    $exception->getMessage()
                );

                $error = 'Registration failed. Please try again. Check the PHP error log for the exact cause.';
            }


            /*
             * Close any remaining statements.
             * No finally block is required.
             */

            if ($roleStmt) {
                $roleStmt->close();
            }

            if ($checkStmt) {
                $checkStmt->close();
            }

            if ($insertStmt) {
                $insertStmt->close();
            }

            if ($numberStmt) {
                $numberStmt->close();
            }

            if ($verifyStmt) {
                $verifyStmt->close();
            }

            if ($columnStmt) {
                $columnStmt->close();
            }
        }
    }
}


/*
 * Registration success modal data.
 */

if (isset($_SESSION['registration_success'])) {

    $registrationSuccess = true;

    $registrationUserNo = isset(
        $_SESSION['registration_user_no']
    )
        ? (string)$_SESSION['registration_user_no']
        : '';

    unset(
        $_SESSION['registration_success'],
        $_SESSION['registration_user_no']
    );
}
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Account | Membership Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet"
          href="../assets/css/style.css">

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
                <i class="bi bi-arrow-left"></i>
                Back to login
            </a>

        </div>


        <div class="login-content register-content">

            <div class="login-heading">

                <h2>Create Your Account</h2>

                <p>
                    Join us and start your professional journey.
                </p>

            </div>


            <!-- Error message -->

            <?php if ($error !== ''): ?>

                <div class="alert alert-danger" role="alert">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <p class="small mb-3"
               style="color: #6c757d; font-style: italic;">

                Fields marked with

                <span style="color: #dc3545; font-weight: bold;">
                    *
                </span>

                are required.

            </p>


            <form method="POST" action="">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= htmlspecialchars(
                           $_SESSION['csrf_token'],
                           ENT_QUOTES,
                           'UTF-8'
                       ) ?>">


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
                           value="<?= htmlspecialchars(
                               $f_name,
                               ENT_QUOTES,
                               'UTF-8'
                           ) ?>"
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
                           value="<?= htmlspecialchars(
                               $l_name,
                               ENT_QUOTES,
                               'UTF-8'
                           ) ?>"
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
                           value="<?= htmlspecialchars(
                               $email,
                               ENT_QUOTES,
                               'UTF-8'
                           ) ?>"
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


                    <div class="form-text mt-2"
                         id="passwordRequirements">

                        Your password must include:

                    </div>


                    <ul class="list-unstyled small mt-2 mb-0"
                        id="passwordRules">

                        <li class="password-rule text-muted"
                            data-rule="length">

                            <i class="bi bi-circle me-1"></i>
                            At least 8 characters

                        </li>

                        <li class="password-rule text-muted"
                            data-rule="uppercase">

                            <i class="bi bi-circle me-1"></i>
                            At least one uppercase letter (A–Z)

                        </li>

                        <li class="password-rule text-muted"
                            data-rule="lowercase">

                            <i class="bi bi-circle me-1"></i>
                            At least one lowercase letter (a–z)

                        </li>

                        <li class="password-rule text-muted"
                            data-rule="number">

                            <i class="bi bi-circle me-1"></i>
                            At least one number (0–9)

                        </li>

                        <li class="password-rule text-muted"
                            data-rule="special">

                            <i class="bi bi-circle me-1"></i>
                            At least one special character (e.g. !@#$%)

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

                    <div class="small mt-2"
                         id="passwordMatchMessage"
                         aria-live="polite"></div>

                </div>


                <!-- REGISTER BUTTON -->

                <button type="submit"
                        class="btn login-btn w-100">

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

                <span>
                    Member<span class="brand-highlight">Connect</span>
                </span>

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


                <h4 class="fw-bold mb-2"
                    id="registrationSuccessTitle">

                    Registration Successful!

                </h4>


                <p class="text-muted">

                    Your account has been created successfully.

                </p>


                <div class="application-message">

                    <div class="small text-muted mb-1">

                        Your User Number

                    </div>


                    <div class="fs-3 fw-bold text-success">

                        <?= htmlspecialchars(
                            $registrationUserNo,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                    <p class="small mb-0 mt-2">

                        Keep this number safe. You can use it or
                        your email address to sign in.

                    </p>

                </div>


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


                    <!-- APPLY FOR MEMBERSHIP -->

                    <div class="membership-option apply-option">

                        <div class="mb-3">

                            <i class="bi bi-person-vcard fs-2 text-success"></i>

                        </div>


                        <h6>Apply for Membership</h6>


                        <p>

                            Proceed to the membership application form
                            and explore the available membership categories.

                        </p>


                        <a href="../membership_application/index.php"
                           class="apply-btn">

                            Apply Now

                            <i class="bi bi-arrow-right ms-2"></i>

                        </a>

                    </div>



                    <!-- CONTINUE AS NON-MEMBER -->

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


                        <a href="../index.php"
                           class="continue-btn">

                            Continue

                            <i class="bi bi-arrow-right ms-2"></i>

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- LIVE PASSWORD VALIDATION -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const passwordInput =
        document.getElementById('password');

    const confirmInput =
        document.getElementById('confirm_password');

    const matchMessage =
        document.getElementById('passwordMatchMessage');


    if (!passwordInput || !confirmInput) {
        return;
    }


    const rules = {

        length: value => value.length >= 8,

        uppercase: value => /[A-Z]/.test(value),

        lowercase: value => /[a-z]/.test(value),

        number: value => /[0-9]/.test(value),

        special: value => /[^A-Za-z0-9]/.test(value)

    };


    function validatePassword() {

        const value = passwordInput.value;


        Object.entries(rules).forEach(function ([ruleName, check]) {

            const ruleElement = document.querySelector(
                '.password-rule[data-rule="' + ruleName + '"]'
            );

            if (!ruleElement) {
                return;
            }


            const icon = ruleElement.querySelector('i');

            const isValid = check(value);


            ruleElement.classList.toggle(
                'text-success',
                value.length > 0 && isValid
            );

            ruleElement.classList.toggle(
                'text-muted',
                value.length === 0 || !isValid
            );


            if (icon) {

                icon.className =
                    value.length > 0 && isValid
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

            confirmInput.setCustomValidity(
                'Passwords do not match.'
            );

        }

    }


    passwordInput.addEventListener(
        'input',
        validatePassword
    );

    confirmInput.addEventListener(
        'input',
        validatePasswordMatch
    );

});

</script>


<!-- BOOTSTRAP JAVASCRIPT -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js"></script>


<?php
$showSuccessModal = $registrationSuccess;
?>


<?php if ($showSuccessModal): ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modalElement = document.getElementById(
        'registrationSuccessModal'
    );

    if (modalElement) {

        const successModal = new bootstrap.Modal(
            modalElement,
            {
                backdrop: 'static',
                keyboard: false
            }
        );

        successModal.show();

    }

});

</script>

<?php endif; ?>


</body>
</html>
