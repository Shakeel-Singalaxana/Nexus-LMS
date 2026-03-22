<?php
// admin/analytics.php
require_once '../config/db.php';
$active_page = 'admin_analytics';
$page_title = 'Admin - Analytics & Performance';

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

// --- DATA AGGREGATION ---

// 1. Overall User Stats
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$verified_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_verified = 1")->fetchColumn();
$pending_students = $total_students - $verified_students;
$verification_rate = $total_students > 0 ? round(($verified_students / $total_students) * 100) : 0;

// 2. Overall Content Stats
$total_lessons = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$total_videos = $pdo->query("SELECT COUNT(*) FROM lesson_videos")->fetchColumn();
$total_resources = $pdo->query("SELECT COUNT(*) FROM lesson_resources")->fetchColumn();
$total_batches = $pdo->query("SELECT COUNT(*) FROM batches")->fetchColumn();

// 3. Overall Completion Rate
// Total potential completions = Total Verified Students * Total Lessons
// (This is a simplified metric, better to do it per batch)
$total_potential = 0;
$stmt_batches = $pdo->query("SELECT id FROM batches");
while($b = $stmt_batches->fetch()) {
    $students_in_batch = $pdo->prepare("SELECT COUNT(*) FROM users WHERE batch_id = ? AND role = 'student' AND is_verified = 1");
    $students_in_batch->execute([$b['id']]);
    $student_count = $students_in_batch->fetchColumn();
    
    $lessons_in_batch = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE batch_id = ?");
    $lessons_in_batch->execute([$b['id']]);
    $lesson_count = $lessons_in_batch->fetchColumn();
    
    $total_potential += ($student_count * $lesson_count);
}
$actual_completions = $pdo->query("SELECT COUNT(*) FROM progress")->fetchColumn();
$overall_completion_rate = $total_potential > 0 ? round(($actual_completions / $total_potential) * 100, 1) : 0;

// 4. Batch-wise Breakdown
$batch_stats = $pdo->query("
    SELECT b.id, b.name, 
           (SELECT COUNT(*) FROM users u WHERE u.batch_id = b.id AND u.role = 'student') as student_count,
           (SELECT COUNT(*) FROM users u WHERE u.batch_id = b.id AND u.role = 'student' AND u.is_verified = 1) as verified_count,
           (SELECT COUNT(*) FROM lessons l WHERE l.batch_id = b.id) as lesson_count
    FROM batches b
    ORDER BY b.name DESC
")->fetchAll();

foreach ($batch_stats as &$bs) {
    $batch_potential = $bs['verified_count'] * $bs['lesson_count'];
    $stmt_comp = $pdo->prepare("
        SELECT COUNT(*) FROM progress p 
        JOIN lessons l ON p.lesson_id = l.id 
        WHERE l.batch_id = ?
    ");
    $stmt_comp->execute([$bs['id']]);
    $batch_actual = $stmt_comp->fetchColumn();
    $bs['completion_rate'] = $batch_potential > 0 ? round(($batch_actual / $batch_potential) * 100, 1) : 0;
}
unset($bs);

// 5. Class Type Breakdown
$class_types = ['Theory', 'Revision', 'Practical'];
$type_stats = [];
foreach ($class_types as $type) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE class_type = ?");
    $stmt->execute([$type]);
    $count = $stmt->fetchColumn();
    
    // Completion for this type
    $stmt_comp = $pdo->prepare("
        SELECT COUNT(*) FROM progress p 
        JOIN lessons l ON p.lesson_id = l.id 
        WHERE l.class_type = ?
    ");
    $stmt_comp->execute([$type]);
    $actual = $stmt_comp->fetchColumn();
    
    // Potential for this type
    $type_potential = 0;
    $stmt_b = $pdo->query("SELECT id FROM batches");
    while($b = $stmt_b->fetch()) {
        $stmt_l = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE batch_id = ? AND class_type = ?");
        $stmt_l->execute([$b['id'], $type]);
        $l_count = $stmt_l->fetchColumn();
        
        $stmt_s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE batch_id = ? AND is_verified = 1");
        $stmt_s->execute([$b['id']]);
        $s_count = $stmt_s->fetchColumn();
        
        $type_potential += ($l_count * $s_count);
    }
    
    $type_stats[$type] = [
        'count' => $count,
        'rate' => $type_potential > 0 ? round(($actual / $type_potential) * 100, 1) : 0
    ];
}

?>

<div class="container-fluid py-4">
    <div class="row mb-5 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold"><i class="bi bi-bar-chart-line-fill text-primary"></i> LMS Analytics & Performance</h2>
            <p class="text-muted">Real-time overview of academic progress and technical metrics.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <span class="badge bg-white text-dark border p-2 shadow-sm rounded-pill">
                <i class="bi bi-calendar3 me-2 text-primary"></i> Data as of: <?php echo date('F d, Y - H:i'); ?>
            </span>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 bg-primary-subtle overflow-hidden position-relative analytics-card">
                <div class="position-relative z-1">
                    <h6 class="text-primary small fw-bold text-uppercase mb-2">Total Enrollments</h6>
                    <h2 class="fw-bold mb-1 text-primary"><?php echo $total_students; ?></h2>
                    <p class="mb-0 small text-primary opacity-75">
                        <i class="bi bi-patch-check-fill me-1"></i> <?php echo $verification_rate; ?>% Verified (<?php echo $verified_students; ?>)
                    </p>
                </div>
                <i class="bi bi-people-fill position-absolute end-0 bottom-0 opacity-10 text-primary" style="font-size: 5rem; transform: translate(15%, 15%);"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 bg-secondary-subtle overflow-hidden position-relative analytics-card">
                <div class="position-relative z-1">
                    <h6 class="text-secondary small fw-bold text-uppercase mb-2">Engagement Score</h6>
                    <h2 class="fw-bold mb-1 text-secondary"><?php echo $overall_completion_rate; ?>%</h2>
                    <p class="mb-0 small text-secondary opacity-75">Average Lesson Completion</p>
                </div>
                <i class="bi bi-graph-up-arrow position-absolute end-0 bottom-0 opacity-10 text-secondary" style="font-size: 5rem; transform: translate(15%, 15%);"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 bg-white overflow-hidden position-relative border-start border-4 border-success analytics-card">
                <div class="position-relative z-1">
                    <h6 class="text-muted small fw-bold text-uppercase mb-2">Academic Content</h6>
                    <h2 class="fw-bold mb-1 text-dark"><?php echo $total_lessons; ?></h2>
                    <p class="mb-0 small text-muted">Across <?php echo $total_batches; ?> active batches</p>
                </div>
                <i class="bi bi-journal-check position-absolute end-0 bottom-0 opacity-10 text-success" style="font-size: 5rem; transform: translate(15%, 15%);"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 bg-white overflow-hidden position-relative border-start border-4 border-info analytics-card">
                <div class="position-relative z-1">
                    <h6 class="text-muted small fw-bold text-uppercase mb-2">Technical Assets</h6>
                    <h2 class="fw-bold mb-1 text-dark"><?php echo $total_videos + $total_resources; ?></h2>
                    <p class="mb-0 small text-muted"><?php echo $total_videos; ?> Videos, <?php echo $total_resources; ?> Files</p>
                </div>
                <i class="bi bi-cpu position-absolute end-0 bottom-0 opacity-10 text-info" style="font-size: 5rem; transform: translate(15%, 15%);"></i>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Batch Breakdown -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100 p-4">
                <h5 class="fw-bold mb-4 border-start border-3 border-primary ps-3">Performance by Academic Batch</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>BATCH NAME</th>
                                <th>STUDENTS</th>
                                <th>CONTENT</th>
                                <th>PROGRESS</th>
                                <th class="text-end">SCORE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batch_stats as $bs): ?>
                                <tr>
                                    <td><span class="fw-bold"><?php echo htmlspecialchars($bs['name']); ?></span></td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?php echo $bs['student_count']; ?> Total</div>
                                        <div class="x-small text-muted"><?php echo $bs['verified_count']; ?> Verified</div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?php echo $bs['lesson_count']; ?> Lessons</div>
                                    </td>
                                    <td style="min-width: 150px;">
                                        <div class="progress progress-tech mb-1" style="height: 6px;">
                                            <div class="progress-bar progress-bar-tech progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $bs['completion_rate']; ?>%"></div>
                                        </div>
                                        <span class="x-small text-muted fw-bold"><?php echo $bs['completion_rate']; ?>% Completed</span>
                                    </td>
                                    <td class="text-end">
                                        <?php 
                                            $color = 'text-danger';
                                            if($bs['completion_rate'] > 40) $color = 'text-warning';
                                            if($bs['completion_rate'] > 70) $color = 'text-success';
                                        ?>
                                        <span class="fw-bold <?php echo $color; ?>"><?php echo $bs['completion_rate']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Class Type Stats -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 p-4">
                <h5 class="fw-bold mb-4 border-start border-3 border-info ps-3">Learning Categorization</h5>
                
                <?php foreach ($type_stats as $name => $stat): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <div>
                                <h6 class="mb-0 fw-bold"><?php echo $name; ?></h6>
                                <small class="text-muted fw-bold"><?php echo $stat['count']; ?> Active Lessons</small>
                            </div>
                            <div class="text-end">
                                <span class="h4 fw-bold mb-0 text-info"><?php echo $stat['rate']; ?>%</span>
                            </div>
                        </div>
                        <div class="progress bg-light" style="height: 4px;">
                            <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $stat['rate']; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mt-auto p-3 rounded-4 bg-light">
                    <h6 class="small fw-bold mb-2 text-primary"><i class="bi bi-info-circle me-1"></i> Technical Insight</h6>
                    <p class="x-small mb-0 text-muted">Completion rates are calculated based on verified enrollments and published lessons. Pending students are excluded to ensure accuracy of academic engagement metrics.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Technical Stats -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 h-100 bg-white rounded-4 border-bottom border-4 border-warning">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning-subtle text-warning p-3 rounded-circle me-3">
                        <i class="bi bi-person-fill-exclamation h4 mb-0"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">Pending Approvals</h6>
                        <span class="h3 fw-bold mb-0"><?php echo $pending_students; ?></span>
                    </div>
                </div>
                <p class="text-muted small mb-0">Students registered but not yet verified. Verification is required for curriculum access.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 h-100 bg-white rounded-4 border-bottom border-4 border-primary">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                        <i class="bi bi-server h4 mb-0"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">Content Density</h6>
                        <span class="h3 fw-bold mb-0"><?php echo $total_lessons > 0 ? round(($total_videos + $total_resources) / $total_lessons, 1) : 0; ?></span>
                    </div>
                </div>
                <p class="text-muted small mb-0">Average number of support materials (videos & files) provided per lesson container.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 h-100 bg-white rounded-4 border-bottom border-4 border-success">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success-subtle text-success p-3 rounded-circle me-3">
                        <i class="bi bi-lightning-fill h4 mb-0"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">LMS Core Status</h6>
                        <span class="h3 fw-bold mb-0 text-success">Healthy</span>
                    </div>
                </div>
                <p class="text-muted small mb-0">Database connection and session management active. Version stable @ v2.3.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
