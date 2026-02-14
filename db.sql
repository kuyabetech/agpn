-- phpMyAdmin SQL Dump
-- Database: `agpn_db`
-- Generation Time: <?php echo date('Y-m-d'); ?>

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Database: `agpn_db`
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `agpn_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `agpn_db`;

-- --------------------------------------------------------
-- Table structure for table `admin_users`
-- --------------------------------------------------------
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','editor','viewer') DEFAULT 'editor',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `admin_users`
-- --------------------------------------------------------
INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `last_login`, `created_at`) VALUES
(1, 'agpn_admin', 'admin@afroglobeprime.net', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'AGPN Administrator', 'superadmin', NULL, CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `application_submissions`
-- --------------------------------------------------------
CREATE TABLE `application_submissions` (
  `id` int(11) NOT NULL,
  `application_type` enum('digital-skills','study-abroad','sponsorship','business-growth') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('pending','reviewed','contacted','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `arms`
-- --------------------------------------------------------
CREATE TABLE `arms` (
  `id` int(11) NOT NULL,
  `arm_name` varchar(100) NOT NULL,
  `arm_slug` varchar(100) NOT NULL,
  `short_description` text DEFAULT NULL,
  `full_description` longtext DEFAULT NULL,
  `icon_class` varchar(50) DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `arms`
-- --------------------------------------------------------
INSERT INTO `arms` (`id`, `arm_name`, `arm_slug`, `short_description`, `full_description`, `icon_class`, `featured_image`, `display_order`, `status`, `created_at`) VALUES
(1, 'Digital Skills Training', 'digital-skills', 'Equipping individuals with in-demand tech skills for the digital economy.', NULL, 'fa-laptop-code', NULL, 1, 'active', CURRENT_TIMESTAMP),
(2, 'Business Growth Services', 'business-growth', 'Strategic consulting and support for SMEs and enterprises.', NULL, 'fa-chart-line', NULL, 2, 'active', CURRENT_TIMESTAMP),
(3, 'Study Abroad Programs', 'study-abroad', 'Comprehensive support for international education opportunities.', NULL, 'fa-graduation-cap', NULL, 3, 'active', CURRENT_TIMESTAMP),
(4, 'Sponsorship & Partnerships', 'sponsorship', 'Connecting sponsors with talent and impactful opportunities.', NULL, 'fa-handshake', NULL, 4, 'active', CURRENT_TIMESTAMP),
(5, 'Career Development', 'career-dev', 'Empowering professionals for career success and advancement.', NULL, 'fa-briefcase', NULL, 5, 'active', CURRENT_TIMESTAMP),
(6, 'Innovation Hub', 'innovation-hub', 'Fostering innovation and entrepreneurship through incubation.', NULL, 'fa-lightbulb', NULL, 6, 'active', CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `blog_posts`
-- --------------------------------------------------------
CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `status` enum('draft','published') DEFAULT 'draft',
  `published_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `certificates`
-- --------------------------------------------------------
CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `certificate_title` varchar(200) NOT NULL,
  `certificate_image` varchar(255) NOT NULL,
  `issuing_body` varchar(100) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `contact_submissions`
-- --------------------------------------------------------
CREATE TABLE `contact_submissions` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `galleries`
-- --------------------------------------------------------
CREATE TABLE `galleries` (
  `id` int(11) NOT NULL,
  `gallery_name` varchar(100) NOT NULL,
  `gallery_slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `gallery_images`
-- --------------------------------------------------------
CREATE TABLE `gallery_images` (
  `id` int(11) NOT NULL,
  `gallery_id` int(11) NOT NULL,
  `image_title` varchar(200) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `menus`
-- --------------------------------------------------------
CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `menu_name` varchar(50) NOT NULL,
  `menu_location` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `menus`
-- --------------------------------------------------------
INSERT INTO `menus` (`id`, `menu_name`, `menu_location`, `created_at`) VALUES
(1, 'Main Menu', 'primary', CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `menu_items`
-- --------------------------------------------------------
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT 0,
  `title` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `target` enum('_self','_blank') DEFAULT '_self',
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `menu_items`
-- --------------------------------------------------------
INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `title`, `url`, `target`, `display_order`) VALUES
(1, 1, 0, 'Home', '/', '_self', 1),
(2, 1, 0, 'About Us', '/about.php', '_self', 2),
(3, 1, 0, 'The 6 Arms', '/arms.php', '_self', 3),
(4, 1, 0, 'Digital Skills', '/digital-skills.php', '_self', 4),
(5, 1, 0, 'Business Growth', '/business-growth.php', '_self', 5),
(6, 1, 0, 'Study Abroad', '/study-abroad.php', '_self', 6),
(7, 1, 0, 'Sponsor AGPN', '/sponsor.php', '_self', 7),
(8, 1, 0, 'Certificates', '/certificates.php', '_self', 8),
(9, 1, 0, 'Blog', '/blog.php', '_self', 9),
(10, 1, 0, 'Contact', '/contact.php', '_self', 10);

-- --------------------------------------------------------
-- Table structure for table `pages`
-- --------------------------------------------------------
CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `page_name` varchar(100) NOT NULL,
  `page_title` varchar(200) NOT NULL,
  `page_content` longtext DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'published',
  `last_edited_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `pages`
-- --------------------------------------------------------
INSERT INTO `pages` (`id`, `page_name`, `page_title`, `page_content`, `meta_description`, `meta_keywords`, `featured_image`, `status`, `last_edited_by`, `created_at`, `updated_at`) VALUES
(1, 'home', 'Home', '<h2>Welcome to Afroglobe Prime Network Limited</h2><p>AGPN is a multi-dimensional organization committed to driving economic growth through digital skills, business development, and global opportunities.</p>', 'Afroglobe Prime Network Limited (AGPN) - Empowering global excellence through digital skills, business growth, and international opportunities.', 'AGPN, Afroglobe, digital skills, business growth, study abroad, Nigeria', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 'about', 'About Us', '<h2>Who We Are</h2><p>Afroglobe Prime Network Limited (AGPN) is a dynamic and multi-dimensional organization committed to driving economic growth, digital transformation, and global talent development.</p>', 'Learn about AGPN - our mission, vision, values, and commitment to excellence.', 'about AGPN, mission, vision, values', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(3, 'arms', 'The 6 Arms of AGPN', '<p>AGPN operates through six specialized service arms, each designed to address specific needs in the digital economy and global talent development ecosystem.</p>', 'Explore the six core service arms of Afroglobe Prime Network Limited.', '6 arms, services, AGPN programs', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(4, 'contact', 'Contact Us', '<h2>Get in Touch</h2><p>Have questions about our programs, partnerships, or services? Our team is ready to assist you.</p>', 'Contact Afroglobe Prime Network Limited. Get in touch with our team.', 'contact AGPN, email, phone, address', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(5, 'sponsor', 'Why Sponsor AGPN', '<p>Partner with AGPN to empower the next generation of African talent and drive meaningful impact.</p>', 'Partner with AGPN - strategic sponsorship opportunities for forward-thinking organizations.', 'sponsor AGPN, partnership, CSR, corporate sponsorship', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(6, 'certificates', 'Our Certificates', '<p>AGPN holds various accreditations and certifications that validate our commitment to quality, excellence, and industry standards.</p>', 'View AGPN\'s accreditations, certifications, and industry recognitions.', 'AGPN certificates, accreditations, ISO, certifications', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(7, 'digital-skills', 'Digital Skills Training', '<h2>Transform Your Career with Digital Skills</h2><p>In today\'s rapidly evolving digital economy, technical skills are no longer optional—they\'re essential. AGPN\'s Digital Skills Training program equips you with practical, industry-relevant competencies that employers demand.</p>', 'Comprehensive digital skills training programs in web development, data science, digital marketing, and UI/UX design.', 'digital skills, tech training, programming, data science, Nigeria', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(8, 'business-growth', 'Business Growth Services', '<h2>Accelerate Your Business Growth</h2><p>AGPN\'s Business Growth Services provide strategic guidance and practical support to help SMEs and enterprises scale sustainably, enter new markets, and optimize operations.</p>', 'Strategic business consulting and growth services for SMEs and enterprises in Nigeria and Africa.', 'business growth, SME consulting, business strategy, Nigeria', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(9, 'study-abroad', 'Study Abroad Programs', '<h2>Your Gateway to Global Education</h2><p>AGPN\'s Study Abroad Programs open doors to prestigious universities and colleges worldwide. We provide end-to-end support for students seeking international education opportunities.</p>', 'Study abroad programs and international education opportunities with AGPN.', 'study abroad, international students, overseas education, Nigeria', NULL, 'published', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `site_settings`
-- --------------------------------------------------------
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','textarea','image','email','number') DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `site_settings`
-- --------------------------------------------------------
INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`) VALUES
(1, 'site_title', 'Afroglobe Prime Network Limited', 'text', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 'site_tagline', 'Empowering Global Excellence', 'text', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(3, 'contact_email', 'info@afroglobeprime.net', 'email', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(4, 'contact_phone', '+234 800 123 4567', 'text', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(5, 'address', 'Lagos, Nigeria', 'textarea', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(6, 'facebook_url', '#', 'text', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(7, 'twitter_url', '#', 'text', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(8, 'linkedin_url', '#', 'text', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(9, 'instagram_url', '#', 'text', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(10, 'footer_copyright', '© 2025 Afroglobe Prime Network Limited. All Rights Reserved.', 'textarea', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- --------------------------------------------------------
-- Table structure for table `testimonials`
-- --------------------------------------------------------
CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_position` varchar(100) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `testimonial_text` text NOT NULL,
  `client_photo` varchar(255) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Indexes for dumped tables
-- --------------------------------------------------------
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `application_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_type` (`application_type`,`status`);

ALTER TABLE `arms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `arm_slug` (`arm_slug`);

ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `status` (`status`,`published_date`);

ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `contact_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_read` (`is_read`,`submitted_at`);

ALTER TABLE `galleries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gallery_slug` (`gallery_slug`);

ALTER TABLE `gallery_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gallery_id` (`gallery_id`);

ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `menu_location` (`menu_location`);

ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_id` (`menu_id`),
  ADD KEY `parent_id` (`parent_id`);

ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_name` (`page_name`);

ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

-- --------------------------------------------------------
-- AUTO_INCREMENT for dumped tables
-- --------------------------------------------------------
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `application_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `arms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `contact_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `galleries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `gallery_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- Foreign key constraints
-- --------------------------------------------------------
ALTER TABLE `gallery_images`
  ADD CONSTRAINT `gallery_images_ibfk_1` FOREIGN KEY (`gallery_id`) REFERENCES `galleries` (`id`) ON DELETE CASCADE;

ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_items_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;