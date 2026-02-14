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
            <?php echo $is_edit ? 'Editing: ' . htmlspecialchars($post['title'] ?? 'Untitled') : 'Create a new blog post'; ?>
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

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('Post editor page loaded');
    
    // ============================================
    // INITIALIZE CKEDITOR - with forced content load
    // ============================================
    if (typeof CKEDITOR !== 'undefined') {
        console.log('CKEditor library detected');
        
        try {
            const editorInstance = CKEDITOR.replace('editor', {
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
                        console.log('CKEditor ready - injecting content');
                        // Force set content from PHP
                        ev.editor.setData(`<?php echo addslashes(htmlspecialchars($post['content'] ?? '')); ?>`);
                        updateContentCount();
                        updateSEOPreview();
                    },
                    change: function() {
                        updateContentCount();
                        updateSEOPreview();
                    }
                }
            });
        } catch (err) {
            console.error('CKEditor init failed:', err);
        }
    } else {
        console.error('CKEditor script not loaded - check network/console');
    }
    
    // ============================================
    // SLUG GENERATION
    // ============================================
    const titleInput = document.getElementById('postTitle');
    const slugInput = document.getElementById('postSlug');
    
    if (titleInput && slugInput) {
        titleInput.addEventListener('keyup', function() {
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
    
    if (chooseImageBtn && featuredImageInput) {
        chooseImageBtn.addEventListener('click', () => featuredImageInput.click());
    }
    
    if (featuredImageContainer) {
        featuredImageContainer.addEventListener('click', e => {
            if (!e.target.closest('.remove-image-btn')) {
                featuredImageInput.click();
            }
        });
    }
    
    if (featuredImageInput) {
        featuredImageInput.addEventListener('change', function(e) {
            if (this.files?.[0]) {
                const reader = new FileReader();
                reader.onload = ev => {
                    if (featuredImagePreview) {
                        featuredImagePreview.src = ev.target.result;
                        featuredImagePreview.style.display = 'block';
                    }
                    if (imagePlaceholder) imagePlaceholder.style.display = 'none';
                    
                    if (!document.getElementById('removeImageBtn')) {
                        const btn = document.createElement('div');
                        btn.className = 'remove-image-btn';
                        btn.id = 'removeImageBtn';
                        btn.innerHTML = '<i class="fas fa-times"></i>';
                        imagePreviewContainer.appendChild(btn);
                        btn.addEventListener('click', e => {
                            e.stopPropagation();
                            removeFeaturedImage();
                        });
                    }
                    if (removeFlag) removeFlag.value = '0';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    window.removeFeaturedImage = function() {
        if (featuredImagePreview) {
            featuredImagePreview.src = '';
            featuredImagePreview.style.display = 'none';
        }
        if (imagePlaceholder) imagePlaceholder.style.display = 'block';
        
        const btn = document.getElementById('removeImageBtn');
        if (btn) btn.remove();
        
        if (featuredImageInput) featuredImageInput.value = '';
        if (removeFlag) removeFlag.value = '1';
    };
    
    if (removeImageBtn) removeImageBtn.addEventListener('click', e => { e.stopPropagation(); removeFeaturedImage(); });
    if (removeImageBtn2) removeImageBtn2.addEventListener('click', e => { e.preventDefault(); removeFeaturedImage(); });
    
    // ============================================
    // CHARACTER COUNTERS & SEO PREVIEW
    // ============================================
    function updateContentCount() {
        const editor = CKEDITOR.instances?.editor;
        const countEl = document.getElementById('contentCount');
        if (editor && countEl) {
            const text = editor.getData().replace(/<[^>]*>/g, '').trim();
            const count = text.length;
            countEl.textContent = count + ' characters';
            countEl.className = 'character-count' + 
                (count > 10000 ? ' danger' : count > 8000 ? ' warning' : '');
        }
    }
    
    function updateExcerptCount() {
        const input = document.getElementById('postExcerpt');
        const countEl = document.getElementById('excerptCount');
        if (input && countEl) {
            const count = input.value.length;
            countEl.textContent = count + '/160 characters';
            countEl.className = 'character-count' + 
                (count > 160 ? ' danger' : count > 140 ? ' warning' : '');
        }
    }
    
    function updateMetaTitleCount() {
        const input = document.getElementById('metaTitle');
        const countEl = document.getElementById('metaTitleCount');
        if (input && countEl) {
            const count = input.value.length;
            countEl.textContent = count + '/60 characters';
            countEl.className = 'character-count' + 
                (count > 60 ? ' danger' : count > 50 ? ' warning' : '');
            updateSEOPreview();
        }
    }
    
    function updateMetaDescCount() {
        const input = document.getElementById('metaDescription');
        const countEl = document.getElementById('metaDescCount');
        if (input && countEl) {
            const count = input.value.length;
            countEl.textContent = count + '/160 characters';
            countEl.className = 'character-count' + 
                (count > 160 ? ' danger' : count > 140 ? ' warning' : '');
            updateSEOPreview();
        }
    }
    
    function updateSEOPreview() {
        const titleEl = document.getElementById('seoPreviewTitle');
        const urlEl   = document.getElementById('seoPreviewUrl');
        const descEl  = document.getElementById('seoPreviewDesc');
        
        if (titleEl) {
            let t = document.getElementById('metaTitle')?.value || 
                    document.getElementById('postTitle')?.value || 'Post Title';
            titleEl.textContent = t.length > 60 ? t.substring(0,57) + '...' : t;
        }
        
        if (urlEl && slugInput) {
            let s = slugInput.value.trim() || 'post-title';
            urlEl.textContent = '<?php echo SITE_URL; ?>/single.php?slug=' + s;
        }
        
        if (descEl) {
            let d = document.getElementById('metaDescription')?.value || 
                    document.getElementById('postExcerpt')?.value || '';
            descEl.textContent = d.length > 160 ? d.substring(0,157) + '...' : d;
        }
    }
    
    // Attach input listeners
    ['postExcerpt', 'metaTitle', 'metaDescription'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', window[`update${id.replace('post', '').replace('meta', '')}Count`] || updateSEOPreview);
    });
    
    // Initial calls
    updateExcerptCount();
    updateMetaTitleCount();
    updateMetaDescCount();
    updateSEOPreview();
    
    // ============================================
    // DELETE CONFIRMATION
    // ============================================
    document.getElementById('deletePostBtn')?.addEventListener('click', e => {
        if (!confirm('Delete this post permanently? This cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    // ============================================
    // UNSAVED CHANGES WARNING
    // ============================================
    let formChanged = false;
    const postForm = document.getElementById('postForm');
    
    if (postForm) {
        postForm.querySelectorAll('input, textarea, select').forEach(el => {
            el.addEventListener('input', () => formChanged = true);
            el.addEventListener('change', () => formChanged = true);
        });
        
        if (CKEDITOR?.instances?.editor) {
            CKEDITOR.instances.editor.on('change', () => formChanged = true);
        }
        
        postForm.addEventListener('submit', () => formChanged = false);
    }
    
    window.addEventListener('beforeunload', e => {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    // ============================================
    // TOAST AUTO-HIDE
    // ============================================
    document.querySelectorAll('.toast-notification').forEach(t => {
        setTimeout(() => {
            t.style.opacity = '0';
            setTimeout(() => t.remove(), 400);
        }, 5000);
    });
});
</script>

<?php
// Include admin footer
require_once '../includes/admin-footer.php';
?>