<?php
// student/dashboard.php
require_once '../config/db.php';
$active_page = 'student_dashboard';
$page_title = 'Student Dashboard';
// Start session manually if not already started to check login BEFORE header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

require_once '../includes/header.php';

$user_id = $_SESSION['user_id'];

// Handle Mark as Completed
if (isset($_POST['mark_completed'])) {
    $lesson_id = $_POST['lesson_id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO progress (user_id, lesson_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $lesson_id]);
    // Redirect to avoid resubmission
    header("Location: dashboard.php?type=" . ($_GET['type'] ?? 'Theory'));
    exit;
}

// Refresh user session data from DB
$stmt = $pdo->prepare("SELECT is_verified, batch_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

$is_verified = $current_user['is_verified'] ?? false;
$batch_id = $current_user['batch_id'] ?? null;

// Fetch Batch Name
$batch_name = '';
if ($batch_id) {
    $stmt = $pdo->prepare("SELECT name FROM batches WHERE id = ?");
    $stmt->execute([$batch_id]);
    $batch_name = $stmt->fetchColumn();
}

// Handle Class Type Filtering
$active_type = $_GET['type'] ?? 'Theory';
$class_types = ['Theory', 'Revision', 'Practical'];

// Fetch Lessons and completion status
$lessons = [];
$progress_stats = [];

if ($is_verified && $batch_id) {
    // Fetch Lessons for the active type
    $stmt = $pdo->prepare("
        SELECT l.*, 
               (SELECT COUNT(*) FROM progress p WHERE p.lesson_id = l.id AND p.user_id = ?) as is_completed,
               (SELECT COUNT(*) FROM lesson_videos lv WHERE lv.lesson_id = l.id) as video_count,
               (SELECT COUNT(*) FROM video_progress vp JOIN lesson_videos lv ON vp.video_id = lv.id WHERE lv.lesson_id = l.id AND vp.user_id = ?) as watched_count,
               (SELECT COUNT(*) FROM lesson_resources lr WHERE lr.lesson_id = l.id) as file_count
        FROM lessons l 
        WHERE l.batch_id = ? AND l.class_type = ? 
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $batch_id, $active_type]);
    $lessons = $stmt->fetchAll();

    // Calculate progress for each type based on video counts
    foreach ($class_types as $type) {
        // Total videos in this category for this batch
        $stmt_total = $pdo->prepare("
            SELECT COUNT(*) FROM lesson_videos lv 
            JOIN lessons l ON lv.lesson_id = l.id 
            WHERE l.batch_id = ? AND l.class_type = ?
        ");
        $stmt_total->execute([$batch_id, $type]);
        $total_videos = $stmt_total->fetchColumn();

        // Watched videos in this category
        $stmt_comp = $pdo->prepare("
            SELECT COUNT(*) FROM video_progress vp 
            JOIN lesson_videos lv ON vp.video_id = lv.id 
            JOIN lessons l ON lv.lesson_id = l.id 
            WHERE vp.user_id = ? AND l.batch_id = ? AND l.class_type = ?
        ");
        $stmt_comp->execute([$user_id, $batch_id, $type]);
        $watched_videos = $stmt_comp->fetchColumn();

        // If no videos, fallback to lesson-based progress (optional but good for UX)
        if ($total_videos > 0) {
            $progress_stats[$type] = round(($watched_videos / $total_videos) * 100);
        } else {
            // Fallback: Use lesson completion if no videos exist
            $stmt_lessons = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE batch_id = ? AND class_type = ?");
            $stmt_lessons->execute([$batch_id, $type]);
            $total_l = $stmt_lessons->fetchColumn();
            
            $stmt_comp_l = $pdo->prepare("
                SELECT COUNT(*) FROM progress p 
                JOIN lessons l ON p.lesson_id = l.id 
                WHERE p.user_id = ? AND l.batch_id = ? AND l.class_type = ?
            ");
            $stmt_comp_l->execute([$user_id, $batch_id, $type]);
            $comp_l = $stmt_comp_l->fetchColumn();
            
            $progress_stats[$type] = ($total_l > 0) ? round(($comp_l / $total_l) * 100) : 0;
        }
    }
}

// Helper to get YouTube ID for embedding
function getYouTubeID($url) {
    preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
    return $matches[1] ?? null;
}
?>

<div class="container-fluid py-4">
    <?php if (!$is_verified): ?>
        <!-- Unverified State: Shows Advertisements -->
        <div class="row min-vh-75 align-items-center justify-content-center py-5">
            <div class="col-md-9 text-center">
                <div class="p-5 glass-card border-0 mb-5 shadow-lg">
                    <div class="bg-primary-subtle text-primary d-inline-block p-4 rounded-circle mb-4">
                        <i class="bi bi-shield-lock h1 mb-0"></i>
                    </div>
                    <h1 class="fw-bold mb-3">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                    <p class="lead text-muted mb-4 px-lg-5">Your account has been successfully created. For security and quality control, our administrators must verify your enrollment before you can access the curriculum.</p>
                    <div class="alert alert-warning border-0 shadow-sm d-inline-block py-3 px-5 rounded-pill">
                        <i class="bi bi-info-circle-fill me-2"></i> Only class advertisements are visible right now.
                    </div>
                </div>

                <h2 class="fw-bold mb-5">Explore Our Batches</h2>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card glass-card border-0 h-100 p-4 transition-all hover-up">
                            <div class="text-primary mb-3"><i class="bi bi-mortarboard-fill fs-1"></i></div>
                            <h4 class="fw-bold">2026 A/L Theory</h4>
                            <p class="text-muted small">Targeting the upcoming exam with comprehensive theory coverage and past paper analysis.</p>
                            <button class="btn btn-outline-primary btn-sm mt-auto">Learn More</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card glass-card border-0 h-100 p-4 transition-all hover-up">
                            <div class="text-success mb-3"><i class="bi bi-lightning-charge-fill fs-1"></i></div>
                            <h4 class="fw-bold">2027 A/L Theory</h4>
                            <p class="text-muted small">Perfect for students starting their Advanced Level journey. Focus on core fundamental physics.</p>
                            <button class="btn btn-outline-success btn-sm mt-auto">Learn More</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card glass-card border-0 h-100 p-4 transition-all hover-up">
                            <div class="text-info mb-3"><i class="bi bi-stars fs-1"></i></div>
                            <h4 class="fw-bold">2028 A/L Early Starters</h4>
                            <p class="text-muted small">Special foundation program for Grade 10/11 students aiming for competitive results early on.</p>
                            <button class="btn btn-outline-info btn-sm mt-auto">Learn More</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Verified State: Full Access -->
        <div class="row mb-5 align-items-end g-4">
            <div class="col-lg-6">
                <span class="badge badge-tech rounded-pill mb-2"><?php echo htmlspecialchars($batch_name); ?> Batch</span>
                <h1 class="fw-bold mb-1">Student Dashboard</h1>
                <p class="text-muted">Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! Continue your learning journey.</p>
            </div>
            <div class="col-lg-6 text-lg-end">
                <div class="row g-2 justify-content-lg-end">
                    <?php foreach ($class_types as $type): ?>
                        <div class="col-4">
                            <div class="p-2 border rounded-3 small bg-white shadow-sm">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold text-muted" style="font-size: 0.7rem;"><?php echo strtoupper($type); ?></span>
                                    <span class="fw-bold text-primary" style="font-size: 0.7rem;"><?php echo $progress_stats[$type] ?? 0; ?>%</span>
                                </div>
                                <div class="progress progress-tech" style="height: 4px;">
                                    <div class="progress-bar progress-bar-tech progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $progress_stats[$type] ?? 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row mb-4 align-items-center g-3">
            <div class="col-md-6">
                <div class="nav-pills-container bg-white border p-1 rounded-pill shadow-sm d-inline-flex">
                    <?php foreach ($class_types as $type): ?>
                        <a href="?type=<?php echo $type; ?>" class="btn btn-sm px-4 rounded-pill <?php echo ($active_type == $type) ? 'btn-primary shadow-sm' : 'btn-link text-muted text-decoration-none'; ?>">
                            <?php echo $type; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="search-container ms-auto">
                    <i class="bi bi-search"></i>
                    <input type="text" id="lessonSearch" class="form-control bg-white" placeholder="Search lessons by title...">
                </div>
            </div>
        </div>

        <div class="row g-4" id="lessonsGrid">
            <?php if (!$batch_id): ?>
                <div class="col-12 text-center py-5">
                    <div class="p-5 bg-white rounded-4 border shadow-sm d-inline-block">
                        <i class="bi bi-folder-x h1 text-warning opacity-50 d-block mb-3"></i>
                        <h4 class="fw-bold">No Batch Assigned</h4>
                        <p class="text-muted small">Please contact administration to assign you to a batch (e.g. 2026AL).</p>
                    </div>
                </div>
            <?php elseif (empty($lessons)): ?>
                <div class="col-12 text-center py-5">
                    <div class="p-5 bg-white rounded-4 border shadow-sm d-inline-block">
                        <i class="bi bi-journals h1 text-muted opacity-25 d-block mb-3"></i>
                        <h4 class="fw-bold">No <?php echo $active_type; ?> Lessons</h4>
                        <p class="text-muted small">The administrator hasn't uploaded any content for this category yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($lessons as $lesson): ?>
                    <div class="col-lg-6 col-xl-4 lesson-item">
                        <div class="card lesson-card border-0 h-100 shadow-sm overflow-hidden transition-all">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge badge-tech"><?php echo $lesson['class_type']; ?></span>
                                    <?php if ($lesson['is_completed']): ?>
                                        <span class="badge bg-success-subtle text-success p-2 px-3 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i> Completed
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h4 class="card-title fw-bold mb-3"><?php echo htmlspecialchars($lesson['title']); ?></h4>
                                
                                <div class="row g-2 mb-4">
                                    <div class="col-6">
                                        <div class="p-2 bg-danger-subtle text-danger rounded text-center">
                                            <div class="h4 mb-0"><?php echo $lesson['watched_count']; ?>/<?php echo $lesson['video_count']; ?></div>
                                            <div class="small fw-bold">Watched</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-primary-subtle text-primary rounded text-center">
                                            <div class="h4 mb-0"><?php echo $lesson['file_count']; ?></div>
                                            <div class="small fw-bold">Resources</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <a href="lesson_view.php?id=<?php echo $lesson['id']; ?>" class="btn btn-primary w-100 py-2 fw-bold">
                                        <i class="bi bi-play-circle-fill me-2"></i> Open Lesson
                                    </a>
                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i> Added: <?php echo date('M d, Y', strtotime($lesson['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Real-time Filter Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('lessonSearch');
    const lessonItems = document.querySelectorAll('.lesson-item');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            lessonItems.forEach(item => {
                const title = item.querySelector('.card-title').textContent.toLowerCase();
                if (title.includes(term)) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
