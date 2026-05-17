<?php
/**
 * SmartChashi - Get My Report Detail AJAX Handler
 * Returns details of a specific farmer's disease report
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'farmer') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$reportId = intval($_GET['id'] ?? 0);
if (!$reportId) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

$db = new Database();

try {
    // Get report (only if belongs to current user)
    $query = "SELECT dr.*, c.crop_name, DATE_FORMAT(dr.detected_date, '%M %d, %Y at %h:%i %p') as formatted_date
              FROM disease_reports dr
              LEFT JOIN crop_data c ON dr.crop_id = c.crop_id
              WHERE dr.detection_id = ? AND dr.user_id = ?";
    
    $report = $db->single($query, [$reportId, $currentUser['user_id']]);
    
    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }
    
    // Check if response table exists and get response
    $response = null;
    $tableCheck = $db->single("SHOW TABLES LIKE 'disease_report_responses'");
    if ($tableCheck) {
        $responseQuery = "SELECT drr.*, u.first_name, u.last_name,
                          CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as officer_name,
                          DATE_FORMAT(drr.created_at, '%M %d, %Y') as formatted_date
                          FROM disease_report_responses drr
                          JOIN users u ON drr.officer_id = u.user_id
                          WHERE drr.report_id = ?
                          ORDER BY drr.created_at DESC
                          LIMIT 1";
        $response = $db->single($responseQuery, [$reportId]);
    }
    
    echo json_encode([
        'success' => true,
        'report' => $report,
        'response' => $response
    ]);
    
} catch (Exception $e) {
    error_log('Get report detail error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => __('error_occurred')]);
}
