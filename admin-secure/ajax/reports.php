<?php
/**
 * Reports AJAX Handler — Fixed & Enhanced
 *
 * Bugs fixed:
 *  - generateJSON filename mismatch (pdf saved as .pdf.json but wrong name returned)
 *  - downloadReport / deleteReport used wrong column `id` (should be `report_id`)
 *  - Missing report types: content_analytics, system_health, financial, ai_usage
 *  - DB INSERT missing `report_name` field
 *  - createScheduledReport missing `report_name`
 *  - Consistent type name: content_analytics everywhere (was content_overview in backend)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/config.php';

function jsonResponse($data, $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

set_exception_handler(function ($e) {
    error_log('Reports exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
});

header('Content-Type: application/json');

// Auth
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$db = new Database();

if (isset($_SESSION['user_id'])) {
    $user = $db->single("SELECT role FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    if (!$user || $user['role'] !== 'admin') {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }
    $adminId = (int)$_SESSION['user_id'];
} else {
    $adminId = (int)$_SESSION['admin_id'];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF validation (POST only; download is GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
}

switch ($action) {
    case 'generate_report':         generateReport();           break;
    case 'download_report':         downloadReport();           break;
    case 'preview_report':          previewReport();            break;
    case 'delete_report':           deleteReport();             break;
    case 'create_scheduled_report': createScheduledReport();    break;
    case 'toggle_scheduled_report': toggleScheduledReport();    break;
    case 'delete_scheduled_report': deleteScheduledReport();    break;
    case 'get_stats':               getReportStats();           break;
    default: jsonResponse(['error' => 'Invalid action'], 400);
}

// ─────────────────────────────────────────────────────────────────────────────
// GENERATE REPORT
// ─────────────────────────────────────────────────────────────────────────────
function generateReport(): void {
    global $db, $adminId;

    $type     = trim($_POST['report_type'] ?? '');
    $format   = trim($_POST['format'] ?? 'csv');
    $dateFrom = trim($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
    $dateTo   = trim($_POST['date_to']   ?? date('Y-m-d'));
    $name     = trim($_POST['report_name'] ?? '');

    $validTypes   = ['user_summary','security_audit','activity_log','content_analytics','system_health','financial','ai_usage'];
    $validFormats = ['pdf','csv','xlsx'];

    if (!in_array($type, $validTypes, true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid report type'], 400);
    }
    if (!in_array($format, $validFormats, true)) $format = 'csv';

    $autoName = $name ?: ucwords(str_replace('_', ' ', $type)) . ' — ' . date('M d, Y', strtotime($dateFrom)) . ' to ' . date('M d, Y', strtotime($dateTo));

    try {
        $reportData = getReportData($type, $dateFrom, $dateTo);
        [$fileName, $filePath] = generateReportFile($type, $format, $reportData, $dateFrom, $dateTo);

        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

        try {
            $db->query(
                "INSERT INTO generated_reports
                 (report_name, report_type, file_name, file_path, file_size, format, date_from, date_to, generated_by, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,'completed',NOW())"
            )->bind(1,$autoName)->bind(2,$type)->bind(3,$fileName)
             ->bind(4,'reports/'.date('Y-m').'/'.$fileName)->bind(5,$fileSize)
             ->bind(6,$format)->bind(7,$dateFrom)->bind(8,$dateTo)->bind(9,$adminId)->execute();
        } catch (Exception $dbErr) {
            error_log('Could not save report record: ' . $dbErr->getMessage());
        }

        jsonResponse([
            'success'   => true,
            'message'   => 'Report generated successfully',
            'file_name' => $fileName,
            'file_path' => 'reports/' . date('Y-m') . '/' . $fileName,
            'file_size' => $fileSize,
            'rows'      => countReportRows($reportData),
        ]);

    } catch (Exception $e) {
        error_log('Report generation error: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Failed to generate: ' . $e->getMessage()], 500);
    }
}

function countReportRows(array $data): int {
    foreach (['recent_users','activities','failed_attempts','blocked_ips','products','posts'] as $k) {
        if (!empty($data[$k])) return count($data[$k]);
    }
    return 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// REPORT DATA COLLECTORS
// ─────────────────────────────────────────────────────────────────────────────
function getReportData(string $type, string $dateFrom, string $dateTo): array {
    global $db;
    $data = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'generated_at' => date('Y-m-d H:i:s')];
    $to   = $dateTo . ' 23:59:59';

    try {
        switch ($type) {

            // ── User Summary ──────────────────────────────────────────────
            case 'user_summary':
                $data['total_users'] = (int)($db->single(
                    "SELECT COUNT(*) c FROM users WHERE created_at BETWEEN ? AND ?", [$dateFrom, $to])['c'] ?? 0);
                $data['total_all_time'] = (int)($db->single("SELECT COUNT(*) c FROM users")['c'] ?? 0);
                $data['users_by_role']  = $db->resultSet(
                    "SELECT role, COUNT(*) count FROM users WHERE created_at BETWEEN ? AND ? GROUP BY role", [$dateFrom, $to]) ?? [];
                $data['recent_users']   = $db->resultSet(
                    "SELECT user_id, first_name, last_name, email, role, created_at FROM users
                     WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 200", [$dateFrom, $to]) ?? [];
                $data['active_users'] = (int)($db->single(
                    "SELECT COUNT(DISTINCT user_id) c FROM activity_logs WHERE created_at BETWEEN ? AND ?",
                    [$dateFrom, $to])['c'] ?? 0);
                break;

            // ── Security Audit ────────────────────────────────────────────
            case 'security_audit':
                try {
                    $ls = $db->single(
                        "SELECT COUNT(*) count, SUM(status='success') successful, SUM(status='failed') failed
                         FROM login_attempts WHERE attempted_at BETWEEN ? AND ?", [$dateFrom, $to]);
                    $data['login_attempts']    = (int)($ls['count'] ?? 0);
                    $data['successful_logins'] = (int)($ls['successful'] ?? 0);
                    $data['failed_logins']     = (int)($ls['failed'] ?? 0);
                } catch (Exception $e) { $data['login_attempts'] = $data['successful_logins'] = $data['failed_logins'] = 0; }

                try {
                    $data['failed_attempts'] = $db->resultSet(
                        "SELECT ip_address, COUNT(*) attempts FROM login_attempts
                         WHERE status='failed' AND attempted_at BETWEEN ? AND ?
                         GROUP BY ip_address ORDER BY attempts DESC LIMIT 50", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['failed_attempts'] = []; }

                try {
                    $data['blocked_ips'] = $db->resultSet(
                        "SELECT ip_address, reason, blocked_at FROM blocked_ips
                         WHERE blocked_at BETWEEN ? AND ? ORDER BY blocked_at DESC LIMIT 50", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['blocked_ips'] = []; }

                try {
                    $data['security_events'] = $db->resultSet(
                        "SELECT event_type, severity, description, created_at FROM security_events
                         WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 100", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['security_events'] = []; }
                break;

            // ── Activity Log ──────────────────────────────────────────────
            case 'activity_log':
                try {
                    $data['activities'] = $db->resultSet(
                        "SELECT al.*, CONCAT(u.first_name,' ',COALESCE(u.last_name,'')) as user_name
                         FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id
                         WHERE al.created_at BETWEEN ? AND ? ORDER BY al.created_at DESC LIMIT 500", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['activities'] = []; }

                try {
                    $data['activity_counts'] = $db->resultSet(
                        "SELECT action_type, COUNT(*) count FROM activity_logs
                         WHERE created_at BETWEEN ? AND ? GROUP BY action_type ORDER BY count DESC", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['activity_counts'] = []; }
                break;

            // ── Content Analytics ─────────────────────────────────────────
            case 'content_analytics':
                foreach ([
                    'total_posts'     => "SELECT COUNT(*) c FROM community_posts WHERE created_at BETWEEN ? AND ?",
                    'total_products'  => "SELECT COUNT(*) c FROM marketplace_products WHERE created_at BETWEEN ? AND ?",
                    'disease_reports' => "SELECT COUNT(*) c FROM disease_detections WHERE detected_at BETWEEN ? AND ?",
                    'learn_views'     => "SELECT COUNT(*) c FROM learning_content WHERE created_at BETWEEN ? AND ?",
                ] as $key => $sql) {
                    try { $data[$key] = (int)($db->single($sql, [$dateFrom, $to])['c'] ?? 0); }
                    catch (Exception $e) { $data[$key] = 0; }
                }
                try {
                    $data['products'] = $db->resultSet(
                        "SELECT title, price, category, created_at FROM marketplace_products
                         WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 100", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['products'] = []; }
                try {
                    $data['posts'] = $db->resultSet(
                        "SELECT p.title, p.created_at, CONCAT(u.first_name,' ',COALESCE(u.last_name,'')) author
                         FROM community_posts p LEFT JOIN users u ON p.user_id = u.user_id
                         WHERE p.created_at BETWEEN ? AND ? ORDER BY p.created_at DESC LIMIT 100", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['posts'] = []; }
                break;

            // ── System Health ─────────────────────────────────────────────
            case 'system_health':
                $data['php_version']    = PHP_VERSION;
                $data['server_os']      = PHP_OS;
                $data['memory_limit']   = ini_get('memory_limit');
                $data['max_execution']  = ini_get('max_execution_time') . 's';
                $data['disk_total']     = formatBytes(disk_total_space(__DIR__));
                $data['disk_free']      = formatBytes(disk_free_space(__DIR__));
                $data['disk_used_pct']  = round((1 - disk_free_space(__DIR__) / disk_total_space(__DIR__)) * 100, 1) . '%';
                $data['upload_dir']     = is_writable(__DIR__ . '/../../public/uploads/') ? 'Writable' : 'Not Writable';
                $data['reports_dir']    = is_writable(__DIR__ . '/../../reports/') ? 'Writable' : 'Not Writable';

                try {
                    $data['total_errors'] = (int)($db->single(
                        "SELECT COUNT(*) c FROM error_logs WHERE created_at BETWEEN ? AND ?", [$dateFrom, $to])['c'] ?? 0);
                    $data['unresolved_errors'] = (int)($db->single(
                        "SELECT COUNT(*) c FROM error_logs WHERE is_resolved=0 AND created_at BETWEEN ? AND ?", [$dateFrom, $to])['c'] ?? 0);
                    $data['critical_errors'] = (int)($db->single(
                        "SELECT COUNT(*) c FROM error_logs WHERE severity='critical' AND created_at BETWEEN ? AND ?", [$dateFrom, $to])['c'] ?? 0);
                    $data['error_log'] = $db->resultSet(
                        "SELECT severity, message, created_at FROM error_logs
                         WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 100", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) {
                    $data['total_errors'] = $data['unresolved_errors'] = $data['critical_errors'] = 0;
                    $data['error_log'] = [];
                }
                break;

            // ── Financial / Marketplace ───────────────────────────────────
            case 'financial':
                try {
                    $fs = $db->single(
                        "SELECT COUNT(*) count, COALESCE(SUM(amount),0) total, COALESCE(AVG(amount),0) avg
                         FROM marketplace_orders WHERE created_at BETWEEN ? AND ?", [$dateFrom, $to]);
                    $data['total_orders']   = (int)($fs['count'] ?? 0);
                    $data['total_revenue']  = round((float)($fs['total'] ?? 0), 2);
                    $data['avg_order']      = round((float)($fs['avg'] ?? 0), 2);
                } catch (Exception $e) {
                    $data['total_orders'] = 0; $data['total_revenue'] = 0; $data['avg_order'] = 0;
                }
                try {
                    $data['orders_by_status'] = $db->resultSet(
                        "SELECT status, COUNT(*) count FROM marketplace_orders
                         WHERE created_at BETWEEN ? AND ? GROUP BY status", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['orders_by_status'] = []; }
                try {
                    $data['top_products'] = $db->resultSet(
                        "SELECT p.title, COUNT(o.id) sales, SUM(o.amount) revenue
                         FROM marketplace_orders o JOIN marketplace_products p ON o.product_id = p.product_id
                         WHERE o.created_at BETWEEN ? AND ?
                         GROUP BY p.product_id ORDER BY sales DESC LIMIT 20", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['top_products'] = []; }
                break;

            // ── AI / Agent Usage ──────────────────────────────────────────
            case 'ai_usage':
                try {
                    $as = $db->single(
                        "SELECT COUNT(*) count, AVG(response_time_ms) avg_ms,
                                SUM(success) ok, SUM(1-success) fail
                         FROM ai_usage_logs WHERE created_at BETWEEN ? AND ?", [$dateFrom, $to]);
                    $data['total_calls']   = (int)($as['count'] ?? 0);
                    $data['success_calls'] = (int)($as['ok'] ?? 0);
                    $data['failed_calls']  = (int)($as['fail'] ?? 0);
                    $data['avg_response_ms'] = round((float)($as['avg_ms'] ?? 0));
                } catch (Exception $e) {
                    $data['total_calls'] = $data['success_calls'] = $data['failed_calls'] = 0;
                    $data['avg_response_ms'] = 0;
                }
                try {
                    $data['by_provider'] = $db->resultSet(
                        "SELECT provider, model, COUNT(*) calls, AVG(response_time_ms) avg_ms, SUM(success) ok
                         FROM ai_usage_logs WHERE created_at BETWEEN ? AND ?
                         GROUP BY provider, model ORDER BY calls DESC", [$dateFrom, $to]) ?? [];
                } catch (Exception $e) { $data['by_provider'] = []; }
                try {
                    $data['conversations'] = (int)($db->single(
                        "SELECT COUNT(*) c FROM agent_conversations WHERE created_at BETWEEN ? AND ?", [$dateFrom, $to])['c'] ?? 0);
                    $data['messages'] = (int)($db->single(
                        "SELECT COUNT(*) c FROM agent_messages WHERE created_at BETWEEN ? AND ?", [$dateFrom, $to])['c'] ?? 0);
                } catch (Exception $e) { $data['conversations'] = $data['messages'] = 0; }
                break;

            default:
                throw new Exception("Unknown report type: {$type}");
        }
    } catch (Exception $e) {
        error_log("getReportData({$type}): " . $e->getMessage());
        $data['_error'] = $e->getMessage();
    }

    return $data;
}

// ─────────────────────────────────────────────────────────────────────────────
// FILE GENERATORS  — returns [fileName, filePath]
// ─────────────────────────────────────────────────────────────────────────────
function generateReportFile(string $type, string $format, array $data, string $dateFrom, string $dateTo): array {
    $dir = __DIR__ . '/../../reports/' . date('Y-m');
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $ts  = date('Y-m-d_H-i-s');

    // xlsx falls back to csv (no library dependency)
    if ($format === 'xlsx') $format = 'csv';
    // pdf falls back to json
    if ($format === 'pdf') $format = 'json';

    $fileName = "report_{$type}_{$ts}.{$format}";
    $filePath = $dir . '/' . $fileName;

    match ($format) {
        'csv'  => generateCSV($filePath, $type, $data),
        'json' => file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        default => file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT)),
    };

    return [$fileName, $filePath];
}

function generateCSV(string $filePath, string $type, array $data): void {
    $fp = fopen($filePath, 'w');
    // BOM for Excel UTF-8
    fwrite($fp, "\xEF\xBB\xBF");

    fputcsv($fp, ['Smart Chashi Report', ucwords(str_replace('_', ' ', $type)), $data['generated_at']]);
    fputcsv($fp, ['Period', $data['date_from'], '→', $data['date_to']]);
    fputcsv($fp, []);

    switch ($type) {
        case 'user_summary':
            fputcsv($fp, ['SUMMARY']);
            fputcsv($fp, ['New Users (period)', $data['total_users']]);
            fputcsv($fp, ['Total Users (all time)', $data['total_all_time'] ?? 'N/A']);
            fputcsv($fp, ['Active Users (period)', $data['active_users'] ?? 'N/A']);
            fputcsv($fp, []);
            fputcsv($fp, ['USERS BY ROLE']);
            fputcsv($fp, ['Role', 'Count']);
            foreach ($data['users_by_role'] ?? [] as $r) fputcsv($fp, [$r['role'], $r['count']]);
            fputcsv($fp, []);
            fputcsv($fp, ['USER LIST']);
            fputcsv($fp, ['ID', 'First Name', 'Last Name', 'Email', 'Role', 'Joined']);
            foreach ($data['recent_users'] ?? [] as $u) {
                fputcsv($fp, [$u['user_id'],$u['first_name'],$u['last_name']??'',$u['email'],$u['role'],$u['created_at']]);
            }
            break;

        case 'security_audit':
            fputcsv($fp, ['SUMMARY']);
            fputcsv($fp, ['Total Login Attempts', $data['login_attempts'] ?? 0]);
            fputcsv($fp, ['Successful Logins',    $data['successful_logins'] ?? 0]);
            fputcsv($fp, ['Failed Logins',         $data['failed_logins'] ?? 0]);
            fputcsv($fp, ['Blocked IPs',           count($data['blocked_ips'] ?? [])]);
            fputcsv($fp, []);
            fputcsv($fp, ['TOP FAILED IPs']);
            fputcsv($fp, ['IP Address', 'Attempts']);
            foreach ($data['failed_attempts'] ?? [] as $r) fputcsv($fp, [$r['ip_address'], $r['attempts']]);
            fputcsv($fp, []);
            fputcsv($fp, ['BLOCKED IPs']);
            fputcsv($fp, ['IP Address', 'Reason', 'Blocked At']);
            foreach ($data['blocked_ips'] ?? [] as $r) fputcsv($fp, [$r['ip_address'], $r['reason'] ?? '', $r['blocked_at']]);
            fputcsv($fp, []);
            fputcsv($fp, ['SECURITY EVENTS']);
            fputcsv($fp, ['Type', 'Severity', 'Description', 'Date']);
            foreach ($data['security_events'] ?? [] as $e) fputcsv($fp, [$e['event_type'],$e['severity'],$e['description'],$e['created_at']]);
            break;

        case 'activity_log':
            fputcsv($fp, ['ACTIVITY COUNTS']);
            fputcsv($fp, ['Action', 'Count']);
            foreach ($data['activity_counts'] ?? [] as $r) fputcsv($fp, [$r['action_type'], $r['count']]);
            fputcsv($fp, []);
            fputcsv($fp, ['ACTIVITY LOG']);
            fputcsv($fp, ['User', 'Action', 'Description', 'Date']);
            foreach ($data['activities'] ?? [] as $a) {
                fputcsv($fp, [$a['user_name'] ?? '',$a['action_type'] ?? '',$a['description'] ?? '',$a['created_at']]);
            }
            break;

        case 'content_analytics':
            fputcsv($fp, ['SUMMARY']);
            fputcsv($fp, ['Community Posts',      $data['total_posts'] ?? 0]);
            fputcsv($fp, ['Marketplace Products', $data['total_products'] ?? 0]);
            fputcsv($fp, ['Disease Detections',   $data['disease_reports'] ?? 0]);
            fputcsv($fp, ['Learning Content',     $data['learn_views'] ?? 0]);
            fputcsv($fp, []);
            fputcsv($fp, ['PRODUCTS']);
            fputcsv($fp, ['Title', 'Price', 'Category', 'Created']);
            foreach ($data['products'] ?? [] as $p) fputcsv($fp, [$p['title'],$p['price'],$p['category'],$p['created_at']]);
            fputcsv($fp, []);
            fputcsv($fp, ['COMMUNITY POSTS']);
            fputcsv($fp, ['Title', 'Author', 'Created']);
            foreach ($data['posts'] ?? [] as $p) fputcsv($fp, [$p['title'],$p['author'],$p['created_at']]);
            break;

        case 'system_health':
            fputcsv($fp, ['SYSTEM INFO']);
            fputcsv($fp, ['PHP Version',    $data['php_version']]);
            fputcsv($fp, ['Server OS',      $data['server_os']]);
            fputcsv($fp, ['Memory Limit',   $data['memory_limit']]);
            fputcsv($fp, ['Max Execution',  $data['max_execution']]);
            fputcsv($fp, ['Disk Total',     $data['disk_total']]);
            fputcsv($fp, ['Disk Free',      $data['disk_free']]);
            fputcsv($fp, ['Disk Used',      $data['disk_used_pct']]);
            fputcsv($fp, []);
            fputcsv($fp, ['ERROR SUMMARY']);
            fputcsv($fp, ['Total Errors',      $data['total_errors'] ?? 0]);
            fputcsv($fp, ['Unresolved',        $data['unresolved_errors'] ?? 0]);
            fputcsv($fp, ['Critical',          $data['critical_errors'] ?? 0]);
            fputcsv($fp, []);
            fputcsv($fp, ['ERROR LOG']);
            fputcsv($fp, ['Severity', 'Message', 'Date']);
            foreach ($data['error_log'] ?? [] as $e) fputcsv($fp, [$e['severity'],$e['message'],$e['created_at']]);
            break;

        case 'financial':
            fputcsv($fp, ['FINANCIAL SUMMARY']);
            fputcsv($fp, ['Total Orders',   $data['total_orders'] ?? 0]);
            fputcsv($fp, ['Total Revenue',  '৳' . number_format($data['total_revenue'] ?? 0, 2)]);
            fputcsv($fp, ['Avg Order Value','৳' . number_format($data['avg_order'] ?? 0, 2)]);
            fputcsv($fp, []);
            fputcsv($fp, ['ORDERS BY STATUS']);
            fputcsv($fp, ['Status', 'Count']);
            foreach ($data['orders_by_status'] ?? [] as $r) fputcsv($fp, [$r['status'], $r['count']]);
            fputcsv($fp, []);
            fputcsv($fp, ['TOP PRODUCTS']);
            fputcsv($fp, ['Product', 'Sales', 'Revenue (৳)']);
            foreach ($data['top_products'] ?? [] as $p) fputcsv($fp, [$p['title'],$p['sales'],number_format($p['revenue']??0,2)]);
            break;

        case 'ai_usage':
            fputcsv($fp, ['AI USAGE SUMMARY']);
            fputcsv($fp, ['Total API Calls',     $data['total_calls'] ?? 0]);
            fputcsv($fp, ['Successful Calls',    $data['success_calls'] ?? 0]);
            fputcsv($fp, ['Failed Calls',        $data['failed_calls'] ?? 0]);
            fputcsv($fp, ['Avg Response Time',   ($data['avg_response_ms'] ?? 0) . ' ms']);
            fputcsv($fp, ['Conversations',       $data['conversations'] ?? 0]);
            fputcsv($fp, ['Total Messages',      $data['messages'] ?? 0]);
            fputcsv($fp, []);
            fputcsv($fp, ['BY PROVIDER & MODEL']);
            fputcsv($fp, ['Provider', 'Model', 'Calls', 'Avg Response (ms)', 'Success']);
            foreach ($data['by_provider'] ?? [] as $r) {
                fputcsv($fp, [$r['provider'],$r['model'],$r['calls'],round($r['avg_ms']??0),$r['ok']]);
            }
            break;
    }

    fclose($fp);
}

// ─────────────────────────────────────────────────────────────────────────────
// DOWNLOAD — fixed column name report_id
// ─────────────────────────────────────────────────────────────────────────────
function downloadReport(): void {
    global $db;

    $reportId = (int)($_GET['report_id'] ?? 0);
    if (!$reportId) { http_response_code(400); die('Invalid report ID'); }

    // FIX: was `WHERE id = ?` (wrong), now uses `report_id`
    $report = $db->single("SELECT * FROM generated_reports WHERE report_id = ?", [$reportId]);
    if (!$report) { http_response_code(404); die('Report not found'); }

    $filePath = __DIR__ . '/../../' . $report['file_path'];
    if (!file_exists($filePath)) {
        // Try common alternate extensions
        $alts = [$filePath . '.json', str_replace('.pdf', '.json', $filePath), str_replace('.pdf', '.csv', $filePath)];
        $found = null;
        foreach ($alts as $alt) { if (file_exists($alt)) { $found = $alt; break; } }
        if (!$found) { http_response_code(404); die('File not found on disk'); }
        $filePath = $found;
    }

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $contentType = match($ext) {
        'csv'  => 'text/csv; charset=utf-8',
        'json' => 'application/json',
        'pdf'  => 'application/pdf',
        default => 'application/octet-stream',
    };

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache');
    readfile($filePath);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PREVIEW — return JSON data for in-browser preview
// ─────────────────────────────────────────────────────────────────────────────
function previewReport(): void {
    global $db;

    $reportId = (int)($_POST['report_id'] ?? 0);
    if (!$reportId) jsonResponse(['success' => false, 'message' => 'Missing ID'], 400);

    $report = $db->single("SELECT * FROM generated_reports WHERE report_id = ?", [$reportId]);
    if (!$report) jsonResponse(['success' => false, 'message' => 'Not found'], 404);

    $filePath = __DIR__ . '/../../' . $report['file_path'];
    // Try json variants
    if (!file_exists($filePath)) {
        foreach ([$filePath.'.json', str_replace('.pdf','.json',$filePath)] as $alt) {
            if (file_exists($alt)) { $filePath = $alt; break; }
        }
    }

    if (!file_exists($filePath)) {
        // Regenerate preview data on-the-fly
        $type = $report['report_type'];
        $data = getReportData($type, $report['date_from'] ?? date('Y-m-d', strtotime('-30d')), $report['date_to'] ?? date('Y-m-d'));
        jsonResponse(['success' => true, 'data' => $data, 'report' => $report, 'live' => true]);
    }

    $ext  = pathinfo($filePath, PATHINFO_EXTENSION);
    $content = file_get_contents($filePath);
    if ($ext === 'json') {
        jsonResponse(['success' => true, 'data' => json_decode($content, true), 'report' => $report]);
    }
    // CSV — return first 50 rows
    $lines = array_filter(explode("\n", $content));
    $rows  = array_map('str_getcsv', array_slice($lines, 0, 52));
    jsonResponse(['success' => true, 'csv_preview' => $rows, 'report' => $report]);
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE — fixed column name
// ─────────────────────────────────────────────────────────────────────────────
function deleteReport(): void {
    global $db;

    $reportId = (int)($_POST['report_id'] ?? 0);
    if (!$reportId) jsonResponse(['success' => false, 'message' => 'Report ID required'], 400);

    try {
        // FIX: was `WHERE id = :id` (wrong column), now uses `report_id`
        $report = $db->single("SELECT * FROM generated_reports WHERE report_id = ?", [$reportId]);
        if ($report) {
            $filePath = __DIR__ . '/../../' . $report['file_path'];
            foreach ([$filePath, $filePath.'.json'] as $fp) {
                if (file_exists($fp)) @unlink($fp);
            }
            $db->query("DELETE FROM generated_reports WHERE report_id = ?")->bind(1, $reportId)->execute();
        }
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()], 500);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SCHEDULED REPORTS
// ─────────────────────────────────────────────────────────────────────────────
function createScheduledReport(): void {
    global $db, $adminId;

    $reportName  = trim($_POST['report_name']  ?? '');
    $reportType  = trim($_POST['report_type']  ?? '');
    $format      = trim($_POST['format']       ?? 'csv');
    $scheduleCron = trim($_POST['schedule_cron'] ?? '');
    $scheduleHuman = trim($_POST['schedule_human'] ?? '');
    $recipients  = trim($_POST['recipients']   ?? '');

    if (!$reportName || !$reportType || !$scheduleCron) {
        jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }

    try {
        $db->query(
            "INSERT INTO scheduled_reports
             (report_name, report_type, format, schedule_cron, schedule_human, recipients, is_enabled, created_by, created_at)
             VALUES (?,?,?,?,?,?,1,?,NOW())"
        )->bind(1,$reportName)->bind(2,$reportType)->bind(3,$format)
         ->bind(4,$scheduleCron)->bind(5,$scheduleHuman)->bind(6,$recipients)->bind(7,$adminId)->execute();

        jsonResponse(['success' => true, 'message' => 'Report scheduled successfully']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to schedule: ' . $e->getMessage()], 500);
    }
}

function toggleScheduledReport(): void {
    global $db;
    $id = (int)($_POST['schedule_id'] ?? 0);
    $en = (int)($_POST['is_enabled'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'Missing ID'], 400);
    try {
        $db->query("UPDATE scheduled_reports SET is_enabled=? WHERE schedule_id=?")
           ->bind(1,$en)->bind(2,$id)->execute();
        jsonResponse(['success' => true]);
    } catch (Exception $e) { jsonResponse(['success' => false, 'message' => $e->getMessage()], 500); }
}

function deleteScheduledReport(): void {
    global $db;
    $id = (int)($_POST['schedule_id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'Missing ID'], 400);
    try {
        $db->query("DELETE FROM scheduled_reports WHERE schedule_id=?")->bind(1,$id)->execute();
        jsonResponse(['success' => true]);
    } catch (Exception $e) { jsonResponse(['success' => false, 'message' => $e->getMessage()], 500); }
}

// ─────────────────────────────────────────────────────────────────────────────
// STATS
// ─────────────────────────────────────────────────────────────────────────────
function getReportStats(): void {
    global $db;
    try {
        $total    = (int)($db->single("SELECT COUNT(*) c FROM generated_reports")['c'] ?? 0);
        $month    = (int)($db->single("SELECT COUNT(*) c FROM generated_reports WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'] ?? 0);
        $sizeMB   = round((float)($db->single("SELECT COALESCE(SUM(file_size),0) s FROM generated_reports")['s'] ?? 0) / 1048576, 2);
        $sched    = (int)($db->single("SELECT COUNT(*) c FROM scheduled_reports WHERE is_enabled=1")['c'] ?? 0);
        jsonResponse(['success' => true, 'total' => $total, 'this_month' => $month, 'size_mb' => $sizeMB, 'scheduled' => $sched]);
    } catch (Exception $e) {
        jsonResponse(['success' => true, 'total' => 0, 'this_month' => 0, 'size_mb' => 0, 'scheduled' => 0]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function formatBytes(int|float $bytes, int $precision = 1): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $pow   = min(floor(log($bytes) / log(1024)), count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
