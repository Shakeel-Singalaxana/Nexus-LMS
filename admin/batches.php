<?php
// admin/batches.php
require_once '../config/db.php';
$active_page = 'admin_batches';
$page_title = 'Admin - Batch Management';
require_once '../includes/header.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

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

    if (isset($_POST['delete_batch'])) {
        $batch_id = $_POST['batch_id'];
        $stmt = $pdo->prepare("DELETE FROM batches WHERE id = ?");
        if ($stmt->execute([$batch_id])) {
            $message = 'Batch deleted successfully!';
        }
    }
}

// Fetch all batches
$batches = $pdo->query("SELECT * FROM batches ORDER BY name DESC")->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6 text-start">
            <h2 class="fw-bold"><i class="bi bi-collection-fill text-primary"></i> Batch Management</h2>
            <p class="text-muted">Dynamic management of student batches (2026AL, 2027AL, etc.)</p>
        </div>
        <div class="col-md-6 text-md-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
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
                <div class="card glass-card border-0 h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="p-3 bg-primary-subtle rounded-3">
                            <i class="bi bi-mortarboard-fill text-primary h4 mb-0"></i>
                        </div>
                        <form method="POST" onsubmit="return confirm('Deleting this batch will unassign all linked students. Proceed?');">
                            <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                            <button type="submit" name="delete_batch" class="btn btn-sm btn-outline-danger border-0">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($batch['name']); ?></h4>
                    <span class="text-muted small">Academic Year Batch</span>
                    <hr class="text-secondary opacity-25">
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Created: <?php echo date('Y-m-d', strtotime($batch['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 glass-card">
            <div class="modal-header border-bottom-0 p-4">
                <h5 class="modal-title fw-bold">Create New Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4 pt-0">
                    <label class="form-label small fw-bold mb-2">Batch Name</label>
                    <input type="text" name="batch_name" class="form-control form-control-lg mb-3" placeholder="e.g. 2029AL" required>
                    <p class="text-muted small">Provide a unique name for the academic year batch.</p>
                </div>
                <div class="modal-footer border-top-0 p-4 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_batch" class="btn btn-primary px-4">Create Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
