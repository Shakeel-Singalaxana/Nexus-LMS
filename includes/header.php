<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;
$page_title = $page_title ?? 'LMS Dashboard';

// Dynamic path logic to handle root vs subfolders
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_subfolder = in_array($current_dir, ['admin', 'student', 'auth', 'includes']);
$prefix = $is_subfolder ? '../' : './';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $prefix; ?>assets/css/style.css">
</head>
<body class="bg-light">

<?php if ($is_logged_in): ?>
    <!-- Sidebar Toggle (Mobile) -->
    <div class="d-lg-none p-3 bg-white border-bottom fixed-top shadow-sm d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
            <i class="bi bi-list"></i> Menu
        </button>
        <span class="h5 mb-0 fw-bold text-primary">LMS PORTAL</span>
    </div>

    <!-- Sidebar for Desktop -->
    <div id="sidebar" class="d-none d-lg-flex flex-column p-0">
        <div class="p-4 mb-3 border-bottom border-white border-opacity-10 text-center">
            <h4 class="fw-bold mb-0 text-white">LMS <span class="badge bg-primary fs-7">2.2</span></h4>
        </div>
        <nav class="nav flex-column mb-auto">
            <?php if ($user_role === 'admin'): ?>
                <a class="nav-link <?php echo ($active_page == 'admin_dashboard') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/dashboard.php">
                    <i class="bi bi-people me-2"></i> Students
                </a>
                <a class="nav-link <?php echo ($active_page == 'admin_batches') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/batches.php">
                    <i class="bi bi-collection me-2"></i> Batches
                </a>
                <a class="nav-link <?php echo ($active_page == 'admin_lessons') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/lessons.php">
                    <i class="bi bi-journal-text me-2"></i> Lessons
                </a>
                <a class="nav-link <?php echo ($active_page == 'admin_announcements') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/announcements.php">
                    <i class="bi bi-megaphone me-2"></i> Announcements
                </a>
            <?php else: ?>
                <a class="nav-link <?php echo ($active_page == 'student_dashboard') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>student/dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            <?php endif; ?>
        </nav>
        <div class="p-4 border-top border-white border-opacity-10">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-primary rounded-circle p-2 me-2 text-white shadow-sm">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="text-truncate">
                    <p class="mb-0 small fw-bold text-white"><?php echo $_SESSION['full_name'] ?? 'User'; ?></p>
                    <p class="mb-0 small text-white-50"><?php echo ucfirst($user_role); ?></p>
                </div>
            </div>
            <a href="<?php echo $prefix; ?>auth/logout.php" class="btn btn-danger w-100 btn-sm shadow-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>

    <!-- Offcanvas Sidebar for Mobile -->
    <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarOffcanvas" style="width: 280px;">
        <div class="offcanvas-header border-bottom border-secondary">
            <h5 class="offcanvas-title fw-bold">LMS Menu</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 py-3 d-flex flex-column h-100">
            <nav class="nav flex-column mb-auto">
                <?php if ($user_role === 'admin'): ?>
                    <a class="nav-link <?php echo ($active_page == 'admin_dashboard') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/dashboard.php">
                        <i class="bi bi-people me-2"></i> Students
                    </a>
                    <a class="nav-link <?php echo ($active_page == 'admin_batches') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/batches.php">
                        <i class="bi bi-collection me-2"></i> Batches
                    </a>
                    <a class="nav-link <?php echo ($active_page == 'admin_lessons') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/lessons.php">
                        <i class="bi bi-journal-text me-2"></i> Lessons
                    </a>
                    <a class="nav-link <?php echo ($active_page == 'admin_announcements') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/announcements.php">
                        <i class="bi bi-megaphone me-2"></i> Announcements
                    </a>
                <?php else: ?>
                    <a class="nav-link <?php echo ($active_page == 'student_dashboard') ? 'active' : ''; ?>" href="<?php echo $prefix; ?>student/dashboard.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                <?php endif; ?>
            </nav>
            <div class="p-4 border-top border-secondary mt-auto">
                <a href="<?php echo $prefix; ?>auth/logout.php" class="btn btn-outline-danger w-100">Logout</a>
            </div>
        </div>
    </div>

    <main id="main-content">
        <?php
        // Fetch and show Announcement for Students
        if ($user_role === 'student' && basename($_SERVER['PHP_SELF']) == 'dashboard.php') {
            global $pdo;
            $stmt = $pdo->query("SELECT message FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
            $annMsg = $stmt->fetch();
            if ($annMsg) {
                echo '<div class="alert banner-alert fade show shadow-sm mb-4 py-3" role="alert">
                        <i class="bi bi-megaphone-fill me-2"></i> ' . htmlspecialchars($annMsg['message']) . '
                      </div>';
            }
        }
        ?>
<?php else: ?>
    <!-- Basic Header for Non-Logged-In Pages (Login/Signup) -->
    <div class="bg-white border-bottom py-3 shadow-sm mb-5 text-center">
        <h3 class="fw-bold mb-0 text-gradient">LMS PORTAL</h3>
    </div>
<?php endif; ?>
