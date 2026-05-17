<?php
/**
 * Delete Marketplace Product
 * Allows sellers to delete their own products
 */

header('Content-Type: application/json');

// Include necessary files
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = new Database();

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['product_id'] ?? null;
    
    if (!$productId) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }
    
    // Verify product exists and belongs to current user
    $product = $db->single(
        "SELECT * FROM marketplace_products WHERE product_id = ? AND seller_id = ?",
        [$productId, $userId]
    );
    
    if (!$product) {
        echo json_encode([
            'success' => false, 
            'message' => 'Product not found or you do not have permission to delete it'
        ]);
        exit;
    }
    
    // Check if product has pending orders
    $pendingOrders = $db->single(
        "SELECT COUNT(*) as count FROM marketplace_orders 
         WHERE product_id = ? AND order_status IN ('pending', 'confirmed')",
        [$productId]
    );
    
    if ($pendingOrders['count'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete product with pending orders. Please fulfill or cancel them first.'
        ]);
        exit;
    }
    
    // Delete related data first (to maintain referential integrity)
    
    // Delete product reviews
    $db->query("DELETE FROM product_reviews WHERE product_id = ?")
       ->bind(1, $productId)
       ->execute();
    
    // Delete product from wishlists
    $db->query("DELETE FROM product_wishlist WHERE product_id = ?")
       ->bind(1, $productId)
       ->execute();
    
    // Delete product from comparisons
    $db->query("DELETE FROM product_comparisons WHERE product_id = ?")
       ->bind(1, $productId)
       ->execute();
    
    // Delete product offers
    $db->query("DELETE FROM product_offers WHERE product_id = ?")
       ->bind(1, $productId)
       ->execute();
    
    // Delete product images (if stored separately)
    if (!empty($product['image_url'])) {
        $imagePath = __DIR__ . '/../' . $product['image_url'];
        if (file_exists($imagePath)) {
            @unlink($imagePath);
        }
    }
    
    // Finally, delete the product
    $result = $db->query("DELETE FROM marketplace_products WHERE product_id = ?")
                 ->bind(1, $productId)
                 ->execute();
    
    if ($result) {
        // Log the deletion (optional - only if activity_logs table exists)
        try {
            $db->query(
                "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                 VALUES (?, ?, ?, NOW())")
               ->bind(1, $userId)
               ->bind(2, 'product_deleted')
               ->bind(3, "Deleted product: {$product['product_name']}")
               ->execute();
        } catch (Exception $logError) {
            // Ignore if activity_logs table doesn't exist
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete product'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting product',
        'error' => $e->getMessage()
    ]);
}
