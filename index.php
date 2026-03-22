<?php
// index.php
require_once 'config/db.php';

// Start session at the very beginning to check for existing login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// REDIRECT IF ALREADY LOGGED IN
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header('Location: student/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        try {
            $mobile_number = trim($_POST['mobile_number']);
            $password = $_POST['password'];

            // Check against Admin Credentials first
            require_once 'config/admin_cfg.php';
            if ($mobile_number === $admin_username && $password === $admin_password_raw) {
                // Admin Login Successful
                $_SESSION['user_id'] = 0; // Admin index 0 or special
                $_SESSION['username'] = $admin_username;
                $_SESSION['full_name'] = 'Administrator';
                $_SESSION['role'] = 'admin';
                $_SESSION['theme'] = 'light'; // Admin default
                header('Location: admin/dashboard.php');
                exit;
            }

            // Validate mobile number format (10 digits starting with 0)
            if (!preg_match('/^0[0-9]{9}$/', $mobile_number)) {
                $error = 'Invalid mobile number format. Must be 10 digits starting with 0.';
            } else {
                // Check against Database for Students
                // We use a query that handles both 'mobile_number' and 'username' columns for compatibility
                // but we prefer 'mobile_number' as requested.
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile_number = ?");
                    $stmt->execute([$mobile_number]);
                } catch (PDOException $e) {
                    // Fallback to 'username' if 'mobile_number' column doesn't exist yet
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->execute([$mobile_number]);
                }
                
                $user = $stmt->fetch();

                $login_valid = false;
                if ($user) {
                    if (!empty($user['password'])) {
                        if (password_verify($password, $user['password'])) {
                            $login_valid = true;
                        }
                    } elseif ($user['is_first_login']) {
                        // Allow login without password ONLY for first time users who haven't set one yet
                        $login_valid = true;
                    }
                }

                if ($login_valid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['mobile_number'] = $user['mobile_number'] ?? $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['is_verified'] = $user['is_verified'];
                    $_SESSION['is_first_login'] = $user['is_first_login'];
                    $_SESSION['batch_id'] = $user['batch_id'];
                    $_SESSION['theme'] = $user['theme'] ?? 'light';

                    if ($user['is_first_login']) {
                        header('Location: auth/change_password.php');
                    } else {
                        header('Location: student/dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Invalid mobile number or password.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }

    if (isset($_POST['signup'])) {
        try {
            $full_name = trim($_POST['full_name']);
            $mobile_number = trim($_POST['mobile_number']);

            // Validate mobile number format (10 digits starting with 0)
            if (!preg_match('/^0[0-9]{9}$/', $mobile_number)) {
                $error = 'Invalid mobile number format. Must be 10 digits starting with 0.';
            } else {
                // Check if mobile number already exists (checking both columns for compatibility)
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile_number = ?");
                    $stmt->execute([$mobile_number]);
                } catch (PDOException $e) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$mobile_number]);
                }

                if ($stmt->fetch()) {
                    $error = 'Mobile number already registered.';
                } else {
                    // Register without password
                    // Using empty string instead of NULL for compatibility with non-migrated databases
                    // and attempting to use 'mobile_number' column first, then 'username'.
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (full_name, mobile_number, password, role, is_verified, is_first_login) VALUES (?, ?, '', 'student', 0, 1)");
                        $res = $stmt->execute([$full_name, $mobile_number]);
                    } catch (PDOException $e) {
                        // Fallback to 'username' column
                        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role, is_verified, is_first_login) VALUES (?, ?, '', 'student', 0, 1)");
                        $res = $stmt->execute([$full_name, $mobile_number]);
                    }

                    if ($res) {
                        $success = 'Successfully registered! Please login with your mobile number. Your account is pending verification.';
                    } else {
                        $error = 'Something went wrong. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Registration error: ' . $e->getMessage() . '. Please ensure you have run the latest schema.sql on your host.';
        }
    }
}
require_once 'includes/header.php';
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
            <!-- Heading -->
            <div class="text-center mb-5">
                <div class="d-inline-block bg-primary p-3 rounded-circle shadow-lg mb-3">
                    <i class="bi bi-grid-fill text-white h2 mb-0"></i>
                </div>
                <h1 class="fw-bold text-gradient display-5">Nexus-LMS</h1>
                <p class="text-muted small">Learning Management System portal</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-card border-0 shadow-lg p-5 mb-4">
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
                        <form method="POST" id="loginForm">
                            <h4 class="text-center mb-4 fw-bold">Sign In</h4>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Mobile Number</label>
                                <input type="text" name="mobile_number" class="form-control bg-light" placeholder="e.g. 0123456789" maxlength="10" pattern="0[0-9]{9}" required>
                                <div class="form-text small">10 digits starting with 0</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Password</label>
                                <input type="password" name="password" class="form-control bg-light" placeholder="Enter password (leave blank if first time)">
                                <div class="form-text small">Enter password if already set.</div>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100 py-2 shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Login
                            </button>
                        </form>
                    </div>

                    <!-- Signup Form -->
                    <div class="tab-pane fade" id="pills-signup" role="tabpanel">
                        <form method="POST" id="signupForm">
                            <h4 class="text-center mb-4 fw-bold">Register Account</h4>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="full_name" class="form-control bg-light" placeholder="e.g. John Doe" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Mobile Number</label>
                                <input type="text" name="mobile_number" class="form-control bg-light" placeholder="e.g. 0123456789" maxlength="10" pattern="0[0-9]{9}" required>
                                <div class="form-text small">10 digits starting with 0. No password required for registration.</div>
                            </div>
                            <button type="submit" name="signup" class="btn btn-outline-primary w-100 py-2">
                                <i class="bi bi-person-plus me-2"></i> Create Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Credits -->
            <div class="text-center mt-4">
                <p class="mb-1 small text-muted">&copy; <?php echo date('Y'); ?> <strong>Nexus-LMS</strong>. All Rights Reserved.</p>
                <p class="small text-muted">
                    Developed by <a href="https://github.com/Shakeel-Singalaxana/" target="_blank" class="text-decoration-none text-primary"><strong>shakbrotech</strong></a> (Shakeel Singalaxana)
                </p>
                <p class="small text-muted mb-0">
                    <i class="bi bi-envelope-at-fill me-1"></i> 
                    <a href="mailto:shefazslaxana@outlook.com" class="text-muted text-decoration-none">shefazslaxana@outlook.com</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('input[name="mobile_number"]').forEach(input => {
    input.addEventListener('input', function(e) {
        // Remove non-digit characters
        this.value = this.value.replace(/[^0-9]/g, '');
        // Limit to 10 digits
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
