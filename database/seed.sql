USE tepak_ee;

-- Passwords: admin → admin123 | user1 → user1pass | user2 → user2pass | hr1 → hr1pass | eval1 → eval1pass
INSERT INTO users (username,email,password_hash,role) VALUES
('admin','admin@test.com','$2y$12$eVhw3byr42xiO5QkUQ77Begrgx/ByZDdt/8MGSc9caDTnfygfQgMC','admin'),
('user1','user1@test.com','$2y$12$SN7T9DBRm6I6VFgmUWQK/.UEiuzH9Vd4jDYrOI3jsSHk6uAIehLay','candidate'),
('user2','user2@test.com','$2y$12$95dX5p605NIhNeWxUto/iu3IDednopZ2IDRo35L64Gmw9xfM8/t52','candidate'),
('hr1','hr1@test.com','$2y$12$ublbgtyzl5qJPVRY45Dg2ueGf2U8NAa.7WHXrOKmEUZbWQPFheq8i','hr'),
('eval1','eval1@test.com','$2y$12$HAXFoTJ9ggCHa7yYdOsN3ebc16OQWNKBsi5cd/B4z46WmDU/7N.G.','evaluator');

INSERT INTO applications (user_id,course,department,status) VALUES
(1,'Web Engineering','Computer Science','approved'),
(2,'Databases','Computer Science','pending'),
(3,'Networks','Engineering','pending'),
(2,'Programming','Computer Science','review'),
(3,'Algorithms','Computer Science','approved');
