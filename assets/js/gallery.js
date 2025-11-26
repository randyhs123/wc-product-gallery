/**
 * WC Product Gallery JavaScript
 * Version: 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get all gallery instances on the page
    const galleries = document.querySelectorAll('.wc-gallery');
    
    galleries.forEach(initGallery);
    
    function initGallery(gallery) {
        const track = gallery.querySelector('.wc-main-track');
        const slides = gallery.querySelectorAll('.wc-slide');
        const thumbs = gallery.querySelectorAll('.wc-thumb');
        const thumbsContainer = gallery.querySelector('.wc-thumbs');
        
        if (!track || slides.length === 0) return;
        
        // ========================================
        // STATE
        // ========================================
        let index = 0;
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let isDragging = false;
        let isHorizontalSwipe = false;
        
        // ========================================
        // UPDATE SLIDE FUNCTION
        // ========================================
        function updateSlide() {
            track.style.transition = "transform 0.35s ease";
            track.style.transform = `translateX(-${index * 100}%)`;
            
            // Update active thumbnail
            thumbs.forEach(t => t.classList.remove('active'));
            if (thumbs[index]) {
                thumbs[index].classList.add('active');
                
            }
        }
        
        // ========================================
        // THUMBNAIL CLICK
        // ========================================
        thumbs.forEach((thumb, i) => {
            thumb.addEventListener('click', () => {
                index = i;
                updateSlide();
            });
        });
        
        // ========================================
        // TOUCH SWIPE EVENTS
        // ========================================
        track.addEventListener('touchstart', handleTouchStart, { passive: true });
        track.addEventListener('touchmove', handleTouchMove, { passive: false });
        track.addEventListener('touchend', handleTouchEnd, { passive: true });
        
        function handleTouchStart(e) {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            currentX = startX;
            isDragging = true;
            isHorizontalSwipe = false;
            track.style.transition = "none";
        }
        
        function handleTouchMove(e) {
            if (!isDragging) return;
            
            currentX = e.touches[0].clientX;
            const currentY = e.touches[0].clientY;
            const diffX = Math.abs(currentX - startX);
            const diffY = Math.abs(currentY - startY);
            
            // Detect swipe direction
            if (!isHorizontalSwipe && (diffX > 10 || diffY > 10)) {
                isHorizontalSwipe = diffX > diffY;
            }
            
            // Only prevent scroll if horizontal swipe
            if (isHorizontalSwipe) {
                e.preventDefault();
                const diff = currentX - startX;
                track.style.transform = `translateX(calc(-${index * 100}% + ${diff}px))`;
            }
        }
        
        function handleTouchEnd() {
            if (!isDragging) return;
            isDragging = false;
            
            if (isHorizontalSwipe) {
                const diff = currentX - startX;
                const threshold = 50; // pixels to trigger slide change
                
                if (diff < -threshold && index < slides.length - 1) {
                    index++;
                } else if (diff > threshold && index > 0) {
                    index--;
                }
                updateSlide();
            }
        }
        
        // ========================================
        // KEYBOARD NAVIGATION (Optional bonus)
        // ========================================
        document.addEventListener('keydown', (e) => {
            if (!gallery.matches(':hover')) return;
            
            if (e.key === 'ArrowLeft' && index > 0) {
                index--;
                updateSlide();
            } else if (e.key === 'ArrowRight' && index < slides.length - 1) {
                index++;
                updateSlide();
            }
        });
        
        // ========================================
        // RESIZE HANDLER
        // ========================================
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                updateSlide();
            }, 250);
        });
        
        // Initial setup
        updateSlide();
    }
});