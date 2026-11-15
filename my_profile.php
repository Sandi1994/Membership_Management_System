
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/config/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Escape output
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$userId = (int)$_SESSION['user_id'];

$error = '';
$success = '';
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

// CSRF token
if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

// Retrieve logged-in user's information
function getProfile($conn, $userId) {

    $stmt = $conn->prepare(
        "SELECT
            u.user_id,
            u.user_no,
            u.f_name,
            u.l_name,
            u.email,
            u.role_id,
            u.is_active,
            u.is_deleted,
            ur.role_name
         FROM users u
         LEFT JOIN user_role ur
            ON ur.role_id = u.role_id
         WHERE u.user_id = ?
         LIMIT 1"
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    return $user;
}

try {

    $user = getProfile($conn, $userId);

    if (
        !$user ||
        (int)$user['is_deleted'] === 1 ||
        (int)$user['is_active'] !== 1
    ) {
        session_unset();
        session_destroy();

        header("Location: auth/login.php");
        exit();
    }

} catch (Throwable $exception) {

    error_log('Profile retrieval error: ' . $exception->getMessage());

    http_response_code(500);
    exit('Unable to load your profile. Please try again later.');
}


// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$postedToken = isset($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : '';

    if (
        !hash_equals(
            $_SESSION['profile_csrf'],
            $postedToken
        )
    ) {

        $error = 'Invalid request. Please refresh the page and try again.';

    } else {

            $firstName = isset($_POST['f_name'])
                ? trim($_POST['f_name'])
                : '';

            $lastName = isset($_POST['l_name'])
                ? trim($_POST['l_name'])
                : '';

            $email = isset($_POST['email'])
                ? trim($_POST['email'])
                : '';
        if (
            $firstName === '' ||
            $lastName === '' ||
            $email === ''
        ) {

            $error = 'Please complete all required fields.';

        } elseif (
            mb_strlen($firstName) > 255 ||
            mb_strlen($lastName) > 255 ||
            mb_strlen($email) > 255
        ) {

            $error = 'One or more fields exceed the maximum allowed length.';

        } elseif (
            !preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u", $firstName) ||
            !preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u", $lastName)
        ) {

            $error = 'Please enter valid first and last names.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        } else {

            try {

                // Check whether another user already uses this email.
                $checkStmt = $conn->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE email = ?
                       AND user_id <> ?
                     LIMIT 1"
                );

                $checkStmt->bind_param(
                    'si',
                    $email,
                    $userId
                );

                $checkStmt->execute();

                $checkResult = $checkStmt->get_result();

                $emailExists = $checkResult->num_rows > 0;

                $checkStmt->close();

                if ($emailExists) {

                    $error = 'This email address is already registered to another account.';

                } else {

                    // Update only the logged-in user's own record.
                    $updateStmt = $conn->prepare(
                        "UPDATE users
                         SET f_name = ?,
                             l_name = ?,
                             email = ?
                         WHERE user_id = ?
                           AND is_deleted = 0
                           AND is_active = 1"
                    );

                    $updateStmt->bind_param(
                        'sssi',
                        $firstName,
                        $lastName,
                        $email,
                        $userId
                    );

                    $updateStmt->execute();

                    $updateStmt->close();

                    // Refresh session information.
                    $_SESSION['email'] = $email;

                    // Fetch updated details.
                    $user = getProfile($conn, $userId);

                    // Prevent form resubmission.
                    $_SESSION['profile_success'] =
                        'Your profile has been updated successfully.';

                    header("Location: my_profile.php");
                    exit();
                }

            } catch (Throwable $exception) {

                error_log(
                    'Profile update error: ' .
                    $exception->getMessage()
                );

                $error = 'Unable to update your profile. Please try again.';
            }
        }

        // Preserve entered values when validation fails.
        $user['f_name'] = $firstName;
        $user['l_name'] = $lastName;
        $user['email'] = $email;

        $editMode = true;
    }
}


// Success message after redirect
if (isset($_SESSION['profile_success'])) {

    $success = $_SESSION['profile_success'];

    unset($_SESSION['profile_success']);
}


// Profile display values
$fullName = trim(
    $user['f_name'] . ' ' . $user['l_name']
);

$initials = strtoupper(
    mb_substr($user['f_name'], 0, 1) .
    mb_substr($user['l_name'], 0, 1)
);

if ($initials === '') {
    $initials = 'U';
}

$roleLabel = $user['role_name'] ?: 'User';

$accountStatus = (
    (int)$user['is_active'] === 1
) ? 'Active' : 'Inactive';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile | Membership System</title>

    <!-- Bootstrap -->
    <link rel="stylesheet"
          href="assets/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>

        :root {
            --deep-green: #344b3c;
            --text-green: #304b3d;
            --teal: #087f78;
            --light-green: #dcefe2;
            --cream: #fffdf4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(
                115deg,
                #dcefe0 0%,
                #e9efc8 48%,
                #f8e9a5 100%
            );
            background-attachment: fixed;
            font-family: system-ui, sans-serif;
            color: var(--text-green);
        }

        /* Top navigation */

        .profile-nav {
            min-height: 76px;
            background: rgba(255,253,244,0.96);
            border-bottom: 1px solid rgba(255,255,255,0.8);
            padding: 14px 35px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .brand {
            color: var(--deep-green);
            font-size: 22px;
            font-weight: 800;
            text-decoration: none;
        }

        .brand i {
            color: var(--deep-green);
            margin-right: 8px;
        }

        .back-dashboard {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 18px;
            background: #0f5d63;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
        }

        .back-dashboard:hover {
            background: #087f78;
            color: #fff;
            transform: translateY(-2px);
        }

        /* Main page */

        .profile-container {
            max-width: 1050px;
            margin: 45px auto;
            padding: 0 22px 45px;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 29px;
            font-weight: 800;
            color: var(--deep-green);
            margin-bottom: 7px;
        }

        .page-heading p {
            color: #64786b;
            font-size: 14px;
            margin: 0;
        }

        /* Main card */

        .profile-card {
            background: rgba(255,255,249,0.97);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(47,73,54,0.09);
        }

        /* Profile banner */

        .profile-banner {
            padding: 32px 35px;
            background: linear-gradient(
                120deg,
                #e3f0df,
                #f2f3d9
            );

            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;

            border-bottom: 1px solid #e6eddf;
        }

        .profile-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar {
            width: 88px;
            height: 88px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #fffdf4;
            border: 4px solid rgba(255,255,255,0.8);
            color: var(--teal);

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 27px;
            font-weight: 800;
            box-shadow: 0 5px 15px rgba(47,73,54,0.08);
        }

        .profile-user h2 {
            font-size: 23px;
            font-weight: 800;
            color: var(--deep-green);
            margin: 0 0 7px;
        }

        .profile-user p {
            color: #66796b;
            font-size: 13px;
            margin: 0 0 9px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #d3ebdf;
            color: #087f78;
            padding: 6px 13px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 750;
        }

        /* Buttons */

        .edit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #0f5d63;
            border: none;
            color: white;
            padding: 12px 21px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s;
        }

        .edit-button:hover {
            background: var(--teal);
            color: white;
            transform: translateY(-2px);
        }

        /* Content */

        .profile-content {
            padding: 35px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 11px;
            color: var(--deep-green);
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 23px;
        }

        .section-title i {
            width: 36px;
            height: 36px;
            background: #e0f0e5;
            color: var(--teal);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 17px;
        }

        /* Detail boxes */


        /* =================================
        VIEW PROFILE - SAGE & EMERALD THEME
        ================================= */

        .profile-content {
            background: linear-gradient(
                135deg,
                #e6f0df,
                #dcefe2,
                #c7dfa2
            );
            padding: 35px;
        }

        /* Information cards */

        .detail-box {
            background: linear-gradient(
                145deg,
                #f8fbf3,
                #eaf3e5
            );
            border: 1px solid #b9d3bc;
            border-radius: 15px;
            padding: 21px 20px;
            height: 100%;
            box-shadow: 0 5px 14px rgba(52,75,60,0.05);
            transition: all 0.25s ease;
        }

        /* Hover effect */

        .detail-box:hover {
            background: #ffffff;
            border-color: #087f78;
            transform: translateY(-3px);
            box-shadow: 0 9px 20px rgba(8,127,120,0.10);
        }

        /* Field labels */

        .detail-label {
            display: block;
            color: #526b59;
            font-size: 12px;
            font-weight: 750;
            margin-bottom: 11px;
            letter-spacing: 0.3px;
        }

        /* Field values */

        .detail-value {
            color: #344b3c;
            font-size: 15px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        /* Teal icons */

        .detail-value i {
            color: #087f78;
            margin-right: 9px;
            font-size: 17px;
        }

        /* Section heading */

        .section-title {
            color: #344b3c;
            font-size: 19px;
            font-weight: 800;
        }

        .section-title i {
            background: #087f78;
            color: #ffffff;
            width: 42px;
            height: 42px;
            border-radius: 12px;
        }

        /* Account status */

        .account-status {
            background: #d0ebd9;
            color: #087f78;
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 750;
        }


        .detail-box:hover {
            border-color: #c4ddcc;
            background: #f3f8ef;
        }

        .detail-label {
            display: block;
            font-size: 11px;
            font-weight: 650;
            color: #788a7b;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .detail-value {
            color: var(--deep-green);
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .detail-value i {
            color: var(--teal);
            margin-right: 7px;
        }

        .required-star {
            color: #dc3545;
        }

        /* Account status */

        .account-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #e0f2e6;
            color: #198754;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            background: #198754;
            border-radius: 50%;
        }

        /* Footer */

        .profile-footer {
            border-top: 1px solid #edf0e8;
            padding: 18px 35px;
            color: #849185;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .profile-footer i {
            color: var(--teal);
            margin-right: 5px;
        }

        @media(max-width: 768px) {

            .profile-container {
                margin-top: 28px;
            }

            .profile-banner {
                padding: 25px 22px;
            }

            .profile-content {
                padding: 25px 22px;
            }

            .profile-footer {
                padding: 18px 22px;
            }

        }

        @media(max-width: 480px) {

            .profile-nav {
                padding: 13px 15px;
            }

            .brand {
                font-size: 17px;
            }

            .back-dashboard {
                padding: 10px 12px;
                font-size: 11px;
            }

            .profile-user {
                gap: 13px;
            }

            .profile-avatar {
                width: 67px;
                height: 67px;
                font-size: 22px;
            }

            .profile-user h2 {
                font-size: 19px;
            }

            .page-heading h1 {
                font-size: 25px;
            }

        }

    </style>

</head>

<body>

<!-- TOP NAVIGATION -->

<header class="profile-nav">

    <a href="dashboard.php" class="brand">

        <i class="bi bi-mortarboard-fill"></i>
        Membership System

    </a>

    <a href="dashboard.php" class="back-dashboard">

        <i class="bi bi-arrow-left"></i>
        Back to Dashboard

    </a>

</header>


<!-- MAIN CONTENT -->

<main class="profile-container">

    <div class="page-heading">

        <h1>My Profile</h1>

        <p>
            Manage your personal information and account details.
        </p>

    </div>


    <div class="profile-card">


        <!-- PROFILE HEADER -->

        <div class="profile-banner">

            <div class="profile-user">

                <div class="profile-avatar">

                    <?= e($initials) ?>

                </div>

                <div>

                    <h2><?= e($fullName) ?></h2>

                    <p>
                        User Number:
                        <strong><?= e($user['user_no']) ?></strong>
                    </p>

                    <span class="role-badge">

                        <i class="bi bi-person-check-fill"></i>

                        <?= e($roleLabel) ?>

                    </span>

                </div>

            </div>


            <?php if (!$editMode): ?>

                <a href="my_profile.php?edit=1"
                   class="edit-button">

                    <i class="bi bi-pencil-square"></i>
                    Edit Profile

                </a>

            <?php endif; ?>

        </div>


        <!-- PROFILE DETAILS -->

        <div class="profile-content">


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger rounded-3">

                    <i class="bi bi-exclamation-circle me-2"></i>

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <?php if ($success !== ''): ?>

                <div class="alert alert-success rounded-3">

                    <i class="bi bi-check-circle-fill me-2"></i>

                    <?= e($success) ?>

                </div>

            <?php endif; ?>


            <?php if (!$editMode): ?>


                <!-- VIEW MODE -->

                <div class="section-title">

                    <i class="bi bi-person-lines-fill"></i>

                    Personal Information

                </div>


                <div class="row g-3">


                    <div class="col-md-6">

                        <div class="detail-box">

                            <span class="detail-label">
                                User Number
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-person-badge"></i>
                                <?= e($user['user_no']) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-box">

                            <span class="detail-label">
                                First Name
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-person"></i>
                                <?= e($user['f_name']) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-box">

                            <span class="detail-label">
                                Last Name
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-person"></i>
                                <?= e($user['l_name']) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-box">

                            <span class="detail-label">
                                Email Address
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-envelope"></i>
                                <?= e($user['email']) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-box">

                            <span class="detail-label">
                                Account Role
                            </span>

                            <div class="detail-value">
                                <i class="bi bi-shield-check"></i>
                                <?= e($roleLabel) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-box">

                            <span class="detail-label">
                                Account Status
                            </span>

                            <div class="detail-value">

                                <span class="account-status">

                                    <span class="status-dot"></span>

                                    <?= e($accountStatus) ?>

                                </span>

                            </div>

                        </div>

                    </div>


                </div>


                <div class="mt-4">

                    <!-- <a href="my_profile.php?edit=1"
                       class="edit-button">

                        <i class="bi bi-pencil-square"></i>
                        Edit My Information

                    </a> -->

                </div>


            <?php else: ?>


                <!-- EDIT MODE -->

                <div class="section-title">

                    <i class="bi bi-pencil-square"></i>

                    Edit Personal Information

                </div>


                <p class="text-muted small mb-4">

                    Update your personal details below.
                    Fields marked with
                    <span class="required-star">*</span>
                    are required.

                </p>


                <form method="POST"
                      action="my_profile.php?edit=1"
                      class="profile-form">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($_SESSION['profile_csrf']) ?>">


                    <div class="row g-4">


                        <!-- USER NUMBER -->

                        <div class="col-md-6">

                            <label class="form-label">
                                User Number
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-person-badge"></i>
                                </span>

                                <input type="text"
                                       class="form-control readonly-field"
                                       value="<?= e($user['user_no']) ?>"
                                       readonly>

                            </div>

                            <small class="text-muted">
                                User number cannot be changed.
                            </small>

                        </div>


                        <!-- ROLE -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Account Role
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-shield-check"></i>
                                </span>

                                <input type="text"
                                       class="form-control readonly-field"
                                       value="<?= e($roleLabel) ?>"
                                       readonly>

                            </div>

                            <small class="text-muted">
                                Account role cannot be changed here.
                            </small>

                        </div>


                        <!-- FIRST NAME -->

                        <div class="col-md-6">

                            <label for="f_name"
                                   class="form-label">

                                First Name
                                <span class="required-star">*</span>

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>

                                <input type="text"
                                       class="form-control"
                                       id="f_name"
                                       name="f_name"
                                       value="<?= e($user['f_name']) ?>"
                                       placeholder="Enter first name"
                                       maxlength="255"
                                       autocomplete="given-name"
                                       required>

                            </div>

                        </div>


                        <!-- LAST NAME -->

                        <div class="col-md-6">

                            <label for="l_name"
                                   class="form-label">

                                Last Name
                                <span class="required-star">*</span>

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>

                                <input type="text"
                                       class="form-control"
                                       id="l_name"
                                       name="l_name"
                                       value="<?= e($user['l_name']) ?>"
                                       placeholder="Enter last name"
                                       maxlength="255"
                                       autocomplete="family-name"
                                       required>

                            </div>

                        </div>


                        <!-- EMAIL -->

                        <div class="col-12">

                            <label for="email"
                                   class="form-label">

                                Email Address
                                <span class="required-star">*</span>

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>

                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       value="<?= e($user['email']) ?>"
                                       placeholder="Enter email address"
                                       maxlength="255"
                                       autocomplete="email"
                                       required>

                            </div>

                            <small class="text-muted">
                                Your email must not already belong to another account.
                            </small>

                        </div>


                    </div>


                    <!-- ACTION BUTTONS -->

                    <div class="d-flex justify-content-end flex-wrap gap-3 mt-5">

                        <a href="my_profile.php"
                           class="cancel-button">

                            <i class="bi bi-x-lg me-1"></i>
                            Cancel

                        </a>

                        <button type="submit"
                                class="save-button">

                            <i class="bi bi-check-circle me-1"></i>
                            Save Changes

                        </button>

                    </div>

                </form>


            <?php endif; ?>


        </div>


        <!-- FOOTER -->

        <div class="profile-footer">

            <span>
                <i class="bi bi-shield-check"></i>
                Your account information is protected.
            </span>

            <span>
                Membership and Subscription Management System
            </span>

        </div>


    </div>

</main>

</body>
</html>
