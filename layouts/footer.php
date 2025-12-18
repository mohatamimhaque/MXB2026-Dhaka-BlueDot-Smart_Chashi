    </div><!-- End container -->
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>About Smart Chashi</h4>
                    <p>AI-powered smart farming ecosystem to empower farmers with intelligent advice and real-time information.</p>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo $base_url; ?>">Home</a></li>
                        <li><a href="<?php echo $base_url; ?>chat">AI Chat</a></li>
                        <li><a href="<?php echo $base_url; ?>weather">Weather</a></li>
                        <li><a href="<?php echo $base_url; ?>marketplace">Marketplace</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>Email: info@cashibhai.com</p>
                    <p>Phone: +880 1234 567890</p>
                </div>
                
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <div class="social-links">
                        <a href="#" class="social">Facebook</a>
                        <a href="#" class="social">Twitter</a>
                        <a href="#" class="social">LinkedIn</a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 Smart Chashi. All rights reserved.</p>
                <p>Version <?php echo APP_VERSION; ?></p>
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
    
    <script>
        // Set base URL for JavaScript
        const baseUrl = '<?php echo $base_url; ?>';
        
        // Enhanced Mobile Navigation & Language Switcher
        document.addEventListener('DOMContentLoaded', function() {
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
                userMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!userDropdown.contains(e.target) && !userMenuToggle.contains(e.target)) {
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

                    const langOptionBtns = langOptionsInDropdown.querySelectorAll('.lang-option-btn');
                    langOptionBtns.forEach(option => {
                        option.addEventListener('click', function(e) {
                            e.preventDefault();
                            const lang = this.dataset.lang;
                            
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
                                }
                            })
                            .catch(error => console.error('Error:', error));
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
                        const lang = this.dataset.lang;
                        
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
                            }
                        })
                        .catch(error => console.error('Error:', error));
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

            // Floating Chat Button & Modal with macOS-like animation
            const chatFloatingBtn = document.getElementById('chatFloatingBtn');
            const chatModal = document.getElementById('chatModal');
            const chatModalClose = document.getElementById('chatModalClose');

            if (chatFloatingBtn && chatModal) {
                // Open chat modal with animation
                chatFloatingBtn.addEventListener('click', function() {
                    chatModal.classList.add('opening');
                    chatModal.classList.add('show');
                    document.body.style.overflow = 'hidden';
                    
                    // Remove opening class after animation completes
                    setTimeout(() => {
                        chatModal.classList.remove('opening');
                    }, 400);
                });

                // Close chat modal with animation
                const closeModal = function() {
                    chatModal.classList.add('closing');
                    chatModal.classList.remove('show');
                    
                    // Wait for animation to complete before cleaning up
                    setTimeout(() => {
                        chatModal.classList.remove('closing');
                        document.body.style.overflow = '';
                    }, 400);
                };

                if (chatModalClose) {
                    chatModalClose.addEventListener('click', closeModal);
                }

                // Close on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && chatModal.classList.contains('show')) {
                        closeModal();
                    }
                });
            }
        });
    </script>
</body>
</html>
