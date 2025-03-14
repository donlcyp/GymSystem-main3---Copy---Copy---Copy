<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_role = $_SESSION['role'];
$error = '';
$success = '';
$employees = [];

try {
    $db = GymDatabaseConnector::getInstance();
    $employees = $db->getAllEmployees();

    // Handle delete request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Invalid CSRF token";
        } else {
            $deleteId = filter_input(INPUT_POST, 'delete_id', FILTER_SANITIZE_STRING);
            if ($deleteId) {
                $currentUser = $db->getEmployeeDetails($_SESSION['username']);
                if ($deleteId === $currentUser['userid']) {
                    $error = "You cannot delete your own account while logged in.";
                } else {
                    $employee = $db->getEmployeeDetails($deleteId);
                    if ($employee) {
                        $result = $db->deleteEmployee($deleteId);
                        if (strpos($result, 'success') !== false) {
                            $action = "Deleted employee: {$employee['username']} (ID: $deleteId)";
                            $db->addLog($_SESSION['username'], $action);
                            $success = "Employee deleted successfully!";
                            header("Location: employees.php?success=1");
                            exit();
                        } else {
                            $error = "Error deleting employee: $result";
                        }
                    } else {
                        $error = "Employee not found.";
                    }
                }
            } else {
                $error = "Invalid employee ID.";
            }
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Function to determine status based on last login
function getDynamicStatus($lastLogin) {
    if ($lastLogin === null) {
        return 0; // Inactive if never logged in
    }
    $lastLoginDate = new DateTime($lastLogin);
    $currentDate = new DateTime();
    $interval = $currentDate->diff($lastLoginDate);
    return ($interval->days <= 7) ? 1 : 0; // Active if within 7 days, Inactive otherwise
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - He-Man Fitness Gym</title>
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
            padding: 20px;
            background-color: #1e1e1e;
            width: 100%;
        }

        .form-container {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 40px;
            background-color: #2c2c2c;
            border-radius: 5px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.25);
            text-align: center;
        }

        h1 {
            color: #ffffff;
            font-family: 'Anton', sans-serif;
            font-size: 32px;
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            max-width: 600px;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #333;
            color: #e0e0e0;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .search-input:focus {
            border-color: #ff4444;
            outline: none;
        }

        .search-button {
            background-color: #ff4444;
            color: #fff;
            padding: 12px 24px;
            border: none;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-bottom: 20px;
        }

        .search-button:hover {
            background-color: #cc0000;
        }

        .employees-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #333;
            color: #e0e0e0;
            table-layout: fixed;
        }

        .employees-table th,
        .employees-table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #444;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .employees-table th {
            background-color: #ff4444;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
        }

        .employees-table td {
            font-size: 15px;
        }

        .employees-table tr:hover {
            background-color: #3a3a3a;
        }

        .employees-table .no-employees {
            text-align: center;
            padding: 20px;
            color: #ff4444;
            font-weight: 700;
        }

        .edit-btn, .delete-btn {
            background-color: #ff4444;
            color: #fff;
            border: none;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 5px;
            text-decoration: none;
        }

        .edit-btn:hover, .delete-btn:hover {
            background-color: #cc0000;
        }

        .status-active {
            color: #28a745;
            font-weight: bold;
        }

        .status-inactive {
            color: #ff4444;
            font-weight: bold;
        }

        .error-message {
            color: #ff4444;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: rgba(255, 68, 68, 0.1);
            border-radius: 4px;
        }

        .success-message {
            color: #28a745;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
        }

        @media (max-width: 991px) {
            .sidebar-nav { width: 250px; left: -250px; }
            .sidebar-nav.active { left: 0; }
            .form-container { padding: 20px; max-width: 100%; }
            .search-input { max-width: 100%; }
            .employees-table {
                font-size: 13px;
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .employees-table th, .employees-table td {
                padding: 10px 8px;
                min-width: 100px;
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
                    </div>
                </div>
            </nav>

            <main class="main-content" id="mainContent">
                <div class="form-container">
                    <h1>Employees</h1>
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success || (isset($_GET['success']) && $_GET['success'] == 1)): ?>
                        <div class="success-message"><?php echo htmlspecialchars($success ?: 'Operation completed successfully!'); ?></div>
                    <?php endif; ?>
                    <input type="text" class="search-input" id="searchInput" placeholder="Search employees...">
                    <button class="search-button" id="searchButton">Search</button>
                    <table class="employees-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (count($employees) > 0) {
                                foreach ($employees as $row) {
                                    $dynamicStatus = getDynamicStatus($row["last_login"]);
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row["userid"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["first_name"] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["last_name"] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["email"] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["role"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["created_at"] ?? 'N/A') . "</td>";
                                    echo "<td class='" . ($dynamicStatus == 1 ? "status-active" : "status-inactive") . "'>" . 
                                         htmlspecialchars($dynamicStatus == 1 ? "Active" : "Inactive") . "</td>";
                                    echo "<td>";
                                    echo "<a href='edit_employee.php?id=" . htmlspecialchars($row["userid"]) . "' class='edit-btn'><i class='fas fa-edit'></i></a>";
                                    echo "<form method='POST' action='' style='display:inline;' class='delete-form'>";
                                    echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token']) . "'>";
                                    echo "<input type='hidden' name='delete_id' value='" . htmlspecialchars($row["userid"]) . "'>";
                                    echo "<button type='submit' class='delete-btn'><i class='fas fa-trash'></i></button>";
                                    echo "</form>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' class='no-employees'>No Employees Yet</td></tr>";
                            }
                            ?>
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

        // Search functionality
        document.getElementById('searchButton').addEventListener('click', function() {
            searchTable();
        });

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchTable();
            }
        });

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('.employees-table');
            const tr = table.getElementsByTagName('tr');
            let hasResults = false;

            for (let i = 1; i < tr.length; i++) {
                let rowText = '';
                const td = tr[i].getElementsByTagName('td');
                
                for (let j = 0; j < td.length - 1; j++) { // Exclude Action column
                    rowText += td[j].textContent.toLowerCase() + ' ';
                }

                if (rowText.indexOf(filter) > -1) {
                    tr[i].style.display = '';
                    hasResults = true;
                } else {
                    tr[i].style.display = 'none';
                }
            }

            if (filter === '') {
                for (let i = 1; i < tr.length; i++) {
                    tr[i].style.display = '';
                }
            }

            const noResultsRow = table.querySelector('.no-employees');
            if (noResultsRow && !hasResults && filter !== '') {
                noResultsRow.style.display = '';
            }
        }

        // Delete confirmation
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to delete this employee?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>