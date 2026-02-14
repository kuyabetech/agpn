<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo isset($meta_description) ? htmlspecialchars($meta_description) : htmlspecialchars(getSetting('site_tagline', 'Empowering Global Excellence')); ?>">
    <meta name="keywords" content="<?php echo isset($meta_keywords) ? htmlspecialchars($meta_keywords) : 'Afroglobe, AGPN, Prime Network, business growth, digital skills, study abroad, Nigeria, Africa, innovation, technology'; ?>">
    <meta name="author" content="Afroglobe Prime Network Limited">
    <meta name="robots" content="<?php echo isset($meta_robots) ? $meta_robots : 'index, follow'; ?>">
    
    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="<?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . SITE_NAME : SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo isset($meta_description) ? htmlspecialchars($meta_description) : htmlspecialchars(getSetting('site_tagline', 'Empowering Global Excellence')); ?>">
    <meta property="og:image" content="<?php echo isset($og_image) ? SITE_URL . $og_image : SITE_URL . '/assets/images/og-default.jpg'; ?>">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . SITE_NAME : SITE_NAME; ?>">
    <meta name="twitter:description" content="<?php echo isset($meta_description) ? htmlspecialchars($meta_description) : htmlspecialchars(getSetting('site_tagline', 'Empowering Global Excellence')); ?>">
    <meta name="twitter:image" content="<?php echo isset($og_image) ? SITE_URL . $og_image : SITE_URL . '/assets/images/twitter-default.jpg'; ?>">
    <meta name="twitter:site" content="@agpn_africa">
    
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo SITE_URL; ?>/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo SITE_URL; ?>/assets/images/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo SITE_URL; ?>/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/site.webmanifest">
    <meta name="theme-color" content="#0A1929">
    <meta name="msapplication-TileColor" content="#0A1929">
    
    <!-- Fonts - Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 (Latest) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- AOS Animation Library (Deferred) -->
    <link rel="preload" href="https://unpkg.com/aos@2.3.1/dist/aos.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css"></noscript>
    
    <!-- Main Stylesheet -->
    <?php 
    $main_css_file = $_SERVER['DOCUMENT_ROOT'] . '/assets/css/style.css';
    $main_css_version = file_exists($main_css_file) ? filemtime($main_css_file) : '1.0';
    ?>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo $main_css_version; ?>">
    
    <!-- Page Specific CSS -->
    <?php if (isset($page_css)): ?>
        <?php $css_files = is_array($page_css) ? $page_css : [$page_css]; ?>
        <?php foreach ($css_files as $css_file): ?>
            <?php 
            $clean_css = preg_replace('/[^a-zA-Z0-9\-\.]/', '', $css_file);
            $css_path = $_SERVER['DOCUMENT_ROOT'] . '/assets/css/' . $clean_css;
            $css_version = file_exists($css_path) ? filemtime($css_path) : '1.0';
            ?>
            <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/<?php echo $clean_css; ?>?v=<?php echo $css_version; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Custom CSS from Settings -->
    <?php $custom_css = getSetting('custom_css', ''); ?>
    <?php if (!empty($custom_css)): ?>
        <style id="custom-css">
            <?php echo $custom_css; ?>
        </style>
    <?php endif; ?>
    
    <!-- Google Analytics / Tracking Code -->
    <?php $google_analytics = getSetting('google_analytics', ''); ?>
    <?php if (!empty($google_analytics) && !isset($_SESSION['admin_logged_in'])): ?>
        <!-- Google tag (gtag.js) - Deferred -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($google_analytics); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo htmlspecialchars($google_analytics); ?>', { 'anonymize_ip': true });
        </script>
    <?php endif; ?>
    
    <!-- Schema.org Structured Data - Organization -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "<?php echo SITE_NAME; ?>",
        "url": "<?php echo SITE_URL; ?>",
        "logo": "<?php echo SITE_URL; ?>/assets/images/logo.png",
        "sameAs": [
            "<?php echo getSetting('facebook_url', '#'); ?>",
            "<?php echo getSetting('twitter_url', '#'); ?>",
            "<?php echo getSetting('linkedin_url', '#'); ?>",
            "<?php echo getSetting('instagram_url', '#'); ?>"
        ],
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "<?php echo getSetting('contact_phone', '+234 XXX XXX XXXX'); ?>",
            "contactType": "customer service",
            "email": "<?php echo getSetting('contact_email', 'info@afroglobeprime.net'); ?>",
            "availableLanguage": "English"
        },
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "<?php echo getSetting('address', 'Lagos'); ?>",
            "addressLocality": "Lagos",
            "addressCountry": "NG"
        }
    }
    </script>
    
    <!-- Page Specific Schema.org -->
    <?php if (isset($page_schema) && !empty($page_schema)): ?>
        <script type="application/ld+json">
            <?php echo json_encode($page_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
        </script>
    <?php endif; ?>
    
    <!-- Preload Critical Images -->
    <?php if (isset($preload_image)): ?>
        <link rel="preload" as="image" href="<?php echo SITE_URL . $preload_image; ?>" fetchpriority="high">
    <?php else: ?>
        <link rel="preload" as="image" href="<?php echo SITE_URL; ?>/assets/images/logo.png" fetchpriority="high">
    <?php endif; ?>
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    
    <!-- Alternate Language Versions (if multi-language) -->
    <?php if (isset($alternate_urls) && is_array($alternate_urls)): ?>
        <?php foreach ($alternate_urls as $lang => $url): ?>
            <link rel="alternate" hreflang="<?php echo htmlspecialchars($lang); ?>" href="<?php echo htmlspecialchars($url); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- RSS Feed -->
    <link rel="alternate" type="application/rss+xml" title="<?php echo SITE_NAME; ?> - Blog Feed" href="<?php echo SITE_URL; ?>/rss.xml">
    
    <!-- Security Headers (for browsers) -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
    <meta name="referrer" content="strict-origin-when-cross-origin">
</head>
<body <?php echo isset($body_class) ? 'class="' . htmlspecialchars($body_class) . '"' : ''; ?>>
    
    <!-- Loading Spinner (Optional) -->
    <?php if (isset($show_loader) && $show_loader === true): ?>
    <div id="preloader">
        <div class="loader">
            <div class="loader-circle"></div>
            <div class="loader-logo">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo-icon.png" alt="<?php echo SITE_NAME; ?>" loading="lazy" style="border-radius:50%;">
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Announcement Bar / Top Notification -->
    <?php $announcement = getSetting('announcement_text', ''); ?>
    <?php $announcement_link = getSetting('announcement_link', ''); ?>
    <?php if (!empty($announcement) && !isset($_COOKIE['announcement_closed'])): ?>
    <div class="announcement-bar" id="announcementBar">
        <div class="container">
            <div class="announcement-content">
                <i class="fas fa-bullhorn"></i>
                <?php if (!empty($announcement_link)): ?>
                    <a href="<?php echo htmlspecialchars($announcement_link); ?>" class="announcement-link">
                        <span><?php echo htmlspecialchars($announcement); ?></span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <span><?php echo htmlspecialchars($announcement); ?></span>
                <?php endif; ?>
            </div>
            <button class="announcement-close" onclick="closeAnnouncement()" aria-label="Close announcement">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <header class="site-header <?php echo isset($header_class) ? htmlspecialchars($header_class) : ''; ?>" id="siteHeader">
        <div class="container">
            <div class="header-wrapper">
                <!-- Logo -->
                <div class="logo">
                    <a href="<?php echo SITE_URL; ?>" aria-label="<?php echo SITE_NAME; ?> - Home" class="logo-link">
                        <?php 
                        $site_logo = getSetting('site_logo', '');
                        if (!empty($site_logo) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $site_logo)): 
                        ?>
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($site_logo); ?>" 
                                 alt="<?php echo SITE_NAME; ?>" 
                                 width="180" 
                                 height="50"
                                 class="logo-image"
                                 fetchpriority="high"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <span class="logo-text" style="display:none;">AGPN</span>
                        <?php else: ?>
                            <span class="logo-text">AGPN</span>
                            <span class="logo-tagline">Prime Network</span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <nav class="main-nav" aria-label="Main Navigation">
                    <!-- Hidden checkbox for pure CSS toggle -->
                    <input type="checkbox" id="nav-toggle-checkbox" class="nav-toggle-checkbox" hidden aria-hidden="true">

                    <!-- Mobile Menu Toggle (now a label) -->
                    <label for="nav-toggle-checkbox" class="nav-toggle" id="navToggle" aria-label="Toggle navigation menu" role="button">
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                    </label>

                    <ul class="nav-menu" id="navMenu">
                        <?php
                        $menu_items = getNavigationMenu('primary');
                        if (empty($menu_items)) {
                            // Default menu items if none configured
                            $menu_items = [
                                ['url' => '/', 'title' => 'Home', 'target' => '_self', 'children' => []],
                                ['url' => '/about.php', 'title' => 'About', 'target' => '_self', 'children' => []],
                                ['url' => '/arms.php', 'title' => 'Our Arms', 'target' => '_self', 'children' => []],
                                ['url' => '/blog.php', 'title' => 'Blog', 'target' => '_self', 'children' => []],
                                ['url' => '/contact.php', 'title' => 'Contact', 'target' => '_self', 'children' => []]
                            ];
                        }
                        
                        foreach ($menu_items as $item):
                            $has_children = !empty($item['children']);
                            $current_page = basename($_SERVER['PHP_SELF']);
                            $is_active = ($item['url'] == '/' && $current_page == 'index.php') || 
                                        (!empty($item['url']) && $item['url'] != '/' && strpos($_SERVER['REQUEST_URI'], $item['url']) !== false);
                        ?>
                            <li class="nav-item <?php echo $has_children ? 'has-dropdown' : ''; ?> <?php echo $is_active ? 'active' : ''; ?>">
                                <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                                   target="<?php echo htmlspecialchars($item['target'] ?? '_self'); ?>" 
                                   class="nav-link <?php echo $is_active ? 'active' : ''; ?>"
                                   <?php echo $has_children ? 'aria-haspopup="true" aria-expanded="false"' : ''; ?>>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                    <?php if ($has_children): ?>
                                        <i class="fas fa-chevron-down dropdown-icon" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </a>
                                
                                <?php if ($has_children): ?>
                                    <ul class="dropdown-menu">
                                        <?php foreach ($item['children'] as $child): ?>
                                            <li>
                                                <a href="<?php echo htmlspecialchars($child['url']); ?>" 
                                                   target="<?php echo htmlspecialchars($child['target'] ?? '_self'); ?>"
                                                   class="dropdown-item">
                                                    <?php echo htmlspecialchars($child['title']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        
                        <!-- CTA Button in Navigation -->
                        <?php $cta_text = getSetting('nav_cta_text', 'Contact Us'); ?>
                        <?php $cta_url = getSetting('nav_cta_url', '/contact.php'); ?>
                        <li class="nav-item nav-cta">
                            <a href="<?php echo htmlspecialchars($cta_url); ?>" class="btn btn-primary btn-sm">
                                <?php echo htmlspecialchars($cta_text); ?>
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </li>
                    </ul>

                </nav>
            </div>
        </div>
    </header>

    <!-- Breadcrumbs (Optional) -->
    <?php if (isset($show_breadcrumbs) && $show_breadcrumbs === true && !is_front_page()): ?>
    <div class="breadcrumbs">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
                    <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                        <a href="<?php echo SITE_URL; ?>" itemprop="item" class="breadcrumb-link">
                            <span itemprop="name">Home</span>
                        </a>
                        <meta itemprop="position" content="1" />
                    </li>
                    
                    <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                        <?php foreach ($breadcrumbs as $position => $crumb): ?>
                            <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                <i class="fas fa-chevron-right separator" aria-hidden="true"></i>
                                <?php if (isset($crumb['url'])): ?>
                                    <a href="<?php echo htmlspecialchars($crumb['url']); ?>" itemprop="item" class="breadcrumb-link">
                                        <span itemprop="name"><?php echo htmlspecialchars($crumb['title']); ?></span>
                                    </a>
                                <?php else: ?>
                                    <span itemprop="name" class="breadcrumb-current"><?php echo htmlspecialchars($crumb['title']); ?></span>
                                <?php endif; ?>
                                <meta itemprop="position" content="<?php echo $position + 2; ?>" />
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page">
                            <i class="fas fa-chevron-right separator" aria-hidden="true"></i>
                            <span class="breadcrumb-current"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Page'; ?></span>
                        </li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
    </div>
    <?php endif; ?>

    <main id="main-content" class="main-content-wrapper">