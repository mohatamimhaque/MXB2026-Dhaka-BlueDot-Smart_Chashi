<?php
/**
 * File Upload Handler for Message Attachments
 * Accepts image files; returns JSON with URL.
 */

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isShopLoggedIn()) {
    jsonError('Login required', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    jsonError('No file uploaded');
}

$file    = $_FILES['file'];
$maxSize = 5 * 1024 * 1024; // 5 MB
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonError('Upload error');
}
if ($file['size'] > $maxSize) {
    jsonError('File too large (max 5 MB)');
}

// Validate MIME via finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    jsonError('Only JPEG, PNG, GIF, and WebP images are allowed');
}

$ext    = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext    = strtolower($ext ?: 'jpg');
$name   = 'msg_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dir    = SHOP_ROOT . '/uploads/messages/';

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$dest = $dir . $name;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonError('Failed to save file');
}

$url = SHOP_URL . 'uploads/messages/' . $name;
jsonSuccess('Uploaded', ['url' => $url, 'type' => $mime]);
