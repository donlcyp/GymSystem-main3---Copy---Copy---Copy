<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

// Check if user is admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Define user_role from session
$user_role = $_SESSION['role'] ?? null;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$db = GymDatabaseConnector::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        // Sanitize and validate input
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

        // Validation
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif (!in_array($role, ['staff', 'admin'])) {
            $error = "Invalid role selected";
        } else {
            try {
                $userId = $db->registerEmployee(
                    $first_name,
                    $last_name,
                    $username,
                    $email,
                    $password,
                    $role,
                    $_SESSION['username']
                );
                
                if ($userId) {
                    $success = "Employee registered successfully! User ID: " . htmlspecialchars($userId);
                    $_POST = array();
                } else {
                    $error = "Registration failed";
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - He-Man Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
            background-color: rgba(49, 49, 49, 0.88);
        }

        .site-header {
            background-color: rgba(68, 68, 68, 1);
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            box-sizing: border-box;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.25);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 20;
        }

        .menu-icon {
            font-size: 20px;
            color: #fff;
            margin-right: 15px;
            cursor: pointer;
        }

        .brand-name {
            font-family: 'Anton', sans-serif;
            font-size: 24px;
            color: #fff;
            font-weight: 400;
            margin: 0;
        }

        .dashboard-layout {
            background-color: rgba(49, 49, 49, 0.88);
            min-height: 100%;
            display: flex;
            flex-direction: column;
            padding-top: 50px;
        }

        .content-wrapper {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .sidebar-nav {
            background-color: rgba(44, 44, 44, 1);
            width: 250px;
            padding: 20px 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 30;
            position: fixed;
            height: 100%;
            top: 0;
            left: -250px;
            transition: left 0.3s ease;
        }

        .sidebar-nav.active {
            left: 0;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px 0;
        }

        .sidebar-logo {
            width: 30px;
            margin-left: 10px;
            display: inline-block;
        }

        .sidebar-content {
            padding: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .gym-title {
            color: #fff;
            font-size: 24px;
            font-family: 'Anton', sans-serif;
            font-weight: 400;
            margin: 0;
            text-align: center;
            display: inline-block;
        }

        .nav-menu {
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            width: 100%;
            padding: 0 10px;
            box-sizing: border-box;
        }

        .nav-item {
            border-radius: 20px;
            background-color: rgba(226, 29, 29, 1);
            padding: 12px 15px;
            color: #fff;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            box-sizing: border-box;
            text-align: left;
            cursor: pointer;
        }

        .nav-item--active {
            background-color: rgba(255, 50, 50, 1);
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .main-content {
            flex: 1;
            padding: 80px 20px 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 50px);
        }

        .login-container {
            background-color: rgba(44, 44, 44, 1);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .logo-image {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
        }

        .login-heading {
            font-family: 'Anton', sans-serif;
            font-size: 48px;
            font-weight: 400;
            margin: 0 0 20px;
        }

        .text-red {
            color: rgba(226, 29, 29, 1);
        }

        .text-white {
            color: #fff;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .form-label {
            font-family: 'Arial', sans-serif;
            font-size: 16px;
            color: #fff;
            font-weight: 500;
            margin-bottom: 5px;
            width: 70%;
            text-align: left;
        }

        .form-input {
            width: 70%;
            padding: 10px;
            border-radius: 10px;
            background-color: rgba(68, 68, 68, 0.8);
            color: #fff;
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            text-align: left;
            margin: 0 auto;
            display: block;
            border: none;
        }

        .login-button {
            background-color: rgba(226, 29, 29, 1);
            padding: 12px;
            border: none;
            border-radius: 20px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            width: 70%;
            margin: 0 auto;
        }

        .error-message {
            color: rgba(226, 29, 29, 1);
            font-size: 14px;
            margin-top: 10px;
        }

        .success-message {
            color: #28a745;
            font-size: 14px;
            margin-top: 10px;
        }

        @media (max-width: 991px) {
            .sidebar-nav { width: 250px; left: -250px; }
            .sidebar-nav.active { left: 0; }
            .login-container { padding: 20px; max-width: 90%; }
            .form-input { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <i class="fas fa-bars menu-icon" id="menuToggle"></i>
        <h1 class="brand-name">He-Man Fitness Gym</h1>
    </header>

    <div class="dashboard-layout">
        <div class="content-wrapper">
            <nav class="sidebar-nav" id="sidebar">
                <div class="sidebar-header">
                    <h2 class="gym-title">He-Man Fitness Gym</h2>
                    <img src="https://cdn.builder.io/api/v1/image/assets/d47b9c64343c4fb28708dd8b67fd1cce/487c8045bbe2fa69683c59be11df183c65f6fc7aac898ce424235e21c4b67556?placeholderIfAbsent=true" alt="Dashboard Logo" class="sidebar-logo" />
                </div>
                <div class="sidebar-content">
                    <div class="nav-menu">
                        <?php if ($user_role === 'staff'): ?>
                        <a href="dashboard.php" class="nav-item">
                            <i class="fas fa-home nav-icon"></i>
                            <span>Home</span>
                        </a>
                        <a href="ListMember.php" class="nav-item">
                            <i class="fas fa-user-plus nav-icon"></i>
                            <span>Add Member</span>
                        </a>
                        <a href="attendance.php" class="nav-item">
                            <i class="fas fa-calendar-check nav-icon"></i>
                            <span>Attendance</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="dashboard.php" class="nav-item">
                            <i class="fas fa-home nav-icon"></i>
                            <span>Home</span>
                        </a>
                        <a href="ListMember.php" class="nav-item">
                            <i class="fas fa-user-plus nav-icon"></i>
                            <span>Add Member</span>
                        </a>
                        <a href="attendance.php" class="nav-item">
                            <i class="fas fa-calendar-check nav-icon"></i>
                            <span>Attendance</span>
                        </a>
                        <a href="membership.php" class="nav-item">
                            <i class="fas fa-id-card nav-icon"></i>
                            <span>Membership</span>
                        </a>
                        <a href="logs.php" class="nav-item">
                            <i class="fas fa-file-alt nav-icon"></i>
                            <span>Logs</span>
                        </a>
                        <a href="register.php" class="nav-item">
                            <i class="fa-solid fa-circle-user"></i>
                            <span>Employee Create Account</span>
                        </a>
                        <a href="employees.php" class="nav-item">
                            <i class="fa-solid fa-user-tie"></i>
                            <span>Employees</span>
                        </a>
                        <a href="pricing.php" class="nav-item nav-item--active">
                            <i class="fas fa-dollar-sign nav-icon"></i>
                            <span>Pricing</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <main class="main-content">
                <div class="login-container">
                    <img src="../img/logo2.jpg" class="logo-image" alt="He-Man Fitness Gym Logo" />
                    <h2 class="login-heading">
                        <span class="text-red">REG</span><span class="text-white">ISTER</span>
                    </h2>
                    <form class="login-form" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" />
                        </div>

                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" />
                        </div>

                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-input" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-input" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-input" required />
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required />
                        </div>

                        <div class="form-group">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-input" required>
                                <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role'] === 'staff') ? 'selected' : ''; ?>>Staff</option>
                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>

                        <button type="submit" class="login-button">Register</button>
                    </form>
                    <?php if ($error): ?>
                        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js" integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js" integrity="sha384-fbbOQedDUMZZ5KreZpsbe1LCZPVmfTnH7ois6mU1QK+m14rQ1l2bGBq41eYeM/fS" crossorigin="anonymous"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menuToggle');

        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        sidebar.addEventListener('click', function(e) {
            if (!e.target.closest('a')) {
                e.stopPropagation();
            }
        });
    </script>
</body>
</html>