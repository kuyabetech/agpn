<?php
// ============================================
// PAGE EDITOR - ADD/EDIT PAGES
// ============================================

// Define IN_ADMIN before including any files
define('IN_ADMIN', true);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
Auth::requireAuth();

// Get current user
$user = Auth::user();

// Get page ID from URL
$page_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_new = $page_id === 0;
$page = null;

// Get page if editing
if (!$is_new) {
    try {
        $stmt = db()->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$page) {
            $_SESSION['error'] = 'Page not found';
            redirect('pages.php');
        }
    } catch (PDOException $e) {
        error_log("Error fetching page: " . $e->getMessage());
        $_SESSION['error'] = 'Error loading page';
        redirect('pages.php');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_name = sanitize($_POST['page_name']);
    $page_title = sanitize($_POST['page_title']);
    $page_content = $_POST['page_content']; // Don't sanitize - contains HTML
    $meta_description = sanitize($_POST['meta_description']);
    $meta_keywords = sanitize($_POST['meta_keywords']);
    $status = sanitize($_POST['status']);
    $template = sanitize($_POST['template'] ?? 'default');
    $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
    $menu_order = (int)sanitize($_POST['menu_order'] ?? 0);
    
    // Validate
    if (empty($page_name) || empty($page_title)) {
        $error = 'Page name and title are required';
    } else {
        try {
            $db = db();
            
            // Handle feature image upload
            $feature_image = $page['feature_image'] ?? null;
            if (isset($_FILES['feature_image']) && $_FILES['feature_image']['error'] === 0) {
                $upload = uploadImage($_FILES['feature_image'], 'pages');
                if ($upload['success']) {
                    // Delete old feature image if exists
                    if ($feature_image && file_exists('../' . $feature_image)) {
                        unlink('../' . $feature_image);
                    }
                    $feature_image = $upload['path'];
                } else {
                    $error = $upload['error'];
                }
            }
            
            // Remove feature image if requested
            if (isset($_POST['remove_feature_image']) && $_POST['remove_feature_image'] == '1') {
                if ($feature_image && file_exists('../' . $feature_image)) {
                    unlink('../' . $feature_image);
                }
                $feature_image = null;
            }
            
            if (empty($error)) {
                if ($is_new) {
                    // Check if page name exists
                    $check = $db->prepare("SELECT id FROM pages WHERE page_name = ?");
                    $check->execute([$page_name]);
                    if ($check->fetch()) {
                        $error = 'A page with this name already exists';
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO pages (
                                page_name, page_title, page_content, 
                                meta_description, meta_keywords, 
                                feature_image, template, show_in_menu, menu_order,
                                status, last_edited_by, created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $page_name, $page_title, $page_content,
                            $meta_description, $meta_keywords,
                            $feature_image, $template, $show_in_menu, $menu_order,
                            $status, $_SESSION['admin_user_id'] ?? $user['id']
                        ]);
                        
                        $_SESSION['success'] = 'Page created successfully';
                        redirect('pages.php');
                    }
                } else {
                    $stmt = $db->prepare("
                        UPDATE pages 
                        SET page_title = ?, 
                            page_content = ?, 
                            meta_description = ?, 
                            meta_keywords = ?, 
                            feature_image = ?,
                            template = ?,
                            show_in_menu = ?,
                            menu_order = ?,
                            status = ?, 
                            last_edited_by = ?, 
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $page_title, $page_content,
                        $meta_description, $meta_keywords,
                        $feature_image, $template, $show_in_menu, $menu_order,
                        $status, $_SESSION['admin_user_id'] ?? $user['id'],
                        $page_id
                    ]);
                    
                    $_SESSION['success'] = 'Page updated successfully';
                    redirect('pages.php');
                }
            }
        } catch (PDOException $e) {
            error_log("Error saving page: " . $e->getMessage());
            $error = 'Error saving page. Please try again.';
        }
    }
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? $error ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = $is_new ? 'Add New Page' : 'Edit Page: ' . htmlspecialchars($page['page_title'] ?? '');

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => 'pages.php', 'title' => 'Pages'],
    ['url' => '#', 'title' => $is_new ? 'Add New Page' : htmlspecialchars($page['page_title'] ?? 'Edit Page')]
];

// Page specific CSS
$page_css = ['page-editor.css'];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    .page-editor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .feature-image-container {
        border: 2px dashed var(--gray-300);
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        background: var(--gray-100);
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 20px;
        position: relative;
    }
    
    .feature-image-container:hover {
        border-color: var(--gold);
        background: rgba(255,184,28,0.05);
    }
    
    .feature-image-preview {
        max-width: 100%;
        max-height: 300px;
        object-fit: contain;
        margin-bottom: 15px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .feature-image-placeholder {
        padding: 20px;
    }
    
    .feature-image-placeholder i {
        font-size: 48px;
        color: var(--gold);
        margin-bottom: 15px;
    }
    
    .feature-image-actions {
        position: absolute;
        top: 15px;
        right: 15px;
        display: flex;
        gap: 10px;
        z-index: 10;
    }
    
    .btn-feature-action {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .btn-feature-action:hover {
        background: var(--gold);
        color: var(--navy);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .btn-feature-action.delete:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    .meta-info-panel {
        background: var(--gray-100);
        border-radius: 16px;
        padding: 25px;
        margin-top: 30px;
    }
    
    .template-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }
    
    .template-option {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .template-option:hover {
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    
    .template-option.active {
        border-color: var(--gold);
        background: rgba(255,184,28,0.05);
    }
    
    .template-option i {
        font-size: 32px;
        color: var(--navy);
        margin-bottom: 10px;
    }
    
    .template-option span {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--gray-900);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .page-editor-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<!-- Toast Notifications -->
<?php if ($success): ?>
    <div class="toast-notification animate__animated animate__fadeInRight">
        <div style="background: #D4EDDA; color: #155724; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #28a745;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="toast-notification animate__animated animate__fadeInRight">
        <div style="background: #F8D7DA; color: #721C24; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #dc3545;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    </div>
<?php endif; ?>

<!-- Page Editor Header -->
<div class="page-editor-header animate__animated animate__fadeIn">
    <div>
        <h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
            <i class="fas fa-<?php echo $is_new ? 'plus-circle' : 'edit'; ?>" style="color: var(--gold);"></i>
            <?php echo $is_new ? 'Add New Page' : 'Edit Page'; ?>
        </h1>
        <?php if (!$is_new): ?>
            <div style="color: var(--gray-600);">
                <i class="fas fa-code"></i> Page slug: <code><?php echo htmlspecialchars($page['page_name']); ?>.php</code>
                <?php if (!in_array($page['page_name'], ['home', 'about', 'contact'])): ?>
                    <a href="?delete=<?php echo $page['id']; ?>" 
                       class="btn-icon delete" 
                       style="margin-left: 15px; color: #dc3545;"
                       onclick="return confirm('Are you sure you want to delete this page?')">
                        <i class="fas fa-trash"></i> Delete Page
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div>
        <?php if (!$is_new): ?>
            <a href="<?php echo SITE_URL; ?>/<?php echo $page['page_name'] == 'home' ? '' : $page['page_name'] . '.php'; ?>" 
               target="_blank" 
               class="btn-secondary">
                <i class="fas fa-external-link-alt"></i> View Page
            </a>
        <?php endif; ?>
        <a href="pages.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Pages
        </a>
    </div>
</div>

<!-- Page Editor Form -->
<div class="card animate__animated animate__fadeIn">
    <div class="card-header">
        <h3><i class="fas fa-file-alt" style="color: var(--gold);"></i> Page Details</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Feature Image -->
            <div class="form-group">
                <label>Feature Image</label>
                <div class="feature-image-container" onclick="document.getElementById('feature_image').click()">
                    <input type="file" name="feature_image" id="feature_image" accept="image/*" style="display: none;" onchange="previewFeatureImage(this)">
                    <input type="hidden" name="remove_feature_image" id="remove_feature_image" value="0">
                    
                    <?php if (!$is_new && !empty($page['feature_image'])): ?>
                        <div class="feature-image-actions">
                            <button type="button" class="btn-feature-action" onclick="event.stopPropagation(); document.getElementById('feature_image').click()" title="Change Image">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn-feature-action delete" onclick="event.stopPropagation(); removeFeatureImage()" title="Remove Image">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <img id="feature_image_preview" src="<?php echo SITE_URL . '/' . $page['feature_image']; ?>" class="feature-image-preview">
                        <p style="color: var(--gray-600); margin-top: 10px;">
                            <i class="fas fa-image"></i> Current feature image
                        </p>
                    <?php else: ?>
                        <div id="feature_image_placeholder" class="feature-image-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h4 style="margin-bottom: 10px; color: var(--navy);">Upload Feature Image</h4>
                            <p style="color: var(--gray-600); margin-bottom: 5px;">Click to browse or drag and drop</p>
                            <p style="font-size: 12px; color: var(--gray-500);">Recommended size: 1200x630px • JPG, PNG, WEBP up to 5MB</p>
                        </div>
                        <img id="feature_image_preview" class="feature-image-preview" style="display: none;">
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Basic Information -->
            <div class="form-row">
                <div class="form-group">
                    <label>Page Name <span style="color: #dc3545;">*</span></label>
                    <input type="text" name="page_name" class="form-control" 
                           value="<?php echo htmlspecialchars($page['page_name'] ?? ''); ?>" 
                           <?php echo !$is_new ? 'readonly' : ''; ?> 
                           placeholder="e.g., about-us, services, contact"
                           required>
                    <small style="color: var(--gray-600);">Unique identifier for URL. Use lowercase letters and hyphens.</small>
                </div>
                
                <div class="form-group">
                    <label>Page Title <span style="color: #dc3545;">*</span></label>
                    <input type="text" name="page_title" class="form-control" 
                           value="<?php echo htmlspecialchars($page['page_title'] ?? ''); ?>" 
                           placeholder="e.g., About Us, Our Services"
                           required>
                    <small style="color: var(--gray-600);">Displayed in browser tab and search results.</small>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="form-group">
                <label>Page Content</label>
                <textarea name="page_content" id="editor" class="form-control" rows="20"><?php echo htmlspecialchars($page['page_content'] ?? ''); ?></textarea>
                <small style="color: var(--gray-600);">Use the editor to format your content.</small>
            </div>
            
            <!-- Page Settings -->
            <div class="meta-info-panel">
                <h4 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-cog" style="color: var(--gold);"></i>
                    Page Settings
                </h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Page Template</label>
                        <select name="template" class="form-control">
                            <option value="default" <?php echo ($page['template'] ?? 'default') == 'default' ? 'selected' : ''; ?>>Default Template</option>
                            <option value="full-width" <?php echo ($page['template'] ?? '') == 'full-width' ? 'selected' : ''; ?>>Full Width Template</option>
                            <option value="sidebar-left" <?php echo ($page['template'] ?? '') == 'sidebar-left' ? 'selected' : ''; ?>>Left Sidebar</option>
                            <option value="sidebar-right" <?php echo ($page['template'] ?? '') == 'sidebar-right' ? 'selected' : ''; ?>>Right Sidebar</option>
                            <option value="landing" <?php echo ($page['template'] ?? '') == 'landing' ? 'selected' : ''; ?>>Landing Page</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Page Status</label>
                        <select name="status" class="form-control">
                            <option value="published" <?php echo ($page['status'] ?? '') == 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo ($page['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="show_in_menu" value="1" <?php echo ($page['show_in_menu'] ?? 0) == 1 ? 'checked' : ''; ?>>
                            Show in Navigation Menu
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Menu Order</label>
                        <input type="number" name="menu_order" class="form-control" 
                               value="<?php echo htmlspecialchars($page['menu_order'] ?? 0); ?>" 
                               min="0" step="1">
                        <small style="color: var(--gray-600);">Lower numbers appear first.</small>
                    </div>
                </div>
            </div>
            
            <!-- SEO Meta Information -->
            <div class="meta-info-panel">
                <h4 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-line" style="color: var(--gold);"></i>
                    SEO Meta Information
                </h4>
                
                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea name="meta_description" class="form-control" rows="3" 
                              placeholder="Brief description of the page for search engines..."
                              onkeyup="updateMetaCounter(this)"><?php echo htmlspecialchars($page['meta_description'] ?? ''); ?></textarea>
                    <small id="meta_counter" style="color: var(--gray-600);">0/160 characters</small>
                </div>
                
                <div class="form-group">
                    <label>Meta Keywords</label>
                    <input type="text" name="meta_keywords" class="form-control" 
                           value="<?php echo htmlspecialchars($page['meta_keywords'] ?? ''); ?>" 
                           placeholder="e.g., about, company, history, mission">
                    <small style="color: var(--gray-600);">Comma-separated keywords for SEO.</small>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-group" style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-primary" style="flex: 1; padding: 14px;">
                    <i class="fas fa-save"></i> <?php echo $is_new ? 'Create Page' : 'Update Page'; ?>
                </button>
                
                <?php if (!$is_new && !in_array($page['page_name'], ['home', 'about', 'contact'])): ?>
                    <a href="?delete=<?php echo $page['id']; ?>" 
                       class="btn-secondary" 
                       style="background: #dc3545; color: white; border-color: #dc3545; padding: 14px 30px;"
                       onclick="return confirm('Are you sure you want to delete this page? This action cannot be undone.')">
                        <i class="fas fa-trash"></i> Delete Page
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>

<script>
    // ============================================
    // PAGE EDITOR - JAVASCRIPT
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        // ============================================
        // INITIALIZE CKEDITOR
        // ============================================
        
        CKEDITOR.replace('editor', {
            height: 500,
            removeButtons: 'Save,NewPage,Print,Templates,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Scayt,About',
            format_tags: 'p;h1;h2;h3;h4;h5;h6;pre;address;div',
            toolbarGroups: [
                { name: 'document', groups: ['mode', 'document', 'doctools'] },
                { name: 'clipboard', groups: ['clipboard', 'undo'] },
                { name: 'editing', groups: ['find', 'selection', 'spellchecker'] },
                { name: 'forms' },
                '/',
                { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
                { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align', 'bidi'] },
                { name: 'links' },
                { name: 'insert' },
                '/',
                { name: 'styles' },
                { name: 'colors' },
                { name: 'tools' }
            ]
        });
        
        // ============================================
        // FEATURE IMAGE HANDLING
        // ============================================
        
        window.previewFeatureImage = function(input) {
            const preview = document.getElementById('feature_image_preview');
            const placeholder = document.getElementById('feature_image_placeholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                    
                    // Reset remove flag
                    document.getElementById('remove_feature_image').value = '0';
                };
                reader.readAsDataURL(input.files[0]);
            }
        };
        
        window.removeFeatureImage = function() {
            const preview = document.getElementById('feature_image_preview');
            const placeholder = document.getElementById('feature_image_placeholder');
            const fileInput = document.getElementById('feature_image');
            const removeFlag = document.getElementById('remove_feature_image');
            
            preview.src = '';
            preview.style.display = 'none';
            if (placeholder) {
                placeholder.style.display = 'block';
            }
            
            // Clear file input
            fileInput.value = '';
            
            // Set remove flag
            removeFlag.value = '1';
        };
        
        // ============================================
        // META DESCRIPTION COUNTER
        // ============================================
        
        window.updateMetaCounter = function(textarea) {
            const counter = document.getElementById('meta_counter');
            if (counter) {
                const length = textarea.value.length;
                counter.innerHTML = `${length}/160 characters`;
                
                if (length > 160) {
                    counter.style.color = '#dc3545';
                } else {
                    counter.style.color = 'var(--gray-600)';
                }
            }
        };
        
        // Initialize meta counter
        const metaDescription = document.querySelector('textarea[name="meta_description"]');
        if (metaDescription) {
            updateMetaCounter(metaDescription);
        }
        
        // ============================================
        // DRAG AND DROP FOR FEATURE IMAGE
        // ============================================
        
        const featureContainer = document.querySelector('.feature-image-container');
        
        if (featureContainer) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                featureContainer.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            featureContainer.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                const fileInput = document.getElementById('feature_image');
                
                if (files.length > 0) {
                    fileInput.files = files;
                    previewFeatureImage(fileInput);
                }
            }, false);
        }
        
        // ============================================
        // CONFIRM PAGE NAME CHANGE
        // ============================================
        
        const pageNameInput = document.querySelector('input[name="page_name"]');
        if (pageNameInput && pageNameInput.hasAttribute('readonly')) {
            pageNameInput.addEventListener('click', function(e) {
                alert('Page name cannot be changed after creation. Please create a new page if you need a different URL.');
            });
        }
        
        // ============================================
        // AUTO-GENERATE PAGE SLUG (for new pages)
        // ============================================
        
        if (<?php echo $is_new ? 'true' : 'false'; ?>) {
            const titleInput = document.querySelector('input[name="page_title"]');
            const slugInput = document.querySelector('input[name="page_name"]');
            
            if (titleInput && slugInput) {
                titleInput.addEventListener('blur', function() {
                    if (slugInput.value === '') {
                        // Create slug from title
                        let slug = this.value
                            .toLowerCase()
                            .replace(/[^\w\s-]/g, '')
                            .replace(/[\s_-]+/g, '-')
                            .replace(/^-+|-+$/g, '');
                        
                        slugInput.value = slug;
                    }
                });
            }
        }
        
    }); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>