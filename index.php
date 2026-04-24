<?php
session_start();

// ONLY redirect if user is logged in AND has a valid role
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] !== '') {
    $role = strtolower($_SESSION['role']);
    
    if ($role == 'admin') {
        header("Location: admin/dashboard.php");
        exit;
    } elseif ($role == 'hr') {
        header("Location: enrollment/dashboard.php");
        exit;
    } elseif ($role == 'candidate' || $role == 'evaluator') {
        header("Location: recruitment/dashboard.php");
        exit;
    }
}
// If not logged in or role not set, show the landing page (NO ELSE REDIRECT!)
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <title>ΤΕΠΑΚ - Σύστημα Διαχείρισης Ειδικών Επιστημόνων</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f1ec;
            min-height: 100vh;
        }
        
        .landing-hero {
            background: #e8ded5;
            color: #5a4a40;
            text-align: center;
            padding: 80px 20px;
            border-bottom: 1px solid #d6c9bf;
        }
        
        .landing-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #4a3a32;
        }
        
        .landing-hero p {
            font-size: 1.2rem;
            color: #8a7163;
        }
        
        .landing-modules {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }
        
        .landing-modules h2 {
            text-align: center;
            color: #5a4a40;
            margin-bottom: 30px;
            font-size: 1.8rem;
        }
        
        .cards {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            justify-content: center;
        }
        
        /* Admin Card */
        .card-admin {
            background: white;
            border-radius: 20px;
            padding: 30px;
            width: 300px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(90, 70, 60, 0.08);
            border: 1px solid #e9dfd7;
        }
        
        .card-admin:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(90, 70, 60, 0.15);
            border-color: #dacbc1;
        }
        
        /* Recruitment Card */
        .card-recruitment {
            background: white;
            border-radius: 20px;
            padding: 30px;
            width: 300px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(90, 70, 60, 0.08);
            border: 1px solid #e9dfd7;
        }
        
        .card-recruitment:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(90, 70, 60, 0.15);
            border-color: #dacbc1;
        }
        
        /* Enrollment Card */
        .card-enrollment {
            background: white;
            border-radius: 20px;
            padding: 30px;
            width: 300px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(90, 70, 60, 0.08);
            border: 1px solid #e9dfd7;
        }
        
        .card-enrollment:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(90, 70, 60, 0.15);
            border-color: #dacbc1;
        }
        
        .card-icon {
            font-size: 55px;
            margin-bottom: 20px;
        }
        
        .card-admin h2, .card-recruitment h2, .card-enrollment h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #5a4a40;
        }
        
        .card-admin p, .card-recruitment p, .card-enrollment p {
            color: #8a7163;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .module-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .badge-admin { background: #2c5f8a; color: white; }
        .badge-recruitment { background: #8b6b4d; color: white; }
        .badge-enrollment { background: #6c9ebf; color: white; }
        
        .btn {
            display: inline-block;
            padding: 10px 25px;
            background: #e6d9d0;
            border: 1px solid #dacbc1;
            color: #5a4a40;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s;
            margin-top: 10px;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #dccfc4;
            transform: translateY(-2px);
        }
        
        .landing-register {
            text-align: center;
            margin-top: 40px;
            color: #8a7163;
        }
        
        .landing-register a {
            color: #8b6b4d;
            text-decoration: none;
            font-weight: bold;
        }
        
        .landing-register a:hover {
            color: #5a4a40;
            text-decoration: underline;
        }
        
        .demo-credentials {
            text-align: center;
            margin-top: 30px;
            padding: 18px;
            background: #faf6f2;
            border: 1px solid #e9dfd7;
            border-radius: 16px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            font-size: 13px;
            color: #5a4a40;
        }
        
        .demo-credentials strong {
            color: #5a4a40;
        }
        
        .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e1d6ce;
        }
        
        .cred-row:last-child {
            border-bottom: none;
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .role-admin { background: #2c5f8a; color: white; }
        .role-candidate { background: #8b6b4d; color: white; }
        .role-hr { background: #6c9ebf; color: white; }
        
        footer {
            background: #e8ded5;
            border-top: 1px solid #d6c9bf;
            color: #8a7163;
            text-align: center;
            padding: 20px;
            margin-top: 40px;
        }
        
        @media (max-width: 768px) {
            .landing-hero h1 { font-size: 1.8rem; }
            .cards { flex-direction: column; align-items: center; }
            .card-admin, .card-recruitment, .card-enrollment { width: 90%; max-width: 350px; }
            .cred-row { flex-direction: column; gap: 5px; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="landing-hero">
    <h1><i class="fas fa-university"></i> Σύστημα Διαχείρισης Ειδικών Επιστημόνων</h1>
    <p>Τεχνολογικό Πανεπιστήμιο Κύπρου (ΤΕΠΑΚ)</p>
</div>

<div class="landing-modules">
    <h2><i class="fas fa-cubes"></i> Επιλέξτε Module</h2>
    <div class="cards">

        <!-- Admin Card -->
        <div class="card-admin" onclick="location.href='auth/login.php?module=admin'">
            <div class="card-icon">🛡️</div>
            <h2>Admin Module</h2>
            <p>Διαχείριση χρηστών, προσλήψεων, ρυθμίσεων συστήματος και αναφορών.</p>
            <span class="module-badge badge-admin">🔒 Μόνο για Διαχειριστές</span>
            <div class="btn">Είσοδος →</div>
        </div>

        <!-- Recruitment Card -->
        <div class="card-recruitment" onclick="location.href='auth/login.php?module=recruitment'">
            <div class="card-icon">📄</div>
            <h2>Recruitment Module</h2>
            <p>Υποβολή αιτήσεων, παρακολούθηση κατάστασης και διαχείριση προφίλ.</p>
            <span class="module-badge badge-recruitment">👥 Υποψήφιοι & Αξιολογητές</span>
            <div class="btn">Είσοδος →</div>
        </div>

        <!-- Enrollment Card -->
        <div class="card-enrollment" onclick="location.href='auth/login.php?module=enrollment'">
            <div class="card-icon">📚</div>
            <h2>Enrollment Module</h2>
            <p>Συγχρονισμός ειδικών επιστημόνων με το LMS Moodle και αναφορές.</p>
            <span class="module-badge badge-enrollment">🏢 HR & Διαχειριστές</span>
            <div class="btn">Είσοδος →</div>
        </div>

    </div>

    <div class="landing-register">
        ✨ Νέος χρήστης; 
        <a href="auth/register.php">Εγγραφή εδώ</a>
    </div>
    
    <div class="demo-credentials">
        <strong><i class="fas fa-key"></i> Δοκιμαστικοί Λογαριασμοί</strong>
        <div class="cred-row">
            <span><span class="role-badge role-admin">Admin</span></span>
            <span>admin@tepak.edu.cy</span>
            <span>admin123</span>
        </div>
        <div class="cred-row">
            <span><span class="role-badge role-candidate">Υποψήφιος</span></span>
            <span>candidate1@example.com</span>
            <span>candidate123</span>
        </div>
        <div class="cred-row">
            <span><span class="role-badge role-hr">HR</span></span>
            <span>hr@tepak.edu.cy</span>
            <span>admin123</span>
        </div>
    </div>
</div>

<footer>
    <p><i class="fas fa-copyright"></i> Σύστημα Διαχείρισης Ειδικών Επιστημόνων — ΤΕΠΑΚ <?= date('Y') ?></p>
</footer>

</body>
</html>