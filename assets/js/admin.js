// ============================================
// AGPN MAIN JAVASCRIPT
// ============================================

$(document).ready(function() {
    
    // Mobile Navigation Toggle
    $('.nav-toggle').click(function() {
        $('.nav-menu').toggleClass('active');
        $(this).toggleClass('active');
    });
    
    // Smooth Scroll for Anchor Links
    $('a[href*="#"]').not('[href="#"]').click(function(e) {
        if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') 
            && location.hostname === this.hostname) {
            var target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 1000);
            }
        }
    });
    
    // Active Navigation Link
    var currentLocation = window.location.pathname;
    $('.nav-link').each(function() {
        var link = $(this).attr('href');
        if (currentLocation.includes(link) && link !== '/') {
            $(this).addClass('active');
        } else if (currentLocation === '/' && link === '/') {
            $(this).addClass('active');
        }
    });
    
    // Testimonial Slider (if exists)
    if ($('.testimonial-slider').length) {
        // Simple fade effect - replace with proper slider if needed
        var currentSlide = 0;
        var slides = $('.testimonial-slide');
        var slideCount = slides.length;
        
        if (slideCount > 1) {
            slides.hide();
            slides.eq(0).show();
            
            setInterval(function() {
                slides.eq(currentSlide).fadeOut(500);
                currentSlide = (currentSlide + 1) % slideCount;
                slides.eq(currentSlide).fadeIn(500);
            }, 5000);
        }
    }
    
    // Form Validation
    $('form').submit(function(e) {
        var isValid = true;
        $(this).find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
    
    // Lazy Loading Images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        $('img.lazy').each(function() {
            imageObserver.observe(this);
        });
    }
    
    // Scroll Animations
    function checkScroll() {
        $('.animate-on-scroll').each(function() {
            var elementTop = $(this).offset().top;
            var viewportBottom = $(window).scrollTop() + $(window).height();
            
            if (elementTop < viewportBottom - 50) {
                $(this).addClass('animated');
            }
        });
    }
    
    $(window).scroll(checkScroll);
    checkScroll(); // Run on load
    
});