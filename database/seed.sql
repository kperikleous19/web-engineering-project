USE tepak_ee;

-- --------------------------------------------------------
-- ROLES
-- --------------------------------------------------------
INSERT INTO roles (role_name) VALUES
('admin'),
('candidate'),
('evaluator'),
('hr'),
('ee');

-- --------------------------------------------------------
-- FACULTIES
-- --------------------------------------------------------
INSERT INTO faculties (faculty_name, faculty_name_el) VALUES
('Faculty of Engineering and Technology', 'Σχολή Μηχανικής και Τεχνολογίας'),
('Faculty of Management and Economics', 'Σχολή Διοίκησης και Οικονομίας'),
('Faculty of Communication and Media Studies', 'Σχολή Επικοινωνίας και Μέσων Ενημέρωσης'),
('Faculty of Fine and Applied Arts', 'Σχολή Καλών και Εφαρμοσμένων Τεχνών'),
('Faculty of Health Sciences', 'Σχολή Επιστημών Υγείας');

-- --------------------------------------------------------
-- DEPARTMENTS
-- --------------------------------------------------------
INSERT INTO departments (dept_name, dept_name_el, faculty_id) VALUES
('Department of Electrical Engineering', 'Τμήμα Ηλεκτρολόγων Μηχανικών', 1),
('Department of Computer Science', 'Τμήμα Επιστήμης Υπολογιστών', 1),
('Department of Civil Engineering', 'Τμήμα Πολιτικών Μηχανικών', 1),
('Department of Business Administration', 'Τμήμα Διοίκησης Επιχειρήσεων', 2),
('Department of Accounting and Finance', 'Τμήμα Λογιστικής και Χρηματοοικονομικής', 2),
('Department of Multimedia and Graphic Arts', 'Τμήμα Πολυμέσων και Γραφικών Τεχνών', 3),
('Department of Nursing', 'Τμήμα Νοσηλευτικής', 5);

-- --------------------------------------------------------
-- COURSES
-- --------------------------------------------------------
INSERT INTO courses (course_code, course_name, department_id) VALUES
('EE101', 'Εισαγωγή στην Ηλεκτρολογία', 1),
('EE202', 'Κυκλώματα και Συστήματα', 1),
('CS101', 'Εισαγωγή στον Προγραμματισμό', 2),
('CS203', 'Δομές Δεδομένων', 2),
('CS305', 'Βάσεις Δεδομένων', 2),
('CE101', 'Στατική', 3),
('BA101', 'Αρχές Διοίκησης', 4),
('BA210', 'Μάρκετινγκ', 4),
('AF101', 'Λογιστική Ι', 5),
('MG101', 'Εισαγωγή στα Πολυμέσα', 6),
('NU101', 'Ανατομία και Φυσιολογία', 7);

-- --------------------------------------------------------
-- USERS
-- Passwords: admin → admin123 | user1 → user1pass | user2 → user2pass | hr1 → hr1pass | eval1 → eval1pass
-- --------------------------------------------------------
INSERT INTO users (username, email, password_hash, role, role_id, first_name, last_name) VALUES
('admin',  'admin@test.com',  '$2y$12$eVhw3byr42xiO5QkUQ77Begrgx/ByZDdt/8MGSc9caDTnfygfQgMC', 'admin',     1, 'Admin', 'ΤΕΠΑΚ'),
('user1',  'user1@test.com',  '$2y$12$SN7T9DBRm6I6VFgmUWQK/.UEiuzH9Vd4jDYrOI3jsSHk6uAIehLay', 'candidate', 2, 'Ανδρέας', 'Παπαδόπουλος'),
('user2',  'user2@test.com',  '$2y$12$95dX5p605NIhNeWxUto/iu3IDednopZ2IDRo35L64Gmw9xfM8/t52', 'candidate', 2, 'Μαρία', 'Νικολάου'),
('hr1',    'hr1@test.com',    '$2y$12$ublbgtyzl5qJPVRY45Dg2ueGf2U8NAa.7WHXrOKmEUZbWQPFheq8i', 'hr',        4, 'Ελένη', 'Κωνσταντίνου'),
('eval1',  'eval1@test.com',  '$2y$12$HAXFoTJ9ggCHa7yYdOsN3ebc16OQWNKBsi5cd/B4z46WmDU/7N.G.', 'evaluator', 3, 'Νίκος', 'Αντωνίου');

-- --------------------------------------------------------
-- RECRUITMENT PERIODS
-- --------------------------------------------------------
INSERT INTO recruitment_periods (period_name, start_date, end_date, description, is_active, created_by) VALUES
('Περίοδος Προσλήψεων 2024-2025', '2024-10-01', '2025-01-31', 'Ακαδημαϊκή περίοδος 2024-2025 για Ειδικούς Επιστήμονες', 1, 1),
('Περίοδος Προσλήψεων 2025 (Εαρινό)', '2025-02-01', '2025-06-30', 'Εαρινό εξάμηνο 2025', 1, 1);

-- --------------------------------------------------------
-- ANNOUNCEMENTS
-- --------------------------------------------------------
INSERT INTO announcements (title_el, application_start, application_end, course_id) VALUES
('Πρόσκληση Εκδήλωσης Ενδιαφέροντος - Εισαγωγή στον Προγραμματισμό', '2024-10-01', '2024-11-30', 3),
('Πρόσκληση Εκδήλωσης Ενδιαφέροντος - Βάσεις Δεδομένων', '2024-10-01', '2024-11-30', 5),
('Πρόσκληση Εκδήλωσης Ενδιαφέροντος - Δομές Δεδομένων', '2025-02-01', '2025-03-31', 4),
('Πρόσκληση Εκδήλωσης Ενδιαφέροντος - Λογιστική Ι', '2025-02-01', '2025-03-31', 9);

-- --------------------------------------------------------
-- MOODLE INTEGRATION
-- --------------------------------------------------------
INSERT INTO moodle_integration (user_id, course_id, moodle_enrolled, access_enabled, last_sync) VALUES
(2, 3, 1, 1, NOW()),
(2, 5, 0, 0, NULL),
(3, 4, 1, 1, NOW());

-- --------------------------------------------------------
-- APPLICATIONS
-- --------------------------------------------------------
INSERT INTO applications (user_id, course, department, course_name, department_name, school_name, status, completion_percentage) VALUES
(2, 'Βάσεις Δεδομένων', 'Τμήμα Επιστήμης Υπολογιστών', 'Βάσεις Δεδομένων', 'Τμήμα Επιστήμης Υπολογιστών', 'Σχολή Μηχανικής και Τεχνολογίας', 'approved', 100),
(2, 'Εισαγωγή στον Προγραμματισμό', 'Τμήμα Επιστήμης Υπολογιστών', 'Εισαγωγή στον Προγραμματισμό', 'Τμήμα Επιστήμης Υπολογιστών', 'Σχολή Μηχανικής και Τεχνολογίας', 'pending', 30),
(3, 'Δομές Δεδομένων', 'Τμήμα Επιστήμης Υπολογιστών', 'Δομές Δεδομένων', 'Τμήμα Επιστήμης Υπολογιστών', 'Σχολή Μηχανικής και Τεχνολογίας', 'under_review', 70),
(3, 'Λογιστική Ι', 'Τμήμα Λογιστικής και Χρηματοοικονομικής', 'Λογιστική Ι', 'Τμήμα Λογιστικής και Χρηματοοικονομικής', 'Σχολή Διοίκησης και Οικονομίας', 'pending', 50);

-- --------------------------------------------------------
-- SYSTEM CONFIG
-- --------------------------------------------------------
INSERT INTO system_config (config_key, config_value) VALUES
('auto_sync_enabled', '1'),
('system_name', 'Σύστημα Διαχείρισης Ειδικών Επιστημόνων'),
('institution_name', 'Τεχνολογικό Πανεπιστήμιο Κύπρου'),
('max_applications_per_period', '3'),
('moodle_url', 'https://moodle.tepak.ac.cy');