<?php
// student/lesson_view.php
require_once '../config/db.php';

// Start session manually if not already started to check login BEFORE header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Handle video completion (BEFORE HEADER) - Keep for fallback
if (isset($_POST['mark_video_done'])) {
    $video_id = (int)$_POST['video_id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO video_progress (user_id, video_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $video_id]);
    
    // Check if all videos in this lesson are completed
    $stmt = $pdo->prepare("SELECT id FROM lesson_videos WHERE lesson_id = ?");
    $stmt->execute([$lesson_id]);
    $all_vids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($all_vids)) {
        $stmt = $pdo->prepare("SELECT video_id FROM video_progress WHERE user_id = ? AND video_id IN (" . implode(',', array_fill(0, count($all_vids), '?')) . ")");
        $stmt->execute(array_merge([$user_id], $all_vids));
        $watched_vids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($watched_vids) === count($all_vids)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO progress (user_id, lesson_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $lesson_id]);
        }
    }

    header("Location: lesson_view.php?id=$lesson_id");
    exit;
}

// Handle lesson completion (BEFORE HEADER) - Keep for fallback
if (isset($_POST['mark_done'])) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO progress (user_id, lesson_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $lesson_id]);
    header("Location: lesson_view.php?id=$lesson_id");
    exit;
}

// Now include header and set titles
$active_page = 'student_dashboard';
$page_title = 'Lesson View';
require_once '../includes/header.php';

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

// Fetch Completed Videos for this user
$stmt = $pdo->prepare("SELECT video_id FROM video_progress WHERE user_id = ?");
$stmt->execute([$user_id]);
$completed_videos = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    /* Security measures for video */
    .video-container {
        position: relative;
        overflow: hidden;
        user-select: none;
        -webkit-user-drag: none;
        background: #000;
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
    }
    
    /* Full overlay to block all YouTube native interactions */
    .video-full-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10;
        cursor: pointer;
        background: rgba(0,0,0,0.01); /* Nearly invisible but catches all events */
    }

    /* Custom Controls */
    .custom-player-controls {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        z-index: 20;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        padding: 50px 20px 15px;
        opacity: 0;
        transition: opacity 0.3s ease;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .video-container:hover .custom-player-controls,
    .video-container.playing:hover .custom-player-controls {
        opacity: 1;
    }
    
    .control-btn {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s;
    }
    .control-btn:hover {
        color: #3b82f6;
        transform: scale(1.1);
    }
    
    .center-play-btn {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 15;
        background: rgba(59, 130, 246, 0.9);
        color: white;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
    }
    .video-container.playing .center-play-btn {
        opacity: 0;
        pointer-events: none;
    }
    .video-container:not(.playing) .custom-player-controls {
        opacity: 1; /* Always show controls when paused */
    }

    .progress-container {
        flex-grow: 1;
        height: 4px;
        background: rgba(255,255,255,0.2);
        position: relative;
        cursor: pointer;
        border-radius: 2px;
    }
    .progress-bar {
        height: 100%;
        background: #3b82f6;
        width: 0%;
        border-radius: 2px;
    }
    
    .speed-badge {
        font-size: 0.75rem;
        font-weight: bold;
        background: rgba(255,255,255,0.1);
        padding: 2px 8px;
        border-radius: 4px;
        cursor: pointer;
    }
    .speed-badge:hover {
        background: rgba(255,255,255,0.2);
    }

    .video-wrapper {
        border-radius: 1rem;
        overflow: hidden;
        z-index: 1;
    }

    /* Prevent text selection and dragging */
    .lesson-content-area {
        user-select: none;
        -webkit-user-select: none;
    }

    /* Style for the YouTube iframe (pushed back) */
    .yt-player {
        pointer-events: none; /* Secondary layer of protection */
    }
</style>

<div class="container py-4 lesson-content-area">
    <!-- Header -->
    <div class="row mb-5 align-items-center card border-0 p-4 rounded-4 shadow-sm mx-0 flex-row">
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
        <div class="col-md-4 text-md-end mt-3 mt-md-0" id="lesson_status_container">
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
                <?php 
                    $is_watched = in_array($video['id'], $completed_videos);
                    $yt_id = getYouTubeID($video['video_url']);
                ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm overflow-hidden rounded-4 <?php echo $is_watched ? 'border-success border-top border-4' : ''; ?>" id="video_card_<?php echo $video['id']; ?>">
                        <div class="card-header bg-secondary-subtle border-0 py-3 px-4 d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-dark"><i class="bi bi-play-btn-fill text-danger me-2"></i> PART <?php echo $index + 1; ?></span>
                            <div class="d-flex align-items-center gap-3">
                                <small class="text-muted"><i class="bi bi-shield-lock-fill me-1"></i></small>
                                <div id="status_badge_<?php echo $video['id']; ?>">
                                    <?php if ($is_watched): ?>
                                        <span class="badge bg-success-subtle text-success py-1 px-3 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i> Completed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning py-1 px-3 rounded-pill">
                                            <i class="bi bi-clock-fill me-1"></i> Not Watched
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="video-container" id="video_container_<?php echo $video['id']; ?>">
                            <!-- Full Secure Overlay -->
                            <div class="video-full-overlay" onclick="togglePlay(<?php echo $video['id']; ?>)" oncontextmenu="return false;"></div>
                            
                            <!-- Center Play Button -->
                            <div class="center-play-btn" id="center_play_<?php echo $video['id']; ?>" onclick="togglePlay(<?php echo $video['id']; ?>)">
                                <i class="bi bi-play-fill"></i>
                            </div>

                            <!-- Custom Controls Bar -->
                            <div class="custom-player-controls" id="controls_<?php echo $video['id']; ?>">
                                <button class="control-btn" onclick="togglePlay(<?php echo $video['id']; ?>)">
                                    <i class="bi bi-play-fill" id="play_icon_<?php echo $video['id']; ?>"></i>
                                </button>
                                
                                <div class="progress-container" onclick="seek(event, <?php echo $video['id']; ?>)">
                                    <div class="progress-bar" id="progress_bar_<?php echo $video['id']; ?>"></div>
                                </div>
                                
                                <button class="control-btn" onclick="toggleMute(<?php echo $video['id']; ?>)">
                                    <i class="bi bi-volume-up-fill" id="volume_icon_<?php echo $video['id']; ?>"></i>
                                </button>
                                
                                <div class="speed-badge" onclick="cycleSpeed(<?php echo $video['id']; ?>)" id="speed_<?php echo $video['id']; ?>">1x</div>
                                
                                <button class="control-btn" onclick="toggleFullscreen(<?php echo $video['id']; ?>)">
                                    <i class="bi bi-fullscreen"></i>
                                </button>
                            </div>
                            
                            <div class="ratio ratio-16x9 bg-dark video-wrapper">
                                <?php if ($yt_id): ?>
                                    <div id="player_<?php echo $video['id']; ?>" class="yt-player" 
                                         data-video-id="<?php echo $yt_id; ?>" 
                                         data-db-id="<?php echo $video['id']; ?>"
                                         data-completed="<?php echo $is_watched ? '1' : '0'; ?>"></div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center text-white-50">Invalid Video Link</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer py-3 px-4 text-end border-0" id="video_footer_<?php echo $video['id']; ?>">
                            <?php if ($is_watched): ?>
                                <button class="btn btn-sm btn-success rounded-pill px-4 disabled">
                                    <i class="bi bi-check-circle-fill me-1"></i> Completed
                                </button>
                            <?php else: ?>
                                <form method="POST" class="d-inline" id="form_video_<?php echo $video['id']; ?>">
                                    <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                    <button type="submit" name="mark_video_done" id="btn_video_<?php echo $video['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                                        <i class="bi bi-check2 me-1"></i> Mark as Watched
                                    </button>
                                </form>
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
                    <div class="card border-0 shadow-sm p-3 hover-up transition-all rounded-4">
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
                                    <?php 
                                        $url = $res['file_path'];
                                        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                                            $url = "http://" . $url;
                                        }
                                    ?>
                                    <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="btn btn-sm btn-primary px-3 rounded-pill shadow-sm">
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
            <div class="p-5 card border-0 rounded-4 shadow-sm d-inline-block">
                <i class="bi bi-inbox text-muted h1 opacity-25 d-block mb-3"></i>
                <p class="text-muted">This lesson container is currently empty.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- YouTube IFrame API -->
<script src="https://www.youtube.com/iframe_api"></script>

<script>
    let players = {};
    const lessonId = <?php echo $lesson_id; ?>;

    function onYouTubeIframeAPIReady() {
        document.querySelectorAll('.yt-player').forEach(el => {
            const playerId = el.id;
            const videoId = el.dataset.videoId;
            const dbId = el.dataset.dbId;
            const isCompleted = el.dataset.completed === '1';

            players[dbId] = new YT.Player(playerId, {
                height: '100%',
                width: '100%',
                videoId: videoId,
                playerVars: {
                    'rel': 0,
                    'modestbranding': 1,
                    'controls': 0, // Disable native controls
                    'disablekb': 1,
                    'iv_load_policy': 3,
                    'fs': 0, // Disable native fullscreen
                    'showinfo': 0
                },
                events: {
                    'onReady': (event) => onPlayerReady(event, dbId),
                    'onStateChange': (event) => onPlayerStateChange(event, dbId)
                }
            });
        });
    }

    function onPlayerReady(event, dbId) {
        // Initial setup if needed
    }

    function onPlayerStateChange(event, dbId) {
        const container = document.getElementById('video_container_' + dbId);
        const playIcon = document.getElementById('play_icon_' + dbId);
        
        if (event.data === YT.PlayerState.PLAYING) {
            container.classList.add('playing');
            playIcon.className = "bi bi-pause-fill";
            startTracking(dbId);
        } else {
            container.classList.remove('playing');
            playIcon.className = "bi bi-play-fill";
        }
    }

    function startTracking(dbId) {
        const player = players[dbId];
        const progressBar = document.getElementById('progress_bar_' + dbId);
        const cardEl = document.getElementById('video_card_' + dbId);
        
        const interval = setInterval(() => {
            if (player.getPlayerState() !== YT.PlayerState.PLAYING) {
                clearInterval(interval);
                return;
            }

            const duration = player.getDuration();
            const currentTime = player.getCurrentTime();
            
            // Update Progress Bar
            if (duration > 0) {
                const percent = (currentTime / duration) * 100;
                progressBar.style.width = percent + '%';
            }

            // Check if watched until the last 3 seconds
            if (duration > 0 && (duration - currentTime) <= 3) {
                if (cardEl.dataset.completedUI !== 'true') {
                    markAsCompleted(dbId);
                }
            }
        }, 500);
    }

    function togglePlay(dbId) {
        const player = players[dbId];
        const state = player.getPlayerState();
        if (state === YT.PlayerState.PLAYING) {
            player.pauseVideo();
        } else {
            player.playVideo();
        }
    }

    function toggleMute(dbId) {
        const player = players[dbId];
        const muteIcon = document.getElementById('volume_icon_' + dbId);
        if (player.isMuted()) {
            player.unMute();
            muteIcon.className = "bi bi-volume-up-fill";
        } else {
            player.mute();
            muteIcon.className = "bi bi-volume-mute-fill";
        }
    }

    function cycleSpeed(dbId) {
        const player = players[dbId];
        const speedEl = document.getElementById('speed_' + dbId);
        const currentSpeed = player.getPlaybackRate();
        const speeds = [0.5, 1, 1.5, 2];
        let nextIndex = speeds.indexOf(currentSpeed) + 1;
        if (nextIndex >= speeds.length) nextIndex = 0;
        
        player.setPlaybackRate(speeds[nextIndex]);
        speedEl.innerText = speeds[nextIndex] + 'x';
    }

    function seek(event, dbId) {
        const player = players[dbId];
        const progressBarCont = event.currentTarget;
        const rect = progressBarCont.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const width = rect.width;
        const percent = x / width;
        const duration = player.getDuration();
        
        // Optional: Block seeking forward if you want more security
        // if (percent * duration > player.getCurrentTime()) return;

        player.seekTo(percent * duration);
    }

    function toggleFullscreen(dbId) {
        const container = document.getElementById('video_container_' + dbId);
        if (!document.fullscreenElement) {
            container.requestFullscreen().catch(err => {
                console.error(`Error attempting to enable full-screen mode: ${err.message}`);
            });
        } else {
            document.exitFullscreen();
        }
    }

    function markAsCompleted(dbId) {
        const cardEl = document.getElementById('video_card_' + dbId);
        if (cardEl.dataset.completedUI === 'true') return;

        fetch('api_update_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_video_done',
                video_id: dbId,
                lesson_id: lessonId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update Video UI
                cardEl.dataset.completedUI = 'true';
                cardEl.classList.add('border-success', 'border-top', 'border-4');
                
                const statusBadge = document.getElementById('status_badge_' + dbId);
                statusBadge.innerHTML = `<span class="badge bg-success-subtle text-success py-1 px-3 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Completed</span>`;
                
                const footer = document.getElementById('video_footer_' + dbId);
                if (footer) {
                    footer.innerHTML = `<button class="btn btn-sm btn-success rounded-pill px-4 disabled"><i class="bi bi-check-circle-fill me-1"></i> Completed</button>`;
                }

                // If all videos completed, update Lesson UI
                if (data.all_completed) {
                    const lessonContainer = document.getElementById('lesson_status_container');
                    if (lessonContainer) {
                        lessonContainer.innerHTML = `
                            <div class="btn btn-success rounded-pill px-4 py-2 disabled shadow-none">
                                <i class="bi bi-check-circle-fill me-2"></i> Lesson Completed
                            </div>
                        `;
                    }
                }
            }
        })
        .catch(err => console.error('Error marking progress:', err));
    }

    // Double disable right click on containers
    document.addEventListener('contextmenu', function(e) {
        if (e.target.closest('.video-container')) {
            e.preventDefault();
        }
    }, false);
</script>

<?php require_once '../includes/footer.php'; ?>
