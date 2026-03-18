<?php
// admin/dashboard.php
require_once '../config/db.php';
$active_page = 'admin_dashboard';
$page_title = 'Admin - Student Management';
require_once '../includes/header.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : null;

    if (isset($_POST['verify']) && $student_id) {
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        if ($stmt->execute([$student_id])) {
            $message = 'Student verified successfully!';
        } else {
            $error = 'Verification failed.';
        }
    }

    if (isset($_POST['assign_batch']) && $student_id) {
        $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
        if ($batch_id) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET batch_id = ? WHERE id = ?");
                if ($stmt->execute([$batch_id, $student_id])) {
                    $message = 'Batch assigned successfully!';
                } else {
                    $error = 'Could not update student record.';
                }
            } catch (PDOException $e) {
                $error = 'Database Error: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid batch selection.';
        }
    }

    if (isset($_POST['reset_password']) && $student_id) {
        $default_password = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_first_login = 1 WHERE id = ?");
        if ($stmt->execute([$default_password, $student_id])) {
            $message = 'Password reset to default (123456).';
        } else {
            $error = 'Reset failed.';
        }
    }

    if (isset($_POST['delete_student']) && $student_id) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$student_id])) {
            $message = 'Student account deleted.';
        } else {
            $error = 'Deletion failed.';
        }
    }
}

// Fetch all batches
$batches = $pdo->query("SELECT * FROM batches ORDER BY name DESC")->fetchAll();

// Fetch all students
$students = $pdo->query("
    SELECT u.*, b.name AS batch_name 
    FROM users u 
    LEFT JOIN batches b ON u.batch_id = b.id 
    WHERE u.role = 'student' 
    ORDER BY u.created_at DESC
")->fetchAll();

?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold"><i class="bi bi-people-fill text-primary"></i> Student Management</h2>
            <p class="text-muted">Manage user verification, batch assignment, and account security.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <span class="badge bg-secondary p-2 px-3">Total Students: <?php echo count($students); ?></span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card glass-card border-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Student Name</th>
                        <th>Username</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No students found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                    <small class="text-muted">Registered: <?php echo date('Y-m-d', strtotime($student['created_at'])); ?></small>
                                </td>
                                <td><code><?php echo htmlspecialchars($student['username']); ?></code></td>
                                <td>
                                    <?php if ($student['batch_name']): ?>
                                        <span class="badge bg-info-subtle text-info border border-info shadow-none"><?php echo htmlspecialchars($student['batch_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($student['is_verified']): ?>
                                        <span class="badge bg-success shadow-none"><i class="bi bi-check2"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark shadow-none"><i class="bi bi-clock"></i> Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group shadow-sm">
                                        <!-- Verify Action -->
                                        <?php if (!$student['is_verified']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" name="verify" class="btn btn-sm btn-outline-success" title="Verify Account">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Batch Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#batchModal<?php echo $student['id']; ?>" title="Assign Batch">
                                            <i class="bi bi-tag"></i> Batch
                                        </button>

                                        <!-- Reset Password -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Reset password to 123456?');">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="reset_password" class="btn btn-sm btn-outline-secondary" title="Reset Password">
                                                <i class="bi bi-key"></i>
                                            </button>
                                        </form>

                                        <!-- Delete Student -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student account?');">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="delete_student" class="btn btn-sm btn-outline-danger" title="Delete Account">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals (Moved outside table for proper rendering) -->
<?php if (!empty($students)): ?>
    <?php foreach ($students as $student): ?>
        <div class="modal fade" id="batchModal<?php echo $student['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">Assign Batch: <?php echo htmlspecialchars($student['full_name']); ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body py-4">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Active Batch</label>
                                <select name="batch_id" class="form-select form-select-lg" required>
                                    <option value="">-- Choose Batch --</option>
                                    <?php foreach ($batches as $batch): ?>
                                        <option value="<?php echo $batch['id']; ?>" <?php echo ($student['batch_id'] == $batch['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($batch['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="text-muted small">The student will be able to access all lessons belonging to this batch.</p>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="assign_batch" class="btn btn-primary px-4">Update Student Batch</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Prevent form double submission
if ( window.history.replaceState ) {
    window.history.replaceState( null, null, window.location.href );
}
</script>

<?php require_once '../includes/footer.php'; ?>
