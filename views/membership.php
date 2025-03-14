<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$error = '';
$success = '';
$defaultDateAdded = date('Y-m-d');
$members = [];

try {
    $db = GymDatabaseConnector::getInstance();
    
    $pricesFromDb = $db->getPrices();
    $prices = [];
    foreach ($pricesFromDb as $price) {
        $prices[$price['type'] === 'per_session' ? 'Per Session' : 'Monthly'] = $price['price'];
    }
    
    $members = $db->getMembers();

    // Handle renewal with payment logging
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_id'])) {
        $memberId = filter_input(INPUT_POST, 'renew_id', FILTER_VALIDATE_INT);
        $plan = filter_input(INPUT_POST, 'plan', FILTER_SANITIZE_STRING);
        $startDate = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING) ?: $defaultDateAdded;
        $amount = $prices[$plan] ?? 0.00;

        if ($memberId === false || $memberId <= 0) {
            $error = "Invalid or missing Membership ID for renewal.";
        } elseif (!in_array($plan, array_keys($prices))) {
            $error = "Invalid plan selected for renewal.";
        } else {
            $result = $db->renewPlan($memberId, $plan === 'Per Session' ? 'per_session' : 'monthly', $amount, $startDate);
            if (strpos($result, 'success') !== false) {
                $userid = $_SESSION['username'];
                $action = "Renewed plan for member ID $memberId to $plan";
                $db->addLog($userid, $action);
                $db->addPaymentLog($memberId, $amount, $userid);
                $success = "Plan renewed successfully";
                $members = $db->getMembers(); // Refresh member list
            } else {
                $error = "Error renewing plan: $result";
            }
        }
    }

    // Handle member deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $deleteId = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
        if ($deleteId === false || $deleteId <= 0) {
            $error = "Invalid or missing Membership ID for deletion.";
        } else {
            try {
                $result = $db->deleteMember($deleteId);
                if ($result === "Member deleted successfully") {
                    $userid = $_SESSION['username'];
                    $action = "Deleted member ID $deleteId";
                    $db->addLog($userid, $action);
                    $success = "Member deleted successfully";
                    $members = $db->getMembers(); // Refresh member list
                } else {
                    $error = "Error deleting member: $result";
                }
            } catch (Exception $e) {
                $error = "Deletion failed: " . $e->getMessage();
            }
        }
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    $prices = ['Per Session' => 80.00, 'Monthly' => 850.00];
    $members = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership - He-Man Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <style>
        /* General Layout */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: #1e1e1e;
            color: #e0e0e0;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        /* Header */
        .admin-header {
            background-color: #444;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.25);
            z-index: 1000;
        }
        .menu-icon {
            font-size: 20px;
            color: #fff;
            cursor: pointer;
        }
        .admin-title {
            font-family: 'Anton', sans-serif;
            font-size: 24px;
            color: #fff;
            margin: 0;
        }

        /* Dashboard Layout */
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

        /* Sidebar */
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
        .nav-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .main-content.shifted {
            margin-left: 250px;
        }
        .form-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background-color: #2c2c2c;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        h1, h2 {
            color: #fff;
            font-family: 'Anton', sans-serif;
            font-size: 32px;
            margin-bottom: 25px;
            text-align: center;
        }
        h2 {
            font-size: 24px;
        }

        /* Search Section */
        .search-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
        }
        .search-input {
            width: 100%;
            max-width: 400px;
            padding: 10px 15px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #333;
            color: #e0e0e0;
            font-size: 14px;
        }
        .search-input:focus {
            border-color: #ff4444;
            outline: none;
        }
        .search-button {
            background-color: #ff4444;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .search-button:hover {
            background-color: #cc0000;
        }

        /* Membership Table (Renewal) */
        .table-container {
            overflow-x: auto;
            width: 100%;
            margin-bottom: 40px;
        }
        .membership-table {
            width: 100%;
            background-color: #333;
            color: #e0e0e0;
            border-collapse: collapse;
            font-size: 14px;
            table-layout: auto;
        }
        .membership-table th {
            background-color: #ff4444;
            color: #fff;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }
        .membership-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #444;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .membership-table tr:hover {
            background-color: #3a3a3a;
        }
        .no-members {
            text-align: center;
            padding: 20px;
            color: #ff4444;
        }

        /* Action Buttons */
        .action-btn {
            background-color: #ff4444;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 50%;
            cursor: pointer;
            margin: 0 3px;
            font-size: 14px;
            transition: background-color 0.3s;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .action-btn:hover {
            background-color: #cc0000;
        }
        .renew-btn {
            border-radius: 20px;
            padding: 6px 10px;
            width: auto;
            font-size: 12px;
        }

        /* Membership List Report Table */
        .report-container {
            margin-top: 40px;
        }
        .report-filter-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .report-search-input {
            width: 100%;
            max-width: 400px;
            padding: 10px 15px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #333;
            color: #e0e0e0;
            font-size: 14px;
        }
        .report-search-input:focus {
            border-color: #ff4444;
            outline: none;
        }
        .report-table {
            width: 100%;
            background-color: #333;
            color: #e0e0e0;
            border-collapse: collapse;
            font-size: 14px;
        }
        .report-table th {
            background-color: #e21d1d;
            color: #fff;
            padding: 10px;
            text-align: left;
        }
        .report-table td {
            padding: 10px;
            border-bottom: 1px solid #444;
        }
        .print-btn {
            background-color: #ff4444;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            display: block;
            margin: 15px auto;
        }
        .print-btn:hover {
            background-color: #cc0000;
        }

        /* Modal */
        .modal-content {
            background-color: #2c2c2c;
            color: #e0e0e0;
            border-radius: 8px;
        }
        .modal-header, .modal-footer {
            border-color: #444;
        }
        .modal-title {
            font-family: 'Anton', sans-serif;
            font-size: 20px;
        }
        .payment-history-table {
            width: 100%;
            background-color: #333;
            color: #e0e0e0;
            margin-top: 20px;
            font-size: 14px;
        }
        .payment-history-table th {
            background-color: #e21d1d;
            color: #fff;
            padding: 10px;
        }
        .payment-history-table td {
            padding: 10px;
            border-bottom: 1px solid #444;
        }

        /* Messages */
        .error-message {
            color: #ff4444;
            background-color: rgba(255, 68, 68, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success-message {
            color: #28a745;
            padding: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Print Styles */
        @media print {
            .sidebar-nav, .admin-header, .search-container, .membership-table, .action-btn, .print-btn, .modal, .report-filter-container {
                display: none !important;
            }
            .report-container {
                display: block !important;
                margin: 0;
                padding: 0;
            }
            .report-table {
                width: 100%;
                border-collapse: collapse;
            }
            .report-table th, .report-table td {
                border: 1px solid #000;
                padding: 8px;
                color: #000;
                background-color: #fff;
            }
            .form-container {
                background-color: #fff;
                box-shadow: none;
                padding: 0;
            }
            .main-content {
                padding: 0;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-container {
                padding: 15px;
            }
            .search-container, .report-filter-container {
                flex-direction: column;
                gap: 15px;
            }
            .search-input, .search-button, .report-search-input {
                width: 100%;
                max-width: none;
            }
            .membership-table th, .membership-table td, .report-table th, .report-table td {
                font-size: 12px;
                padding: 8px;
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
                        <?php if ($user_role === 'staff'): ?>
                            <a href="dashboard.php" class="nav-item"><i class="fas fa-home nav-icon"></i><span>Home</span></a>
                            <a href="ListMember.php" class="nav-item"><i class="fas fa-user-plus nav-icon"></i><span>Add Member</span></a>
                            <a href="membership.php" class="nav-item nav-item--active"><i class="fas fa-id-card nav-icon"></i><span>Membership</span></a>
                            <a href="attendance.php" class="nav-item"><i class="fas fa-calendar-check nav-icon"></i><span>Attendance</span></a>
                        <?php endif; ?>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="dashboard.php" class="nav-item"><i class="fas fa-home nav-icon"></i><span>Home</span></a>
                            <a href="ListMember.php" class="nav-item"><i class="fas fa-user-plus nav-icon"></i><span>Add Member</span></a>
                            <a href="attendance.php" class="nav-item"><i class="fas fa-calendar-check nav-icon"></i><span>Attendance</span></a>
                            <a href="membership.php" class="nav-item nav-item--active"><i class="fas fa-id-card nav-icon"></i><span>Membership</span></a>
                            <a href="logs.php" class="nav-item"><i class="fas fa-file-alt nav-icon"></i><span>Logs</span></a>
                            <a href="register.php" class="nav-item"><i class="fa-solid fa-circle-user nav-icon"></i><span>Employee Create Account</span></a>
                            <a href="employees.php" class="nav-item"><i class="fa-solid fa-user-tie nav-icon"></i><span>Employees</span></a>
                            <a href="pricing.php" class="nav-item"><i class="fas fa-dollar-sign nav-icon"></i><span>Pricing</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <main class="main-content" id="mainContent">
                <div class="form-container">
                    <h1>Membership</h1>
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success || (isset($_GET['success']) && $_GET['success'] == 1)): ?>
                        <div class="success-message"><?php echo htmlspecialchars($success ?: 'Operation completed successfully!'); ?></div>
                    <?php endif; ?>

                    <div class="search-container">
                        <input type="text" class="search-input" id="searchInput" placeholder="Search members...">
                        <button class="search-button" id="searchButton">Search</button>
                    </div>
                    <div class="table-container">
                        <table class="membership-table">
                            <thead>
                                <tr>
                                    <th>Membership ID</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Birth Date</th>
                                    <th>Plan</th>
                                    <th>Start Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($members) && is_array($members)): ?>
                                    <?php foreach ($members as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['membership_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['first_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['last_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['birth_date'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['plan'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['start_date'] ?? ''); ?></td>
                                            <td>₱<?php echo htmlspecialchars(number_format($row['amount'] ?? 0, 2)); ?></td>
                                            <td><?php echo htmlspecialchars($row['status'] ?? 'Active'); ?></td>
                                            <td>
                                                <a href="edit_member.php?id=<?php echo htmlspecialchars($row['membership_id']); ?>" class="action-btn"><i class="fas fa-edit"></i></a>
                                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this member?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($row['membership_id']); ?>">
                                                    <button type="submit" class="action-btn"><i class="fas fa-trash"></i></button>
                                                </form>
                                                <button class="action-btn renew-btn" data-member-id="<?php echo $row['membership_id']; ?>" data-bs-toggle="modal" data-bs-target="#renewModal">Renew</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="12" class="no-members">No Membership Yet</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Membership List Report Table -->
                    <div class="report-container">
                        <h2>Membership List Report</h2>
                        <div class="report-filter-container">
                            <input type="text" class="report-search-input" id="reportSearchInput" placeholder="Filter report...">
                        </div>
                        <div class="table-container">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Membership ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Registration Date</th>
                                    </tr>
                                </thead>
                                <tbody id="reportTableBody">
                                    <?php if (!empty($members) && is_array($members)): ?>
                                        <?php foreach ($members as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['membership_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['start_date'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="no-members">No Members Registered Yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button class="print-btn" onclick="printReport()">Print Reports</button>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Renew Plan Modal -->
    <div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="renewModalLabel">Renew Membership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="renew_id" id="renewMemberId">
                        <div class="mb-3">
                            <label for="renewPlan" class="form-label">Select Plan</label>
                            <select name="plan" id="renewPlan" class="form-select" required>
                                <option value="" disabled selected>Select a plan</option>
                                <option value="Per Session">Per Session (₱<?php echo number_format($prices['Per Session'], 2); ?>)</option>
                                <option value="Monthly">Monthly (₱<?php echo number_format($prices['Monthly'], 2); ?>)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="renewStartDate" class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="renewStartDate" class="form-control" value="<?php echo $defaultDateAdded; ?>" required>
                        </div>
                        <h6>Payment History</h6>
                        <div id="paymentHistoryContainer">
                            <table class="payment-history-table">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentHistoryBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Renew</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js" integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js" integrity="sha384-fbbOQedDUMZZ5KreZpsbe1LCZPVmfTnH7ois6mU1QK+m14rQ1l2bGBq41eYeM/fS" crossorigin="anonymous"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menuToggle');
        const mainContent = document.getElementById('mainContent');

        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('shifted');
        });

        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('shifted');
            }
        });

        // Search functionality for renewal table
        document.getElementById('searchButton').addEventListener('click', searchRenewalTable);
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') searchRenewalTable();
        });

        function searchRenewalTable() {
            const filter = document.getElementById('searchInput').value.toLowerCase();
            const tr = document.querySelectorAll('.membership-table tbody tr:not(.no-members)');
            let visibleRows = false;

            tr.forEach(row => {
                const text = Array.from(row.cells).slice(0, -1).map(cell => cell.textContent.toLowerCase()).join(' ');
                row.style.display = text.includes(filter) ? '' : 'none';
                if (text.includes(filter)) {
                    visibleRows = true;
                }
            });

            const noMembersCell = document.querySelector('.membership-table .no-members');
            if (noMembersCell) {
                noMembersCell.parentElement.style.display = tr.length === 0 || !visibleRows ? '' : 'none';
            }
        }

        // Filter functionality for report table
        document.getElementById('reportSearchInput').addEventListener('input', filterReportTable);

        function filterReportTable() {
            const filter = document.getElementById('reportSearchInput').value.toLowerCase();
            const tr = document.querySelectorAll('.report-table tbody tr:not(.no-members)');
            let visibleRows = false;

            tr.forEach(row => {
                const text = Array.from(row.cells).map(cell => cell.textContent.toLowerCase()).join(' ');
                row.style.display = text.includes(filter) ? '' : 'none';
                if (text.includes(filter)) {
                    visibleRows = true;
                }
            });

            const noMembersCell = document.querySelector('.report-table .no-members');
            if (noMembersCell) {
                noMembersCell.parentElement.style.display = tr.length === 0 || !visibleRows ? '' : 'none';
            }
        }

        // Renew Modal Payment History
        document.querySelectorAll('.renew-btn').forEach(button => {
            button.addEventListener('click', function() {
                const memberId = this.getAttribute('data-member-id');
                document.getElementById('renewMemberId').value = memberId;

                fetch(`get_payment_log.php?member_id=${memberId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        const paymentHistoryBody = document.getElementById('paymentHistoryBody');
                        paymentHistoryBody.innerHTML = '';
                        if (data.error) {
                            paymentHistoryBody.innerHTML = `<tr><td colspan="3" class="error-message">${data.error}</td></tr>`;
                        } else if (!Array.isArray(data) || data.length === 0) {
                            paymentHistoryBody.innerHTML = '<tr><td colspan="3">No payment records found.</td></tr>';
                        } else {
                            data.forEach(payment => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>₱${Number(payment.amount).toFixed(2)}</td>
                                    <td>${payment.payment_date || 'N/A'}</td>
                                    <td>${payment.created_by || 'N/A'}</td>
                                `;
                                paymentHistoryBody.appendChild(row);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fetch Error:', error);
                        document.getElementById('paymentHistoryBody').innerHTML = `<tr><td colspan="3" class="error-message">Error loading payment log: ${error.message}</td></tr>`;
                    });
            });
        });

        // Print Reports Function
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>