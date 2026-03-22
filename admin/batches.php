<?php
// admin/batches.php
require_once '../config/db.php';
$active_page = 'admin_batches';
$page_title = 'Admin - Batch Management';
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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_batch'])) {
        $name = trim($_POST['batch_name']);
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO batches (name) VALUES (?)");
            if ($stmt->execute([$name])) {
                $message = 'Batch added successfully!';
            }
        }
    }

    if (isset($_POST['edit_batch'])) {
        $batch_id = $_POST['batch_id'];
        $new_name = trim($_POST['batch_name']);
        if (!empty($new_name)) {
            $stmt = $pdo->prepare("UPDATE batches SET name = ? WHERE id = ?");
            if ($stmt->execute([$new_name, $batch_id])) {
                $message = 'Batch renamed successfully!';
            }
        }
    }

    if (isset($_POST['delete_batch'])) {
        $batch_id = $_POST['batch_id'];
        $stmt = $pdo->prepare("DELETE FROM batches WHERE id = ?");
        if ($stmt->execute([$batch_id])) {
            $message = 'Batch deleted successfully!';
        }
    }
}

// Fetch all batches with student counts
$batches = $pdo->query("
    SELECT b.*, 
    (SELECT COUNT(*) FROM users u WHERE u.batch_id = b.id AND u.role = 'student') AS student_count 
    FROM batches b 
    ORDER BY b.name DESC
")->fetchAll();

// Fetch all students grouped by batch for the modals
$all_students = $pdo->query("
    SELECT id, full_name, mobile_number, batch_id, is_verified 
    FROM users 
    WHERE role = 'student' AND batch_id IS NOT NULL
    ORDER BY full_name ASC
")->fetchAll();

$students_by_batch = [];
foreach ($all_students as $s) {
    $students_by_batch[$s['batch_id']][] = $s;
}
?>

<style>
.batch-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.batch-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.student-list-item:hover {
    background-color: var(--bs-light);
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6 text-start">
            <h2 class="fw-bold"><i class="bi bi-collection-fill text-primary"></i> Batch Management</h2>
            <p class="text-muted">Click on a batch to view or search enrolled students.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                <i class="bi bi-plus-circle me-1"></i> Add New Batch
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($batches as $batch): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                <div class="card glass-card border-0 h-100 p-3 batch-card" data-bs-toggle="modal" data-bs-target="#viewBatchModal<?php echo $batch['id']; ?>">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="p-3 bg-primary-subtle rounded-3">
                            <i class="bi bi-mortarboard-fill text-primary h4 mb-0"></i>
                        </div>
                        <div class="d-flex gap-1">
                            <!-- Edit Modal Trigger -->
                            <button type="button" class="btn btn-sm btn-outline-primary border-0" data-bs-toggle="modal" data-bs-target="#editBatchModal<?php echo $batch['id']; ?>" onclick="event.stopPropagation();">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            
                            <form method="POST" onsubmit="event.stopPropagation(); return confirm('Deleting this batch will unassign all linked students. Proceed?');">
                                <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                <button type="submit" name="delete_batch" class="btn btn-sm btn-outline-danger border-0" onclick="event.stopPropagation();">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($batch['name']); ?></h4>
                    <span class="text-muted small">Academic Year Batch</span>
                    <hr class="text-secondary opacity-25">
                    
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <div class="text-primary fw-bold">
                            <i class="bi bi-people-fill me-1"></i> <?php echo $batch['student_count']; ?> Students
                        </div>
                        <div class="small text-muted">
                            <i class="bi bi-calendar3 me-1"></i> <?php echo date('Y', strtotime($batch['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Batch Students Modal -->
            <div class="modal fade" id="viewBatchModal<?php echo $batch['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-primary text-white border-bottom-0 p-4">
                            <div>
                                <h5 class="modal-title fw-bold mb-0"><?php echo htmlspecialchars($batch['name']); ?> - Student List</h5>
                                <p class="mb-0 small opacity-75">Total of <?php echo $batch['student_count']; ?> students enrolled</p>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <!-- In-Modal Search -->
                            <div class="input-group mb-4 shadow-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 py-2 student-modal-search" 
                                       placeholder="Search students within this batch..." 
                                       data-batch-id="<?php echo $batch['id']; ?>">
                            </div>

                            <div class="list-group list-group-flush rounded-3 border overflow-hidden" id="studentList<?php echo $batch['id']; ?>">
                                <?php 
                                $batch_students = $students_by_batch[$batch['id']] ?? []; 
                                if (empty($batch_students)): ?>
                                    <div class="list-group-item py-5 text-center text-muted">
                                        <i class="bi bi-person-x h1 d-block mb-2 opacity-25"></i>
                                        No students assigned to this batch yet.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($batch_students as $student): ?>
                                        <div class="list-group-item p-3 student-list-item" data-search-term="<?php echo strtolower($student['full_name'] . ' ' . ($student['mobile_number'] ?? '')); ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary-subtle text-primary rounded-circle p-2 me-3">
                                                        <i class="bi bi-person-fill"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                        <div class="small text-muted">
                                                            <i class="bi bi-phone me-1"></i><?php echo htmlspecialchars($student['mobile_number'] ?? 'N/A'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($student['is_verified']): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success px-3 rounded-pill">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning px-3 rounded-pill text-dark">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Batch Modal -->
            <div class="modal fade" id="editBatchModal<?php echo $batch['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg text-dark">
                        <div class="modal-header border-bottom-0 p-4">
                            <h5 class="modal-title fw-bold">Edit Batch Name</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body p-4 pt-0">
                                <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                <label class="form-label small fw-bold mb-2">New Batch Name</label>
                                <input type="text" name="batch_name" class="form-control form-control-lg mb-3 shadow-none border" value="<?php echo htmlspecialchars($batch['name']); ?>" required>
                            </div>
                            <div class="modal-footer border-top-0 p-4 pt-0">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="edit_batch" class="btn btn-primary px-4 shadow-sm">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 p-4">
                <h5 class="modal-title fw-bold">Create New Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4 pt-0">
                    <label class="form-label small fw-bold mb-2">Batch Name</label>
                    <input type="text" name="batch_name" class="form-control form-control-lg mb-3 shadow-none border" placeholder="e.g. 2029AL" required>
                    <p class="text-muted small">Provide a unique name for the academic year batch.</p>
                </div>
                <div class="modal-footer border-top-0 p-4 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_batch" class="btn btn-primary px-4 shadow-sm">Create Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.student-modal-search').forEach(input => {
    input.addEventListener('input', function() {
        const batchId = this.dataset.batchId;
        const term = this.value.toLowerCase();
        const items = document.querySelectorAll(`#studentList${batchId} .student-list-item`);
        
        items.forEach(item => {
            const searchText = item.dataset.searchTerm;
            if (searchText.includes(term)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
