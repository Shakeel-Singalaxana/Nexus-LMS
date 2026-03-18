<?php
// index.php
require_once 'config/db.php';
require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Check against Admin Credentials first
        require_once 'config/admin_cfg.php';
        if ($username === $admin_username && $password === $admin_password_raw) {
            // Admin Login Successful
            $_SESSION['user_id'] = 0; // Admin index 0 or special
            $_SESSION['username'] = $admin_username;
            $_SESSION['full_name'] = 'Administrator';
            $_SESSION['role'] = 'admin';
            header('Location: admin/dashboard.php');
            exit;
        }

        // Check against Database for Students
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_verified'] = $user['is_verified'];
            $_SESSION['is_first_login'] = $user['is_first_login'];
            $_SESSION['batch_id'] = $user['batch_id'];

            if ($user['is_first_login']) {
                header('Location: auth/change_password.php');
            } else {
                header('Location: student/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }

    if (isset($_POST['signup'])) {
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role, is_verified, is_first_login) VALUES (?, ?, ?, 'student', 0, 1)");
                if ($stmt->execute([$full_name, $username, $hashed_password])) {
                    $success = 'Successfully registered! Please login. Your account is pending verification.';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}
?>

<div class="container min-vh-100 d-flex flex-column justify-content-center py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-card border-0 p-4">
                <!-- Tabs for Login/Signup -->
                <ul class="nav nav-pills mb-4 justify-content-center" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active px-4" id="pills-login-tab" data-bs-toggle="pill" data-bs-target="#pills-login" type="button" role="tab">Login</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4" id="pills-signup-tab" data-bs-toggle="pill" data-bs-target="#pills-signup" type="button" role="tab">New Sign Up</button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    <!-- Login Form -->
                    <div class="tab-pane fade show active" id="pills-login" role="tabpanel">
                        <form method="POST">
                            <h4 class="text-center mb-4 fw-bold">Sign In</h4>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Username</label>
                                <input type="text" name="username" class="form-control bg-light" placeholder="Enter username" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Password</label>
                                <input type="password" name="password" class="form-control bg-light" placeholder="Enter password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100 py-2 shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Login
                            </button>
                        </form>
                    </div>

                    <!-- Signup Form -->
                    <div class="tab-pane fade" id="pills-signup" role="tabpanel">
                        <form method="POST">
                            <h4 class="text-center mb-4 fw-bold">Register Account</h4>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="full_name" class="form-control bg-light" placeholder="e.g. John Doe" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Username</label>
                                <input type="text" name="username" class="form-control bg-light" placeholder="Choose a username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Password</label>
                                <input type="password" name="password" class="form-control bg-light" placeholder="Create a password" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control bg-light" placeholder="Repeat your password" required>
                            </div>
                            <button type="submit" name="signup" class="btn btn-outline-primary w-100 py-2">
                                <i class="bi bi-person-plus me-2"></i> Create Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <small class="text-muted">© <?php echo date('Y'); ?> Learning Management System. <br> Powered by InfinityFree.</small>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
