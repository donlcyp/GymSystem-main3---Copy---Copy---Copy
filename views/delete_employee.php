<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $userid = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING);
    $db = GymDatabaseConnector::getInstance();
    try {
        $db->deleteEmployee($userid);
        header("Location: employees.php?success=Employee deleted successfully");
    } catch (Exception $e) {
        header("Location: employees.php?error=" . urlencode($e->getMessage()));
    }
}
?>