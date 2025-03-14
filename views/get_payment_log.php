<?php
require_once '../database/GymDatabaseConnector.php';

header('Content-Type: application/json');

try {
    $db = GymDatabaseConnector::getInstance();
    $memberId = filter_input(INPUT_GET, 'member_id', FILTER_VALIDATE_INT);

    if ($memberId === false || $memberId <= 0) {
        echo json_encode(['error' => 'Invalid member ID']);
        exit;
    }

    $payments = $db->getPaymentLogByMemberId($memberId);
    echo json_encode($payments); // Return empty array or data
} catch (Exception $e) {
    error_log("Error in get_payment_log.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error fetching payment log: ' . $e->getMessage()]);
}
?>