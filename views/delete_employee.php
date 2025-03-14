<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userid = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    $db = GymDatabaseConnector::getInstance();
    error_log("Attempting to delete employee with ID: " . $userid); // Log the attempt
    try {
        $db->deleteEmployee($userid);
        error_log("Employee deleted successfully: " . $userid); // Log success
        header("Location: employees.php?success=Employee deleted successfully");
    } catch (Exception $e) {
        error_log("Error deleting employee: " . $e->getMessage()); // Log the error
        header("Location: employees.php?error=" . urlencode("Error deleting employee. Please try again."));
    }
}
?>
