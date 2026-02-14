<?php
// ============================================
// BLOG POST EDITOR - ADD/EDIT POSTS (FIXED)
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
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $post_id > 0;
$post = null;

// Get post if editing
if ($is_edit) {
    try {
        $stmt = db()->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            $_SESSION['error'] = 'Post not found';
            redirect('blog.php');
        }
    } catch (PDOException $e) {
        error_log("Error fetching post: " . $e->getMessage());
        $_SESSION['error'] = 'Error loading post';
        redirect('blog.php');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if (!$slug) {
        $slug = function_exists('createSlug') ? createSlug($title) : preg_replace('/[^a-z0-9-]+/i', '-', strtolower(trim($title)));
    }
    $content = $_POST['content'] ?? ''; // Don't sanitize - contains HTML
    $excerpt = trim($_POST['excerpt'] ?? '');
    $status = in_array($_POST['status'] ?? 'draft', ['draft','published']) ? $_POST['status'] : 'draft';
    $author = trim($_POST['author'] ?? '') ?: ($user['full_name'] ?? 'Admin');
    $meta_title = trim($_POST['meta_title'] ?? $title);
    $meta_description = trim($_POST['meta_description'] ?? $excerpt);
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');
    $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
    $published_date = null;
    
    // Set published date
    if ($status === 'published') {
        if ($is_edit && ($post['status'] ?? '') === 'draft') {
            $published_date = date('Y-m-d H:i:s');
        } elseif ($is_edit && !empty($post['published_date'])) {
            $published_date = $post['published_date'];
        } elseif (!$is_edit) {
            $published_date = date('Y-m-d H:i:s');
        }
    }
    
    // Handle featured image upload
    $featured_image = $post['featured_image'] ?? null;
    
    // Check if remove image is requested
    if (isset($_POST['remove_featured_image']) && $_POST['remove_featured_image'] == '1') {
        if ($featured_image && file_exists('../' . $featured_image)) {
            @unlink('../' . $featured_image);
        }
        $featured_image = null;
    }
    
    // Upload new featured image
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
        $upload = uploadImage($_FILES['featured_image'], 'blog');
        if ($upload['success']) {
            // Delete old image if exists
            if ($featured_image && file_exists('../' . $featured_image)) {
                @unlink('../' . $featured_image);
            }
            $featured_image = $upload['path'];
        } else {
            $_SESSION['error'] = $upload['error'] ?? 'Image upload failed';
        }
    }
    
    // Validate
    if (empty($title)) {
        $error = 'Post title is required';
    } elseif (empty($content)) {
        $error = 'Post content is required';
    } else {
        try {
            $db = db();
            
            // Check if slug exists (exclude current post)
            $check = $db->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
            $check->execute([$slug, $post_id]);
            
            if ($check->fetch()) {
                $slug = $slug . '-' . substr(md5(time()), 0, 8);
            }
            
            if ($is_edit) {
                $stmt = $db->prepare("
                    UPDATE blog_posts 
                    SET title = ?, 
                        slug = ?, 
                        content = ?, 
                        excerpt = ?, 
                        featured_image = ?, 
                        status = ?, 
                        author = ?, 
                        meta_title = ?,
                        meta_description = ?,
                        meta_keywords = ?,
                        allow_comments = ?,
                        published_date = ?, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $slug, $content, $excerpt, $featured_image, 
                    $status, $author, $meta_title, $meta_description, $meta_keywords,
                    $allow_comments, $published_date, $post_id
                ]);
                $_SESSION['success'] = 'Post updated successfully';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO blog_posts (
                        title, slug, content, excerpt, featured_image, 
                        status, author, meta_title, meta_description, meta_keywords,
                        allow_comments, published_date, created_at, updated_at, views
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)
                ");
                $stmt->execute([
                    $title, $slug, $content, $excerpt, $featured_image, 
                    $status, $author, $meta_title, $meta_description, $meta_keywords,
                    $allow_comments, $published_date
                ]);
                $_SESSION['success'] = 'Post created successfully';
            }
            
            // Reset unsaved changes flag
            echo '<script>formChanged = false;</script>';
            
            redirect('blog.php');
            
        } catch (PDOException $e) {
            error_log("Error saving post: " . $e->getMessage());
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Get session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? $error ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Set page title
$page_title = $is_edit ? 'Edit Post: ' . htmlspecialchars($post['title'] ?? '') : 'Add New Post';

// Breadcrumbs
$breadcrumbs = [
    ['url' => 'dashboard.php', 'title' => 'Dashboard'],
    ['url' => 'blog.php', 'title' => 'Blog Posts'],
    ['url' => '#', 'title' => $is_edit ? 'Edit Post' : 'Add New Post']
];

// Include admin header
require_once '../includes/admin-header.php';
?>

<!-- Page Specific Styles -->
<style>
    .post-editor {
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        border: 1px solid var(--gray-200);
    }
    
    .editor-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--gray-200);
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .slug-preview {
        background: var(--gray-100);
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 13px;
        color: var(--gray-600);
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .slug-preview i {
        color: var(--gold);
    }
    
    .slug-preview input {
        border: 1px solid var(--gray-200);
        background: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 13px;
        min-width: 200px;
    }
    
    .slug-preview input:focus {
        outline: none;
        border-color: var(--gold);
    }
    
    .featured-image-container {
        border: 2px dashed var(--gray-300);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        background: var(--gray-100);
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 15px;
        position: relative;
    }
    
    .featured-image-container:hover {
        border-color: var(--gold);
        background: rgba(255,184,28,0.05);
    }
    
    .featured-image-preview {
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    
    .image-preview-container {
        position: relative;
        display: inline-block;
        width: 100%;
    }
    
    .remove-image-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: white;
        color: #dc3545;
        border: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .remove-image-btn:hover {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    .character-count {
        font-size: 12px;
        color: var(--gray-600);
        margin-top: 5px;
        text-align: right;
    }
    
    .character-count.warning {
        color: #ffc107;
    }
    
    .character-count.danger {
        color: #dc3545;
    }
    
    .seo-preview {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid var(--gray-200);
    }
    
    .seo-preview-title {
        color: #1a0dab;
        font-size: 18px;
        line-height: 1.3;
        margin-bottom: 5px;
        word-wrap: break-word;
    }
    
    .seo-preview-url {
        color: #006621;
        font-size: 14px;
        margin-bottom: 5px;
        word-wrap: break-word;
    }
    
    .seo-preview-desc {
        color: #545454;
        font-size: 13px;
        line-height: 1.4;
        word-wrap: break-word;
    }
    
    .meta-fields {
        background: var(--gray-100);
        padding: 20px;
        border-radius: 12px;
        margin-top: 20px;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        border: 1px solid var(--gray-200);
        overflow: hidden;
        margin-bottom: 25px;
    }
    
    .card-header {
        padding: 18px 25px;
        background: var(--gray-100);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--navy);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .card-body {
        padding: 25px;
    }
    
    .grid-2 {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
    }
    
    .form-control-lg {
        font-size: 20px;
        padding: 15px;
    }
    
    @media (max-width: 992px) {
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .post-editor {
            padding: 20px;
        }
        
        .editor-toolbar {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .slug-preview {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .slug-preview input {
            width: 100%;
        }
    }
</style>

<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>

<!-- Toast Notifications -->
<?php if ($success): ?>
    <div class="toast-notification" id="successToast">
        <div style="background: #D4EDDA; color: #155724; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #28a745; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-check-circle"></i> 
            <span style="flex: 1;"><?php echo htmlspecialchars($success); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #155724; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="toast-notification" id="errorToast">
        <div style="background: #F8D7DA; color: #721C24; padding: 15px 25px; border-radius: 10px; border-left: 4px solid #dc3545; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-exclamation-circle"></i> 
            <span style="flex: 1;"><?php echo htmlspecialchars($error); ?></span>
            <button onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; color: #721C24; cursor: pointer; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
            <i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus-circle'; ?>" style="color: var(--gold);"></i>
            <?php echo $is_edit ? 'Edit Post' : 'Add New Post'; ?>
        </h1>
        <div style="color: var(--gray-600);">
            <i class="fas fa-file-alt"></i> 
            <?php echo $is_edit ? 'Editing: ' . htmlspecialchars($post['title'] ?? '') : 'Create a new blog post'; ?>
        </div>
    </div>
    
    <div style="display: flex; gap: 15px;">
        <a href="blog.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Cancel
        </a>
        <button type="submit" form="postForm" class="btn-primary">
            <i class="fas fa-save"></i> 
            <?php echo $is_edit ? 'Update Post' : 'Publish Post'; ?>
        </button>
    </div>
</div>

<form method="POST" action="" id="postForm" enctype="multipart/form-data">
    <div class="grid-2">
        <!-- Main Content Column -->
        <div class="post-editor">
            <div class="editor-toolbar">
                <span class="badge" style="background: rgba(255,184,28,0.1); color: #FFB81C; padding: 8px 16px;">
                    <i class="fas fa-<?php echo $is_edit ? 'file-alt' : 'plus'; ?>"></i> 
                    <?php echo $is_edit ? 'Editing Post #' . $post_id : 'New Post'; ?>
                </span>
                
                <?php if ($is_edit): ?>
                    <span style="color: var(--gray-600); font-size: 13px;">
                        <i class="fas fa-eye"></i> Views: <?php echo number_format($post['views'] ?? 0); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Post Title <span style="color: #dc3545;">*</span></label>
                <input type="text" name="title" class="form-control form-control-lg" 
                       value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" 
                       placeholder="Enter an engaging title (e.g., 10 Tips for Better Photography)" 
                       required
                       id="postTitle">
            </div>
            
            <div class="form-group">
                <label>Permalink (URL)</label>
                <div class="slug-preview">
                    <i class="fas fa-link"></i>
                    <span style="color: var(--gray-600);"><?php echo SITE_URL; ?>/single.php?slug=</span>
                    <input type="text" name="slug" id="postSlug" 
                           value="<?php echo htmlspecialchars($post['slug'] ?? ''); ?>" 
                           placeholder="url-friendly-slug"
                           style="flex: 1; border: 1px solid var(--gray-200); padding: 8px 12px; border-radius: 4px;">
                </div>
                <small style="color: var(--gray-600);">Leave empty to auto-generate from title. Use lowercase letters, numbers, and hyphens only.</small>
            </div>
            
            <div class="form-group">
                <label>Post Content <span style="color: #dc3545;">*</span></label>
                <textarea name="content" id="editor" rows="20" class="form-control"><?php echo htmlspecialchars($post['content'] ?? ''); ?></textarea>
                <div class="character-count" id="contentCount">0 characters</div>
            </div>
            
            <div class="form-group">
                <label>Excerpt (Summary)</label>
                <textarea name="excerpt" id="postExcerpt" class="form-control" rows="4" 
                          placeholder="Brief summary of your post. This will appear in blog listings and search results..."><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                <small style="color: var(--gray-600);">Recommended: 150-160 characters for optimal SEO</small>
                <div class="character-count" id="excerptCount">0/160 characters</div>
            </div>
            
            <!-- SEO Meta Fields -->
            <div class="meta-fields">
                <h4 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-line" style="color: var(--gold);"></i>
                    SEO Meta Information
                </h4>
                
                <div class="form-group">
                    <label>Meta Title</label>
                    <input type="text" name="meta_title" id="metaTitle" class="form-control" 
                           value="<?php echo htmlspecialchars($post['meta_title'] ?? $post['title'] ?? ''); ?>" 
                           placeholder="SEO title (leave empty to use post title)">
                    <small style="color: var(--gray-600);">Recommended: 50-60 characters</small>
                    <div class="character-count" id="metaTitleCount">0/60 characters</div>
                </div>
                
                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea name="meta_description" id="metaDescription" class="form-control" rows="3"
                              placeholder="SEO description (leave empty to use excerpt)"><?php echo htmlspecialchars($post['meta_description'] ?? ''); ?></textarea>
                    <small style="color: var(--gray-600);">Recommended: 150-160 characters</small>
                    <div class="character-count" id="metaDescCount">0/160 characters</div>
                </div>
                
                <div class="form-group">
                    <label>Meta Keywords</label>
                    <input type="text" name="meta_keywords" class="form-control" 
                           value="<?php echo htmlspecialchars($post['meta_keywords'] ?? ''); ?>" 
                           placeholder="blog, article, news, tips (comma-separated)">
                    <small style="color: var(--gray-600);">Comma-separated keywords for search engines</small>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Column -->
        <div style="display: flex; flex-direction: column; gap: 25px;">
            <!-- Publish Settings -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> Publish Settings</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" id="postStatus">
                            <option value="draft" <?php echo ($post['status'] ?? 'draft') == 'draft' ? 'selected' : ''; ?>>Draft - Save as draft</option>
                            <option value="published" <?php echo ($post['status'] ?? '') == 'published' ? 'selected' : ''; ?>>Published - Live on website</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Author</label>
                        <input type="text" name="author" class="form-control" 
                               value="<?php echo htmlspecialchars($post['author'] ?? $user['full_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="allow_comments" value="1" 
                                   <?php echo ($post['allow_comments'] ?? 1) == 1 ? 'checked' : ''; ?>>
                            Allow comments
                        </label>
                    </div>
                    
                    <?php if ($is_edit): ?>
                        <div style="background: var(--gray-100); padding: 15px; border-radius: 8px; margin-top: 15px;">
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <small style="color: var(--gray-600); display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-calendar-alt"></i>
                                    <strong>Created:</strong> <?php echo $post['created_at'] ? formatDate($post['created_at'], 'F j, Y \a\t H:i') : 'N/A'; ?>
                                </small>
                                <?php if ($post['published_date']): ?>
                                <small style="color: var(--gray-600); display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-clock"></i>
                                    <strong>Published:</strong> <?php echo formatDate($post['published_date'], 'F j, Y \a\t H:i'); ?>
                                </small>
                                <?php endif; ?>
                                <small style="color: var(--gray-600); display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-edit"></i>
                                    <strong>Updated:</strong> <?php echo $post['updated_at'] ? formatDate($post['updated_at'], 'F j, Y \a\t H:i') : 'Never'; ?>
                                </small>
                                <small style="color: var(--gray-600); display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-eye"></i>
                                    <strong>Views:</strong> <?php echo number_format($post['views'] ?? 0); ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Featured Image -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-image"></i> Featured Image</h3>
                </div>
                <div class="card-body">
                    <input type="hidden" name="remove_featured_image" id="remove_featured_image" value="0">
                    
                    <div class="featured-image-container" id="featuredImageContainer">
                        <div class="image-preview-container" id="imagePreviewContainer">
                            <?php if ($is_edit && !empty($post['featured_image'])): ?>
                                <img src="<?php echo SITE_URL . '/' . $post['featured_image']; ?>" 
                                     class="featured-image-preview" id="featuredImagePreview">
                                <div class="remove-image-btn" id="removeImageBtn">
                                    <i class="fas fa-times"></i>
                                </div>
                            <?php else: ?>
                                <img src="" class="featured-image-preview" id="featuredImagePreview" style="display: none;">
                                <div id="imagePlaceholder" style="padding: 30px;">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: var(--gold); margin-bottom: 15px;"></i>
                                    <h4 style="margin-bottom: 10px; color: var(--navy);">Upload Featured Image</h4>
                                    <p style="color: var(--gray-600); margin-bottom: 5px;">Click to browse or drag and drop</p>
                                    <p style="font-size: 12px; color: var(--gray-500);">Recommended size: 1200x800px • JPG, PNG, WEBP up to 5MB</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <input type="file" name="featured_image" id="featured_image" 
                           accept="image/*" style="display: none;">
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
                        <button type="button" id="chooseImageBtn" class="btn-secondary">
                            <i class="fas fa-upload"></i> Choose Image
                        </button>
                        <?php if ($is_edit && !empty($post['featured_image'])): ?>
                            <button type="button" id="removeImageBtn2" class="btn-secondary">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SEO Preview -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-search"></i> Google Search Preview</h3>
                </div>
                <div class="card-body">
                    <div class="seo-preview">
                        <div class="seo-preview-title" id="seoPreviewTitle">
                            <?php 
                            $seo_title = htmlspecialchars($post['meta_title'] ?? $post['title'] ?? 'Post Title');
                            echo truncateText($seo_title, 60);
                            ?>
                        </div>
                        <div class="seo-preview-url" id="seoPreviewUrl">
                            <?php echo SITE_URL; ?>/single.php?slug=<?php echo $post['slug'] ?? 'post-title'; ?>
                        </div>
                        <div class="seo-preview-desc" id="seoPreviewDesc">
                            <?php 
                            $seo_desc = htmlspecialchars($post['meta_description'] ?? $post['excerpt'] ?? strip_tags($post['content'] ?? ''));
                            echo truncateText($seo_desc, 160);
                            ?>
                        </div>
                    </div>
                    <small style="display: block; margin-top: 15px; color: var(--gray-600);">
                        <i class="fas fa-info-circle"></i> This is how your post may appear in Google search results.
                    </small>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <?php if ($is_edit): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="<?php echo SITE_URL; ?>/single.php?slug=<?php echo urlencode($post['slug']); ?>" 
                           target="_blank" 
                           class="btn-secondary" 
                           style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-external-link-alt"></i> View Post
                        </a>
                        <a href="?delete=<?php echo $post['id']; ?>" 
                           class="btn-secondary" 
                           id="deletePostBtn"
                           style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #dc3545; color: white; border-color: #dc3545;">
                            <i class="fas fa-trash"></i> Delete Post
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
// ============================================
// BLOG POST EDITOR - JAVASCRIPT (FIXED)
// ============================================

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('Post editor loaded');
    
    // ============================================
    // INITIALIZE CKEDITOR
    // ============================================
    if (typeof CKEDITOR !== 'undefined') {
        console.log('Initializing CKEditor...');
        
        // Replace the textarea with CKEditor
        CKEDITOR.replace('editor', {
            height: 500,
            toolbarGroups: [
                { name: 'document', groups: ['mode', 'document', 'doctools'] },
                { name: 'clipboard', groups: ['clipboard', 'undo'] },
                { name: 'editing', groups: ['find', 'selection', 'spellchecker'] },
                { name: 'forms', groups: ['forms'] },
                '/',
                { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
                { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align', 'bidi'] },
                { name: 'links', groups: ['links'] },
                { name: 'insert', groups: ['insert'] },
                '/',
                { name: 'styles', groups: ['styles'] },
                { name: 'colors', groups: ['colors'] },
                { name: 'tools', groups: ['tools'] }
            ],
            removeButtons: 'Save,NewPage,Preview,Print,Templates,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,CreateDiv,BidiLtr,BidiRtl,Language,PageBreak,Iframe,Flash,Smiley,About',
            format_tags: 'p;h1;h2;h3;h4;h5;h6;pre;address;div',
            removeDialogTabs: 'image:advanced;link:advanced',
            on: {
                instanceReady: function(ev) {
                    console.log('CKEditor instance ready');
                    // Update content count
                    updateContentCount();
                    
                    // Set up change event
                    ev.editor.on('change', function() {
                        updateContentCount();
                        updateSEOPreview();
                    });
                }
            }
        });
    } else {
        console.error('CKEditor not loaded');
    }
    
    // ============================================
    // SLUG GENERATION
    // ============================================
    const titleInput = document.getElementById('postTitle');
    const slugInput = document.getElementById('postSlug');
    
    if (titleInput && slugInput) {
        titleInput.addEventListener('keyup', function() {
            // Only auto-generate if slug is empty
            if (!slugInput.value || slugInput.value === '') {
                const slug = this.value
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/--+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .trim();
                
                slugInput.value = slug;
                updateSEOPreview();
            }
        });
        
        slugInput.addEventListener('keyup', updateSEOPreview);
    }
    
    // ============================================
    // FEATURED IMAGE HANDLING
    // ============================================
    const featuredImageInput = document.getElementById('featured_image');
    const featuredImagePreview = document.getElementById('featuredImagePreview');
    const imagePlaceholder = document.getElementById('imagePlaceholder');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const featuredImageContainer = document.getElementById('featuredImageContainer');
    const chooseImageBtn = document.getElementById('chooseImageBtn');
    const removeImageBtn = document.getElementById('removeImageBtn');
    const removeImageBtn2 = document.getElementById('removeImageBtn2');
    const removeFlag = document.getElementById('remove_featured_image');
    
    // Choose image button click
    if (chooseImageBtn && featuredImageInput) {
        chooseImageBtn.addEventListener('click', function() {
            featuredImageInput.click();
        });
    }
    
    // Container click
    if (featuredImageContainer) {
        featuredImageContainer.addEventListener('click', function(e) {
            // Don't trigger if clicking on remove button
            if (!e.target.closest('.remove-image-btn')) {
                featuredImageInput.click();
            }
        });
    }
    
    // File input change
    if (featuredImageInput) {
        featuredImageInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Show preview
                    featuredImagePreview.src = e.target.result;
                    featuredImagePreview.style.display = 'block';
                    
                    // Hide placeholder
                    if (imagePlaceholder) {
                        imagePlaceholder.style.display = 'none';
                    }
                    
                    // Add remove button if not exists
                    if (!document.getElementById('removeImageBtn')) {
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-image-btn';
                        removeBtn.id = 'removeImageBtn';
                        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                        imagePreviewContainer.appendChild(removeBtn);
                        
                        // Add click event to remove button
                        removeBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            removeFeaturedImage();
                        });
                    }
                    
                    // Reset remove flag
                    if (removeFlag) {
                        removeFlag.value = '0';
                    }
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Remove image function
    window.removeFeaturedImage = function() {
        // Hide preview
        featuredImagePreview.src = '';
        featuredImagePreview.style.display = 'none';
        
        // Show placeholder
        if (imagePlaceholder) {
            imagePlaceholder.style.display = 'block';
        }
        
        // Remove remove button
        const removeBtn = document.getElementById('removeImageBtn');
        if (removeBtn) {
            removeBtn.remove();
        }
        
        // Clear file input
        if (featuredImageInput) {
            featuredImageInput.value = '';
        }
        
        // Set remove flag
        if (removeFlag) {
            removeFlag.value = '1';
        }
    };
    
    // Remove button click
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            removeFeaturedImage();
        });
    }
    
    if (removeImageBtn2) {
        removeImageBtn2.addEventListener('click', function(e) {
            e.preventDefault();
            removeFeaturedImage();
        });
    }
    
    // ============================================
    // CHARACTER COUNTERS
    // ============================================
    function updateContentCount() {
        const editor = CKEDITOR.instances.editor;
        const countEl = document.getElementById('contentCount');
        
        if (editor && countEl) {
            const text = editor.getData().replace(/<[^>]*>/g, '');
            const count = text.length;
            countEl.textContent = count + ' characters';
            
            if (count > 10000) {
                countEl.className = 'character-count danger';
            } else if (count > 8000) {
                countEl.className = 'character-count warning';
            } else {
                countEl.className = 'character-count';
            }
        }
    }
    
    // Excerpt counter
    const excerptInput = document.getElementById('postExcerpt');
    const excerptCountEl = document.getElementById('excerptCount');
    
    function updateExcerptCount() {
        if (excerptInput && excerptCountEl) {
            const count = excerptInput.value.length;
            excerptCountEl.textContent = count + '/160 characters';
            
            if (count > 160) {
                excerptCountEl.className = 'character-count danger';
            } else if (count > 140) {
                excerptCountEl.className = 'character-count warning';
            } else {
                excerptCountEl.className = 'character-count';
            }
        }
    }
    
    if (excerptInput) {
        excerptInput.addEventListener('keyup', updateExcerptCount);
        updateExcerptCount();
    }
    
    // Meta title counter
    const metaTitleInput = document.getElementById('metaTitle');
    const metaTitleCountEl = document.getElementById('metaTitleCount');
    
    function updateMetaTitleCount() {
        if (metaTitleInput && metaTitleCountEl) {
            const count = metaTitleInput.value.length;
            metaTitleCountEl.textContent = count + '/60 characters';
            
            if (count > 60) {
                metaTitleCountEl.className = 'character-count danger';
            } else if (count > 50) {
                metaTitleCountEl.className = 'character-count warning';
            } else {
                metaTitleCountEl.className = 'character-count';
            }
            updateSEOPreview();
        }
    }
    
    if (metaTitleInput) {
        metaTitleInput.addEventListener('keyup', updateMetaTitleCount);
        updateMetaTitleCount();
    }
    
    // Meta description counter
    const metaDescInput = document.getElementById('metaDescription');
    const metaDescCountEl = document.getElementById('metaDescCount');
    
    function updateMetaDescCount() {
        if (metaDescInput && metaDescCountEl) {
            const count = metaDescInput.value.length;
            metaDescCountEl.textContent = count + '/160 characters';
            
            if (count > 160) {
                metaDescCountEl.className = 'character-count danger';
            } else if (count > 140) {
                metaDescCountEl.className = 'character-count warning';
            } else {
                metaDescCountEl.className = 'character-count';
            }
            updateSEOPreview();
        }
    }
    
    if (metaDescInput) {
        metaDescInput.addEventListener('keyup', updateMetaDescCount);
        updateMetaDescCount();
    }
    
    // ============================================
    // SEO PREVIEW
    // ============================================
    function updateSEOPreview() {
        const titlePreview = document.getElementById('seoPreviewTitle');
        const urlPreview = document.getElementById('seoPreviewUrl');
        const descPreview = document.getElementById('seoPreviewDesc');
        
        // Title preview
        if (titlePreview) {
            let titleText = '';
            if (metaTitleInput && metaTitleInput.value) {
                titleText = metaTitleInput.value;
            } else if (titleInput) {
                titleText = titleInput.value;
            } else {
                titleText = 'Post Title';
            }
            
            if (titleText.length > 60) {
                titleText = titleText.substring(0, 60) + '...';
            }
            titlePreview.textContent = titleText;
        }
        
        // URL preview
        if (urlPreview && slugInput) {
            let slugText = slugInput.value || 'post-title';
            urlPreview.textContent = '<?php echo SITE_URL; ?>/single.php?slug=' + slugText;
        }
        
        // Description preview
        if (descPreview) {
            let descText = '';
            if (metaDescInput && metaDescInput.value) {
                descText = metaDescInput.value;
            } else if (excerptInput && excerptInput.value) {
                descText = excerptInput.value;
            } else {
                descText = 'Post description will appear here...';
            }
            
            if (descText.length > 160) {
                descText = descText.substring(0, 160) + '...';
            }
            descPreview.textContent = descText;
        }
    }
    
    // Initialize SEO preview
    updateSEOPreview();
    
    // ============================================
    // DELETE POST CONFIRMATION
    // ============================================
    const deletePostBtn = document.getElementById('deletePostBtn');
    
    if (deletePostBtn) {
        deletePostBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                window.location.href = this.href;
            }
        });
    }
    
    // ============================================
    // UNSAVED CHANGES WARNING
    // ============================================
    let formChanged = false;
    const postForm = document.getElementById('postForm');
    
    function markFormAsChanged() {
        formChanged = true;
    }
    
    // Watch for changes in form inputs
    if (postForm) {
        const formInputs = postForm.querySelectorAll('input:not([type=file]), textarea, select');
        formInputs.forEach(function(input) {
            input.addEventListener('change', markFormAsChanged);
            input.addEventListener('keyup', markFormAsChanged);
        });
        
        // Watch for CKEditor changes
        if (CKEDITOR.instances.editor) {
            CKEDITOR.instances.editor.on('change', markFormAsChanged);
        }
        
        // Reset form changed flag on submit
        postForm.addEventListener('submit', function() {
            formChanged = false;
        });
    }
    
    // Warn before leaving if form changed
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // ============================================
    // TOAST NOTIFICATIONS AUTO-HIDE
    // ============================================
    const successToast = document.getElementById('successToast');
    const errorToast = document.getElementById('errorToast');
    
    function hideToast(toast) {
        if (toast) {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }
    }
    
    if (successToast) {
        setTimeout(function() { hideToast(successToast); }, 5000);
    }
    
    if (errorToast) {
        setTimeout(function() { hideToast(errorToast); }, 5000);
    }
    
    // ============================================
    // ADD SLIDEOUT ANIMATION
    // ============================================
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
}); // End DOMContentLoaded
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>