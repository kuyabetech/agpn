<?php
// ============================================
// ADMIN MASTER FOOTER - FIXED
// Include this at the bottom of every admin page
// ============================================

// Ensure this file is included, not accessed directly
if (!defined('IN_ADMIN') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    exit('Direct access not allowed');
}
?>

            </div> <!-- /.content-body -->
        </main> <!-- /.main-content -->
    </div> <!-- /.admin-wrapper -->

    <!-- ============================================
         SIDEBAR OVERLAY FOR MOBILE
    ============================================ -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ============================================
         GLOBAL SEARCH MODAL
    ============================================ -->
    <div id="searchModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Global Search</h3>
                <span class="close-modal" id="closeSearchModal" style="font-size: 28px; cursor: pointer;">&times;</span>
            </div>
            <input type="text" class="form-control" id="searchInput" placeholder="Search pages, posts, users..." autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid var(--gray-200); border-radius: 8px;">
            <div id="searchResults" style="margin-top: 20px; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>

    <!-- ============================================
         USER DROPDOWN MENU
    ============================================ -->
    <div id="userDropdownMenu" style="display: none; position: absolute; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--gray-200); min-width: 200px; z-index: 1001;">
        <a href="profile.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--gray-700); text-decoration: none; border-bottom: 1px solid var(--gray-200);">
            <i class="fas fa-user" style="width: 20px; color: var(--gold);"></i> My Profile
        </a>
        <a href="settings.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--gray-700); text-decoration: none; border-bottom: 1px solid var(--gray-200);">
            <i class="fas fa-cog" style="width: 20px; color: var(--gold);"></i> Settings
        </a>
        <a href="logout.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: var(--gray-700); text-decoration: none;">
            <i class="fas fa-sign-out-alt" style="width: 20px; color: var(--gold);"></i> Logout
        </a>
    </div>

    <!-- ============================================
         SCRIPTS - jQuery first, then admin.js
    ============================================ -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <!-- ============================================
         MASTER ADMIN JAVASCRIPT
    ============================================ -->
    <script>
        (function($) {
            'use strict';
            
            // ========================================
            // DOCUMENT READY
            // ========================================
            $(document).ready(function() {
                initMobileMenu();
                initThemeToggle();
                initGlobalSearch();
                initUserDropdown();
                initToastNotifications();
                initKeyboardShortcuts();
            });
            
            // ========================================
            // MOBILE MENU TOGGLE
            // ========================================
            function initMobileMenu() {
                const $menuToggle = $('#menuToggle');
                const $sidebar = $('#adminSidebar');
                const $overlay = $('#sidebarOverlay');
                
                function closeSidebar() {
                    $sidebar.removeClass('active');
                    $overlay.removeClass('active');
                    $('body').css('overflow', '');
                }
                
                function openSidebar() {
                    $sidebar.addClass('active');
                    $overlay.addClass('active');
                    $('body').css('overflow', 'hidden');
                }
                
                $menuToggle.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if ($sidebar.hasClass('active')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
                
                $overlay.on('click', closeSidebar);
                
                $(window).on('resize', function() {
                    if ($(window).width() > 768) {
                        closeSidebar();
                    }
                });
            }
            
            // ========================================
            // THEME TOGGLE (DARK/LIGHT MODE)
            // ========================================
            function initThemeToggle() {
                const $themeToggle = $('#themeToggle');
                
                // Check for saved theme preference
                const savedTheme = localStorage.getItem('darkMode');
                if (savedTheme === 'true') {
                    $('body').addClass('dark-mode');
                    $themeToggle.find('.fa-sun').removeClass('active');
                    $themeToggle.find('.fa-moon').addClass('active');
                }
                
                $themeToggle.on('click', function() {
                    $('body').toggleClass('dark-mode');
                    const $sun = $(this).find('.fa-sun');
                    const $moon = $(this).find('.fa-moon');
                    
                    $sun.toggleClass('active');
                    $moon.toggleClass('active');
                    
                    localStorage.setItem('darkMode', $('body').hasClass('dark-mode'));
                });
            }
            
            // ========================================
            // GLOBAL SEARCH
            // ========================================
            function initGlobalSearch() {
                const $searchInput = $('#globalSearch');
                const $searchModal = $('#searchModal');
                const $modalSearchInput = $('#searchInput');
                const $closeModal = $('#closeSearchModal');
                const $searchResults = $('#searchResults');
                
                let searchTimeout;
                
                $searchInput.on('focus', function() {
                    $searchModal.fadeIn(300);
                    setTimeout(() => $modalSearchInput.focus(), 300);
                });
                
                $closeModal.on('click', function() {
                    $searchModal.fadeOut(300);
                });
                
                $(window).on('click', function(e) {
                    if ($(e.target).is($searchModal)) {
                        $searchModal.fadeOut(300);
                    }
                });
                
                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape' && $searchModal.is(':visible')) {
                        $searchModal.fadeOut(300);
                    }
                });
                
                $modalSearchInput.on('keyup', function() {
                    clearTimeout(searchTimeout);
                    
                    const query = $(this).val().trim();
                    
                    if (query.length > 2) {
                        searchTimeout = setTimeout(() => {
                            $.ajax({
                                url: 'ajax-search.php',
                                method: 'POST',
                                data: { query: query },
                                success: function(response) {
                                    $searchResults.html(response);
                                },
                                error: function() {
                                    $searchResults.html('<p style="color: var(--gray-600); padding: 20px; text-align: center;">Search failed. Please try again.</p>');
                                }
                            });
                        }, 300);
                    } else {
                        $searchResults.html('');
                    }
                });
            }
            
            // ========================================
            // USER DROPDOWN
            // ========================================
            function initUserDropdown() {
                const $userDropdown = $('#userDropdown');
                const $dropdownMenu = $('#userDropdownMenu');
                
                $userDropdown.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const offset = $(this).offset();
                    const height = $(this).outerHeight();
                    
                    $dropdownMenu.css({
                        position: 'absolute',
                        top: offset.top + height + 10,
                        right: 20,
                        display: 'block',
                        zIndex: 1000
                    }).fadeIn(200);
                });
                
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#userDropdown, #userDropdownMenu').length) {
                        $dropdownMenu.fadeOut(200);
                    }
                });
            }
            
            // ========================================
            // TOAST NOTIFICATIONS
            // ========================================
            function initToastNotifications() {
                $('.toast-notification').each(function() {
                    const $toast = $(this);
                    setTimeout(() => {
                        $toast.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 5000);
                });
            }
            
            // ========================================
            // KEYBOARD SHORTCUTS
            // ========================================
            function initKeyboardShortcuts() {
                $(document).on('keydown', function(e) {
                    // Ctrl/Cmd + / - Focus search
                    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                        e.preventDefault();
                        $('#globalSearch').focus();
                    }
                    
                    // Ctrl/Cmd + , - Open settings
                    if ((e.ctrlKey || e.metaKey) && e.key === ',') {
                        e.preventDefault();
                        window.location.href = 'settings.php';
                    }
                    
                    // Ctrl/Cmd + D - Go to dashboard
                    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                        e.preventDefault();
                        window.location.href = 'dashboard.php';
                    }
                });
            }
            
            // ========================================
            // TIME AGO FUNCTION
            // ========================================
            window.timeAgo = function(date) {
                const seconds = Math.floor((new Date() - new Date(date)) / 1000);
                
                let interval = Math.floor(seconds / 31536000);
                if (interval > 1) return interval + ' years ago';
                
                interval = Math.floor(seconds / 2592000);
                if (interval > 1) return interval + ' months ago';
                
                interval = Math.floor(seconds / 86400);
                if (interval > 1) return interval + ' days ago';
                
                interval = Math.floor(seconds / 3600);
                if (interval > 1) return interval + ' hours ago';
                
                interval = Math.floor(seconds / 60);
                if (interval > 1) return interval + ' minutes ago';
                
                return Math.floor(seconds) + ' seconds ago';
            };
            
        })(jQuery);
    </script>
    
    <!-- ============================================
         PAGE SPECIFIC JAVASCRIPT
    ============================================ -->
    <?php if (isset($page_js)): ?>
        <?php if (is_array($page_js)): ?>
            <?php foreach ($page_js as $js_file): ?>
                <script src="../assets/js/<?php echo $js_file; ?>?v=<?php echo file_exists('../assets/js/' . $js_file) ? filemtime('../assets/js/' . $js_file) : '1.0'; ?>"></script>
            <?php endforeach; ?>
        <?php else: ?>
            <script src="../assets/js/<?php echo $page_js; ?>?v=<?php echo file_exists('../assets/js/' . $page_js) ? filemtime('../assets/js/' . $page_js) : '1.0'; ?>"></script>
        <?php endif; ?>
    <?php endif; ?>
    
</body>
</html>