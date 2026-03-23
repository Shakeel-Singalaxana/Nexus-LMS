<?php
// admin/lessons.php
require_once '../config/db.php';
$active_page = 'admin_lessons';
$page_title = 'Admin - Lesson Management';
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
            $youtube_urls_raw = trim($_POST['youtube_urls'] ?? '');
            if (!empty($youtube_urls_raw)) {
                $urls = preg_split("/\r\n|\r|\n/", $youtube_urls_raw);
                foreach ($urls as $index => $url) {
                    $url = trim($url);
                    if (!empty($url)) {
                        $stmt = $pdo->prepare("INSERT INTO lesson_videos (lesson_id, video_url, display_order) VALUES (?, ?, ?)");
                        $stmt->execute([$lesson_id, $url, ($index + 1)]);
                    }
                }
            }

            // 3. Insert external links
            $external_links_raw = trim($_POST['external_links'] ?? '');
            if (!empty($external_links_raw)) {
                $links = preg_split("/\r\n|\r|\n/", $external_links_raw);
                foreach ($links as $index => $link) {
                    $link = trim($link);
                    if (!empty($link)) {
                        $name = "Resource" . ($index + 1);
                        $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_type, file_path, file_name, display_order) VALUES (?, 'link', ?, ?, ?)");
                        $stmt->execute([$lesson_id, $link, $name, ($index + 100)]);
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

// Handle Filters
$filter_batch_id = $_GET['batch_id'] ?? '';
$filter_class_type = $_GET['class_type'] ?? '';

$where_clauses = [];
$params = [];

if (!empty($filter_batch_id)) {
    $where_clauses[] = "l.batch_id = ?";
    $params[] = $filter_batch_id;
}

if (!empty($filter_class_type)) {
    $where_clauses[] = "l.class_type = ?";
    $params[] = $filter_class_type;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch Lessons with aggregate counts
$query = "
    SELECT l.*, b.name AS batch_name,
           (SELECT COUNT(*) FROM lesson_videos lv WHERE lv.lesson_id = l.id) as video_count,
           (SELECT COUNT(*) FROM lesson_resources lr WHERE lr.lesson_id = l.id) as file_count
    FROM lessons l 
    JOIN batches b ON l.batch_id = b.id 
    $where_sql
    ORDER BY l.created_at DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lessons = $stmt->fetchAll();
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

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                        <div class="input-group border rounded shadow-none overflow-hidden">
                            <span class="input-group-text bg-transparent border-0 text-muted small fw-bold">BATCH</span>
                            <select name="batch_id" class="form-select border-0 small shadow-none bg-transparent">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>" <?php echo ($filter_batch_id == $batch['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                        <div class="input-group border rounded shadow-none overflow-hidden">
                            <span class="input-group-text bg-transparent border-0 text-muted small fw-bold">TYPE</span>
                            <select name="class_type" class="form-select border-0 small shadow-none bg-transparent">
                            <option value="">All Types</option>
                            <option value="Theory" <?php echo ($filter_class_type == 'Theory') ? 'selected' : ''; ?>>Theory</option>
                            <option value="Revision" <?php echo ($filter_class_type == 'Revision') ? 'selected' : ''; ?>>Revision</option>
                            <option value="Practical" <?php echo ($filter_class_type == 'Practical') ? 'selected' : ''; ?>>Practical</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 shadow-sm w-100">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if (!empty($filter_batch_id) || !empty($filter_class_type)): ?>
                        <a href="lessons.php" class="btn btn-outline-secondary px-3 shadow-sm border-0">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

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
                                <td><span class="badge bg-secondary-subtle"><?php echo htmlspecialchars($lesson['batch_name']); ?></span></td>
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
                            <textarea name="external_links" class="form-control" rows="5" placeholder="Paste external links here..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_lesson" class="btn btn-primary px-5">Publish Lesson</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
