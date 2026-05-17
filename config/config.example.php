<?php
// ================================================================
//  Smart Chashi — Configuration Template
//  Copy this file to config.php and fill in your actual values.
//  NEVER commit config.php — it is listed in .gitignore
// ================================================================

// ── Database ─────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'smartcashi_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // your DB password

// ── Application ──────────────────────────────────────────────────
define('APP_NAME', 'Smart Chashi - AI Powered Smart Farming');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development');   // 'production' on live server
define('APP_DEBUG', true);

define('DEFAULT_LANGUAGE', 'en');
define('SUPPORTED_LANGUAGES', ['en', 'bn']);

define('PROJECT_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', PROJECT_ROOT . '/public/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB

define('SESSION_TIMEOUT', 3600);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $cookiePath = '/';
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $scriptDir  = dirname($_SERVER['SCRIPT_NAME']);
        $cookiePath = preg_replace('#/(pages|ajax|api|admin-secure|agent|public|config)(/.*)?$#', '', $scriptDir);
        if (empty($cookiePath)) $cookiePath = '/';
        if ($cookiePath !== '/' && !str_ends_with($cookiePath, '/')) $cookiePath .= '/';
    }

    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => $cookiePath,
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Security ──────────────────────────────────────────────────────
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_TIMEOUT', 3600);

// ── Disease Detection API ─────────────────────────────────────────
// Local Python server (Disease detection/app.py) on port 8080
define('DISEASE_DETECTION_API_URL', 'http://localhost:8080/api/analyze');

// ── Map / Geo APIs (all free, no key needed) ──────────────────────
define('OPENSTREETMAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
define('OPENSTREETMAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors');
define('NOMINATIM_API', 'https://nominatim.openstreetmap.org');

// ── Weather APIs (all free, no key needed) ────────────────────────
define('OPEN_METEO_API', 'https://api.open-meteo.com/v1/forecast');
define('OPEN_METEO_AIR_QUALITY_API', 'https://air-quality-api.open-meteo.com/v1/air-quality');
define('NASA_EONET_API', 'https://eonet.gsfc.nasa.gov/api/v3/events');
define('RAINVIEWER_API', 'https://api.rainviewer.com/public/weather-maps.json');
define('NASA_GIBS_API', 'https://gibs.earthdata.nasa.gov/wms/epsg4326/best/wms.cgi');
define('WINDY_EMBED_URL', 'https://embed.windy.com/embed2.html');

// ── Groq AI API ───────────────────────────────────────────────────
// Free tier — get your key at https://console.groq.com (no card needed)
define('GROQ_API_KEY', 'YOUR_GROQ_API_KEY_HERE');
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL',   'llama-3.1-8b-instant');

// ── Email / SMTP ──────────────────────────────────────────────────
// Use a Gmail App Password (not your real Gmail password)
// Guide: https://support.google.com/accounts/answer/185833
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USERNAME',   'your@gmail.com');
define('SMTP_PASSWORD',   'YOUR_GMAIL_APP_PASSWORD');
define('SMTP_FROM_NAME',  'Smart Chashi');
define('SMTP_FROM_EMAIL', 'your@gmail.com');

// ── Brand Colors ──────────────────────────────────────────────────
define('COLOR_PRIMARY',   '#557A46');
define('COLOR_SECONDARY', '#8FBC46');
define('COLOR_ACCENT',    '#FF8C00');

// ── Dynamic Base URL ──────────────────────────────────────────────
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base_path = preg_replace('#/(pages|ajax|api|admin-secure|agent|public|config|shop)(/.*)?$#', '', $base_path);
$base_url  = $protocol . $host . $base_path . '/';

// ── Database Class ────────────────────────────────────────────────
class Database {
    private $connection;
    private $statement;

    public function __construct() {
        try {
            $this->connection = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die('Database Connection Error: ' . $e->getMessage());
        }
    }

    public function query($sql) {
        if (empty($sql)) throw new Exception('SQL query cannot be empty.');
        $this->statement = $this->connection->prepare($sql);
        if ($this->statement === false) throw new Exception('Failed to prepare SQL statement.');
        return $this;
    }

    public function bind($param, $value, $type = PDO::PARAM_STR) {
        $this->statement->bindValue($param, $value, $type);
        return $this;
    }

    public function execute() {
        if ($this->statement === null) throw new Exception('No statement prepared. Call query() first.');
        return $this->statement->execute();
    }

    public function fetch()    { $this->execute(); return $this->statement->fetch(); }
    public function fetchAll() { $this->execute(); return $this->statement->fetchAll(); }

    public function resultSet($sql = null, $params = []) {
        if ($sql !== null) {
            $this->query($sql);
            foreach ($params as $i => $value) $this->bind(($i + 1), $value);
        }
        $this->execute();
        return $this->statement->fetchAll();
    }

    public function single($sql, $params = []) {
        $this->query($sql);
        foreach ($params as $i => $value) $this->bind(($i + 1), $value);
        $this->execute();
        return $this->statement->fetch();
    }

    public function insert($table, $data) {
        $cols  = implode(',', array_keys($data));
        $ph    = implode(',', array_fill(0, count($data), '?'));
        return $this->single("INSERT INTO $table ($cols) VALUES ($ph)", array_values($data));
    }

    public function lastInsertId() { return $this->connection->lastInsertId(); }
    public function rowCount()     { return $this->statement->rowCount(); }
    public function getConnection(){ return $this->connection; }
    public function beginTransaction() { return $this->connection->beginTransaction(); }
    public function commit()           { return $this->connection->commit(); }
    public function rollback()         { return $this->connection->rollBack(); }
}

// ── Helpers ───────────────────────────────────────────────────────
if (!function_exists('getCleanUrl')) {
    function getCleanUrl($page, $params = []) {
        global $base_url;
        $url = $base_url . $page;
        if (!empty($params)) $url .= '?' . http_build_query($params);
        return $url;
    }
}
if (!function_exists('isLoggedIn'))    { function isLoggedIn()    { return isset($_SESSION['user_id']); } }
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (isLoggedIn()) { $db = new Database(); return $db->single('SELECT * FROM users WHERE user_id = ?', [$_SESSION['user_id']]); }
        return null;
    }
}
if (!function_exists('redirect')) {
    function redirect($page, $params = []) { header('Location: ' . getCleanUrl($page, $params)); exit; }
}
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) return false;
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
if (!function_exists('hashPassword'))   { function hashPassword($p)    { return password_hash($p, PASSWORD_BCRYPT); } }
if (!function_exists('verifyPassword')) { function verifyPassword($p, $h) { return password_verify($p, $h); } }

require_once __DIR__ . '/languages.php';

$SYSTEM_SETTINGS = null;
try {
    $db = new Database();
    $SYSTEM_SETTINGS = $db->single("SELECT * FROM system_settings WHERE id = 1");
} catch (Exception $e) {
    error_log("Failed to load system settings: " . $e->getMessage());
    $SYSTEM_SETTINGS = [];
}

if (!function_exists('getSystemSetting')) {
    function getSystemSetting($key, $default = null) { global $SYSTEM_SETTINGS; return $SYSTEM_SETTINGS[$key] ?? $default; }
}
if (!function_exists('outputSystemSettingsJS')) {
    function outputSystemSettingsJS() {
        global $SYSTEM_SETTINGS;
        $s = $SYSTEM_SETTINGS ?: [];
        echo '<script>window.SYSTEM_SETTINGS = ' . json_encode([
            'site_name'              => $s['site_name'] ?? 'SmartChashi',
            'site_description'       => $s['site_description'] ?? '',
            'default_language'       => $s['default_language'] ?? 'en',
            'timezone'               => $s['timezone'] ?? 'Asia/Dhaka',
            'currency'               => $s['currency'] ?? 'BDT',
            'currency_symbol'        => $s['currency_symbol'] ?? '৳',
            'disease_detection_api_url' => $s['disease_detection_api_url'] ?? '',
            'agent_api_url'          => $s['agent_api_url'] ?? '',
            'items_per_page'         => (int)($s['items_per_page'] ?? 20),
        ]) . ';</script>' . PHP_EOL;
    }
}
