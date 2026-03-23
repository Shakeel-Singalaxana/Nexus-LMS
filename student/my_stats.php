<?php
// student/my_stats.php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$active_page = 'student_stats';
$page_title = 'My Performance';

require_once '../includes/header.php';

// Fetch Student's Batch info
$stmt = $pdo->prepare("SELECT batch_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$student_batch_id = $stmt->fetchColumn();

// 1. Total Lessons for this batch
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE batch_id = ?");
$stmt->execute([$student_batch_id]);
$total_lessons = $stmt->fetchColumn();

// 2. Completed Lessons
$stmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE user_id = ?");
$stmt->execute([$user_id]);
$completed_lessons = $stmt->fetchColumn();

// 3. Total Videos for this batch's lessons
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_videos lv JOIN lessons l ON lv.lesson_id = l.id WHERE l.batch_id = ?");
$stmt->execute([$student_batch_id]);
$total_videos = $stmt->fetchColumn();

// 4. Completed Videos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM video_progress WHERE user_id = ?");
$stmt->execute([$user_id]);
$completed_videos = $stmt->fetchColumn();

// 5. Completion by Category (Theory, Revision, Practical)
$categories = ['Theory', 'Revision', 'Practical'];
$stats_by_cat = [];
foreach ($categories as $cat) {
    // Total in cat
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE batch_id = ? AND class_type = ?");
    $stmt->execute([$student_batch_id, $cat]);
    $total_in_cat = $stmt->fetchColumn();

    // Completed in cat
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM progress p 
        JOIN lessons l ON p.lesson_id = l.id 
        WHERE p.user_id = ? AND l.class_type = ?
    ");
    $stmt->execute([$user_id, $cat]);
    $completed_in_cat = $stmt->fetchColumn();

    $stats_by_cat[$cat] = [
        'total' => $total_in_cat,
        'completed' => $completed_in_cat,
        'percent' => ($total_in_cat > 0) ? round(($completed_in_cat / $total_in_cat) * 100) : 0
    ];
}

// 6. Recent Activity (Last 5 completed items)
$stmt = $pdo->prepare("
    (SELECT 'lesson' as type, l.title, p.completed_at as date 
     FROM progress p JOIN lessons l ON p.lesson_id = l.id 
     WHERE p.user_id = ?)
    UNION
    (SELECT 'video' as type, CONCAT('Video Part ', lv.display_order + 1, ' - ', l.title) as title, vp.watched_at as date 
     FROM video_progress vp 
     JOIN lesson_videos lv ON vp.video_id = lv.id 
     JOIN lessons l ON lv.lesson_id = l.id
     WHERE vp.user_id = ?)
    ORDER BY date DESC LIMIT 8
");
$stmt->execute([$user_id, $user_id]);
$recent_activity = $stmt->fetchAll();

// Calculate overall percentages
$lesson_percent = ($total_lessons > 0) ? round(($completed_lessons / $total_lessons) * 100) : 0;
$video_percent = ($total_videos > 0) ? round(($completed_videos / $total_videos) * 100) : 0;
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold mb-1">My Performance</h2>
            <p class="text-muted">Track your learning progress and achievements.</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4 text-center">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white h-100">
                <div class="bg-primary-subtle text-primary p-3 rounded-circle d-inline-block mx-auto mb-3">
                    <i class="bi bi-journal-check h3 mb-0"></i>
                </div>
                <h3 class="fw-bold mb-0"><?php echo $completed_lessons; ?>/<?php echo $total_lessons; ?></h3>
                <p class="text-muted small mb-0">Lessons Finished</p>
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-primary" style="width: <?php echo $lesson_percent; ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white h-100">
                <div class="bg-danger-subtle text-danger p-3 rounded-circle d-inline-block mx-auto mb-3">
                    <i class="bi bi-play-circle-fill h3 mb-0"></i>
                </div>
                <h3 class="fw-bold mb-0"><?php echo $completed_videos; ?>/<?php echo $total_videos; ?></h3>
                <p class="text-muted small mb-0">Videos Watched</p>
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-danger" style="width: <?php echo $video_percent; ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white h-100">
                <div class="bg-success-subtle text-success p-3 rounded-circle d-inline-block mx-auto mb-3">
                    <i class="bi bi-award h3 mb-0"></i>
                </div>
                <h3 class="fw-bold mb-0"><?php echo floor(($lesson_percent + $video_percent) / 2); ?>%</h3>
                <p class="text-muted small mb-0">Overall Score</p>
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: <?php echo floor(($lesson_percent + $video_percent) / 2); ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <?php 
                $overall_prog = floor(($lesson_percent + $video_percent) / 2);
                $level = 1;
                $level_title = 'Apprentice';
                $level_icon = 'bi-lightning-fill';
                
                if ($overall_prog >= 90) { $level = 6; $level_title = 'Master'; }
                else if ($overall_prog >= 70) { $level = 5; $level_title = 'Expert'; }
                else if ($overall_prog >= 50) { $level = 4; $level_title = 'Scholar'; }
                else if ($overall_prog >= 30) { $level = 3; $level_title = 'Learner'; }
                else if ($overall_prog >= 10) { $level = 2; $level_title = 'Novice'; }
            ?>
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white h-100">
                <div class="bg-warning-subtle text-warning p-3 rounded-circle d-inline-block mx-auto mb-3">
                    <i class="bi <?php echo $level_icon; ?> h3 mb-0"></i>
                </div>
                <h3 class="fw-bold mb-0">Level <?php echo $level; ?></h3>
                <p class="text-muted small mb-0"><?php echo $level_title; ?></p>
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-warning" style="width: <?php echo $overall_prog; ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Performance by Category -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white h-100">
                <h5 class="fw-bold mb-4">Category Progress</h5>
                <?php foreach ($stats_by_cat as $cat => $data): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-600"><?php echo $cat; ?> Lessons</span>
                            <span class="text-muted small"><?php echo $data['completed']; ?>/<?php echo $data['total']; ?> (<?php echo $data['percent']; ?>%)</span>
                        </div>
                        <div class="progress rounded-pill bounce-progress" style="height: 10px;">
                            <?php 
                                $color = 'primary';
                                if ($cat == 'Revision') $color = 'info';
                                if ($cat == 'Practical') $color = 'success';
                            ?>
                            <div class="progress-bar bg-<?php echo $color; ?> rounded-pill" role="progressbar" style="width: <?php echo $data['percent']; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="mt-2 p-3 bg-light rounded-3">
                    <p class="mb-0 small text-muted"><i class="bi bi-info-circle me-1"></i> Completion increases as you finish videos and lessons assigned to your batch.</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white h-100">
                <h5 class="fw-bold mb-4">Recent Activity</h5>
                <?php if (empty($recent_activity)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-activity h1 text-muted opacity-25"></i>
                        <p class="text-muted mt-2">No activity detected yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0 activity-list">
                        <?php foreach ($recent_activity as $act): ?>
                            <li class="mb-3 d-flex align-items-center">
                                <div class="p-2 <?php echo $act['type'] == 'lesson' ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger'; ?> rounded-circle me-3">
                                    <i class="bi <?php echo $act['type'] == 'lesson' ? 'bi-check-all' : 'bi-play-fill'; ?>"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <p class="mb-0 fw-600 text-truncate"><?php echo htmlspecialchars($act['title']); ?></p>
                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($act['date'])); ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-600 { font-weight: 600; }
    .activity-list li:last-child { margin-bottom: 0 !important; }
    .bounce-progress .progress-bar {
        transition: width 1s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
</style>

<?php require_once '../includes/footer.php'; ?>
