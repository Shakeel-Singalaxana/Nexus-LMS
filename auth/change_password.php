<?php
// auth/change_password.php
require_once '../config/db.php';

// Start session manually if not already started to check login BEFORE header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';
$redirect_now = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_id = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_first_login = 0 WHERE id = ?");
        if ($stmt->execute([$hashed_password, $user_id])) {
            $_SESSION['is_first_login'] = 0;
            $success = 'Password successfully updated! Redirecting to your dashboard...';
            $redirect_now = true;
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card glass-card border-0 p-4">
                <div class="text-center mb-4">
                    <div class="bg-primary-subtle d-inline-block p-3 rounded-circle mb-3">
                        <i class="bi bi-shield-lock-fill h1 text-primary"></i>
                    </div>
                </div>
                <h4 class="text-center mb-2 fw-bold">Update Password</h4>
                <p class="text-center text-muted mb-4 small">For security reasons, please update your password on your first login.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="bi bi-exclamation-circle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success mb-4">
                        <i class="bi bi-check-circle me-2"></i> <?php echo $success; ?>
                    </div>
                    <script>
                        setTimeout(function() {
                            window.location.href = '../student/dashboard.php';
                        }, 2000);
                    </script>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 shadow-sm">
                        <i class="bi bi-save me-2"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
