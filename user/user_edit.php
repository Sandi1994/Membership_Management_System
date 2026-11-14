<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Role permissions:
// 3 = Membership Manager
// 4 = Membership Ops
$roleId = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
$isManager = ($roleId === 3);
$isOps = ($roleId === 4);

if (!$isManager && !$isOps) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

// The page URL uses user_no. The internal user_id is still used in UPDATE.
$userNo = 0;

if (isset($_GET['user_no'])) {
    $userNo = (int)$_GET['user_no'];
} elseif (isset($_POST['user_no'])) {
    $userNo = (int)$_POST['user_no'];
}

if ($userNo < 1) {
    header('Location: user_find.php?error=invalid_target');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$errors = array();
$success = false;

// Load account by user_no. Membership details are intentionally not joined here.
$stmt = $conn->prepare(
    'SELECT u.user_id, u.user_no, u.f_name, u.l_name, u.email,
            u.is_active, u.is_deleted, u.created_date, ur.role_name
     FROM users u
     LEFT JOIN user_role ur ON ur.role_id = u.role_id
     WHERE u.user_no = ? LIMIT 1'
);

if (!$stmt) {
    error_log('User edit lookup prepare failed: ' . $conn->error);
    die('Could not load the selected user.');
}

$stmt->bind_param('i', $userNo);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    header('Location: user_find.php?error=invalid_target');
    exit();
}

$userId = (int)$user['user_id'];

// Prevent editing the currently signed-in account through User Find.
if ($userId === (int)$_SESSION['user_id']) {
    header('Location: user_find.php?error=invalid_target');
    exit();
}

// Only Manager can edit deactivated accounts.
if ((int)$user['is_deleted'] === 1 && !$isManager) {
    header('HTTP/1.1 403 Forbidden');
    exit('Only the Membership Manager can edit deactivated accounts.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token'])
        ? (string)$_POST['csrf_token'] : '';

    if (!hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }

    $firstName = isset($_POST['f_name']) ? trim($_POST['f_name']) : '';
    $lastName = isset($_POST['l_name']) ? trim($_POST['l_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // Check whether another account already uses this email.
    if (count($errors) === 0) {
        $emailCheck = $conn->prepare(
            'SELECT user_id FROM users
             WHERE email = ? AND user_id <> ?
             LIMIT 1'
        );

        if (!$emailCheck) {
            $errors[] = 'Could not validate the email address.';
        } else {
            $emailCheck->bind_param('si', $email, $userId);
            $emailCheck->execute();
            $emailResult = $emailCheck->get_result();

            if ($emailResult && $emailResult->num_rows > 0) {
                $errors[] = 'This email address is already assigned to another user.';
            }

            $emailCheck->close();
        }
    }

    if (count($errors) === 0) {
        // Re-check the target account status immediately before updating.
        $statusCheck = $conn->prepare(
            'SELECT is_deleted FROM users WHERE user_id = ? LIMIT 1'
        );

        if (!$statusCheck) {
            $errors[] = 'Could not verify the account status.';
        } else {
            $statusCheck->bind_param('i', $userId);
            $statusCheck->execute();
            $statusResult = $statusCheck->get_result();
            $latestStatus = $statusResult ? $statusResult->fetch_assoc() : null;
            $statusCheck->close();

            if (!$latestStatus) {
                $errors[] = 'The selected user could not be found.';
            } elseif ((int)$latestStatus['is_deleted'] === 1 && !$isManager) {
                header('HTTP/1.1 403 Forbidden');
                exit('Only the Membership Manager can edit deactivated accounts.');
            }
        }
    }

    if (count($errors) === 0) {
        $update = $conn->prepare(
            'UPDATE users
             SET f_name = ?, l_name = ?, email = ?
             WHERE user_id = ?'
        );

        if (!$update) {
            $errors[] = 'Could not prepare the update. Please try again.';
        } else {
            $update->bind_param('sssi', $firstName, $lastName, $email, $userId);

            if ($update->execute()) {
                $success = true;
                $user['f_name'] = $firstName;
                $user['l_name'] = $lastName;
                $user['email'] = $email;
            } else {
                error_log('User edit update failed: ' . $update->error);
                $errors[] = 'Could not save the changes. Please try again.';
            }

            $update->close();
        }
    }
}

// Keep user_no in the URL for both Back and form submission.
$backUrl = 'user_view.php?user_no=' . urlencode((string)$userNo);
$editUrl = 'user_edit.php?user_no=' . urlencode((string)$userNo);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User | Membership System</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(115deg,#dcefe0 0%,#e9efc8 48%,#f8e9a5 100%);
            background-attachment: fixed;
            color: #29433e;
        }
        .staff-nav {
            min-height: 70px;
            background: rgba(255,253,244,.96);
            border-bottom: 1px solid rgba(255,255,255,.8);
            padding: 12px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .staff-brand {
            color: #344b3c;
            font-size: 19px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }
        .staff-user {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #536b5b;
            font-size: 14px;
        }
        .logout-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 40px;
            padding: 9px 17px;
            border: 1px solid #0f5d63;
            border-radius: 9px;
            background: #0f5d63;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
        .page-wrapper { padding: 36px 0 55px; }
        .content-panel {
            background: rgba(255,255,248,.94);
            border: 1px solid rgba(255,255,255,.95);
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 10px 28px rgba(69,83,48,.08);
        }
        .page-heading { color: #344b3c; font-weight: 700; }
        .page-description { color: #687a6c; }
        @media(max-width:767px) {
            .staff-nav { padding: 12px 15px; }
            .staff-user { gap: 7px; font-size: 12px; }
            .staff-user .user-role { display: none; }
            .content-panel { padding: 18px; }
        }
    </style>
</head>
<body>
<nav class="staff-nav">
    <a class="staff-brand" href="../dashboard.php">
        <i class="bi bi-mortarboard-fill me-2"></i>Membership System
    </a>
    <div class="staff-user">
        <span><?php echo h(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ''); ?></span>
        <span class="user-role">· <?php echo $isManager ? 'Membership Manager' : 'Membership Ops'; ?></span>
        <a href="../auth/logout.php" class="logout-button">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>

<main class="container page-wrapper">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-heading mb-1">Edit User</h1>
            <p class="page-description mb-0">Update user account information</p>
        </div>
        <a href="<?php echo h($backUrl); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to User Details
        </a>
    </div>

    <section class="content-panel">
        <div class="mb-4">
            <div class="text-muted small">User Number</div>
            <div class="fw-semibold"><?php echo h($user['user_no']); ?></div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                User details updated successfully.
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger" role="alert"><?php echo h($error); ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?php echo h($editUrl); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="f_name" class="form-label fw-semibold">First Name</label>
                    <input type="text"
                           class="form-control"
                           id="f_name"
                           name="f_name"
                           maxlength="100"
                           required
                           value="<?php echo h(isset($_POST['f_name']) ? $_POST['f_name'] : $user['f_name']); ?>">
                </div>

                <div class="col-md-6">
                    <label for="l_name" class="form-label fw-semibold">Last Name</label>
                    <input type="text"
                           class="form-control"
                           id="l_name"
                           name="l_name"
                           maxlength="100"
                           required
                           value="<?php echo h(isset($_POST['l_name']) ? $_POST['l_name'] : $user['l_name']); ?>">
                </div>

                <div class="col-12">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <input type="email"
                           class="form-control"
                           id="email"
                           name="email"
                           maxlength="255"
                           required
                           value="<?php echo h(isset($_POST['email']) ? $_POST['email'] : $user['email']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">User Role</label>
                    <input type="text"
                           class="form-control"
                           readonly
                           value="<?php echo h($user['role_name'] !== null ? $user['role_name'] : '—'); ?>">
                    <div class="form-text">Role changes are managed separately.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Account Status</label>
                    <input type="text"
                           class="form-control"
                           readonly
                           value="<?php
                               echo (int)$user['is_deleted'] === 1
                                   ? 'Deactivated'
                                   : ((int)$user['is_active'] === 1 ? 'Active' : 'Inactive');
                           ?>">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?php echo h($backUrl); ?>" class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </section>
</main>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
