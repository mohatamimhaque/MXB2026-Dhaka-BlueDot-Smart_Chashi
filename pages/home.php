<?php
include __DIR__ . '/../layouts/header.php';

// Get user stats if logged in
$stats = [];
if (isLoggedIn()) {
    $db = new Database();
    $userId = $_SESSION['user_id'];
    $user = getCurrentUser();
    
    try {
        // Get user's crop count
        $stats['crop_count'] = $db->single("SELECT COUNT(*) as count FROM crops WHERE user_id = ?", [$userId])['count'] ?? 0;
        
        // Get user's tasks count
        $stats['task_count'] = $db->single("SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status != 'completed'", [$userId])['count'] ?? 0;
        
        // Get community posts count
        $stats['post_count'] = $db->single("SELECT COUNT(*) as count FROM community_posts", [])['count'] ?? 0;
        
        // Get recent activity
        $recentActivities = $db->resultSet("SELECT 'post' as type, title as content, created_at 
            FROM community_posts WHERE user_id = ? 
            UNION ALL 
            SELECT 'task' as type, title as content, created_at 
            FROM tasks WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT 5", [$userId, $userId]);
    } catch (Exception $e) {
        // Silently handle errors
    }
}

// Get platform statistics
$platformStats = [];
try {
    $db = new Database();
    $platformStats['total_users'] = $db->single("SELECT COUNT(*) as count FROM users", [])['count'] ?? 0;
    $platformStats['total_posts'] = $db->single("SELECT COUNT(*) as count FROM community_posts", [])['count'] ?? 0;
    $platformStats['active_farmers'] = $db->single("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'", [])['count'] ?? 0;
} catch (Exception $e) {
    $platformStats = ['total_users' => 0, 'total_posts' => 0, 'active_farmers' => 0];
}
?>

<section class="hero">
    <h1><?php echo __('smart_chashi'); ?></h1>
    <p><span class="material-icons" style="vertical-align: middle;">agriculture</span> <?php echo __('ai_farming_assistant'); ?> <span class="material-icons" style="vertical-align: middle;">smart_toy</span></p>
    <p><?php echo __('get_smart_farming'); ?></p>
    
    <?php if (isLoggedIn()): ?>
        <!-- User Dashboard Quick Stats -->
        <div class="hero-stats">
            <div class="stat-card">
                <span class="material-icons">eco</span>
                <div>
                    <h3><?php echo $stats['crop_count'] ?? 0; ?></h3>
                    <p>Active Crops</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="material-icons">task_alt</span>
                <div>
                    <h3><?php echo $stats['task_count'] ?? 0; ?></h3>
                    <p>Pending Tasks</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="material-icons">forum</span>
                <div>
                    <h3><?php echo $stats['post_count'] ?? 0; ?></h3>
                    <p>Community Posts</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Platform Statistics for Visitors -->
        <div class="hero-stats">
            <div class="stat-card">
                <span class="material-icons">people</span>
                <div>
                    <h3><?php echo number_format($platformStats['total_users']); ?>+</h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="material-icons">agriculture</span>
                <div>
                    <h3><?php echo number_format($platformStats['active_farmers']); ?>+</h3>
                    <p>Farmers</p>
                </div>
            </div>
            <div class="stat-card">
                <span class="material-icons">article</span>
                <div>
                    <h3><?php echo number_format($platformStats['total_posts']); ?>+</h3>
                    <p>Posts Shared</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="hero-buttons">
        <?php if (!isLoggedIn()): ?>
            <a href="<?php echo $base_url; ?>login" class="btn"><?php echo __('login'); ?></a>
            <a href="<?php echo $base_url; ?>register" class="btn btn-secondary"><?php echo __('register'); ?></a>
        <?php else: ?>
            <a href="<?php echo $base_url; ?>dashboard" class="btn"><?php echo __('dashboard'); ?></a>
            <a href="<?php echo $base_url; ?>crops" class="btn btn-secondary">Manage Crops</a>
        <?php endif; ?>
    </div>
</section>

<div class="grid">
        <div class="card feature-card" data-feature="crops">
            <div class="feature-icon">
                <span class="material-icons">agriculture</span>
            </div>
            <h3><?php echo __('crops'); ?></h3>
            <p><?php echo __('track_manage_crops'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>crops" class="btn btn-small"><?php echo __('manage'); ?></a>
            <?php else: ?>
                <p class="text-muted">Login to access</p>
            <?php endif; ?>
        </div>

        <div class="card feature-card" data-feature="disease">
            <div class="feature-icon">
                <span class="material-icons">bug_report</span>
            </div>
            <h3><?php echo __('disease_check'); ?></h3>
            <p><?php echo __('detect_crop_diseases'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>disease" class="btn btn-small"><?php echo __('check'); ?></a>
            <?php else: ?>
                <p class="text-muted">Login to access</p>
            <?php endif; ?>
        </div>

        <div class="card feature-card" data-feature="agent">
            <div class="feature-icon">
                <span class="material-icons">chat</span>
            </div>
            <h3><?php echo __('chat'); ?></h3>
            <p><?php echo __('ask_farming_questions'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>agent" class="btn btn-small"><?php echo __('chat'); ?></a>
            <?php else: ?>
                <p class="text-muted">Login to access</p>
            <?php endif; ?>
        </div>

        <div class="card feature-card" data-feature="weather">
            <div class="feature-icon">
                <span class="material-icons">wb_sunny</span>
            </div>
            <h3><?php echo __('weather'); ?></h3>
            <p><?php echo __('realtime_weather'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>weather" class="btn btn-small"><?php echo __('view'); ?></a>
            <?php else: ?>
                <p class="text-muted">Login to access</p>
            <?php endif; ?>
        </div>

        <div class="card feature-card" data-feature="marketplace">
            <div class="feature-icon">
                <span class="material-icons">shopping_cart</span>
            </div>
            <h3><?php echo __('marketplace'); ?></h3>
            <p><?php echo __('buy_sell_products'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>marketplace" class="btn btn-small"><?php echo __('browse'); ?></a>
            <?php else: ?>
                <p class="text-muted">Login to access</p>
            <?php endif; ?>
        </div>

        <div class="card feature-card" data-feature="community">
            <div class="feature-icon">
                <span class="material-icons">people</span>
            </div>
            <h3><?php echo __('community'); ?></h3>
            <p><?php echo __('connect_farmers'); ?></p>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo $base_url; ?>community" class="btn btn-small"><?php echo __('join'); ?></a>
            <?php else: ?>
                <p class="text-muted">Login to access</p>
            <?php endif; ?>
        </div>
</div>

<section class="mt-4">
    <h2 class="text-center"><?php echo __('why_choose_smart_chashi'); ?></h2>
    
    <div class="grid mt-3">
        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">smartphone</span> <?php echo __('mobile_first_design'); ?></h4>
            <p><?php echo __('mobile_first_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">lock</span> <?php echo __('secure_private'); ?></h4>
            <p><?php echo __('secure_private_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">cloud_off</span> <?php echo __('works_offline'); ?></h4>
            <p><?php echo __('works_offline_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">attach_money</span> <?php echo __('free_open'); ?></h4>
            <p><?php echo __('free_open_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">translate</span> <?php echo __('bangla_support'); ?></h4>
            <p><?php echo __('bangla_support_desc'); ?></p>
        </div>

        <div class="card text-center">
            <h4><span class="material-icons" style="vertical-align: middle; color: var(--primary);">update</span> <?php echo __('always_updated'); ?></h4>
            <p><?php echo __('always_updated_desc'); ?></p>
        </div>
    </div>
</section>

<section class="mt-4 mb-4">
    <h2 class="text-center">Success Stories</h2>
    
    <div class="testimonial-carousel">
        <div class="testimonial-track">
            <div class="testimonial-item">
                <div class="card">
                    <h4>Increased Yield by 30%</h4>
                    <p><strong>Farmer Rahman, Rangpur</strong></p>
                    <p>"Using Chashi's recommendations, I increased my rice yield by 30%. The fertilizer guidance was spot on!"</p>
                    <p class="text-muted">- Using for 6 months</p>
                    <div style="margin-top: 1rem;">
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-item">
                <div class="card">
                    <h4>Saved Entire Crop</h4>
                    <p><strong>Farmer Fatema, Sylhet</strong></p>
                    <p>"The disease detection caught a fungal infection early. I saved my entire tomato crop!"</p>
                    <p class="text-muted">- Using for 3 months</p>
                    <div style="margin-top: 1rem;">
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-item">
                <div class="card">
                    <h4>Reduced Costs by 25%</h4>
                    <p><strong>Farmer Ahmed, Dhaka</strong></p>
                    <p>"Smart irrigation recommendations reduced my water usage and costs significantly."</p>
                    <p class="text-muted">- Using for 4 months</p>
                    <div style="margin-top: 1rem;">
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                        <span class="material-icons" style="color: #FFD700;">star</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="carousel-controls">
            <button id="prevTestimonial" class="carousel-btn">
                <span class="material-icons">chevron_left</span>
            </button>
            <button id="nextTestimonial" class="carousel-btn">
                <span class="material-icons">chevron_right</span>
            </button>
        </div>
        
        <div class="carousel-dots">
            <span class="dot active"></span>
            <span class="dot"></span>
            <span class="dot"></span>
        </div>
    </div>
</section>

<style>
.hero-stats {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    margin: 2rem 0;
    flex-wrap: wrap;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    min-width: 180px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.stat-card .material-icons {
    font-size: 3rem;
    color: var(--primary);
}

.stat-card h3 {
    margin: 0;
    font-size: 2rem;
    color: var(--primary);
    font-weight: bold;
}

.stat-card p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.feature-card {
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
    overflow: hidden;
}

.feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.feature-card:hover::before {
    transform: scaleX(1);
}

.feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.feature-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    transition: all 0.3s ease;
}

.feature-card:hover .feature-icon {
    transform: rotate(360deg) scale(1.1);
}

.feature-icon .material-icons {
    font-size: 3rem;
    color: white;
}

.testimonial-carousel {
    position: relative;
    overflow: hidden;
    padding: 2rem 0;
}

.testimonial-track {
    display: flex;
    transition: transform 0.5s ease;
}

.testimonial-item {
    min-width: 100%;
    padding: 0 1rem;
}

.carousel-controls {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
}

.carousel-btn {
    background: var(--primary);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.carousel-btn:hover {
    background: var(--secondary);
    transform: scale(1.1);
}

.carousel-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.carousel-dots {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ddd;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dot.active {
    background: var(--primary);
    width: 30px;
    border-radius: 5px;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeIn 0.6s ease forwards;
    opacity: 0;
}

.card:nth-child(1) { animation-delay: 0.1s; }
.card:nth-child(2) { animation-delay: 0.2s; }
.card:nth-child(3) { animation-delay: 0.3s; }
.card:nth-child(4) { animation-delay: 0.4s; }
.card:nth-child(5) { animation-delay: 0.5s; }
.card:nth-child(6) { animation-delay: 0.6s; }

@media (max-width: 768px) {
    .hero-stats {
        flex-direction: column;
        align-items: stretch;
    }
    
    .stat-card {
        width: 100%;
    }
    
    .feature-icon {
        width: 60px;
        height: 60px;
    }
    
    .feature-icon .material-icons {
        font-size: 2rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Testimonial Carousel
    let currentTestimonial = 0;
    const testimonials = document.querySelectorAll('.testimonial-item');
    const track = document.querySelector('.testimonial-track');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.getElementById('prevTestimonial');
    const nextBtn = document.getElementById('nextTestimonial');
    
    if (testimonials.length > 0) {
        function updateCarousel() {
            if (track) {
                track.style.transform = `translateX(-${currentTestimonial * 100}%)`;
            }
            
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentTestimonial);
            });
            
            if (prevBtn) prevBtn.disabled = currentTestimonial === 0;
            if (nextBtn) nextBtn.disabled = currentTestimonial === testimonials.length - 1;
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentTestimonial < testimonials.length - 1) {
                    currentTestimonial++;
                    updateCarousel();
                }
            });
        }
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentTestimonial > 0) {
                    currentTestimonial--;
                    updateCarousel();
                }
            });
        }
        
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentTestimonial = index;
                updateCarousel();
            });
        });
        
        // Auto-advance carousel every 5 seconds
        setInterval(() => {
            currentTestimonial = (currentTestimonial + 1) % testimonials.length;
            updateCarousel();
        }, 5000);
        
        updateCarousel();
    }
    
    // Feature card click handler
    document.querySelectorAll('.feature-card').forEach(card => {
        card.addEventListener('click', function() {
            const link = this.querySelector('a');
            if (link) {
                link.click();
            }
        });
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
