<?php
// admin/lessons.php
require_once '../config/db.php';
$active_page = 'admin_lessons';
$page_title = 'Admin - Lesson Management';
require_once '../includes/header.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

// Helper function to extract YouTube ID
function getYouTubeID($url) {
    preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
    return $matches[1] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Lesson
    if (isset($_POST['add_lesson'])) {
        $batch_id = $_POST['batch_id'];
        $class_type = $_POST['class_type'];
        $title = trim($_POST['title']);
        $youtube_urls_raw = trim($_POST['youtube_urls']);
        
        try {
            $pdo->beginTransaction();
            
            // 1. Insert base lesson
            $stmt = $pdo->prepare("INSERT INTO lessons (batch_id, class_type, title) VALUES (?, ?, ?)");
            $stmt->execute([$batch_id, $class_type, $title]);
            $lesson_id = $pdo->lastInsertId();
            
            // 2. Insert videos
            if (!empty($youtube_urls_raw)) {
                $urls = explode("\n", $youtube_urls_raw);
                foreach ($urls as $index => $url) {
                    $url = trim($url);
                    if (!empty($url)) {
                        $stmt = $pdo->prepare("INSERT INTO lesson_videos (lesson_id, video_url, display_order) VALUES (?, ?, ?)");
                        $stmt->execute([$lesson_id, $url, ($index + 1)]);
                    }
                }
            }

            // 3. Insert external links
            $external_links = trim($_POST['external_links'] ?? '');
            if (!empty($external_links)) {
                $links = explode("\n", $external_links);
                foreach ($links as $index => $link) {
                    $link = trim($link);
                    if (!empty($link)) {
                        $name = "Resource Link " . ($index + 1);
                        $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_type, file_path, file_name, display_order) VALUES (?, 'link', ?, ?, ?)");
                        $stmt->execute([$lesson_id, $link, $name, ($index + 100)]); // Using offset to keep links separate if needed
                    }
                }
            }
            
            // 4. Insert local files
            if (!empty($_FILES['lesson_files']['name'][0])) {
                $target_dir = "../uploads/";
                foreach ($_FILES['lesson_files']['tmp_name'] as $key => $tmp_name) {
                    $original_name = $_FILES['lesson_files']['name'][$key];
                    $file_name = time() . '_' . $key . '_' . basename($original_name);
                    $target_file = $target_dir . $file_name;
                    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                    if (in_array($file_type, ["pdf", "png", "jpg", "jpeg"])) {
                        if (move_uploaded_file($tmp_name, $target_file)) {
                            $file_path = "uploads/" . $file_name;
                            $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_type, file_path, file_name, display_order) VALUES (?, 'file', ?, ?, ?)");
                            $stmt->execute([$lesson_id, $file_path, $original_name, ($key + 200)]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            $message = 'Lesson created with all materials successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error adding lesson: ' . $e->getMessage();
        }
    }

    // Delete Lesson
    if (isset($_POST['delete_lesson'])) {
        $lesson_id = $_POST['lesson_id'];
        
        // Fetch files to delete from server
        try {
            $stmt = $pdo->prepare("SELECT file_path FROM lesson_resources WHERE lesson_id = ?");
            $stmt->execute([$lesson_id]);
            $files = $stmt->fetchAll();
            foreach ($files as $file) {
                if (!empty($file['file_path'])) @unlink("../" . $file['file_path']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
            if ($stmt->execute([$lesson_id])) {
                $message = 'Lesson and all its associated materials deleted.';
            }
        } catch (Exception $e) {
            $error = 'Error deleting lesson: ' . $e->getMessage();
        }
    }
}

// Fetch Batches for dropdown
$batches = $pdo->query("SELECT * FROM batches ORDER BY name DESC")->fetchAll();

// Fetch Lessons with aggregate counts
$lessons = $pdo->query("
    SELECT l.*, b.name AS batch_name,
           (SELECT COUNT(*) FROM lesson_videos lv WHERE lv.lesson_id = l.id) as video_count,
           (SELECT COUNT(*) FROM lesson_resources lr WHERE lr.lesson_id = l.id) as file_count
    FROM lessons l 
    JOIN batches b ON l.batch_id = b.id 
    ORDER BY l.created_at DESC
")->fetchAll();

?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold"><i class="bi bi-journal-text text-primary"></i> Curriculum Management</h2>
            <p class="text-muted">Manage multi-part lessons with multiple videos and resources.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <button class="btn btn-primary px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addLessonModal">
                <i class="bi bi-plus-lg me-1"></i> Create Multi-Part Lesson
            </button>
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

    <div class="card border-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Lesson Title</th>
                        <th>Batch</th>
                        <th>Class Type</th>
                        <th>Content</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lessons)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No lessons uploaded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                    <small class="text-muted">Created: <?php echo date('Y-m-d', strtotime($lesson['created_at'])); ?></small>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-dark"><?php echo htmlspecialchars($lesson['batch_name']); ?></span></td>
                                <td>
                                    <?php 
                                    $badge_class = 'bg-primary';
                                    if ($lesson['class_type'] == 'Revision') $badge_class = 'bg-success';
                                    if ($lesson['class_type'] == 'Practical') $badge_class = 'bg-info';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $lesson['class_type']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-danger-subtle text-danger border border-danger shadow-none me-2">
                                        <i class="bi bi-youtube me-1"></i> <?php echo $lesson['video_count']; ?> Videos
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary shadow-none">
                                        <i class="bi bi-file-earmark-text me-1"></i> <?php echo $lesson['file_count']; ?> Files
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="edit_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-outline-primary shadow-sm" title="Edit Content">
                                            <i class="bi bi-pencil-square"></i> EDIT
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Permanently delete this entire lesson and all its materials?');" class="d-inline">
                                            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                            <button type="submit" name="delete_lesson" class="btn btn-sm btn-outline-danger shadow-sm ms-1">
                                                <i class="bi bi-trash"></i> DELETE
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

<!-- Add Lesson Modal -->
<div class="modal fade" id="addLessonModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white p-4">
                <h5 class="modal-title fw-bold">Create Multi-Part Lesson</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Target Batch</label>
                            <select name="batch_id" class="form-select" required>
                                <option value="">-- Choose Batch --</option>
                                <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Class Type</label>
                            <select name="class_type" class="form-select" required>
                                <option value="Theory">Theory</option>
                                <option value="Revision">Revision</option>
                                <option value="Practical">Practical</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Lesson Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Unit 5: Modern Physics" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">YouTube URLs (One URL per line)</label>
                            <textarea name="youtube_urls" class="form-control" rows="3" placeholder="Paste YouTube links here..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">External Resource Links (GDrive, Dropbox, etc. - One per line)</label>
                            <textarea name="external_links" class="form-control" rows="3" placeholder="Paste external links here..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Local Files (Multiple PDFs/Images)</label>
                            <input type="file" name="lesson_files[]" class="form-control" accept=".pdf,.png,.jpg,.jpeg" multiple>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_lesson" class="btn btn-primary px-5">Publish Lesson</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
