<?php
session_start();

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = ''; // Default XAMPP password
$db_name = 'food_fraud_db1';

// We attempt to connect to the specific database first
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, 3306);
} catch (mysqli_sql_exception $e) {
    // If database doesn't exist, connect to server only and try to create it
    $conn = new mysqli($db_host, $db_user, $db_pass, "", 3306);
    if (!$conn->connect_error) {
        $conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
        $conn->select_db($db_name);
        
        // Auto-import if SQL exists
        $sqlFile = __DIR__ . '/food_fraud_db1.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $conn->multi_query($sql);
            while ($conn->next_result()) {;} // flush
        }
    }
}

// Fallback error handling
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Google Maps API Key
$google_maps_api_key = 'YOUR_GOOGLE_MAPS_API_KEY'; // Replace with your real API key and enable Maps JavaScript API

// --- AUTO-MIGRATION (HEURISTIC ANOMALY DETECTOR PIVOT) ---
// Column expansion for Forensic Analytics
$result = $conn->query("SHOW COLUMNS FROM scan_history LIKE 'product_name'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE scan_history ADD COLUMN product_name VARCHAR(255) AFTER scanned_bar_number");
}
$result = $conn->query("SHOW COLUMNS FROM scan_history LIKE 'confidence_score'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE scan_history ADD COLUMN confidence_score INT DEFAULT 100 AFTER is_fraud");
}
$result = $conn->query("SHOW COLUMNS FROM scan_history LIKE 'status'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE scan_history ADD COLUMN status VARCHAR(50) DEFAULT 'under_review' AFTER confidence_score");
}
$result = $conn->query("SHOW COLUMNS FROM scan_history LIKE 'evidence_image'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE scan_history ADD COLUMN evidence_image VARCHAR(255) AFTER status");
}
$result = $conn->query("SHOW COLUMNS FROM scan_history LIKE 'latitude'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE scan_history ADD COLUMN latitude DECIMAL(10,8) AFTER evidence_image");
}
$result = $conn->query("SHOW COLUMNS FROM scan_history LIKE 'longitude'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE scan_history ADD COLUMN longitude DECIMAL(11,8) AFTER latitude");
}
$result = $conn->query("SHOW COLUMNS FROM scan_history LIKE 'admin_notes'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE scan_history ADD COLUMN admin_notes TEXT AFTER status");
}

// Ensure Evidence Directory exists
if (!file_exists(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0777, true);
}

// --- TABLE MIGRATIONS ---

// FSSAI Cases
$conn->query("CREATE TABLE IF NOT EXISTS fssai_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) UNIQUE,
    case_type VARCHAR(50),
    report_id INT,
    user_id INT,
    title VARCHAR(255),
    description TEXT,
    evidence TEXT,
    status ENUM('open', 'investigating', 'closed', 'convicted') DEFAULT 'open',
    action_taken TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Public Announcements
$conn->query("CREATE TABLE IF NOT EXISTS public_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    announcement_type ENUM('fraud_alert', 'warning', 'action_taken', 'case_update'),
    case_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Notifications
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255),
    message TEXT,
    type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Contact Messages
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    subject VARCHAR(255),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Learned Formats
$conn->query("CREATE TABLE IF NOT EXISTS learned_formats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode_prefix VARCHAR(10),
    brand_name VARCHAR(100),
    batch_format VARCHAR(20),
    shelf_life_months INT,
    confidence_score INT DEFAULT 0,
    sample_count INT DEFAULT 1,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_prefix_format (barcode_prefix, batch_format)
)");

// --- Helper Functions ---

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

function getUnreadNotifications($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
}

function addNotification($conn, $user_id, $title, $message, $type) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $title, $message, $type);
        return $stmt->execute();
    }
    return false;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
