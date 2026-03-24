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

// Handle video completion has been moved to API
// Handle lesson completion has been moved to API

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
        height: 6px;
        background: rgba(255,255,255,0.3);
        position: relative;
        cursor: pointer;
        border-radius: 3px;
        transition: height 0.2s;
    }
    .progress-container:hover {
        height: 8px;
    }
    .progress-bar {
        height: 100%;
        background: #3b82f6;
        width: 0%;
        border-radius: 3px;
    }
    
    .speed-badge {
        font-size: 0.85rem;
        font-weight: bold;
        background: rgba(255,255,255,0.2);
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .speed-badge:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .volume-container {
        position: relative;
        display: flex;
        align-items: center;
    }
    .volume-slider-wrapper {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.8);
        padding: 10px;
        border-radius: 5px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s;
        margin-bottom: 10px;
    }
    .volume-container:hover .volume-slider-wrapper {
        opacity: 1;
        visibility: visible;
    }
    .volume-slider {
        -webkit-appearance: slider-vertical;
        width: 8px;
        height: 80px;
        outline: none;
        cursor: pointer;
    }
    
    .quality-container {
        position: relative;
        display: flex;
        align-items: center;
    }
    .quality-menu {
        position: absolute;
        bottom: 100%;
        right: 0;
        background: rgba(0,0,0,0.8);
        border-radius: 5px;
        padding: 5px 0;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        min-width: 80px;
        margin-bottom: 10px;
    }
    .quality-container:hover .quality-menu {
        opacity: 1;
        visibility: visible;
    }
    .quality-btn {
        background: none;
        border: none;
        color: white;
        padding: 5px 15px;
        font-size: 0.85rem;
        cursor: pointer;
        text-align: left;
    }
    .quality-btn:hover {
        background: #3b82f6;
    }
    .quality-btn.active {
        color: #3b82f6;
        font-weight: bold;
        background: rgba(255,255,255,0.1);
    }
    .quality-toggle {
        font-size: 1.2rem;
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s;
    }
    .quality-toggle:hover {
        color: #3b82f6;
        transform: scale(1.1);
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
                <div class="btn btn-warning rounded-pill px-4 py-2 disabled shadow-none text-dark">
                    <i class="bi bi-clock-fill me-2"></i> Lesson Incomplete
                </div>
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
                                            <i class="bi bi-clock-fill me-1"></i> Incomplete
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
                                <button class="control-btn" onclick="skip(<?php echo $video['id']; ?>, -10)" title="Rewind 10s">
                                    <i class="bi bi-rewind-fill"></i>
                                </button>
                                <button class="control-btn" onclick="togglePlay(<?php echo $video['id']; ?>)" title="Play/Pause">
                                    <i class="bi bi-play-fill" id="play_icon_<?php echo $video['id']; ?>"></i>
                                </button>
                                <button class="control-btn" onclick="skip(<?php echo $video['id']; ?>, 10)" title="Forward 10s">
                                    <i class="bi bi-fast-forward-fill"></i>
                                </button>
                                
                                <div class="progress-container" onclick="seek(event, <?php echo $video['id']; ?>)">
                                    <div class="progress-bar" id="progress_bar_<?php echo $video['id']; ?>"></div>
                                </div>
                                
                                <div class="volume-container">
                                    <button class="control-btn" onclick="toggleMute(<?php echo $video['id']; ?>)" title="Mute/Unmute">
                                        <i class="bi bi-volume-up-fill" id="volume_icon_<?php echo $video['id']; ?>"></i>
                                    </button>
                                    <div class="volume-slider-wrapper">
                                        <input type="range" class="volume-slider" min="0" max="100" value="100" id="volume_slider_<?php echo $video['id']; ?>" oninput="changeVolume(<?php echo $video['id']; ?>, this.value)">
                                    </div>
                                </div>
                                
                                <div class="speed-badge" onclick="cycleSpeed(<?php echo $video['id']; ?>)" id="speed_<?php echo $video['id']; ?>" title="Playback Speed">1x</div>
                                
                                <div class="quality-container">
                                    <button class="quality-toggle" title="Quality Settings"><i class="bi bi-gear-fill"></i></button>
                                    <div class="quality-menu" id="quality_menu_<?php echo $video['id']; ?>">
                                        <button class="quality-btn active" onclick="changeQuality(<?php echo $video['id']; ?>, 'default', this)">Auto</button>
                                        <button class="quality-btn" onclick="changeQuality(<?php echo $video['id']; ?>, 'hd1080', this)">1080p</button>
                                        <button class="quality-btn" onclick="changeQuality(<?php echo $video['id']; ?>, 'hd720', this)">720p</button>
                                        <button class="quality-btn" onclick="changeQuality(<?php echo $video['id']; ?>, 'large', this)">480p</button>
                                        <button class="quality-btn" onclick="changeQuality(<?php echo $video['id']; ?>, 'medium', this)">360p</button>
                                    </div>
                                </div>
                                
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
                                <span class="badge bg-success py-2 px-3 rounded-pill text-white shadow-sm">
                                    <i class="bi bi-check-circle-fill me-1"></i> Completed
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning py-2 px-3 rounded-pill text-dark shadow-sm">
                                    <i class="bi bi-clock-fill me-1"></i> Incomplete
                                </span>
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
    let maxWatchedTime = {};
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
        maxWatchedTime[dbId] = 0;
        // Optionally, check if marked completed, then allow full seeking
        const el = document.getElementById('player_' + dbId);
        if (el.dataset.completed === '1') {
            maxWatchedTime[dbId] = event.target.getDuration() || 999999;
        }
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
        
        if (typeof maxWatchedTime[dbId] === 'undefined') {
            maxWatchedTime[dbId] = 0;
        }

        const interval = setInterval(() => {
            if (player.getPlayerState() !== YT.PlayerState.PLAYING) {
                clearInterval(interval);
                return;
            }

            const duration = player.getDuration();
            const currentTime = player.getCurrentTime();
            
            // Update max watched time
            // Allow larger jump tolerance for clicking the skip 10s button
            if (currentTime > maxWatchedTime[dbId] && (currentTime - maxWatchedTime[dbId] <= 12)) {
                maxWatchedTime[dbId] = currentTime;
            } else if (currentTime > maxWatchedTime[dbId]) {
                // If they bypassed somehow (e.g. hack/drag), revert them
                player.seekTo(maxWatchedTime[dbId]);
            }

            // Update Progress Bar
            if (duration > 0) {
                let displayPercent = (currentTime / duration) * 100;
                // Allow progress bar to also reflect max watched when dragging
                progressBar.style.width = displayPercent + '%';
            }

            // Sync volume slider back
            const volSlider = document.getElementById('volume_slider_' + dbId);
            if (volSlider) {
                volSlider.value = player.getVolume();
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
        const popSlider = document.getElementById('volume_slider_' + dbId);
        
        if (player.isMuted()) {
            player.unMute();
            muteIcon.className = "bi bi-volume-up-fill";
            if(popSlider) popSlider.value = player.getVolume();
        } else {
            player.mute();
            muteIcon.className = "bi bi-volume-mute-fill";
            if(popSlider) popSlider.value = 0;
        }
    }

    function changeVolume(dbId, value) {
        const player = players[dbId];
        if (player.isMuted() && value > 0) {
            player.unMute();
        }
        player.setVolume(value);
        const icon = document.getElementById('volume_icon_' + dbId);
        if (value == 0) icon.className = "bi bi-volume-mute-fill";
        else if (value < 50) icon.className = "bi bi-volume-down-fill";
        else icon.className = "bi bi-volume-up-fill";
    }

    function changeQuality(dbId, quality, btnEl) {
        const player = players[dbId];
        const currentTime = player.getCurrentTime();
        const videoId = player.getVideoData().video_id;
        
        // Highlight active quality button
        if (btnEl) {
            const menu = document.getElementById('quality_menu_' + dbId);
            if (menu) {
                menu.querySelectorAll('.quality-btn').forEach(btn => btn.classList.remove('active'));
            }
            btnEl.classList.add('active');
        }

        // setPlaybackQuality is deprecated, so we force-reload the video at the current timestamp with the specified quality
        player.loadVideoById({
            videoId: videoId,
            startSeconds: currentTime,
            suggestedQuality: quality
        });
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
        
        const seekTime = percent * duration;
        const maxTime = maxWatchedTime[dbId];
        
        // Prevent seeking forward beyond max watched time
        if (seekTime > maxTime) {
            player.seekTo(maxTime);
        } else {
            player.seekTo(seekTime);
        }
    }

    function skip(dbId, seconds) {
        const player = players[dbId];
        const currentTime = player.getCurrentTime();
        let newTime = currentTime + seconds;
        const duration = player.getDuration();
        
        if (newTime < 0) newTime = 0;
        if (newTime > duration) newTime = duration;
        
        // Let them skip forward 10s smoothly, maxWatchedTime will adjust in tracking
        if (seconds > 0) {
            maxWatchedTime[dbId] = newTime;
        }
        
        player.seekTo(newTime);
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
                    footer.innerHTML = `<span class="badge bg-success py-2 px-3 rounded-pill text-white shadow-sm"><i class="bi bi-check-circle-fill me-1"></i> Completed</span>`;
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
