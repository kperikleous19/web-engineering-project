# Web Engineering Project – Recruitment Management System

## 👥 Team Members

* Katerina Perikleous 30270
* Christina Antoniou 30330
* Andri Georgiou
* Andrianna Ioannou 30400

---

## 📌 Project Description

This project is a web-based Recruitment Management System developed for managing Special Scientists (EE) at the Cyprus University of Technology.

The system supports the full recruitment workflow, including user management, application handling, and application tracking.

Users can:

* Register and login securely
* Submit and view applications
* Search applications using keywords
* Access protected content based on authentication

---

## ⚙️ Technologies Used

* PHP (Backend)
* MySQL / MariaDB (Database)
* HTML / CSS (Frontend)
* PDO (Secure Database Connection)

---

## 🧱 Backend Implementation (Milestone 2)

The backend system includes:

* Database design using MySQL (`schema.sql`)
* Demo data (`seed.sql`)
* Secure PDO connection (`db.php`)
* Full Authentication System:

  * Register (with validation)
  * Login (with password verification)
  * Logout (session destroy)
* Session-based access control (Session Guard)
* Search functionality in `list.php` using GET method

---

## 📁 Project Structure

```
project-root/

database/
  ├── schema.sql
  └── seed.sql

includes/
  └── db.php

auth/
  ├── register.php
  ├── login.php
  └── logout.php

modules/
  ├── dashboard.php
  └── list.php

README.md
```

---

## 🗄️ Database Setup

1. Open phpMyAdmin
2. Create a new database:

```
web_project
```

3. Import the following files:

* `database/schema.sql`
* `database/seed.sql`

---

## ▶️ How to Run the Project

1. Install XAMPP or Laragon
2. Place the project folder inside:

```
htdocs/
```

3. Start Apache and MySQL
4. Open browser and go to:

```
http://localhost/web-engineering-project/auth/register.php
```

---

## 🔐 Security Features

The application follows secure coding practices:

* ✔ PDO with Prepared Statements (prevents SQL Injection)
* ✔ password_hash() for secure password storage
* ✔ password_verify() for login validation
* ✔ htmlspecialchars() to prevent XSS attacks
* ✔ Session-based authentication system
* ✔ No exposure of database error messages

---

## 🔍 Features Implemented

* User Registration with validation
* Secure Login System
* Logout functionality
* Protected Dashboard (only logged-in users)
* Applications list
* Keyword Search (GET method, bookmarkable)


## 📊 Notes

* Each team member has contributed with at least one commit
* Authentication flow is fully functional
* The project follows the required folder structure
* All security requirements have been implemented

---

## 📎 Repository

GitHub Repository:
https://github.com/kperikleous19/web-engineering-project
