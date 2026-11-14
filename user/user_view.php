<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$userNo = isset($_GET['user_no'])
    ? (int)$_GET['user_no']
    : 0;

require_once __DIR__ . '/../config/config.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$roleId = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
$isManager = ($roleId === 3);
$isOps = ($roleId === 4);

if (!$isManager && !$isOps) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$userNo = isset($_GET['user_no']) ? (int)$_GET['user_no'] : 0;
if ($userId < 1 && $userNo < 1) {
    header('Location: user_find.php?error=invalid_target');
    exit();
}

$sql = "SELECT
            u.user_id,
            u.user_no,
            u.f_name,
            u.l_name,
            u.email,
            u.is_active,
            u.is_deleted,
            u.created_date,
            ur.role_name
        FROM users u
        LEFT JOIN user_role ur ON ur.role_id = u.role_id
        WHERE u.user_id = ? OR u.user_no = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Could not prepare user details query.');
}

$stmt->bind_param('ii', $userId, $userNo);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    header('Location: user_find.php?error=invalid_target');
    exit();
}

// Membership Ops cannot view deactivated accounts, including by direct URL.
if ((int)$user['is_deleted'] === 1 && !$isManager) {
    header('HTTP/1.1 403 Forbidden');
    exit('You do not have permission to view deactivated accounts.');
}

$fullName = trim($user['f_name'] . ' ' . $user['l_name']);
$backUrl = 'user_find.php';
if ((int)$user['is_deleted'] === 1) {
    $backUrl .= '?show_deleted=1';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View User Details | Membership System</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { min-height:100vh; background:linear-gradient(115deg,#dcefe0 0%,#e9efc8 48%,#f8e9a5 100%); background-attachment:fixed; color:#29433e; }
        .staff-nav { min-height:70px; background:rgba(255,253,244,.96); border-bottom:1px solid rgba(255,255,255,.8); padding:12px 28px; display:flex; align-items:center; justify-content:space-between; gap:18px; }
        .staff-brand { color:#344b3c; font-size:19px; font-weight:700; text-decoration:none; white-space:nowrap; }
        .staff-user { display:flex; align-items:center; gap:12px; color:#536b5b; font-size:14px; }
        .logout-button { display:inline-flex; align-items:center; justify-content:center; gap:5px; min-height:40px; padding:9px 17px; border:1px solid #0f5d63; border-radius:9px; background:#0f5d63; color:#fff; font-size:13px; font-weight:700; text-decoration:none; }
        .page-wrapper { padding:36px 0 55px; }
        .content-panel { background:rgba(255,255,248,.94); border:1px solid rgba(255,255,255,.95); border-radius:18px; padding:26px; box-shadow:0 10px 28px rgba(69,83,48,.08); }
        .page-heading { color:#344b3c; font-weight:700; }
        .page-description { color:#687a6c; }
        .detail-row { padding:14px 4px; border-bottom:1px solid #e5eadf; }
        .detail-row:last-child { border-bottom:0; }
        .detail-label { color:#687a6c; font-weight:600; }
        .detail-value { color:#29433e; overflow-wrap:anywhere; }
        @media(max-width:767px) { .staff-nav { padding:12px 15px; } .staff-user { gap:7px; font-size:12px; } .staff-user .user-role { display:none; } .content-panel { padding:18px; } }
    </style>
</head>
<body>
<nav class="staff-nav">
    <a class="staff-brand" href="../dashboard.php"><i class="bi bi-mortarboard-fill me-2"></i>Membership System</a>
    <div class="staff-user">
        <span><?php echo h(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ''); ?></span>
        <span class="user-role">· <?php echo $isManager ? 'Membership Manager' : 'Membership Ops'; ?></span>
        <a href="../auth/logout.php" class="logout-button"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </div>
</nav>

<main class="container page-wrapper">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-heading mb-1">User Details</h1>
            <p class="page-description mb-0">View user account information</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo h($backUrl); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to User Find</a>
            <a href="user_edit.php?user_no=<?php echo (int)$user['user_no']; ?>" class="btn btn-success"><i class="bi bi-pencil-square me-1"></i>Edit User</a>
        </div>
    </div>

    <section class="content-panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0"><?php echo h($fullName); ?></h2>
            <?php if ((int)$user['is_deleted'] === 1): ?>
                <span class="badge bg-secondary">Deactivated</span>
            <?php elseif ((int)$user['is_active'] === 1): ?>
                <span class="badge bg-success">Active</span>
            <?php else: ?>
                <span class="badge bg-secondary">Inactive</span>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="detail-row row">
                    <div class="col-5 detail-label">User No.</div>
                    <div class="col-7 detail-value"><?php echo h($user['user_no']); ?></div>
                </div>
                <div class="detail-row row">
                    <div class="col-5 detail-label">First Name</div>
                    <div class="col-7 detail-value"><?php echo h($user['f_name']); ?></div>
                </div>
                <div class="detail-row row">
                    <div class="col-5 detail-label">Last Name</div>
                    <div class="col-7 detail-value"><?php echo h($user['l_name']); ?></div>
                </div>
                <div class="detail-row row">
                    <div class="col-5 detail-label">Email</div>
                    <div class="col-7 detail-value"><?php echo h($user['email']); ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-row row">
                    <div class="col-5 detail-label">Role</div>
                    <div class="col-7 detail-value"><?php echo h($user['role_name'] !== null ? $user['role_name'] : '—'); ?></div>
                </div>
                <div class="detail-row row">
                    <div class="col-5 detail-label">Account Status</div>
                    <div class="col-7 detail-value"><?php echo (int)$user['is_deleted'] === 1 ? 'Deactivated' : ((int)$user['is_active'] === 1 ? 'Active' : 'Inactive'); ?></div>
                </div>
                <div class="detail-row row">
                    <div class="col-5 detail-label">Created Date</div>
                    <div class="col-7 detail-value"><?php echo !empty($user['created_date']) && $user['created_date'] !== '0000-00-00 00:00:00' ? h($user['created_date']) : '—'; ?></div>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
