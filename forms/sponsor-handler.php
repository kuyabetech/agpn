<?php
// ============================================
// SPONSORSHIP FORM HANDLER
// Handles: Corporate sponsors, partnerships, donations
// Features: Email notifications, database storage, auto-responder
// ============================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'errors' => []
];

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) {
        http_response_code(405);
        $response['message'] = 'Method not allowed';
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['sponsor_error'] = 'Invalid request method';
        redirect(SITE_URL . '/sponsor.php');
    }
}

// ============================================
// 1. SANITIZE AND VALIDATE INPUT
// ============================================

// Get and sanitize form data
$organization = sanitize($_POST['organization'] ?? '');
$contact_name = sanitize($_POST['contact_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$tier = sanitize($_POST['tier'] ?? 'custom');
$message = sanitize($_POST['message'] ?? '');
$newsletter = isset($_POST['newsletter']) ? 1 : 0;
$agree_terms = isset($_POST['agree_terms']) ? 1 : 0;

// Validate required fields
$errors = [];

if (empty($organization)) {
    $errors['organization'] = 'Organization name is required';
}

if (empty($contact_name)) {
    $errors['contact_name'] = 'Contact person name is required';
}

if (empty($email)) {
    $errors['email'] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

if (empty($phone)) {
    $errors['phone'] = 'Phone number is required';
}

if (!$agree_terms) {
    $errors['agree_terms'] = 'You must agree to the terms and conditions';
}

// Validate sponsorship tier
$valid_tiers = ['bronze', 'silver', 'gold', 'platinum', 'custom'];
if (!in_array($tier, $valid_tiers)) {
    $tier = 'custom';
}

// If there are validation errors, return early
if (!empty($errors)) {
    if ($is_ajax) {
        http_response_code(422);
        $response['errors'] = $errors;
        $response['message'] = 'Please correct the errors and try again.';
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['sponsor_errors'] = $errors;
        $_SESSION['sponsor_data'] = $_POST;
        $_SESSION['sponsor_error'] = 'Please correct the errors and try again.';
        redirect(SITE_URL . '/sponsor.php#sponsor-form');
    }
}

// ============================================
// 2. INSERT INTO DATABASE
// ============================================

try {
    // Begin transaction
    db()->beginTransaction();

    // Insert into sponsorship_submissions table
    $stmt = db()->prepare("
        INSERT INTO sponsorship_submissions (
            organization,
            contact_name,
            email,
            phone,
            sponsorship_tier,
            message,
            newsletter_optin,
            ip_address,
            user_agent,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $organization,
        $contact_name,
        $email,
        $phone,
        $tier,
        $message,
        $newsletter,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $submission_id = db()->lastInsertId();

    // Log the submission
    error_log("Sponsorship submission #{$submission_id} received from {$organization}");

    // Commit transaction
    db()->commit();

} catch (PDOException $e) {
    // Rollback transaction on error
    db()->rollBack();
    
    error_log("Sponsorship form database error: " . $e->getMessage());
    
    if ($is_ajax) {
        http_response_code(500);
        $response['message'] = 'Database error occurred. Please try again.';
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['sponsor_error'] = 'An error occurred. Please try again.';
        redirect(SITE_URL . '/sponsor.php#sponsor-form');
    }
}

// ============================================
// 3. SEND EMAIL NOTIFICATION TO ADMIN
// ============================================

$admin_email = getSetting('contact_email', 'info@afroglobeprime.net');
$site_name = SITE_NAME;
$site_url = SITE_URL;

// Format sponsorship tier for display
$tier_display = ucfirst($tier);
if ($tier === 'custom') {
    $tier_display = 'Custom Partnership';
}

// Email subject
$admin_subject = "New Sponsorship Inquiry: {$organization} - {$tier_display}";

// Email headers
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: {$site_name} <noreply@{$_SERVER['HTTP_HOST']}>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Build email template for admin
$admin_message = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .email-header {
            background: linear-gradient(135deg, #0A1929 0%, #1A2A3A 100%);
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            color: #FFB81C;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .email-content {
            padding: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-item {
            background: #F8F9FA;
            padding: 15px;
            border-radius: 12px;
        }
        .info-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6C757D;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #0A1929;
        }
        .message-box {
            background: #F8F9FA;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .message-box h3 {
            color: #0A1929;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .tier-badge {
            display: inline-block;
            padding: 8px 16px;
            background: #FFB81C;
            color: #0A1929;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .email-footer {
            background: #F8F9FA;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #E9ECEF;
            font-size: 14px;
            color: #6C757D;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #FFB81C;
            color: #0A1929;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='email-wrapper'>
        <div class='email-header'>
            <h1>New Sponsorship Inquiry</h1>
        </div>
        
        <div class='email-content'>
            <div style='margin-bottom: 30px; text-align: center;'>
                <span class='tier-badge'>{$tier_display}</span>
            </div>
            
            <div class='info-grid'>
                <div class='info-item'>
                    <div class='info-label'>Organization</div>
                    <div class='info-value'>{$organization}</div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Contact Person</div>
                    <div class='info-value'>{$contact_name}</div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Email Address</div>
                    <div class='info-value'><a href='mailto:{$email}'>{$email}</a></div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Phone Number</div>
                    <div class='info-value'>{$phone}</div>
                </div>
            </div>
            
            <div class='message-box'>
                <h3>Message / Specific Interests</h3>
                <p style='margin: 0; line-height: 1.8;'>{$message}</p>
            </div>
            
            <div style='background: #E9ECEF; padding: 15px; border-radius: 12px; margin-bottom: 30px;'>
                <p style='margin: 0; color: #495057;'>
                    <strong>Newsletter Opt-in:</strong> " . ($newsletter ? 'Yes' : 'No') . "<br>
                    <strong>IP Address:</strong> {$_SERVER['REMOTE_ADDR']}<br>
                    <strong>Submission ID:</strong> #{$submission_id}<br>
                    <strong>Date:</strong> " . date('F j, Y \a\t g:i A') . "
                </p>
            </div>
            
            <div style='text-align: center;'>
                <a href='{$site_url}/admin/view-sponsorship.php?id={$submission_id}' class='btn'>
                    View Submission in Admin Panel
                </a>
            </div>
        </div>
        
        <div class='email-footer'>
            <p style='margin: 0;'>© " . date('Y') . " {$site_name}. All rights reserved.</p>
            <p style='margin: 5px 0 0; font-size: 12px;'>This is an automated notification from your website.</p>
        </div>
    </div>
</body>
</html>
";

// Send email to admin
$admin_sent = mail($admin_email, $admin_subject, $admin_message, $headers);

// Log email status
if (!$admin_sent) {
    error_log("Failed to send sponsorship notification email for submission #{$submission_id}");
}

// ============================================
// 4. SEND AUTO-RESPONSE TO SUBMITTER
// ============================================

// Auto-responder subject
$responder_subject = "Thank You for Your Sponsorship Inquiry - {$site_name}";

// Build auto-responder template
$responder_message = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .email-header {
            background: linear-gradient(135deg, #0A1929 0%, #1A2A3A 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .email-header h1 {
            color: #FFB81C;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .email-content {
            padding: 40px 30px;
        }
        .email-footer {
            background: #F8F9FA;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #E9ECEF;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: #FFB81C;
            color: #0A1929;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            margin-top: 20px;
        }
        .social-links {
            margin-top: 20px;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6C757D;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class='email-wrapper'>
        <div class='email-header'>
            <h1>Thank You for Your Interest!</h1>
        </div>
        
        <div class='email-content'>
            <h2 style='color: #0A1929; margin-top: 0;'>Dear {$contact_name},</h2>
            
            <p style='font-size: 16px; line-height: 1.8;'>
                Thank you for reaching out to <strong>{$site_name}</strong> regarding sponsorship opportunities. 
                We truly appreciate your interest in partnering with us to drive global excellence and empower the next generation of African talent.
            </p>
            
            <div style='background: #FFF3CD; padding: 25px; border-radius: 12px; margin: 30px 0;'>
                <h3 style='color: #856404; margin-top: 0; margin-bottom: 15px;'>📋 What Happens Next?</h3>
                <ol style='margin: 0; padding-left: 20px; color: #856404;'>
                    <li style='margin-bottom: 10px;'>Our partnerships team will review your inquiry within 24-48 hours</li>
                    <li style='margin-bottom: 10px;'>A dedicated relationship manager will contact you via email or phone</li>
                    <li style='margin-bottom: 10px;'>We'll schedule a consultation to discuss your specific goals and interests</li>
                    <li style='margin-bottom: 0;'>Together, we'll create a customized partnership proposal</li>
                </ol>
            </div>
            
            <div style='margin: 30px 0;'>
                <h3 style='color: #0A1929;'>📌 Your Submission Details</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px 0; color: #6C757D;'>Organization:</td>
                        <td style='padding: 10px 0; font-weight: 600;'>{$organization}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #6C757D;'>Sponsorship Tier:</td>
                        <td style='padding: 10px 0; font-weight: 600;'>{$tier_display}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #6C757D;'>Submission ID:</td>
                        <td style='padding: 10px 0; font-weight: 600;'>#{$submission_id}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #6C757D;'>Date:</td>
                        <td style='padding: 10px 0; font-weight: 600;'>" . date('F j, Y') . "</td>
                    </tr>
                </table>
            </div>
            
            <p style='font-size: 16px; line-height: 1.8;'>
                If you have any immediate questions or would like to provide additional information, 
                please don't hesitate to contact our partnerships team directly at 
                <a href='mailto:partnerships@afroglobeprime.net' style='color: #FFB81C; text-decoration: none; font-weight: 600;'>
                    partnerships@afroglobeprime.net
                </a>
            </p>
            
            <div style='text-align: center;'>
                <a href='{$site_url}/sponsorship-tiers.php' class='btn'>
                    Explore Sponsorship Tiers
                </a>
            </div>
        </div>
        
        <div class='email-footer'>
            <img src='{$site_url}/assets/images/logo.png' alt='{$site_name}' style='max-height: 50px; margin-bottom: 20px;'>
            <p style='margin: 0; color: #6C757D;'>
                Afroglobe Prime Network Limited<br>
                Empowering Global Excellence
            </p>
            <div class='social-links'>
                <a href='" . getSetting('facebook_url', '#') . "'>Facebook</a> •
                <a href='" . getSetting('twitter_url', '#') . "'>Twitter</a> •
                <a href='" . getSetting('linkedin_url', '#') . "'>LinkedIn</a> •
                <a href='" . getSetting('instagram_url', '#') . "'>Instagram</a>
            </div>
            <p style='margin: 20px 0 0; font-size: 12px; color: #ADB5BD;'>
                This email was sent in response to your sponsorship inquiry submitted through our website.<br>
                © " . date('Y') . " {$site_name}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
";

// Send auto-response
$responder_sent = mail($email, $responder_subject, $responder_message, $headers);

// ============================================
// 5. RETURN RESPONSE
// ============================================

if ($is_ajax) {
    // AJAX response
    $response['success'] = true;
    $response['message'] = 'Thank you for your sponsorship inquiry! We will contact you within 24-48 hours.';
    $response['submission_id'] = $submission_id;
    echo json_encode($response);
    exit;
} else {
    // Standard form submission
    $_SESSION['sponsor_success'] = 'Thank you for your sponsorship inquiry! We will contact you within 24-48 hours.';
    unset($_SESSION['sponsor_data']);
    redirect(SITE_URL . '/sponsor.php?success=1');
}
?>