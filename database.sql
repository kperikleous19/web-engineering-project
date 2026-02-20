-- --------------------------------------------------------
-- Τεχνολογικό Πανεπιστήμιο Κύπρου (ΤΕΠΑΚ)
-- Σύστημα Διαχείρισης Ειδικών Επιστημόνων
-- Πλήρες MySQL Schema με όλους τους πίνακες
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS tepak_ee_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE tepak_ee_db;

-- --------------------------------------------------------
-- ΡΟΛΟΙ ΧΡΗΣΤΩΝ
-- --------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (role_name, description) VALUES
('Admin', 'Πλήρης διαχείριση συστήματος'),
('HR', 'Υπεύθυνος Ανθρώπινου Δυναμικού'),
('Evaluator', 'Αξιολογητής αιτήσεων'),
('Candidate', 'Υποψήφιος Ειδικός Επιστήμων'),
('SpecialScientist', 'Ειδικός Επιστήμων (προσληφθείς)');

-- --------------------------------------------------------
-- ΧΡΗΣΤΕΣ
-- --------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    role_id INT NOT NULL,
    moodle_user_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_email (email),
    INDEX idx_role (role_id),
    INDEX idx_moodle (moodle_user_id)
);

-- Default users (password: 123456)
INSERT INTO users (username, email, password_hash, first_name, last_name, role_id) VALUES
('admin', 'admin@cut.ac.cy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Διαχειριστής', 'Συστήματος', 1),
('hr_manager', 'hr@cut.ac.cy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ελένη', 'Χριστοφόρου', 2),
('evaluator1', 'a.charalambous@cut.ac.cy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Αντώνης', 'Χαραλάμπους', 3),
('candidate1', 'm.papadopoulou@edu.cut.ac.cy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Μαρία', 'Παπαδοπούλου', 4),
('special1', 'p.petrou@cut.ac.cy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Πέτρος', 'Πέτρου', 5);

-- --------------------------------------------------------
-- ΣΧΟΛΕΣ ΤΕΠΑΚ (ΕΠΙΣΗΜΕΣ 8 ΣΧΟΛΕΣ)
-- --------------------------------------------------------
CREATE TABLE schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_el VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    location VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO schools (name_el, name_en, location) VALUES
('Σχολή Επιστημών Υγείας', 'School of Health Sciences', 'Λεμεσός'),
('Σχολή Μηχανικής και Τεχνολογίας', 'School of Engineering and Technology', 'Λεμεσός'),
('Σχολή Γεωτεχνικών Επιστημών και Διαχείρισης Περιβάλλοντος', 'School of Geotechnical Sciences', 'Λεμεσός'),
('Σχολή Διοίκησης και Οικονομίας', 'School of Management and Economics', 'Λεμεσός'),
('Σχολή Καλών και Εφαρμοσμένων Τεχνών', 'School of Fine and Applied Arts', 'Λεμεσός'),
('Σχολή Επικοινωνίας και Μέσων Ενημέρωσης', 'School of Communication and Media', 'Λεμεσός'),
('Σχολή Διοίκησης Τουρισμού, Φιλοξενίας και Επιχειρηματικότητας', 'School of Tourism Management', 'Πάφος'),
('Σχολή Θαλάσσιων Επιστημών, Τεχνολογίας και Βιώσιμης Ανάπτυξης', 'School of Marine Sciences', 'Λάρνακα');

-- --------------------------------------------------------
-- ΤΜΗΜΑΤΑ (ΕΠΙΣΗΜΑ ΤΜΗΜΑΤΑ ΤΕΠΑΚ)
-- --------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    name_el VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    code VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    INDEX idx_school (school_id)
);

INSERT INTO departments (school_id, name_el, code) VALUES
(1, 'Τμήμα Νοσηλευτικής', 'NUR'),
(1, 'Τμήμα Επιστημών Αποκατάστασης', 'REH'),
(2, 'Τμήμα Ηλεκτρολόγων Μηχανικών και Μηχανικών Ηλεκτρονικών Υπολογιστών και Πληροφορικής', 'ECE'),
(2, 'Τμήμα Μηχανολόγων Μηχανικών', 'MEC'),
(2, 'Τμήμα Πολιτικών Μηχανικών και Μηχανικών Γεωπληροφορικής', 'CIV'),
(3, 'Τμήμα Γεωπονικών Επιστημών, Βιοτεχνολογίας και Επιστήμης Τροφίμων', 'AGR'),
(3, 'Τμήμα Χημικών Μηχανικών', 'CHE'),
(4, 'Τμήμα Χρηματοποικονομικής, Λογιστικής και Διοικητικής Επιστήμης', 'FIN'),
(4, 'Τμήμα Ναυτιλιακών', 'MAR'),
(5, 'Τμήμα Πολυμέσων και Γραφικών Τεχνών', 'MME'),
(5, 'Τμήμα Καλών Τεχνών', 'FAR'),
(6, 'Τμήμα Επικοινωνίας και Σπουδών Διαδικτύου', 'COM'),
(6, 'Τμήμα Επικοινωνίας και Μάρκετιγκ', 'MKT'),
(7, 'Τμήμα Διοίκησης Τουρισμού και Φιλοξενίας', 'TOU'),
(7, 'Τμήμα Διοίκησης, Επιχειρηματικότητας και Ψηφιακού Επιχειρείν', 'ENT'),
(8, 'Marine Biology', 'MARB'),
(8, 'Marine Technology', 'MART'),
(8, 'Sustainable Development', 'SUS');

-- --------------------------------------------------------
-- ΜΑΘΗΜΑΤΑ
-- --------------------------------------------------------
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name_el VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    credits INT DEFAULT 6,
    semester VARCHAR(50),
    moodle_course_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    INDEX idx_department (department_id)
);

INSERT INTO courses (department_id, code, name_el, credits, semester) VALUES
(3, 'CS521', 'Τεχνητή Νοημοσύνη', 6, 'Χειμερινό'),
(3, 'CS432', 'Αλγόριθμοι', 6, 'Εαρινό'),
(2, 'OT101', 'Εργοθεραπεία', 5, 'Χειμερινό'),
(2, 'PT202', 'Φυσικοθεραπεία', 5, 'Εαρινό'),
(14, 'HM301', 'Hotel Management', 6, 'Χειμερινό'),
(8, 'DS401', 'Data Science in Finance', 6, 'Εαρινό');

-- --------------------------------------------------------
-- ΠΡΟΚΗΡΥΞΕΙΣ
-- --------------------------------------------------------
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title_el VARCHAR(255) NOT NULL,
    title_en VARCHAR(255),
    department_id INT NOT NULL,
    course_id INT NOT NULL,
    positions_count INT DEFAULT 1,
    application_start DATE NOT NULL,
    application_end DATE NOT NULL,
    status ENUM('draft', 'published', 'closed', 'cancelled') DEFAULT 'draft',
    description TEXT,
    requirements TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_dates (application_start, application_end)
);

INSERT INTO announcements (title_el, department_id, course_id, positions_count, application_start, application_end, status, created_by) VALUES
('Ειδικός Επιστήμων - Τεχνητή Νοημοσύνη', 3, 1, 2, '2026-02-01', '2026-04-30', 'published', 1),
('Ειδικός Επιστήμων - Εργοθεραπεία', 2, 3, 1, '2026-02-15', '2026-05-15', 'published', 1),
('Ειδικός Επιστήμων - Hotel Management', 14, 5, 3, '2026-03-01', '2026-06-01', 'draft', 1);

-- --------------------------------------------------------
-- ΑΞΙΟΛΟΓΗΤΕΣ ΠΡΟΚΗΡΥΞΕΩΝ
-- --------------------------------------------------------
CREATE TABLE announcement_evaluators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE KEY unique_evaluator_announcement (announcement_id, evaluator_id)
);

INSERT INTO announcement_evaluators (announcement_id, evaluator_id, assigned_by) VALUES
(1, 3, 1),
(2, 3, 1);

-- --------------------------------------------------------
-- ΑΙΤΗΣΕΙΣ ΥΠΟΨΗΦΙΩΝ
-- --------------------------------------------------------
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    candidate_id INT NOT NULL,
    application_data JSON,
    completion_percentage INT DEFAULT 0,
    status ENUM('draft', 'submitted', 'under_review', 'accepted', 'rejected', 'withdrawn') DEFAULT 'draft',
    submission_date TIMESTAMP NULL,
    reviewed_by INT NULL,
    review_notes TEXT,
    reviewed_at TIMESTAMP NULL,
    moodle_enrolled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id),
    FOREIGN KEY (candidate_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_candidate (candidate_id)
);

INSERT INTO applications (announcement_id, candidate_id, completion_percentage, status, submission_date) VALUES
(1, 4, 65, 'under_review', '2026-03-15 10:30:00'),
(2, 4, 30, 'draft', NULL);

-- --------------------------------------------------------
-- ΚΑΤΑΣΤΑΣΗ ΕΓΓΡΑΦΗΣ ΣΤΟ MOODLE
-- --------------------------------------------------------
CREATE TABLE moodle_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    moodle_course_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'student',
    status ENUM('pending', 'enrolled', 'unenrolled', 'failed') DEFAULT 'pending',
    enrolled_at TIMESTAMP NULL,
    unenrolled_at TIMESTAMP NULL,
    sync_attempts INT DEFAULT 0,
    last_sync_attempt TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY unique_enrollment (user_id, course_id)
);

-- --------------------------------------------------------
-- ΙΣΤΟΡΙΚΟ ΣΥΓΧΡΟΝΙΣΜΟΥ MOODLE
-- --------------------------------------------------------
CREATE TABLE moodle_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action ENUM('user_created', 'user_updated', 'enrolled', 'unenrolled', 'sync_full') NOT NULL,
    moodle_user_id INT NULL,
    moodle_course_id INT NULL,
    status ENUM('success', 'failed', 'pending') NOT NULL,
    response_data JSON,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

-- --------------------------------------------------------
-- ΡΥΘΜΙΣΕΙΣ ΣΥΣΤΗΜΑΤΟΣ
-- --------------------------------------------------------
CREATE TABLE system_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

INSERT INTO system_configurations (config_key, config_value, description) VALUES
('moodle_url', 'http://localhost/moodle', 'URL τοπικής εγκατάστασης Moodle'),
('moodle_token', '', 'Webservice token για Moodle API'),
('moodle_enabled', '0', 'Ενεργοποίηση συγχρονισμού'),
('site_name', 'ΤΕΠΑΚ - Διαχείριση Ειδικών Επιστημόνων', 'Όνομα εφαρμογής'),
('site_logo', '/assets/img/logo.png', 'Διαδρομή λογότυπου'),
('primary_color', '#6c4f3a', 'Κύριο χρώμα'),
('academic_year', '2025-2026', 'Τρέχον ακαδημαϊκό έτος'),
('auto_sync_enabled', '0', 'Αυτόματος συγχρονισμός Moodle'),
('auto_sync_interval', '3600', 'Διάστημα συγχρονισμού σε δευτερόλεπτα');

-- --------------------------------------------------------
-- ΣΥΝΕΔΡΙΕΣ ΧΡΗΣΤΩΝ (JWT BLACKLIST)
-- --------------------------------------------------------
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    is_revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);
