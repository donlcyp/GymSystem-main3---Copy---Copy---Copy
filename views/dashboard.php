<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

$user_role = $_SESSION['role'];

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$total_sales = 0.00;
$total_members = 0;
$error = '';
$success = '';

try {
    $db = GymDatabaseConnector::getInstance();
    $stats = $db->getDashboardStats();
    
    $total_members = $stats['total_members'] ?? 0;
    $active_members = $stats['total_members'] ?? 0; // Consider differentiating active vs total if needed
    $total_revenue = $stats['total_sales'] ?? 0.00; // Ensure this matches the key from getDashboardStats
    $recent_logs = $stats['recent_logs'] ?? [];
} catch (Exception $e) {
    $error = "Error fetching dashboard data: " . $e->getMessage();
}

// Member addition (only process if POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING) ?? '';
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING) ?? '';
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
    $phoneNumber = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING) ?? '';
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING) ?? '';
    $birthDate = filter_input(INPUT_POST, 'birth_date', FILTER_SANITIZE_STRING) ?? '';
    $plan = filter_input(INPUT_POST, 'plan', FILTER_SANITIZE_STRING) ?? '';
    $dateAdded = filter_input(INPUT_POST, 'date_added', FILTER_SANITIZE_STRING) ?? '';
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? '';

    if (empty($firstName) || empty($lastName) || empty($email) || empty($phoneNumber) || 
        empty($address) || empty($birthDate) || empty($plan) || empty($dateAdded) || empty($amount)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (!in_array($plan, ['Per Session', 'Monthly'])) {
        $error = "Invalid plan selected";
    } else {
        try {
            $db = GymDatabaseConnector::getInstance();
            $memberData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phoneNumber,
                'address' => $address,
                'birth_date' => $birthDate,
                'plan' => $plan,
                'start_date' => $dateAdded,
                'amount' => $amount
            ];
            
            $result = $db->addMember($memberData);
            
            if (strpos($result, 'success') !== false) {
                $success = "Member added successfully";
                header("Location: dashboard.php?success=1");
                exit();
            } else {
                $error = $result;
            }
        } catch (Exception $e) {
            $error = "Error adding member: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - He-Man Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
        }

        .admin-header {
            background-color: rgba(68, 68, 68, 1);
            padding: 15px 40px;
            display: flex;
            align-items: center;
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

        .admin-profile {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .admin-name-container {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .admin-name {
            color: #fff;
            font-family: Inter, -apple-system, Roboto, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }

        .admin-subtitle {
            color: #ccc;
            font-family: Inter, -apple-system, Roboto, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 400;
            margin: 0;
        }

        .settings-container {
            position: relative;
            cursor: pointer;
        }

        .settings-icon {
            color: #fff;
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        .settings-icon:hover {
            transform: rotate(90deg);
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 30px;
            background-color: rgba(44, 44, 44, 1);
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            display: none;
            transform-origin: top right;
            transform: scaleY(0);
            transition: transform 0.3s ease;
            min-width: 120px;
        }

        .settings-container.active .dropdown-menu {
            display: block;
            transform: scaleY(1);
        }

        .dropdown-item {
            padding: 8px 15px;
            color: #fff;
            text-decoration: none;
            display: block;
            font-family: Inter, -apple-system, Roboto, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: background-color 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(226, 29, 29, 1);
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
            z-index: 10;
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
            font-family: Inter, -apple-system, Roboto, Helvetica, sans-serif;
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

        .nav-item--bordered {
            border: 2px solid rgba(226, 29, 29, 1);
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            color: #fff;
            font-family: Inter, -apple-system, Roboto, Helvetica, sans-serif;
            background-color: rgba(49, 49, 49, 0.88);
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        .main-content.shifted {
            margin-left: 250px;
        }

        .dashboard-cards {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .card {
            background-color: rgba(44, 44, 44, 1);
            padding: 20px;
            border-radius: 5px;
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .card-icon {
            font-size: 30px;
            color: rgba(226, 29, 29, 1);
        }

        .card-content h3 {
            margin: 0;
            font-size: 16px;
            color: #fff;
        }

        .card-content p {
            margin: 5px 0 0;
            font-size: 24px;
            font-weight: bold;
            color: #fff;
        }

        .graph-container {
            margin-top: 30px;
            width: 80%;
            height: 70%;
        }

        .graph-container h3 {
            margin: 0 0 20px 0;
            color: #fff;
        }

        .main-footer {
            background-color: #000;
            padding: 30px 40px;
            width: 100%;
            box-sizing: border-box;
            color: #fff;
            font-family: Inter, -apple-system, Roboto, Helvetica, sans-serif;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
        }

        .footer-section {
            flex: 1;
            min-width: 180px; /* Reduced from 200px */
            padding: 10px; /* Added padding for better spacing */
        }

        .footer-title {
            font-size: 18px; /* Slightly smaller for compactness */
            margin-bottom: 10px; /* Reduced margin */
            font-family: 'Anton', sans-serif; /* Matching dashboard title font */
            color: #fff;
            font-weight: 400;
        }

        .footer-contact-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px; /* Reduced from 15px */
            gap: 10px; /* Reduced from 12px */
        }

        .footer-contact-icon {
            font-size: 16px; /* Slightly smaller */
            color: rgba(226, 29, 29, 1);
            width: 16px; /* Reduced from 18px */
            text-align: center;
        }

        .footer-contact-text {
            font-size: 13px; /* Slightly smaller */
            color: #fff;
            margin: 0;
        }

        .footer-contact-text a {
            color: #fff;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-contact-text a:hover {
            color: rgba(226, 29, 29, 1);
        }

        .footer-social-list {
            display: flex;
            gap: 15px; /* Reduced from 20px */
            padding: 0;
            margin: 10px 0 0; /* Reduced from 15px */
            list-style: none;
        }

        .footer-social-item a {
            color: #fff;
            font-size: 20px; /* Slightly smaller */
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .footer-social-item a:hover {
            color: rgba(226, 29, 29, 1);
            transform: scale(1.1);
        }

        .footer-copyright {
            width: 100%;
            text-align: center;
            margin-top: 20px; /* Reduced from 30px */
            font-size: 11px; /* Slightly smaller */
            color: #ccc;
        }

        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .footer-social-list {
                justify-content: center;
            }

            .footer-section {
                min-width: 100%; /* Full width on mobile */
            }
        }
        </style>
</head>
<body>
    <header class="admin-header">
        <i class="fas fa-bars menu-icon" id="menuToggle"></i>
        <h1 class="admin-title">He-Man Fitness</h1>
        <div class="admin-profile">
            <div class="admin-name-container">
                <span class="admin-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <span class="admin-subtitle"><?php echo ucfirst($user_role); ?></span>
            </div>
            <div class="settings-container" id="settingsToggle">
                <i class="fas fa-cog settings-icon"></i>
                <div class="dropdown-menu">
                    <a href="#" class="dropdown-item">Settings</a>
                    <a href="logout.php" class="dropdown-item" id="logoutLink">Log Out</a>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-layout">
        <div class="content-wrapper">
            <nav class="sidebar-nav" id="sidebar">
                <div class="sidebar-header">
                    <h2 class="gym-title">He-Man Fitness Gym</h2>
                    <img src="https://cdn.builder.io/api/v1/image/assets/d47b9c64343c4fb28708dd8b67fd1cce/487c8045bbe2fa69683c59be11df183c65f6fc7aac898ce424235e21c4b67556?placeholderIfAbsent=true" alt="Dashboard Logo" class="sidebar-logo"/>
                </div>

                <div class="sidebar-content">
                    <div class="nav-menu">
                        <?php if ($user_role === 'staff'): ?>
                        <a href="dashboard.php" class="nav-item nav-item--active">
                            <i class="fas fa-home nav-icon"></i>
                            <span>Home</span>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <a href="dashboard.php" class="nav-item nav-item--active">
                            <i class="fas fa-home nav-icon"></i>
                            <span>Home</span>
                        <?php endif; ?>
                        </a>
                        <?php if ($user_role === 'staff'): ?>
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
                        <?php endif; ?>
                        <?php if ($user_role === 'admin'): ?>
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

            <main class="main-content" id="mainContent">
                <h2>Get Fit</h2>
                <p>This is where your dashboard content will go, aligned with the sidebar.</p>
                
                <div class="dashboard-cards">
                    <div class="card">
                        <i class="fas fa-dollar-sign card-icon"></i>
                        <div class="card-content">
                            <h3>Total Revenue</h3>
                            <p>$<?php echo number_format($total_revenue, 2); ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <i class="fas fa-users card-icon"></i>
                        <div class="card-content">
                            <h3>Active Members</h3>
                            <p><?php echo $active_members; ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <i class="fas fa-users card-icon"></i>
                        <div class="card-content">
                            <h3>Total Members</h3>
                            <p><?php echo $total_members; ?></p>
                        </div>
                    </div>
                </div>

                <?php if ($user_role === 'admin'): ?>
                <div class="graph-container">
                    <h3>Sales Overview (Admin)</h3>
                    <canvas id="adminSalesChart"></canvas>
                </div>
                <?php endif; ?>

                <?php if ($user_role === 'staff'): ?>
                <div class="graph-container">
                    <h3>Daily Check-ins (Staff)</h3>
                    <canvas id="staffCheckinChart"></canvas>
                </div>
                <?php endif; ?>
            </main>
        </div>

        <footer class="main-footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h3 class="footer-title">Contacts</h3>
                    <ul class="footer-contact-list">
                        <li class="footer-contact-item">
                            <i class="fas fa-phone footer-contact-icon"></i>
                            <p class="footer-contact-text">415-555-0132</p>
                        </li>
                        <li class="footer-contact-item">
                            <i class="fas fa-envelope footer-contact-icon"></i>
                            <p class="footer-contact-text">
                                <a href="mailto:info@hemanfitness.com">info@hemanfitness.com</a>
                            </p>
                        </li>
                        <li class="footer-contact-item">
                            <i class="fas fa-map-marker-alt footer-contact-icon"></i>
                            <p class="footer-contact-text">123 Fitness Street, Gym City, CA 90210</p>
                        </li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3 class="footer-title">Follow Us</h3>
                    <ul class="footer-social-list">
                        <li class="footer-social-item">
                            <a href="https://facebook.com/hemanfitness" target="_blank" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                        </li>
                        <li class="footer-social-item">
                            <a href="https://twitter.com/hemanfitness" target="_blank" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </li>
                        <li class="footer-social-item">
                            <a href="https://instagram.com/hemanfitness" target="_blank" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </li>
                        <li class="footer-social-item">
                            <a href="https://linkedin.com/company/hemanfitness" target="_blank" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="footer-copyright">
                    <p>© <?php echo date('Y'); ?> He-Man Fitness Gym. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('shifted');
        });

        document.getElementById('settingsToggle').addEventListener('click', function() {
            this.classList.toggle('active');
        });

        document.addEventListener('click', function(event) {
            const settingsContainer = document.getElementById('settingsToggle');
            if (!settingsContainer.contains(event.target)) {
                settingsContainer.classList.remove('active');
            }
        });

        <?php if ($user_role === 'admin'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('adminSalesChart').getContext('2d');
                
                const dailyLabels = <?php echo json_encode(array_column($stats['daily_sales'], 'date')); ?>;
                const dailyData = <?php echo json_encode(array_column($stats['daily_sales'], 'total')); ?>;
                const weeklyLabels = <?php echo json_encode(array_column($stats['weekly_sales'], 'week')); ?>;
                const weeklyData = <?php echo json_encode(array_column($stats['weekly_sales'], 'total')); ?>;
                const yearlyLabels = <?php echo json_encode(array_column($stats['yearly_sales'], 'year')); ?>;
                const yearlyData = <?php echo json_encode(array_column($stats['yearly_sales'], 'total')); ?>;

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        datasets: [{
                            label: 'Daily Sales',
                            data: dailyData,
                            borderColor: 'rgba(226, 29, 29, 1)',
                            tension: 0.1
                        }, {
                            label: 'Weekly Sales',
                            data: weeklyData,
                            borderColor: '#4CAF50',
                            tension: 0.1
                        }, {
                            label: 'Monthly Sales',
                            data: monthlyData,
                            borderColor: '#FF9800', // Orange for monthly
                            backgroundColor: 'rgba(255, 152, 0, 0.2)',
                            fill: false,
                            tension: 0.1
                        }, {
                            label: 'Yearly Sales',
                            data: yearlyData,
                            borderColor: '#2196F3',
                            tension: 0.1
                        }]
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'category',
                                labels: dailyLabels.concat(weeklyLabels, yearlyLabels),
                                title: { display: true, text: 'Time Period', color: '#fff' },
                                ticks: { color: '#fff' }
                            },
                            y: {
                                title: { display: true, text: 'Amount ($)', color: '#fff' },
                                ticks: { color: '#fff' }
                            }
                        },
                        plugins: {
                            legend: { labels: { color: '#fff' } }
                        }
                    }
                });
            });
        <?php endif; ?>

        <?php if ($user_role === 'staff'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('staffCheckinChart').getContext('2d');
                
                const checkinLabels = <?php echo json_encode(array_column($stats['daily_sales'], 'date')); ?>;
                const checkinData = <?php echo json_encode(array_column($stats['daily_sales'], 'total')); ?>;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: checkinLabels,
                        datasets: [{
                            label: 'Daily Check-ins',
                            data: checkinData,
                            backgroundColor: 'rgba(226, 29, 29, 0.7)',
                            borderColor: 'rgba(226, 29, 29, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            x: {
                                title: { display: true, text: 'Date', color: '#fff' },
                                ticks: { color: '#fff' }
                            },
                            y: {
                                title: { display: true, text: 'Number of Check-ins', color: '#fff' },
                                ticks: { color: '#fff' },
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: { labels: { color: '#fff' } }
                        }
                    }
                });
            });
        <?php endif; ?>
    </script>
</body>
</html>