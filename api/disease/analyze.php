<?php
// Disease analysis with mock AI response
$_POST = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!isset($_SESSION['user_id']) || !isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

// Mock AI disease detection response
$mockDiseases = [
    ['disease' => 'Rice Blast', 'severity' => 'high', 'confidence' => 0.92, 'treatment' => 'Apply fungicide immediately. Ensure proper field drainage.'],
    ['disease' => 'Early Blight', 'severity' => 'medium', 'confidence' => 0.85, 'treatment' => 'Remove affected leaves and apply copper-based fungicide.'],
    ['disease' => 'Healthy Plant', 'severity' => 'low', 'confidence' => 0.98, 'treatment' => 'No treatment needed. Continue regular monitoring.'],
    ['disease' => 'Powdery Mildew', 'severity' => 'medium', 'confidence' => 0.88, 'treatment' => 'Apply sulfur dust or neem oil spray twice a week.'],
];

$result = $mockDiseases[array_rand($mockDiseases)];

echo json_encode([
    'success' => true,
    'data' => $result
]);
?>
