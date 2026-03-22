<?php
// admin/dashboard.php
require_once '../config/db.php';
$active_page = 'admin_dashboard';
$page_title = 'Admin - Student Management';
// Start session manually if not already started to check login BEFORE header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/header.php';

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
            <a href="export_students.php" class="btn btn-outline-success me-2">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Student CSV
            </a>
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

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="search-container shadow-sm border rounded-pill overflow-hidden bg-white">
                <i class="bi bi-search h5 mb-0 opacity-50"></i>
                <input type="text" id="studentSearch" class="form-control border-0 ps-5 py-3" placeholder="Search student by name or mobile number...">
            </div>
        </div>
    </div>

    <div class="card glass-card border-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Student Name</th>
                        <th>Mobile Number</th>
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
                                <td><code><?php echo htmlspecialchars($student['mobile_number']); ?></code></td>
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
                                        <!-- View Stats (AJAX Modal) -->
                                        <button type="button" class="btn btn-sm btn-outline-info stats-btn" 
                                                data-student-id="<?php echo $student['id']; ?>" 
                                                data-bs-toggle="modal" data-bs-target="#studentStatsModal" 
                                                title="View Performance">
                                            <i class="bi bi-graph-up"></i> Stats
                                        </button>

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

<!-- Single Stat Modal (AJAX Loaded) -->
<div class="modal fade" id="studentStatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0 p-4">
                <div id="statsHeaderContent">
                    <h5 class="modal-title fw-bold">Student Performance</h5>
                    <p class="mb-0 small opacity-75">Loading analytics...</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="statsModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Student Performance Stats AJAX Loader
document.querySelectorAll('.stats-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const studentId = this.dataset.studentId;
        const modalBody = document.getElementById('statsModalBody');
        const headerContent = document.getElementById('statsHeaderContent');

        // Reset state
        modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
        headerContent.innerHTML = '<h5 class="modal-title fw-bold">Student Performance</h5><p class="mb-0 small opacity-75">Loading analytics...</p>';

        fetch(`ajax_student_stats.php?student_id=${studentId}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    modalBody.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                    return;
                }

                // Update Header
                headerContent.innerHTML = `
                    <h5 class="modal-title fw-bold">${data.full_name}</h5>
                    <span class="badge bg-primary px-3 rounded-pill">${data.batch_name}</span>
                `;

                // Build Stats Grid
                let statsHtml = `
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 bg-info-subtle p-3 rounded-4 shadow-sm h-100">
                                <h6 class="text-muted small fw-bold text-uppercase mb-2">Lessons Completed</h6>
                                <h3 class="fw-bold mb-0 text-info">${data.completed_count} <small class="text-muted fw-normal" style="font-size: 0.8rem;">/ ${data.total_lessons}</small></h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-primary-subtle p-3 rounded-4 shadow-sm h-100">
                                <h6 class="text-muted small fw-bold text-uppercase mb-2">Completion Rate</h6>
                                <h3 class="fw-bold mb-1 text-primary">${data.completion_rate}%</h3>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: ${data.completion_rate}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-success-subtle p-3 rounded-4 shadow-sm h-100">
                                <h6 class="text-muted small fw-bold text-uppercase mb-2">Videos Watched</h6>
                                <h3 class="fw-bold mb-0 text-success">${data.videos_watched}</h3>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 border-start border-3 border-primary ps-2">Curriculum Progress Track</h6>
                    <div class="table-responsive bg-white rounded-3 border">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="small text-muted">
                                    <th class="ps-3 py-2">LESSON TITLE</th>
                                    <th>TYPE</th>
                                    <th class="text-end pe-3">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                if (data.history.length === 0) {
                    statsHtml += '<tr><td colspan="3" class="text-center py-3 text-muted">No sessions found for this batch.</td></tr>';
                } else {
                    data.history.forEach(item => {
                        const date = item.completed_at ? new Date(item.completed_at).toLocaleDateString() : '--';
                        const statusClass = item.completed_at ? 'text-success' : 'text-muted opacity-50';
                        const statusIcon = item.completed_at ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-circle"></i>';
                        const statusLabel = item.completed_at ? 'Completed' : 'Pending';

                        statsHtml += `
                            <tr>
                                <td class="ps-3 py-2 fw-bold text-dark" style="font-size: 0.85rem;">${item.title}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary py-1" style="font-size: 0.7rem;">${item.class_type}</span></td>
                                <td class="text-end pe-3 font-monospace" style="font-size: 0.8rem;">
                                    <span class="${statusClass}">${statusIcon} ${statusLabel}</span>
                                    ${item.completed_at ? `<div class="x-small text-muted">${date}</div>` : ''}
                                </td>
                            </tr>
                        `;
                    });
                }

                statsHtml += `
                            </tbody>
                        </table>
                    </div>
                `;

                modalBody.innerHTML = statsHtml;
            })
            .catch(err => {
                modalBody.innerHTML = `<div class="alert alert-danger">Error loading stats: ${err}</div>`;
            });
    });
});

// Student Search Logic
document.getElementById('studentSearch').addEventListener('keyup', function(e) {
    const term = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr:not(.empty-row)');
    
    rows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        const mobile = row.cells[1].textContent.toLowerCase();
        
        if (name.includes(term) || mobile.includes(term)) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });

    // Check if any rows are visible
    const visibleRows = Array.from(rows).filter(r => !r.classList.contains('d-none'));
    const tBody = document.querySelector('tbody');
    let emptyMsg = document.getElementById('searchEmptyMsg');
    
    if (visibleRows.length === 0 && rows.length > 0) {
        if (!emptyMsg) {
            emptyMsg = document.createElement('tr');
            emptyMsg.id = 'searchEmptyMsg';
            emptyMsg.classList.add('empty-row');
            emptyMsg.innerHTML = '<td colspan="5" class="text-center py-5 text-muted">No students matching your search.</td>';
            tBody.appendChild(emptyMsg);
        }
    } else if (emptyMsg) {
        emptyMsg.remove();
    }
});

// Prevent form double submission
if ( window.history.replaceState ) {
    window.history.replaceState( null, null, window.location.href );
}
</script>

<?php require_once '../includes/footer.php'; ?>
