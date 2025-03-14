<?php
session_start();
require_once '../database/GymDatabaseConnector.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = GymDatabaseConnector::getInstance();
    $membershipId = filter_input(INPUT_GET, 'membership_id', FILTER_SANITIZE_NUMBER_INT);

    if (!$membershipId) {
        echo json_encode(['error' => 'Membership ID is required']);
        exit();
    }

    $paymentLogs = $db->getPaymentLogs($membershipId);
    echo json_encode($paymentLogs);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error fetching payment logs: ' . $e->getMessage()]);
}
exit();