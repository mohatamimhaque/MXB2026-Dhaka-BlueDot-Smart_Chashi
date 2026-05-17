<?php
// Redirect logged-in users to their role-specific dashboards
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    
    if ($currentUser['role'] === 'admin') {
        header('Location: ' . $base_url . 'admin-secure/pages/admin-dashboard.php');
        exit;
    } elseif ($currentUser['role'] === 'officer') {
        header('Location: ' . $base_url . '?page=officer-dashboard');
        exit;
    } elseif ($currentUser['role'] === 'farmer') {
        header('Location: ' . $base_url . '?page=farmer-dashboard');
        exit;
    }
}

include __DIR__ . '/../layouts/header.php';

// Get platform statistics for visitors
$stats = [];
$platformStats = [];

// Get platform statistics for visitors (this is always shown to non-logged in users)
try {
    $db = new Database();
    $realUsers = $db->single("SELECT COUNT(*) as count FROM users", [])['count'] ?? 0;
    $realPosts = $db->single("SELECT COUNT(*) as count FROM community_posts", [])['count'] ?? 0;
    $realFarmers = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
    $realCrops = $db->single("SELECT COUNT(*) as count FROM crops", [])['count'] ?? 0;
    $realDetections = $db->single("SELECT COUNT(*) as count FROM disease_detections", [])['count'] ?? 0;
    
    // Show real numbers with minimum base amounts for marketing
    $platformStats['total_users'] = max($realUsers, 8000);
    $platformStats['total_posts'] = max($realPosts, 20000);
    $platformStats['active_farmers'] = max($realFarmers, 10000);
    $platformStats['crops_tracked'] = max($realCrops, 1000);
    $platformStats['disease_detected'] = max($realDetections, 150);
    $platformStats['success_rate'] = 95;
} catch (Exception $e) {
    // Fallback to attractive dummy numbers even if DB fails
    $platformStats = [
        'total_users' => 8000, 
        'total_posts' => 20000, 
        'active_farmers' => 10000,
        'crops_tracked' => 1000,
        'disease_detected' => 150,
        'success_rate' => 95
    ];
}

// Testimonials data (can be made dynamic from database later)
$testimonials = [
    [
        'title' => __('testimonial_1_title'),
        'name' => __('testimonial_1_name'),
        'location' => __('testimonial_1_location'),
        'content' => __('testimonial_1_content'),
        'duration' => __('testimonial_1_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_2_title'),
        'name' => __('testimonial_2_name'),
        'location' => __('testimonial_2_location'),
        'content' => __('testimonial_2_content'),
        'duration' => __('testimonial_2_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_3_title'),
        'name' => __('testimonial_3_name'),
        'location' => __('testimonial_3_location'),
        'content' => __('testimonial_3_content'),
        'duration' => __('testimonial_3_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_4_title'),
        'name' => __('testimonial_4_name'),
        'location' => __('testimonial_4_location'),
        'content' => __('testimonial_4_content'),
        'duration' => __('testimonial_4_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_5_title'),
        'name' => __('testimonial_5_name'),
        'location' => __('testimonial_5_location'),
        'content' => __('testimonial_5_content'),
        'duration' => __('testimonial_5_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_6_title'),
        'name' => __('testimonial_6_name'),
        'location' => __('testimonial_6_location'),
        'content' => __('testimonial_6_content'),
        'duration' => __('testimonial_6_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_7_title'),
        'name' => __('testimonial_7_name'),
        'location' => __('testimonial_7_location'),
        'content' => __('testimonial_7_content'),
        'duration' => __('testimonial_7_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_8_title'),
        'name' => __('testimonial_8_name'),
        'location' => __('testimonial_8_location'),
        'content' => __('testimonial_8_content'),
        'duration' => __('testimonial_8_duration'),
        'rating' => 5
    ],
    [
        'title' => __('testimonial_9_title'),
        'name' => __('testimonial_9_name'),
        'location' => __('testimonial_9_location'),
        'content' => __('testimonial_9_content'),
        'duration' => __('testimonial_9_duration'),
        'rating' => 5
    ]
];
?>

<!-- Hero Section - Modern Design -->
<section class="hero-modern">
    <div class="hero-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="material-icons">verified</span>
            <span><?php echo __('trusted_platform'); ?></span>
        </div>
        <h1 class="hero-title">
            <span class="gradient-text"><?php echo __('smart_chashi'); ?></span>
        </h1>
        <p class="hero-tagline">
            <span class="material-icons">agriculture</span>
            <?php echo __('ai_farming_assistant'); ?>
            <span class="material-icons">smart_toy</span>
        </p>
        <p class="hero-description"><?php echo __('get_smart_farming'); ?></p>
        
        <!-- Platform Statistics -->
        <div class="hero-stats-modern">
            <div class="stat-card-modern">
                <div class="stat-icon-wrap stat-gradient-green">
                    <span class="material-icons">people</span>
                </div>
                <div class="stat-info">
                    <span class="stat-number" data-value="<?php echo $platformStats['total_users']; ?>"><?php echo format_number($platformStats['total_users']); ?>+</span>
                    <span class="stat-label"><?php echo __('active_users'); ?></span>
                </div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-icon-wrap stat-gradient-blue">
                    <span class="material-icons">agriculture</span>
                </div>
                <div class="stat-info">
                    <span class="stat-number" data-value="<?php echo $platformStats['active_farmers']; ?>"><?php echo format_number($platformStats['active_farmers']); ?>+</span>
                    <span class="stat-label"><?php echo __('farmers'); ?></span>
                </div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-icon-wrap stat-gradient-orange">
                    <span class="material-icons">article</span>
                </div>
                <div class="stat-info">
                    <span class="stat-number" data-value="<?php echo $platformStats['total_posts']; ?>"><?php echo format_number($platformStats['total_posts']); ?>+</span>
                    <span class="stat-label"><?php echo __('posts_shared'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="hero-buttons">
            <a href="<?php echo $base_url; ?>?page=login" class="btn-hero btn-primary-hero">
                <span class="material-icons">login</span>
                <?php echo __('login'); ?>
            </a>
            <a href="<?php echo $base_url; ?>?page=register" class="btn-hero btn-secondary-hero">
                <span class="material-icons">person_add</span>
                <?php echo __('register'); ?>
            </a>
        </div>
    </div>
    <div class="hero-illustration">
        <div class="floating-card fc-1">
            <span class="material-icons">eco</span>
            <span><?php echo __('crops'); ?></span>
        </div>
        <div class="floating-card fc-2">
            <span class="material-icons">bug_report</span>
            <span><?php echo __('disease_detection'); ?></span>
        </div>
        <div class="floating-card fc-3">
            <span class="material-icons">chat</span>
            <span>AI</span>
        </div>
    </div>
</section>

<!-- Features Grid - Modern Cards -->
<section class="features-section">
    <div class="section-header">
        <h2 class="section-title-modern">
            <span class="material-icons">apps</span>
            <?php echo __('our_features'); ?>
        </h2>
        <p class="section-subtitle"><?php echo __('features_subtitle'); ?></p>
    </div>
    
    <div class="features-grid-modern">
        <div class="feature-card-modern" data-feature="crops">
            <div class="feature-glow"></div>
            <div class="feature-icon-modern">
                <span class="material-icons">agriculture</span>
            </div>
            <h3><?php echo __('crops'); ?></h3>
            <p><?php echo __('track_manage_crops'); ?></p>
            <div class="feature-footer">
                <span class="feature-tag"><?php echo __('login_to_access'); ?></span>
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>

        <div class="feature-card-modern" data-feature="disease">
            <div class="feature-glow"></div>
            <div class="feature-icon-modern feature-icon-red">
                <span class="material-icons">bug_report</span>
            </div>
            <h3><?php echo __('disease_check'); ?></h3>
            <p><?php echo __('detect_crop_diseases'); ?></p>
            <div class="feature-footer">
                <span class="feature-tag"><?php echo __('login_to_access'); ?></span>
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>

        <div class="feature-card-modern" data-feature="agent">
            <div class="feature-glow"></div>
            <div class="feature-icon-modern feature-icon-blue">
                <span class="material-icons">smart_toy</span>
            </div>
            <h3><?php echo __('ai_assistant'); ?></h3>
            <p><?php echo __('ask_farming_questions'); ?></p>
            <div class="feature-footer">
                <span class="feature-tag"><?php echo __('login_to_access'); ?></span>
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>

        <div class="feature-card-modern" data-feature="weather">
            <div class="feature-glow"></div>
            <div class="feature-icon-modern feature-icon-cyan">
                <span class="material-icons">wb_sunny</span>
            </div>
            <h3><?php echo __('weather'); ?></h3>
            <p><?php echo __('realtime_weather'); ?></p>
            <div class="feature-footer">
                <span class="feature-tag"><?php echo __('login_to_access'); ?></span>
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>

        <div class="feature-card-modern" data-feature="marketplace">
            <div class="feature-glow"></div>
            <div class="feature-icon-modern feature-icon-purple">
                <span class="material-icons">storefront</span>
            </div>
            <h3><?php echo __('marketplace'); ?></h3>
            <p><?php echo __('buy_sell_products'); ?></p>
            <div class="feature-footer">
                <span class="feature-tag"><?php echo __('login_to_access'); ?></span>
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>

        <div class="feature-card-modern" data-feature="community">
            <div class="feature-glow"></div>
            <div class="feature-icon-modern feature-icon-teal">
                <span class="material-icons">groups</span>
            </div>
            <h3><?php echo __('community'); ?></h3>
            <p><?php echo __('connect_farmers'); ?></p>
            <div class="feature-footer">
                <span class="feature-tag"><?php echo __('login_to_access'); ?></span>
                <span class="material-icons">arrow_forward</span>
            </div>
        </div>
    </div>
</section>

<!-- Platform Impact Statistics - Modern -->
<section class="impact-section">
    <div class="impact-content">
        <h2 class="impact-title">
            <span class="material-icons">trending_up</span>
            <?php echo __('making_real_impact'); ?>
        </h2>
        <div class="impact-stats-grid">
            <div class="impact-stat-card">
                <div class="impact-stat-icon">
                    <span class="material-icons">agriculture</span>
                </div>
                <span class="impact-stat-number"><?php echo format_number($platformStats['crops_tracked']); ?>+</span>
                <span class="impact-stat-label"><?php echo __('crops_tracked'); ?></span>
            </div>
            <div class="impact-stat-card">
                <div class="impact-stat-icon">
                    <span class="material-icons">bug_report</span>
                </div>
                <span class="impact-stat-number"><?php echo format_number($platformStats['disease_detected']); ?>+</span>
                <span class="impact-stat-label"><?php echo __('diseases_detected'); ?></span>
            </div>
            <div class="impact-stat-card">
                <div class="impact-stat-icon">
                    <span class="material-icons">verified</span>
                </div>
                <span class="impact-stat-number"><?php echo bn_number($platformStats['success_rate']); ?>%</span>
                <span class="impact-stat-label"><?php echo __('success_rate'); ?></span>
            </div>
            <div class="impact-stat-card">
                <div class="impact-stat-icon">
                    <span class="material-icons">support_agent</span>
                </div>
                <span class="impact-stat-number"><?php echo bn_number('24/7'); ?></span>
                <span class="impact-stat-label"><?php echo __('ai_support'); ?></span>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us - Modern Cards -->
<section class="why-choose-section">
    <div class="section-header">
        <h2 class="section-title-modern">
            <span class="material-icons">star</span>
            <?php echo __('why_choose_smart_chashi'); ?>
        </h2>
    </div>
    
    <div class="benefits-grid">
        <div class="benefit-card">
            <div class="benefit-icon">
                <span class="material-icons">smartphone</span>
            </div>
            <h4><?php echo __('mobile_first_design'); ?></h4>
            <p><?php echo __('mobile_first_desc'); ?></p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <span class="material-icons">lock</span>
            </div>
            <h4><?php echo __('secure_private'); ?></h4>
            <p><?php echo __('secure_private_desc'); ?></p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <span class="material-icons">cloud_off</span>
            </div>
            <h4><?php echo __('works_offline'); ?></h4>
            <p><?php echo __('works_offline_desc'); ?></p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <span class="material-icons">attach_money</span>
            </div>
            <h4><?php echo __('free_open'); ?></h4>
            <p><?php echo __('free_open_desc'); ?></p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <span class="material-icons">translate</span>
            </div>
            <h4><?php echo __('bangla_support'); ?></h4>
            <p><?php echo __('bangla_support_desc'); ?></p>
        </div>

        <div class="benefit-card">
            <div class="benefit-icon">
                <span class="material-icons">update</span>
            </div>
            <h4><?php echo __('always_updated'); ?></h4>
            <p><?php echo __('always_updated_desc'); ?></p>
        </div>
    </div>
</section>

<!-- Testimonials Section - Animated Scroll -->
<section class="testimonials-section">
    <div class="section-header">
        <h2 class="section-title-modern">
            <span class="material-icons">format_quote</span>
            <?php echo __('success_stories'); ?>
        </h2>
        <p class="section-subtitle"><?php echo __('hear_from_farmers'); ?></p>
    </div>
    
    <div class="testimonial-scroll-container">
        <div class="testimonial-scroll-track">
            <?php foreach ($testimonials as $index => $testimonial): ?>
            <div class="testimonial-card">
                <div class="testimonial-card-inner">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">
                            <span class="material-icons">person</span>
                        </div>
                        <div class="testimonial-author">
                            <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                            <p><?php echo htmlspecialchars($testimonial['location']); ?></p>
                        </div>
                        <div class="testimonial-badge">
                            <span class="material-icons">verified</span>
                        </div>
                    </div>
                    <h3 class="testimonial-title"><?php echo htmlspecialchars($testimonial['title']); ?></h3>
                    <p class="testimonial-content">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                    <div class="testimonial-footer">
                        <div class="testimonial-rating">
                            <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                            <span class="material-icons">star</span>
                            <?php endfor; ?>
                        </div>
                        <span class="testimonial-duration"><?php echo htmlspecialchars($testimonial['duration']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <!-- Duplicate for infinite scroll -->
            <?php foreach ($testimonials as $index => $testimonial): ?>
            <div class="testimonial-card">
                <div class="testimonial-card-inner">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">
                            <span class="material-icons">person</span>
                        </div>
                        <div class="testimonial-author">
                            <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                            <p><?php echo htmlspecialchars($testimonial['location']); ?></p>
                        </div>
                        <div class="testimonial-badge">
                            <span class="material-icons">verified</span>
                        </div>
                    </div>
                    <h3 class="testimonial-title"><?php echo htmlspecialchars($testimonial['title']); ?></h3>
                    <p class="testimonial-content">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                    <div class="testimonial-footer">
                        <div class="testimonial-rating">
                            <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                            <span class="material-icons">star</span>
                            <?php endfor; ?>
                        </div>
                        <span class="testimonial-duration"><?php echo htmlspecialchars($testimonial['duration']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="carousel-controls-modern">
        <button class="carousel-btn-modern" id="prevTestimonial">
            <span class="material-icons">chevron_left</span>
        </button>
        <div class="carousel-dots-modern" id="carouselDots">
            <?php foreach ($testimonials as $index => $testimonial): ?>
            <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></span>
            <?php endforeach; ?>
        </div>
        <button class="carousel-btn-modern" id="nextTestimonial">
            <span class="material-icons">chevron_right</span>
        </button>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section">
    <div class="cta-content">
        <h2><?php echo __('ready_to_start'); ?></h2>
        <p><?php echo __('join_thousands_farmers'); ?></p>
        <div class="cta-buttons">
            <a href="<?php echo $base_url; ?>?page=register" class="btn-cta btn-cta-primary">
                <span class="material-icons">rocket_launch</span>
                <?php echo __('get_started_free'); ?>
            </a>
            <a href="<?php echo $base_url; ?>?page=login" class="btn-cta btn-cta-secondary">
                <span class="material-icons">login</span>
                <?php echo __('login'); ?>
            </a>
        </div>
    </div>
</section>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

/* ============================================
   HOME PAGE - MODERN DESIGN SYSTEM
   ============================================ */

:root {
    --gradient-green: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    --gradient-blue: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    --gradient-orange: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
    --gradient-red: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    --gradient-purple: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    --gradient-cyan: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
    --gradient-teal: linear-gradient(135deg, #20c997 0%, #28a745 100%);
    --shadow-soft: 0 4px 20px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 30px rgba(0,0,0,0.12);
    --shadow-strong: 0 15px 40px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --border-radius-sm: 12px;
    --transition-fast: 0.2s ease;
    --transition-medium: 0.3s ease;
}

/* Hero Section - Modern */
.hero-modern {
    position: relative;
    background: linear-gradient(135deg, #557a46 0%, #3d5a35 100%);
    border-radius: 16px;
    padding: 3.5rem 2rem;
    margin-bottom: 3rem;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 450px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.hero-particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    pointer-events: none;
}

.hero-particles .particle {
    position: absolute;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    animation: float 6s ease-in-out infinite;
}

.hero-particles .particle:nth-child(1) {
    top: -100px;
    right: -50px;
}

.hero-particles .particle:nth-child(2) {
    bottom: -150px;
    left: 10%;
    animation-delay: -2s;
}

.hero-particles .particle:nth-child(3) {
    top: 50%;
    left: 50%;
    width: 200px;
    height: 200px;
    animation-delay: -4s;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(5deg); }
}

.hero-content {
    position: relative;
    z-index: 2;
    color: white;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0 2rem;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(20px);
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 2rem;
    border: 1px solid rgba(255,255,255,0.2);
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(12px);
    padding: 0.625rem 1.25rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255,255,255,0.25);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    letter-spacing: 0.01em;
}

.hero-badge:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.hero-badge .material-icons {
    font-size: 1.125rem;
    color: #ffd54f;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 800;
    margin: 0 0 1.5rem;
    line-height: 1.3;
    letter-spacing: -0.03em;
    font-family: 'Inter', sans-serif;
    padding: 0.25rem 0;
    overflow: visible;
}

.gradient-text {
    background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    filter: drop-shadow(0 4px 16px rgba(255,255,255,0.25));
    display: inline-block;
    padding: 0.1rem 0.2rem;
}

.hero-tagline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    font-size: 1.125rem;
    font-weight: 600;
    opacity: 0.98;
    margin: 0 0 1rem;
    flex-wrap: wrap;
    background: rgba(255,255,255,0.08);
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
    letter-spacing: 0.005em;
}

.hero-tagline .material-icons {
    font-size: 1.25rem;
    color: #86efac;
}

.hero-description {
    font-size: 1.125rem;
    opacity: 0.95;
    margin: 0;
    padding: 0 0 2rem 0;
    line-height: 1.7;
    max-width: 700px;
    font-weight: 500;
    letter-spacing: 0.01em;
    color: rgba(255,255,255,0.95);
}

/* Hero Stats Modern */
.hero-stats-modern {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin: 0 auto 1rem auto;
    max-width: 500px;
}

.stat-card-modern {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    background: transparent;
    padding: 0.5rem 0.375rem;
    border-radius: 8px;
    border: none;
    box-shadow: none;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card-modern:hover {
    transform: translateY(-3px);
}

.stat-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.stat-card-modern:hover .stat-icon-wrap {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
}

.stat-gradient-green { 
    background: #557a46;
}
.stat-gradient-blue { 
    background: #3b82f6;
}
.stat-gradient-orange { 
    background: #f59e0b;
}

.stat-icon-wrap .material-icons {
    font-size: 1rem;
}

.stat-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.125rem;
    position: relative;
    z-index: 1;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.2;
    color: #ffffff;
    margin: 0;
    letter-spacing: -0.01em;
    font-family: 'Inter', sans-serif;
}

.stat-label {
    font-size: 0.625rem;
    font-weight: 600;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
    font-family: 'Inter', sans-serif;
}
    color: #4a5568;
    margin-top: 0.25rem;
}

/* Hero Buttons */
.hero-buttons {
    display: flex;
    gap: 4rem;
    flex-wrap: wrap;
    justify-content: center;
    max-width: 700px;
    width: 100%;
    margin: 0 auto;
}

.btn-hero {
    flex: 1;
    min-width: 170px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding: 1.125rem 2.25rem;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(0,0,0,0.18);
    position: relative;
    overflow: hidden;
    letter-spacing: 0.01em;
    font-family: 'Inter', sans-serif;
}

.btn-hero .material-icons {
    font-size: 1.2rem;
}

.btn-primary-hero {
    background: #ffffff;
    color: #3d5a35;
    border: none;
}

.btn-primary-hero:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 16px 40px rgba(0,0,0,0.3);
    background: #fafafa;
}

.btn-secondary-hero {
    background: rgba(255,255,255,0.08);
    color: white;
    border: 2px solid rgba(255,255,255,0.75);
    backdrop-filter: blur(12px);
}

.btn-secondary-hero:hover {
    background: rgba(255,255,255,0.18);
    border-color: rgba(255,255,255,0.95);
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 16px 40px rgba(255,255,255,0.25);
}

/* Hero Illustration */
.hero-illustration {
    position: relative;
    width: 300px;
    height: 300px;
    display: none;
}

@media (min-width: 992px) {
    .hero-illustration {
        display: block;
    }
}

.floating-card {
    position: absolute;
    background: white;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    display: flex;
    align-items: center;
    gap: 0.625rem;
    animation: floatCard 3s ease-in-out infinite;
    color: #333;
    min-height: 48px;
}

.floating-card .material-icons {
    color: var(--primary);
    font-size: 1.5rem;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.floating-card span:last-child {
    font-weight: 600;
    font-size: 0.875rem;
    line-height: 1.2;
    white-space: nowrap;
    font-family: 'Inter', sans-serif;
}

.fc-1 { top: 0; right: 0; animation-delay: 0s; }
.fc-2 { top: 40%; left: 0; animation-delay: -1s; }
.fc-3 { bottom: 20%; right: 10%; animation-delay: -2s; }

@keyframes floatCard {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

/* Section Header */
.section-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.section-title-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
    margin: 0 0 0.75rem;
    font-family: 'Inter', sans-serif;
}

.section-title-modern .material-icons {
    color: var(--primary);
    font-size: 2rem;
}

.section-subtitle {
    color: #666;
    font-size: 1rem;
    margin: 0;
}

/* Features Section */
.features-section {
    margin-bottom: 3rem;
}

.features-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.75rem;
}

@media (min-width: 992px) {
    .features-grid-modern {
        grid-template-columns: repeat(3, 1fr);
    }
}

.feature-card-modern {
    position: relative;
    background: #ffffff;
    border-radius: 18px;
    padding: 2.25rem 1.75rem;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 2px solid #f0f0f0;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
}

.feature-card-modern:hover {
    transform: translateY(-10px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    border-color: #e0e0e0;
}

.feature-glow {
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(85, 122, 70, 0.1) 0%, transparent 70%);
    opacity: 0;
    transition: var(--transition-medium);
}

.feature-card-modern:hover .feature-glow {
    opacity: 1;
    transform: scale(2);
}

.feature-icon-modern {
    width: 64px;
    height: 64px;
    background: var(--gradient-green);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 0 1.25rem 0;
    transition: all 0.4s ease;
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}

.feature-card-modern:hover .feature-icon-modern {
    transform: translateY(-6px) scale(1.08);
    box-shadow: 0 10px 30px rgba(0,0,0,0.18);
}

.feature-icon-red { background: var(--gradient-red); }
.feature-icon-blue { background: var(--gradient-blue); }
.feature-icon-cyan { background: var(--gradient-cyan); }
.feature-icon-purple { background: var(--gradient-purple); }
.feature-icon-teal { background: var(--gradient-teal); }

.feature-icon-modern .material-icons {
    font-size: 1.875rem;
    color: white;
}

.feature-card-modern h3 {
    margin: 0 0 0.875rem 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #1a1a1a;
    text-align: center;
}

.feature-card-modern p {
    margin: 0 0 1.25rem 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.6;
    text-align: center;
}

.feature-footer {
    display: flex;
    align-items: center;
    justify-content: center;
}

.feature-tag {
    font-size: 0.8rem;
    color: #888;
    background: #f5f5f5;
    padding: 0.4rem 0.8rem;
    border-radius: 50px;
}

.feature-footer .material-icons {
    color: var(--primary);
    opacity: 0;
    transform: translateX(-10px);
    transition: var(--transition-fast);
}

.feature-card-modern:hover .feature-footer .material-icons {
    opacity: 1;
    transform: translateX(0);
}

/* Impact Section */
.impact-section {
    background: linear-gradient(135deg, var(--primary) 0%, #2d5a27 100%);
    border-radius: var(--border-radius);
    padding: 3rem 2rem;
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
}

.impact-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -30%;
    width: 500px;
    height: 500px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
}

.impact-content {
    position: relative;
    z-index: 1;
}

.impact-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: white;
    font-size: 2rem;
    margin: 0 0 2.5rem;
    text-align: center;
}

.impact-title .material-icons {
    font-size: 2.5rem;
}

.impact-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.impact-stat-card {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius-sm);
    padding: 2rem 1.5rem;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.2);
    transition: var(--transition-fast);
}

.impact-stat-card:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-5px);
}

.impact-stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.impact-stat-icon .material-icons {
    font-size: 1.75rem;
    color: white;
}

.impact-stat-number {
    display: block;
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 0.5rem;
}

.impact-stat-label {
    display: block;
    font-size: 0.95rem;
    color: rgba(255,255,255,0.9);
}

/* Why Choose Section */
.why-choose-section {
    margin-bottom: 3rem;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.benefit-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
    text-align: center;
    box-shadow: var(--shadow-soft);
    transition: var(--transition-medium);
    border: 1px solid transparent;
}

.benefit-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
    border-color: var(--primary);
}

.benefit-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1.5rem;
    background: rgba(85, 122, 70, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-fast);
}

.benefit-card:hover .benefit-icon {
    background: var(--primary);
}

.benefit-icon .material-icons {
    font-size: 1.75rem;
    color: var(--primary);
    transition: var(--transition-fast);
}

.benefit-card:hover .benefit-icon .material-icons {
    color: white;
}

.benefit-card h4 {
    margin: 0 0 0.75rem;
    font-size: 1.1rem;
    color: #333;
}

.benefit-card p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.6;
}

/* Testimonials Section */
.testimonials-section {
    margin-bottom: 3rem;
}

/* Testimonial Scroll Container */
.testimonial-scroll-container {
    overflow: hidden;
    position: relative;
    padding: 1rem 0;
}

.testimonial-scroll-track {
    display: flex;
    gap: 1.5rem;
    animation: scroll-testimonials 60s linear infinite;
    width: max-content;
}

.testimonial-scroll-track:hover {
    animation-play-state: paused;
}

@keyframes scroll-testimonials {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}

.testimonial-card {
    flex: 0 0 400px;
    max-width: 400px;
}

.testimonial-card-inner {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
    height: 100%;
    border: 1px solid #eee;
    transition: var(--transition-medium);
}

.testimonial-card-inner:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
    border-color: var(--primary);
}

@media (max-width: 768px) {
    .testimonial-card {
        flex: 0 0 320px;
        max-width: 320px;
    }
    
    .testimonial-card-inner {
        padding: 1.25rem;
    }
    
    .testimonial-scroll-track {
        animation-duration: 40s;
    }
}

.testimonial-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.testimonial-avatar {
    width: 50px;
    height: 50px;
    background: var(--gradient-green);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
}

.testimonial-author {
    flex: 1;
}

.testimonial-author h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    color: #333;
}

.testimonial-author p {
    margin: 0;
    font-size: 0.85rem;
    color: #888;
}

.testimonial-badge {
    width: 32px;
    height: 32px;
    background: rgba(40, 167, 69, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.testimonial-badge .material-icons {
    font-size: 1rem;
    color: #28a745;
}

.testimonial-title {
    margin: 0 0 1rem;
    font-size: 1.1rem;
    color: var(--primary);
}

.testimonial-content {
    margin: 0 0 1.5rem;
    color: #555;
    font-size: 0.95rem;
    line-height: 1.7;
    font-style: italic;
}

.testimonial-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.testimonial-rating {
    display: flex;
    gap: 0.25rem;
}

.testimonial-rating .material-icons {
    font-size: 1.1rem;
    color: #ffc107;
}

.testimonial-duration {
    font-size: 0.8rem;
    color: #888;
}

/* Carousel Controls Modern */
.carousel-controls-modern {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
}

.carousel-btn-modern {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 2px solid #e0e0e0;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    color: #557A46;
}

.carousel-btn-modern:hover {
    background: #557A46;
    color: white;
    border-color: #557A46;
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(85, 122, 70, 0.3);
}

.carousel-btn-modern .material-icons {
    font-size: 1.5rem;
    line-height: 1;
}

.carousel-dots-modern {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.carousel-dots-modern .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ddd;
    cursor: pointer;
    transition: all 0.3s ease;
}

.carousel-dots-modern .dot:hover {
    background: #bbb;
}

.carousel-dots-modern .dot.active {
    background: #557A46;
    width: 28px;
    border-radius: 5px;
}

/* CTA Section */
.cta-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: var(--border-radius);
    padding: 3rem 2rem;
    margin-bottom: 2rem;
    text-align: center;
}

.cta-content h2 {
    margin: 0 0 1rem;
    font-size: 2rem;
    color: #333;
}

.cta-content p {
    margin: 0 0 2rem;
    color: #666;
    font-size: 1.1rem;
}

.cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition-medium);
}

.btn-cta-primary {
    background: var(--primary);
    color: white;
}

.btn-cta-primary:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}

.btn-cta-secondary {
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-cta-secondary:hover {
    background: var(--primary);
    color: white;
}

/* Responsive */
@media (max-width: 992px) {
    .hero-modern {
        flex-direction: column;
        text-align: center;
        padding: 2rem 1.5rem;
        min-height: auto;
    }

    .hero-title {
        font-size: 2.5rem;
        justify-content: center;
    }

    .hero-tagline {
        justify-content: center;
        font-size: 1rem;
        padding: 0.625rem 1rem;
    }

    .hero-description {
        font-size: 1rem;
        padding: 0 0 1.5rem 0;
    }

    .hero-stats-modern {
        justify-content: center;
        max-width: 400px;
    }

    .hero-buttons {
        justify-content: center;
        gap: 2rem;
    }

    .btn-hero {
        min-width: 150px;
        padding: 1rem 1.75rem;
        font-size: 0.95rem;
    }
}

@media (max-width: 768px) {
    .hero-modern {
        padding: 2rem 1rem;
        min-height: 350px;
    }

    .hero-badge {
        font-size: 0.8rem;
        padding: 0.5rem 0.875rem;
    }

    .hero-title {
        font-size: 2rem;
        margin-bottom: 1rem;
        line-height: 1.35;
        padding: 0.2rem 0;
    }

    .hero-tagline {
        font-size: 0.95rem;
        padding: 0.5rem 1rem;
        gap: 0.5rem;
    }

    .hero-tagline .material-icons {
        font-size: 1.125rem;
    }

    .hero-description {
        font-size: 0.95rem;
        line-height: 1.6;
        padding: 0 0 1.25rem 0;
        max-width: 100%;
    }

    .hero-stats-modern {
        flex-direction: row;
        gap: 0.375rem;
        max-width: 100%;
    }

    .stat-card-modern {
        justify-content: center;
        padding: 0.375rem 0.25rem;
    }

    .stat-icon-wrap {
        width: 28px;
        height: 28px;
    }

    .stat-icon-wrap .material-icons {
        font-size: 0.875rem;
    }

    .stat-number {
        font-size: 1rem;
    }

    .stat-label {
        font-size: 0.5rem;
    }

    .hero-buttons {
        gap: 1.5rem;
        max-width: 100%;
    }

    .btn-hero {
        min-width: 140px;
        padding: 0.875rem 1.5rem;
        font-size: 0.9rem;
        border-radius: 12px;
    }

    .btn-hero .material-icons {
        font-size: 1.1rem;
    }

    .features-grid-modern {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .feature-card-modern {
        padding: 1.25rem;
    }

    .section-title-modern {
        font-size: 1.5rem;
    }

    .impact-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .benefits-grid {
        grid-template-columns: 1fr;
    }

    .testimonial-card {
        min-width: 100%;
    }

    .cta-content h2 {
        font-size: 1.5rem;
        text-align: center;
        margin: 0 auto 1rem;
        max-width: 100%;
    }

    .cta-content p {
        font-size: 1rem;
        text-align: center;
        margin: 0 auto 1.5rem;
        max-width: 100%;
        padding: 0 0.5rem;
    }

    .cta-buttons {
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        width: 100%;
    }

    .btn-cta {
        width: 100%;
        max-width: 280px;
        justify-content: center;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .hero-modern {
        padding: 1.5rem 0.875rem;
        min-height: 320px;
    }

    .hero-badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        gap: 0.375rem;
    }

    .hero-badge .material-icons {
        font-size: 1rem;
    }

    .hero-title {
        font-size: 1.75rem;
        margin-bottom: 0.875rem;
        line-height: 1.4;
        padding: 0.2rem 0;
    }

    .hero-tagline {
        font-size: 0.875rem;
        padding: 0.5rem 0.875rem;
        gap: 0.375rem;
    }

    .hero-description {
        font-size: 0.875rem;
        padding: 0 0 1rem 0;
    }

    .hero-stats-modern {
        gap: 0.25rem;
        margin-bottom: 1rem;
    }

    .stat-card-modern {
        padding: 0.25rem 0.125rem;
    }

    .stat-icon-wrap {
        width: 24px;
        height: 24px;
        border-radius: 6px;
    }

    .stat-icon-wrap .material-icons {
        font-size: 0.75rem;
    }

    .stat-number {
        font-size: 0.875rem;
    }

    .stat-label {
        font-size: 0.4rem;
        letter-spacing: 0.02em;
    }

    .hero-buttons {
        flex-direction: column;
        gap: 1rem;
        max-width: 280px;
    }

    .btn-hero {
        width: 100%;
        min-width: auto;
        padding: 0.875rem 1.25rem;
        font-size: 0.875rem;
    }

    .impact-stats-grid {
        grid-template-columns: 1fr;
    }

    .impact-stat-number {
        font-size: 2rem;
    }

    .section-title-modern {
        font-size: 1.25rem;
        gap: 0.5rem;
    }

    .section-title-modern .material-icons {
        font-size: 1.5rem;
    }

    .section-subtitle {
        font-size: 0.9rem;
    }

    .cta-section {
        padding: 2rem 1rem;
        margin-bottom: 1rem;
    }

    .cta-content h2 {
        font-size: 1.25rem;
        text-align: center;
        margin: 0 auto 0.875rem;
        line-height: 1.4;
    }

    .cta-content p {
        font-size: 0.9rem;
        text-align: center;
        margin: 0 auto 1.25rem;
        line-height: 1.6;
        padding: 0 0.5rem;
    }

    .cta-buttons {
        flex-direction: column;
        align-items: center;
        gap: 0.875rem;
        width: 100%;
    }

    .btn-cta {
        width: 100%;
        max-width: 260px;
        padding: 0.875rem 1.5rem;
        font-size: 0.9rem;
        justify-content: center;
        text-align: center;
    }
}

/* Animation Classes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-on-scroll {
    opacity: 0;
    animation: fadeInUp 0.6s ease forwards;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }
.animate-delay-4 { animation-delay: 0.4s; }
.animate-delay-5 { animation-delay: 0.5s; }
.animate-delay-6 { animation-delay: 0.6s; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Testimonial Scroll with Manual Controls
    const scrollTrack = document.querySelector('.testimonial-scroll-track');
    const prevBtn = document.getElementById('prevTestimonial');
    const nextBtn = document.getElementById('nextTestimonial');
    const dots = document.querySelectorAll('.carousel-dots-modern .dot');
    const cards = document.querySelectorAll('.testimonial-card');
    const totalTestimonials = <?php echo count($testimonials); ?>;
    
    let currentIndex = 0;
    let isAutoScrolling = true;
    let autoScrollInterval;
    
    function scrollToIndex(index) {
        if (!scrollTrack) return;
        
        currentIndex = index;
        const cardWidth = 400 + 24; // card width + gap
        const scrollPosition = currentIndex * cardWidth;
        
        // Pause animation and manually set position
        scrollTrack.style.animation = 'none';
        scrollTrack.style.transform = `translateX(-${scrollPosition}px)`;
        
        // Update dots
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === currentIndex);
        });
        
        isAutoScrolling = false;
        clearInterval(autoScrollInterval);
        
        // Resume auto-scroll after 5 seconds
        setTimeout(() => {
            isAutoScrolling = true;
            startAutoScroll();
        }, 5000);
    }
    
    function startAutoScroll() {
        if (autoScrollInterval) clearInterval(autoScrollInterval);
        
        autoScrollInterval = setInterval(() => {
            if (isAutoScrolling) {
                currentIndex = (currentIndex + 1) % totalTestimonials;
                scrollToIndex(currentIndex);
            }
        }, 5000);
    }
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + totalTestimonials) % totalTestimonials;
            scrollToIndex(currentIndex);
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % totalTestimonials;
            scrollToIndex(currentIndex);
        });
    }
    
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            scrollToIndex(index);
        });
    });
    
    // Pause on hover
    if (scrollTrack) {
        scrollTrack.addEventListener('mouseenter', () => {
            isAutoScrolling = false;
        });
        
        scrollTrack.addEventListener('mouseleave', () => {
            isAutoScrolling = true;
        });
    }
    
    // Start auto-scroll
    startAutoScroll();
    
    // Feature card click handler - redirect to login
    document.querySelectorAll('.feature-card-modern').forEach(card => {
        card.addEventListener('click', function() {
            window.location.href = '<?php echo $base_url; ?>?page=login';
        });
    });
    
    // Animated counter for stats
    function animateCounter(element, target, duration = 800) {
        let start = 0;
        const increment = target / (duration / 16);
        const isBengali = document.documentElement.lang === 'bn';
        
        function toBengaliNumber(num) {
            const bengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
            return num.toString().split('').map(char => {
                return char >= '0' && char <= '9' ? bengaliDigits[parseInt(char)] : char;
            }).join('');
        }
        
        function formatNumber(num) {
            const formatted = Math.floor(num).toLocaleString();
            return isBengali ? toBengaliNumber(formatted) : formatted;
        }
        
        function updateCounter() {
            start += increment;
            if (start < target) {
                element.textContent = formatNumber(start) + '+';
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = formatNumber(target) + '+';
            }
        }
        
        updateCounter();
    }
    
    // Trigger counter animation when stats are visible
    const statNumbers = document.querySelectorAll('.stat-number[data-value]');
    const observerOptions = { threshold: 0.5 };
    
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.getAttribute('data-value'));
                animateCounter(entry.target, target);
                statsObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    statNumbers.forEach(stat => {
        statsObserver.observe(stat);
    });
    
    // Create floating particles in hero
    const heroParticles = document.querySelector('.hero-particles');
    if (heroParticles) {
        for (let i = 0; i < 15; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: absolute;
                width: ${Math.random() * 10 + 5}px;
                height: ${Math.random() * 10 + 5}px;
                background: rgba(255, 255, 255, ${Math.random() * 0.2 + 0.05});
                border-radius: 50%;
                left: ${Math.random() * 100}%;
                top: ${Math.random() * 100}%;
                animation: particleFloat ${Math.random() * 4 + 4}s ease-in-out infinite;
                animation-delay: ${Math.random() * 2}s;
            `;
            heroParticles.appendChild(particle);
        }
    }
    
    // Scroll animations
    const animateElements = document.querySelectorAll('.feature-card-modern, .benefit-card, .impact-stat-card');
    
    const scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.animation = `fadeInUp 0.6s ease ${index * 0.1}s forwards`;
                entry.target.style.opacity = '1';
                scrollObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    animateElements.forEach(el => {
        el.style.opacity = '0';
        scrollObserver.observe(el);
    });
});

// Add particle float animation to head
const particleStyle = document.createElement('style');
particleStyle.textContent = `
    @keyframes particleFloat {
        0%, 100% { transform: translateY(0) translateX(0); opacity: 0.5; }
        25% { transform: translateY(-30px) translateX(15px); opacity: 0.8; }
        50% { transform: translateY(-50px) translateX(-15px); opacity: 0.5; }
        75% { transform: translateY(-30px) translateX(25px); opacity: 0.8; }
    }
`;
document.head.appendChild(particleStyle);
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
