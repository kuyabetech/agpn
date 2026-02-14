<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Blog & News';
$meta_description = 'Latest updates, insights, and news from Afroglobe Prime Network Limited.';
$meta_keywords = 'AGPN blog, news, updates, insights, Africa, business, digital skills, study abroad';

// Get search query
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// Build query with search filter
try {
    $db = db();
    
    $countQuery = "SELECT COUNT(*) FROM blog_posts WHERE status = 'published'";
    $postsQuery = "SELECT * FROM blog_posts WHERE status = 'published'";
    $params = [];
    
    // Add search filter
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $countQuery .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
        $postsQuery .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Get total count
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    $total_pages = ceil($count / $limit);
    
    // Add pagination to posts query
    $postsQuery .= " ORDER BY published_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Get posts
    $stmt = $db->prepare($postsQuery);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get featured post (latest published)
    $featured_post = $db->query("
        SELECT * FROM blog_posts 
        WHERE status = 'published' 
        ORDER BY published_date DESC 
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching blog posts: " . $e->getMessage());
    $posts = [];
    $featured_post = null;
    $total_pages = 0;
}

// Get session messages
$success = isset($_SESSION['newsletter_success']) ? $_SESSION['newsletter_success'] : null;
$error = isset($_SESSION['newsletter_error']) ? $_SESSION['newsletter_error'] : null;
unset($_SESSION['newsletter_success'], $_SESSION['newsletter_error']);

$show_breadcrumbs = true;
$breadcrumbs = [
    ['title' => 'Blog']
];
$body_class = 'blog-page';

include 'includes/header.php';
?>

<style>
/* ============================================
   BLOG PAGE STYLES - FULLY RESPONSIVE
   NO CATEGORIES - CLEAN AND SIMPLE
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
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    --success: #28a745;
    --danger: #dc3545;
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
   HERO SECTION - FULLY RESPONSIVE
============================================ */
.blog-hero {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 0;
    position: relative;
    overflow: hidden;
}

@media (min-width: 768px) {
    .blog-hero {
        padding: 80px 0;
    }
}

@media (min-width: 992px) {
    .blog-hero {
        padding: 100px 0;
    }
}

.blog-hero::before {
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
    .blog-hero::before {
        width: 400px;
        height: 400px;
    }
}

@media (min-width: 992px) {
    .blog-hero::before {
        width: 500px;
        height: 500px;
    }
}

@keyframes pulse {
    0%, 100% { opacity: 0.1; transform: scale(1); }
    50% { opacity: 0.2; transform: scale(1.1); }
}

.blog-hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
    color: var(--white);
}

.blog-hero h1 {
    color: var(--white);
    font-size: 32px;
    margin-bottom: 15px;
    animation: fadeInUp 1s ease;
}

@media (min-width: 576px) {
    .blog-hero h1 {
        font-size: 36px;
    }
}

@media (min-width: 768px) {
    .blog-hero h1 {
        font-size: 42px;
    }
}

@media (min-width: 992px) {
    .blog-hero h1 {
        font-size: 48px;
    }
}

.blog-hero p {
    color: rgba(255,255,255,0.9);
    font-size: 16px;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto;
    animation: fadeInUp 1s ease 0.1s both;
}

@media (min-width: 768px) {
    .blog-hero p {
        font-size: 18px;
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

/* ============================================
   SEARCH BAR - FULLY RESPONSIVE
============================================ */
.blog-toolbar {
    background: var(--white);
    padding: 20px 0;
    border-bottom: 1px solid var(--gray-200);
}

.search-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-width: 600px;
    margin: 0 auto;
}

@media (min-width: 576px) {
    .search-container {
        flex-direction: row;
        max-width: 100%;
    }
}

.search-form {
    display: flex;
    flex: 1;
    gap: 10px;
    width: 100%;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    width: 100%;
}

@media (min-width: 576px) {
    .search-input {
        font-size: 15px;
    }
}

.search-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 4px rgba(255,184,28,0.1);
}

.search-btn {
    padding: 12px 20px;
    background: var(--gold);
    color: var(--navy);
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    white-space: nowrap;
}

@media (min-width: 576px) {
    .search-btn {
        padding: 12px 24px;
        font-size: 15px;
    }
}

.search-btn:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
}

.search-btn i {
    font-size: 14px;
}

.clear-search {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--gray-500);
    font-size: 14px;
    text-decoration: none;
    padding: 12px 0;
}

@media (min-width: 576px) {
    .clear-search {
        padding: 12px 20px;
    }
}

.clear-search:hover {
    color: var(--gold);
}

/* ============================================
   FEATURED POST - FULLY RESPONSIVE
============================================ */
.featured-section {
    padding: 40px 0;
    background: var(--white);
}

@media (min-width: 768px) {
    .featured-section {
        padding: 60px 0;
    }
}

.featured-card {
    display: flex;
    flex-direction: column;
    background: var(--white);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--gray-200);
}

@media (min-width: 768px) {
    .featured-card {
        flex-direction: row;
    }
}

.featured-image {
    width: 100%;
    height: 250px;
    overflow: hidden;
}

@media (min-width: 768px) {
    .featured-image {
        width: 50%;
        height: auto;
        min-height: 350px;
    }
}

@media (min-width: 992px) {
    .featured-image {
        min-height: 400px;
    }
}

.featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.featured-card:hover .featured-image img {
    transform: scale(1.05);
}

.featured-content {
    padding: 25px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

@media (min-width: 768px) {
    .featured-content {
        width: 50%;
        padding: 30px;
    }
}

@media (min-width: 992px) {
    .featured-content {
        padding: 40px;
    }
}

.featured-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--gold);
    color: var(--navy);
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 15px;
    align-self: flex-start;
}

@media (min-width: 768px) {
    .featured-badge {
        font-size: 12px;
        padding: 6px 14px;
    }
}

.featured-content h2 {
    font-size: 20px;
    margin-bottom: 12px;
    color: var(--navy);
    line-height: 1.3;
}

@media (min-width: 576px) {
    .featured-content h2 {
        font-size: 24px;
    }
}

@media (min-width: 768px) {
    .featured-content h2 {
        font-size: 28px;
    }
}

@media (min-width: 992px) {
    .featured-content h2 {
        font-size: 32px;
    }
}

.featured-content h2 a {
    color: var(--navy);
    text-decoration: none;
}

.featured-content h2 a:hover {
    color: var(--gold);
}

.featured-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    color: var(--gray-600);
    font-size: 12px;
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .featured-meta {
        font-size: 14px;
        gap: 20px;
        margin-bottom: 20px;
    }
}

.featured-meta i {
    color: var(--gold);
    margin-right: 5px;
}

.featured-excerpt {
    color: var(--gray-600);
    line-height: 1.7;
    margin-bottom: 20px;
    font-size: 14px;
}

@media (min-width: 768px) {
    .featured-excerpt {
        font-size: 15px;
        line-height: 1.8;
        margin-bottom: 25px;
    }
}

/* ============================================
   BLOG GRID - FULLY RESPONSIVE
============================================ */
.blog-section {
    padding: 40px 0;
    background: var(--gray-100);
}

@media (min-width: 768px) {
    .blog-section {
        padding: 60px 0;
    }
}

.results-info {
    margin-bottom: 20px;
    color: var(--gray-600);
    font-size: 14px;
    text-align: center;
}

@media (min-width: 576px) {
    .results-info {
        text-align: left;
        font-size: 15px;
        margin-bottom: 30px;
    }
}

.blog-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

@media (min-width: 576px) {
    .blog-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}

@media (min-width: 992px) {
    .blog-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
    }
}

.blog-card {
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

.blog-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: var(--gold);
}

.blog-card-image {
    height: 180px;
    overflow: hidden;
    position: relative;
}

@media (min-width: 576px) {
    .blog-card-image {
        height: 200px;
    }
}

@media (min-width: 768px) {
    .blog-card-image {
        height: 220px;
    }
}

.blog-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.blog-card:hover .blog-card-image img {
    transform: scale(1.1);
}

.blog-card-content {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

@media (min-width: 768px) {
    .blog-card-content {
        padding: 25px;
    }
}

.blog-meta {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 11px;
    color: var(--gray-600);
    flex-wrap: wrap;
}

@media (min-width: 768px) {
    .blog-meta {
        font-size: 12px;
        gap: 15px;
        margin-bottom: 15px;
    }
}

.blog-meta i {
    color: var(--gold);
    margin-right: 4px;
}

.blog-card h3 {
    font-size: 16px;
    margin-bottom: 12px;
    line-height: 1.4;
}

@media (min-width: 576px) {
    .blog-card h3 {
        font-size: 18px;
    }
}

@media (min-width: 768px) {
    .blog-card h3 {
        font-size: 20px;
        margin-bottom: 15px;
    }
}

.blog-card h3 a {
    color: var(--navy);
    text-decoration: none;
}

.blog-card h3 a:hover {
    color: var(--gold);
}

.blog-card p {
    color: var(--gray-600);
    line-height: 1.6;
    margin-bottom: 15px;
    flex: 1;
    font-size: 13px;
}

@media (min-width: 768px) {
    .blog-card p {
        font-size: 14px;
        line-height: 1.7;
        margin-bottom: 20px;
    }
}

.read-more {
    color: var(--gold);
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.3s ease;
    font-size: 13px;
}

@media (min-width: 768px) {
    .read-more {
        font-size: 14px;
        gap: 8px;
    }
}

.read-more:hover {
    gap: 10px;
    color: var(--gold-light);
}

/* ============================================
   PAGINATION - FULLY RESPONSIVE
============================================ */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    margin-top: 40px;
    flex-wrap: wrap;
}

@media (min-width: 576px) {
    .pagination {
        gap: 8px;
        margin-top: 50px;
    }
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 8px;
    background: var(--white);
    color: var(--navy);
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.3s ease;
}

@media (min-width: 576px) {
    .page-link {
        min-width: 40px;
        height: 40px;
        font-size: 14px;
        border-radius: 8px;
    }
}

.page-link:hover {
    background: var(--gold);
    color: var(--navy);
    border-color: var(--gold);
}

.page-link.active {
    background: var(--gold);
    color: var(--navy);
    border-color: var(--gold);
}

.page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* ============================================
   NEWSLETTER SECTION - FULLY RESPONSIVE
============================================ */
.newsletter-section {
    padding: 50px 0;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    color: var(--white);
}

@media (min-width: 768px) {
    .newsletter-section {
        padding: 80px 0;
    }
}

.newsletter-container {
    max-width: 100%;
    margin: 0 auto;
    text-align: center;
    padding: 0 20px;
}

@media (min-width: 576px) {
    .newsletter-container {
        max-width: 500px;
    }
}

@media (min-width: 768px) {
    .newsletter-container {
        max-width: 600px;
    }
}

.newsletter-container h2 {
    color: var(--white);
    margin-bottom: 12px;
    font-size: 24px;
}

@media (min-width: 576px) {
    .newsletter-container h2 {
        font-size: 28px;
        margin-bottom: 15px;
    }
}

@media (min-width: 768px) {
    .newsletter-container h2 {
        font-size: 32px;
    }
}

.newsletter-container p {
    color: rgba(255,255,255,0.9);
    margin-bottom: 25px;
    font-size: 14px;
    line-height: 1.6;
}

@media (min-width: 576px) {
    .newsletter-container p {
        font-size: 15px;
        margin-bottom: 30px;
    }
}

@media (min-width: 768px) {
    .newsletter-container p {
        font-size: 16px;
    }
}

.newsletter-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

@media (min-width: 576px) {
    .newsletter-form {
        flex-direction: row;
        background: rgba(255,255,255,0.1);
        padding: 5px;
        border-radius: 50px;
        border: 1px solid rgba(255,255,255,0.2);
    }
}

.newsletter-input {
    width: 100%;
    padding: 14px 18px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 50px;
    color: var(--white);
    font-size: 14px;
    transition: all 0.3s ease;
}

@media (min-width: 576px) {
    .newsletter-input {
        background: transparent;
        border: none;
        flex: 1;
    }
    
    .newsletter-input:focus {
        background: transparent;
    }
}

.newsletter-input::placeholder {
    color: rgba(255,255,255,0.6);
}

.newsletter-input:focus {
    outline: none;
    background: rgba(255,255,255,0.15);
}

.newsletter-btn {
    width: 100%;
    padding: 14px 24px;
    background: var(--gold);
    color: var(--navy);
    border: none;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

@media (min-width: 576px) {
    .newsletter-btn {
        width: auto;
        padding: 14px 30px;
        font-size: 15px;
    }
}

.newsletter-btn:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
}

.privacy-note {
    font-size: 11px;
    margin-top: 15px;
    opacity: 0.8;
}

@media (min-width: 768px) {
    .privacy-note {
        font-size: 12px;
        margin-top: 20px;
    }
}

/* ============================================
   TOAST NOTIFICATIONS - FULLY RESPONSIVE
============================================ */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    left: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}

@media (min-width: 576px) {
    .toast-container {
        left: auto;
        right: 30px;
        top: 30px;
    }
}

.toast {
    background: var(--white);
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    transform: translateX(100%);
    animation: slideIn 0.3s ease forwards;
    border-left: 4px solid;
    pointer-events: auto;
    width: 100%;
}

@media (min-width: 576px) {
    .toast {
        min-width: 300px;
        max-width: 400px;
        padding: 16px 20px;
    }
}

.toast-success {
    border-left-color: var(--success);
}

.toast-error {
    border-left-color: var(--danger);
}

.toast-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.toast-success .toast-icon {
    background: rgba(40,167,69,0.1);
    color: var(--success);
}

.toast-error .toast-icon {
    background: rgba(220,53,69,0.1);
    color: var(--danger);
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 700;
    margin-bottom: 4px;
    color: var(--navy);
    font-size: 14px;
}

.toast-message {
    font-size: 13px;
    color: var(--gray-600);
}

.toast-close {
    color: var(--gray-500);
    cursor: pointer;
    font-size: 16px;
    transition: color 0.3s ease;
    background: none;
    border: none;
    padding: 0;
    flex-shrink: 0;
}

.toast-close:hover {
    color: var(--danger);
}

@keyframes slideIn {
    to {
        transform: translateX(0);
    }
}

@keyframes slideOut {
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* ============================================
   EMPTY STATE - FULLY RESPONSIVE
============================================ */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}

@media (min-width: 768px) {
    .empty-state {
        padding: 60px 20px;
    }
}

.empty-state i {
    font-size: 48px;
    color: var(--gold);
    margin-bottom: 20px;
}

@media (min-width: 768px) {
    .empty-state i {
        font-size: 64px;
    }
}

.empty-state h2 {
    font-size: 20px;
    margin-bottom: 12px;
    color: var(--navy);
}

@media (min-width: 768px) {
    .empty-state h2 {
        font-size: 24px;
        margin-bottom: 15px;
    }
}

.empty-state p {
    color: var(--gray-600);
    max-width: 500px;
    margin: 0 auto 20px;
    font-size: 14px;
    line-height: 1.6;
}

@media (min-width: 768px) {
    .empty-state p {
        font-size: 15px;
        margin-bottom: 25px;
    }
}

.empty-state .btn {
    display: inline-block;
    padding: 12px 24px;
    background: var(--gold);
    color: var(--navy);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
}

@media (min-width: 768px) {
    .empty-state .btn {
        padding: 14px 30px;
        font-size: 15px;
    }
}

.empty-state .btn:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
}

/* ============================================
   LOADING SPINNER
============================================ */
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(10,25,41,0.1);
    border-radius: 50%;
    border-top-color: var(--navy);
    animation: spin 1s ease-in-out infinite;
    margin-right: 5px;
}

@media (min-width: 768px) {
    .spinner {
        width: 20px;
        height: 20px;
        border-width: 3px;
    }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Hero Section -->
<section class="blog-hero">
    <div class="container">
        <div class="blog-hero-content">
            <h1>Blog & News</h1>
            <p>Insights, updates, and stories from AGPN</p>
        </div>
    </div>
</section>

<!-- Search Bar -->
<section class="blog-toolbar">
    <div class="container">
        <div class="search-container">
            <form action="" method="GET" class="search-form">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search articles..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> <span>Search</span>
                </button>
            </form>
            
            <?php if (!empty($search)): ?>
                <a href="blog.php" class="clear-search">
                    <i class="fas fa-times"></i> Clear search
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Featured Post -->
<?php if ($featured_post && empty($search) && $page == 1): ?>
<section class="featured-section">
    <div class="container">
        <div class="featured-card">
            <div class="featured-image">
                <?php if ($featured_post['featured_image']): ?>
                    <img src="<?php echo SITE_URL . '/' . $featured_post['featured_image']; ?>" 
                         alt="<?php echo htmlspecialchars($featured_post['title']); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; background: var(--navy); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-newspaper" style="font-size: 48px; color: var(--gold); opacity: 0.5;"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="featured-content">
                <span class="featured-badge">Featured Post</span>
                <h2>
                    <a href="single.php?slug=<?php echo $featured_post['slug']; ?>">
                        <?php echo htmlspecialchars($featured_post['title']); ?>
                    </a>
                </h2>
                
                <div class="featured-meta">
                    <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($featured_post['published_date']); ?></span>
                    <?php if ($featured_post['author']): ?>
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($featured_post['author']); ?></span>
                    <?php endif; ?>
                </div>
                
                <p class="featured-excerpt">
                    <?php echo htmlspecialchars($featured_post['excerpt'] ?: truncateText(strip_tags($featured_post['content']), 200)); ?>
                </p>
                
                <a href="single.php?slug=<?php echo $featured_post['slug']; ?>" class="btn btn-primary" style="align-self: flex-start; display: inline-block; padding: 10px 20px; background: var(--gold); color: var(--navy); text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                    Read Article <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Blog Grid -->
<section class="blog-section">
    <div class="container">
        <?php if ($posts): ?>
            <!-- Results Info -->
            <?php if (!empty($search)): ?>
                <div class="results-info">
                    Showing <?php echo count($posts); ?> of <?php echo $count; ?> articles for "<?php echo htmlspecialchars($search); ?>"
                </div>
            <?php endif; ?>
            
            <!-- Posts Grid -->
            <div class="blog-grid">
                <?php foreach ($posts as $post): ?>
                    <article class="blog-card">
                        <div class="blog-card-image">
                            <?php if ($post['featured_image']): ?>
                                <img src="<?php echo SITE_URL . '/' . $post['featured_image']; ?>" 
                                     alt="<?php echo htmlspecialchars($post['title']); ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: var(--navy); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-newspaper" style="font-size: 48px; color: var(--gold); opacity: 0.5;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="blog-card-content">
                            <div class="blog-meta">
                                <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($post['published_date']); ?></span>
                                <?php if ($post['author']): ?>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['author']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <h3>
                                <a href="single.php?slug=<?php echo $post['slug']; ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </h3>
                            
                            <p><?php echo htmlspecialchars($post['excerpt'] ?: truncateText(strip_tags($post['content']), 120)); ?></p>
                            
                            <a href="single.php?slug=<?php echo $post['slug']; ?>" class="read-more">
                                Read More <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <a class="page-link" href="?<?php 
                            echo !empty($search) ? 'search=' . urlencode($search) . '&' : '';
                            echo 'page=' . ($page - 1); 
                        ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if (
                            $i == 1 || 
                            $i == $total_pages || 
                            ($i >= $page - 2 && $i <= $page + 2)
                        ): ?>
                            <a class="page-link <?php echo $i == $page ? 'active' : ''; ?>" href="?<?php 
                                echo !empty($search) ? 'search=' . urlencode($search) . '&' : '';
                                echo 'page=' . $i; 
                            ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php elseif ($i == 2 && $page > 4): ?>
                            <span class="page-link disabled">...</span>
                        <?php elseif ($i == $total_pages - 1 && $page < $total_pages - 3): ?>
                            <span class="page-link disabled">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <a class="page-link" href="?<?php 
                            echo !empty($search) ? 'search=' . urlencode($search) . '&' : '';
                            echo 'page=' . ($page + 1); 
                        ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-newspaper"></i>
                <h2>No Articles Found</h2>
                <p>
                    <?php if (!empty($search)): ?>
                        No articles match your search "<?php echo htmlspecialchars($search); ?>". Try different keywords.
                    <?php else: ?>
                        Check back soon for updates, news, and insights from AGPN.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search)): ?>
                    <a href="blog.php" class="btn">View All Articles</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Newsletter Section -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-container">
            <h2>Subscribe to Our Newsletter</h2>
            <p>Get the latest updates on programs, events, and opportunities delivered to your inbox.</p>
            
            <form action="forms/newsletter-handler.php" method="POST" class="newsletter-form" id="newsletterForm">
                <input type="email" 
                       name="email" 
                       class="newsletter-input" 
                       placeholder="Enter your email address" 
                       required
                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                <button type="submit" class="newsletter-btn">
                    Subscribe <i class="fas fa-paper-plane"></i>
                </button>
            </form>
            
            <p class="privacy-note">
                We respect your privacy. Unsubscribe at any time.
            </p>
        </div>
    </div>
</section>

<script>
// ============================================
// BLOG PAGE JAVASCRIPT - SIMPLE & RESPONSIVE
// ============================================

(function() {
    'use strict';

    // ============================================
    // TOAST NOTIFICATION SYSTEM
    // ============================================
    const ToastManager = {
        container: null,
        
        init: function() {
            this.container = document.getElementById('toastContainer');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainer';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },
        
        show: function(title, message, type = 'success', duration = 5000) {
            if (!this.container) this.init();
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            let icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.closest('.toast').remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            this.container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => {
                        if (toast.parentNode) toast.remove();
                    }, 300);
                }
            }, duration);
        },
        
        success: function(title, message) {
            this.show(title, message, 'success');
        },
        
        error: function(title, message) {
            this.show(title, message, 'error');
        }
    };

    // ============================================
    // NEWSLETTER FORM HANDLER
    // ============================================
    const NewsletterForm = {
        form: null,
        
        init: function() {
            this.form = document.getElementById('newsletterForm');
            if (!this.form) return;
            
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            const email = this.form.querySelector('input[type="email"]');
            const submitBtn = this.form.querySelector('button[type="submit"]');
            
            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailRegex.test(email.value)) {
                ToastManager.error('Invalid Email', 'Please enter a valid email address.');
                email.focus();
                return;
            }
            
            // Show loading state
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Subscribing...';
            
            // Submit form
            this.form.submit();
        }
    };

    // ============================================
    // NAVBAR TOGGLE
    // ============================================
    const NavbarToggle = {
        init: function() {
            this.toggle = document.getElementById('navToggle');
            this.menu = document.getElementById('navMenu');
            
            if (this.toggle && this.menu) {
                this.toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.menu.classList.toggle('active');
                    this.toggle.classList.toggle('active');
                });
                
                // Close menu on window resize
                window.addEventListener('resize', () => {
                    if (window.innerWidth > 768 && this.menu.classList.contains('active')) {
                        this.menu.classList.remove('active');
                        this.toggle.classList.remove('active');
                    }
                });
            }
        }
    };

    // ============================================
    // SESSION MESSAGES
    // ============================================
    const SessionMessages = {
        init: function() {
            <?php if ($success): ?>
            ToastManager.success(
                'Success!', 
                '<?php echo addslashes($success); ?>'
            );
            <?php endif; ?>
            
            <?php if ($error): ?>
            ToastManager.error(
                'Error!', 
                '<?php echo addslashes($error); ?>'
            );
            <?php endif; ?>
        }
    };

    // ============================================
    // INITIALIZE ALL MODULES
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Blog page initialized');
        
        ToastManager.init();
        NewsletterForm.init();
        NavbarToggle.init();
        SessionMessages.init();
    });

})();
</script>

<?php
include 'includes/footer.php';
?>