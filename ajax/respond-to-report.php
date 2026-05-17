<?php
/**
 * SmartChashi - Respond to Report AJAX Handler
 * Allows officers to respond to farmer disease reports
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Authentication check
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => __('unauthorized')]);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer' && $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => __('access_denied')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reportId = intval($_POST['report_id'] ?? 0);
$status = $_POST['status'] ?? 'reviewed';
$message = trim($_POST['message'] ?? '');
$action = trim($_POST['action'] ?? '');
$notifyFarmer = isset($_POST['notify_farmer']) && $_POST['notify_farmer'] == '1';

// Validate input
if (!$reportId) {
    echo json_encode(['success' => false, 'message' => __('invalid_report_id')]);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => __('response_message_required')]);
    exit;
}

// Validate status
$allowedStatus = ['pending', 'reviewed', 'resolved'];
if (!in_array($status, $allowedStatus)) {
    $status = 'reviewed';
}

$db = new Database();

try {
    $db->getDbh()->beginTransaction();
    
    // Get the report
    $report = $db->single("
        SELECT dr.*, u.user_id as farmer_id, u.first_name, u.email
        FROM disease_reports dr
        JOIN users u ON dr.user_id = u.user_id
        WHERE dr.detection_id = ?
    ", [$reportId]);
    
    if (!$report) {
        throw new Exception(__('report_not_found'));
    }
    
    // Insert response
    $db->query("
        INSERT INTO disease_report_responses 
        (report_id, officer_id, message, recommended_action, status, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ", [
        $reportId,
        $currentUser['user_id'],
        $message,
        $action,
        $status
    ]);
    
    // Update report status
    $db->query("
        UPDATE disease_reports 
        SET status = ?, updated_at = NOW()
        WHERE detection_id = ?
    ", [$status, $reportId]);
    
    // Send notification to farmer if requested
    if ($notifyFarmer && $report['farmer_id']) {
        $notificationTitle = $status === 'resolved' 
            ? __('disease_report_resolved') 
            : __('new_response_to_report');
        
        $notificationMessage = sprintf(
            __('officer_responded_to_report'),
            $currentUser['first_name'],
            $report['disease_name'] ?? __('unknown')
        );
        
        // Insert notification
        $db->query("
            INSERT INTO notifications 
            (user_id, type, title, message, link, created_at)
            VALUES (?, 'report_response', ?, ?, ?, NOW())
        ", [
            $report['farmer_id'],
            $notificationTitle,
            $notificationMessage,
            'disease-reports?id=' . $reportId
        ]);
    }
    
    $db->getDbh()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => __('response_sent_successfully')
    ]);
    
} catch (Exception $e) {
    $db->getDbh()->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
