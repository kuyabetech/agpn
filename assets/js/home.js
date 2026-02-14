// ============================================
// AGPN HOMEPAGE JAVASCRIPT - COMPLETE EDITION
// All Interactions: Animations, Stats Counter,
// Mobile Menu, Smooth Scroll, Parallax, etc.
// ============================================

(function($) {
    'use strict';

    // ============================================
    // 1. INITIALIZATION ON DOCUMENT READY
    // ============================================
    $(document).ready(function() {
        initAOS();
        initMobileMenu();
        initStatsCounter();
        initTrustBadges();
        initBackToTop();
        initSmoothScroll();
        initParallax();
        initNewsletterForm();
        initTestimonialSlider();
        initArmHoverEffects();
        initBlogCardEffects();
        initLazyLoading();
        initScrollAnimations();
        initVideoPlayer();
        initCookieConsent();
        initAnnouncementBar();
        initSearchToggle();
        initHeaderScroll();
        initPreloader();
    });

    // ============================================
    // 2. AOS (ANIMATE ON SCROLL) INITIALIZATION
    // ============================================
    function initAOS() {
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true,
                mirror: false,
                offset: 100,
                delay: 100,
                disable: 'mobile'
            });
        } else {
            // Fallback if AOS is not loaded
            console.warn('AOS library not loaded');
        }
    }

    // ============================================
    // 3. MOBILE MENU TOGGLE
    // ============================================
    function initMobileMenu() {
        const $navToggle = $('#navToggle');
        const $navMenu = $('#navMenu');
        const $body = $('body');
        const $overlay = $('<div class="nav-overlay"></div>');

        if ($navToggle.length && $navMenu.length) {
            // Toggle menu
            $navToggle.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const isActive = $navMenu.hasClass('active');
                
                $navMenu.toggleClass('active');
                $navToggle.toggleClass('active');
                $body.toggleClass('nav-open');
                
                if (!isActive) {
                    $body.append($overlay);
                    setTimeout(() => $overlay.addClass('active'), 10);
                } else {
                    $overlay.removeClass('active');
                    setTimeout(() => $overlay.remove(), 300);
                }
            });

            // Close menu when clicking overlay
            $overlay.on('click', function() {
                $navMenu.removeClass('active');
                $navToggle.removeClass('active');
                $body.removeClass('nav-open');
                $(this).removeClass('active');
                setTimeout(() => $(this).remove(), 300);
            });

            // Close menu on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $navMenu.hasClass('active')) {
                    $navMenu.removeClass('active');
                    $navToggle.removeClass('active');
                    $body.removeClass('nav-open');
                    $overlay.removeClass('active');
                    setTimeout(() => $overlay.remove(), 300);
                }
            });

            // Dropdown toggle for mobile
            $('.has-dropdown > a').on('click', function(e) {
                if ($(window).width() <= 991) {
                    e.preventDefault();
                    const $parent = $(this).parent('.has-dropdown');
                    $parent.toggleClass('active');
                    $parent.find('.dropdown-menu').slideToggle(300);
                }
            });
        }
    }

    // ============================================
    // 4. STATS COUNTER ANIMATION
    // ============================================
    function initStatsCounter() {
        const $statNumbers = $('.stat-number');
        
        if ($statNumbers.length) {
            const counterObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const $el = $(entry.target);
                        const target = parseInt($el.data('target')) || parseInt($el.text().replace(/[^0-9]/g, ''));
                        const suffix = $el.text().includes('+') ? '+' : '';
                        
                        animateCounter($el[0], 0, target, 2000, suffix);
                        counterObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            $statNumbers.each(function() {
                const $this = $(this);
                const target = parseInt($this.text().replace(/[^0-9]/g, ''));
                $this.data('target', target);
                counterObserver.observe(this);
            });
        }
    }

    function animateCounter(element, start, end, duration, suffix = '') {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            element.textContent = value.toLocaleString() + suffix;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    // ============================================
    // 5. TRUST BADGES ANIMATION
    // ============================================
    function initTrustBadges() {
        const $badges = $('.trust-badge');
        
        $badges.each(function(index) {
            $(this).css('animation-delay', `${index * 0.1}s`);
        });

        // Add hover effect
        $badges.on('mouseenter', function() {
            $(this).find('img').css('filter', 'brightness(1) invert(0)');
        }).on('mouseleave', function() {
            $(this).find('img').css('filter', 'brightness(0) invert(1)');
        });
    }

    // ============================================
    // 6. BACK TO TOP BUTTON
    // ============================================
    function initBackToTop() {
        const $backToTop = $('<button class="back-to-top" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>');
        $('body').append($backToTop);

        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 300) {
                $backToTop.addClass('show');
            } else {
                $backToTop.removeClass('show');
            }
        });

        $backToTop.on('click', function(e) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: 0
            }, 600, 'easeInOutCubic');
        });
    }

    // ============================================
    // 7. SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================
    function initSmoothScroll() {
        $('a[href*="#"]:not([href="#"]):not(.no-smooth)').on('click', function(e) {
            const $target = $(this.hash);
            
            if ($target.length) {
                e.preventDefault();
                
                const headerHeight = $('.site-header').outerHeight() || 0;
                const targetOffset = $target.offset().top - headerHeight - 20;
                
                $('html, body').animate({
                    scrollTop: targetOffset
                }, 800, 'easeInOutCubic');
                
                // Update URL without jumping
                if (history.pushState) {
                    history.pushState(null, null, this.hash);
                }
            }
        });
    }

    // ============================================
    // 8. PARALLAX EFFECT FOR HERO
    // ============================================
    function initParallax() {
        const $hero = $('.hero');
        
        if ($hero.length && !isMobile()) {
            $(window).on('scroll', function() {
                const scrolled = $(this).scrollTop();
                $hero.css('background-position-y', `${scrolled * 0.5}px`);
            });
        }
    }

    // ============================================
    // 9. NEWSLETTER FORM HANDLING
    // ============================================
    function initNewsletterForm() {
        $('.newsletter-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $email = $form.find('input[type="email"]');
            const $button = $form.find('button[type="submit"]');
            const $message = $('<div class="form-message"></div>');
            
            // Remove any existing messages
            $form.find('.form-message').remove();
            
            // Validate email
            if (!isValidEmail($email.val())) {
                showFormMessage($form, 'Please enter a valid email address.', 'error');
                return;
            }
            
            // Disable form
            $email.prop('disabled', true);
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Subscribing...');
            
            // AJAX request
            $.ajax({
                url: $form.attr('action') || '/forms/newsletter-handler.php',
                method: 'POST',
                data: {
                    email: $email.val(),
                    ajax: true
                },
                success: function(response) {
                    showFormMessage($form, 'Thank you for subscribing! Please check your email.', 'success');
                    $email.val('');
                },
                error: function(xhr, status, error) {
                    showFormMessage($form, 'Something went wrong. Please try again later.', 'error');
                    console.error('Newsletter error:', error);
                },
                complete: function() {
                    $email.prop('disabled', false);
                    $button.prop('disabled', false).html('Subscribe <i class="fas fa-paper-plane"></i>');
                }
            });
        });
    }

    function showFormMessage($form, message, type) {
        const $message = $(`<div class="form-message form-message-${type}">${message}</div>`);
        $form.append($message);
        
        setTimeout(() => {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }

    // ============================================
    // 10. TESTIMONIAL SLIDER
    // ============================================
    function initTestimonialSlider() {
        const $grid = $('.testimonials-grid');
        
        if ($grid.length && $grid.children().length > 3) {
            // Initialize Slick Slider if available
            if (typeof $.fn.slick !== 'undefined') {
                $grid.slick({
                    slidesToShow: 3,
                    slidesToScroll: 1,
                    autoplay: true,
                    autoplaySpeed: 5000,
                    dots: true,
                    arrows: true,
                    prevArrow: '<button type="button" class="slick-prev"><i class="fas fa-chevron-left"></i></button>',
                    nextArrow: '<button type="button" class="slick-next"><i class="fas fa-chevron-right"></i></button>',
                    responsive: [
                        {
                            breakpoint: 992,
                            settings: {
                                slidesToShow: 2
                            }
                        },
                        {
                            breakpoint: 768,
                            settings: {
                                slidesToShow: 1,
                                arrows: false
                            }
                        }
                    ]
                });
            }
        }
    }

    // ============================================
    // 11. ARM CARD HOVER EFFECTS
    // ============================================
    function initArmHoverEffects() {
        $('.arm-card').on('mouseenter', function() {
            const $icon = $(this).find('.arm-icon');
            $icon.css('transform', 'scale(1.1) rotate(5deg)');
        }).on('mouseleave', function() {
            const $icon = $(this).find('.arm-icon');
            $icon.css('transform', 'scale(1) rotate(0)');
        });
    }

    // ============================================
    // 12. BLOG CARD EFFECTS
    // ============================================
    function initBlogCardEffects() {
        $('.blog-card').on('mouseenter', function() {
            const $image = $(this).find('.blog-image img');
            $image.css('transform', 'scale(1.1)');
        }).on('mouseleave', function() {
            const $image = $(this).find('.blog-image img');
            $image.css('transform', 'scale(1)');
        });
    }

    // ============================================
    // 13. LAZY LOADING IMAGES
    // ============================================
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.dataset.src;
                        
                        if (src) {
                            img.src = src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });

            $('img.lazy').each(function() {
                imageObserver.observe(this);
            });
        } else {
            // Fallback for older browsers
            $('img.lazy').each(function() {
                const $img = $(this);
                $img.attr('src', $img.data('src'));
            });
        }
    }

    // ============================================
    // 14. SCROLL ANIMATIONS
    // ============================================
    function initScrollAnimations() {
        const animateElements = document.querySelectorAll('[data-animate]');
        
        if (animateElements.length && 'IntersectionObserver' in window) {
            const animationObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const animation = element.dataset.animate || 'fadeInUp';
                        const delay = element.dataset.delay || 0;
                        
                        setTimeout(() => {
                            element.classList.add('animated', animation);
                        }, delay);
                        
                        animationObserver.unobserve(element);
                    }
                });
            }, { threshold: 0.2 });

            animateElements.forEach(el => animationObserver.observe(el));
        }
    }

    // ============================================
    // 15. VIDEO PLAYER (IF APPLICABLE)
    // ============================================
    function initVideoPlayer() {
        $('.video-play-btn').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const videoId = $btn.data('video-id');
            const $container = $btn.closest('.video-container');
            
            if (videoId) {
                const iframe = `<iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                $container.html(iframe);
            }
        });
    }

    // ============================================
    // 16. COOKIE CONSENT
    // ============================================
    function initCookieConsent() {
        if (!localStorage.getItem('cookieConsent')) {
            const $cookieBar = $(`
                <div class="cookie-consent">
                    <div class="container">
                        <div class="cookie-content">
                            <div class="cookie-text">
                                <i class="fas fa-cookie-bite"></i>
                                <span>We use cookies to enhance your experience. By continuing to visit this site you agree to our use of cookies.</span>
                            </div>
                            <div class="cookie-buttons">
                                <button class="btn btn-sm btn-primary" id="cookieAccept">Accept</button>
                                <button class="btn btn-sm btn-outline" id="cookieDecline">Decline</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append($cookieBar);
            
            setTimeout(() => {
                $('.cookie-consent').addClass('active');
            }, 1000);
            
            $('#cookieAccept').on('click', function() {
                localStorage.setItem('cookieConsent', 'accepted');
                $('.cookie-consent').removeClass('active');
                setTimeout(() => $('.cookie-consent').remove(), 300);
            });
            
            $('#cookieDecline').on('click', function() {
                localStorage.setItem('cookieConsent', 'declined');
                $('.cookie-consent').removeClass('active');
                setTimeout(() => $('.cookie-consent').remove(), 300);
            });
        }
    }

    // ============================================
    // 17. ANNOUNCEMENT BAR
    // ============================================
    function initAnnouncementBar() {
        const $bar = $('.announcement-bar');
        
        if ($bar.length && !localStorage.getItem('announcementClosed')) {
            $bar.show();
            
            $('.announcement-close').on('click', function() {
                $bar.slideUp(300, function() {
                    $(this).remove();
                });
                localStorage.setItem('announcementClosed', 'true');
            });
        }
    }

    // ============================================
    // 18. SEARCH TOGGLE
    // ============================================
    function initSearchToggle() {
        const $searchToggle = $('.search-toggle');
        const $searchOverlay = $('.search-overlay');
        const $searchInput = $('.search-input');
        
        if ($searchToggle.length) {
            $searchToggle.on('click', function(e) {
                e.preventDefault();
                $searchOverlay.toggleClass('active');
                
                if ($searchOverlay.hasClass('active')) {
                    setTimeout(() => $searchInput.focus(), 300);
                    $('body').addClass('search-open');
                } else {
                    $('body').removeClass('search-open');
                }
            });
            
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $searchOverlay.hasClass('active')) {
                    $searchOverlay.removeClass('active');
                    $('body').removeClass('search-open');
                }
            });
        }
    }

    // ============================================
    // 19. HEADER SCROLL EFFECT
    // ============================================
    function initHeaderScroll() {
        const $header = $('.site-header');
        let lastScroll = 0;
        
        $(window).on('scroll', function() {
            const currentScroll = $(this).scrollTop();
            
            if (currentScroll > 50) {
                $header.addClass('scrolled');
            } else {
                $header.removeClass('scrolled');
            }
            
            // Hide/show on scroll direction
            if (currentScroll > lastScroll && currentScroll > 200) {
                $header.addClass('header-hidden');
            } else {
                $header.removeClass('header-hidden');
            }
            
            lastScroll = currentScroll;
        });
    }

    // ============================================
    // 20. PRELOADER
    // ============================================
    function initPreloader() {
        const $preloader = $('#preloader');
        
        if ($preloader.length) {
            $(window).on('load', function() {
                $preloader.fadeOut(600, function() {
                    $(this).remove();
                });
            });
            
            // Fallback in case window.load doesn't fire
            setTimeout(function() {
                if ($preloader.length) {
                    $preloader.fadeOut(600);
                }
            }, 3000);
        }
    }

    // ============================================
    // 21. UTILITY FUNCTIONS
    // ============================================
    
    // Check if mobile device
    function isMobile() {
        return window.innerWidth <= 768;
    }

    // Debounce function for performance
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Throttle function for performance
    function throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // ============================================
    // 22. WINDOW RESIZE HANDLER
    // ============================================
    $(window).on('resize', debounce(function() {
        // Close mobile menu on resize to desktop
        if (window.innerWidth > 991) {
            const $navMenu = $('#navMenu');
            const $navToggle = $('#navToggle');
            
            if ($navMenu.hasClass('active')) {
                $navMenu.removeClass('active');
                $navToggle.removeClass('active');
                $('body').removeClass('nav-open');
                $('.nav-overlay').remove();
            }
            
            // Reset dropdowns
            $('.has-dropdown').removeClass('active');
            $('.dropdown-menu').removeAttr('style');
        }
    }, 250));

    // ============================================
    // 23. AJAX FORM HANDLING
    // ============================================
    $(document).on('submit', '.ajax-form', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const originalText = $button.html();
        
        // Disable button
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: $form.attr('action'),
            method: $form.attr('method') || 'POST',
            data: $form.serialize(),
            success: function(response) {
                // Show success message
                const $success = $('<div class="alert alert-success">Form submitted successfully!</div>');
                $form.prepend($success);
                
                setTimeout(() => $success.fadeOut(), 5000);
                $form.trigger('reset');
            },
            error: function(xhr, status, error) {
                // Show error message
                const $error = $('<div class="alert alert-error">Something went wrong. Please try again.</div>');
                $form.prepend($error);
                
                setTimeout(() => $error.fadeOut(), 5000);
                console.error('Form error:', error);
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // ============================================
    // 24. EASING FUNCTIONS (FALLBACK)
    // ============================================
    $.easing.easeInOutCubic = function(x, t, b, c, d) {
        if ((t /= d / 2) < 1) return c / 2 * t * t * t + b;
        return c / 2 * ((t -= 2) * t * t + 2) + b;
    };

})(jQuery);

// ============================================
// 25. VANILLA JS FALLBACKS (NO JQUERY)
// ============================================

// Ensure jQuery is loaded
if (typeof jQuery === 'undefined') {
    console.warn('jQuery is not loaded. Some features may not work.');
    
    // Basic fallback for DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu vanilla fallback
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function(e) {
                e.preventDefault();
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
        }
        
        // Back to top vanilla fallback
        const backToTop = document.querySelector('.back-to-top');
        if (backToTop) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTop.classList.add('show');
                } else {
                    backToTop.classList.remove('show');
                }
            });
            
            backToTop.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
    });
}

// ============================================
// 26. SERVICE WORKER REGISTRATION (PWA)
// ============================================
if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').then(function(registration) {
            console.log('ServiceWorker registered:', registration.scope);
        }).catch(function(error) {
            console.log('ServiceWorker registration failed:', error);
        });
    });
}

// ============================================
// 27. PERFORMANCE MARKERS
// ============================================
window.addEventListener('load', function() {
    if (window.performance) {
        const perfData = window.performance.timing;
        const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
        console.log(`Page load time: ${pageLoadTime}ms`);
    }
});

// ============================================
// 28. ERROR TRACKING
// ============================================
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.message, 'at', e.filename, 'line', e.lineno);
    // You can send this to your error tracking service
});

// ============================================
// 29. EXPORT FUNCTIONS FOR GLOBAL USE
// ============================================
window.AGPN = {
    initStatsCounter: initStatsCounter,
    initMobileMenu: initMobileMenu,
    initBackToTop: initBackToTop,
    isMobile: isMobile,
    debounce: debounce,
    throttle: throttle
};

// ============================================
// END OF HOMEPAGE JAVASCRIPT
// AGPN - Empowering Global Excellence
// ============================================