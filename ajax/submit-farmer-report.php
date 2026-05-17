<?php
/**
 * SmartChashi - Submit Farmer Report AJAX Handler
 * Handles disease report submissions from farmers
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'farmer') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$cropId = intval($_POST['crop_id'] ?? 0);
$diseaseName = trim($_POST['disease_name'] ?? '');
$severity = $_POST['severity'] ?? 'low';
$symptoms = trim($_POST['symptoms'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validation
if (!$cropId) {
    echo json_encode(['success' => false, 'message' => __('please_select_crop')]);
    exit;
}

if (empty($symptoms)) {
    echo json_encode(['success' => false, 'message' => __('please_enter_symptoms')]);
    exit;
}

// Validate severity
$validSeverities = ['low', 'medium', 'high'];
if (!in_array($severity, $validSeverities)) {
    $severity = 'low';
}

$db = new Database();

try {
    // Verify crop belongs to user
    $crop = $db->single("SELECT crop_id FROM crop_data WHERE crop_id = ? AND farmer_id = ?", [$cropId, $currentUser['user_id']]);
    if (!$crop) {
        echo json_encode(['success' => false, 'message' => __('invalid_crop')]);
        exit;
    }

    // Handle image uploads
    $imageUrl = null;
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = PROJECT_ROOT . '/public/uploads/disease_reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedImages = [];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        for ($i = 0; $i < min(count($_FILES['images']['name']), 3); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['images']['tmp_name'][$i];
                $originalName = $_FILES['images']['name'][$i];
                $fileType = $_FILES['images']['type'][$i];
                
                if (in_array($fileType, $allowedTypes)) {
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $newFileName = 'report_' . $currentUser['user_id'] . '_' . time() . '_' . $i . '.' . $extension;
                    $destination = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($tmpName, $destination)) {
                        $uploadedImages[] = 'uploads/disease_reports/' . $newFileName;
                    }
                }
            }
        }
        
        if (!empty($uploadedImages)) {
            $imageUrl = $uploadedImages[0]; // Primary image
        }
    }

    // Combine symptoms and notes
    $fullSymptoms = $symptoms;
    if (!empty($notes)) {
        $fullSymptoms .= "\n\n" . __('additional_notes') . ": " . $notes;
    }

    // Insert disease report
    $query = "INSERT INTO disease_reports (user_id, crop_id, disease_name, severity, symptoms, image_url, detected_date, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'detected')";
    
    $db->query($query);
    $db->bind(1, $currentUser['user_id']);
    $db->bind(2, $cropId);
    $db->bind(3, $diseaseName ?: null);
    $db->bind(4, $severity);
    $db->bind(5, $fullSymptoms);
    $db->bind(6, $imageUrl);
    $db->execute();

    $reportId = $db->lastInsertId();

    // Create notification for officers
    try {
        $officers = $db->resultSet("SELECT user_id FROM users WHERE role = 'officer'");
        if ($officers) {
            foreach ($officers as $officer) {
                $notificationMsg = __('new_disease_report_from') . ' ' . $currentUser['first_name'];
                $db->query("INSERT INTO user_notifications (user_id, user_type, title, message, type, icon, link, reference_id) VALUES (?, 'officer', ?, ?, 'report', 'bug_report', '?page=farmer-reports', ?)")
                   ->bind(1, $officer['user_id'])
                   ->bind(2, __('new_disease_report'))
                   ->bind(3, $notificationMsg)
                   ->bind(4, $reportId)
                   ->execute();
            }
        }
    } catch (Exception $notifError) {
        // Notifications failed but report was saved - continue
        error_log('Notification error: ' . $notifError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => __('report_submitted_successfully'),
        'report_id' => $reportId
    ]);

} catch (Exception $e) {
    error_log('Disease report submission error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => __('error_occurred')]);
}
