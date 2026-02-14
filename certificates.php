<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page = getPageContent('certificates');
$page_title = $page ? $page['page_title'] : 'Our Certificates';
$meta_description = $page ? $page['meta_description'] : 'View our accreditations and certifications demonstrating our commitment to excellence.';

// Get certificates
try {
    $stmt = db()->query("SELECT * FROM certificates WHERE status = 'active' ORDER BY display_order");
    $certificates = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching certificates: " . $e->getMessage());
    $certificates = [];
}

include 'includes/header.php';
?>

<!-- Page Header -->
<section class="section bg-navy" style="padding: 60px 0;">
    <div class="container">
        <h1 style="color: white; margin-bottom: 15px;"><?php echo $page ? $page['page_title'] : 'Our Certificates'; ?></h1>
        <p style="color: rgba(255,255,255,0.9); font-size: 1.2rem;">Accreditations and certifications that validate our commitment to quality and excellence</p>
    </div>
</section>

<!-- Introduction -->
<?php if ($page && trim($page['page_content'])): ?>
<section class="section">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto; text-align: center;">
            <?php echo $page['page_content']; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Certificates Grid -->
<section class="section">
    <div class="container">
        <?php if ($certificates): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                <?php foreach ($certificates as $cert): ?>
                    <div class="card" style="padding: 20px; text-align: center; cursor: pointer;" onclick="openModal('<?php echo SITE_URL . '/' . $cert['certificate_image']; ?>', '<?php echo $cert['certificate_title']; ?>')">
                        <div style="height: 250px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; background: var(--gray-100); border-radius: 8px; overflow: hidden;">
                            <img src="<?php echo SITE_URL . '/' . $cert['certificate_image']; ?>" alt="<?php echo $cert['certificate_title']; ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                        <h3 style="margin-bottom: 10px; font-size: 1.25rem;"><?php echo $cert['certificate_title']; ?></h3>
                        
                        <?php if ($cert['issuing_body']): ?>
                            <p style="color: var(--gold); font-weight: 600; margin-bottom: 5px;"><?php echo $cert['issuing_body']; ?></p>
                        <?php endif; ?>
                        
                        <?php if ($cert['issue_date']): ?>
                            <p style="color: var(--gray-600); font-size: 14px; margin: 0;">
                                Issued: <?php echo formatDate($cert['issue_date'], 'M Y'); ?>
                                <?php if ($cert['expiry_date']): ?>
                                    | Expires: <?php echo formatDate($cert['expiry_date'], 'M Y'); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-certificate" style="font-size: 64px; color: var(--gold); margin-bottom: 20px;"></i>
                <h2 style="margin-bottom: 15px;">No Certificates Yet</h2>
                <p style="color: var(--gray-600);">Check back soon for our accreditations and certifications.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Image Modal -->
<div id="certModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9);">
    <span onclick="closeModal()" style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
    
    <div style="display: flex; align-items: center; justify-content: center; height: 100%; padding: 20px;">
        <div style="max-width: 90%; max-height: 90%; text-align: center;">
            <img id="modalImage" src="" alt="" style="max-width: 100%; max-height: 80vh; object-fit: contain; border: 5px solid white; border-radius: 8px;">
            <h3 id="modalTitle" style="color: white; margin-top: 20px;"></h3>
        </div>
    </div>
</div>

<script>
function openModal(imageSrc, title) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('modalTitle').innerHTML = title;
    document.getElementById('certModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('certModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('certModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php
include 'includes/footer.php';
?>