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

// Additional fields
$middleName = validateInput('middleName', 'string', NULL);
$preferredName = validateInput('preferredName', 'string', NULL);
$idNumber = validateInput('idNumber', 'string', NULL);
$contactDuration = isset($_POST['contactDuration']) ? json_encode($_POST['contactDuration']) : NULL;
$otherContactDays = validateInput('otherContactDays', 'string', NULL);

$auditory_comments  = validateInput('auditory_comments', 'string', NULL);
$visual_comments    = validateInput('visual_comments', 'string', NULL);
$touch_comments     = validateInput('touch_comments', 'string', NULL);
$movement_comments  = validateInput('movement_comments', 'string', NULL);
$behavior_comments  = validateInput('behavior_comments', 'string', NULL);
// Interpretations for Sensory Profile
$interpretations = [
    "Sensitivity to Environment" => classifyScore($quadrants['Sensitivity']),
    "Resilience and Adaptability" => classifyScore($school_factors['Factor 1 (Need for External Supports)']),
    "Emotional Intensity" => classifyScore($quadrants['Avoiding']),
    "Social Interaction" => classifyScore($school_factors['Factor 3 (Tolerance in Learning Environment)']),
    "Stress Response" => classifyScore($school_factors['Factor 4 (Availability for Learning)'])
];

$comments = [
    "Sensitivity to Environment" => [
        "Much Less Than Others" => "You may not be easily affected by sensory stimuli.",
        "Just Like the Majority of Others" => "You seem to have a balanced awareness.",
        "Much More Than Others" => "You may be highly sensitive to environmental changes."
    ],
    "Resilience and Adaptability" => [
        "Much Less Than Others" => "You may find it challenging to adjust to changes.",
        "Just Like the Majority of Others" => "You demonstrate reasonable adaptability.",
        "Much More Than Others" => "You adapt well to new situations."
    ]
];

$personalized_comments = [];
foreach ($interpretations as $category => $classification) {
    $personalized_comments[$category] = $comments[$category][$classification] ?? "No specific comment available.";
}
$stmt = $conn->prepare("INSERT INTO sensory_profile_responses (submission_id, user_id, anon_token, firstName, lastName, birthDate, testDate, schoolName, examinerName, caregiverName, seeking_total, avoiding_total, sensitivity_total, registration_total, factor1_total, factor2_total, factor3_total, factor4_total, submission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$stmt->bind_param("sissssssssiiiiiii",
    $submission_id, $user_id, $anon_token, $firstName, $lastName, $birthDate, $testDate, $schoolName, $examinerName, $caregiverName,
    $quadrants['Seeking'], $quadrants['Avoiding'], $quadrants['Sensitivity'], $quadrants['Registration'],
    $school_factors['Factor 1 (Need for External Supports)'], $school_factors['Factor 2 (Awareness and Attention)'],
    $school_factors['Factor 3 (Tolerance in Learning Environment)'], $school_factors['Factor 4 (Availability for Learning)']
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "submission_id" => $submission_id, "quadrant_classifications" => $quadrant_classifications, "school_factor_classifications" => $school_factor_classifications, "interpretations" => $interpretations, "comments" => $personalized_comments]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
$stmt = $conn->prepare("INSERT INTO sensory_profile_responses (submission_id, user_id, anon_token, firstName, lastName, birthDate, testDate, schoolName, examinerName, caregiverName, seeking_total, avoiding_total, sensitivity_total, registration_total, factor1_total, factor2_total, factor3_total, factor4_total, submission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$stmt->bind_param("sissssssssiiiiiii",
    $submission_id, $user_id, $anon_token, $firstName, $lastName, $birthDate, $testDate, $schoolName, $examinerName, $caregiverName,
    $quadrants['Seeking'], $quadrants['Avoiding'], $quadrants['Sensitivity'], $quadrants['Registration'],
    $school_factors['Factor 1 (Need for External Supports)'], $school_factors['Factor 2 (Awareness and Attention)'],
    $school_factors['Factor 3 (Tolerance in Learning Environment)'], $school_factors['Factor 4 (Availability for Learning)']
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "submission_id" => $submission_id, "quadrant_classifications" => $quadrant_classifications, "school_factor_classifications" => $school_factor_classifications, "interpretations" => $interpretations, "comments" => $personalized_comments]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Quadrant Scores
    const quadrantScores = <?= json_encode(array_values($quadrants)) ?>;
    const schoolFactorScores = <?= json_encode(array_values($school_factors)) ?>;
    
    // Labels for charts
    const quadrantLabels = ["Seeking", "Avoiding", "Sensitivity", "Registration"];
    const schoolFactorLabels = [
        "Need for External Supports",
        "Awareness and Attention",
        "Tolerance in Learning Environment",
        "Availability for Learning"
    ];

    // Create Quadrant Chart
    const quadrantCtx = document.getElementById("quadrantChart").getContext("2d");
    new Chart(quadrantCtx, {
        type: "bar",
        data: {
            labels: quadrantLabels,
            datasets: [{
                label: "Quadrant Scores",
                data: quadrantScores,
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

    // Create School Factors Chart
    const schoolFactorCtx = document.getElementById("schoolFactorChart").getContext("2d");
    new Chart(schoolFactorCtx, {
        type: "bar",
        data: {
            labels: schoolFactorLabels,
            datasets: [{
                label: "School Factor Scores",
                data: schoolFactorScores,
                backgroundColor: ["#17A2B8", "#6C757D", "#DC3545", "#FFC107"],
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
});
</script>

<!-- HTML Canvas Elements for Charts -->
<canvas id="quadrantChart" width="400" height="200"></canvas>
<canvas id="schoolFactorChart" width="400" height="200"></canvas>
