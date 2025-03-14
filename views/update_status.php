<?php
require_once 'database/GymDatabaseConnector.php';

try {
    $db = GymDatabaseConnector::getInstance();
    $pdo = $db->getConnection();

    $stmt = $pdo->query("UPDATE membership SET status = 'inactive' WHERE end_date < CURDATE() AND status = 'active'");
    echo "Status update completed.";
} catch (Exception $e) {
    error_log("Error updating membership status: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}
?>