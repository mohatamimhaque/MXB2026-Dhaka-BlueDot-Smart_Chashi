<?php
header('Content-Type: application/json');

// Suppress HTML errors, return JSON only
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/config.php';

// Check if user is authenticated
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $db = new Database();
    
    // Get form data
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    $product_type = isset($_POST['product_type']) ? trim($_POST['product_type']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $price_unit = isset($_POST['price_unit']) ? trim($_POST['price_unit']) : '';
    $quantity_available = isset($_POST['quantity_available']) ? floatval($_POST['quantity_available']) : 0;
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $region = isset($_POST['region']) ? trim($_POST['region']) : '';
    $quality_grade = isset($_POST['quality_grade']) ? trim($_POST['quality_grade']) : '';
    $is_negotiable = isset($_POST['is_negotiable']) ? 1 : 0;
    
    // Validate required fields
    if (empty($product_name) || empty($product_type) || $price <= 0 || $quantity_available <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit;
    }
    
    // Verify ownership
    $product = $db->single(
        "SELECT seller_id, image_url FROM marketplace_products WHERE product_id = ? AND seller_id = ?",
        [$product_id, $user_id]
    );
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
        exit;
    }
    
    // Handle image upload
    $image_url = $product['image_url']; // Keep existing image by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/marketplace/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images are allowed']);
            exit;
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'product_' . $product_id . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Delete old image if it exists
            if ($product['image_url']) {
                $old_image_path = __DIR__ . '/../' . $product['image_url'];
                if (file_exists($old_image_path)) {
                    @unlink($old_image_path);
                }
            }
            
            $image_url = 'uploads/marketplace/' . $new_filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    }
    
    // Update product in database
    $db->query("
        UPDATE marketplace_products 
        SET product_name = ?, 
            product_type = ?, 
            description = ?, 
            price = ?, 
            price_unit = ?, 
            quantity_available = ?, 
            unit = ?, 
            category = ?, 
            region = ?, 
            quality_grade = ?, 
            is_negotiable = ?, 
            image_url = ?, 
            updated_at = NOW()
        WHERE product_id = ? AND seller_id = ?
    ")
    ->bind(1, $product_name)
    ->bind(2, $product_type)
    ->bind(3, $description)
    ->bind(4, $price)
    ->bind(5, $price_unit)
    ->bind(6, $quantity_available)
    ->bind(7, $unit)
    ->bind(8, $category)
    ->bind(9, $region)
    ->bind(10, $quality_grade)
    ->bind(11, $is_negotiable)
    ->bind(12, $image_url)
    ->bind(13, $product_id)
    ->bind(14, $user_id)
    ->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Product updated successfully',
        'product_id' => $product_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating product: ' . $e->getMessage()
    ]);
}
?>
