<?php
// student/lesson_view.php
require_once '../config/db.php';
$active_page = 'student_dashboard';
$page_title = 'Lesson View';
require_once '../includes/header.php';

// Check if user is student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$lesson_id) {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch Lesson details
$stmt = $pdo->prepare("
    SELECT l.*, b.name as batch_name,
    (SELECT COUNT(*) FROM progress p WHERE p.lesson_id = l.id AND p.user_id = ?) as is_completed
    FROM lessons l 
    JOIN batches b ON l.batch_id = b.id
    WHERE l.id = ?
");
$stmt->execute([$user_id, $lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Lesson not found.</div></div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch Videos
$stmt = $pdo->prepare("SELECT * FROM lesson_videos WHERE lesson_id = ? ORDER BY display_order ASC");
$stmt->execute([$lesson_id]);
$videos = $stmt->fetchAll();

// Fetch Resources
$stmt = $pdo->prepare("SELECT * FROM lesson_resources WHERE lesson_id = ? ORDER BY display_order ASC");
$stmt->execute([$lesson_id]);
$resources = $stmt->fetchAll();

// YouTube ID Helper
function getYouTubeID($url) {
    preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
    return $matches[1] ?? null;
}

// Handle completion
if (isset($_POST['mark_done'])) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO progress (user_id, lesson_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $lesson_id]);
    header("Location: lesson_view.php?id=$lesson_id");
    exit;
}
?>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-5 align-items-center bg-white p-4 rounded-4 border shadow-sm mx-0">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2 small">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($lesson['batch_name']); ?></li>
                </ol>
            </nav>
            <h1 class="fw-bold mb-1"><?php echo htmlspecialchars($lesson['title']); ?></h1>
            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="badge badge-tech rounded-pill"><?php echo $lesson['class_type']; ?></span>
                <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i> <?php echo date('M d, Y', strtotime($lesson['created_at'])); ?></span>
            </div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <?php if ($lesson['is_completed']): ?>
                <div class="btn btn-success rounded-pill px-4 py-2 disabled shadow-none">
                    <i class="bi bi-check-circle-fill me-2"></i> Lesson Completed
                </div>
            <?php else: ?>
                <form method="POST">
                    <button type="submit" name="mark_done" class="btn btn-primary rounded-pill px-4 py-2 shadow">
                        <i class="bi bi-check2-circle me-2"></i> Mark Lesson as Done
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Videos Section -->
    <?php if (!empty($videos)): ?>
        <h4 class="fw-bold mb-4 border-start border-4 border-primary ps-3">Video Lectures</h4>
        <div class="row g-4 mb-5">
            <?php foreach ($videos as $index => $video): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm overflow-hidden bg-white rounded-4">
                        <div class="card-header bg-light border-0 py-3 px-4 d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-dark"><i class="bi bi-play-btn-fill text-danger me-2"></i> PART <?php echo $index + 1; ?></span>
                            <small class="text-muted">YouTube Embed</small>
                        </div>
                        <div class="ratio ratio-21x9 bg-dark">
                            <?php $yt_id = getYouTubeID($video['video_url']); ?>
                            <?php if ($yt_id): ?>
                                <iframe src="https://www.youtube.com/embed/<?php echo $yt_id; ?>?rel=0" allowfullscreen></iframe>
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center text-white-50">Invalid Video Link</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Learning Materials Section -->
    <?php if (!empty($resources)): ?>
        <h4 class="fw-bold mb-4 border-start border-4 border-success ps-3">Learning Materials</h4>
        <div class="row g-3">
            <?php foreach ($resources as $res): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm p-3 bg-white hover-up transition-all rounded-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                                <?php 
                                    if ($res['resource_type'] == 'link') {
                                        $icon = 'bi-link-45deg';
                                    } else {
                                        $ext = strtolower(pathinfo($res['file_name'], PATHINFO_EXTENSION));
                                        $icon = ($ext == 'pdf') ? 'bi-file-earmark-pdf-fill' : 'bi-image-fill';
                                    }
                                ?>
                                <i class="bi <?php echo $icon; ?> h4 mb-0"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h6 class="fw-bold mb-1 text-truncate">
                                    <?php echo ($res['resource_type'] == 'link') ? 'External Resource' : htmlspecialchars($res['file_name']); ?>
                                </h6>
                                <?php if ($res['resource_type'] == 'link'): ?>
                                    <a href="<?php echo htmlspecialchars($res['file_path']); ?>" target="_blank" class="btn btn-sm btn-primary px-3 rounded-pill">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Link
                                    </a>
                                <?php else: ?>
                                    <a href="../<?php echo $res['file_path']; ?>" class="btn btn-sm btn-outline-primary px-3 rounded-pill" download>
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($videos) && empty($resources)): ?>
        <div class="text-center py-5">
            <div class="p-5 bg-white rounded-4 shadow-sm d-inline-block border">
                <i class="bi bi-inbox text-muted h1 opacity-25 d-block mb-3"></i>
                <p class="text-muted">This lesson container is currently empty.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
