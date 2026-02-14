<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/contact.php');
}

// Get and sanitize form data
$full_name = sanitize($_POST['full_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$subject = sanitize($_POST['subject'] ?? '');
$message = sanitize($_POST['message'] ?? '');

// Validate required fields
$errors = [];

if (empty($full_name)) {
    $errors[] = 'Full name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email address is required';
}

if (empty($message)) {
    $errors[] = 'Message is required';
}

if (!empty($errors)) {
    $_SESSION['contact_error'] = implode('<br>', $errors);
    redirect(SITE_URL . '/contact.php');
}

try {
    // Save to database
    $stmt = db()->prepare("
        INSERT INTO contact_submissions (full_name, email, phone, subject, message, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $full_name,
        $email,
        $phone,
        $subject,
        $message,
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Send email notification (optional)
    $to = getSetting('contact_email', 'info@afroglobeprime.net');
    $email_subject = "New Contact Form Submission: " . ($subject ?: 'No Subject');
    $email_message = "
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> {$full_name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Phone:</strong> " . ($phone ?: 'Not provided') . "</p>
        <p><strong>Subject:</strong> " . ($subject ?: 'Not provided') . "</p>
        <p><strong>Message:</strong></p>
        <p>" . nl2br($message) . "</p>
    ";
    
    // Uncomment to enable email
    /*
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    
    mail($to, $email_subject, $email_message, $headers);
    */
    
    $_SESSION['contact_success'] = 'Thank you for contacting us! We will get back to you within 24 hours.';
    
} catch (PDOException $e) {
    error_log("Contact form error: " . $e->getMessage());
    $_SESSION['contact_error'] = 'There was an error sending your message. Please try again later.';
}

redirect(SITE_URL . '/contact.php');
?>