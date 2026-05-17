<?php
/**
 * Maintenance Mode Page
 * $__mMsg is set by index.php before including this file.
 */
$_mainMsg = $_mMsg ?? 'We are currently performing scheduled maintenance. Please check back soon.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance — Smart Chashi</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f7ee;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
  .card{background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(85,122,70,.15);padding:48px 40px;max-width:480px;width:100%;text-align:center}
  .icon{font-size:64px;margin-bottom:16px}
  h1{color:#557A46;font-size:1.75rem;margin-bottom:12px}
  p{color:#555;line-height:1.6;font-size:1rem;margin-bottom:24px}
  .badge{display:inline-block;background:#f0fdf4;color:#557A46;border:1px solid #bbf7d0;padding:6px 16px;border-radius:99px;font-size:.85rem;font-weight:600}
  a{color:#557A46;text-decoration:none;font-weight:600}
  a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
  <div class="icon">🌾</div>
  <h1>Under Maintenance</h1>
  <p><?php echo $_mainMsg; ?></p>
  <span class="badge">We'll be back soon</span>
  <p style="margin-top:24px;font-size:.875rem">
    Already have an account? <a href="?page=login">Sign in</a>
  </p>
</div>
</body>
</html>
