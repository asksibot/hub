<?php
session_start();
require_once '../../config/config.php';

// Debugging: Log POST and SESSION data
file_put_contents("debug.log", print_r($_POST, true));
file_put_contents("debug.log", print_r($_SESSION, true), FILE_APPEND);

// Check if form data was submitted
if (empty($_POST)) {
    die(json_encode(["success" => false, "error" => "No data received."]));
}

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]));
}

// Generate a unique submission ID
$submission_id = bin2hex(random_bytes(16));

// Determine user authentication
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $anon_token = NULL;
} else {
    if (!isset($_SESSION['anon_token'])) {
        $_SESSION['anon_token'] = bin2hex(random_bytes(16));
    }
    $user_id = 0;
    $anon_token = $_SESSION['anon_token'];
}
?>
// Helper function to validate inputs
function validateInput($key, $type, $default = null) {
    if (!isset($_POST[$key]) || $_POST[$key] === "") {
        return $default;
    }
    return $type === 'int' ? (int)$_POST[$key] : htmlspecialchars(trim($_POST[$key]), ENT_QUOTES, 'UTF-8');
}

function validateDate($date) {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

// Required fields
$firstName = validateInput('firstName', 'string', '');
$lastName = validateInput('lastName', 'string', '');
$birthDate = validateInput('birthDate', 'string', '');
$testDate = validateInput('testDate', 'string', '');
$schoolName = validateInput('schoolName', 'string', '');
$examinerName = validateInput('examinerName', 'string', '');
$caregiverName = validateInput('caregiverName', 'string', '');

if (!$firstName || !$lastName || !$birthDate || !$testDate || !validateDate($birthDate) || !validateDate($testDate)) {
    die(json_encode(["success" => false, "error" => "Missing or invalid required fields."]));
}
// Quadrant scores
$quadrants = [
    "Seeking" => validateInput('seeking_total', 'int', 0),
    "Avoiding" => validateInput('avoiding_total', 'int', 0),
    "Sensitivity" => validateInput('sensitivity_total', 'int', 0),
    "Registration" => validateInput('registration_total', 'int', 0)
];

// School factor scores
$school_factors = [
    "Factor 1 (Need for External Supports)" => validateInput('factor1_total', 'int', 0),
    "Factor 2 (Awareness and Attention)" => validateInput('factor2_total', 'int', 0),
    "Factor 3 (Tolerance in Learning Environment)" => validateInput('factor3_total', 'int', 0),
    "Factor 4 (Availability for Learning)" => validateInput('factor4_total', 'int', 0)
];

// Classification of results
function classifyScore($score) {
    if ($score >= 26) return "Much More Than Others";
    if ($score >= 20) return "More Than Others";
    if ($score >= 7) return "Just Like the Majority of Others";
    if ($score >= 1) return "Less Than Others";
    return "Much Less Than Others";
}

$quadrant_classifications = array_map('classifyScore', $quadrants);
$school_factor_classifications = array_map('classifyScore', $school_factors);
