<?php
/*
 * Shared top navigation and My Account dropdown.
 *
 * Before including this file:
 *   1. Start the session.
 *   2. Include config/config.php so $conn is available.
 *   3. Set $baseUrl:
 *        Root-level page:       $baseUrl = '';
 *        Page inside a folder: $baseUrl = '../';
 *
 * Optional:
 *   $currentPage = 'dashboard'; // hides "My Dashboard" while on dashboard
 */
if (!isset($baseUrl)) {
    $baseUrl = '';
}
if (!isset($currentPage)) {
    $currentPage = '';
}

$navUser = null;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $navUserId = (int) $_SESSION['user_id'];

    $navSql = "SELECT u.user_no, u.f_name, u.l_name, u.email,
                      m.membership_no
               FROM users u
               LEFT JOIN members m ON m.user_id = u.user_id
               WHERE u.user_id = ?
                 AND (u.is_active=0)
               LIMIT 1";

    $navStmt = $conn->prepare($navSql);
    if ($navStmt) {
        $navStmt->bind_param("i", $navUserId);
        $navStmt->execute();
        $navResult = $navStmt->get_result();
        $navUser = $navResult->fetch_assoc();
        $navStmt->close();
    }
}

$navFirst = isset($navUser['f_name']) ? $navUser['f_name'] : '';
$navLast = isset($navUser['l_name']) ? $navUser['l_name'] : '';
$navFullName = trim($navFirst . ' ' . $navLast);
$navEmail = isset($navUser['email']) ? $navUser['email'] : '';
$navUserNo = isset($navUser['user_no']) ? $navUser['user_no'] : '';
$navMembershipNo = isset($navUser['membership_no']) ? $navUser['membership_no'] : '';

$navInitials = '';
if ($navFirst !== '') {
    $navInitials .= strtoupper(substr($navFirst, 0, 1));
}
if ($navLast !== '') {
    $navInitials .= strtoupper(substr($navLast, 0, 1));
}
if ($navInitials === '') {
    $navInitials = 'U';
}

if (!function_exists('nav_escape')) {
    function nav_escape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
?>

<style>
    .shared-topbar {
        background: #fffdf8;
        border-bottom: 1px solid #e7ece2;
        min-height: 80px;
        padding: 12px 0;
        z-index: 1030;
    }
    .shared-topbar .navbar-brand {
        color: #29483d;
        font-size: 21px;
        font-weight: 700;
        white-space: nowrap;
    }
    .shared-topbar .nav-link {
        color: #29483d;
        font-weight: 500;
        padding: 10px 13px !important;
    }
    .shared-topbar .nav-link:hover,
    .shared-topbar .nav-link.active {
        color: #087f7d;
    }
    .shared-account-button {
        background: #344b3c;
        border: 1px solid #344b3c;
        color: #fff;
        border-radius: 10px;
        padding: 10px 15px;
        font-weight: 600;
    }
    .shared-account-button:hover,
    .shared-account-button:focus {
        background: #263c30;
        color: #fff;
    }
    .shared-avatar {
        width: 34px;
        height: 34px;
        min-width: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #e3f3ef;
        color: #087f7d;
        font-size: 13px;
        font-weight: 700;
    }
    .shared-avatar.large {
        width: 48px;
        height: 48px;
        font-size: 15px;
    }
    .shared-account-menu {
        width: 290px;
        border: 0;
        border-radius: 14px;
        padding: 15px;
        box-shadow: 0 12px 32px rgba(25, 53, 46, .16);
    }
    .shared-account-detail {
        font-size: 13px;
        overflow-wrap: anywhere;
        margin-top: 9px;
    }
    .shared-account-detail .detail-label {
        display: block;
        color: #87928d;
        font-size: 12px;
    }
    .shared-account-detail .detail-value {
        color: #344a45;
        font-weight: 600;
    }
    @media (max-width: 991.98px) {
        .shared-topbar .navbar-collapse {
            padding-top: 12px;
        }
        .shared-topbar .account-area {
            padding: 10px 0;
        }
    }
</style>

<nav class="navbar navbar-expand-lg shared-topbar">
    <div class="container">
        <a class="navbar-brand" href="<?= nav_escape($baseUrl) ?>index.php">
            <i class="bi bi-mortarboard-fill me-2"></i>Membership System
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#sharedTopbarNav"
                aria-controls="sharedTopbarNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="sharedTopbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="<?= nav_escape($baseUrl) ?>index.php#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= nav_escape($baseUrl) ?>index.php#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= nav_escape($baseUrl) ?>index.php#categories">Membership</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= nav_escape($baseUrl) ?>index.php#benefits">Benefits</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= nav_escape($baseUrl) ?>index.php#contact">Contact</a></li>
            </ul>

            <div class="account-area ms-lg-3">
                <?php if ($navUser): ?>
                    <div class="dropdown">
                        <button class="btn shared-account-button dropdown-toggle d-flex align-items-center"
                                type="button" id="sharedAccountDropdown"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="shared-avatar me-2"><?= nav_escape($navInitials) ?></span>
                            My Account
                        </button>

                        <div class="dropdown-menu dropdown-menu-end shared-account-menu mt-2"
                             aria-labelledby="sharedAccountDropdown">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="shared-avatar large"><?= nav_escape($navInitials) ?></span>
                                <div>
                                    <div class="fw-semibold"><?= nav_escape($navFullName ?: 'Account') ?></div>
                                    <small class="text-muted">My Account</small>
                                </div>
                            </div>

                            <div class="shared-account-detail">
                                <span class="detail-label">User Number</span>
                                <span class="detail-value"><?= nav_escape($navUserNo) ?></span>
                            </div>

                            <?php if ($navMembershipNo !== '' && $navMembershipNo !== null): ?>
                                <div class="shared-account-detail">
                                    <span class="detail-label">Membership Number</span>
                                    <span class="detail-value"><?= nav_escape($navMembershipNo) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="shared-account-detail">
                                <span class="detail-label">Email Address</span>
                                <span class="detail-value"><?= nav_escape($navEmail) ?></span>
                            </div>

                            <hr>
                            <?php if ($currentPage !== 'dashboard'): ?>
                                <a class="dropdown-item rounded py-2"
                                   href="<?= nav_escape($baseUrl) ?>dashboard.php">
                                    <i class="bi bi-grid me-2"></i> My Dashboard
                                </a>
                            <?php endif; ?>
                            <a class="dropdown-item rounded py-2 text-danger"
                               href="<?= nav_escape($baseUrl) ?>auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= nav_escape($baseUrl) ?>auth/login.php" class="btn btn-outline-success me-2">Sign In</a>
                    <a href="<?= nav_escape($baseUrl) ?>auth/register.php" class="btn btn-success">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
