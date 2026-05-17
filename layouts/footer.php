    </div><!-- End container -->
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><?php echo __('about_smart_chashi'); ?></h4>
                    <p><?php echo __('footer_description'); ?></p>
                </div>
                
                <div class="footer-section">
                    <h4><?php echo __('quick_links'); ?></h4>
                    <ul class="footer-links-grid">
                        <li><a href="<?php echo $base_url; ?>"><?php echo __('home'); ?></a></li>
                        <li><a href="<?php echo $base_url; ?>agent/chat.php"><?php echo __('chat'); ?></a></li>
                        <li><a href="<?php echo $base_url; ?>weather"><?php echo __('weather'); ?></a></li>
                        <li><a href="<?php echo $base_url; ?>marketplace"><?php echo __('marketplace'); ?></a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4><?php echo __('contact'); ?></h4>
                    <?php if ($contact_email = getSystemSetting('contact_email')): ?>
                        <p><?php echo __('email'); ?>: <?php echo htmlspecialchars($contact_email); ?></p>
                    <?php endif; ?>
                    <?php if ($contact_phone = getSystemSetting('contact_phone')): ?>
                        <p><?php echo __('phone'); ?>: <?php echo htmlspecialchars($contact_phone); ?></p>
                    <?php endif; ?>
                    <?php if ($contact_address = getSystemSetting('contact_address')): ?>
                        <p><?php echo htmlspecialchars($contact_address); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="footer-section">
                    <h4><?php echo __('follow_us'); ?></h4>
                    <div class="social-links">
                        <?php if ($facebook_url = getSystemSetting('facebook_url')): ?>
                            <a href="<?php echo htmlspecialchars($facebook_url); ?>" class="social" target="_blank" rel="noopener noreferrer">Facebook</a>
                        <?php endif; ?>
                        <?php if ($twitter_url = getSystemSetting('twitter_url')): ?>
                            <a href="<?php echo htmlspecialchars($twitter_url); ?>" class="social" target="_blank" rel="noopener noreferrer">Twitter</a>
                        <?php endif; ?>
                        <?php if ($youtube_url = getSystemSetting('youtube_url')): ?>
                            <a href="<?php echo htmlspecialchars($youtube_url); ?>" class="social" target="_blank" rel="noopener noreferrer">YouTube</a>
                        <?php endif; ?>
                        <?php if ($instagram_url = getSystemSetting('instagram_url')): ?>
                            <a href="<?php echo htmlspecialchars($instagram_url); ?>" class="social" target="_blank" rel="noopener noreferrer">Instagram</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <span id="copyrightYear"></span> <?php echo __('smart_chashi'); ?>. <?php echo __('all_rights_reserved'); ?>. <?php echo __('version'); ?> <?php echo APP_VERSION; ?></p>
            </div>
        </div>
    </footer>

    </main>
    </div><!-- End container -->
    
    <?php
    if(isLoggedIn()){
        include __DIR__ . '/../layouts/agent.php';
    } ?>
    
    <script src="<?php echo $base_url; ?>public/js/app.js"></script>
    <?php if (isLoggedIn()): ?>
    <script src="<?php echo $base_url; ?>public/js/notifications.js"></script>
    <?php endif; ?>
    
    <script>
        // Set base URL for JavaScript (only if not already defined)
        if (typeof baseUrl === 'undefined') {
            var baseUrl = '<?php echo $base_url; ?>';
        }
        
        // Set copyright year dynamically
        document.getElementById('copyrightYear').textContent = new Date().getFullYear();
        
        // ================================================
        // Global Toast Notification System
        // ================================================
        (function() {
            // Add toast CSS if not already added
            if (!document.getElementById('toast-styles')) {
                const style = document.createElement('style');
                style.id = 'toast-styles';
                style.textContent = `
                    .toast-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        max-width: 400px;
                        min-width: 280px;
                        padding: 1rem 1.5rem;
                        color: white;
                        border-radius: 12px;
                        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                        z-index: 99999;
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                        font-size: 0.95rem;
                        font-weight: 500;
                        animation: toastSlideIn 0.3s ease;
                        backdrop-filter: blur(10px);
                    }
                    .toast-notification.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
                    .toast-notification.error { background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); }
                    .toast-notification.warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: #333; }
                    .toast-notification.info { background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%); }
                    .toast-notification .toast-icon { font-size: 1.5rem; }
                    .toast-notification .toast-message { flex: 1; }
                    .toast-notification .toast-close {
                        background: none;
                        border: none;
                        color: inherit;
                        cursor: pointer;
                        padding: 0;
                        opacity: 0.7;
                        transition: opacity 0.2s;
                    }
                    .toast-notification .toast-close:hover { opacity: 1; }
                    .toast-notification.toast-exit { animation: toastSlideOut 0.3s ease forwards; }
                    @keyframes toastSlideIn {
                        from { opacity: 0; transform: translateX(100px); }
                        to { opacity: 1; transform: translateX(0); }
                    }
                    @keyframes toastSlideOut {
                        from { opacity: 1; transform: translateX(0); }
                        to { opacity: 0; transform: translateX(100px); }
                    }
                    @media (max-width: 480px) {
                        .toast-notification {
                            top: auto;
                            bottom: 20px;
                            right: 10px;
                            left: 10px;
                            max-width: none;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Global showNotification function
            window.showNotification = function(message, type = 'info', duration = 3000) {
                const icons = {
                    success: 'check_circle',
                    error: 'error',
                    warning: 'warning',
                    info: 'info'
                };
                
                const toast = document.createElement('div');
                toast.className = `toast-notification ${type}`;
                toast.innerHTML = `
                    <span class="material-icons toast-icon">${icons[type] || icons.info}</span>
                    <span class="toast-message">${message}</span>
                    <button class="toast-close" onclick="this.parentElement.remove()">
                        <span class="material-icons">close</span>
                    </button>
                `;
                
                document.body.appendChild(toast);
                
                // Auto remove after duration
                setTimeout(() => {
                    toast.classList.add('toast-exit');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
                
                return toast;
            };
        })();
        
        // Enhanced Mobile Navigation & Language Switcher
        // Use a function to initialize that can be called immediately if DOM is ready
        function initializeHeaderFooterInteractions() {
            // Mobile Menu Toggle
            const menuToggle = document.getElementById('menuToggle');
            const navbarNav = document.getElementById('navbarNav');
            const navbarOverlay = document.getElementById('navbarOverlay');
            
            if (menuToggle && navbarNav) {
                menuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    this.classList.toggle('active');
                    navbarNav.classList.toggle('show');
                    navbarOverlay.classList.toggle('show');
                    document.body.style.overflow = navbarNav.classList.contains('show') ? 'hidden' : '';
                });

                // Close menu when clicking overlay
                navbarOverlay.addEventListener('click', function() {
                    menuToggle.classList.remove('active');
                    navbarNav.classList.remove('show');
                    navbarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                });

                // Close menu when clicking on a link
                const menuLinks = navbarNav.querySelectorAll('.nav-link');
                menuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        menuToggle.classList.remove('active');
                        navbarNav.classList.remove('show');
                        navbarOverlay.classList.remove('show');
                        document.body.style.overflow = '';
                    });
                });

                // Close menu on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && navbarNav.classList.contains('show')) {
                        menuToggle.classList.remove('active');
                        navbarNav.classList.remove('show');
                        navbarOverlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            }

            // User Dropdown Menu (for logged in users)
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userDropdown = document.getElementById('userDropdown');
            const languageSelector = document.querySelector('.language-selector');
            const langOptionsInDropdown = document.getElementById('langOptionsInDropdown');

            if (userMenuToggle && userDropdown) {
                // Remove any existing event listeners by cloning the element
                const newToggle = userMenuToggle.cloneNode(true);
                userMenuToggle.parentNode.replaceChild(newToggle, userMenuToggle);
                
                newToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const isShowing = userDropdown.classList.contains('show');
                    
                    // Close all other dropdowns first
                    document.querySelectorAll('.user-dropdown.show').forEach(d => {
                        if (d !== userDropdown) d.classList.remove('show');
                    });
                    
                    userDropdown.classList.toggle('show');
                    
                    // Reset language submenu when closing
                    if (isShowing && languageSelector) {
                        languageSelector.classList.remove('active');
                        if (langOptionsInDropdown) {
                            langOptionsInDropdown.classList.remove('show');
                        }
                    }
                });

                // Handle clicks on dropdown links (like Profile)
                // Use event delegation on the dropdown itself
                userDropdown.addEventListener('click', function(e) {
                    // Check if clicked element is a link (but not language selector)
                    const link = e.target.closest('a.user-dropdown-item');
                    if (link && !link.classList.contains('language-selector')) {
                        // Allow the link to navigate naturally
                        // The browser will handle the navigation
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!userDropdown.contains(e.target) && !newToggle.contains(e.target)) {
                        userDropdown.classList.remove('show');
                        if (languageSelector) {
                            languageSelector.classList.remove('active');
                            if (langOptionsInDropdown) {
                                langOptionsInDropdown.classList.remove('show');
                            }
                        }
                    }
                });

                // Language selector inside user dropdown
                if (languageSelector && langOptionsInDropdown) {
                    languageSelector.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.classList.toggle('active');
                        langOptionsInDropdown.classList.toggle('show');
                    });

                    // Close language submenu when clicking outside
                    document.addEventListener('click', function(e) {
                        if (languageSelector && !languageSelector.contains(e.target) && !langOptionsInDropdown.contains(e.target)) {
                            languageSelector.classList.remove('active');
                            langOptionsInDropdown.classList.remove('show');
                        }
                    });

                    const langOptionBtns = langOptionsInDropdown.querySelectorAll('.lang-option-btn');
                    langOptionBtns.forEach(option => {
                        option.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const lang = this.dataset.lang;
                            
                            // Show loading indicator
                            this.innerHTML = '<span class="material-icons">sync</span> Switching...';
                            
                            fetch(baseUrl + 'api/set-language.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'language=' + lang
                            })
                            .then(response => {
                                return response.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    location.reload();
                                } else {
                                    console.error('Language switch failed:', data.message);
                                    alert('Failed to switch language. Please try again.');
                                    location.reload();
                                }
                            })
                            .catch(error => {
                                console.error('Error switching language:', error);
                                alert('Error switching language. Please try again.');
                                location.reload();
                            });
                        });
                    });
                }
            }

            // Language Switcher (for logged out users)
            const langToggle = document.getElementById('langToggle');
            const langMenu = document.getElementById('langMenu');
            const langOptions = document.querySelectorAll('.lang-option');

            if (langToggle && langMenu) {
                langToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    langMenu.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!langMenu.contains(e.target) && !langToggle.contains(e.target)) {
                        langMenu.classList.remove('show');
                    }
                });

                langOptions.forEach(option => {
                    option.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const lang = this.dataset.lang;
                        
                        // Show loading
                        this.innerHTML = '<span class="material-icons">sync</span> Switching...';
                        
                        fetch(baseUrl + 'api/set-language.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'language=' + lang
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Failed to switch language. Please try again.');
                                location.reload();
                            }
                        })
                        .catch(error => {
                            console.error('Error switching language:', error);
                            alert('Error switching language. Please try again.');
                            location.reload();
                        });
                    });
                });
            }

            // Password Toggle Functionality (supports multiple password fields)
            const passwordToggles = document.querySelectorAll('.password-toggle');
            
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.dataset.target || 'password';
                    const passwordInput = document.getElementById(targetId);
                    
                    if (passwordInput) {
                        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                        passwordInput.setAttribute('type', type);
                        
                        // Toggle Material Icon
                        const eyeIcon = this.querySelector('.eye-icon');
                        if (eyeIcon) {
                            eyeIcon.textContent = type === 'password' ? 'visibility' : 'visibility_off';
                        }
                    }
                });
            });

            // Touch device optimization
            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
            }

            // Handle orientation changes
            window.addEventListener('orientationchange', function() {
                // Close mobile menu on orientation change
                if (menuToggle && navbarNav) {
                    menuToggle.classList.remove('active');
                    navbarNav.classList.remove('show');
                }
            });

        }

        // Execute immediately if DOM is already loaded, otherwise wait for DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeHeaderFooterInteractions);
        } else {
            initializeHeaderFooterInteractions();
        }
    </script>
</body>
</html>
