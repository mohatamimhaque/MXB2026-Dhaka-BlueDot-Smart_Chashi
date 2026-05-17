<?php
/**
 * SmartChashi - Get Report Details AJAX Handler
 * Returns detailed information about a disease report
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Authentication check
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer' && $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$reportId = intval($_GET['id'] ?? 0);

if (!$reportId) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

$db = new Database();

// Get report details
$report = $db->single("
    SELECT dr.*, 
           u.first_name, u.last_name, u.email, u.phone, u.profile_picture,
           c.crop_name, c.crop_type,
           fp.region, fp.farm_size, fp.experience_years, fp.address,
           DATE_FORMAT(dr.created_at, '%M %d, %Y at %h:%i %p') as formatted_date
    FROM disease_reports dr
    JOIN users u ON dr.user_id = u.user_id
    LEFT JOIN crop_data c ON dr.crop_id = c.crop_id
    LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id
    WHERE dr.detection_id = ?
", [$reportId]);

if (!$report) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

// Get previous responses
$responses = $db->resultSet("
    SELECT drr.*, 
           CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as officer_name,
           DATE_FORMAT(drr.created_at, '%M %d, %Y at %h:%i %p') as created_at
    FROM disease_report_responses drr
    LEFT JOIN users u ON drr.officer_id = u.user_id
    WHERE drr.report_id = ?
    ORDER BY drr.created_at DESC
", [$reportId]) ?: [];

$report['responses'] = $responses;

echo json_encode([
    'success' => true,
    'report' => $report
]);
