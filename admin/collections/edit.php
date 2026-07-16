<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/collections.php');
    exit;
}

$collection = $db->prepare("SELECT * FROM collections WHERE id = :id");
$collection->execute([':id' => $id]);
$collection = $collection->fetch();

if (!$collection) {
    header('Location: /admin/collections.php');
    exit;
}

$error   = '';
$success = '';

// Auto-migrate new visibility columns (safe to run every time)
try { $db->exec("ALTER TABLE collections ADD COLUMN hide_from_homepage TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}
try { $db->exec("ALTER TABLE collections ADD COLUMN hide_products TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    // Visibility
    $visMode         = $_POST['vis_mode'] ?? 'visible';
    $isHidden        = 0;
    $hideFromHomepage = 0;
    $hideProducts    = isset($_POST['hide_products']) ? 1 : 0;
    if ($visMode === 'home_hidden') {
        $hideFromHomepage = 1;
    } elseif ($visMode === 'fully_hidden' || $visMode === 'hidden_products') {
        $isHidden         = 1;
        $hideFromHomepage = 1;
    }
    // hide_products only makes sense when fully hidden
    if (!$isHidden) $hideProducts = 0;

    if (!$slug) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
    }

    if (!$name || !$slug) {
        $error = 'Name and Slug are required.';
    } else {
        try {
            $coverUrl = $collection['cover_image_url'];

            // Handle new cover image
            if (!empty($_FILES['cover_image']['tmp_name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['cover_image']['size'] <= MAX_FILE_SIZE) {
                    $uploadDir = __DIR__ . '/../../api/uploads/collections/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $filename = uniqid('col_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $filename)) {
                        // Delete old file
                        if ($coverUrl) {
                            $old = __DIR__ . '/../../' . ltrim($coverUrl, '/');
                            if (file_exists($old)) @unlink($old);
                        }
                        $coverUrl = '/api/uploads/collections/' . $filename;
                    }
                }
            }

            // Remove cover if requested
            if (isset($_POST['remove_cover']) && $coverUrl) {
                $old = __DIR__ . '/../../' . ltrim($coverUrl, '/');
                if (file_exists($old)) @unlink($old);
                $coverUrl = '';
            }

            $upd = $db->prepare("
                UPDATE collections SET
                    slug=:slug, name=:name, description=:desc,
                    cover_image_url=:cover, sort_order=:sort,
                    is_hidden=:hidden, hide_from_homepage=:hhp, hide_products=:hp
                WHERE id=:id
            ");
            $upd->execute([
                ':slug'   => $slug,
                ':name'   => $name,
                ':desc'   => $desc,
                ':cover'  => $coverUrl,
                ':sort'   => $sortOrder,
                ':hidden' => $isHidden,
                ':hhp'    => $hideFromHomepage,
                ':hp'     => $hideProducts,
                ':id'     => $id,
            ]);

            header('Location: /admin/collections.php?updated=1');
            exit;

        } catch (Throwable $e) {
            $error = 'Failed to update. ' . (APP_ENV === 'development' ? $e->getMessage() : '');
        }
    }
}

$adminTitle = 'Edit: ' . htmlspecialchars($collection['name']);
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
        <input type="text" name="name" class="admin-input" value="<?= htmlspecialchars($collection['name']) ?>" required oninput="autoSlug(this.value)">
      </div>
      <div class="admin-form-group">
        <label class="admin-label">URL Slug *</label>
        <input type="text" name="slug" id="slug-input" class="admin-input" value="<?= htmlspecialchars($collection['slug']) ?>" required>
      </div>
      <div class="admin-form-group">
        <label class="admin-label">Description</label>
        <textarea name="description" class="admin-input" rows="3"><?= htmlspecialchars($collection['description'] ?? '') ?></textarea>
      </div>
      <div class="admin-form-group">
        <label class="admin-label">Sort Order</label>
        <input type="number" name="sort_order" class="admin-input" value="<?= (int)$collection['sort_order'] ?>" min="0" style="max-width:120px">
      </div>

      <div class="admin-form-group" style="border-top:1px solid var(--admin-border);padding-top:20px;margin-top:4px">
        <label class="admin-label">Visibility</label>
        <?php
        // Determine current visibility mode
        $curHidden  = !empty($collection['is_hidden']);
        $curHhp     = !empty($collection['hide_from_homepage']);
        $curHprod   = !empty($collection['hide_products']);
        if ($curHidden && $curHprod)  $curVisMode = 'hidden_products';
        elseif ($curHidden)           $curVisMode = 'fully_hidden';
        elseif ($curHhp)              $curVisMode = 'home_hidden';
        else                          $curVisMode = 'visible';
        ?>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:6px" id="vis-options">

          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;border-radius:8px;border:1px solid var(--admin-border);background:<?= $curVisMode==='visible' ? 'rgba(40,167,69,.1)' : 'transparent' ?>">
            <input type="radio" name="vis_mode" value="visible" <?= $curVisMode==='visible' ? 'checked' : '' ?> style="margin-top:3px;accent-color:#28a745" onchange="updateVisUI()">
            <div>
              <span style="font-size:13px;font-weight:600;color:var(--text-primary)">🟢 Visible Everywhere</span>
              <p style="margin:2px 0 0;font-size:11px;color:var(--text-muted)">Shows on homepage, in the collections sidebar, and in All Fragrances.</p>
            </div>
          </label>

          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;border-radius:8px;border:1px solid var(--admin-border);background:<?= $curVisMode==='home_hidden' ? 'rgba(255,193,7,.1)' : 'transparent' ?>">
            <input type="radio" name="vis_mode" value="home_hidden" <?= $curVisMode==='home_hidden' ? 'checked' : '' ?> style="margin-top:3px;accent-color:#ffc107" onchange="updateVisUI()">
            <div>
              <span style="font-size:13px;font-weight:600;color:var(--text-primary)">🟡 Hidden from Homepage Only</span>
              <p style="margin:2px 0 0;font-size:11px;color:var(--text-muted)">Not shown in the homepage "Shop By Collection" grid, but still visible in the collections sidebar and browsable directly.</p>
            </div>
          </label>

          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;border-radius:8px;border:1px solid var(--admin-border);background:<?= $curVisMode==='fully_hidden' ? 'rgba(220,53,69,.1)' : 'transparent' ?>">
            <input type="radio" name="vis_mode" value="fully_hidden" <?= $curVisMode==='fully_hidden' ? 'checked' : '' ?> style="margin-top:3px;accent-color:#dc3545" onchange="updateVisUI()">
            <div>
              <span style="font-size:13px;font-weight:600;color:var(--text-primary)">🔴 Fully Hidden</span>
              <p style="margin:2px 0 0;font-size:11px;color:var(--text-muted)">Hidden from homepage and from the collections sidebar. Products still appear in "All Fragrances" listing.</p>
            </div>
          </label>

        </div>

        <!-- hide_products option — only relevant when fully hidden -->
        <div id="hide-products-wrap" style="margin-top:12px;padding:12px;background:rgba(220,53,69,.07);border:1px solid rgba(220,53,69,.2);border-radius:8px;<?= ($curVisMode !== 'fully_hidden' && $curVisMode !== 'hidden_products') ? 'display:none' : '' ?>">
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
            <input type="checkbox" name="hide_products" <?= $curHprod ? 'checked' : '' ?> style="margin-top:2px;accent-color:#dc3545">
            <div>
              <span style="font-size:13px;font-weight:600;color:var(--text-primary)">⛔ Also hide products from "All Fragrances"</span>
              <p style="margin:2px 0 0;font-size:11px;color:var(--text-muted)">When checked, products inside this collection will NOT appear in the "All Fragrances" listing on the collections page.</p>
            </div>
          </label>
        </div>
      </div>
    </div>

    <div class="admin-card" style="margin-bottom:20px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Cover Image</h3>

      <?php if ($collection['cover_image_url']): ?>
      <div style="margin-bottom:14px;display:flex;align-items:flex-end;gap:14px">
        <img src="<?= htmlspecialchars($collection['cover_image_url']) ?>" alt=""
             style="width:160px;height:110px;object-fit:cover;border-radius:8px;border:1px solid var(--admin-border)"
             onerror="this.style.opacity='.2'">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:var(--danger)">
          <input type="checkbox" name="remove_cover" style="accent-color:var(--danger)"> Remove cover image
        </label>
      </div>
      <?php endif; ?>

      <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Upload new cover (replaces existing):</p>
      <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp" class="admin-input" style="padding:10px" onchange="previewCover(this)">
      <div id="cover-preview" style="margin-top:12px"></div>
    </div>

    <div style="display:flex;gap:10px;margin-bottom:16px">
      <a href="/admin/collections.php" class="btn-admin-outline" style="flex:1;justify-content:center">Cancel</a>
      <button type="submit" class="btn-admin-gold" style="flex:2">Update Collection</button>
    </div>
  </form>

  <div style="padding:12px;background:rgba(220,53,69,.08);border:1px solid rgba(220,53,69,.25);border-radius:8px">
    <p style="font-size:12px;color:var(--danger);margin-bottom:10px;font-weight:600">Danger Zone</p>
    <button onclick="confirmDelete('/admin/actions/collection_delete.php?id=<?= $id ?>', '<?= htmlspecialchars(addslashes($collection['name'])) ?>')" class="btn-admin-danger" style="width:100%">Delete Collection</button>
  </div>
</div>

<?php
$adminScripts = <<<'JS'
<script>
function updateVisUI() {
  const mode = document.querySelector('input[name="vis_mode"]:checked')?.value || 'visible';
  const wrap = document.getElementById('hide-products-wrap');
  if (wrap) wrap.style.display = (mode === 'fully_hidden' || mode === 'hidden_products') ? 'block' : 'none';
  // Highlight active card
  const colors = { visible:'rgba(40,167,69,.1)', home_hidden:'rgba(255,193,7,.1)', fully_hidden:'rgba(220,53,69,.1)' };
  document.querySelectorAll('#vis-options > label').forEach(lbl => {
    const r = lbl.querySelector('input[type=radio]');
    lbl.style.background = r.checked ? (colors[r.value] || 'transparent') : 'transparent';
  });
}
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
