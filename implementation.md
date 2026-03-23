# Engineering Technology LMS - Implementation Plan

## 1. Database Schema (MySQL)
- **users**: `id`, `name`, `email`, `password`, `role` (admin/student), `batch_year`, `is_verified` (0/1), `enrolled_classes` (JSON/String).
- **batches**: `id`, `year` (e.g., 2026AL).
- **lessons**: `id`, `batch_id`, `class_type` (Theory/Revision/Practical), `title`, `description`, `yt_link`, `file_path`, `created_at`.
- **settings**: `id`, `config_key`, `config_value` (Used for dynamic batch management).

## 2. File Structure
- `/index.php` - Landing page with Login/Sign-up.
- `/register.php` - Account creation logic (default state: unverified).
- `/dashboard.php` - Custom view based on user session & verification status.
- `/admin/` - Directory for management tools (Admin login required).
    - `manage_users.php` - Verify/Reset passwords/Delete users.
    - `manage_content.php` - CRUD for lessons (Add YouTube/PDF/Images).
    - `manage_batches.php` - Add/Remove AL batches.
- `/config/db.php` - Database connection.
- `/config/admin_gate.php` - Hardcoded admin password validation (per requirements).
- `/assets/` - CSS/JS for mobile-responsive UI.

## 3. Key Functionality
- **Access Control:** Verified students see their enrolled batch content; unverified users see class advertisements only.
- **Content Delivery:** Responsive video embeds for YouTube and secure links for PDF/PNG materials.
- **Admin Security:** Admin password is set in `config/admin_gate.php` for root-level security.

# Engineering Technology LMS - Enterprise Edition

## 1. Database Schema Additions
- **progress**: `id`, `user_id`, `lesson_id`, `completed_at` (Track student syllabus coverage).
- **announcements**: `id`, `message`, `priority` (High/Normal), `created_at` (Global alerts).
- **lessons**: (Enhanced) `tags` column for advanced filtering.

## 2. Refinement Logic
- **Syllabus Progress:** Students can "Check" a lesson. The dashboard calculates a % completion bar for Theory, Revision, and Practical.
- **Search Engine:** A client-side JavaScript filter to instantly find lessons by name or tag without reloading.
- **Admin Alert System:** A "Broadcast" tool in the Admin panel to push urgent class updates to the top of student dashboards.

## 3. Deployment Constraints (InfinityFree)
- **Upload Limit:** Note that InfinityFree has a 10MB upload limit. Recommend using Google Drive or Mega links for very large PDF files, while small PNGs/PDFs stay local.