<?php
/**
 * Get Upcoming Tasks
 * Returns upcoming tasks for farmer dashboard
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
    // Get upcoming tasks
    $tasks = $db->resultSet(
        "SELECT 
            id,
            title, 
            description,
            due_date, 
            status,
            priority
        FROM tasks 
        WHERE user_id = ? 
            AND status != 'completed'
            AND (due_date IS NOT NULL AND due_date != '0000-00-00')
        ORDER BY due_date ASC 
        LIMIT 5",
        [$userId]
    );
    
    // Clean up dates
    foreach ($tasks as &$task) {
        if (empty($task['due_date']) || $task['due_date'] == '0000-00-00') {
            $task['due_date'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'count' => count($tasks),
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch tasks',
        'message' => $e->getMessage()
    ]);
}
