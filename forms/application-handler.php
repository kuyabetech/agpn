<?php
// ============================================
// APPLICATION FORM HANDLER
// Processes: Digital Skills, Study Abroad, Business Growth, Sponsorship
// Features: File upload, Database storage, Email notifications
// ============================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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
        $_SESSION['application_error'] = 'Invalid request method';
        redirect(SITE_URL . '/application.php');
    }
}

// ============================================
// 1. SANITIZE AND VALIDATE INPUT
// ============================================

// Get and sanitize form data
$program = sanitize($_POST['program'] ?? '');
$full_name = sanitize($_POST['full_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$country = sanitize($_POST['country'] ?? '');
$date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
$gender = sanitize($_POST['gender'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$cover_letter = sanitize($_POST['cover_letter'] ?? '');

// Validate required fields
$errors = [];

if (empty($program)) {
    $errors['program'] = 'Please select a program';
}

if (empty($full_name)) {
    $errors['full_name'] = 'Full name is required';
}

if (empty($email)) {
    $errors['email'] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

if (empty($phone)) {
    $errors['phone'] = 'Phone number is required';
}

if (empty($country)) {
    $errors['country'] = 'Country of residence is required';
}

// Validate program type
$valid_programs = ['digital-skills', 'study-abroad', 'business-growth', 'sponsorship'];
if (!in_array($program, $valid_programs)) {
    $errors['program'] = 'Invalid program selection';
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
        $_SESSION['application_errors'] = $errors;
        $_SESSION['application_data'] = $_POST;
        $_SESSION['application_error'] = 'Please correct the errors and try again.';
        redirect(SITE_URL . '/application.php');
    }
}

// ============================================
// 2. HANDLE FILE UPLOADS
// ============================================

// Create upload directory if not exists
$upload_dir = '../uploads/applications/' . date('Y/m/d');
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Process CV/Resume upload
$cv_path = null;
if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === 0) {
    $file = $_FILES['cv_file'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['pdf', 'doc', 'docx'];
    
    // Validate file type
    if (!in_array($file_extension, $allowed_types)) {
        $errors['cv_file'] = 'Invalid file type. Please upload PDF, DOC, or DOCX.';
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5000000) {
        $errors['cv_file'] = 'File is too large. Max size is 5MB.';
    }
    
    if (empty($errors)) {
        $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "", $file['name']);
        $destination = $upload_dir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $cv_path = 'uploads/applications/' . date('Y/m/d') . '/' . $filename;
        } else {
            $errors['cv_file'] = 'Failed to upload file. Please try again.';
        }
    }
} else {
    $errors['cv_file'] = 'CV/Resume is required';
}

// Process additional files
$additional_files = [];
if (isset($_FILES['additional_files']) && !empty($_FILES['additional_files']['name'][0])) {
    $files = $_FILES['additional_files'];
    $total_size = 0;
    
    foreach ($files['name'] as $index => $name) {
        if ($files['error'][$index] === 0) {
            $total_size += $files['size'][$index];
        }
    }
    
    // Check total size (10MB max)
    if ($total_size > 10000000) {
        $errors['additional_files'] = 'Total file size exceeds 10MB limit.';
    }
    
    if (empty($errors)) {
        foreach ($files['name'] as $index => $name) {
            if ($files['error'][$index] === 0) {
                $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "", $name);
                $destination = $upload_dir . '/' . $filename;
                
                if (move_uploaded_file($files['tmp_name'][$index], $destination)) {
                    $additional_files[] = [
                        'original_name' => $name,
                        'file_path' => 'uploads/applications/' . date('Y/m/d') . '/' . $filename,
                        'file_size' => $files['size'][$index],
                        'file_type' => $file_extension
                    ];
                }
            }
        }
    }
}

// If there are file upload errors, return
if (!empty($errors)) {
    if ($is_ajax) {
        http_response_code(422);
        $response['errors'] = $errors;
        $response['message'] = 'Please correct the errors and try again.';
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['application_errors'] = $errors;
        $_SESSION['application_data'] = $_POST;
        $_SESSION['application_error'] = 'Please correct the errors and try again.';
        redirect(SITE_URL . '/application.php');
    }
}

// ============================================
// 3. INSERT INTO DATABASE
// ============================================

try {
    // Begin transaction
    db()->beginTransaction();
    
    // Insert main application
    $stmt = db()->prepare("
        INSERT INTO application_submissions (
            application_type,
            full_name,
            email,
            phone,
            country,
            date_of_birth,
            gender,
            address,
            cover_letter,
            cv_path,
            status,
            ip_address,
            user_agent,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
    ");
    
    $stmt->execute([
        $program,
        $full_name,
        $email,
        $phone,
        $country,
        $date_of_birth ?: null,
        $gender ?: null,
        $address ?: null,
        $cover_letter ?: null,
        $cv_path,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    $application_id = db()->lastInsertId();
    
    // Insert additional files if any
    if (!empty($additional_files)) {
        $file_stmt = db()->prepare("
            INSERT INTO application_files (
                application_id,
                file_name,
                file_path,
                file_size,
                file_type,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($additional_files as $file) {
            $file_stmt->execute([
                $application_id,
                $file['original_name'],
                $file['file_path'],
                $file['file_size'],
                $file['file_type']
            ]);
        }
    }
    
    // Commit transaction
    db()->commit();
    
    // Log the submission
    error_log("Application #{$application_id} submitted for {$program} by {$full_name}");
    
} catch (PDOException $e) {
    // Rollback transaction on error
    db()->rollBack();
    
    // Delete uploaded files if database insert fails
    if ($cv_path && file_exists('../' . $cv_path)) {
        unlink('../' . $cv_path);
    }
    
    foreach ($additional_files as $file) {
        if (file_exists('../' . $file['file_path'])) {
            unlink('../' . $file['file_path']);
        }
    }
    
    error_log("Application database error: " . $e->getMessage());
    
    if ($is_ajax) {
        http_response_code(500);
        $response['message'] = 'Database error occurred. Please try again.';
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['application_error'] = 'An error occurred. Please try again.';
        redirect(SITE_URL . '/application.php');
    }
}

// ============================================
// 4. SEND EMAIL NOTIFICATION TO ADMIN
// ============================================

$admin_email = getSetting('contact_email', 'info@afroglobeprime.net');
$site_name = SITE_NAME;
$site_url = SITE_URL;

// Format program name for display
$program_display = str_replace('-', ' ', ucwords($program));

// Email subject
$admin_subject = "New Application: {$program_display} - {$full_name}";

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
        .program-badge {
            display: inline-block;
            padding: 8px 20px;
            background: #FFB81C;
            color: #0A1929;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
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
        .files-list {
            background: #F8F9FA;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .files-list ul {
            margin: 10px 0 0;
            padding-left: 20px;
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
        .email-footer {
            background: #F8F9FA;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #E9ECEF;
            font-size: 14px;
            color: #6C757D;
        }
    </style>
</head>
<body>
    <div class='email-wrapper'>
        <div class='email-header'>
            <h1>New Program Application</h1>
        </div>
        
        <div class='email-content'>
            <div style='text-align: center;'>
                <span class='program-badge'>{$program_display}</span>
            </div>
            
            <div class='info-grid'>
                <div class='info-item'>
                    <div class='info-label'>Full Name</div>
                    <div class='info-value'>{$full_name}</div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Email</div>
                    <div class='info-value'><a href='mailto:{$email}'>{$email}</a></div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Phone</div>
                    <div class='info-value'>{$phone}</div>
                </div>
                <div class='info-item'>
                    <div class='info-label'>Country</div>
                    <div class='info-value'>{$country}</div>
                </div>
            </div>";
            
            if (!empty($cover_letter)) {
                $admin_message .= "
            <div class='message-box'>
                <h3>Cover Letter / Statement of Purpose</h3>
                <p style='margin: 0; line-height: 1.8;'>" . nl2br($cover_letter) . "</p>
            </div>";
            }
            
            $admin_message .= "
            <div class='files-list'>
                <h3 style='margin-top: 0; margin-bottom: 15px;'>Uploaded Documents</h3>
                <p><strong>CV/Resume:</strong> " . basename($cv_path) . "</p>";
                
                if (!empty($additional_files)) {
                    $admin_message .= "<p><strong>Additional Files:</strong></p><ul>";
                    foreach ($additional_files as $file) {
                        $admin_message .= "<li>" . $file['original_name'] . " (" . round($file['file_size'] / 1024, 2) . " KB)</li>";
                    }
                    $admin_message .= "</ul>";
                } else {
                    $admin_message .= "<p><em>No additional files uploaded</em></p>";
                }
                
                $admin_message .= "
            </div>
            
            <div style='background: #E9ECEF; padding: 15px; border-radius: 12px; margin-bottom: 30px;'>
                <p style='margin: 0; color: #495057;'>
                    <strong>Application ID:</strong> #{$application_id}<br>
                    <strong>IP Address:</strong> {$_SERVER['REMOTE_ADDR']}<br>
                    <strong>Date:</strong> " . date('F j, Y \a\t g:i A') . "
                </p>
            </div>
            
            <div style='text-align: center;'>
                <a href='{$site_url}/admin/view-application.php?id={$application_id}' class='btn'>
                    View Application in Admin Panel
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
    error_log("Failed to send application notification email for #{$application_id}");
}

// ============================================
// 5. SEND AUTO-RESPONSE TO APPLICANT
// ============================================

// Auto-responder subject
$responder_subject = "Application Received - {$program_display} - {$site_name}";

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
        .program-badge {
            display: inline-block;
            padding: 6px 16px;
            background: #FFB81C;
            color: #0A1929;
            border-radius: 30px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #F8F9FA;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
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
        .email-footer {
            background: #F8F9FA;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #E9ECEF;
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
            <h1>Application Received!</h1>
        </div>
        
        <div class='email-content'>
            <div style='text-align: center;'>
                <span class='program-badge'>{$program_display}</span>
            </div>
            
            <h2 style='color: #0A1929; margin-top: 0;'>Dear {$full_name},</h2>
            
            <p style='font-size: 16px; line-height: 1.8;'>
                Thank you for applying to the <strong>{$program_display}</strong> program at {$site_name}. 
                We have successfully received your application and all supporting documents.
            </p>
            
            <div class='info-box'>
                <h3 style='color: #0A1929; margin-top: 0; margin-bottom: 20px;'>📋 What Happens Next?</h3>
                <ol style='margin: 0; padding-left: 20px; color: #495057;'>
                    <li style='margin-bottom: 15px;'>Our admissions team will review your application within 5-7 business days.</li>
                    <li style='margin-bottom: 15px;'>You will receive an email confirmation with your application reference number.</li>
                    <li style='margin-bottom: 15px;'>If additional information is needed, we will contact you via email or phone.</li>
                    <li style='margin-bottom: 0;'>You will be notified of the admission decision via email.</li>
                </ol>
            </div>
            
            <div style='background: #FFF3CD; padding: 20px; border-radius: 12px; margin: 30px 0;'>
                <p style='margin: 0; color: #856404;'>
                    <strong>Application Reference:</strong> #{$application_id}<br>
                    <strong>Program:</strong> {$program_display}<br>
                    <strong>Submission Date:</strong> " . date('F j, Y') . "
                </p>
            </div>
            
            <p style='font-size: 16px; line-height: 1.8;'>
                If you have any questions or need to update your application, please contact our admissions team at 
                <a href='mailto:admissions@afroglobeprime.net' style='color: #FFB81C; text-decoration: none; font-weight: 600;'>
                    admissions@afroglobeprime.net
                </a>
            </p>
            
            <div style='text-align: center;'>
                <a href='{$site_url}/programs.php' class='btn'>
                    Explore More Programs
                </a>
            </div>
        </div>
        
        <div class='email-footer'>
            <img src='{$site_url}/assets/images/logo.png' alt='{$site_name}' style='max-height: 50px; margin-bottom: 20px;'>
            <p style='margin: 0; color: #6C757D;'>
                {$site_name}<br>
                Empowering Global Excellence
            </p>
            <div class='social-links'>
                <a href='" . getSetting('facebook_url', '#') . "'>Facebook</a> •
                <a href='" . getSetting('twitter_url', '#') . "'>Twitter</a> •
                <a href='" . getSetting('linkedin_url', '#') . "'>LinkedIn</a> •
                <a href='" . getSetting('instagram_url', '#') . "'>Instagram</a>
            </div>
            <p style='margin: 20px 0 0; font-size: 12px; color: #ADB5BD;'>
                This email was sent in response to your application submitted through our website.<br>
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
// 6. RETURN RESPONSE
// ============================================

if ($is_ajax) {
    // AJAX response
    $response['success'] = true;
    $response['message'] = 'Application submitted successfully!';
    $response['application_id'] = $application_id;
    echo json_encode($response);
    exit;
} else {
    // Standard form submission
    $_SESSION['application_success'] = 'Your application has been submitted successfully! We will contact you within 5-7 business days.';
    $_SESSION['application_id'] = $application_id;
    redirect(SITE_URL . '/application-success.php?id=' . $application_id);
}
?>