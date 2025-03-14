<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$logs = [];

try {
    $db = GymDatabaseConnector::getInstance();
    $logs = $db->getLogs();
} catch (Exception $e) {
    $error = "Error fetching logs: " . $e->getMessage();
}

$user_role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - He-Man Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
            background-color: #1e1e1e;
            color: #e0e0e0;
            font-family: 'Inter', sans-serif;
        }

        .admin-header {
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

        .admin-title {
            font-family: 'Anton', sans-serif;
            font-size: 24px;
            color: #fff;
            font-weight: 400;
            margin: 0;
        }

        .dashboard-layout {
            padding-top: 70px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .content-wrapper {
            display: flex;
            flex: 1;
        }

        .sidebar-nav {
            background-color: #2c2c2c;
            width: 250px;
            position: fixed;
            top: 0;
            left: -250px;
            height: 100%;
            transition: left 0.3s ease;
            z-index: 900;
        }

        .sidebar-nav.active {
            left: 0;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
        }

        .gym-title {
            color: #fff;
            font-family: 'Anton', sans-serif;
            font-size: 22px;
        }

        .sidebar-logo {
            width: 30px;
            vertical-align: middle;
        }

        .nav-item {
            display: block;
            background-color: #e21d1d;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
            border-radius: 20px;
            margin: 10px 20px;
            font-size: 14px;
        }

        .nav-item--active {
            background-color: #ff3232;
        }

        .nav-icon {
            margin-right: 10px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .logs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background-color: #2c2c2c;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        h1 {
            color: #fff;
            font-family: 'Anton', sans-serif;
            font-size: 32px;
            margin-bottom: 25px;
            text-align: center;
        }

        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-container input {
            padding: 10px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #333;
            color: #e0e0e0;
            flex: 1;
        }

        .search-container button {
            background-color: #ff4444;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
        }

        .search-container button:hover {
            background-color: #cc0000;
        }

        .logs-table {
            width: 100%;
            background-color: #333;
            color: #e0e0e0;
            border-collapse: collapse;
            font-size: 14px;
        }

        .logs-table th {
            background-color: #ff4444;
            color: #fff;
            padding: 12px;
            text-align: left;
        }

        .logs-table td {
            padding: 12px;
            border-bottom: 1px solid #444;
        }

        .logs-table tr:hover {
            background-color: #3a3a3a;
        }

        .no-logs {
            text-align: center;
            padding: 20px;
            color: #ff4444;
        }

        .error-message {
            color: #ff4444;
            background-color: rgba(255, 68, 68, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        @media (max-width: 991px) {
            .logs-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <i class="fas fa-bars menu-icon" id="menuToggle"></i>
        <h1 class="admin-title">He-Man Fitness Gym</h1>
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
                        <a href="dashboard.php" class="nav-item"><i class="fas fa-home nav-icon"></i><span>Home</span></a>
                        <a href="ListMember.php" class="nav-item"><i class="fas fa-user-plus nav-icon"></i><span>Add Member</span></a>
                        <a href="attendance.php" class="nav-item"><i class="fas fa-calendar-check nav-icon"></i><span>Attendance</span></a>
                        <a href="membership.php" class="nav-item"><i class="fas fa-id-card nav-icon"></i><span>Membership</span></a>
                        <a href="logs.php" class="nav-item nav-item--active"><i class="fas fa-file-alt nav-icon"></i><span>Logs</span></a>
                        <a href="register.php" class="nav-item"><i class="fa-solid fa-circle-user nav-icon"></i><span>Employee Create Account</span></a>
                        <a href="employees.php" class="nav-item"><i class="fa-solid fa-user-tie nav-icon"></i><span>Employees</span></a>
                        <a href="pricing.php" class="nav-item"><i class="fas fa-dollar-sign nav-icon"></i><span>Pricing</span></a>
                        <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt nav-icon"></i><span>Logout</span></a>
                    </div>
                </div>
            </nav>

            <main class="main-content">
                <div class="logs-container">
                    <h1>Logs</h1>
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <div class="search-container">
                        <input type="text" id="searchInput" placeholder="Search logs...">
                        <button onclick="searchLogs()">Search</button>
                    </div>
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>User ID</th>
                                <th>Action</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="4" class="no-logs">No logs yet</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['userid']); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        function searchLogs() {
            const filter = document.getElementById('searchInput').value.toLowerCase();
            const tr = document.querySelectorAll('.logs-table tbody tr:not(.no-logs)');
            tr.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
            const visibleRows = Array.from(tr).some(row => row.style.display !== 'none');
            document.querySelector('.no-logs').style.display = visibleRows ? 'none' : '';
        }

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') searchLogs();
        });
    </script>
</body>
</html>