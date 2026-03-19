<?php
// admin/edit_lesson.php
require_once '../config/db.php';
$active_page = 'admin_lessons';
$page_title = 'Edit Lesson';
require_once '../includes/header.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$lesson_id = $_GET['id'] ?? null;
if (!$lesson_id) { header('Location: lessons.php'); exit; }

$message = '';
$error = '';

// 1. Update Lesson Basic Info
if (isset($_POST['update_info'])) {
    $stmt = $pdo->prepare("UPDATE lessons SET title = ?, batch_id = ?, class_type = ? WHERE id = ?");
    if ($stmt->execute([$_POST['title'], $_POST['batch_id'], $_POST['class_type'], $lesson_id])) {
        $message = "Lesson information updated.";
    }
}

// 2. Add More Videos
if (isset($_POST['add_videos'])) {
    $raw = trim($_POST['new_youtube_urls']);
    if (!empty($raw)) {
        $urls = explode("\n", $raw);
        foreach ($urls as $url) {
            $url = trim($url);
            if (!empty($url)) {
                $stmt = $pdo->prepare("INSERT INTO lesson_videos (lesson_id, video_url) VALUES (?, ?)");
                $stmt->execute([$lesson_id, $url]);
            }
        }
        $message = "Videos added.";
    }
}

// 3. (REMOVED) Local File uploads are disabled.

// 6. Add More Links
if (isset($_POST['add_links'])) {
    $raw = trim($_POST['new_links'] ?? '');
    if (!empty($raw)) {
        try {
            $pdo->beginTransaction();
            $links = preg_split("/\r\n|\r|\n/", $raw);
            foreach ($links as $index => $link) {
                $link = trim($link);
                if (!empty($link)) {
                    $name = "Resource " . ($index + 1);
                    $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_type, file_path, file_name, display_order) VALUES (?, 'link', ?, ?, ?)");
                    $stmt->execute([$lesson_id, $link, $name, ($index + 100)]);
                }
            }
            $pdo->commit();
            $message = "Links added successfully.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error adding links: " . $e->getMessage();
        }
    }
}

// 4. Delete Single Video
if (isset($_POST['delete_video'])) {
    $stmt = $pdo->prepare("DELETE FROM lesson_videos WHERE id = ? AND lesson_id = ?");
    $stmt->execute([$_POST['video_id'], $lesson_id]);
    $message = "Video removed.";
}

// 5. Delete Single Resource
if (isset($_POST['delete_resource'])) {
    $stmt = $pdo->prepare("SELECT file_path FROM lesson_resources WHERE id = ? AND lesson_id = ?");
    $stmt->execute([$_POST['resource_id'], $lesson_id]);
    $file = $stmt->fetch();
    if ($file) {
        @unlink("../" . $file['file_path']);
        $stmt = $pdo->prepare("DELETE FROM lesson_resources WHERE id = ?");
        $stmt->execute([$_POST['resource_id']]);
        $message = "Resource deleted.";
    }
}

// Fetch Lesson Data
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ?");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch();

$videos = $pdo->prepare("SELECT * FROM lesson_videos WHERE lesson_id = ?");
$videos->execute([$lesson_id]);
$videos = $videos->fetchAll();

$resources = $pdo->prepare("SELECT * FROM lesson_resources WHERE lesson_id = ?");
$resources->execute([$lesson_id]);
$resources = $resources->fetchAll();

$batches = $pdo->query("SELECT * FROM batches ORDER BY name DESC")->fetchAll();

?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="lessons.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left"></i> Back to Lessons</a>
            <h2 class="fw-bold">Edit Lesson: <?php echo htmlspecialchars($lesson['title']); ?></h2>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm border-0 bg-success-subtle text-success p-3 rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm border-0 bg-danger-subtle text-danger p-3 rounded-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Basic Info -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold mb-4">Basic Information</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Lesson Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Batch</label>
                        <select name="batch_id" class="form-select" required>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo ($b['id'] == $lesson['batch_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Class Type</label>
                        <select name="class_type" class="form-select" required>
                            <option value="Theory" <?php echo ($lesson['class_type'] == 'Theory') ? 'selected' : ''; ?>>Theory</option>
                            <option value="Revision" <?php echo ($lesson['class_type'] == 'Revision') ? 'selected' : ''; ?>>Revision</option>
                            <option value="Practical" <?php echo ($lesson['class_type'] == 'Practical') ? 'selected' : ''; ?>>Practical</option>
                        </select>
                    </div>
                    <button type="submit" name="update_info" class="btn btn-primary w-100">Update Basic Info</button>
                </form>
            </div>
        </div>

        <!-- Content Management -->
        <div class="col-lg-7">
            <!-- Videos -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-youtube text-danger"></i> Manage Videos</h5>
                
                <div class="list-group list-group-flush mb-4">
                    <?php foreach ($videos as $v): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center bg-light rounded-3 mb-2 border-0 px-3">
                            <span class="text-truncate small"><?php echo htmlspecialchars($v['video_url']); ?></span>
                            <form method="POST" class="ms-2">
                                <input type="hidden" name="video_id" value="<?php echo $v['id']; ?>">
                                <button type="submit" name="delete_video" onclick="return confirm('Remove video?')" class="btn btn-sm btn-link text-danger"><i class="bi bi-x-circle-fill"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST">
                    <label class="form-label small fw-bold">Add Additional Videos (One per line)</label>
                    <textarea name="new_youtube_urls" class="form-control mb-3" rows="3" placeholder="Paste links here..."></textarea>
                    <button type="submit" name="add_videos" class="btn btn-outline-primary btn-sm">Add Videos</button>
                </form>
            </div>

            <!-- Resources -->
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-text text-primary"></i> Manage Resources & Links</h5>
                
                <div class="list-group list-group-flush mb-4">
                    <?php foreach ($resources as $r): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center bg-light rounded-3 mb-2 border-0 px-3">
                            <span class="text-truncate small">
                                <?php if ($r['resource_type'] == 'link'): ?>
                                    <i class="bi bi-link-45deg me-2 text-primary"></i> <span class="text-muted">Link:</span> <?php echo htmlspecialchars($r['file_path']); ?>
                                <?php else: ?>
                                    <i class="bi bi-paperclip me-2 text-success"></i> <?php echo htmlspecialchars($r['file_name']); ?>
                                <?php endif; ?>
                            </span>
                            <form method="POST" class="ms-2">
                                <input type="hidden" name="resource_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" name="delete_resource" onclick="return confirm('Delete this resource?')" class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <form method="POST" class="h-100 p-3 border rounded">
                            <label class="form-label small fw-bold">Add External Resource Links (One per line)</label>
                            <textarea name="new_links" class="form-control mb-3" rows="3" placeholder="Paste GDrive, Dropbox, or other links here..."></textarea>
                            <button type="submit" name="add_links" class="btn btn-primary btn-sm w-100">Add Links</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
