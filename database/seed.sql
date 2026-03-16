USE tepak_ee;

INSERT INTO users (username,email,password_hash,role) VALUES
('admin','admin@test.com','$2y$10$examplehash','admin'),
('user1','user1@test.com','$2y$10$examplehash','candidate'),
('user2','user2@test.com','$2y$10$examplehash','candidate');

INSERT INTO applications (user_id,course,department,status) VALUES
(1,'Web Engineering','Computer Science','approved'),
(2,'Databases','Computer Science','pending'),
(3,'Networks','Engineering','pending'),
(2,'Programming','Computer Science','review'),
(3,'Algorithms','Computer Science','approved');
