<?php
// admin/announcements.php
require_once '../config/db.php';
$active_page = 'admin_announcements';
$page_title = 'Admin - Announcements';
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

$message_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_announcement'])) {
        $msg = trim($_POST['announcement_text']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Deactivate all previous ones or just update the latest one.
        // For simplicity, we'll just insert a new one and deactivate others, or update the specific one.
        // Let's just keep ONE active announcement at a time.
        $pdo->query("UPDATE announcements SET is_active = 0");
        
        if (!empty($msg)) {
            $stmt = $pdo->prepare("INSERT INTO announcements (message, is_active) VALUES (?, ?)");
            if ($stmt->execute([$msg, $is_active])) {
                $message_status = 'Announcement updated successfully!';
            }
        }
    }
}

// Fetch the current active announcement
$stmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
$current_announcement = $stmt->fetch();
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-12">
            <h2 class="fw-bold"><i class="bi bi-megaphone-fill text-primary"></i> Global Announcements</h2>
            <p class="text-muted">Broadcast a banner message to all student dashboards.</p>
        </div>
    </div>

    <?php if ($message_status): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message_status; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card glass-card border-0 p-4 shadow-sm">
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Banner Message Content</label>
                        <textarea name="announcement_text" class="form-control" rows="4" placeholder="Enter the announcement message here..." required><?php echo $current_announcement ? htmlspecialchars($current_announcement['message']) : ''; ?></textarea>
                    </div>
                    <div class="mb-4 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActiveSwitch" <?php echo ($current_announcement && $current_announcement['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold ms-2" for="isActiveSwitch">Show this message on student dashboards</label>
                        </div>
                    </div>
                    <button type="submit" name="update_announcement" class="btn btn-primary px-5 py-2">
                        <i class="bi bi-send-fill me-2"></i> Update Banner
                    </button>
                    <?php if ($current_announcement && $current_announcement['is_active']): ?>
                        <div class="mt-4 p-3 bg-light border rounded">
                            <small class="text-muted d-block mb-1">Live Preview:</small>
                            <div class="alert alert-primary mb-0 py-2 border-0 shadow-sm">
                                <i class="bi bi-info-circle-fill me-2"></i> <?php echo htmlspecialchars($current_announcement['message']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 p-4 h-100 shadow-sm bg-info-subtle bg-opacity-10 border-start border-4 border-info">
                <h5 class="fw-bold mb-3 text-info"><i class="bi bi-lightbulb me-2 text-warning"></i> Admin Tips</h5>
                <ul class="small ps-3 mb-0">
                    <li class="mb-2">Keep messages concise for mobile users.</li>
                    <li class="mb-2">Use announcements for class timing changes, holiday alerts, or exam reminders.</li>
                    <li class="mb-2">You can hide the banner anytime by toggling the switch.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
