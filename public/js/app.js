// Smart Chashi - Main JavaScript Application
(function($) {
    'use strict';

    // Configuration
    const config = {
        apiBaseUrl: (typeof baseUrl !== 'undefined' ? baseUrl : '/smartcashi/') + 'api/',
        imageUploadLimit: 50 * 1024 * 1024, // 50MB
    };

    // Global App Object
    const App = {
        init: function() {
            this.setupEventListeners();
            this.setupAjax();
            console.log('Smart Chashi App Initialized');
        },

        setupAjax: function() {
            $.ajaxSetup({
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                dataType: 'json',
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    App.showAlert('An error occurred. Please try again.', 'danger');
                }
            });
        },

        setupEventListeners: function() {
            // Login form
            $(document).on('submit', '#loginForm', function(e) {
                e.preventDefault();
                App.handleLogin();
            });

            // Register form
            $(document).on('submit', '#registerForm', function(e) {
                e.preventDefault();
                App.handleRegister();
            });

            // Crop form
            $(document).on('submit', '#cropForm', function(e) {
                e.preventDefault();
                App.handleCropSubmit();
            });

            // Disease detection form
            $(document).on('submit', '#diseaseForm', function(e) {
                e.preventDefault();
                App.handleDiseaseDetection();
            });

            // Chat form
            $(document).on('submit', '#chatForm', function(e) {
                e.preventDefault();
                App.handleChatMessage();
            });

            // Alert close buttons
            $(document).on('click', '.alert-close', function() {
                $(this).closest('.alert').slideUp(200, function() {
                    $(this).remove();
                });
            });

            // Modal close
            $(document).on('click', '.modal-close', function() {
                $(this).closest('.modal').removeClass('show');
            });

            $(document).on('click', '.modal', function(e) {
                if (e.target === this) {
                    $(this).removeClass('show');
                }
            });

            // Delete confirmation
            $(document).on('click', '.btn-delete', function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });

            // Auto-detect location
            if (navigator.geolocation) {
                $(document).on('click', '.btn-detect-location', function() {
                    App.detectLocation();
                });
            }

            // Like post
            $(document).on('click', '.btn-like', function() {
                const postId = $(this).data('post-id');
                App.likePost(postId);
            });
        },

        // ===== Authentication =====
        handleLogin: function() {
            const email = $('#email').val().trim();
            const password = $('#password').val();

            if (!this.validateEmail(email)) {
                this.showAlert('Please enter a valid email address.', 'danger');
                return;
            }

            if (password.length < 6) {
                this.showAlert('Password must be at least 6 characters.', 'danger');
                return;
            }

            const data = {
                action: 'login',
                email: email,
                password: password
            };

            $.post(config.apiBaseUrl + 'handler.php', data, function(response) {
                if (response.success) {
                    App.showAlert('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = (typeof baseUrl !== 'undefined' ? baseUrl : '/smartcashi/') + 'dashboard';
                    }, 1500);
                } else {
                    App.showAlert(response.message || 'Login failed.', 'danger');
                }
            });
        },

        handleRegister: function() {
            const firstName = $('#firstName').val().trim();
            const lastName = $('#lastName').val().trim();
            const email = $('#email').val().trim();
            const phone = $('#phone').val().trim();
            const password = $('#password').val();
            const passwordConfirm = $('#passwordConfirm').val();
            const role = $('input[name="role"]:checked').val() || 'farmer';

            // Validation
            if (!firstName) {
                this.showAlert('First name is required.', 'danger');
                return;
            }

            if (!this.validateEmail(email)) {
                this.showAlert('Please enter a valid email address.', 'danger');
                return;
            }

            if (!phone) {
                this.showAlert('Phone number is required.', 'danger');
                return;
            }

            if (password.length < 8) {
                this.showAlert('Password must be at least 8 characters.', 'danger');
                return;
            }

            if (password !== passwordConfirm) {
                this.showAlert('Passwords do not match.', 'danger');
                return;
            }

            const data = {
                action: 'register',
                firstName: firstName,
                lastName: lastName,
                email: email,
                phone: phone,
                password: password,
                role: role
            };

            $.post(config.apiBaseUrl + 'handler.php', data, function(response) {
                if (response.success) {
                    App.showAlert('Registration successful! Redirecting to login...', 'success');
                    setTimeout(() => {
                        window.location.href = (typeof baseUrl !== 'undefined' ? baseUrl : '/smartcashi/') + 'login';
                    }, 1500);
                } else {
                    App.showAlert(response.message || 'Registration failed.', 'danger');
                }
            });
        },

        // ===== Crop Management =====
        handleCropSubmit: function() {
            const cropName = $('#cropName').val().trim();
            const variety = $('#variety').val().trim();
            const area = $('#area').val();
            const plantedDate = $('#plantedDate').val();
            const expectedHarvest = $('#expectedHarvest').val();

            if (!cropName) {
                this.showAlert('Crop name is required.', 'danger');
                return;
            }

            if (!area || area <= 0) {
                this.showAlert('Please enter a valid area in hectares.', 'danger');
                return;
            }

            const data = {
                action: 'add-crop',
                cropName: cropName,
                variety: variety,
                area: area,
                plantedDate: plantedDate,
                expectedHarvest: expectedHarvest
            };

            $.post(config.apiBaseUrl + 'handler.php', data, function(response) {
                if (response.success) {
                    App.showAlert('Crop added successfully!', 'success');
                    $('#cropForm')[0].reset();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    App.showAlert(response.message || 'Failed to add crop.', 'danger');
                }
            });
        },

        // ===== Disease Detection =====
        handleDiseaseDetection: function() {
            const cropId = $('#cropId').val();
            const image = $('#diseaseImage')[0].files[0];

            if (!cropId) {
                this.showAlert('Please select a crop.', 'danger');
                return;
            }

            if (!image) {
                this.showAlert('Please select an image.', 'danger');
                return;
            }

            if (image.size > config.imageUploadLimit) {
                this.showAlert('Image size must be less than 50MB.', 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'analyze-disease');
            formData.append('cropId', cropId);
            formData.append('image', image);

            $.ajax({
                url: config.apiBaseUrl + 'handler.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        App.displayDiseaseResults(response.data);
                        App.showAlert('Disease analysis complete!', 'success');
                    } else {
                        App.showAlert(response.message || 'Analysis failed.', 'danger');
                    }
                }
            });
        },

        displayDiseaseResults: function(data) {
            const resultsHtml = `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Analysis Results</h3>
                    </div>
                    <div class="card-content">
                        <p><strong>Disease:</strong> ${data.disease || 'No disease detected'}</p>
                        <p><strong>Severity:</strong> <span class="badge badge-${this.getSeverityClass(data.severity)}">${data.severity || 'N/A'}</span></p>
                        <p><strong>Confidence:</strong> ${(data.confidence * 100).toFixed(2)}%</p>
                        ${data.treatment ? `<p><strong>Treatment:</strong> ${data.treatment}</p>` : ''}
                        ${data.recommendations ? `<p><strong>Recommendations:</strong> ${data.recommendations}</p>` : ''}
                    </div>
                </div>
            `;
            $('#diseaseResults').html(resultsHtml).show();
        },

        getSeverityClass: function(severity) {
            switch(severity.toLowerCase()) {
                case 'high': return 'danger';
                case 'medium': return 'warning';
                case 'low': return 'success';
                default: return 'info';
            }
        },

        // ===== Chat =====
        handleChatMessage: function() {
            const message = $('#chatInput').val().trim();
            const language = $('input[name="language"]:checked').val() || 'english';

            if (!message) {
                return;
            }

            // Add user message to chat
            this.addChatMessage(message, 'user');
            $('#chatInput').val('').focus();

            const data = {
                action: 'send-message',
                message: message,
                language: language
            };

            $.post(config.apiBaseUrl + 'handler.php', data, function(response) {
                if (response.success) {
                    App.addChatMessage(response.reply, 'bot');
                } else {
                    App.addChatMessage('Sorry, I could not process your request.', 'bot');
                }
                $('#chatContainer').scrollTop($('#chatContainer')[0].scrollHeight);
            });
        },

        addChatMessage: function(message, sender) {
            const messageHtml = `
                <div class="chat-message chat-${sender}">
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
            $('#chatContainer').append(messageHtml);
        },

        // ===== Community =====
        likePost: function(postId) {
            const data = {
                action: 'like-post',
                postId: postId
            };

            $.post(config.apiBaseUrl + 'handler.php', data, function(response) {
                if (response.success) {
                    $('[data-post-id="' + postId + '"]').text(response.likes + ' Likes');
                }
            });
        },

        // ===== Location =====
        detectLocation: function() {
            if (!navigator.geolocation) {
                this.showAlert('Geolocation is not supported by your browser.', 'danger');
                return;
            }

            const btn = $('.btn-detect-location');
            btn.prop('disabled', true).text('Detecting...');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    $('#locationLat').val(lat);
                    $('#locationLng').val(lng);

                    App.getReverseGeocoding(lat, lng);
                    btn.prop('disabled', false).text('Detect Location');
                },
                function(error) {
                    App.showAlert('Unable to get your location: ' + error.message, 'danger');
                    btn.prop('disabled', false).text('Detect Location');
                }
            );
        },

        getReverseGeocoding: function(lat, lng) {
            // Placeholder for reverse geocoding using Google Maps API
            // In production, call Google Maps Geocoding API
            console.log('Location detected:', lat, lng);
            this.showAlert('Location detected successfully!', 'success');
        },

        // ===== Utility Functions =====
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },

        showAlert: function(message, type = 'info') {
            const alertId = 'toast-' + Date.now();
            const alertHtml = `
                <div class="alert alert-${type}" id="${alertId}">
                    <span>${App.escapeHtml(message)}</span>
                </div>
            `;

            $('body').append(alertHtml);
            const $alert = $('#' + alertId);

            // Auto-remove after 4 seconds (toast style)
            const removeTimer = setTimeout(() => {
                $alert.addClass('removing');
                setTimeout(() => {
                    $alert.remove();
                }, 300); // Wait for animation
            }, 4000);

            // Allow manual dismissal on click
            $alert.on('click', function() {
                clearTimeout(removeTimer);
                $(this).addClass('removing');
                setTimeout(() => {
                    $(this).remove();
                }, 300);
            });
        },

        showModal: function(modalId) {
            $('#' + modalId).addClass('show');
        },

        hideModal: function(modalId) {
            $('#' + modalId).removeClass('show');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        App.init();
    });

    // Export App globally
    window.App = App;

})(jQuery);
