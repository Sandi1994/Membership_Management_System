<?php

// Start the session only if it has not already been started.

if (session_id() == '') {

    session_start();

}



if (!isset($_SESSION['user_id'])) {

    header("Location: auth/login.php");

    exit();

}



require_once __DIR__ . '/config/config.php';



// Escape values before displaying them in HTML.

function e($value)

{

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

}



// Retrieve the logged-in user's details.

$userId = (int) $_SESSION['user_id'];



$sql = "SELECT

            u.user_id,

            u.user_no,

            u.f_name,

            u.l_name,

            u.email,

            u.role_id

        FROM users u

        WHERE u.user_id = ?

        LIMIT 1";



$stmt = $conn->prepare($sql);



if (!$stmt) {

    die("User query failed: " . $conn->error);

}



$stmt->bind_param("i", $userId);

$stmt->execute();



$result = $stmt->get_result();

$user = $result->fetch_assoc();



$stmt->close();



if (!$user) {

    session_unset();

    session_destroy();

    header("Location: auth/login.php");

    exit();

}



$firstName = isset($user['f_name']) ? $user['f_name'] : '';

$lastName = isset($user['l_name']) ? $user['l_name'] : '';

$fullName = trim($firstName . ' ' . $lastName);



$roleId = isset($user['role_id']) ? (int) $user['role_id'] : 0;



// Role IDs currently configured in the project:
// 1 = Non-member
// 2 = Member
// 3 = Membership Manager
// 4 = Membership Ops / Staff
$isNonMember = ($roleId == 1);
$isMember = ($roleId == 2);
$isManager = ($roleId == 3);
$isOps = ($roleId == 4);
$isStaff = ($isManager || $isOps);

// All four configured roles can access their own dashboard.
// Individual PHP pages must still enforce their own permissions.
if (!$isNonMember && !$isMember && !$isManager && !$isOps) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

if ($isManager) {
    $roleLabel = 'Membership Manager';
} elseif ($isOps) {
    $roleLabel = 'Membership Ops';
} elseif ($isMember) {
    $roleLabel = 'Member';
} else {
    $roleLabel = 'Non-member';
}

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

<!doctype html>

<html lang="en">



<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard | Membership System</title>



    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <link

        rel="stylesheet"

        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">

    

    <link rel="stylesheet" href="assets/css/style.css">



    <style>

        :root {

            --deep-green: #344b3c;

            --text-green: #304b3d;

            --teal: #087f78;

        }



        * {

            box-sizing: border-box;

        }



        body {

            min-height: 100vh;

            margin: 0;

            background: linear-gradient(115deg, #dcefe0 0%, #e9efc8 48%, #f8e9a5 100%);

            background-attachment: fixed;

            color: var(--text-green);

            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;

        }



        .staff-nav {

            min-height: 70px;

            background: rgba(255, 253, 244, 0.92);

            border-bottom: 1px solid rgba(255, 255, 255, 0.8);

            padding: 12px 28px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 18px;

        }



        .staff-nav .brand {

            color: var(--deep-green);

            font-size: 19px;

            font-weight: 750;

            text-decoration: none;

            white-space: nowrap;

        }



        .nav-left {

            display: flex;

            align-items: center;

            gap: 16px;

            min-width: 0;

        }



        .sidebar-toggle {

            width: 44px;

            height: 44px;

            flex: 0 0 44px;

            border: 1px solid #c8d9cc;

            border-radius: 10px;

            background: #e3f0df;

            color: var(--deep-green);

            display: inline-flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;

            font-size: 27px;

            line-height: 1;

            transition: background 0.2s ease, transform 0.2s ease;

        }



        .sidebar-toggle:hover {

            background: #cfe5d3;

            transform: translateY(-1px);

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

            color: #ffffff;

            font-size: 13px;

            font-weight: 700;

            text-decoration: none;

            box-shadow: 0 3px 8px rgba(185, 45, 59, 0.18);

            transition: background 0.2s ease, box-shadow 0.2s ease;

        }



        .logout-button:hover {

            background: #0f5d63;

            color: #ffffff;

            box-shadow: 0 5px 12px rgba(185, 45, 59, 0.25);

        }



        .staff-user {

            display: flex;

            align-items: center;

            gap: 12px;

            color: #536b5b;

            font-size: 14px;

        }



        .user-avatar {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #dcefe2;

            color: var(--teal);

            font-weight: 700;

        }



        .dashboard-layout {

            display: flex;

            min-height: calc(100vh - 70px);

            align-items: stretch;

        }



        /* Wider sidebar that extends to the bottom of the viewport. */

        .staff-side {

            width: 285px;

            min-width: 285px;

            min-height: calc(100vh - 70px);

            background: var(--deep-green);

            padding: 30px 18px;

            box-shadow: 8px 0 28px rgba(47, 73, 54, 0.08);

        }



        .side-heading {

            color: #c9d9cc;

            font-size: 11px;

            font-weight: 700;

            letter-spacing: 1.5px;

            padding: 0 13px;

            margin: 5px 0 15px;

        }



        .side-link {

            display: flex;

            align-items: center;

            gap: 13px;

            color: #e1ebe2;

            text-decoration: none;

            padding: 13px 14px;

            border-radius: 11px;

            margin-bottom: 7px;

            font-size: 14px;

            font-weight: 550;

            transition: 0.2s ease;

        }



        .side-link i {

            width: 22px;

            font-size: 18px;

            text-align: center;

        }



        a.side-link:hover,

        .side-link.active {

            background: #e3f0df;

            color: var(--teal);

            transform: translateX(2px);

        }



        .side-link.disabled-module {

            color: #d3dfd5;

            cursor: default;

            opacity: 0.88;

        }



        .side-group {

            margin-bottom: 6px;

        }



        .side-group > summary {

            list-style: none;

            cursor: pointer;

        }



        .side-group > summary::-webkit-details-marker {

            display: none;

        }



        .side-group > summary .side-chevron {

            width: auto;

            margin-left: auto;

            font-size: 13px;

            transition: transform 0.2s ease;

        }



        .side-group[open] > summary .side-chevron {

            transform: rotate(180deg);

        }



        .side-submenu {

            margin: 2px 0 10px 24px;

            padding: 5px 0 5px 15px;

            border-left: 1px solid rgba(225, 235, 226, 0.28);

        }



        .side-sub-link {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 9px 10px;

            margin: 2px 0;

            border-radius: 8px;

            color: #cbd9ce;

            font-size: 13px;

            line-height: 1.35;

        }



        .side-sub-link i {

            width: 17px;

            font-size: 14px;

            text-align: center;

        }



        .side-sub-link:hover {

            background: rgba(227, 240, 223, 0.10);

            color: #ffffff;

        }



        .side-group > summary:hover {

            background: rgba(227, 240, 223, 0.12);

            color: #ffffff;

        }



        .side-divider {

            height: 1px;

            background: rgba(225, 235, 226, 0.18);

            margin: 20px 8px;

        }



        .side-note {

            color: #b9cbbd;

            font-size: 11px;

            line-height: 1.6;

            padding: 0 13px;

            margin-top: 20px;

        }



        .staff-main {

            flex: 1;

            min-width: 0;

            padding: 42px clamp(22px, 4vw, 58px);

        }



        /* The hamburger hides the sidebar and lets the content use the full width. */

        .dashboard-layout.sidebar-collapsed .staff-side {

            display: none;

        }



        .dashboard-layout.sidebar-collapsed .staff-main {

            width: 100%;

        }



        .welcome-title {

            color: var(--deep-green);

            font-size: clamp(30px, 3vw, 40px);

            font-weight: 750;

            letter-spacing: -0.7px;

            margin-bottom: 7px;

        }



        .intro {

            color: #607565;

            font-size: 15px;

            margin-bottom: 30px;

        }



        .section-title {

            color: var(--deep-green);

            font-size: 17px;

            font-weight: 750;

            margin: 28px 0 15px;

        }



        .module-card {

            height: 100%;

            min-height: 205px;

            padding: 24px;

            background: rgba(255, 255, 248, 0.88);

            border: 1px solid rgba(255, 255, 255, 0.95);

            border-radius: 18px;

            box-shadow: 0 10px 28px rgba(69, 83, 48, 0.07);

            transition: transform 0.2s ease, box-shadow 0.2s ease;

        }



        .module-card:hover {

            transform: translateY(-4px);

            box-shadow: 0 16px 32px rgba(69, 83, 48, 0.12);

        }



        .module-icon {

            width: 48px;

            height: 48px;

            border-radius: 14px;

            background: #dcefe2;

            color: var(--teal);

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 17px;

        }



        .module-icon i {

            font-size: 22px;

        }



        .module-card h3 {

            color: var(--deep-green);

            font-size: 18px;

            font-weight: 700;

            margin-bottom: 9px;

        }



        .module-card p {

            color: #687a6c;

            font-size: 13px;

            line-height: 1.65;

            margin-bottom: 0;

        }



        .module-card a {

            color: inherit;

            text-decoration: none;

        }



        .module-card.linked {

            cursor: pointer;

        }



        .module-card .module-status {

            display: inline-block;

            margin-top: 15px;

            color: var(--teal);

            font-size: 12px;

            font-weight: 700;

        }



        @media (max-width: 991px) {

            .dashboard-layout {

                display: block;

            }



            .staff-side {

                width: 100%;

                min-width: 0;

                min-height: auto;

                padding: 16px;

            }



            .staff-side .side-menu {

                display: grid;

                grid-template-columns: repeat(2, minmax(0, 1fr));

                gap: 5px;

            }



            .side-link {

                margin-bottom: 0;

            }



            .side-divider,

            .side-note {

                display: none;

            }



            .staff-main {

                padding: 28px 20px;

            }

        }



        @media (max-width: 575px) {

            .staff-nav {

                padding: 12px 15px;

            }



            .nav-left {

                gap: 9px;

            }



            .sidebar-toggle {

                width: 40px;

                height: 40px;

                flex-basis: 40px;

            }



            .logout-button {

                min-height: 38px;

                padding: 8px 11px;

            }



            .staff-nav .brand {

                font-size: 16px;

            }



            .staff-user .user-details {

                display: none;

            }



            .staff-side .side-menu {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>



<body>



    <!-- Shared top navigation for all authenticated roles -->

    <header class="staff-nav">

        <div class="nav-left">

            <button

                type="button"

                class="sidebar-toggle"

                id="sidebarToggle"

                aria-label="Expand or collapse sidebar"

                aria-expanded="true"

                title="Expand or collapse sidebar"

            >

                <i class="bi bi-list"></i>

            </button>



            <a class="brand" href="index.php">

                <i class="bi bi-mortarboard-fill me-2"></i>

                Membership System

            </a>

        </div>



        <div class="staff-user">

            <div class="user-avatar">

                <?php echo e($initials); ?>

            </div>



            <div class="user-details">

                <div class="fw-semibold"><?php echo e($fullName); ?></div>

                <small><?php echo e($roleLabel); ?></small>

            </div>



            <a href="auth/logout.php" class="logout-button">

                <i class="bi bi-box-arrow-right me-1"></i>

                Logout

            </a>

        </div>

    </header>



    <div class="dashboard-layout">



        <!-- Role-specific sidebar -->

        <aside class="staff-side">

            <div class="side-heading">WORKSPACE</div>



            <nav class="side-menu">
                <a class="side-link active" href="dashboard.php">
                    <i class="bi bi-grid"></i> Dashboard
                </a>

                <?php if ($isManager): ?>
                    <div class="side-divider"></div>
                    <div class="side-heading">ADMINISTRATION</div>
                    <a class="side-link" href="user/user_find.php">
                        <i class="bi bi-person-fill-gear"></i> Find User
                    </a>
                    <a class="side-link" href="user/user_find.php">
                        <i class="bi bi-people"></i> Find Member
                    </a>
                    <span class="side-link disabled-module"><i class="bi bi-sliders"></i> Membership Settings</span>
                    <div class="side-heading mt-3">OPERATIONS</div>
                    <span class="side-link disabled-module"><i class="bi bi-file-earmark-check"></i> Review Applications</span>
                    <span class="side-link disabled-module"><i class="bi bi-folder-check"></i> Document Verification</span>
                    <span class="side-link disabled-module"><i class="bi bi-credit-card"></i> Verify Payments</span>
                    <span class="side-link disabled-module"><i class="bi bi-receipt"></i> Receipts & Certificates</span>
                    <span class="side-link disabled-module"><i class="bi bi-arrow-repeat"></i> Process Renewals</span>
                    <span class="side-link disabled-module"><i class="bi bi-person-gear"></i> Update Member Records</span>
                    <span class="side-link disabled-module"><i class="bi bi-bell"></i> Renewal Reminders</span>
                    <span class="side-link disabled-module"><i class="bi bi-journal-check"></i> Audit Log</span>
                    <span class="side-link disabled-module"><i class="bi bi-file-earmark-bar-graph"></i> Reports</span>
                <?php elseif ($isOps): ?>
                    <div class="side-divider"></div>
                    <div class="side-heading">MEMBERSHIP OPERATIONS</div>
                    <span class="side-link disabled-module"><i class="bi bi-file-earmark-check"></i> Review Applications</span>
                    <span class="side-link disabled-module"><i class="bi bi-folder-check"></i> Document Verification</span>
                    <span class="side-link disabled-module"><i class="bi bi-credit-card"></i> Verify Payments</span>
                    <span class="side-link disabled-module"><i class="bi bi-receipt"></i> Receipts & Certificates</span>
                    <span class="side-link disabled-module"><i class="bi bi-arrow-repeat"></i> Process Renewals</span>
                    <span class="side-link disabled-module"><i class="bi bi-person-gear"></i> Update Member Records</span>
                    <span class="side-link disabled-module"><i class="bi bi-bell"></i> Renewal Reminders</span>
                <?php elseif ($isMember): ?>
                    <div class="side-divider"></div>
                    <div class="side-heading">MY ACCOUNT</div>
                    <a class="side-link" href="my_profile.php">
                        <i class="bi bi-person-circle"></i> My Profile
                    </a>
                    <span class="side-link disabled-module"><i class="bi bi-award"></i> My Membership</span>
                    <span class="side-link disabled-module"><i class="bi bi-file-earmark-text"></i> My Applications</span>
                    <span class="side-link disabled-module"><i class="bi bi-credit-card"></i> My Payments</span>
                    <span class="side-link disabled-module"><i class="bi bi-arrow-repeat"></i> My Renewals</span>
                    <span class="side-link disabled-module"><i class="bi bi-download"></i> Receipts & Certificates</span>
                <?php else: ?>
                    <div class="side-divider"></div>
                    <div class="side-heading">MY ACCOUNT</div>
                    <a class="side-link" href="my_profile.php">
                        <i class="bi bi-person-circle"></i> My Profile
                    </a>
                    <span class="side-link disabled-module"><i class="bi bi-file-earmark-text"></i> My Applications</span>
                    <span class="side-link disabled-module"><i class="bi bi-credit-card"></i> My Payments</span>
                    <span class="side-link disabled-module"><i class="bi bi-person-plus"></i> Apply for Membership</span>
                <?php endif; ?>
            </nav>



            <p class="side-note">

                Membership and Subscription Management System

            </p>

        </aside>



        <!-- Role-specific dashboard content -->

        <main class="staff-main">



            <h1 class="welcome-title">

                Welcome, <?php echo e($firstName); ?>!

            </h1>



            <?php if ($isManager): ?>
                <p class="intro">Manage users, members, applications, payments, membership rules and system activities.</p>
                <h2 class="section-title">Administration & Management</h2>
                <div class="row g-4">
                    <div class="col-md-6 col-xl-4"><a class="text-decoration-none text-reset" href="user/user_find.php"><div class="module-card linked"><div class="module-icon"><i class="bi bi-person-fill-gear"></i></div><h3>Find User</h3><p>Search user accounts, view details and manage account access.</p><span class="module-status">Open user directory <i class="bi bi-arrow-right"></i></span></div></a></div>
                    <div class="col-md-6 col-xl-4"><a class="text-decoration-none text-reset" href="user/user_find.php"><div class="module-card linked"><div class="module-icon"><i class="bi bi-people"></i></div><h3>Find Member</h3><p>Find member records and review their account information.</p><span class="module-status">Open member directory <i class="bi bi-arrow-right"></i></span></div></a></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-file-earmark-text"></i></div><h3>Membership Applications</h3><p>Oversee applications and make final approval or rejection decisions.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-credit-card"></i></div><h3>Payment Management</h3><p>Oversee payments, fees and payment-related activities.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-sliders"></i></div><h3>Membership Settings</h3><p>Manage membership tiers, fees, eligibility rules and system settings.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-arrow-repeat"></i></div><h3>Batch Jobs & Reminders</h3><p>Manage payment, renewal and upgrade reminder processes.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-file-earmark-bar-graph"></i></div><h3>Reports</h3><p>Review membership, user, payment and operational reports.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-journal-check"></i></div><h3>Audit Log</h3><p>Review recorded activities and changes made within the system.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-folder-check"></i></div><h3>Document Verification</h3><p>Review and verify documents submitted with applications.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-receipt"></i></div><h3>Receipts & Certificates</h3><p>Oversee issuing receipts and membership certificates.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-person-gear"></i></div><h3>Update Member Records</h3><p>Review and update member records as required.</p></div></div>
                </div>

            <?php elseif ($isOps): ?>
                <p class="intro">Manage day-to-day membership operations, applications, documents, payments and renewals.</p>
                <h2 class="section-title">Membership Operations</h2>
                <div class="row g-4">
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-file-earmark-check"></i></div><h3>Review Applications</h3><p>Check membership applications and review submitted information.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-folder-check"></i></div><h3>Verify Documents</h3><p>Check and verify documents submitted with membership applications.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-credit-card"></i></div><h3>Verify Payments</h3><p>Verify membership payments and confirm payment records.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-receipt"></i></div><h3>Issue Receipts & Certificates</h3><p>Issue receipts and membership certificates after the required checks.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-arrow-repeat"></i></div><h3>Process Renewals</h3><p>Process membership renewals and update renewal information.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-person-gear"></i></div><h3>Update Member Records</h3><p>Maintain member information and records within the permitted scope.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-bell"></i></div><h3>Renewal Reminders</h3><p>Review members approaching expiry and trigger renewal reminders.</p></div></div>
                </div>

            <?php elseif ($isMember): ?>
                <p class="intro">Welcome to your membership account. You can manage your own profile and access your membership services here.</p>
                <h2 class="section-title">My Membership</h2>
                <div class="row g-4">
                    <div class="col-md-6 col-xl-4">
                        <a href="my_profile.php"
                        class="text-decoration-none text-reset d-block h-100">

                            <div class="module-card linked">

                                <div class="module-icon">
                                    <i class="bi bi-person-circle"></i>
                                </div>

                                <h3>My Profile</h3>

                                <p>
                                    View and update your personal contact information.
                                </p>

                                <span class="module-status">
                                    View My Profile
                                    <i class="bi bi-arrow-right"></i>
                                </span>

                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-award"></i></div><h3>My Membership</h3><p>View your membership information, membership number and current status.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-file-earmark-text"></i></div><h3>My Applications</h3><p>Track your membership applications and upgrade requests.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-credit-card"></i></div><h3>My Payments</h3><p>View your payment history and outstanding payments.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-arrow-repeat"></i></div><h3>My Renewals</h3><p>View renewal information and available renewal actions.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-download"></i></div><h3>Receipts & Certificates</h3><p>Access your own receipts and membership certificates.</p></div></div>
                </div>

            <?php else: ?>
                <p class="intro">Welcome to your account. You can manage your profile and apply for membership when you are ready.</p>
                <h2 class="section-title">My Account</h2>
                <div class="row g-4">
                    <a href="my_profile.php" class="dashboard-card profile-tile">

                        <div class="card-icon">
                            <i class="bi bi-person-circle"></i>
                        </div>

                        <h3>My Profile</h3>

                        <p>View and update your personal contact information.</p>

                    </a>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-person-plus"></i></div><h3>Apply for Membership</h3><p>Choose an eligible membership tier and submit an application.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-file-earmark-text"></i></div><h3>My Applications</h3><p>View the status of your membership applications.</p></div></div>
                    <div class="col-md-6 col-xl-4"><div class="module-card"><div class="module-icon"><i class="bi bi-credit-card"></i></div><h3>My Payments</h3><p>View your payment information and upload payment slips when requested.</p></div></div>
                </div>
            <?php endif; ?>



        </main>

    </div>



    <script>

        (function () {

            var toggleButton = document.getElementById('sidebarToggle');

            var dashboardLayout = document.querySelector('.dashboard-layout');



            if (toggleButton && dashboardLayout) {

                toggleButton.onclick = function () {

                    var collapsed = dashboardLayout.classList.toggle('sidebar-collapsed');

                    toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

                };

            }

        })();

    </script>



    <script src="assets/js/bootstrap.bundle.min.js"></script>



</body>

</html>
