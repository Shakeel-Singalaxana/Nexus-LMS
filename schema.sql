-- LMS Database Schema
-- Optimized for MySQL / InfinityFree

CREATE DATABASE IF NOT EXISTS lms_db;
USE lms_db;

-- Batches table
CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') DEFAULT 'student',
    is_verified BOOLEAN DEFAULT FALSE,
    is_first_login BOOLEAN DEFAULT TRUE,
    batch_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    theme VARCHAR(10) DEFAULT 'light',
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL
);

-- Lessons table (Refined)
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    class_type ENUM('Theory', 'Revision', 'Practical') NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
);

-- Multiple YouTube Videos per Lesson
CREATE TABLE IF NOT EXISTS lesson_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    video_url VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Multiple Files / Resources per Lesson
CREATE TABLE IF NOT EXISTS lesson_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    resource_type ENUM('file', 'link') DEFAULT 'file',
    file_path TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Progress tracking table
CREATE TABLE IF NOT EXISTS progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, lesson_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Video-level progress tracking
CREATE TABLE IF NOT EXISTS video_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, video_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES lesson_videos(id) ON DELETE CASCADE
);

-- Announcements / Banner messages
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Initial Data
INSERT IGNORE INTO batches (name) VALUES ('2026AL'), ('2027AL'), ('2028AL');

-- Example announcement
INSERT INTO announcements (message, is_active) VALUES ('Welcome to the LMS! Please verify your account with the admin.', 1);
