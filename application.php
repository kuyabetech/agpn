<?php
// ============================================
// PROGRAM APPLICATION FORM
// For: Digital Skills, Study Abroad, Business Growth, Sponsorship
// Features: Multi-step form, File upload, Email notifications
// ============================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Apply for Program - AGPN';
$meta_description = 'Apply for AGPN programs including Digital Skills Training, Study Abroad, Business Growth Services, and Sponsorship opportunities.';
$body_class = 'application-page';

// Get programs from database
try {
    $programs = db()->query("
        SELECT * FROM arms 
        WHERE status = 'active' 
        ORDER BY display_order ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching programs: " . $e->getMessage());
    $programs = [];
}

// Get selected program from URL
$selected_program = isset($_GET['program']) ? sanitize($_GET['program']) : '';

include 'includes/header.php';
?>

<style>
/* ============================================
   APPLICATION FORM STYLES
============================================ */

.application-hero {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: var(--spacing-16) 0;
    position: relative;
    color: var(--white);
    text-align: center;
}

.application-hero h1 {
    color: var(--white);
    font-size: clamp(2rem, 5vw, 3rem);
    margin-bottom: var(--spacing-4);
}

.application-hero p {
    color: rgba(255,255,255,0.9);
    font-size: var(--font-size-lg);
    max-width: 700px;
    margin: 0 auto;
}

.application-container {
    max-width: 900px;
    margin: -80px auto var(--spacing-12);
    position: relative;
    z-index: 10;
}

.application-card {
    background: var(--white);
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow-xl);
    padding: var(--spacing-8);
    border: 1px solid var(--gray-200);
}

/* Progress Steps */
.progress-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--spacing-8);
    position: relative;
}

.progress-steps::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--gray-200);
    z-index: 1;
}

.progress-step {
    position: relative;
    z-index: 2;
    background: var(--white);
    padding: 0 var(--spacing-2);
    text-align: center;
    flex: 1;
}

.step-number {
    width: 48px;
    height: 48px;
    background: var(--white);
    border: 2px solid var(--gray-300);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--gray-600);
    margin: 0 auto var(--spacing-2);
    transition: all var(--transition);
}

.step-label {
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--gray-600);
    transition: all var(--transition);
}

.progress-step.active .step-number {
    background: var(--gold);
    border-color: var(--gold);
    color: var(--navy);
}

.progress-step.active .step-label {
    color: var(--navy);
    font-weight: 700;
}

.progress-step.completed .step-number {
    background: var(--success);
    border-color: var(--success);
    color: var(--white);
}

/* Form Steps */
.form-step {
    display: none;
}

.form-step.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

/* Program Cards */
.program-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--spacing-4);
    margin-top: var(--spacing-4);
}

.program-card {
    background: var(--white);
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-5);
    cursor: pointer;
    transition: all var(--transition);
    position: relative;
}

.program-card:hover {
    border-color: var(--gold);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.program-card.selected {
    border-color: var(--gold);
    background: rgba(255,184,28,0.05);
}

.program-card .program-icon {
    font-size: 2rem;
    color: var(--gold);
    margin-bottom: var(--spacing-3);
}

.program-card h4 {
    margin-bottom: var(--spacing-2);
    color: var(--navy);
}

.program-card p {
    color: var(--gray-600);
    font-size: var(--font-size-sm);
    margin-bottom: 0;
}

.program-card .check-icon {
    position: absolute;
    top: var(--spacing-3);
    right: var(--spacing-3);
    color: var(--gold);
    font-size: var(--font-size-lg);
    display: none;
}

.program-card.selected .check-icon {
    display: block;
}

/* File Upload */
.file-upload-area {
    border: 2px dashed var(--gray-300);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-8);
    text-align: center;
    background: var(--gray-100);
    cursor: pointer;
    transition: all var(--transition);
    margin-bottom: var(--spacing-4);
}

.file-upload-area:hover {
    border-color: var(--gold);
    background: rgba(255,184,28,0.05);
}

.file-upload-area i {
    font-size: 3rem;
    color: var(--gold);
    margin-bottom: var(--spacing-3);
}

.file-upload-area p {
    color: var(--gray-600);
    margin-bottom: var(--spacing-2);
}

.file-upload-area small {
    color: var(--gray-500);
}

.file-info {
    background: var(--gray-100);
    border-radius: var(--border-radius-md);
    padding: var(--spacing-4);
    margin-top: var(--spacing-4);
    display: none;
}

.file-info.active {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.file-name {
    font-weight: 600;
    color: var(--navy);
}

.file-size {
    font-size: var(--font-size-sm);
    color: var(--gray-600);
}

.file-remove {
    color: var(--danger);
    cursor: pointer;
    padding: var(--spacing-2);
}

.file-remove:hover {
    color: var(--danger-dark);
}

/* Form Navigation */
.form-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: var(--spacing-8);
    padding-top: var(--spacing-6);
    border-top: 1px solid var(--gray-200);
}

/* Success Modal */
.success-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.success-modal.active {
    display: flex;
}

.success-content {
    background: var(--white);
    border-radius: var(--border-radius-xl);
    padding: var(--spacing-8);
    max-width: 500px;
    width: 90%;
    text-align: center;
    animation: slideInUp 0.5s ease;
}

.success-icon {
    width: 80px;
    height: 80px;
    background: var(--success);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto var(--spacing-5);
}

.success-content h2 {
    color: var(--navy);
    margin-bottom: var(--spacing-3);
}

.success-content p {
    color: var(--gray-600);
    margin-bottom: var(--spacing-6);
}

/* Responsive */
@media (max-width: 768px) {
    .application-container {
        margin-top: -40px;
        padding: 0 var(--spacing-4);
    }
    
    .application-card {
        padding: var(--spacing-5);
    }
    
    .progress-steps {
        flex-direction: column;
        gap: var(--spacing-4);
    }
    
    .progress-steps::before {
        display: none;
    }
    
    .progress-step {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        text-align: left;
    }
    
    .step-number {
        margin: 0;
    }
    
    .program-grid {
        grid-template-columns: 1fr;
    }
    
    .form-navigation {
        flex-direction: column;
        gap: var(--spacing-3);
    }
    
    .form-navigation .btn {
        width: 100%;
    }
}
</style>

<!-- Hero Section -->
<section class="application-hero">
    <div class="container">
        <h1>Apply for AGPN Programs</h1>
        <p>Take the next step in your journey with our comprehensive programs designed to empower your future.</p>
    </div>
</section>

<!-- Application Form -->
<div class="application-container">
    <div class="application-card">
        <form id="applicationForm" action="forms/application-handler.php" method="POST" enctype="multipart/form-data">
            
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Program</div>
                </div>
                <div class="progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="progress-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Documents</div>
                </div>
                <div class="progress-step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Review</div>
                </div>
            </div>
            
            <!-- ========================================
                 STEP 1: SELECT PROGRAM
            ======================================== -->
            <div class="form-step active" id="step1">
                <h3 style="margin-bottom: var(--spacing-5);">Select Your Program</h3>
                <p style="color: var(--gray-600); margin-bottom: var(--spacing-5);">Choose the program you wish to apply for:</p>
                
                <div class="program-grid">
                    <?php foreach ($programs as $program): ?>
                        <div class="program-card" data-program="<?php echo $program['arm_slug']; ?>">
                            <div class="program-icon">
                                <i class="fas <?php echo $program['icon_class'] ?: 'fa-cube'; ?>"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($program['arm_name']); ?></h4>
                            <p><?php echo htmlspecialchars(truncateText($program['short_description'] ?: 'Comprehensive training and development program.', 80)); ?></p>
                            <div class="check-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <input type="hidden" name="program" id="selectedProgram" value="<?php echo htmlspecialchars($selected_program); ?>" required>
                
                <div class="form-navigation">
                    <div></div>
                    <button type="button" class="btn btn-primary" id="toStep2">Continue <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- ========================================
                 STEP 2: PERSONAL INFORMATION
            ======================================== -->
            <div class="form-step" id="step2">
                <h3 style="margin-bottom: var(--spacing-5);">Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span style="color: var(--danger);">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number <span style="color: var(--danger);">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="+234 800 123 4567" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="country">Country of Residence <span style="color: var(--danger);">*</span></label>
                        <select id="country" name="country" class="form-control" required>
                            <option value="">Select your country</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="Ghana">Ghana</option>
                            <option value="Kenya">Kenya</option>
                            <option value="South Africa">South Africa</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Tanzania">Tanzania</option>
                            <option value="Ethiopia">Ethiopia</option>
                            <option value="Egypt">Egypt</option>
                            <option value="Morocco">Morocco</option>
                            <option value="United States">United States</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Canada">Canada</option>
                            <option value="Germany">Germany</option>
                            <option value="France">France</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-control">
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer_not">Prefer not to say</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2" placeholder="Your full address"></textarea>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="btn btn-outline" id="backToStep1"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn btn-primary" id="toStep3">Continue <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- ========================================
                 STEP 3: DOCUMENTS & COVER LETTER
            ======================================== -->
            <div class="form-step" id="step3">
                <h3 style="margin-bottom: var(--spacing-5);">Upload Documents</h3>
                
                <div class="form-group">
                    <label for="cover_letter">Cover Letter / Statement of Purpose</label>
                    <textarea id="cover_letter" name="cover_letter" class="form-control" rows="6" placeholder="Tell us why you're interested in this program and what you hope to achieve..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>CV / Resume <span style="color: var(--danger);">*</span></label>
                    <div class="file-upload-area" id="cvUploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag and drop your CV here, or click to browse</p>
                        <small>Supported formats: PDF, DOC, DOCX (Max 5MB)</small>
                    </div>
                    <input type="file" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx" style="display: none;" required>
                    
                    <div class="file-info" id="cvFileInfo">
                        <div>
                            <div class="file-name" id="cvFileName"></div>
                            <div class="file-size" id="cvFileSize"></div>
                        </div>
                        <div class="file-remove" onclick="removeFile('cv')">
                            <i class="fas fa-times-circle"></i> Remove
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Additional Documents (Optional)</label>
                    <div class="file-upload-area" id="additionalUploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Upload certificates, transcripts, or other supporting documents</p>
                        <small>Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB total)</small>
                    </div>
                    <input type="file" id="additional_files" name="additional_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                    
                    <div id="additionalFilesList"></div>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="btn btn-outline" id="backToStep2"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn btn-primary" id="toStep4">Review Application <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- ========================================
                 STEP 4: REVIEW & SUBMIT
            ======================================== -->
            <div class="form-step" id="step4">
                <h3 style="margin-bottom: var(--spacing-5);">Review Your Application</h3>
                
                <div style="background: var(--gray-100); border-radius: var(--border-radius-lg); padding: var(--spacing-6); margin-bottom: var(--spacing-6);">
                    <h4 style="display: flex; align-items: center; gap: 10px; margin-bottom: var(--spacing-4);">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                        Program Details
                    </h4>
                    <div id="reviewProgram" style="margin-bottom: var(--spacing-5);"></div>
                    
                    <h4 style="display: flex; align-items: center; gap: 10px; margin-bottom: var(--spacing-4);">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </h4>
                    <div id="reviewPersonal" style="margin-bottom: var(--spacing-5);"></div>
                    
                    <h4 style="display: flex; align-items: center; gap: 10px; margin-bottom: var(--spacing-4);">
                        <i class="fas fa-file-alt"></i>
                        Documents
                    </h4>
                    <div id="reviewDocuments"></div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="agree_terms" required>
                        <span>I confirm that the information provided is accurate and I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.</span>
                    </label>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="btn btn-outline" id="backToStep3"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="submit" class="btn btn-primary" id="submitApplication">
                        <span>Submit Application</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="success-modal">
    <div class="success-content">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2>Application Submitted!</h2>
        <p>Thank you for applying to AGPN. We have received your application and will review it shortly. You will receive a confirmation email with further details.</p>
        <div style="display: flex; gap: var(--spacing-3); justify-content: center;">
            <a href="index.php" class="btn btn-primary">Return Home</a>
            <a href="programs.php" class="btn btn-outline">View More Programs</a>
        </div>
    </div>
</div>

<script>
// ============================================
// APPLICATION FORM JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ========================================
    // STEP NAVIGATION
    // ========================================
    let currentStep = 1;
    const totalSteps = 4;
    const steps = document.querySelectorAll('.form-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    
    function showStep(step) {
        // Hide all steps
        steps.forEach(s => s.classList.remove('active'));
        
        // Show current step
        document.getElementById(`step${step}`).classList.add('active');
        
        // Update progress indicators
        progressSteps.forEach((ps, index) => {
            const stepNum = index + 1;
            ps.classList.remove('active', 'completed');
            
            if (stepNum === step) {
                ps.classList.add('active');
            } else if (stepNum < step) {
                ps.classList.add('completed');
            }
        });
        
        currentStep = step;
        
        // Update review if on step 4
        if (step === 4) {
            updateReview();
        }
    }
    
    // Step navigation buttons
    document.getElementById('toStep2').addEventListener('click', function() {
        if (validateStep1()) {
            showStep(2);
        }
    });
    
    document.getElementById('backToStep1').addEventListener('click', function() {
        showStep(1);
    });
    
    document.getElementById('toStep3').addEventListener('click', function() {
        if (validateStep2()) {
            showStep(3);
        }
    });
    
    document.getElementById('backToStep2').addEventListener('click', function() {
        showStep(2);
    });
    
    document.getElementById('toStep4').addEventListener('click', function() {
        if (validateStep3()) {
            showStep(4);
        }
    });
    
    document.getElementById('backToStep3').addEventListener('click', function() {
        showStep(3);
    });
    
    // ========================================
    // PROGRAM SELECTION
    // ========================================
    const programCards = document.querySelectorAll('.program-card');
    const selectedProgramInput = document.getElementById('selectedProgram');
    
    // Pre-select program from URL if exists
    const urlProgram = '<?php echo $selected_program; ?>';
    if (urlProgram) {
        programCards.forEach(card => {
            if (card.dataset.program === urlProgram) {
                card.classList.add('selected');
                selectedProgramInput.value = urlProgram;
            }
        });
    }
    
    programCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            programCards.forEach(c => c.classList.remove('selected'));
            
            // Add selected class to clicked card
            this.classList.add('selected');
            
            // Update hidden input
            selectedProgramInput.value = this.dataset.program;
        });
    });
    
    function validateStep1() {
        if (!selectedProgramInput.value) {
            alert('Please select a program to continue.');
            return false;
        }
        return true;
    }
    
    // ========================================
    // VALIDATION
    // ========================================
    function validateStep2() {
        const fullName = document.getElementById('full_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const country = document.getElementById('country').value;
        
        if (!fullName) {
            alert('Please enter your full name.');
            return false;
        }
        
        if (!email) {
            alert('Please enter your email address.');
            return false;
        }
        
        if (!isValidEmail(email)) {
            alert('Please enter a valid email address.');
            return false;
        }
        
        if (!phone) {
            alert('Please enter your phone number.');
            return false;
        }
        
        if (!country) {
            alert('Please select your country of residence.');
            return false;
        }
        
        return true;
    }
    
    function validateStep3() {
        const cvFile = document.getElementById('cv_file').files[0];
        
        if (!cvFile) {
            alert('Please upload your CV/Resume.');
            return false;
        }
        
        return true;
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }
    
    // ========================================
    // FILE UPLOAD HANDLING
    // ========================================
    
    // CV Upload
    const cvUploadArea = document.getElementById('cvUploadArea');
    const cvFileInput = document.getElementById('cv_file');
    const cvFileInfo = document.getElementById('cvFileInfo');
    const cvFileName = document.getElementById('cvFileName');
    const cvFileSize = document.getElementById('cvFileSize');
    
    cvUploadArea.addEventListener('click', function() {
        cvFileInput.click();
    });
    
    cvUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--gold)';
        this.style.background = 'rgba(255,184,28,0.05)';
    });
    
    cvUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--gray-300)';
        this.style.background = 'var(--gray-100)';
    });
    
    cvUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = 'var(--gray-300)';
        this.style.background = 'var(--gray-100)';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            cvFileInput.files = files;
            updateFileInfo('cv', files[0]);
        }
    });
    
    cvFileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            updateFileInfo('cv', this.files[0]);
        }
    });
    
    // Additional files upload
    const additionalUploadArea = document.getElementById('additionalUploadArea');
    const additionalFileInput = document.getElementById('additional_files');
    const additionalFilesList = document.getElementById('additionalFilesList');
    
    additionalUploadArea.addEventListener('click', function() {
        additionalFileInput.click();
    });
    
    additionalFileInput.addEventListener('change', function() {
        additionalFilesList.innerHTML = '';
        
        Array.from(this.files).forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-info active';
            fileItem.style.marginTop = '10px';
            fileItem.innerHTML = `
                <div>
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                </div>
                <div class="file-remove" onclick="removeAdditionalFile(${index})">
                    <i class="fas fa-times-circle"></i> Remove
                </div>
            `;
            additionalFilesList.appendChild(fileItem);
        });
    });
    
    // ========================================
    // REVIEW SECTION
    // ========================================
    function updateReview() {
        // Program review
        const selectedProgram = document.querySelector('.program-card.selected');
        const programName = selectedProgram ? selectedProgram.querySelector('h4').textContent : 'Not selected';
        document.getElementById('reviewProgram').innerHTML = `
            <p><strong>Program:</strong> ${programName}</p>
        `;
        
        // Personal info review
        const fullName = document.getElementById('full_name').value || 'Not provided';
        const email = document.getElementById('email').value || 'Not provided';
        const phone = document.getElementById('phone').value || 'Not provided';
        const country = document.getElementById('country').options[document.getElementById('country').selectedIndex]?.text || 'Not provided';
        
        document.getElementById('reviewPersonal').innerHTML = `
            <p><strong>Full Name:</strong> ${fullName}</p>
            <p><strong>Email:</strong> ${email}</p>
            <p><strong>Phone:</strong> ${phone}</p>
            <p><strong>Country:</strong> ${country}</p>
        `;
        
        // Documents review
        const cvFile = document.getElementById('cv_file').files[0];
        const cvName = cvFile ? cvFile.name : 'Not uploaded';
        const additionalCount = document.getElementById('additional_files').files.length;
        
        document.getElementById('reviewDocuments').innerHTML = `
            <p><strong>CV/Resume:</strong> ${cvName}</p>
            <p><strong>Additional Documents:</strong> ${additionalCount} file(s)</p>
        `;
    }
    
    // ========================================
    // FORM SUBMISSION
    // ========================================
    document.getElementById('applicationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!document.getElementById('agree_terms').checked) {
            alert('Please agree to the Terms and Conditions.');
            return;
        }
        
        const formData = new FormData(this);
        
        // Disable submit button
        const submitBtn = document.getElementById('submitApplication');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        // AJAX submission
        fetch('forms/application-handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success modal
                document.getElementById('successModal').classList.add('active');
                
                // Reset form
                this.reset();
                
                // Reset file inputs
                cvFileInput.value = '';
                cvFileInfo.classList.remove('active');
                additionalFileInput.value = '';
                additionalFilesList.innerHTML = '';
                
                // Remove selected class from program cards
                programCards.forEach(c => c.classList.remove('selected'));
                selectedProgramInput.value = '';
                
                // Go back to step 1
                showStep(1);
            } else {
                alert(data.message || 'An error occurred. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
    
    // ========================================
    // UTILITY FUNCTIONS
    // ========================================
    window.removeFile = function(type) {
        if (type === 'cv') {
            document.getElementById('cv_file').value = '';
            document.getElementById('cvFileInfo').classList.remove('active');
        }
    };
    
    window.removeAdditionalFile = function(index) {
        const dt = new DataTransfer();
        const files = document.getElementById('additional_files').files;
        
        for (let i = 0; i < files.length; i++) {
            if (i !== index) {
                dt.items.add(files[i]);
            }
        }
        
        document.getElementById('additional_files').files = dt.files;
        document.getElementById('additionalFilesList').innerHTML = '';
        
        Array.from(dt.files).forEach((file, i) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-info active';
            fileItem.style.marginTop = '10px';
            fileItem.innerHTML = `
                <div>
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                </div>
                <div class="file-remove" onclick="removeAdditionalFile(${i})">
                    <i class="fas fa-times-circle"></i> Remove
                </div>
            `;
            additionalFilesList.appendChild(fileItem);
        });
    };
    
    function updateFileInfo(type, file) {
        if (type === 'cv') {
            cvFileName.textContent = file.name;
            cvFileSize.textContent = formatFileSize(file.size);
            cvFileInfo.classList.add('active');
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('successModal');
    if (e.target === modal) {
        modal.classList.remove('active');
    }
});
</script>

<?php include 'includes/footer.php'; ?>