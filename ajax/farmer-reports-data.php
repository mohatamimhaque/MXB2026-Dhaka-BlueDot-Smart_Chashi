<?php
/**
 * SmartChashi - Farmer Reports Data AJAX Handler
 * Handles all AJAX requests for farmer reports page
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer' && $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = new Database();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'stats':
        getStats($db);
        break;
    case 'charts':
        getCharts($db);
        break;
    case 'reports':
        getReports($db);
        break;
    case 'detail':
        getReportDetail($db);
        break;
    case 'respond':
        respondToReport($db, $currentUser);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getStats($db) {
    try {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'resolved' => 0,
            'critical' => 0,
            'today' => 0,
            'this_week' => 0
        ];
        
        // Check if disease_reports table exists
        $tableCheck = $db->single("SHOW TABLES LIKE 'disease_reports'");
        if (!$tableCheck) {
            echo json_encode(['success' => true, 'stats' => $stats]);
            return;
        }
        
        $result = $db->single("SELECT COUNT(*) as count FROM disease_reports");
        $stats['total'] = $result['count'] ?? 0;
        
        // Status: detected, treating = pending; cured, failed = resolved
        $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE status IN ('detected', 'treating') OR status IS NULL");
        $stats['pending'] = $result['count'] ?? 0;
        
        $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE status IN ('cured', 'failed')");
        $stats['resolved'] = $result['count'] ?? 0;
        
        // Critical = high severity
        $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE severity = 'high'");
        $stats['critical'] = $result['count'] ?? 0;
        
        $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE DATE(detected_date) = CURDATE()");
        $stats['today'] = $result['count'] ?? 0;
        
        $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE detected_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['this_week'] = $result['count'] ?? 0;
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'stats' => ['total' => 0, 'pending' => 0, 'resolved' => 0, 'critical' => 0, 'today' => 0, 'this_week' => 0], 'error' => $e->getMessage()]);
    }
}

function getCharts($db) {
    try {
        $data = [
            'disease' => [],
            'trend' => [],
            'regional' => [],
            'severity' => []
        ];
        
        // Check if table exists
        $tableCheck = $db->single("SHOW TABLES LIKE 'disease_reports'");
        if (!$tableCheck) {
            echo json_encode(['success' => true] + $data);
            return;
        }
        
        // Disease distribution
        $result = $db->resultSet("SELECT disease_name, COUNT(*) as count FROM disease_reports WHERE disease_name IS NOT NULL AND disease_name != '' GROUP BY disease_name ORDER BY count DESC LIMIT 10");
        $data['disease'] = $result ?: [];
        
        // Trend (last 30 days) - use detected_date
        $result = $db->resultSet("SELECT DATE(detected_date) as date, COUNT(*) as count FROM disease_reports WHERE detected_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(detected_date) ORDER BY date");
        $data['trend'] = $result ?: [];
        
        // Regional distribution - check if farmer_profiles exists
        $tableCheck = $db->single("SHOW TABLES LIKE 'farmer_profiles'");
        if ($tableCheck) {
            $result = $db->resultSet("SELECT fp.region, COUNT(*) as count FROM disease_reports dr LEFT JOIN farmer_profiles fp ON dr.user_id = fp.user_id WHERE fp.region IS NOT NULL AND fp.region != '' GROUP BY fp.region ORDER BY count DESC LIMIT 8");
            $data['regional'] = $result ?: [];
        }
        
        // Severity distribution
        $result = $db->resultSet("SELECT COALESCE(severity, 'low') as severity, COUNT(*) as count FROM disease_reports GROUP BY severity");
        $data['severity'] = $result ?: [];
        
        echo json_encode(['success' => true] + $data);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'disease' => [], 'trend' => [], 'regional' => [], 'severity' => [], 'error' => $e->getMessage()]);
    }
}

function getReports($db) {
    try {
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;
        
        $search = trim($_GET['search'] ?? '');
        $region = $_GET['region'] ?? 'all';
        $severity = $_GET['severity'] ?? 'all';
        $status = $_GET['status'] ?? 'all';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        // Check if table exists
        $tableCheck = $db->single("SHOW TABLES LIKE 'disease_reports'");
        if (!$tableCheck) {
            echo json_encode(['success' => true, 'reports' => [], 'total' => 0, 'page' => 1, 'totalPages' => 0]);
            return;
        }
        
        // Check what columns exist
        $hasStatus = false;
        $hasSeverity = false;
        $hasFarmerProfiles = false;
        $hasCropData = false;
        
        $columns = $db->resultSet("SHOW COLUMNS FROM disease_reports");
        foreach ($columns as $col) {
            if ($col['Field'] === 'status') $hasStatus = true;
            if ($col['Field'] === 'severity') $hasSeverity = true;
        }
        
        $tableCheck = $db->single("SHOW TABLES LIKE 'farmer_profiles'");
        $hasFarmerProfiles = $tableCheck ? true : false;
        
        $tableCheck = $db->single("SHOW TABLES LIKE 'crop_data'");
        $hasCropData = $tableCheck ? true : false;
        
        // Build query dynamically
        $select = "SELECT dr.*, u.first_name, u.last_name, u.email, u.phone";
        $from = " FROM disease_reports dr JOIN users u ON dr.user_id = u.user_id";
        $where = " WHERE 1=1";
        $params = [];
        
        if ($hasCropData) {
            $select .= ", c.crop_name";
            $from .= " LEFT JOIN crop_data c ON dr.crop_id = c.crop_id";
        }
        
        if ($hasFarmerProfiles) {
            $select .= ", fp.region";
            $from .= " LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id";
            
            if ($region !== 'all') {
                $where .= " AND fp.region = ?";
                $params[] = $region;
            }
        }
        
        if ($hasSeverity && $severity !== 'all') {
            $where .= " AND dr.severity = ?";
            $params[] = $severity;
        }
        
        // Status mapping: pending = detected/treating, resolved = cured/failed
        if ($hasStatus && $status !== 'all') {
            if ($status === 'pending') {
                $where .= " AND dr.status IN ('detected', 'treating')";
            } elseif ($status === 'resolved') {
                $where .= " AND dr.status IN ('cured', 'failed')";
            } elseif ($status === 'reviewed') {
                $where .= " AND dr.status = 'treating'";
            } else {
                $where .= " AND dr.status = ?";
                $params[] = $status;
            }
        }
        
        if (!empty($dateFrom)) {
            $where .= " AND DATE(dr.detected_date) >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $where .= " AND DATE(dr.detected_date) <= ?";
            $params[] = $dateTo;
        }
        
        if (!empty($search)) {
            $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR dr.disease_name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Count total
        $countQuery = "SELECT COUNT(*) as total" . $from . $where;
        $countResult = $db->single($countQuery, $params);
        $total = $countResult['total'] ?? 0;
        $totalPages = ceil($total / $perPage);
        
        // Get reports with formatted date
        $select .= ", DATE_FORMAT(dr.detected_date, '%M %d, %Y') as formatted_date";
        $query = $select . $from . $where . " ORDER BY dr.detected_date DESC LIMIT $perPage OFFSET $offset";
        $reports = $db->resultSet($query, $params) ?: [];
        
        echo json_encode([
            'success' => true,
            'reports' => $reports,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'reports' => [], 'total' => 0, 'page' => 1, 'totalPages' => 0]);
    }
}

function getReportDetail($db) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
        return;
    }
    
    try {
        // Check what tables/columns exist
        $hasFarmerProfiles = $db->single("SHOW TABLES LIKE 'farmer_profiles'") ? true : false;
        $hasCropData = $db->single("SHOW TABLES LIKE 'crop_data'") ? true : false;
        
        $select = "SELECT dr.*, u.first_name, u.last_name, u.email, u.phone, DATE_FORMAT(dr.detected_date, '%M %d, %Y at %h:%i %p') as formatted_date";
        $from = " FROM disease_reports dr JOIN users u ON dr.user_id = u.user_id";
        
        if ($hasCropData) {
            $select .= ", c.crop_name";
            $from .= " LEFT JOIN crop_data c ON dr.crop_id = c.crop_id";
        }
        
        if ($hasFarmerProfiles) {
            $select .= ", fp.region";
            $from .= " LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id";
        }
        
        $query = $select . $from . " WHERE dr.detection_id = ?";
        $report = $db->single($query, [$id]);
        
        if (!$report) {
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'report' => $report]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function respondToReport($db, $currentUser) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        return;
    }
    
    $reportId = intval($_POST['report_id'] ?? 0);
    $status = $_POST['status'] ?? 'reviewed';
    $message = trim($_POST['message'] ?? '');
    
    if (!$reportId || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        return;
    }
    
    try {
        // Map status values: reviewed -> treating, resolved -> cured
        $dbStatus = $status;
        if ($status === 'reviewed') $dbStatus = 'treating';
        if ($status === 'resolved') $dbStatus = 'cured';
        
        // Update the report status using proper method
        $db->query("UPDATE disease_reports SET status = ? WHERE detection_id = ?");
        $db->bind(1, $dbStatus);
        $db->bind(2, $reportId);
        $db->execute();
        
        // Create responses table if not exists
        $db->query("CREATE TABLE IF NOT EXISTS `disease_report_responses` (
            `response_id` int(11) NOT NULL AUTO_INCREMENT,
            `report_id` int(11) NOT NULL,
            `officer_id` int(11) NOT NULL,
            `message` text NOT NULL,
            `recommended_action` text DEFAULT NULL,
            `status` varchar(20) DEFAULT 'reviewed',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`response_id`),
            KEY `idx_report_id` (`report_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->execute();
        
        // Insert the response using proper method
        $action = trim($_POST['action'] ?? '');
        $db->query("INSERT INTO disease_report_responses (report_id, officer_id, message, recommended_action, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $db->bind(1, $reportId);
        $db->bind(2, $currentUser['user_id']);
        $db->bind(3, $message);
        $db->bind(4, $action);
        $db->bind(5, $status);
        $db->execute();
        
        echo json_encode(['success' => true, 'message' => 'Response sent successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
