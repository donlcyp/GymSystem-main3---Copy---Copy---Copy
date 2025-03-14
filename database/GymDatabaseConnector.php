<?php
if (!class_exists('GymDatabaseConnector')) {

class GymDatabaseConnector {
    private $host = "localhost";
    private $dbname = "gymtest";
    private $username = "root";
    private $password = "";
    private $conn;
    private static $instance = null;
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];

    private function __construct() {
        error_log("Attempting to connect to the database...");
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4"; 
            error_log("Database connection string: " . $dsn);
            $this->conn = new PDO($dsn, $this->username, $this->password, $this->options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Register an employee using stored procedure
    public function registerEmployee($first_name, $last_name, $username, $users_email, $password, $role, $admin_username) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("CALL RegisterEmployee(?, ?, ?, ?, ?, ?, ?, @p_userid, @p_error_message)");
            $stmt->execute([
                $first_name,
                $last_name,
                $username,
                $users_email,
                $hashedPassword,
                $role,
                $admin_username
            ]);
            $result = $this->conn->query("SELECT @p_userid AS userid, @p_error_message AS error_message")->fetch();
            if ($result['error_message'] !== null) {
                throw new Exception($result['error_message']);
            }
            if ($result['userid'] === null) {
                throw new Exception("Registration failed - no user ID returned");
            }
            return $result['userid'];
        } catch (PDOException $e) {
            throw new Exception("Database error during registration: " . $e->getMessage());
        } catch (Exception $e) {
            throw $e;
        }
    }

    // Register user (backward compatibility)
    public function registerUser($username, $password, $role = 'staff') {
        try {
            $checkStmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $checkStmt->execute([':username' => $username]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Username already exists");
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userid = uniqid();
            $stmt = $this->conn->prepare("
                INSERT INTO users (userid, username, password, role) 
                VALUES (:userid, :username, :password, :role)
            ");
            $result = $stmt->execute([
                ':userid' => $userid,
                ':username' => $username,
                ':password' => $hashedPassword,
                ':role' => $role
            ]);
            return $result ? $userid : false;
        } catch (PDOException $e) {
            throw new Exception("Database error during registration: " . $e->getMessage());
        }
    }

    // Fetch all logs
    public function getAllLogs() {
        try {
            $stmt = $this->conn->prepare("CALL GetAllLogs()");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching logs: " . $e->getMessage());
        }
    }

    // Fetch logs table structure
    public function getLogsTableStructure() {
        try {
            $stmt = $this->conn->query("DESCRIBE logs");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching logs table structure: " . $e->getMessage());
        }
    }

    // Fetch member by ID
    public function getMemberById($membershipId) {
        try {
            $stmt = $this->conn->prepare("CALL GetMemberById(:membership_id)");
            $stmt->bindParam(':membership_id', $membershipId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Get member by ID error: " . $e->getMessage());
        }
    }

    // Fetch all members (simple query version for compatibility)
    public function getAllMembers() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM membership");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Get all members error: " . $e->getMessage());
        }
    }

    // Fetch member details
    public function getMemberDetails($membershipId) {
        try {
            $stmt = $this->conn->prepare("CALL GetMemberById(:membership_id)");
            $stmt->bindParam(':membership_id', $membershipId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Get member details error: " . $e->getMessage());
        }
    }

    // Delete a member
    public function deleteMember($id) {
        try {
            $stmt = $this->conn->prepare("CALL DeleteMember(:membership_id, @p_result)");
            $stmt->bindParam(':membership_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $this->conn->query("SELECT @p_result AS result")->fetch();
            return $result['result'];
        } catch (PDOException $e) {
            throw new Exception("Error deleting member: " . $e->getMessage());
        }
    }

    public function getDashboardStats() {
    try {
        $stats = [];
        $stmt = $this->conn->prepare("CALL GetDashboardStats()");
        $stmt->execute();
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_members'] = $totals['total_members'] ?? 0;
        $stats['total_sales'] = $totals['total_sales'] ?? 0.00;
        $stmt->nextRowset();
        $stats['daily_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->nextRowset();
        $stats['weekly_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->nextRowset();
        $stats['monthly_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->nextRowset();
        $stats['yearly_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->nextRowset();
        $stats['recent_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $stats;
    } catch (PDOException $e) {
        error_log("PDOException in getDashboardStats: " . $e->getMessage());
        throw new Exception("Error fetching dashboard stats: " . $e->getMessage());
    }
}
    // Authenticate user
    public function authenticateUser($username, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                return [
                    'username' => $user['username'],
                    'role' => $user['role']
                ];
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception("Authentication error: " . $e->getMessage());
        }
    }

    // Fetch attendance records
    public function getAttendanceRecords() {
        try {
            $stmt = $this->conn->prepare("CALL GetAttendanceRecords()");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching attendance records: " . $e->getMessage());
        }
    }

    // Fetch active members
    public function getActiveMembers() {
        try {
            $stmt = $this->conn->prepare("CALL GetActiveMembers()");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching active members: " . $e->getMessage());
        }
    }

    // Fetch attendance table structure
    public function getAttendanceTableStructure() {
        try {
            $stmt = $this->conn->query("DESCRIBE attendance");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching attendance table structure: " . $e->getMessage());
        }
    }

    // Fetch all employees
    public function getAllEmployees() {
        try {
            $stmt = $this->conn->prepare("CALL GetAllEmployees()");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching employees: " . $e->getMessage());
        }
    }

    // Fetch employee details
    public function getEmployeeDetails($userid) {
        try {
            $stmt = $this->conn->prepare("CALL GetEmployeeDetails(:userid)");
            $stmt->execute([':userid' => $userid]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Error fetching employee details: " . $e->getMessage());
        }
    }

    // Delete an employee
    public function deleteEmployee($userid) {
        error_log("Attempting to delete employee with ID: " . $userid); // Log the attempt
        try {
            $stmt = $this->conn->prepare("CALL DeleteEmployee(:userid)"); 
            error_log("Executing stored procedure for deletion."); // Log execution
            error_log("Executing stored procedure for deletion."); // Log execution
            $stmt->execute([':userid' => $userid]);
            error_log("Employee deleted successfully: " . $userid); // Log success
            error_log("Employee deleted successfully: " . $userid); // Log success
            return "Employee deleted successfully" . ($stmt->rowCount() > 0 ? "" : " (no rows affected)");
        } catch (PDOException $e) {
            error_log("Error deleting employee: " . $e->getMessage()); // Log the error
            throw new Exception("Error deleting employee. Please try again.");
        }
    }

    // Fetch logs by session
    public function getLogsBySession($sessionId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM logs 
                WHERE session_id = :session_id 
                ORDER BY timestamp DESC
            ");
            $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching logs by session: " . $e->getMessage());
        }
    }

    // Determine dynamic status based on last login
    public function getDynamicStatus($lastLogin) {
        if ($lastLogin === null) {
            return 0; // Inactive if never logged in
        }
        $lastLoginDate = new DateTime($lastLogin);
        $currentDate = new DateTime();
        $interval = $currentDate->diff($lastLoginDate);
        return ($interval->days <= 7) ? 1 : 0; // Active if within 7 days
    }

    // Update price
    public function updatePrice($type, $price) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE pricing 
                SET price = :price 
                WHERE type = :type
            ");
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':type', $type);
            $stmt->execute();
            return "success";
        } catch (PDOException $e) {
            throw new Exception("Error updating price: " . $e->getMessage());
        }
    }

    // Fetch prices
    public function getPrices() {
        try {
            $stmt = $this->conn->prepare("SELECT type, price FROM pricing WHERE type IN ('per_session', 'monthly')");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching prices: " . $e->getMessage());
        }
    }

    // Add a member using stored procedure
    public function addMember($data) {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare("CALL AddMember(:first_name, :last_name, :email, :phone, :address, :birth_date, :plan, :start_date, :end_date, :amount, :status, :created_by)");
            $stmt->execute([
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':address' => $data['address'],
                ':birth_date' => $data['birth_date'],
                ':plan' => $data['plan'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'],
                ':amount' => $data['amount'],
                ':status' => $data['status'],
                ':created_by' => $data['created_by']
            ]);
    
            // Fetch the result from the stored procedure
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (isset($result['success_message'])) {
                return $result['success_message']; // "Member added successfully"
            } elseif (isset($result['error_message'])) {
                return $result['error_message']; // "0"
            }
            return "Unexpected result from stored procedure";
        } catch (PDOException $e) {
            error_log("Error calling AddMember procedure: " . $e->getMessage());
            return "0";
        }
    }

    // Fetch members with formatted plan
    public function getMembers() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    membership_id, 
                    first_name, 
                    last_name, 
                    email, 
                    phone, 
                    address, 
                    birth_date, 
                    CASE 
                        WHEN plan = 'per_session' THEN 'Per Session' 
                        ELSE 'Monthly' 
                    END AS plan, 
                    start_date, 
                    amount, 
                    status 
                FROM membership 
                ORDER BY membership_id DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching members: " . $e->getMessage());
        }
    }

    // Update a member
    public function updateMember($memberData) {
        try {
            $stmt = $this->conn->prepare("CALL UpdateMember(:membership_id, :first_name, :last_name, :email, :phone, :address, :birth_date, @result)");
            $stmt->bindParam(':membership_id', $memberData['membership_id'], PDO::PARAM_INT);
            $stmt->bindParam(':first_name', $memberData['first_name'], PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $memberData['last_name'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $memberData['email'], PDO::PARAM_STR);
            $stmt->bindParam(':phone', $memberData['phone'], PDO::PARAM_STR);
            $stmt->bindParam(':address', $memberData['address'], PDO::PARAM_STR);
            $stmt->bindParam(':birth_date', $memberData['birth_date'], PDO::PARAM_STR);
            $stmt->execute();
            $result = $this->conn->query("SELECT @result AS result")->fetch();
            return $result['result'];
        } catch (PDOException $e) {
            throw new Exception("Error updating member: " . $e->getMessage());
        }
    }

    // Fetch price by plan type
    public function getPrice($planType) {
        try {
            $stmt = $this->conn->prepare("SELECT price FROM pricing WHERE type = ?");
            $stmt->bindParam(1, $planType);
            $stmt->execute();
            $price = $stmt->fetchColumn();
            return $price !== false ? $price : 0.00;
        } catch (PDOException $e) {
            throw new Exception("Error fetching price: " . $e->getMessage());
        }
    }

    // NEW: Fetch payment log for a member
    public function getPaymentLog($membershipId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    amount, 
                    payment_date, 
                    created_by 
                FROM payment_log 
                WHERE membership_id = :membership_id 
                ORDER BY payment_date DESC
            ");
            $stmt->bindParam(':membership_id', $membershipId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching payment log: " . $e->getMessage());
        }
    }

    // NEW: Log a payment
    public function logPayment($paymentData) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO payment_log (
                    membership_id, 
                    amount, 
                    payment_date, 
                    created_by
                ) VALUES (
                    :membership_id, 
                    :amount, 
                    :payment_date, 
                    :created_by
                )
            ");
            $stmt->execute([
                ':membership_id' => $paymentData['membership_id'],
                ':amount' => $paymentData['amount'],
                ':payment_date' => $paymentData['payment_date'],
                ':created_by' => $paymentData['created_by']
            ]);
            return "Payment logged successfully";
        } catch (PDOException $e) {
            throw new Exception("Error logging payment: " . $e->getMessage());
        }
    }

    public function getLogs() {
        // Updated to match actual columns: log_id, session_id, username, action, timestamp
        $stmt = $this->conn->prepare("
            SELECT log_id AS log_id, 
                   session_id AS session_id, 
                   username AS userid, 
                   action AS action, 
                   timestamp AS timestamp 
            FROM logs 
            ORDER BY timestamp DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addLog($username, $action, $sessionId = null) {
        // Updated to include session_id and match actual columns
        $stmt = $this->conn->prepare("
            INSERT INTO logs (session_id, username, action, timestamp) 
            VALUES (:session_id, :username, :action, NOW())
        ");
        $stmt->execute([
            'session_id' => $sessionId, // Nullable, so can be null
            'username' => $username,
            'action' => $action
        ]);
    }

    // Payment Logs (unchanged, assuming it matches your payment_logs table)
    public function getPaymentLogs($membershipId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT payment_log_id, amount, payment_date, created_by 
                FROM payment_log 
                WHERE membership_id = :membership_id
            ");
            $stmt->execute(['membership_id' => $membershipId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Failed to fetch payment logs: " . $e->getMessage());
        }
    }

    public function addPaymentLog($membershipId, $amount, $createdBy) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO payment_log (membership_id, amount, payment_date, created_by) 
                VALUES (:membership_id, :amount, CURDATE(), :created_by)
            ");
            $stmt->execute([
                'membership_id' => $membershipId,
                'amount' => floatval($amount),
                'created_by' => $createdBy
            ]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to add payment log: " . $e->getMessage());
        }
    }

    public function insertPaymentLog($membership_id, $amount) {
        try {
            $sql = "INSERT INTO `payment_log` (`membership_id`, `amount`) VALUES (:membership_id, :amount)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':membership_id' => $membership_id,
                ':amount' => floatval($amount) // Ensure amount is a float
            ]);
            return true; // Success

        } catch (PDOException $e) {
            error_log("Failed to insert payment log: " . $e->getMessage());
            return false; // Failure
        }
    }

    public function renewPlan($memberId, $planType, $amount, $startDate, $endDate) {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("UPDATE membership SET plan = :plan, amount = :amount, start_date = :start_date, end_date = :end_date, status = 'active' WHERE membership_id = :id");
        $stmt->execute([
            ':plan' => $planType,
            ':amount' => $amount,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':id' => $memberId
        ]);
        return "Renewal success";
    }
    
        public function getPaymentLogByMemberId($memberId) {
            try {
                $stmt = $this->conn->prepare("SELECT payment_log_id, membership_id, amount, payment_date, created_by FROM payment_log WHERE membership_id = ? ORDER BY payment_date DESC");
                $stmt->execute([$memberId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Error in getPaymentLogByMemberId: " . $e->getMessage());
                return [];
            }
        }
    

} // End of class GymDatabaseConnector

} // End of class_exists check
