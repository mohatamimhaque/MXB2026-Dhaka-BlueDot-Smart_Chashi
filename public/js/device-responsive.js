/**
 * ============================================
 * DEVICE RESPONSIVE CSS LOADER
 * Smart Chashi - AI Smart Farming Platform
 * 
 * This script detects the device type and loads
 * appropriate CSS files for optimal performance
 * ============================================
 */

(function () {
    'use strict';

    // Get base URL from various sources
    function getBaseURL() {
        // Try window.BASE_URL first
        if (window.BASE_URL) {
            return window.BASE_URL;
        }

        // Try to detect from current script path
        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++) {
            const src = scripts[i].src;
            if (src && src.includes('device-responsive.js')) {
                // Extract base URL from script path
                const match = src.match(/(.+?)public\/js\/device-responsive\.js/);
                if (match) {
                    return match[1];
                }
            }
        }

        // Try to detect from current page URL
        const pathname = window.location.pathname;
        const baseMatch = pathname.match(/(.+?\/smartchashi\/)/i);
        if (baseMatch) {
            return window.location.origin + baseMatch[1];
        }

        // Default fallback
        return window.location.origin + '/';
    }

    // Configuration
    const baseURL = getBaseURL();
    const CONFIG = {
        cssPath: baseURL + 'public/css/',
        breakpoints: {
            mobile: 767,      // Max width for mobile
            tablet: 991,      // Max width for tablet
            desktop: 1199     // Max width for desktop
        },
        cssFiles: {
            responsive: 'pages-responsive.css',
            mobile: 'pages-mobile.css',
            tablet: 'pages-tablet.css'
        }
    };

    /**
     * Detect device type based on screen size and user agent
     * @returns {string} 'mobile' | 'tablet' | 'desktop'
     */
    function detectDeviceType() {
        const width = window.innerWidth;
        const userAgent = navigator.userAgent.toLowerCase();

        // Check for mobile devices
        const isMobileUA = /android|webos|iphone|ipod|blackberry|iemobile|opera mini|mobile/i.test(userAgent);
        const isTabletUA = /ipad|tablet|playbook|silk/i.test(userAgent);

        // Android tablets often report as android but not mobile
        const isAndroidTablet = /android/i.test(userAgent) && !/mobile/i.test(userAgent);

        // Screen-based detection with UA hints
        if (width <= CONFIG.breakpoints.mobile || (isMobileUA && !isTabletUA && !isAndroidTablet)) {
            return 'mobile';
        } else if (width <= CONFIG.breakpoints.tablet || isTabletUA || isAndroidTablet) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Check if device supports touch
     * @returns {boolean}
     */
    function isTouchDevice() {
        return (('ontouchstart' in window) ||
            (navigator.maxTouchPoints > 0) ||
            (navigator.msMaxTouchPoints > 0));
    }

    /**
     * Get device pixel ratio
     * @returns {number}
     */
    function getDevicePixelRatio() {
        return window.devicePixelRatio || 1;
    }

    /**
     * Load a CSS file dynamically
     * @param {string} filename - CSS filename
     * @param {string} id - Unique ID for the link element
     * @param {boolean} async - Load asynchronously (non-blocking)
     */
    function loadCSS(filename, id, async = false) {
        // Check if already loaded
        const existing = document.getElementById(id);
        if (existing) {
            return;
        }

        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = CONFIG.cssPath + filename + '?v=' + Date.now(); // Cache busting

        if (async) {
            link.media = 'print';
            link.onload = function () {
                this.media = 'all';
            };
        }

        // Insert at the end of head for proper cascade
        document.head.appendChild(link);

        console.log('[DeviceResponsive] Loaded CSS:', CONFIG.cssPath + filename);
    }

    /**
     * Remove a dynamically loaded CSS file
     * @param {string} id - ID of the link element to remove
     */
    function unloadCSS(id) {
        const element = document.getElementById(id);
        if (element) {
            element.parentNode.removeChild(element);
        }
    }

    /**
     * Apply device-specific body classes
     * @param {string} deviceType 
     */
    function applyDeviceClasses(deviceType) {
        const classes = ['device-mobile', 'device-tablet', 'device-desktop'];
        const body = document.body;

        if (!body) return;

        // Remove all device classes
        classes.forEach(cls => body.classList.remove(cls));

        // Add current device class
        body.classList.add('device-' + deviceType);

        // Add touch class if applicable
        if (isTouchDevice()) {
            body.classList.add('touch-device');
        } else {
            body.classList.remove('touch-device');
        }

        // Add retina class if applicable
        if (getDevicePixelRatio() >= 2) {
            body.classList.add('retina-display');
        }

        // Add orientation class
        const orientation = window.innerWidth > window.innerHeight ? 'landscape' : 'portrait';
        body.classList.remove('orientation-portrait', 'orientation-landscape');
        body.classList.add('orientation-' + orientation);
    }

    /**
     * Load appropriate CSS based on device type
     * @param {string} deviceType 
     */
    function loadDeviceCSS(deviceType) {
        // Always load responsive CSS first (contains media queries)
        loadCSS(CONFIG.cssFiles.responsive, 'css-responsive');

        // For mobile and tablet, always load mobile CSS (contains touch optimizations)
        if (deviceType === 'mobile' || deviceType === 'tablet') {
            loadCSS(CONFIG.cssFiles.mobile, 'css-mobile');
        } else {
            unloadCSS('css-mobile');
        }

        // Load tablet-specific CSS for tablets
        if (deviceType === 'tablet') {
            loadCSS(CONFIG.cssFiles.tablet, 'css-tablet');
        } else {
            unloadCSS('css-tablet');
        }
    }

    /**
     * Initialize the device responsive system
     */
    function init() {
        const deviceType = detectDeviceType();

        // Store device info globally
        window.DEVICE_INFO = {
            type: deviceType,
            isTouch: isTouchDevice(),
            pixelRatio: getDevicePixelRatio(),
            screenWidth: window.innerWidth,
            screenHeight: window.innerHeight,
            orientation: window.innerWidth > window.innerHeight ? 'landscape' : 'portrait',
            baseURL: baseURL
        };

        // Apply classes and load CSS
        if (document.body) {
            applyDeviceClasses(deviceType);
        }
        loadDeviceCSS(deviceType);

        // Log for debugging
        console.log('[DeviceResponsive] Initialized:', window.DEVICE_INFO);
        console.log('[DeviceResponsive] CSS Path:', CONFIG.cssPath);
    }

    /**
     * Handle resize events with debouncing
     */
    let resizeTimeout;
    function handleResize() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
            const newDeviceType = detectDeviceType();

            // Only update if device type changed
            if (window.DEVICE_INFO && window.DEVICE_INFO.type !== newDeviceType) {
                window.DEVICE_INFO.type = newDeviceType;
                window.DEVICE_INFO.screenWidth = window.innerWidth;
                window.DEVICE_INFO.screenHeight = window.innerHeight;
                window.DEVICE_INFO.orientation = window.innerWidth > window.innerHeight ? 'landscape' : 'portrait';

                applyDeviceClasses(newDeviceType);
                loadDeviceCSS(newDeviceType);

                // Dispatch custom event for other scripts
                window.dispatchEvent(new CustomEvent('deviceTypeChanged', {
                    detail: { deviceType: newDeviceType }
                }));
            }
        }, 250);
    }

    /**
     * Handle orientation change
     */
    function handleOrientationChange() {
        if (window.DEVICE_INFO) {
            window.DEVICE_INFO.orientation = window.innerWidth > window.innerHeight ? 'landscape' : 'portrait';
            if (document.body) {
                document.body.classList.remove('orientation-portrait', 'orientation-landscape');
                document.body.classList.add('orientation-' + window.DEVICE_INFO.orientation);
            }
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also re-apply classes when body is available
    if (document.readyState !== 'complete') {
        window.addEventListener('load', function () {
            if (window.DEVICE_INFO) {
                applyDeviceClasses(window.DEVICE_INFO.type);
            }
        });
    }

    // Listen for resize events
    window.addEventListener('resize', handleResize);

    // Listen for orientation changes
    window.addEventListener('orientationchange', handleOrientationChange);

    // Expose public API
    window.DeviceResponsive = {
        getDeviceType: detectDeviceType,
        isTouchDevice: isTouchDevice,
        getDevicePixelRatio: getDevicePixelRatio,
        loadCSS: loadCSS,
        unloadCSS: unloadCSS,
        refresh: init,
        config: CONFIG
    };

})();
