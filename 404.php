<?php
/**
 * Custom 404 — Page Not Found
 * Served directly by Apache via ErrorDocument 404, or included from index.php.
 */
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config/config.php';
}
http_response_code(404);

$requestedPath = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Page Not Found — Smart Chashi</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', system-ui, Arial, sans-serif;
    background: #f0f7ee;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
    color: #374151;
}

/* Floating plants animation */
@keyframes sway {
    0%, 100% { transform: rotate(-4deg); }
    50%       { transform: rotate(4deg); }
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-10px); }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}

.scene {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 10px;
    margin-bottom: 32px;
    height: 130px;
    animation: fadeUp 0.6s ease both;
}

.plant {
    display: flex;
    flex-direction: column;
    align-items: center;
    transform-origin: bottom center;
}
.p1 { animation: sway 3.2s ease-in-out infinite; }
.p2 { animation: sway 2.8s ease-in-out infinite 0.4s; }
.p3 { animation: sway 3.6s ease-in-out infinite 0.8s; }

.plant-stem {
    width: 4px;
    background: linear-gradient(180deg, #4ade80, #16a34a);
    border-radius: 2px;
}
.plant-leaf {
    font-size: 32px;
    line-height: 1;
    filter: drop-shadow(0 2px 6px rgba(74,222,128,.35));
}
.plant-leaf.big   { font-size: 48px; }
.plant-leaf.small { font-size: 24px; }

.stem-s { height: 40px; }
.stem-m { height: 60px; }
.stem-l { height: 80px; }

.num404 {
    font-size: 80px;
    font-weight: 900;
    color: #557A46;
    letter-spacing: -4px;
    line-height: 1;
    animation: float 3.5s ease-in-out infinite 0.3s;
    text-shadow: 0 4px 24px rgba(85,122,70,.25);
    user-select: none;
}

.card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 40px rgba(85,122,70,.14), 0 2px 8px rgba(0,0,0,.06);
    padding: 40px 36px 36px;
    max-width: 460px;
    width: 100%;
    text-align: center;
    animation: fadeUp 0.6s ease 0.15s both;
}

.card h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 10px;
}

.card p {
    color: #6b7280;
    font-size: 0.95rem;
    line-height: 1.65;
    margin-bottom: 28px;
}

.path-pill {
    display: inline-block;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
    font-family: 'Courier New', monospace;
    font-size: 0.78rem;
    padding: 4px 12px;
    border-radius: 99px;
    margin-bottom: 24px;
    word-break: break-all;
    max-width: 100%;
}

.btn-group {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    padding: 10px 22px;
    border-radius: 10px;
    transition: opacity 0.15s, transform 0.15s;
    cursor: pointer;
}
.btn:hover { opacity: 0.88; transform: translateY(-1px); }
.btn:active { transform: translateY(0); }

.btn-primary {
    background: linear-gradient(135deg, #1a9c50, #2ecc71);
    color: #fff;
    box-shadow: 0 4px 14px rgba(46,204,113,.35);
}
.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.divider {
    border: none;
    border-top: 1px solid #f0f4f0;
    margin: 28px 0 20px;
}

.quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
}

.quick-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    color: #557A46;
    text-decoration: none;
    background: #f0fdf4;
    border: 1px solid #d1fae5;
    padding: 5px 12px;
    border-radius: 8px;
    transition: background 0.12s;
    font-weight: 500;
}
.quick-link:hover { background: #dcfce7; }

.brand {
    margin-top: 32px;
    font-size: 0.78rem;
    color: #9ca3af;
    animation: fadeUp 0.6s ease 0.3s both;
}
.brand strong { color: #557A46; }

@media (max-width: 480px) {
    .num404 { font-size: 60px; }
    .card { padding: 32px 20px 28px; }
    .card h1 { font-size: 1.25rem; }
}
</style>
</head>
<body>

<!-- Animated plant scene -->
<div class="scene">
    <div class="plant p1">
        <div class="plant-leaf small">🌿</div>
        <div class="plant-stem stem-s"></div>
    </div>
    <div class="plant p2">
        <div class="plant-leaf">🌾</div>
        <div class="plant-stem stem-l"></div>
    </div>
    <div class="num404">404</div>
    <div class="plant p3">
        <div class="plant-leaf big">🌱</div>
        <div class="plant-stem stem-m"></div>
    </div>
    <div class="plant p1" style="animation-delay:1.1s">
        <div class="plant-leaf small">🍃</div>
        <div class="plant-stem stem-s"></div>
    </div>
</div>

<div class="card">
    <h1>Page Not Found</h1>
    <p>Looks like this field hasn't been planted yet. The page you're looking for doesn't exist or may have been moved.</p>

    <?php if ($requestedPath && $requestedPath !== '/smartchashi/404.php'): ?>
    <div class="path-pill"><?php echo $requestedPath; ?></div>
    <?php endif; ?>

    <div class="btn-group">
        <a href="<?php echo $base_url; ?>" class="btn btn-primary">
            &#8962; Go Home
        </a>
        <a href="javascript:history.back()" class="btn btn-secondary">
            &#8592; Go Back
        </a>
    </div>

    <hr class="divider">

    <div class="quick-links">
        <a href="<?php echo $base_url; ?>dashboard" class="quick-link">&#127807; Dashboard</a>
        <a href="<?php echo $base_url; ?>agent/chat/" class="quick-link">&#129302; Chashi Bhai AI</a>
        <a href="<?php echo $base_url; ?>disease" class="quick-link">&#128269; Disease Detection</a>
        <a href="<?php echo $base_url; ?>marketplace" class="quick-link">&#128722; Marketplace</a>
        <a href="<?php echo $base_url; ?>learn" class="quick-link">&#128218; Learning Center</a>
    </div>
</div>

<p class="brand">Powered by <strong>Smart Chashi</strong> — AI-powered agricultural assistant for Bangladesh farmers</p>

</body>
</html>
