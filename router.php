<?php
/**
 * Router for PHP Built-in Server
 * This file handles routing for the PHP development server
 */

// Get the requested URI
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly with caching headers
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    // Set cache headers for static assets
    $extension = pathinfo($uri, PATHINFO_EXTENSION);
    $cacheableExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
    
    if (in_array(strtolower($extension), $cacheableExtensions)) {
        // Cache static assets for 1 week
        header('Cache-Control: public, max-age=604800');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');
    }
    
    return false; // Serve the requested resource as-is
}

// All other requests go through index.php
require_once __DIR__ . '/index.php';
