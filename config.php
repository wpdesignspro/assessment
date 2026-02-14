<?php
/**
 * Configuration File
 * Loads environment variables and database connection
 */

session_start();

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        die('Environment file not found');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key-value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set as environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load environment variables
loadEnv(__DIR__ . '/.env');

// Database configuration
define('DB_HOST', getenv('DB_HOST'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));

// Admin credentials
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME'));
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD'));

// Review credentials
define('REVIEW_USERNAME', getenv('REVIEW_USERNAME'));
define('REVIEW_PASSWORD', getenv('REVIEW_PASSWORD'));

// Upload settings
define('UPLOAD_DIR', getenv('UPLOAD_DIR') ?: 'uploads/');
define('MAX_IMAGE_SIZE', getenv('MAX_IMAGE_SIZE') ?: 104857600);
define('MAX_VIDEO_SIZE', getenv('MAX_VIDEO_SIZE') ?: 10737418240);
define('ALLOWED_IMAGE_TYPES', getenv('ALLOWED_IMAGE_TYPES') ?: 'image/jpeg,image/png,image/jpg');
define('ALLOWED_VIDEO_TYPES', getenv('ALLOWED_VIDEO_TYPES') ?: 'video/mp4,video/mov,video/avi');

// Create database connection
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please contact administrator.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Security functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isReview() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'review';
}

function logActivity($userType, $username, $action, $description = '') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO activity_log (user_type, username, action, description, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userType, $username, $action, $description, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    // Create subdirectories
    mkdir(UPLOAD_DIR . 'images', 0755, true);
    mkdir(UPLOAD_DIR . 'videos', 0755, true);
}

// Set timezone
date_default_timezone_set('Africa/Lagos');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
?>