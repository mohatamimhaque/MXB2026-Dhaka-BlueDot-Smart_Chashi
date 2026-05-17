<?php
/**
 * Shop Logout Handler
 */

require_once __DIR__ . '/../config/config.php';

// Logout the user
logoutShopUser();

setFlashMessage('success', 'You have been logged out successfully.');

// Redirect to shop home
shopRedirect();
