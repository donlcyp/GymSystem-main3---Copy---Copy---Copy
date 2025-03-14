<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? null;
if (!$user_role) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';
$defaultDateAdded = date('Y-m-d');
$prices = [];

try {
    $db = GymDatabaseConnector::getInstance();
    $prices['Per Session'] = floatval($db->getPrice('per_session')) ?: 0.00;
    $prices['Monthly'] = floatval($db->getPrice('monthly')) ?: 0.00;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
        $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
        $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phoneNumber = filter_input(INPUT_POST, 'phoneNumber', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $birthDate = filter_input(INPUT_POST, 'birthDate', FILTER_SANITIZE_STRING);
        $plan = filter_input(INPUT_POST, 'plan', FILTER_SANITIZE_STRING);
        $startDate = filter_input(INPUT_POST, 'dateAdded', FILTER_SANITIZE_STRING) ?: $defaultDateAdded;

        if (empty($firstName) || empty($lastName) || empty($email) || empty($phoneNumber) || 
            empty($address) || empty($birthDate) || empty($plan) || empty($startDate)) {
            $error = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!in_array($plan, ['Per Session', 'Monthly'])) {
            $error = "Invalid plan selected";
        } else {
            $planToType = ['Per Session' => 'per_session', 'Monthly' => 'monthly'];
            $pricingType = $planToType[$plan];
            $amount = $db->getPrice($pricingType);
            if ($amount == 0.00) {
                $error = "Price not found for plan: $plan.";
            } else {
                $startDateTime = new DateTime($startDate);
                $endDateTime = clone $startDateTime;
                if ($plan === 'Per Session') {
                    $endDateTime->modify('+1 day');
                } elseif ($plan === 'Monthly') {
                    $endDateTime->modify('+30 days');
                }
                $endDate = $endDateTime->format('Y-m-d H:i:s');

                $memberData = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phoneNumber,
                    'address' => $address,
                    'birth_date' => $birthDate,
                    'plan' => $pricingType,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount' => $amount,
                    'status' => 'active',
                    'created_by' => $_SESSION['username']
                ];

                $result = $db->addMember($memberData);
                if ($result === true || strpos($result, 'success') !== false) {
                    $userid = $_SESSION['username'];
                    $action = "Added member: $firstName $lastName with plan $plan for $amount, ending on $endDate";
                    $db->addLog($userid, $action);
                    header("Location: ListMember.php?success=1");
                    exit();
                } else {
                    $error = "Error adding member: " . var_export($result, true);
                }
            }
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Membership - He-Man Fitness Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <style>
        /* CSS remains unchanged */
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
        .success-message {
            color: #ff4444;
            text-align: center;
            margin-top: 15px;
        }
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
            font-size: 18px;
        }
        .modal-body {
            padding: 20px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }
        .modal-footer .btn-primary {
            background-color: #ff4444;
            border: none;
        }
        .modal-footer .btn-secondary {
            background-color: #666;
            border: none;
        }
        .modal-footer .btn-primary:hover {
            background-color: #cc0000;
        }
        .modal-footer .btn-secondary:hover {
            background-color: #555;
        }
        .error-message {
            color: #ff4444;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: rgba(255, 68, 68, 0.1);
            border-radius: 4px;
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
                    <img src="https://cdn.builder.io/api/v1/image/assets/d47b9c64343c4fb28708dd8b67fd1cce/487c8045bbe2fa69683c59be11df183c65f6fc7aac898ce424235e21c4b67556?placeholderIfAbsent=true" alt="Dashboard Logo" class="sidebar-logo" />
                </div>
                <div class="sidebar-content">
                    <div class="nav-menu">
                        <?php if ($user_role === 'staff'): ?>
                            <a href="dashboard.php" class="nav-item"><i class="fas fa-home nav-icon"></i><span>Home</span></a>
                            <a href="ListMember.php" class="nav-item nav-item--active"><i class="fas fa-user-plus nav-icon"></i><span>Add Member</span></a>
                            <a href="attendance.php" class="nav-item"><i class="fas fa-calendar-check nav-icon"></i><span>Attendance</span></a>
                            <a href="membership.php" class="nav-item"><i class="fas fa-id-card nav-icon"></i><span>Membership</span></a>
                        <?php endif; ?>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="dashboard.php" class="nav-item"><i class="fas fa-home nav-icon"></i><span>Home</span></a>
                            <a href="ListMember.php" class="nav-item nav-item--active"><i class="fas fa-user-plus nav-icon"></i><span>Add Member</span></a>
                            <a href="attendance.php" class="nav-item"><i class="fas fa-calendar-check nav-icon"></i><span>Attendance</span></a>
                            <a href="membership.php" class="nav-item"><i class="fas fa-id-card nav-icon"></i><span>Membership</span></a>
                            <a href="logs.php" class="nav-item"><i class="fas fa-file-alt nav-icon"></i><span>Logs</span></a>
                            <a href="register.php" class="nav-item"><i class="fa-solid fa-circle-user"></i><span>Employee Create Account</span></a>
                            <a href="employees.php" class="nav-item"><i class="fa-solid fa-user-tie"></i><span>Employees</span></a>
                            <a href="pricing.php" class="nav-item"><i class="fas fa-dollar-sign nav-icon"></i><span>Pricing</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <main class="main-content" id="mainContent">
                <div class="form-container">
                    <h2>Add Membership</h2>
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="addMemberForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name:</label>
                                <input type="text" id="firstName" name="firstName" required>
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name:</label>
                                <input type="text" id="lastName" name="lastName" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phoneNumber">Phone Number:</label>
                                <input type="tel" id="phoneNumber" name="phoneNumber" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="address">Address:</label>
                                <input type="text" id="address" name="address" required>
                            </div>
                            <div class="form-group">
                                <label for="birthDate">Birth Date:</label>
                                <input type="date" id="birthDate" name="birthDate" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="dateAdded">Start Date:</label>
                                <input type="date" id="dateAdded" name="dateAdded" value="<?php echo htmlspecialchars($defaultDateAdded); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="plan">Plan:</label>
                                <select id="plan" name="plan" required onchange="updateEndDatePreview()">
                                    <option value="" disabled selected>Select a plan</option>
                                    <option value="Per Session">Per Session (₱<?php echo number_format($prices['Per Session'], 2); ?>)</option>
                                    <option value="Monthly">Monthly (₱<?php echo number_format($prices['Monthly'], 2); ?>)</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" id="previewButton">Add Member</button>
                    </form>
                    <?php if (isset($_GET['success'])): ?>
                        <p class="success-message">Member added successfully!</p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Preview Member Details & Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="previewModalBody">
                    <!-- Dynamically populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="confirmSubmit">Confirm</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for confirmation -->
    <form method="POST" action="" id="confirmForm" style="display: none;">
        <input type="hidden" name="confirm" value="1">
        <input type="hidden" name="firstName" id="hiddenFirstName">
        <input type="hidden" name="lastName" id="hiddenLastName">
        <input type="hidden" name="phoneNumber" id="hiddenPhoneNumber">
        <input type="hidden" name="email" id="hiddenEmail">
        <input type="hidden" name="address" id="hiddenAddress">
        <input type="hidden" name="birthDate" id="hiddenBirthDate">
        <input type="hidden" name="dateAdded" id="hiddenDateAdded">
        <input type="hidden" name="plan" id="hiddenPlan">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js" integrity="sha384-oBqDVmMz9ATKxIep9tiCxS/Z9fNfEXiDAYTujMAeBAsjFuCZSmKbSSUnQlmh/jp3" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js" integrity="sha384-fbbOQedDUMZZ5KreZpsbe1LCZPVmfTnH7ois6mU1QK+m14rQ1l2bGBq41eYeM/fS" crossorigin="anonymous"></script>
    <script>
        const prices = <?php echo json_encode($prices, JSON_NUMERIC_CHECK); ?>;

        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('shifted');
        });

        document.getElementById('addMemberForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = {
                firstName: document.getElementById('firstName').value,
                lastName: document.getElementById('lastName').value,
                phoneNumber: document.getElementById('phoneNumber').value,
                email: document.getElementById('email').value,
                address: document.getElementById('address').value,
                birthDate: document.getElementById('birthDate').value,
                dateAdded: document.getElementById('dateAdded').value,
                plan: document.getElementById('plan').value
            };

            // Calculate end date for preview
            let endDateText = 'N/A';
            let paymentAmount = 'N/A';
            if (formData.plan && prices[formData.plan] !== undefined) {
                const price = Number(prices[formData.plan]);
                if (!isNaN(price)) {
                    paymentAmount = `₱${price.toFixed(2)}${formData.plan === 'Per Session' ? '/session' : ''}`;
                } else {
                    alert('Invalid price for selected plan.');
                    return;
                }

                const startDate = new Date(formData.dateAdded);
                let endDate = new Date(startDate);
                if (formData.plan === 'Per Session') {
                    endDate.setDate(endDate.getDate() + 1); // 24 hours
                } else if (formData.plan === 'Monthly') {
                    endDate.setDate(endDate.getDate() + 30); // 30 days
                }
                endDateText = endDate.toLocaleString('en-US', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } else {
                alert('Please select a valid plan.');
                return;
            }

            const modalBody = document.getElementById('previewModalBody');
            modalBody.innerHTML = `
                <h6>Member Details:</h6>
                <p><strong>First Name:</strong> ${formData.firstName}</p>
                <p><strong>Last Name:</strong> ${formData.lastName}</p>
                <p><strong>Phone Number:</strong> ${formData.phoneNumber}</p>
                <p><strong>Email:</strong> ${formData.email}</p>
                <p><strong>Address:</strong> ${formData.address}</p>
                <p><strong>Birth Date:</strong> ${formData.birthDate}</p>
                <p><strong>Start Date:</strong> ${formData.dateAdded}</p>
                <p><strong>End Date:</strong> ${endDateText}</p>
                <p><strong>Plan:</strong> ${formData.plan}</p>
                <hr>
                <h6>Payment Procedure:</h6>
                <p><strong>Amount Due:</strong> ${paymentAmount}</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="paymentReceived" required>
                    <label class="form-check-label" for="paymentReceived">Payment Received</label>
                </div>
            `;

            document.getElementById('hiddenFirstName').value = formData.firstName;
            document.getElementById('hiddenLastName').value = formData.lastName;
            document.getElementById('hiddenPhoneNumber').value = formData.phoneNumber;
            document.getElementById('hiddenEmail').value = formData.email;
            document.getElementById('hiddenAddress').value = formData.address;
            document.getElementById('hiddenBirthDate').value = formData.birthDate;
            document.getElementById('hiddenDateAdded').value = formData.dateAdded;
            document.getElementById('hiddenPlan').value = formData.plan;

            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        });

        document.getElementById('confirmSubmit').addEventListener('click', function() {
            const paymentReceived = document.getElementById('paymentReceived');
            if (paymentReceived.checked) {
                document.getElementById('confirmForm').submit();
            } else {
                alert('Please confirm payment has been received.');
            }
        });

        function updateEndDatePreview() {
            const plan = document.getElementById('plan').value;
            const startDateInput = document.getElementById('dateAdded').value;
            if (plan && startDateInput) {
                const startDate = new Date(startDateInput);
                let endDate = new Date(startDate);
                if (plan === 'Per Session') {
                    endDate.setDate(endDate.getDate() + 1);
                } else if (plan === 'Monthly') {
                    endDate.setDate(endDate.getDate() + 30);
                }
                console.log(`Plan: ${plan}, End Date: ${endDate.toLocaleString()}`); // For debugging
            }
        }
    </script>
</body>
</html>