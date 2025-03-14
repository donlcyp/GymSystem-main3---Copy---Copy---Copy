<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$db = GymDatabaseConnector::getInstance(); // Create database connection instance
$user_role = $_SESSION['role']; // Define user role from session


// Initialize variables
$error = '';
$success = '';
$membershipId = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$membershipId) {
    $error = "Membership ID is required. Please ensure you are accessing this page with a valid ID.";
    $member = null; // Ensure $member is null if ID is not provided
} else {
    $member = $db->getMemberById($membershipId); // Fetch member data by ID
} // Closing the if statement


// Ensure the following block is also closed properly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $memberData = [
        'membership_id' => $membershipId,
        'first_name' => filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING),
        'last_name' => filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'phone' => filter_input(INPUT_POST, 'phoneNumber', FILTER_SANITIZE_STRING),
        'address' => filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING),
        'birth_date' => filter_input(INPUT_POST, 'birthDate', FILTER_SANITIZE_STRING)
    ];

    $result = $db->updateMember($memberData);
    if (strpos($result, 'success') !== false) {
        // Log the action with userid and action
        $userid = $_SESSION['username']; // Assuming username is the userid
        $action = "Edited member: {$memberData['first_name']} {$memberData['last_name']} (ID: {$membershipId})";
        $db->addLog($userid, $action);
        
        header("Location: membership.php?success=1");
        exit();
    } else {
        $error = "Error updating member: $result";
    }
} // Closing the POST request handling block
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Membership - He-Man Fitness Gym</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts for Anton and Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
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
            padding: 20px;
            background-color: #1e1e1e;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        .main-content.shifted {
            margin-left: 250px;
        }

        .form-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #2c2c2c;
            border-radius: 5px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.25);
        }

        h2 {
            color: #ffffff;
            font-family: 'Anton', sans-serif;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            gap: 20px;
        }

        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #333;
            color: #e0e0e0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        input:focus, select:focus {
            border-color: #ff4444;
            outline: none;
        }

        button {
            background-color: #ff4444;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            display: block;
            margin: 20px auto 0;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #cc0000;
        }

        .success-message, .error-message {
            color: #ff4444;
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(255, 68, 68, 0.1);
            border-radius: 4px;
        }

        #amountDisplay {
            color: #ff4444;
            font-weight: bold;
            margin-top: 5px;
        }

        @media (max-width: 991px) {
            .sidebar-nav {
                width: 100%;
                left: -100%;
            }
            .sidebar-nav.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .main-content.shifted {
                margin-left: 0;
            }
            .form-row {
                flex-direction: column;
                gap: 15px;
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
                    <img
                        src="https://cdn.builder.io/api/v1/image/assets/d47b9c64343c4fb28708dd8b67fd1cce/487c8045bbe2fa69683c59be11df183c65f6fc7aac898ce424235e21c4b67556?placeholderIfAbsent=true"
                        alt="Dashboard Logo"
                        class="sidebar-logo"
                    />
                </div>

                <div class="sidebar-content">
                    <div class="nav-menu">
                        <?php if ($user_role === 'staff'): ?>
                        <a href="dashboard.php" class="nav-item nav-item--active">
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
                        <!-- Admin-only navigation -->
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
                        <a href="pricing.php" class="nav-item">
                            <i class="fas fa-dollar-sign nav-icon"></i>
                            <span>Pricing</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <main class="main-content" id="mainContent">
                <div class="form-container">
                    <h2>Edit Membership</h2>
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($member): ?>
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="firstName">First Name:</label>
                                    <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="lastName">Last Name:</label>
                                    <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phoneNumber">Phone Number:</label>
                                    <input type="tel" id="phoneNumber" name="phoneNumber" value="<?php echo htmlspecialchars($member['phone']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="address">Address:</label>
                                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($member['address']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="birthDate">Birth Date:</label>
                                    <input type="date" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($member['birth_date']); ?>" required>
                                </div>
                            </div>
                            <input type="hidden" name="confirm" value="1">
                            <input type="hidden" name="membershipId" value="<?php echo htmlspecialchars($membershipId); ?>">

                            <button type="submit">Update Member</button>
                        </form>
                    <?php else: ?>
                        <p class="error-message">No member data available to edit.</p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js" integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js" integrity="sha384-fbbOQedDUMZZ5KreZpsbe1LCZPVmfTnH7ois6mU1QK+m14rQ1l2bGBq41eYeM/fS" crossorigin="anonymous"></script>
    <script>
        // Pass PHP prices to JavaScript for dynamic display
        const prices = <?php echo json_encode($prices); ?>;

        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('shifted');
        });

        // Update amount display when plan changes
        document.getElementById('plan').addEventListener('change', function() {
            const selectedPlan = this.value;
            const amountDisplay = document.getElementById('amountDisplay');
            const amount = prices[selectedPlan] ? prices[selectedPlan].toFixed(2) : 'N/A';
            amountDisplay.textContent = `Amount: ₱${amount}`;
        });
    </script>
</body>
</html>
