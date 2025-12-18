<?php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'smartcashi_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Smart Chashi - AI Powered Smart Farming');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development');
define('APP_DEBUG', true);

define('DEFAULT_LANGUAGE', 'en');
define('SUPPORTED_LANGUAGES', ['en', 'bn']);

define('PROJECT_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', PROJECT_ROOT . '/public/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB

define('SESSION_TIMEOUT', 3600);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'secure' => false,      // Set true for HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_TIMEOUT', 3600);

// External APIs
define('GOOGLE_MAPS_API_KEY', 'AIzaSyDemoKey123'); // Replace with real key
define('OPENWEATHER_API_KEY', 'demo_weather_key'); // Replace with real key

// Colors
define('COLOR_PRIMARY', '#557A46');
define('COLOR_SECONDARY', '#8FBC46');
define('COLOR_ACCENT', '#FF8C00');

$base_url = "http://localhost/smartcashi/";

// Database Connection Class
class Database {
    private $connection;
    private $statement;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $e) {
            die('Database Connection Error: ' . $e->getMessage());
        }
    }
    
    public function query($sql) {
        if (empty($sql)) {
            throw new Exception('SQL query cannot be empty.');
        }
        $this->statement = $this->connection->prepare($sql);
        if ($this->statement === false) {
            throw new Exception('Failed to prepare SQL statement.');
        }
        return $this;
    }
    
    public function bind($param, $value, $type = PDO::PARAM_STR) {
        $this->statement->bindValue($param, $value, $type);
        return $this;
    }
    
    public function execute() {
        if ($this->statement === null) {
            throw new Exception('No statement prepared. Call query() first.');
        }
        return $this->statement->execute();
    }
    
    public function resultSet($sql = null, $params = []) {
        if ($sql !== null) {
            $this->query($sql);
            foreach($params as $i => $value) {
                $this->bind(($i + 1), $value);
            }
        }
        $this->execute();
        return $this->statement->fetchAll();
    }
    
    public function single($sql, $params = []) {
        $this->query($sql);
        foreach($params as $i => $value) {
            $this->bind(($i + 1), $value);
        }
        $this->execute();
        return $this->statement->fetch();
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        return $this->single($sql, array_values($data));
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function rowCount() {
        return $this->statement->rowCount();
    }
}

// Helper function to get clean URLs
if (!function_exists('getCleanUrl')) {
    function getCleanUrl($page, $params = []) {
        global $base_url;
        $url = $base_url . $page;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

// Helper function to check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Helper function to get current user
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (isLoggedIn()) {
            $db = new Database();
            return $db->single('SELECT * FROM users WHERE user_id = ?', [$_SESSION['user_id']]);
        }
        return null;
    }
}

// Helper function to redirect
if (!function_exists('redirect')) {
    function redirect($page, $params = []) {
        header('Location: ' . getCleanUrl($page, $params));
        exit;
    }
}

// Helper function to generate CSRF token
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
}

// Helper function to verify CSRF token
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Helper function to hash password
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}

// Helper function to verify password
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Load language translations
require_once __DIR__ . '/languages.php';
?>
