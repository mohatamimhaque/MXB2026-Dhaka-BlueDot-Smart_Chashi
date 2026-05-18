<!DOCTYPE html>
<html lang="<?php echo get_language(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Smart Chashi - AI Powered Smart Farming Ecosystem">
    <meta name="theme-color" content="#557A46">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- jquery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_url; ?>img/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $base_url; ?>img/logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url; ?>img/logo.png">
    <link rel="shortcut icon" href="<?php echo $base_url; ?>img/logo.png">
    
    <!-- Google Material Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
        <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/weather.css">
    <?php 
    // Include modern pages CSS for all pages except login and register
    $currentPage = $_GET['page'] ?? 'home';
    if (!in_array($currentPage, ['login', 'register'])): 
    ?>
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/modern-pages.css">
    <?php endif; ?>
    <!-- Unified modern CSS for all pages - includes navbar and auth page enhancements -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/unified-modern.css">
    
    <!-- Preloader CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/preloader.css">
    
    <!-- Base Responsive CSS - Always loaded -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/pages-responsive.css">
    
    <!-- Shared Pagination CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/pagination.css">
    
    <!-- Notifications CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/notifications.css">
    
    <!-- Device-Specific CSS - Loaded dynamically via JavaScript based on device type -->
    <!-- pages-mobile.css - Mobile devices (< 768px) -->
    <!-- pages-tablet.css - Tablet devices (768px - 991px) -->
    
    <!-- Device Detection and Responsive CSS Loader -->
    <script src="<?php echo $base_url; ?>public/js/device-responsive.js"></script>
    
    <!-- Global JavaScript Variables -->
    <script>
        window.BASE_URL = '<?php echo $base_url; ?>';
        window.CSRF_TOKEN = '<?php echo generateCSRFToken(); ?>';
    </script>
    <?php 
    // Output system settings as global JS object
    outputSystemSettingsJS();
    
    // Enable browser caching for faster page loads
    $currentPage = $_GET['page'] ?? 'home';
    if ($currentPage !== 'home') {
        // Cache pages other than home for better performance
        header('Cache-Control: public, max-age=3600'); // 1 hour
    } else {
        // Don't cache home page to show preloader on first visit
        header('Cache-Control: no-cache, must-revalidate');
    }
    ?>

</head>
<body>
    <!-- Preloader - All Pages -->
    <div id="preloader" class="preloader" style="display: nne;">
        <div class="preloader-content">
            <div class="preloader-logo">
                <img src="<?php echo $base_url; ?>img/logo.png" alt="Smart Chashi Logo">
            </div>
            <div class="preloader-spinner">
                <div class="leaf leaf-1"></div>
                <div class="leaf leaf-2"></div>
                <div class="leaf leaf-3"></div>
            </div>
            <div class="preloader-text"><?php echo __('smart_chashi'); ?></div>
            <div class="preloader-subtext"><?php echo __('loading'); ?>...</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
    </div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="<?php echo $base_url; ?>" class="navbar-brand">
                <span class="brand-icon"><img src="<?php echo __('img/logo.png'); ?>" alt=""></span>
                <span class="brand-text"><?php echo __('smart_chashi'); ?></span>
            </a>
            
            <ul class="navbar-nav" id="navbarNav">
                <?php if (isLoggedIn()): ?>
                    <?php $currentRole = getCurrentUser()['role']; ?>
                    
                    <?php if ($currentRole === 'officer'): ?>
                        <!-- Extension Officer Navbar -->
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=officer-dashboard" class="nav-link">
                                <span class="material-icons nav-icon">dashboard</span>
                                <span class="nav-text"><?php echo __('dashboard'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=farmer-reports" class="nav-link">
                                <span class="material-icons nav-icon">assessment</span>
                                <span class="nav-text"><?php echo __('farmer_reports'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=issue-alert" class="nav-link">
                                <span class="material-icons nav-icon">notifications_active</span>
                                <span class="nav-text"><?php echo __('issue_alerts'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=advisory" class="nav-link">
                                <span class="material-icons nav-icon">assignment</span>
                                <span class="nav-text"><?php echo __('advisory'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=officer-learn" class="nav-link">
                                <span class="material-icons nav-icon">school</span>
                                <span class="nav-text"><?php echo __('learning'); ?></span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=community" class="nav-link">
                                <span class="material-icons nav-icon">people</span>
                                <span class="nav-text"><?php echo __('community'); ?></span>
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Farmer Navbar -->
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=dashboard" class="nav-link">
                                <span class="material-icons nav-icon">dashboard</span>
                                <span class="nav-text"><?php echo __('dashboard'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=crops" class="nav-link">
                                <span class="material-icons nav-icon">agriculture</span>
                                <span class="nav-text"><?php echo __('crops'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=disease" class="nav-link">
                                <span class="material-icons nav-icon">bug_report</span>
                                <span class="nav-text"><?php echo __('disease_detection'); ?></span>
                            </a>
                        </li>
                      
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=weather" class="nav-link">
                                <span class="material-icons nav-icon">wb_sunny</span>
                                <span class="nav-text"><?php echo __('weather'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=marketplace" class="nav-link">
                                <span class="material-icons nav-icon">shopping_cart</span>
                                <span class="nav-text"><?php echo __('marketplace'); ?></span>
                            </a>
                        </li>
                      
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=learn" class="nav-link">
                                <span class="material-icons nav-icon">school</span>
                                <span class="nav-text"><?php echo __('learning'); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $base_url; ?>?page=community" class="nav-link">
                                <span class="material-icons nav-icon">people</span>
                                <span class="nav-text"><?php echo __('community'); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="<?php echo $base_url; ?>" class="nav-link">
                            <span class="material-icons nav-icon">home</span>
                            <span class="nav-text"><?php echo __('home'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $base_url; ?>?page=login" class="nav-link">
                            <span class="material-icons nav-icon">login</span>
                            <span class="nav-text"><?php echo __('login'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $base_url; ?>?page=register" class="nav-link nav-link-primary">
                            <span class="material-icons nav-icon">person_add</span>
                            <span class="nav-text"><?php echo __('register'); ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="navbar-actions">
                <?php if (isLoggedIn()): ?>
                    <?php 
                    $currentUser = getCurrentUser(); 
                    // Get unread notification count
                    $unreadNotifCount = 0;
                    try {
                        $notifDb = new Database();
                        $notifResult = $notifDb->single(
                            "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND user_type = ? AND is_read = 0",
                            [$currentUser['user_id'], $currentUser['role']]
                        );
                        $unreadNotifCount = $notifResult['count'] ?? 0;
                    } catch (Exception $e) { $unreadNotifCount = 0; }
                    ?>
                    
                    <!-- Message Bell -->
                    <?php
                    $unreadMsgCount = 0;
                    try {
                        $msgCountDb = new Database();
                        $msgRow = $msgCountDb->single(
                            "SELECT COALESCE(SUM(farmer_unread), 0) as cnt FROM shop_conversations WHERE farmer_id = ?",
                            [$currentUser['user_id']]
                        );
                        $unreadMsgCount = (int)($msgRow['cnt'] ?? 0);
                    } catch (Exception $e) { $unreadMsgCount = 0; }
                    ?>
                    <a href="<?php echo $base_url; ?>?page=farmer-messages" class="notification-bell" style="text-decoration:none;display:inline-flex;" title="Messages">
                        <button class="notification-toggle" aria-label="Messages" style="cursor:pointer;">
                            <span class="material-icons">chat</span>
                            <span class="notification-badge" data-count="<?php echo $unreadMsgCount; ?>"
                                  style="<?php echo $unreadMsgCount > 0 ? '' : 'display:none;'; ?>">
                                <?php echo $unreadMsgCount > 99 ? '99+' : ($unreadMsgCount ?: ''); ?>
                            </span>
                        </button>
                    </a>

                    <!-- Notification Bell -->
                    <div class="notification-bell">
                        <button class="notification-toggle" aria-label="Notifications">
                            <span class="material-icons">notifications</span>
                            <span class="notification-badge" data-count="<?php echo $unreadNotifCount; ?>"><?php echo $unreadNotifCount > 0 ? ($unreadNotifCount > 99 ? '99+' : $unreadNotifCount) : ''; ?></span>
                        </button>
                        <div class="notification-dropdown">
                            <div class="notification-header">
                                <h4><?php echo __('notifications') ?: 'Notifications'; ?></h4>
                                <button class="clear-all-btn">Clear All</button>
                            </div>
                            <div class="notification-list">
                                <div class="notification-loading"><div class="spinner"></div></div>
                            </div>
                            <div class="notification-footer">
                                <a href="<?php echo $base_url; ?>?page=all-notifications">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Dropdown Menu -->
                    <div class="user-menu">
                        <button class="user-menu-toggle" id="userMenuToggle" aria-label="User Menu">
                            <?php if (!empty($currentUser['profile_img_url'])): ?>
                                <img src="<?php echo $base_url . 'public/' . $currentUser['profile_img_url']; ?>" alt="Profile" class="user-avatar">
                            <?php else: ?>
                                <span class="material-icons">account_circle</span>
                            <?php endif; ?>
                        </button>
                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-dropdown-header">
                                <?php if (!empty($currentUser['profile_img_url'])): ?>
                                    <img src="<?php echo $base_url . 'public/' . $currentUser['profile_img_url']; ?>" alt="Profile" class="dropdown-avatar">
                                <?php else: ?>
                                    <span class="material-icons">person</span>
                                <?php endif; ?>
                                <span class="user-name"><?php echo $currentUser['first_name'] ?? 'User'; ?></span>
                            </div>
                            <a href="<?php echo $base_url; ?>?page=profile" class="user-dropdown-item">
                                <span class="material-icons">person</span>
                                <?php echo __('profile'); ?>
                            </a>
                            <?php if ($currentRole === 'farmer'): ?>
                            <a href="<?php echo $base_url; ?>?page=create-report" class="user-dropdown-item">
                                <span class="material-icons">bug_report</span>
                                <?php echo __('create_report'); ?>
                            </a>
                            <a href="<?php echo $base_url; ?>?page=my-reports" class="user-dropdown-item">
                                <span class="material-icons">assignment</span>
                                <?php echo __('my_reports'); ?>
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo $base_url; ?>?page=alerts" class="user-dropdown-item">
                                <span class="material-icons">notifications</span>
                                <?php echo __('visit_alerts'); ?>
                            </a>
                            <div class="user-dropdown-divider"></div>
                            <div class="user-dropdown-item language-selector">
                                <span class="material-icons">language</span>
                                <span><?php echo get_language() === 'en' ? 'English' : 'বাংলা'; ?></span>
                                <span class="material-icons arrow">expand_more</span>
                            </div>
                            <div class="language-options" id="langOptionsInDropdown">
                                <button class="lang-option-btn" data-lang="en">
                                    <span class="material-icons">flag</span> English
                                </button>
                                <button class="lang-option-btn" data-lang="bn">
                                    <span class="material-icons">flag</span> বাংলা
                                </button>
                            </div>
                            <div class="user-dropdown-divider"></div>
                            <a href="<?php echo $base_url; ?>?page=logout" class="user-dropdown-item logout">
                                <span class="material-icons">logout</span>
                                <?php echo __('logout'); ?>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Language Switcher for logged out users -->
                    <div class="language-switcher">
                        <button class="lang-toggle" id="langToggle" aria-label="Change Language">
                            <span class="material-icons lang-icon">language</span>
                            <span class="lang-current"><?php echo get_language() === 'en' ? 'EN' : 'বাংলা'; ?></span>
                        </button>
                        <div class="lang-dropdown" id="langMenu">
                            <button class="lang-option" data-lang="en">
                                <span class="material-icons flag">flag</span> English
                            </button>
                            <button class="lang-option" data-lang="bn">
                                <span class="material-icons flag">flag</span> বাংলা
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <button class="navbar-toggler" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false">
                    <span class="toggler-bar"></span>
                    <span class="toggler-bar"></span>
                    <span class="toggler-bar"></span>
                </button>
            </div>
        </div>
    </nav>
    
    <div class="navbar-overlay" id="navbarOverlay"></div>
    
    <!-- Preloader Script -->
    <script src="<?php echo $base_url; ?>public/js/preloader.js"></script>





    <style>
    .nav-more-item { position: relative; }
    .nav-more-btn {
        display: flex; align-items: center; gap: 4px;
        padding: 6px 10px; border: none; background: none; cursor: pointer;
        font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.9) !important; border-radius: 6px;
    }
    .nav-more-btn:hover { background: rgba(255,255,255,0.15); }
    .nav-more-btn .material-icons { color: rgba(255,255,255,0.9) !important; font-size: 18px; }
    .nav-more-dropdown {
        position: fixed; top: 0; right: 0;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.18); min-width: 190px;
        z-index: 9999; display: none; padding: 6px 0;
    }
    .nav-more-dropdown.open { 
        display: block; 
        top: 60px;
        left: 0px;
        transform:translateX(-50%);
    }
    .nav-more-dropdown .nav-link,
    .nav-more-dropdown .nav-link:hover {
        color: #1f2937 !important;
        display: flex !important; align-items: center !important; gap: 8px !important;
        padding: 10px 16px !important; font-size: 13px !important; white-space: nowrap !important;
        border-radius: 0 !important; background: transparent !important;
        transform: none !important; text-decoration: none !important;
    }
    .nav-more-dropdown .nav-link:hover {
        background: #f0fdf4 !important; color: #557A46 !important;
    }
    .nav-more-dropdown .nav-link .material-icons,
    .nav-more-dropdown .nav-link:hover .material-icons {
        color: #557A46 !important; font-size: 18px !important; transform: none !important;
    }
    .nav-more-dropdown .nav-link::before { display: none !important; }
    </style>
    <script>
    (function() {
        function checkNavOverflow() {
            const navList = document.getElementById('navbarNav');
            if (!navList) return;
            const container = navList.closest('.navbar-container');
            if (!container) return;

            // Remove items from More back to nav first
            let moreItem = document.getElementById('navMoreItem');
            if (moreItem) {
                const movedItems = moreItem.querySelectorAll('li[data-moved]');
                movedItems.forEach(li => {
                    li.removeAttribute('data-moved');
                    li.style.display = '';
                    navList.insertBefore(li, moreItem);
                });
            }

            // Create More item if needed
            if (!moreItem) {
                moreItem = document.createElement('li');
                moreItem.id = 'navMoreItem';
                moreItem.className = 'nav-item nav-more-item';
                moreItem.style.display = 'none';
                moreItem.innerHTML = `
                    <button class="nav-more-btn" id="navMoreBtn">
                        <span class="material-icons" style="font-size:17px;">more_horiz</span>
                        More
                    </button>
                    <div class="nav-more-dropdown" id="navMoreDropdown"></div>
                `;
                navList.appendChild(moreItem);
                document.getElementById('navMoreBtn').addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dd = document.getElementById('navMoreDropdown');
                    if (dd.classList.contains('open')) {
                        dd.classList.remove('open');
                    } else {
                        const rect = this.getBoundingClientRect();
                        dd.classList.add('open');
                    }
                });
                document.addEventListener('click', function() {
                    const dd = document.getElementById('navMoreDropdown');
                    if (dd) dd.classList.remove('open');
                });
                window.addEventListener('resize', function() {
                    const dd = document.getElementById('navMoreDropdown');
                    if (dd) dd.classList.remove('open');
                });
            }

            // Measure available width
            const navbarActions = container.querySelector('.navbar-actions');
            const brand = container.querySelector('.navbar-brand');
            const actionsW = navbarActions ? navbarActions.offsetWidth : 0;
            const brandW   = brand ? brand.offsetWidth : 0;
            const gap      = 32;
            const available = container.offsetWidth - brandW - actionsW - gap;

            // Measure items
            const items = Array.from(navList.children).filter(el => el.id !== 'navMoreItem');
            let totalW = 0;
            const overflow = [];

            // Show More item temporarily to measure its width
            moreItem.style.display = 'flex';
            const moreW = moreItem.offsetWidth || 80;
            moreItem.style.display = 'none';

            for (const item of items) {
                item.style.display = 'flex';
                totalW += item.offsetWidth + 4;
            }

            let overflowing = totalW > available;
            if (overflowing) {
                // Accumulate from right until fits + moreW
                let runW = 0;
                const revItems = [...items].reverse();
                for (const item of revItems) {
                    runW += item.offsetWidth + 4;
                    overflow.push(item);
                    if (totalW - runW + moreW <= available) break;
                }
            }

            if (overflow.length > 0) {
                const dropdown = document.getElementById('navMoreDropdown');
                dropdown.innerHTML = '';
                overflow.reverse().forEach(li => {
                    li.setAttribute('data-moved', '1');
                    li.style.display = 'none';
                    // Clone link for dropdown
                    const link = li.querySelector('a');
                    if (link) {
                        const a = link.cloneNode(true);
                        a.style.display = 'flex';
                        a.style.alignItems = 'center';
                        a.style.gap = '8px';
                        a.style.padding = '9px 14px';
                        a.style.borderRadius = '0';
                        dropdown.appendChild(a);
                    }
                });
                moreItem.style.display = 'flex';
            }
        }


        function checkWindowSize(){
            
            if(document.body.clientWidth<1024){
                document.querySelectorAll(".nav-item").forEach(item => {
                    item.style.display = "flex";
                    item.removeAttribute("data-moved");
                });


                let navMoreItem = document.getElementById("navMoreItem");

                if (navMoreItem) {
                    navMoreItem.style.display = "none";
                }

                return;
            };

            checkNavOverflow();

        }


        document.addEventListener('DOMContentLoaded', checkWindowSize);
        window.addEventListener('load', checkWindowSize);
        window.addEventListener('resize', checkWindowSize);
    })();





    </script>
    
    <div class="container">
    <main>
