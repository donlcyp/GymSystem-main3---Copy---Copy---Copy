<?php
require_once 'database/GymDatabaseConnector.php';

try {
    $db = GymDatabaseConnector::getInstance();
    $pdo = $db->getConnection();

    // Get membership ID and new status from POST request
    $membershipType = filter_input(INPUT_POST, 'membership_type', FILTER_SANITIZE_STRING);
    error_log("Membership Type: " . $membershipType);
    error_log("Activation Time: " . $activationTime);

    $activationTime = filter_input(INPUT_POST, 'activation_time', FILTER_SANITIZE_STRING);
    $membershipId = filter_input(INPUT_POST, 'membership_id', FILTER_SANITIZE_NUMBER_INT);
    $newStatus = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($membershipId && $newStatus) {
        // Check if the membership should be inactive based on type and time
        $currentTime = time();
        $activeStatus = 'active';
        if ($membershipType === 'Per session' && ($currentTime - strtotime($activationTime)) >= 86400) {
            error_log("Status should be set to inactive for Per session.");

            $stmt = $pdo->prepare("UPDATE membership SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $activeStatus, 'id' => $membershipId]);
        } elseif ($membershipType === 'Monthly' && ($currentTime - strtotime($activationTime)) >= 2592000) {
            error_log("Status should be set to inactive for Monthly.");

            $stmt = $pdo->prepare("UPDATE membership SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $activeStatus, 'id' => $membershipId]);
        } else {
            $stmt = $pdo->prepare("UPDATE membership SET status = :status WHERE id = :id");
        }

        $stmt->execute(['status' => $newStatus, 'id' => $membershipId]); // This will execute if not inactive

        echo "Status update completed.";
    } else {
        echo "Error: Membership ID and status are required.";
    }
} catch (Exception $e) {
    error_log("Error updating membership status: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}
