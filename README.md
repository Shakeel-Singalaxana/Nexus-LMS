# 🛠️ EngTech Nexus LMS 
**A Comprehensive Management System for Engineering Technology Batches**

EngTech Nexus is a bespoke LMS platform tailored for managing student progress, lesson delivery, and administrative oversight for A/L Engineering Technology classes.

## 🚀 Core Features
* **Role-Based Access:** Distinct student and admin dashboards with secure email authentication.
* **Batch-Specific Environments:** Customized content delivery for 2026AL, 2027AL, and 2028AL batches.
* **Integrated Content Delivery:** Native support for YouTube embeds, technical PDFs, and high-resolution diagrams (PNG).
* **Syllabus Progress Tracking:** Interactive "Mark as Completed" functionality with visual percentage bars for students.
* **Real-time Search:** Lightning-fast, client-side filtering to find specific lessons or topics instantly.
* **Admin Broadcasts:** Global announcement banner for urgent class updates and schedule changes.
* **Mobile-First Design:** Fully responsive UI optimized for low-bandwidth mobile networks and tablets.

## 📂 System Architecture
* **Frontend:** HTML5, CSS3 (Bootstrap 5), Vanilla JavaScript.
* **Backend:** PHP 7.4+ (PDO for secure database transactions).
* **Database:** MySQL (Structured for high scalability).
* **Hosting:** Compatible with InfinityFree/LAMP stacks.

## 🛠️ Installation & Setup
1.  **Database Configuration:**
    * Create a MySQL database in your hosting panel.
    * Import the provided `schema.sql` via phpMyAdmin.
2.  **Configuration:**
    * Edit `/config/db.php` with your database host, name, and credentials.
    * Edit `/config/admin_cfg.php` to set your master admin password (this file must be secured at the root directory).
3.  **Deployment:**
    * Upload all files to the `htdocs` or `public_html` folder via FTP/File Manager.
4.  **Admin Initialization:**
    * Register your first account and manually change the `role` to 'admin' in the database `users` table to gain full access.

## 🔒 Security Measures
* **Gatekeeper Verification:** New accounts are restricted to "Advertisement Only" mode until manually verified by the Admin.
* **Root-Level Admin Security:** The master admin password is non-database dependent, requiring direct file-system access to change.
* **Password Enforcement:** Students are forced to update their default password upon their first successful login.

---
*Developed for the Engineering Technology community.*
