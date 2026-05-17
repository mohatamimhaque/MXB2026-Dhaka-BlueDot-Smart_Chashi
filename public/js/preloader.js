/* =======================================================
   SMART CHASHI PRELOADER
   - Auto-hide after 50% images loaded
   - Uses existing logo, leaves animation, text, progress bar
   - Smooth fade-out
========================================================= */

(function () {
    'use strict';

    const preloader = document.getElementById('preloader');
    if (!preloader) return;

    const progressFill = document.querySelector('.progress-fill');

    // Stop scrolling while preloader is active
    document.body.style.overflow = 'hidden';

    function hidePreloader() {
        if (!preloader.classList.contains('fade-out')) {
            preloader.classList.add('fade-out');

            // Smooth removal after fade animation
            setTimeout(() => {
                preloader.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 700); // Match your CSS transition time
        }
    }

    function initImageTracking() {
        // Collect all page images
        const images = Array.from(document.images);
        let totalImages = images.length;
        let loadedImages = 0;

        // If no images, hide after short delay
        if (totalImages === 0) {
            setTimeout(hidePreloader, 300);
            return;
        }

        function updateProgress() {
            loadedImages++;
            let percentage = Math.round((loadedImages / totalImages) * 100);

            // Update progress bar width
            if (progressFill) {
                progressFill.style.width = percentage + '%';
            }

            // Auto-hide at 50% load
            if (percentage >= 50) {
                hidePreloader();
            }
        }

        // Track image load events
        images.forEach(img => {
            if (img.complete) {
                updateProgress();
            } else {
                img.addEventListener('load', updateProgress);
                img.addEventListener('error', updateProgress);
            }
        });
    }

    // Wait for DOM to be ready before counting images
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initImageTracking);
    } else {
        // DOM already loaded, run after a small delay to ensure all elements are parsed
        setTimeout(initImageTracking, 50);
    }

    // Fallback: hide after 5 seconds max
    setTimeout(hidePreloader, 5000);

})();

