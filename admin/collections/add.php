<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db    = Database::getInstance();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if (!$slug) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
    }

    if (!$name || !$slug) {
        $error = 'Name and Slug are required.';
    } else {
        try {
            $coverUrl = '';

            // Handle cover image upload
            if (!empty($_FILES['cover_image']['tmp_name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    if ($_FILES['cover_image']['size'] <= MAX_FILE_SIZE) {
                        $uploadDir = __DIR__ . '/../../api/uploads/collections/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $filename = uniqid('col_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $filename)) {
                            $coverUrl = '/api/uploads/collections/' . $filename;
                        }
                    }
                }
            }

            $ins = $db->prepare("
                INSERT INTO collections (slug, name, description, cover_image_url, sort_order)
                VALUES (:slug, :name, :desc, :cover, :sort)
            ");
            $ins->execute([
                ':slug'  => $slug,
                ':name'  => $name,
                ':desc'  => $desc,
                ':cover' => $coverUrl,
                ':sort'  => $sortOrder,
            ]);

            header('Location: /admin/collections.php?added=1');
            exit;

        } catch (Throwable $e) {
            $error = 'Failed to add collection. ' . (APP_ENV === 'development' ? $e->getMessage() : 'Slug may already exist.');
        }
    }
}

$adminTitle = 'Add Collection';
require_once __DIR__ . '/../../admin/includes/header.php';
?>

<?php if ($error): ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="margin-bottom:16px">
  <a href="/admin/collections.php" style="color:var(--text-muted);font-size:13px;text-decoration:none">← Back to Collections</a>
</div>

<div style="max-width:680px">
  <form method="POST" enctype="multipart/form-data">
    <div class="admin-card" style="margin-bottom:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Collection Details</h3>

      <div class="admin-form-group">
        <label class="admin-label">Collection Name *</label>
        <input type="text" name="name" class="admin-input" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. For Him" required oninput="autoSlug(this.value)">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">URL Slug *</label>
        <input type="text" name="slug" id="slug-input" class="admin-input" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>" placeholder="e.g. for-him" required>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Description</label>
        <textarea name="description" class="admin-input" rows="3" placeholder="Short description of this collection..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Sort Order</label>
        <input type="number" name="sort_order" class="admin-input" value="<?= (int)($_POST['sort_order'] ?? 0) ?>" min="0" style="max-width:120px">
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px">Lower number = appears first (0 = top)</p>
      </div>
    </div>

    <div class="admin-card" style="margin-bottom:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Cover Image</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">JPG/PNG/WebP, max 5MB. Displays on collections grid.</p>
      <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp" class="admin-input" style="padding:10px" onchange="previewCover(this)">
      <div id="cover-preview" style="margin-top:12px"></div>
    </div>

    <div style="display:flex;gap:10px">
      <a href="/admin/collections.php" class="btn-admin-outline" style="flex:1;justify-content:center">Cancel</a>
      <button type="submit" class="btn-admin-gold" style="flex:2">Save Collection</button>
    </div>
  </form>
</div>

<?php
$adminScripts = <<<'JS'
<script>
let slugManual = false;
function autoSlug(name) {
  if (!slugManual) {
    document.getElementById('slug-input').value = name.toLowerCase()
      .replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-').trim();
  }
}
document.getElementById('slug-input').addEventListener('input', () => { slugManual = true; });

function previewCover(input) {
  const preview = document.getElementById('cover-preview');
  preview.innerHTML = '';
  if (input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.cssText = 'width:160px;height:110px;object-fit:cover;border-radius:8px;border:2px solid var(--accent)';
      preview.appendChild(img);
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
JS;
require_once __DIR__ . '/../../admin/includes/footer.php';
?>
