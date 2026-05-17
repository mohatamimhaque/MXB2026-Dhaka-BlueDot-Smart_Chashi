<?php
/**
 * SmartChashi - Export Reports AJAX Handler
 * Exports disease reports in CSV, Excel, or PDF format
 */

require_once __DIR__ . '/../config/config.php';

// Authentication check
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'officer' && $currentUser['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$format = $_GET['export'] ?? 'csv';
$db = new Database();

// Build query with filters (same as main page)
$region = $_GET['region'] ?? 'all';
$reportType = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$cropFilter = $_GET['crop'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

$query = "SELECT dr.*, 
          CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) as farmer_name,
          u.phone, u.email,
          c.crop_name,
          fp.region,
          DATE_FORMAT(dr.created_at, '%Y-%m-%d %H:%i:%s') as report_date
          FROM disease_reports dr
          JOIN users u ON dr.user_id = u.user_id
          LEFT JOIN crop_data c ON dr.crop_id = c.crop_id
          LEFT JOIN farmer_profiles fp ON u.user_id = fp.user_id
          WHERE 1=1";

$params = [];

if ($region !== 'all') {
    $query .= " AND fp.region = ?";
    $params[] = $region;
}

if ($reportType !== 'all') {
    $query .= " AND dr.severity = ?";
    $params[] = $reportType;
}

if ($status !== 'all') {
    $query .= " AND dr.status = ?";
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(dr.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(dr.created_at) <= ?";
    $params[] = $dateTo;
}

if ($cropFilter !== 'all') {
    $query .= " AND dr.crop_id = ?";
    $params[] = $cropFilter;
}

if (!empty($searchQuery)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR dr.disease_name LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY dr.created_at DESC LIMIT 1000";

$reports = $db->resultSet($query, $params);

// Export based on format
switch ($format) {
    case 'csv':
        exportCSV($reports);
        break;
    case 'excel':
        exportExcel($reports);
        break;
    case 'pdf':
        exportPDF($reports);
        break;
    default:
        exportCSV($reports);
}

function exportCSV($reports) {
    $filename = 'disease_reports_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'ID',
        'Farmer Name',
        'Phone',
        'Email',
        'Disease',
        'Crop',
        'Region',
        'Severity',
        'Status',
        'Symptoms',
        'Treatment',
        'Confidence',
        'Report Date'
    ]);
    
    // Data rows
    foreach ($reports as $report) {
        fputcsv($output, [
            $report['detection_id'],
            $report['farmer_name'],
            $report['phone'] ?? 'N/A',
            $report['email'] ?? 'N/A',
            $report['disease_name'] ?? 'Unknown',
            $report['crop_name'] ?? 'N/A',
            $report['region'] ?? 'N/A',
            ucfirst($report['severity'] ?? 'low'),
            ucfirst($report['status'] ?? 'pending'),
            $report['symptoms'] ?? '',
            $report['treatment'] ?? '',
            ($report['confidence'] ?? 0) . '%',
            $report['report_date']
        ]);
    }
    
    fclose($output);
    exit;
}

function exportExcel($reports) {
    $filename = 'disease_reports_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Headers
    echo '<tr style="background-color: #4ade80; font-weight: bold;">';
    echo '<th>ID</th>';
    echo '<th>Farmer Name</th>';
    echo '<th>Phone</th>';
    echo '<th>Email</th>';
    echo '<th>Disease</th>';
    echo '<th>Crop</th>';
    echo '<th>Region</th>';
    echo '<th>Severity</th>';
    echo '<th>Status</th>';
    echo '<th>Symptoms</th>';
    echo '<th>Treatment</th>';
    echo '<th>Confidence</th>';
    echo '<th>Report Date</th>';
    echo '</tr>';
    
    // Data rows
    foreach ($reports as $report) {
        $severityColor = match($report['severity'] ?? 'low') {
            'high' => '#fee2e2',
            'medium' => '#fef3c7',
            default => '#d1fae5'
        };
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($report['detection_id']) . '</td>';
        echo '<td>' . htmlspecialchars($report['farmer_name']) . '</td>';
        echo '<td>' . htmlspecialchars($report['phone'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($report['email'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($report['disease_name'] ?? 'Unknown') . '</td>';
        echo '<td>' . htmlspecialchars($report['crop_name'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($report['region'] ?? 'N/A') . '</td>';
        echo '<td style="background-color: ' . $severityColor . ';">' . ucfirst($report['severity'] ?? 'low') . '</td>';
        echo '<td>' . ucfirst($report['status'] ?? 'pending') . '</td>';
        echo '<td>' . htmlspecialchars($report['symptoms'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($report['treatment'] ?? '') . '</td>';
        echo '<td>' . ($report['confidence'] ?? 0) . '%</td>';
        echo '<td>' . htmlspecialchars($report['report_date']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit;
}

function exportPDF($reports) {
    // Simple HTML to PDF conversion (browser print)
    $filename = 'disease_reports_' . date('Y-m-d_His') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Disease Reports - SmartChashi</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #166534; text-align: center; }
            .meta { text-align: center; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4ade80; color: white; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .severity-high { background-color: #fee2e2; color: #dc2626; }
            .severity-medium { background-color: #fef3c7; color: #d97706; }
            .severity-low { background-color: #d1fae5; color: #059669; }
            @media print {
                body { margin: 0; }
                button { display: none; }
            }
        </style>
    </head>
    <body>
        <h1>🌾 SmartChashi - Disease Reports</h1>
        <p class="meta">Generated on: ' . date('F d, Y at h:i A') . ' | Total Reports: ' . count($reports) . '</p>
        <button onclick="window.print()" style="padding: 10px 20px; background: #4ade80; border: none; color: white; cursor: pointer; border-radius: 5px; margin-bottom: 20px;">Print / Save as PDF</button>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Farmer</th>
                    <th>Phone</th>
                    <th>Disease</th>
                    <th>Crop</th>
                    <th>Region</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($reports as $report) {
        $severityClass = 'severity-' . ($report['severity'] ?? 'low');
        echo '<tr>';
        echo '<td>' . htmlspecialchars($report['detection_id']) . '</td>';
        echo '<td>' . htmlspecialchars($report['farmer_name']) . '</td>';
        echo '<td>' . htmlspecialchars($report['phone'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($report['disease_name'] ?? 'Unknown') . '</td>';
        echo '<td>' . htmlspecialchars($report['crop_name'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($report['region'] ?? 'N/A') . '</td>';
        echo '<td class="' . $severityClass . '">' . ucfirst($report['severity'] ?? 'low') . '</td>';
        echo '<td>' . ucfirst($report['status'] ?? 'pending') . '</td>';
        echo '<td>' . htmlspecialchars($report['report_date']) . '</td>';
        echo '</tr>';
    }
    
    echo '
            </tbody>
        </table>
        <script>
            // Auto-print on load
            // window.onload = function() { window.print(); }
        </script>
    </body>
    </html>';
    exit;
}
