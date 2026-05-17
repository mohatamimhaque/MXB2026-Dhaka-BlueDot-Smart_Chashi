<?php
/**
 * Get Recent Activities
 * Returns recent activities for farmer dashboard
 */

header('Content-Type: application/json');

// Include necessary files
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = new Database();

try {
    // Get recent activities (crops and posts combined)
    $activities = $db->resultSet("
        SELECT 
            'crop' as type, 
            crop_name as title, 
            planting_date as date, 
            status 
        FROM crop_data 
        WHERE farmer_id = ? 
            AND planting_date IS NOT NULL 
            AND planting_date != '0000-00-00'
        UNION ALL 
        SELECT 
            'post' as type, 
            title, 
            created_at as date, 
            'published' as status 
        FROM community_posts 
        WHERE user_id = ? 
            AND created_at IS NOT NULL
        ORDER BY date DESC 
        LIMIT 5
    ", [$userId, $userId]);
    
    // Clean up dates
    foreach ($activities as &$activity) {
        if (empty($activity['date']) || $activity['date'] == '0000-00-00') {
            $activity['date'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'count' => count($activities),
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch activities',
        'message' => $e->getMessage()
    ]);
}
