<?php
session_start();
require_once '../../config/config.php';

// Debug: Log POST and SESSION data
file_put_contents("debug.log", print_r($_POST, true));
file_put_contents("debug.log", print_r($_SESSION, true), FILE_APPEND);

// Check for submitted data
if (empty($_POST)) {
    die(json_encode(["success" => false, "error" => "No data received."]));
}

// Ensure database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]));
}

// ------------------------------------------------------------------
// Generate a unique submission_id
$submission_id = bin2hex(random_bytes(16));

// Determine if the user is logged in or anonymous
if (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];
    $anon_token = NULL;
} else {
    if (!isset($_SESSION['anon_token'])) {
        $_SESSION['anon_token'] = bin2hex(random_bytes(16));
    }
    $user_id = 0;
    $anon_token = $_SESSION['anon_token'];
}

// ------------------------------------------------------------------
// Helper functions for validation
function validateInput($key, $type, $default = null) {
    if (!isset($_POST[$key]) || $_POST[$key] === "") {
        return $default;
    }
    return $type === 'int' ? (int) $_POST[$key] : htmlspecialchars(trim($_POST[$key]), ENT_QUOTES, 'UTF-8');
}
function validateDate($date) {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

// ------------------------------------------------------------------
// Core required fields (as provided by your form)
$firstName    = validateInput('firstName', 'string', '');
$lastName     = validateInput('lastName', 'string', '');
$birthDate    = validateInput('birthDate', 'string', '');
$testDate     = validateInput('testDate', 'string', '');
$schoolName   = validateInput('schoolName', 'string', '');
$examinerName = validateInput('examinerName', 'string', '');
$caregiverName= validateInput('caregiverName', 'string', '');

// Validate required fields and date formats
if (!$firstName || !$lastName || !$birthDate || !$testDate ||
    !validateDate($birthDate) || !validateDate($testDate)) {
    die(json_encode(["success" => false, "error" => "Missing or invalid required fields."]));
}

// ------------------------------------------------------------------
// Additional optional fields (exactly as in your form)
$middleName       = validateInput('middleName', 'string', NULL);
$preferredName    = validateInput('preferredName', 'string', NULL);
$idNumber         = validateInput('idNumber', 'string', NULL);
$contactDuration  = isset($_POST['contactDuration']) ? json_encode($_POST['contactDuration']) : NULL;
$otherContactDays = validateInput('otherContactDays', 'string', NULL);

$auditory_comments  = validateInput('auditory_comments', 'string', NULL);
$visual_comments    = validateInput('visual_comments', 'string', NULL);
$touch_comments     = validateInput('touch_comments', 'string', NULL);
$movement_comments  = validateInput('movement_comments', 'string', NULL);
$behavior_comments  = validateInput('behavior_comments', 'string', NULL);

// ------------------------------------------------------------------
// Raw Scores
$auditory_raw_score = validateInput('auditory_raw_score', 'int', 0);
$visual_raw_score   = validateInput('visual_raw_score', 'int', 0);
$touch_raw_score    = validateInput('touch_raw_score', 'int', 0);
$movement_raw_score = validateInput('movement_raw_score', 'int', 0);
$behavior_raw_score = validateInput('behavior_raw_score', 'int', 0);

// ------------------------------------------------------------------
// Collect raw score fields for individual items based on ranges
// (Based on your table: auditory1-7, visual9-14, touch15-22, movement23-30, behavior33-40)
$categoryRanges = [
    'auditory' => [1, 7],
    'visual'   => [9, 14],
    'touch'    => [15, 22],
    'movement' => [23, 30],
    'behavior' => [33, 40]
];

$scoreFields = [];
foreach ($categoryRanges as $category => $range) {
    list($start, $end) = $range;
    for ($i = $start; $i <= $end; $i++) {
        $key = "{$category}{$i}";
        $scoreFields[$key] = validateInput($key, 'int', 0);
    }
}

// ------------------------------------------------------------------
// Calculate Quadrant Totals (Summing Individual Scores)
$seeking_total = 0;
$avoiding_total = 0;
$sensitivity_total = 0;
$registration_total = 0;

foreach ($scoreFields as $key => $value) {
    // Seeking: auditory1-3, visual9 or visual10, touch15 or touch16, movement23 or movement24
    if (preg_match('/^(?:auditory(?:[1-3])|visual(?:9|10)|touch(?:15|16)|movement(?:23|24))$/', $key)) {
        $seeking_total += $value;
    }
    // Avoiding: auditory4-6, visual11 or visual12, touch17 or touch18, movement25 or movement26
    if (preg_match('/^(?:auditory(?:[4-6])|visual(?:11|12)|touch(?:17|18)|movement(?:25|26))$/', $key)) {
        $avoiding_total += $value;
    }
    // Sensitivity: auditory7, visual13 or visual14, touch19 or touch20, movement27 or movement28
    if (preg_match('/^(?:auditory7|visual(?:13|14)|touch(?:19|20)|movement(?:27|28))$/', $key)) {
        $sensitivity_total += $value;
    }
    // Registration: touch21 or touch22, movement29 or movement30, behavior(?:33|34)
    if (preg_match('/^(?:touch(?:21|22)|movement(?:29|30)|behavior(?:33|34))$/', $key)) {
        $registration_total += $value;
    }
}

// ------------------------------------------------------------------
// Determine Quadrant Classifications
function getClassification($score) {
    if ($score >= 30) return "Definite Difference";
    if ($score >= 20) return "Probable Difference";
    return "Typical";
}
$seeking_total_classification = getClassification($seeking_total);
$avoiding_total_classification = getClassification($avoiding_total);
$sensitivity_total_classification = getClassification($sensitivity_total);
$registration_total_classification = getClassification($registration_total);

// ------------------------------------------------------------------
// Calculate School Factor Raw Scores
$factor1_total = 0;
$factor2_total = 0;
$factor3_total = 0;
$factor4_total = 0;

foreach ($scoreFields as $key => $value) {
    if (in_array($key, ['factor1_1', 'factor1_2', 'factor1_8', 'factor1_9', 'factor1_10', 'factor1_15', 'factor1_16', 'factor1_17', 'factor1_24', 'factor1_26', 'factor1_27', 'factor1_28'])) {
        $factor1_total += $value;
    }
    if (in_array($key, ['factor2_4', 'factor2_11', 'factor2_12', 'factor2_14', 'factor2_18', 'factor2_19', 'factor2_20', 'factor2_29', 'factor2_35', 'factor2_44'])) {
        $factor2_total += $value;
    }
    if (in_array($key, ['factor3_3', 'factor3_5', 'factor3_6', 'factor3_7', 'factor3_21', 'factor3_22', 'factor3_33', 'factor3_38', 'factor3_39', 'factor3_40', 'factor3_41', 'factor3_42'])) {
        $factor3_total += $value;
    }
    if (in_array($key, ['factor4_13', 'factor4_23', 'factor4_30', 'factor4_31', 'factor4_32', 'factor4_34', 'factor4_36', 'factor4_37', 'factor4_43'])) {
        $factor4_total += $value;
    }
}

// ------------------------------------------------------------------
// Classify School Factor Scores
function getSchoolFactorClassification($score) {
    if ($score >= 25) return "Definite Difference";
    if ($score >= 15) return "Probable Difference";
    return "Typical";
}
$factor1_classification = getSchoolFactorClassification($factor1_total);
$factor2_classification = getSchoolFactorClassification($factor2_total);
$factor3_classification = getSchoolFactorClassification($factor3_total);
$factor4_classification = getSchoolFactorClassification($factor4_total);

// ------------------------------------------------------------------
// Check if a submission already exists for this user & testDate
$check_sql = "SELECT submission_id FROM sensory_profile_responses WHERE user_id = ? AND testDate = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("is", $user_id, $testDate);
$check_stmt->execute();
$check_stmt->store_result();
$exists = $check_stmt->num_rows > 0;
$check_stmt->close();

// ------------------------------------------------------------------
// Build field lists for INSERT/UPDATE
$coreTextFields  = ["firstName", "lastName", "birthDate", "testDate", "schoolName", "examinerName", "caregiverName"];
$extraTextFields = ["middleName", "preferredName", "idNumber"];
$extraJsonFields = ["contactDuration", "otherContactDays"];
$extraCommentFields = ["auditory_comments", "visual_comments", "touch_comments", "movement_comments", "behavior_comments"];
$rawScoreFields = ["auditory_raw_score", "visual_raw_score", "touch_raw_score", "movement_raw_score", "behavior_raw_score"];
$quadrantFields = ["seeking_total", "avoiding_total", "sensitivity_total", "registration_total"];
$classificationFields = [
    "seeking_total_classification", 
    "avoiding_total_classification", 
    "sensitivity_total_classification", 
    "registration_total_classification"
];
$factorFields = ["factor1_total", "factor2_total", "factor3_total", "factor4_total"];
$factorClassificationFields = ["factor1_total_classification", "factor2_total_classification", "factor3_total_classification", "factor4_total_classification"];

$insertColumns = array_merge(
    ["submission_id", "user_id", "anon_token"],
    $coreTextFields,
    $extraTextFields,
    $extraJsonFields,
    $extraCommentFields,
    array_keys($scoreFields),
    $rawScoreFields,
    $quadrantFields,
    $classificationFields,
    $factorFields,
    $factorClassificationFields
);

$updateColumns = array_merge(
    $coreTextFields,
    $extraTextFields,
    $extraJsonFields,
    $extraCommentFields,
    array_keys($scoreFields),
    $rawScoreFields,
    $quadrantFields,
    $classificationFields,
    $factorFields,
    $factorClassificationFields
);

// ------------------------------------------------------------------
// Prepare SQL and bind parameters (UPDATE if exists; otherwise, INSERT)
if ($exists) {
    // UPDATE branch: build SET clause dynamically.
    $setClause = "";
    foreach ($updateColumns as $col) {
        $setClause .= "$col = ?, ";
    }
    $setClause = rtrim($setClause, ", ");
    
    $sql = "UPDATE sensory_profile_responses SET $setClause, submission_date = NOW() WHERE user_id = ? AND testDate = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die(json_encode(["success" => false, "error" => "Prepare failed: " . $conn->error]));
    }
    
    // Build bind values in order.
    $bindValues = [];
    foreach ($coreTextFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($extraTextFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($extraJsonFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($extraCommentFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($scoreFields as $col => $value) {
        $bindValues[] = $value;
    }
    foreach ($rawScoreFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($quadrantFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($classificationFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($factorFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($factorClassificationFields as $col) {
        $bindValues[] = $$col;
    }
    // Append WHERE clause parameters: user_id and testDate.
    $bindValues[] = $user_id;
    $bindValues[] = $testDate;
    
    // Build binding types string:
    // For update branch (no submission_id, anon_token, user_id): text fields count = coreTextFields (7) + extraTextFields (3) + extraJsonFields (2) + extraCommentFields (5) + classificationFields (4) + factorClassificationFields (4) = 25
    // Integer fields count = array_keys($scoreFields) (37) + rawScoreFields (5) + quadrantFields (4) + factorFields (4) = 50
    // Then the WHERE clause: user_id ("i") and testDate ("s")
    $types = str_repeat("s", 25) . str_repeat("i", 50) . "is";
    
    $stmt->bind_param($types, ...$bindValues);
    
} else {
    // INSERT branch
    $colList = implode(", ", $insertColumns);
    $placeholders = rtrim(str_repeat("?, ", count($insertColumns)), ", ");
    
    $sql = "INSERT INTO sensory_profile_responses ($colList, submission_date)
            VALUES ($placeholders, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die(json_encode(["success" => false, "error" => "Prepare failed: " . $conn->error]));
    }
    
    $bindValues = [];
    // First three: submission_id (s), user_id (i), anon_token (s)
    $bindValues[] = $submission_id;
    $bindValues[] = $user_id;
    $bindValues[] = $anon_token;
    foreach ($coreTextFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($extraTextFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($extraJsonFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($extraCommentFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($scoreFields as $col => $value) {
        $bindValues[] = $value;
    }
    foreach ($rawScoreFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($quadrantFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($classificationFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($factorFields as $col) {
        $bindValues[] = $$col;
    }
    foreach ($factorClassificationFields as $col) {
        $bindValues[] = $$col;
    }
    
    // Build binding types string:
    // For insert branch, first three: "sis" then text fields count = 25, integer fields count = 50.
    $types = "sis" . str_repeat("s", 25) . str_repeat("i", 50);
    
    $stmt->bind_param($types, ...$bindValues);
}

// ------------------------------------------------------------------
// Execute the query and return JSON response or redirect
if ($stmt->execute()) {
    // If updating, use the existing submission_id; otherwise, use the newly generated one.
    if ($exists) {
        $sql2 = "SELECT submission_id FROM sensory_profile_responses WHERE user_id = ? AND testDate = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("is", $user_id, $testDate);
        $stmt2->execute();
        $stmt2->bind_result($existing_submission_id);
        $stmt2->fetch();
        $stmt2->close();
        $submission_id_to_use = $existing_submission_id;
    } else {
        $submission_id_to_use = $submission_id;
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(["success" => true, "submission_id" => $submission_id_to_use]);
    } else {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['view_report'] = true;
        }
        header("Location: admin_schoolreport.php?submission_id=" . $submission_id_to_use);
        exit();
    }
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>

<!-- ------------------------------------------------------------------
     JavaScript Chart Code (included here or in your HTML template)
--------------------------------------------------------------------- -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Build scores array from quadrant totals
    const scores = <?= json_encode(array_map(function($q) {
        return $q['score'];
    }, [
        ["score" => $seeking_total],
        ["score" => $avoiding_total],
        ["score" => $sensitivity_total],
        ["score" => $registration_total]
    ])) ?>;
    
    if (scores.some(score => score > 0)) {
        const quadrantCtx = document.getElementById("quadrantChart").getContext("2d");
        new Chart(quadrantCtx, {
            type: "bar",
            data: {
                labels: ["Seeking", "Avoiding", "Sensitivity", "Registration"],
                datasets: [{
                    label: "Raw Score",
                    data: scores,
                    backgroundColor: ["#007BFF", "#FF5733", "#28A745", "#FFC107"],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 5 }
                    }
                }
            }
        });
    } else {
        document.getElementById("quadrantChart").style.display = "none";
        console.log("No valid scores available for chart plotting.");
    }
});
</script>

<!-- Make sure your HTML includes a canvas with id "quadrantChart" -->
<canvas id="quadrantChart" width="400" height="200"></canvas>
