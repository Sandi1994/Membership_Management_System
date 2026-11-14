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

require_once __DIR__ . '/../config/config.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$loggedInUserId = (int)$_SESSION['user_id'];
$loggedInName = '';
$loggedInStmt = $conn->prepare('SELECT f_name, l_name FROM users WHERE user_id = ? LIMIT 1');
if ($loggedInStmt) {
    $loggedInStmt->bind_param('i', $loggedInUserId);
    $loggedInStmt->execute();
    $loggedInResult = $loggedInStmt->get_result();
    if ($loggedInResult && ($loggedInRow = $loggedInResult->fetch_assoc())) {
        $loggedInName = trim($loggedInRow['f_name'] . ' ' . $loggedInRow['l_name']);
    }
    $loggedInStmt->close();
}

// Role ID 3 = Membership Manager; Role ID 4 = Membership Ops.
$roleId = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
$isManager = ($roleId === 3);
$isOps = ($roleId === 4);

if (!$isManager && !$isOps) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

// Only Membership Managers can view deactivated accounts.
$showDeleted = ($isManager && isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1');
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$pageFile = basename($_SERVER['PHP_SELF']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$where = $showDeleted ? 'u.is_deleted = 1' : 'u.is_deleted = 0';
$params = array();
$types = '';

if ($search !== '') {
    $where .= " AND (
        CAST(u.user_no AS CHAR) LIKE ?
        OR u.f_name LIKE ?
        OR u.l_name LIKE ?
        OR CONCAT(u.f_name, ' ', u.l_name) LIKE ?
        OR u.email LIKE ?
    )";
    $like = '%' . $search . '%';
    $params = array($like, $like, $like, $like, $like);
    $types = 'sssss';
}

$sql = "SELECT
            u.user_id,
            u.user_no,
            u.f_name,
            u.l_name,
            u.email,
            u.is_active,
            u.is_deleted,
            ur.role_name
        FROM users u
        LEFT JOIN user_role ur ON ur.role_id = u.role_id
        WHERE " . $where . "
        ORDER BY u.user_id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('User list query failed: ' . h($conn->error));
}
if ($types !== '') {
    $bindParams = array(&$types);
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
}
if (!$stmt->execute()) {
    die('User list execution failed: ' . h($stmt->error));
}
$users = $stmt->get_result();
if (!$users) {
    die('Could not retrieve user records. Please check that MySQL Native Driver (mysqlnd) is enabled.');
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
$clearUrl = $pageFile . ($showDeleted ? '?show_deleted=1' : '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management | Membership System</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { min-height:100vh; background:linear-gradient(115deg,#dcefe0 0%,#e9efc8 48%,#f8e9a5 100%); background-attachment:fixed; color:#29433e; }
        .staff-nav { min-height:70px; background:rgba(255,253,244,.96); border-bottom:1px solid rgba(255,255,255,.8); padding:12px 28px; display:flex; align-items:center; justify-content:space-between; gap:18px; }
        .staff-brand { color:#344b3c; font-size:19px; font-weight:700; text-decoration:none; white-space:nowrap; }
        .staff-user { display:flex; align-items:center; gap:12px; color:#536b5b; font-size:14px; }
        .logout-button { display:inline-flex; align-items:center; justify-content:center; gap:5px; min-height:40px; padding:9px 17px; border:1px solid #0f5d63; border-radius:9px; background:#0f5d63; color:#fff; font-size:13px; font-weight:700; text-decoration:none; }
        .logout-button:hover { background:#0f5d63; color:#fff; }
        .page-wrapper { padding:36px 0 55px; }
        .page-heading { color:#344b3c; font-weight:700; }
        .page-description { color:#687a6c; }
        .content-panel { background:rgba(255,255,248,.92); border:1px solid rgba(255,255,255,.95); border-radius:18px; padding:24px; box-shadow:0 10px 28px rgba(69,83,48,.08); }
        .table thead th { background:#eaf2e7; color:#344b3c; white-space:nowrap; }
        .table td { vertical-align:middle; }
        .table tbody tr:hover { background:rgba(220,239,224,.35); }
        .search-input { min-height:44px; }
        .search-controls { display:flex; align-items:end; gap:10px; flex-wrap:nowrap; }
        .search-field { flex:1 1 auto; min-width:180px; }
        .search-actions { display:flex; align-items:center; gap:8px; flex:0 0 auto; }
        .btn-deleted-users { display:inline-flex; align-items:center; gap:7px; min-height:42px; padding:10px 17px; border:1px solid #c93645; border-radius:9px; background:#c93645; color:#fff; font-weight:700; text-decoration:none; }
        .btn-deleted-users:hover { background:#a92534; border-color:#a92534; color:#fff; }
        .btn-view-user { display:inline-flex; align-items:center; justify-content:center; gap:5px; min-height:36px; padding:8px 12px; border:1px solid #0f5d63; border-radius:8px; background:#0f5d63; color:#fff; font-size:13px; font-weight:700; white-space:nowrap; text-decoration:none; }
        .btn-view-user:hover { background:#0b4b50; border-color:#0b4b50; color:#fff; }
        .btn-deactivate,.btn-reactivate,.btn-permanent-delete { display:inline-flex; align-items:center; justify-content:center; gap:5px; min-height:36px; padding:8px 12px; border-radius:8px; font-size:13px; font-weight:700; white-space:nowrap; }
        .btn-deactivate { border:1px solid #e49b1e; background:#f4a623; color:#263b32; }
        .btn-deactivate:hover { background:#d99016; border-color:#d99016; color:#fff; }
        .btn-reactivate { border:1px solid #089158; background:#087f78; color:#fff; }
        .btn-reactivate:hover { background:#066b65; border-color:#066b65; color:#fff; }
        .btn-permanent-delete { border:1px solid #c93645; background:#c93645; color:#fff; }
        .btn-permanent-delete:hover { background:#a92534; border-color:#a92534; color:#fff; }
        .status-cell { min-width:105px; padding-right:24px !important; }
        .action-heading { text-align:center !important; }
        .action-cell { min-width:210px; text-align:center; white-space:nowrap; padding-left:22px !important; }
        .action-buttons { display:flex; justify-content:center; align-items:center; gap:10px; flex-wrap:wrap; }
        .action-buttons form { display:inline-block; margin:0; }
        @media (max-width:767.98px) {
            .content-panel { padding:16px; }
            .staff-nav { padding:12px 15px; }
            .staff-user { gap:7px; font-size:12px; }
            .staff-user .user-role { display:none; }
            .logout-button { padding:8px 11px; }
            .search-controls { flex-wrap:wrap; }
            .search-field { flex:1 1 100%; }
            .search-actions { width:100%; }
        }
    </style>
</head>
<body>
<nav class="staff-nav">
    <a class="staff-brand" href="../dashboard.php"><i class="bi bi-mortarboard-fill me-2"></i>Membership System</a>
    <div class="staff-user">
        <span><?php echo h($loggedInName); ?></span>
        <span class="user-role">· <?php echo $isManager ? 'Membership Manager' : 'Membership Ops'; ?></span>
        <a href="../auth/logout.php" class="logout-button"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </div>
</nav>

<main class="container page-wrapper">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-heading mb-1">User Management</h1>
            <p class="page-description mb-0"><?php echo $isManager ? 'Membership Manager' : 'Membership Ops'; ?> · User directory</p>
        </div>
        <?php if ($isManager && !$showDeleted): ?>
            <a class="btn btn-outline-danger" href="<?php echo h($pageFile); ?>?show_deleted=1"><i class="bi bi-archive-fill"></i> View Deactivated Accounts</a>
        <?php endif; ?>
        <?php if ($showDeleted): ?>
            <a class="btn btn-outline-success align-self-center" href="<?php echo h($pageFile); ?>"><i class="bi bi-arrow-left me-1"></i>Back to User Directory</a>
        <?php endif; ?>
    </div>

    <?php if ($status === 'soft_deleted'): ?>
        <div class="alert alert-success">The account has been deactivated successfully.</div>
    <?php elseif ($status === 'restored'): ?>
        <div class="alert alert-success">The account has been reactivated successfully.</div>
    <?php elseif ($status === 'hard_deleted'): ?>
        <div class="alert alert-success">User permanently deleted.</div>
    <?php endif; ?>

    <?php if ($error === 'related_records'): ?>
        <div class="alert alert-danger">Cannot permanently delete this user because related database records exist. Deactivate the account instead.</div>
    <?php elseif ($error === 'invalid_request'): ?>
        <div class="alert alert-danger">Invalid request or security token. Please try again.</div>
    <?php elseif ($error === 'invalid_target'): ?>
        <div class="alert alert-danger">The selected user could not be found or cannot be changed.</div>
    <?php elseif ($error !== ''): ?>
        <div class="alert alert-danger">The requested action could not be completed.</div>
    <?php endif; ?>

    <section class="content-panel mb-4">
        <form method="get" id="userSearchForm">
            <?php if ($showDeleted): ?><input type="hidden" name="show_deleted" value="1"><?php endif; ?>
            <div class="search-controls">
                <div class="search-field">
                    <label for="q" class="form-label fw-semibold">Search users</label>
                    <input type="text" class="form-control search-input" id="q" name="q" value="<?php echo h($search); ?>" placeholder="User number, name or email" autocomplete="off">
                </div>
                <div class="search-actions">
                    <button type="submit" class="btn btn-secondary" style="min-height:44px"><i class="bi bi-search me-1"></i>Search</button>
                    <a class="btn btn-outline-secondary" style="min-height:44px;display:inline-flex;align-items:center" id="clearSearch" href="<?php echo h($clearUrl); ?>">Clear</a>
                </div>
            </div>
            <div class="small text-muted mt-2">Search starts automatically after you stop typing.</div>
        </form>
    </section>

    <section class="content-panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 fw-bold mb-0"><?php echo $showDeleted ? 'Deactivated Accounts' : 'User Directory'; ?></h2>
            <span class="small text-muted"><?php echo (int)$users->num_rows; ?> record(s) found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="width:55px">View</th>
                        <th>User No.</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th class="status-cell">Status</th>
                        <th class="action-heading">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
                <?php else: ?>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <?php
                            $isOwnAccount = ((int)$user['user_id'] === $loggedInUserId);
                            ?>
                        <?php
                            // One-time token for the Bootstrap permanent-delete modal.
                            // The server validates this token before deleting anything.
                            $deleteUserId = (int)$user['user_id'];
                            $deleteToken = bin2hex(random_bytes(32));

                            if (!isset($_SESSION['delete_confirmations']) ||
                                !is_array($_SESSION['delete_confirmations'])) {
                                $_SESSION['delete_confirmations'] = array();
                            }

                            // Remove expired confirmation tokens.
                            foreach ($_SESSION['delete_confirmations'] as $tokenUserId => $tokenData) {
                                if (!isset($tokenData['created']) ||
                                    (time() - (int)$tokenData['created']) > 300) {
                                    unset($_SESSION['delete_confirmations'][$tokenUserId]);
                                }
                            }

                            $_SESSION['delete_confirmations'][$deleteUserId] = array(
                                'token' => $deleteToken,
                                'created' => time()
                            );
                        ?>
                        <tr>
                            <td class="text-center"><a href="user_view.php?user_no=<?php echo (int)$user['user_no']; ?>" class="btn btn-sm btn btn-outline-secondary" title="View user details" aria-label="View user details"><i class="bi bi-search"></i></a></td>
                            <td><?php echo h($user['user_no']); ?></td>
                            <td><?php echo h(trim($user['f_name'] . ' ' . $user['l_name'])); ?></td>
                            <td><?php echo h($user['email']); ?></td>
                            <td><?php echo h($user['role_name']); ?></td>
                            <td class="status-cell">
                                <?php if ((int)$user['is_active'] === 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <?php if ($isManager): ?>
                                        <?php if ($showDeleted): ?>
                                            <form method="post" action="user_action.php" onsubmit="return confirm('Are you sure you want to reactivate this account?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                <input type="hidden" name="show_deleted" value="1">
                                                <button type="submit" class="btn btn-sm btn-reactivate"><i class="bi bi-arrow-counterclockwise"></i>Reactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="user_action.php" onsubmit="return confirm('Are you sure you want to deactivate this account? The user records will be retained.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="soft_delete">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                               <button type="submit"
                                                        class="btn btn-sm btn-deactivate"
                                                        <?php echo $isOwnAccount ? 'disabled title="You cannot deactivate your own account."' : ''; ?>>
                                                    <i class="bi bi-person-dash"></i>
                                                    Deactivate Account
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ((int)$user['user_id'] === $loggedInUserId): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-permanent-delete"
                                                    disabled
                                                    title="You cannot permanently delete your own account.">
                                                <i class="bi bi-trash3"></i>Permanently Delete
                                            </button>
                                        <?php else: ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-permanent-delete"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#permanentDeleteModal"
                                                    data-user-id="<?php echo (int)$user['user_id']; ?>"
                                                    data-user-name="<?php echo h(trim($user['f_name'] . ' ' . $user['l_name'])); ?>"
                                                    data-confirmation-token="<?php echo h($deleteToken); ?>"
                                                    data-show-deleted="<?php echo $showDeleted ? '1' : '0'; ?>">
                                                <i class="bi bi-trash3"></i>Permanently Delete
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!$showDeleted): ?>
                                            <form method="post" action="user_action.php" onsubmit="return confirm('Are you sure you want to deactivate this account? The user records will be retained.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="soft_delete">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-deactivate"><i class="bi bi-person-dash"></i>Deactivate Account</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">No actions available</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="permanent-deletion">** Permanent deletion may be blocked when related database records reference the user.</p>
    </section>
</main>

<?php if ($isManager): ?>
<!-- Bootstrap confirmation modal for permanent deletion -->
<div class="modal fade" id="permanentDeleteModal" tabindex="-1"
     aria-labelledby="permanentDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger" id="permanentDeleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Confirm Permanent Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <form method="post" action="user_action.php" id="permanentDeleteForm">
                <div class="modal-body">
                    <p class="mb-2">
                        Are you sure you want to permanently delete
                        <strong id="deleteUserName"></strong>?
                    </p>
                    <p class="text-danger small mb-0">
                        This action cannot be undone. The user record will be removed
                        if database relationships do not prevent deletion.
                    </p>

                    <input type="hidden" name="csrf_token"
                           value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="hard_delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <input type="hidden" name="show_deleted"
                           value="<?php echo $showDeleted ? '1' : '0'; ?>">
                    <input type="hidden" name="confirm_delete" value="1">
                    <input type="hidden" name="confirmation_token"
                           id="deleteConfirmationToken">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>
                        Yes, Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var deleteModal = document.getElementById('permanentDeleteModal');

    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var triggerButton = event.relatedTarget;

            if (!triggerButton) return;

            document.getElementById('deleteUserId').value =
                triggerButton.getAttribute('data-user-id') || '';

            document.getElementById('deleteUserName').textContent =
                triggerButton.getAttribute('data-user-name') || 'this user';

            document.getElementById('deleteConfirmationToken').value =
                triggerButton.getAttribute('data-confirmation-token') || '';

            var showDeletedInput = deleteModal.querySelector(
                'input[name="show_deleted"]'
            );

            if (showDeletedInput) {
                showDeletedInput.value =
                    triggerButton.getAttribute('data-show-deleted') || '0';
            }
        });
    }
})();

(function () {
    var searchInput = document.getElementById('q');
    var searchForm = document.getElementById('userSearchForm');
    var searchTimer = null;

    if (!searchInput || !searchForm) return;

    searchInput.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            searchForm.submit();
        }, 600);
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            window.clearTimeout(searchTimer);
            searchForm.submit();
        }
    });
})();
</script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $stmt->close(); ?>
