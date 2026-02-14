<?php
// ============================================
// SINGLE BLOG POST - CLEAN & RESPONSIVE
// Features: Hero section, content, author bio,
//           related posts, share buttons, reading time
// ============================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get post slug from URL
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (empty($slug)) {
    redirect('blog.php');
}

// Get post data
$post = getBlogPost($slug);

if (!$post) {
    // Post not found, show 404 or redirect
    $_SESSION['error'] = 'Blog post not found';
    redirect('blog.php');
}

// Increment view count
incrementPostViews($post['id']);

// Get author details
$author_name = $post['author'] ?: 'AGPN Editorial';
$author_bio = getSetting('author_bio_' . $author_name, 'Afroglobe Prime Network Limited is committed to empowering global excellence through digital skills, business development, and international opportunities.');

// Get related posts (by same author)
$related_posts = getRelatedPosts($post['id'], 3);

// Get reading time
$word_count = str_word_count(strip_tags($post['content']));
$reading_time = ceil($word_count / 200); // Average reading speed: 200 words per minute

// Meta information
$page_title = $post['title'];
$meta_description = $post['excerpt'] ?: truncateText(strip_tags($post['content']), 160);
$og_image = $post['featured_image'] ? SITE_URL . '/' . $post['featured_image'] : SITE_URL . '/assets/images/og-default.jpg';
$og_url = SITE_URL . '/single.php?slug=' . $post['slug'];

// Schema.org markup for blog post
$page_schema = [
    "@context" => "https://schema.org",
    "@type" => "BlogPosting",
    "headline" => $post['title'],
    "description" => $meta_description,
    "image" => $og_image,
    "author" => [
        "@type" => "Person",
        "name" => $author_name
    ],
    "publisher" => [
        "@type" => "Organization",
        "name" => SITE_NAME,
        "logo" => [
            "@type" => "ImageObject",
            "url" => SITE_URL . "/assets/images/logo.png"
        ]
    ],
    "datePublished" => $post['published_date'],
    "dateModified" => $post['updated_at'],
    "mainEntityOfPage" => $og_url,
    "wordCount" => $word_count,
    "timeRequired" => "PT{$reading_time}M"
];

include 'includes/header.php';
?>

<style>
/* ============================================
   SINGLE BLOG POST - CLEAN & RESPONSIVE
   No categories - just clean content
============================================ */

:root {
    --navy: #0A1929;
    --navy-light: #1A2A3A;
    --gold: #FFB81C;
    --gold-light: #FFD700;
    --white: #ffffff;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-600: #6c757d;
    --gray-800: #343a40;
    --gray-900: #212529;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    line-height: 1.6;
    color: var(--gray-800);
}

.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* ============================================
   HERO SECTION
============================================ */
.post-hero {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 0;
    position: relative;
    overflow: hidden;
}

@media (min-width: 768px) {
    .post-hero {
        padding: 80px 0;
    }
}

@media (min-width: 992px) {
    .post-hero {
        padding: 100px 0;
    }
}

.post-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,184,28,0.1) 0%, transparent 70%);
    border-radius: 50%;
    animation: pulse 8s infinite;
}

@media (min-width: 768px) {
    .post-hero::before {
        width: 400px;
        height: 400px;
    }
}

@keyframes pulse {
    0%, 100% { opacity: 0.1; transform: scale(1); }
    50% { opacity: 0.2; transform: scale(1.1); }
}

.post-hero-content {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
    color: var(--white);
    text-align: center;
}

.post-breadcrumbs {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .post-breadcrumbs {
        font-size: 14px;
        gap: 10px;
    }
}

.post-breadcrumbs a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: color 0.3s ease;
}

.post-breadcrumbs a:hover {
    color: var(--gold);
}

.post-breadcrumbs .separator {
    color: rgba(255,255,255,0.4);
}

.post-breadcrumbs .current {
    color: rgba(255,255,255,0.6);
}

.post-title {
    color: var(--white);
    font-size: 28px;
    margin-bottom: 15px;
    line-height: 1.3;
    animation: fadeInUp 1s ease;
}

@media (min-width: 576px) {
    .post-title {
        font-size: 32px;
    }
}

@media (min-width: 768px) {
    .post-title {
        font-size: 38px;
        margin-bottom: 20px;
    }
}

@media (min-width: 992px) {
    .post-title {
        font-size: 44px;
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.post-excerpt {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    line-height: 1.7;
    max-width: 700px;
    margin: 0 auto 20px;
    animation: fadeInUp 1s ease 0.1s both;
}

@media (min-width: 768px) {
    .post-excerpt {
        font-size: 18px;
        margin-bottom: 30px;
    }
}

.post-meta {
    display: flex;
    flex-direction: column;
    gap: 15px;
    justify-content: center;
    align-items: center;
    animation: fadeInUp 1s ease 0.2s both;
}

@media (min-width: 576px) {
    .post-meta {
        flex-direction: row;
        gap: 20px;
    }
}

@media (min-width: 768px) {
    .post-meta {
        gap: 30px;
    }
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.meta-icon {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 14px;
}

@media (min-width: 768px) {
    .meta-icon {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
}

.meta-text {
    text-align: left;
}

.meta-label {
    display: block;
    font-size: 11px;
    color: rgba(255,255,255,0.6);
    margin-bottom: 2px;
}

@media (min-width: 768px) {
    .meta-label {
        font-size: 12px;
    }
}

.meta-value {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--white);
}

@media (min-width: 768px) {
    .meta-value {
        font-size: 15px;
    }
}

/* ============================================
   MAIN CONTENT LAYOUT
============================================ */
.post-main-section {
    padding: 40px 0;
    background: var(--white);
}

@media (min-width: 768px) {
    .post-main-section {
        padding: 60px 0;
    }
}

.post-layout {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

@media (min-width: 992px) {
    .post-layout {
        flex-direction: row;
        gap: 30px;
    }
}

/* ============================================
   SIDEBAR
============================================ */
.post-sidebar {
    width: 100%;
    order: 2;
}

@media (min-width: 992px) {
    .post-sidebar {
        width: 280px;
        order: 1;
    }
}

.sidebar-sticky {
    position: sticky;
    top: 100px;
}

.sidebar-widget {
    background: var(--white);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid var(--gray-200);
    box-shadow: 0 5px 15px rgba(0,0,0,0.03);
}

@media (min-width: 768px) {
    .sidebar-widget {
        padding: 30px;
    }
}

.widget-title {
    font-size: 18px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gold);
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .widget-title {
        font-size: 20px;
    }
}

/* Table of Contents */
.toc-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.toc-item {
    margin-bottom: 10px;
    border-bottom: 1px dashed var(--gray-200);
    padding-bottom: 8px;
}

.toc-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.toc-link {
    color: var(--gray-600);
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
    display: block;
    line-height: 1.5;
}

.toc-link:hover {
    color: var(--gold);
}

.toc-empty {
    color: var(--gray-500);
    font-style: italic;
    font-size: 14px;
}

/* Share Buttons */
.share-buttons {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

@media (min-width: 576px) {
    .share-buttons {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (min-width: 992px) {
    .share-buttons {
        grid-template-columns: 1fr;
    }
}

.share-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px;
    border-radius: 8px;
    color: var(--white);
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.share-btn i {
    font-size: 14px;
}

.share-btn span {
    display: none;
}

@media (min-width: 992px) {
    .share-btn {
        justify-content: flex-start;
        padding: 12px 15px;
    }
    
    .share-btn span {
        display: inline;
    }
}

.share-btn.facebook { background: #1877F2; }
.share-btn.twitter { background: #1DA1F2; }
.share-btn.linkedin { background: #0077B5; }
.share-btn.whatsapp { background: #25D366; }
.share-btn.copy-link { background: var(--gray-600); }

.share-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* Author Mini Card */
.author-mini-card {
    text-align: center;
}

.author-mini-avatar {
    margin-bottom: 15px;
}

.author-mini-avatar img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid var(--gold-light);
}

@media (min-width: 768px) {
    .author-mini-avatar img {
        width: 100px;
        height: 100px;
    }
}

.author-mini-card h5 {
    font-size: 18px;
    margin-bottom: 10px;
    color: var(--navy);
}

.author-mini-card p {
    font-size: 14px;
    color: var(--gray-600);
    margin-bottom: 15px;
    line-height: 1.6;
}

/* Related Posts List */
.related-posts-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.related-post-item {
    display: flex;
    gap: 12px;
    text-decoration: none;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--gray-200);
}

.related-post-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.related-post-image {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.related-post-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.related-post-placeholder {
    width: 100%;
    height: 100%;
    background: var(--navy);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 20px;
}

.related-post-content {
    flex: 1;
}

.related-post-content h6 {
    font-size: 14px;
    color: var(--navy);
    margin-bottom: 5px;
    line-height: 1.4;
    font-weight: 600;
}

.related-post-date {
    font-size: 11px;
    color: var(--gray-600);
    display: flex;
    align-items: center;
    gap: 4px;
}

.related-post-date i {
    color: var(--gold);
}

/* Newsletter Widget */
.newsletter-widget p {
    font-size: 14px;
    color: var(--gray-600);
    margin-bottom: 20px;
    line-height: 1.6;
}

.sidebar-newsletter-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sidebar-newsletter-form .form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.sidebar-newsletter-form .form-control:focus {
    outline: none;
    border-color: var(--gold);
}

.sidebar-newsletter-form .btn {
    width: 100%;
    padding: 12px;
    background: var(--gold);
    color: var(--navy);
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.sidebar-newsletter-form .btn:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
}

.sidebar-newsletter-form .form-text {
    font-size: 11px;
    color: var(--gray-500);
    text-align: center;
}

/* ============================================
   MAIN CONTENT
============================================ */
.post-main {
    width: 100%;
    order: 1;
}

@media (min-width: 992px) {
    .post-main {
        width: calc(100% - 310px);
        order: 2;
    }
}

.post-featured-image {
    margin-bottom: 30px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.post-featured-image img {
    width: 100%;
    height: auto;
    display: block;
}

.post-body {
    font-size: 16px;
    line-height: 1.8;
    color: var(--gray-800);
}

@media (min-width: 768px) {
    .post-body {
        font-size: 18px;
    }
}

.post-body h2 {
    font-size: 24px;
    margin: 40px 0 20px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .post-body h2 {
        font-size: 28px;
        margin: 50px 0 25px;
    }
}

.post-body h3 {
    font-size: 20px;
    margin: 35px 0 15px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .post-body h3 {
        font-size: 22px;
        margin: 40px 0 20px;
    }
}

.post-body h4 {
    font-size: 18px;
    margin: 30px 0 15px;
    color: var(--navy);
}

.post-body p {
    margin-bottom: 20px;
    line-height: 1.8;
}

.post-body img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 25px 0;
}

.post-body ul,
.post-body ol {
    margin-bottom: 20px;
    padding-left: 25px;
}

.post-body li {
    margin-bottom: 8px;
}

.post-body blockquote {
    background: var(--gray-100);
    padding: 25px;
    border-left: 4px solid var(--gold);
    border-radius: 8px;
    font-style: italic;
    margin: 30px 0;
    color: var(--gray-700);
}

@media (min-width: 768px) {
    .post-body blockquote {
        padding: 30px;
        margin: 40px 0;
    }
}

.post-body pre {
    background: var(--gray-900);
    color: var(--white);
    padding: 20px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 25px 0;
}

.post-body code {
    font-family: monospace;
    font-size: 14px;
}

/* Author Bio */
.author-bio-box {
    display: flex;
    flex-direction: column;
    gap: 20px;
    background: var(--gray-100);
    border-radius: 16px;
    padding: 25px;
    margin-top: 40px;
    border: 1px solid var(--gray-200);
}

@media (min-width: 576px) {
    .author-bio-box {
        flex-direction: row;
        align-items: center;
        gap: 30px;
        padding: 30px;
    }
}

@media (min-width: 768px) {
    .author-bio-box {
        margin-top: 50px;
    }
}

.author-avatar {
    flex-shrink: 0;
    text-align: center;
}

.author-avatar img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 4px solid var(--gold-light);
}

@media (min-width: 768px) {
    .author-avatar img {
        width: 120px;
        height: 120px;
    }
}

.author-info {
    flex: 1;
}

.author-info h4 {
    font-size: 20px;
    margin-bottom: 10px;
    color: var(--navy);
    text-align: center;
}

@media (min-width: 576px) {
    .author-info h4 {
        text-align: left;
    }
}

@media (min-width: 768px) {
    .author-info h4 {
        font-size: 22px;
        margin-bottom: 15px;
    }
}

.author-info p {
    font-size: 14px;
    color: var(--gray-600);
    line-height: 1.7;
    margin-bottom: 15px;
    text-align: center;
}

@media (min-width: 576px) {
    .author-info p {
        text-align: left;
    }
}

@media (min-width: 768px) {
    .author-info p {
        font-size: 15px;
    }
}

.author-social {
    display: flex;
    gap: 10px;
    justify-content: center;
}

@media (min-width: 576px) {
    .author-social {
        justify-content: flex-start;
    }
}

.author-social-link {
    width: 36px;
    height: 36px;
    background: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-700);
    transition: all 0.3s ease;
    text-decoration: none;
}

.author-social-link:hover {
    background: var(--gold);
    color: var(--navy);
}

/* Post Navigation */
.post-navigation {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid var(--gray-200);
}

@media (min-width: 576px) {
    .post-navigation {
        flex-direction: row;
        justify-content: space-between;
        gap: 20px;
    }
}

@media (min-width: 768px) {
    .post-navigation {
        margin-top: 50px;
        padding-top: 40px;
    }
}

.nav-prev,
.nav-next {
    flex: 1;
}

.nav-link {
    display: flex;
    flex-direction: column;
    text-decoration: none;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 12px;
    transition: all 0.3s ease;
    height: 100%;
}

.nav-link:hover {
    background: var(--gold);
    transform: translateY(-2px);
}

.nav-link i {
    color: var(--gold);
    margin-bottom: 8px;
    transition: color 0.3s ease;
}

.nav-link:hover i {
    color: var(--navy);
}

.nav-label {
    font-size: 12px;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.nav-link:hover .nav-label {
    color: var(--navy);
}

.nav-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--navy);
    transition: color 0.3s ease;
    line-height: 1.5;
}

@media (min-width: 768px) {
    .nav-title {
        font-size: 15px;
    }
}

.nav-link:hover .nav-title {
    color: var(--navy);
}

.nav-next .nav-link {
    align-items: flex-end;
    text-align: right;
}

/* ============================================
   RELATED POSTS SECTION
============================================ */
.related-posts-section {
    padding: 50px 0;
    background: var(--gray-100);
}

@media (min-width: 768px) {
    .related-posts-section {
        padding: 70px 0;
    }
}

.section-title {
    text-align: center;
    max-width: 700px;
    margin: 0 auto 40px;
}

@media (min-width: 768px) {
    .section-title {
        margin-bottom: 50px;
    }
}

.section-subtitle {
    display: block;
    color: var(--gold);
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 10px;
}

.section-title h2 {
    font-size: 28px;
    margin-bottom: 15px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .section-title h2 {
        font-size: 32px;
    }
}

.section-title h2 .text-gold {
    color: var(--gold);
}

.section-title p {
    color: var(--gray-600);
    font-size: 15px;
    line-height: 1.6;
}

.related-posts-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

@media (min-width: 576px) {
    .related-posts-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}

@media (min-width: 992px) {
    .related-posts-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 25px;
    }
}

.related-card {
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.03);
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.related-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: var(--gold);
}

.related-card-image {
    height: 180px;
    overflow: hidden;
}

@media (min-width: 576px) {
    .related-card-image {
        height: 200px;
    }
}

.related-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.related-card:hover .related-card-image img {
    transform: scale(1.1);
}

.related-card-content {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

@media (min-width: 768px) {
    .related-card-content {
        padding: 25px;
    }
}

.related-card-meta {
    font-size: 12px;
    color: var(--gray-600);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.related-card-meta i {
    color: var(--gold);
}

.related-card h3 {
    font-size: 16px;
    margin-bottom: 12px;
    line-height: 1.5;
}

@media (min-width: 768px) {
    .related-card h3 {
        font-size: 18px;
    }
}

.related-card h3 a {
    color: var(--navy);
    text-decoration: none;
}

.related-card h3 a:hover {
    color: var(--gold);
}

.related-card p {
    color: var(--gray-600);
    font-size: 13px;
    line-height: 1.7;
    margin-bottom: 15px;
    flex: 1;
}

@media (min-width: 768px) {
    .related-card p {
        font-size: 14px;
    }
}

.related-card .btn-link {
    color: var(--gold);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.3s ease;
}

.related-card .btn-link:hover {
    gap: 8px;
}

/* ============================================
   CTA SECTION
============================================ */
.cta-section {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 0;
    position: relative;
    overflow: hidden;
}

@media (min-width: 768px) {
    .cta-section {
        padding: 80px 0;
    }
}

.cta-wrapper {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
    color: var(--white);
    position: relative;
    z-index: 2;
}

.cta-content h2 {
    color: var(--white);
    font-size: 26px;
    margin-bottom: 15px;
}

@media (min-width: 576px) {
    .cta-content h2 {
        font-size: 32px;
    }
}

@media (min-width: 768px) {
    .cta-content h2 {
        font-size: 36px;
        margin-bottom: 20px;
    }
}

.cta-content p {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    line-height: 1.7;
    margin-bottom: 25px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

@media (min-width: 768px) {
    .cta-content p {
        font-size: 18px;
        margin-bottom: 30px;
    }
}

.cta-buttons {
    display: flex;
    flex-direction: column;
    gap: 15px;
    justify-content: center;
    align-items: center;
}

@media (min-width: 576px) {
    .cta-buttons {
        flex-direction: row;
        gap: 20px;
    }
}

.cta-buttons .btn {
    display: inline-block;
    padding: 14px 30px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    min-width: 200px;
}

@media (min-width: 768px) {
    .cta-buttons .btn {
        padding: 16px 35px;
        font-size: 16px;
    }
}

.cta-buttons .btn-primary {
    background: var(--gold);
    color: var(--navy);
}

.cta-buttons .btn-primary:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(255,184,28,0.3);
}

.cta-buttons .btn-outline-light {
    border: 2px solid var(--white);
    color: var(--white);
}

.cta-buttons .btn-outline-light:hover {
    background: var(--white);
    color: var(--navy);
    transform: translateY(-2px);
}

/* ============================================
   TOAST NOTIFICATION
============================================ */
.toast-notification {
    position: fixed;
    top: 30px;
    right: 30px;
    z-index: 9999;
    animation: slideInRight 0.3s ease;
    transition: opacity 0.3s ease;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* ============================================
   READING PROGRESS BAR
============================================ */
#reading-progress-bar {
    position: fixed;
    top: 0;
    left: 0;
    width: 0%;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    z-index: 9999;
    transition: width 0.1s;
}

/* ============================================
   COMMENTS SECTION (optional)
============================================ */
.comments-section {
    padding: 50px 0;
    background: var(--gray-100);
}

@media (min-width: 768px) {
    .comments-section {
        padding: 70px 0;
    }
}

.comments-container {
    max-width: 800px;
    margin: 0 auto;
}

.comments-title {
    font-size: 24px;
    margin-bottom: 30px;
    color: var(--navy);
    display: flex;
    align-items: center;
    gap: 10px;
}

.comment-form-wrapper {
    background: var(--white);
    padding: 25px;
    border-radius: 16px;
    border: 1px solid var(--gray-200);
    margin-bottom: 40px;
}

@media (min-width: 768px) {
    .comment-form-wrapper {
        padding: 30px;
    }
}

.comment-form .form-row {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 15px;
}

@media (min-width: 576px) {
    .comment-form .form-row {
        flex-direction: row;
    }
}

.comment-form .form-group {
    flex: 1;
    margin-bottom: 15px;
}

.comment-form .form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.comment-form .form-control:focus {
    outline: none;
    border-color: var(--gold);
}

.comment-form textarea {
    resize: vertical;
    font-family: inherit;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.comment-item {
    display: flex;
    flex-direction: column;
    gap: 15px;
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--gray-200);
}

@media (min-width: 576px) {
    .comment-item {
        flex-direction: row;
        gap: 20px;
        padding: 25px;
    }
}

.comment-avatar {
    flex-shrink: 0;
    text-align: center;
}

.comment-avatar img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 3px solid var(--gold-light);
}

.comment-content {
    flex: 1;
}

.comment-header {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 10px;
}

@media (min-width: 576px) {
    .comment-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.comment-author {
    font-size: 16px;
    color: var(--navy);
    margin: 0;
}

.comment-date {
    font-size: 12px;
    color: var(--gray-600);
    display: flex;
    align-items: center;
    gap: 5px;
}

.comment-date i {
    color: var(--gold);
}

.comment-text {
    color: var(--gray-700);
    line-height: 1.7;
    font-size: 14px;
    margin-bottom: 10px;
}

.comment-footer {
    display: flex;
    gap: 10px;
}

.comment-reply-btn {
    background: none;
    border: none;
    color: var(--gold);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 0;
}

.comment-reply-btn:hover {
    color: var(--gold-light);
}

.reply-form {
    margin-top: 20px;
    padding: 20px;
    background: var(--gray-100);
    border-radius: 12px;
}

/* ============================================
   UTILITY CLASSES
============================================ */
.text-gold {
    color: var(--gold);
}

.btn-block {
    display: block;
    width: 100%;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}
</style>

<!-- Reading Progress Bar -->
<div id="reading-progress-bar"></div>

<!-- Toast Container -->
<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<!-- ============================================
     HERO SECTION
============================================ -->
<section class="post-hero" <?php echo $post['featured_image'] ? 'style="background-image: linear-gradient(135deg, rgba(10,25,41,0.95), rgba(26,42,58,0.9)), url(' . SITE_URL . '/' . $post['featured_image'] . '); background-size: cover; background-position: center;"' : ''; ?>>
    <div class="container">
        <div class="post-hero-content">
            <!-- Breadcrumbs -->
            <div class="post-breadcrumbs">
                <a href="<?php echo SITE_URL; ?>">Home</a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <a href="<?php echo SITE_URL; ?>/blog.php">Blog</a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span class="current"><?php echo truncateText($post['title'], 50); ?></span>
            </div>

            <!-- Title -->
            <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>

            <!-- Excerpt -->
            <?php if ($post['excerpt']): ?>
                <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
            <?php endif; ?>

            <!-- Meta Information -->
            <div class="post-meta">
                <div class="meta-item">
                    <div class="meta-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="meta-text">
                        <span class="meta-label">Published</span>
                        <span class="meta-value"><?php echo formatDate($post['published_date'], 'F j, Y'); ?></span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="meta-text">
                        <span class="meta-label">Author</span>
                        <span class="meta-value"><?php echo htmlspecialchars($author_name); ?></span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="meta-text">
                        <span class="meta-label">Reading Time</span>
                        <span class="meta-value"><?php echo $reading_time; ?> min read</span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="meta-text">
                        <span class="meta-label">Views</span>
                        <span class="meta-value"><?php echo number_format($post['views']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     MAIN CONTENT SECTION
============================================ -->
<section class="post-main-section">
    <div class="container">
        <div class="post-layout">
            
            <!-- SIDEBAR -->
            <aside class="post-sidebar">
                <div class="sidebar-sticky">
                    
                    <!-- Table of Contents -->
                    <div class="sidebar-widget toc-widget">
                        <h4 class="widget-title">
                            <i class="fas fa-list-ul"></i>
                            Table of Contents
                        </h4>
                        <div class="toc-content" id="tableOfContents"></div>
                    </div>
                    
                    <!-- Share Buttons -->
                    <div class="sidebar-widget share-widget">
                        <h4 class="widget-title">
                            <i class="fas fa-share-alt"></i>
                            Share
                        </h4>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($og_url); ?>" 
                               target="_blank" 
                               class="share-btn facebook"
                               title="Share on Facebook">
                                <i class="fab fa-facebook-f"></i>
                                <span>Facebook</span>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($og_url); ?>&text=<?php echo urlencode($post['title']); ?>" 
                               target="_blank" 
                               class="share-btn twitter"
                               title="Share on Twitter">
                                <i class="fab fa-twitter"></i>
                                <span>Twitter</span>
                            </a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($og_url); ?>&title=<?php echo urlencode($post['title']); ?>" 
                               target="_blank" 
                               class="share-btn linkedin"
                               title="Share on LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                                <span>LinkedIn</span>
                            </a>
                            <a href="https://wa.me/?text=<?php echo urlencode($post['title'] . ' - ' . $og_url); ?>" 
                               target="_blank" 
                               class="share-btn whatsapp"
                               title="Share on WhatsApp">
                                <i class="fab fa-whatsapp"></i>
                                <span>WhatsApp</span>
                            </a>
                            <button class="share-btn copy-link" 
                                    onclick="copyToClipboard('<?php echo $og_url; ?>')"
                                    title="Copy link">
                                <i class="fas fa-link"></i>
                                <span>Copy Link</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Author Mini Card -->
                    <div class="sidebar-widget author-widget">
                        <div class="author-mini-card">
                            <div class="author-mini-avatar">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($author_name); ?>&size=100&background=0A1929&color=FFB81C" 
                                     alt="<?php echo htmlspecialchars($author_name); ?>">
                            </div>
                            <h5><?php echo htmlspecialchars($author_name); ?></h5>
                            <p><?php echo truncateText($author_bio, 80); ?></p>
                        </div>
                    </div>
                    
                    <!-- Newsletter Widget -->
                    <div class="sidebar-widget newsletter-widget">
                        <h4 class="widget-title">
                            <i class="fas fa-envelope"></i>
                            Newsletter
                        </h4>
                        <p>Get the latest posts delivered right to your inbox.</p>
                        <form action="forms/newsletter-handler.php" method="POST" class="sidebar-newsletter-form">
                            <input type="hidden" name="source" value="blog_post">
                            <div class="form-group">
                                <input type="email" name="email" class="form-control" placeholder="Your email address" required>
                                <button type="submit" class="btn">
                                    Subscribe
                                </button>
                            </div>
                            <small class="form-text">We respect your privacy. Unsubscribe anytime.</small>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- MAIN CONTENT -->
            <article class="post-main">
                
                <!-- Featured Image -->
                <?php if ($post['featured_image']): ?>
                    <div class="post-featured-image">
                        <img src="<?php echo SITE_URL . '/' . $post['featured_image']; ?>" 
                             alt="<?php echo htmlspecialchars($post['title']); ?>"
                             class="featured-image">
                    </div>
                <?php endif; ?>

                <!-- Post Content -->
                <div class="post-body">
                    <?php echo $post['content']; ?>
                </div>

                <!-- Author Bio Box -->
                <div class="author-bio-box">
                    <div class="author-avatar">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($author_name); ?>&size=120&background=0A1929&color=FFB81C" 
                             alt="<?php echo htmlspecialchars($author_name); ?>">
                    </div>
                    <div class="author-info">
                        <h4>About <?php echo htmlspecialchars($author_name); ?></h4>
                        <p><?php echo htmlspecialchars($author_bio); ?></p>
                        <div class="author-social">
                            <a href="#" class="author-social-link"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="author-social-link"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="author-social-link"><i class="fas fa-globe"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Post Navigation -->
                <?php
                // Get previous and next posts
                try {
                    $db = db();
                    
                    $prev_post = $db->prepare("
                        SELECT id, title, slug FROM blog_posts 
                        WHERE id < ? AND status = 'published' 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $prev_post->execute([$post['id']]);
                    $prev = $prev_post->fetch(PDO::FETCH_ASSOC);
                    
                    $next_post = $db->prepare("
                        SELECT id, title, slug FROM blog_posts 
                        WHERE id > ? AND status = 'published' 
                        ORDER BY id ASC LIMIT 1
                    ");
                    $next_post->execute([$post['id']]);
                    $next = $next_post->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Error fetching post navigation: " . $e->getMessage());
                    $prev = null;
                    $next = null;
                }
                ?>
                
                <?php if ($prev || $next): ?>
                <div class="post-navigation">
                    <?php if ($prev): ?>
                        <div class="nav-prev">
                            <a href="single.php?slug=<?php echo $prev['slug']; ?>" class="nav-link">
                                <i class="fas fa-arrow-left"></i>
                                <span class="nav-label">Previous Article</span>
                                <span class="nav-title"><?php echo htmlspecialchars(truncateText($prev['title'], 60)); ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($next): ?>
                        <div class="nav-next">
                            <a href="single.php?slug=<?php echo $next['slug']; ?>" class="nav-link">
                                <span class="nav-label">Next Article</span>
                                <span class="nav-title"><?php echo htmlspecialchars(truncateText($next['title'], 60)); ?></span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </article>
        </div>
    </div>
</section>

<!-- ============================================
     RELATED POSTS SECTION
============================================ -->
<?php if ($related_posts): ?>
<section class="related-posts-section">
    <div class="container">
        <div class="section-title">
            <span class="section-subtitle">You Might Also Like</span>
            <h2>Related <span class="text-gold">Articles</span></h2>
            <p>Discover more insights and updates from AGPN</p>
        </div>
        
        <div class="related-posts-grid">
            <?php foreach ($related_posts as $index => $related): ?>
                <article class="related-card">
                    <div class="related-card-image">
                        <?php if ($related['featured_image']): ?>
                            <img src="<?php echo SITE_URL . '/' . $related['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($related['title']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: var(--navy); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-newspaper" style="font-size: 40px; color: var(--gold); opacity: 0.5;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="related-card-content">
                        <div class="related-card-meta">
                            <i class="fas fa-calendar-alt"></i> <?php echo formatDate($related['published_date'], 'M d, Y'); ?>
                        </div>
                        
                        <h3>
                            <a href="single.php?slug=<?php echo $related['slug']; ?>">
                                <?php echo htmlspecialchars(truncateText($related['title'], 60)); ?>
                            </a>
                        </h3>
                        
                        <p><?php echo htmlspecialchars(truncateText($related['excerpt'] ?: strip_tags($related['content']), 100)); ?></p>
                        
                        <a href="single.php?slug=<?php echo $related['slug']; ?>" class="btn-link">
                            Read Article <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     CTA SECTION
============================================ -->
<section class="cta-section">
    <div class="container">
        <div class="cta-wrapper">
            <div class="cta-content">
                <h2>Stay Updated with AGPN</h2>
                <p>Subscribe to our newsletter and never miss our latest insights, programs, and opportunities.</p>
            </div>
            <div class="cta-buttons">
                <a href="#newsletter" class="btn btn-primary" onclick="document.querySelector('.newsletter-input')?.focus(); return false;">
                    <i class="fas fa-envelope"></i>
                    Subscribe Now
                </a>
                <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-outline-light">
                    Contact Us <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     JAVASCRIPT
============================================ -->
<script>
// ============================================
// 1. TABLE OF CONTENTS GENERATOR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    generateTableOfContents();
    initCopyLink();
    initProgressBar();
    initCommentReplies();
    initSmoothScroll();
});

function generateTableOfContents() {
    const content = document.querySelector('.post-body');
    const tocContainer = document.getElementById('tableOfContents');
    
    if (!content || !tocContainer) return;
    
    const headings = content.querySelectorAll('h2, h3');
    
    if (headings.length === 0) {
        tocContainer.innerHTML = '<p class="toc-empty">No sections in this article</p>';
        return;
    }
    
    let tocHTML = '<ul class="toc-list">';
    
    headings.forEach((heading, index) => {
        // Add ID if not present
        if (!heading.id) {
            heading.id = 'section-' + index;
        }
        
        const text = heading.textContent;
        
        tocHTML += `<li class="toc-item">
            <a href="#${heading.id}" class="toc-link">${text}</a>
        </li>`;
    });
    
    tocHTML += '</ul>';
    tocContainer.innerHTML = tocHTML;
}

// ============================================
// 2. COPY LINK FUNCTIONALITY
// ============================================
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Link copied to clipboard!', 'success');
    }, function(err) {
        showToast('Failed to copy link', 'error');
    });
}

function initCopyLink() {
    const copyBtn = document.querySelector('.share-btn.copy-link');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const url = window.location.href;
            copyToClipboard(url);
        });
    }
}

// ============================================
// 3. TOAST NOTIFICATION
// ============================================
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `
        <div style="background: ${type === 'success' ? '#D4EDDA' : '#F8D7DA'}; 
                    color: ${type === 'success' ? '#155724' : '#721C24'}; 
                    padding: 12px 24px; 
                    border-radius: 8px; 
                    border-left: 4px solid ${type === 'success' ? '#28a745' : '#dc3545'};
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                    display: flex;
                    align-items: center;
                    gap: 10px;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================
// 4. PROGRESS BAR
// ============================================
function initProgressBar() {
    window.addEventListener('scroll', function() {
        const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (winScroll / height) * 100;
        
        const progressBar = document.getElementById('reading-progress-bar');
        if (progressBar) {
            progressBar.style.width = scrolled + '%';
        }
    });
}

// ============================================
// 5. COMMENT REPLY FORMS
// ============================================
function showReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    if (form) {
        form.style.display = 'block';
    }
}

function hideReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    if (form) {
        form.style.display = 'none';
    }
}

function initCommentReplies() {
    // Add any comment reply initialization here
}

// ============================================
// 6. SMOOTH SCROLL FOR ANCHOR LINKS
// ============================================
function initSmoothScroll() {
    document.querySelectorAll('.toc-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const headerHeight = document.querySelector('.site-header')?.offsetHeight || 0;
                const targetPosition = targetElement.offsetTop - headerHeight - 30;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Update URL without jumping
                history.pushState(null, null, targetId);
            }
        });
    });
}
</script>

<?php
include 'includes/footer.php';
?>