<?php
/**
 * Shop Product Reviews AJAX Handler
 * Actions: submit, list, helpful
 */

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$db = new ShopDatabase();

// Parse JSON or form body
$body = json_decode(file_get_contents('php://input'), true);
if (is_array($body) && !empty($body)) {
    $_POST = array_merge($_POST, $body);
}

$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$productId = intval($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

switch ($action) {

    // ------------------------------------------------------------------
    // LIST — public, no auth required
    // ------------------------------------------------------------------
    case 'list':
        if (!$productId) jsonError('Missing product_id');

        $page    = max(1, intval($_GET['page'] ?? 1));
        $perPage = 10;
        $offset  = ($page - 1) * $perPage;

        $reviews = $db->resultSet(
            "SELECT r.*, g.first_name, g.last_name
             FROM product_reviews r
             JOIN general_users g ON r.user_id = g.user_id
             WHERE r.product_id = ? AND r.status = 'active' AND r.parent_review_id IS NULL
             ORDER BY r.created_at DESC
             LIMIT $perPage OFFSET $offset",
            [$productId]
        );

        $counts = $db->single(
            "SELECT COUNT(*) as total,
                    AVG(rating) as average,
                    SUM(rating = 5) as five,
                    SUM(rating = 4) as four,
                    SUM(rating = 3) as three,
                    SUM(rating = 2) as two,
                    SUM(rating = 1) as one
             FROM product_reviews
             WHERE product_id = ? AND status = 'active' AND parent_review_id IS NULL",
            [$productId]
        );

        // Did this user already review?
        $userReview = null;
        if (isShopLoggedIn()) {
            $userReview = $db->single(
                "SELECT review_id FROM product_reviews WHERE product_id = ? AND user_id = ? AND parent_review_id IS NULL",
                [$productId, $_SESSION['shop_user_id']]
            );
        }

        jsonSuccess('Reviews loaded', [
            'reviews'      => $reviews,
            'counts'       => $counts,
            'user_reviewed' => !empty($userReview),
        ]);
        break;

    // ------------------------------------------------------------------
    // SUBMIT — requires shop login
    // ------------------------------------------------------------------
    case 'submit':
        if (!isShopLoggedIn()) jsonError('Please login to submit a review', 401);
        if (!$productId)       jsonError('Missing product_id');

        $userId     = $_SESSION['shop_user_id'];
        $rating     = intval($_POST['rating'] ?? 0);
        $reviewText = trim(htmlspecialchars($_POST['review_text'] ?? '', ENT_QUOTES, 'UTF-8'));

        if ($rating < 1 || $rating > 5) {
            jsonError('Please select a rating (1-5 stars)');
        }
        if (empty($reviewText)) {
            jsonError('Review text is required');
        }

        // Product must exist
        $product = $db->single(
            "SELECT product_id FROM marketplace_products WHERE product_id = ? AND status = 'available'",
            [$productId]
        );
        if (!$product) jsonError('Product not found');

        // One review per user per product
        $existing = $db->single(
            "SELECT review_id FROM product_reviews WHERE product_id = ? AND user_id = ? AND parent_review_id IS NULL",
            [$productId, $userId]
        );
        if ($existing) jsonError('You have already reviewed this product');

        // Check verified purchase
        $order = $db->single(
            "SELECT oi.item_id FROM shop_order_items oi
             JOIN shop_orders so ON oi.order_id = so.order_id
             WHERE oi.product_id = ? AND so.buyer_id = ? AND so.payment_status = 'paid'
             LIMIT 1",
            [$productId, $userId]
        );
        $verified = $order ? 1 : 0;

        $db->insert('product_reviews', [
            'product_id'           => $productId,
            'user_id'              => $userId,
            'rating'               => $rating,
            'review_text'          => $reviewText,
            'is_verified_purchase' => $verified,
            'status'               => 'active',
        ]);

        // Update product aggregate stats (if columns exist from migration_v3)
        $db->query(
            "UPDATE marketplace_products
             SET review_count   = (SELECT COUNT(*) FROM product_reviews WHERE product_id = ? AND status = 'active' AND parent_review_id IS NULL),
                 average_rating = (SELECT AVG(rating)  FROM product_reviews WHERE product_id = ? AND status = 'active' AND parent_review_id IS NULL)
             WHERE product_id = ?"
        )->bind(1, $productId)->bind(2, $productId)->bind(3, $productId)->execute();

        jsonSuccess('Review submitted! Thank you for your feedback.');
        break;

    // ------------------------------------------------------------------
    // HELPFUL — toggle helpful count (no duplicate tracking for simplicity)
    // ------------------------------------------------------------------
    case 'helpful':
        $reviewId = intval($_POST['review_id'] ?? 0);
        if (!$reviewId) jsonError('Missing review_id');

        $db->query("UPDATE product_reviews SET helpful_count = helpful_count + 1 WHERE review_id = ?")
           ->bind(1, $reviewId)->execute();

        jsonSuccess('Marked as helpful');
        break;

    default:
        jsonError('Invalid action');
}
