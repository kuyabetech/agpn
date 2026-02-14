<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAuth();

$user = Auth::user();

// Get parameters
$format = isset($_POST['format']) ? sanitize($_POST['format']) : 'csv';
$report_type = isset($_POST['type']) ? sanitize($_POST['type']) : 'overview';
$date_from = isset($_POST['date_from']) ? sanitize($_POST['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_POST['date_to']) ? sanitize($_POST['date_to']) : date('Y-m-d');

if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="agpn-report-' . $report_type . '-' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers based on report type
    switch ($report_type) {
        case 'overview':
            fputcsv($output, ['Metric', 'Value', 'Period']);
            
            // Get data
            $total_visitors = db()->query("SELECT COUNT(DISTINCT ip_address) FROM contact_submissions WHERE DATE(submitted_at) BETWEEN ? AND ?")->fetchColumn([$date_from, $date_to]) ?: 0;
            $total_views = db()->query("SELECT SUM(views) FROM blog_posts")->fetchColumn() ?: 0;
            $contact_total = db()->query("SELECT COUNT(*) FROM contact_submissions WHERE DATE(submitted_at) BETWEEN ? AND ?")->fetchColumn([$date_from, $date_to]) ?: 0;
            $applications_total = db()->query("SELECT COUNT(*) FROM application_submissions WHERE DATE(submitted_at) BETWEEN ? AND ?")->fetchColumn([$date_from, $date_to]) ?: 0;
            
            fputcsv($output, ['Total Visitors', $total_visitors, "$date_from to $date_to"]);
            fputcsv($output, ['Total Page Views', $total_views, 'All time']);
            fputcsv($output, ['Contact Submissions', $contact_total, "$date_from to $date_to"]);
            fputcsv($output, ['Applications', $applications_total, "$date_from to $date_to"]);
            
            // Daily traffic
            fputcsv($output, []);
            fputcsv($output, ['Date', 'Unique Visitors', 'Total Visits']);
            
            $daily = db()->query("
                SELECT DATE(submitted_at) as date, COUNT(DISTINCT ip_address) as unique_visitors, COUNT(*) as total_visits
                FROM contact_submissions 
                WHERE DATE(submitted_at) BETWEEN ? AND ?
                GROUP BY DATE(submitted_at)
                ORDER BY date
            ")->fetchAll([$date_from, $date_to]);
            
            foreach ($daily as $row) {
                fputcsv($output, [$row['date'], $row['unique_visitors'], $row['total_visits']]);
            }
            break;
            
        case 'content':
            fputcsv($output, ['Content Type', 'Title', 'Views', 'Status', 'Date']);
            
            // Blog posts
            $posts = db()->query("
                SELECT 'Blog Post' as type, title, views, status, published_date as date
                FROM blog_posts
                ORDER BY views DESC
            ")->fetchAll();
            
            foreach ($posts as $post) {
                fputcsv($output, [$post['type'], $post['title'], $post['views'], $post['status'], $post['date']]);
            }
            
            // Pages
            $pages = db()->query("
                SELECT 'Page' as type, page_title as title, 0 as views, status, updated_at as date
                FROM pages
            ")->fetchAll();
            
            foreach ($pages as $page) {
                fputcsv($output, [$page['type'], $page['title'], $page['views'], $page['status'], $page['date']]);
            }
            break;
            
        case 'conversion':
            fputcsv($output, ['Application Type', 'Status', 'Count']);
            
            $apps = db()->query("
                SELECT application_type, status, COUNT(*) as count
                FROM application_submissions
                WHERE DATE(submitted_at) BETWEEN ? AND ?
                GROUP BY application_type, status
                ORDER BY application_type, status
            ")->fetchAll([$date_from, $date_to]);
            
            foreach ($apps as $app) {
                fputcsv($output, [str_replace('-', ' ', ucfirst($app['application_type'])), $app['status'], $app['count']]);
            }
            break;
    }
    
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // Redirect to PDF generator (you would implement this with TCPDF, Dompdf, etc.)
    $_SESSION['error'] = 'PDF export coming soon!';
    redirect('reports.php?type=' . $report_type . '&date_from=' . $date_from . '&date_to=' . $date_to);
}

// Default redirect
redirect('reports.php?type=' . $report_type . '&date_from=' . $date_from . '&date_to=' . $date_to);
exit;