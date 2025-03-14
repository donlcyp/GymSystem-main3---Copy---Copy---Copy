<?php
session_start();
require_once 'database/GymDatabaseConnector.php';

try {
    // Ensure a session ID exists
    if (empty(session_id())) {
        throw new Exception("Session not started.");
    }
    echo "Session ID: " . session_id() . "<br>";

    // Instantiate the database connector
    $db = GymDatabaseConnector::getInstance();

    // Test logging
    $userid = "test_user";
    $action = "Test log entry from script";
    $result = $db->addLog($userid, $action);

    echo "Log Result: " . $result . "<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Check the logs table directly
try {
    $stmt = $db->getConnection()->query("SELECT * FROM logs");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Logs Table Contents:<br>";
    if (empty($logs)) {
        echo "No entries found.<br>";
    } else {
        foreach ($logs as $log) {
            echo "ID: {$log['id']}, Session: {$log['session_id']}, User: {$log['userid']}, Action: {$log['action']}, Time: {$log['timestamp']}<br>";
        }
    }
} catch (Exception $e) {
    echo "Error querying logs: " . $e->getMessage() . "<br>";
}
?>