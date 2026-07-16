<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /admin/products.php');
    exit;
}

// Auto-migrate is_top_rated column if missing
try { $db->exec("ALTER TABLE products ADD COLUMN is_top_rated TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}

$product = $db->prepare("SELECT * FROM products WHERE id = :id");
$product->execute([':id' => $id]);
$product = $product->fetch();

if (!$product) {
    header('Location: /admin/products.php');
    exit;
}

$collections = $db->query("SELECT * FROM collections ORDER BY sort_order")->fetchAll();

// Current product collections
$prodCols = $db->prepare("SELECT collection_id FROM product_collections WHERE product_id = :id");
$prodCols->execute([':id' => $id]);
$assignedCols = $prodCols->fetchAll(PDO::FETCH_COLUMN);

// Current images
$images = $db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order");
$images->execute([':id' => $id]);
$images = $images->fetchAll();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $slug         = trim($_POST['slug'] ?? '');
    $inspiredBy   = trim($_POST['inspired_by'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $shortDesc    = trim($_POST['short_description'] ?? '');
    $topNotes     = trim($_POST['top_notes'] ?? '');
    $heartNotes   = trim($_POST['heart_notes'] ?? '');
    $baseNotes    = trim($_POST['base_notes'] ?? '');
    $price        = (float)($_POST['price'] ?? 899);
    $comparePrice = $_POST['compare_at_price'] !== '' ? (float)$_POST['compare_at_price'] : null;
    $stock        = (int)($_POST['stock_qty'] ?? 0);
    $sku          = trim($_POST['sku'] ?? '');
    $isFeatured   = isset($_POST['is_featured'])   ? 1 : 0;
    $isNewDrop    = isset($_POST['is_new_drop'])    ? 1 : 0;
    $isTopRated   = isset($_POST['is_top_rated'])   ? 1 : 0;
    $colIds       = array_map('intval', $_POST['collections'] ?? []);

    if (!$slug) { $slug = $name; }
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    $topArr   = array_values(array_filter(array_map('trim', explode(',', $topNotes))));
    $heartArr = array_values(array_filter(array_map('trim', explode(',', $heartNotes))));
    $baseArr  = array_values(array_filter(array_map('trim', explode(',', $baseNotes))));

    if (!$name || !$slug) {
        $error = 'Name and Slug are required.';
    } else {
        try {
            $db->beginTransaction();

            $upd = $db->prepare("
                UPDATE products SET
                    slug=:slug, name=:name, inspired_by=:insp, description=:desc,
                    short_description=:sdesc,
                    top_notes=:top, heart_notes=:heart, base_notes=:base,
                    price=:price, compare_at_price=:cprice, stock_qty=:stock, sku=:sku,
                    is_featured=:feat, is_new_drop=:new, is_top_rated=:toprated, updated_at=NOW()
                WHERE id=:id
            ");
            $upd->execute([
                ':slug'     => $slug,
                ':name'     => htmlspecialchars($name),
                ':insp'     => htmlspecialchars($inspiredBy),
                ':desc'     => htmlspecialchars($description),
                ':sdesc'    => htmlspecialchars($shortDesc),
                ':top'      => json_encode($topArr),
                ':heart'    => json_encode($heartArr),
                ':base'     => json_encode($baseArr),
                ':price'    => $price,
                ':cprice'   => $comparePrice,
                ':stock'    => $stock,
                ':sku'      => htmlspecialchars($sku),
                ':feat'     => $isFeatured,
                ':new'      => $isNewDrop,
                ':toprated' => $isTopRated,
                ':id'       => $id,
            ]);

            $uploadDir = __DIR__ . '/../../api/uploads/products/';

            // Handle new image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $nextOrder = count($images);
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if (!$tmpName || $_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                    if ($_FILES['images']['size'][$i] > MAX_FILE_SIZE) continue;
                    $filename = uniqid('product_', true) . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        $db->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (:pid,:url,:ord)")
                           ->execute([':pid' => $id, ':url' => '/api/uploads/products/'.$filename, ':ord' => $nextOrder + $i]);
                    }
                }
            }

            // Handle image deletions
            $deleteIds = array_map('intval', $_POST['delete_images'] ?? []);
            if ($deleteIds) {
                $toDelete = $db->prepare("SELECT image_url FROM product_images WHERE id = :imgId AND product_id = :pid");
                foreach ($deleteIds as $imgId) {
                    $toDelete->execute([':imgId' => $imgId, ':pid' => $id]);
                    $row = $toDelete->fetch();
                    if ($row) {
                        $filePath = __DIR__ . '/../../' . ltrim($row['image_url'], '/');
                        if (file_exists($filePath)) @unlink($filePath);
                        $db->prepare("DELETE FROM product_images WHERE id = :imgId")->execute([':imgId' => $imgId]);
                    }
                }
            }

            // Content blocks
            $blockHeadings = $_POST['block_heading']      ?? [];
            $blockTexts    = $_POST['block_text']         ?? [];
            $blockLayouts  = $_POST['block_layout']       ?? [];
            $blockExisting = $_POST['block_img_existing'] ?? [];
            $bFiles        = $_FILES['block_image']       ?? [];
            $blocks        = [];
            foreach ($blockHeadings as $bi => $bHeading) {
                $bText   = trim($blockTexts[$bi] ?? '');
                $bLayout = in_array($blockLayouts[$bi] ?? '', ['left','right','top','bottom']) ? $blockLayouts[$bi] : 'left';
                $bImg    = trim($blockExisting[$bi] ?? '');
                if (!empty($bFiles['tmp_name'][$bi]) && $bFiles['error'][$bi] === UPLOAD_ERR_OK) {
                    $bExt = strtolower(pathinfo($bFiles['name'][$bi], PATHINFO_EXTENSION));
                    if (in_array($bExt, ['jpg','jpeg','png','webp']) && $bFiles['size'][$bi] <= MAX_FILE_SIZE) {
                        $bFn = 'block_' . $id . '_' . $bi . '_' . uniqid() . '.' . $bExt;
                        if (move_uploaded_file($bFiles['tmp_name'][$bi], $uploadDir . $bFn))
                            $bImg = '/api/uploads/products/' . $bFn;
                    }
                }
                if ($bText || $bImg)
                    $blocks[] = ['heading'=>htmlspecialchars(trim($bHeading)),'text'=>htmlspecialchars($bText),'image'=>$bImg,'layout'=>$bLayout];
            }
            $db->prepare("UPDATE products SET content_blocks=:cb WHERE id=:id")
               ->execute([':cb'=>json_encode($blocks),':id'=>$id]);

            // Update collections
            $db->prepare("DELETE FROM product_collections WHERE product_id = :pid")->execute([':pid' => $id]);
            if ($colIds) {
                $pivotIns = $db->prepare("INSERT IGNORE INTO product_collections (product_id, collection_id) VALUES (:pid,:cid)");
                foreach ($colIds as $cid) {
                    $pivotIns->execute([':pid' => $id, ':cid' => $cid]);
                }
            }

            $db->commit();
            $success = 'Product updated successfully.';

            // Reload data
            $product = $db->prepare("SELECT * FROM products WHERE id = :id");
            $product->execute([':id' => $id]);
            $product = $product->fetch();

            $prodCols->execute([':id' => $id]);
            $assignedCols = $prodCols->fetchAll(PDO::FETCH_COLUMN);

            $images = $db->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order");
            $images->execute([':id' => $id]);
            $images = $images->fetchAll();

        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Failed to update product. ' . (APP_ENV === 'development' ? $e->getMessage() : '');
        }
    }
}

// Decode notes for form display
$topDisplay   = implode(', ', json_decode($product['top_notes']   ?? '[]', true) ?: []);
$heartDisplay = implode(', ', json_decode($product['heart_notes'] ?? '[]', true) ?: []);
$baseDisplay  = implode(', ', json_decode($product['base_notes']  ?? '[]', true) ?: []);

$adminTitle = 'Edit: ' . htmlspecialchars($product['name']);
require_once __DIR__ . '/../../admin/includes/header.php';
?>

<?php if ($success): ?><div class="admin-alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="margin-bottom:16px">
  <a href="/admin/products.php" style="color:var(--text-muted);font-size:13px;text-decoration:none">← Back to Products</a>
</div>

<form method="POST" enctype="multipart/form-data">
  <div style="display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start">

    <!-- Left -->
    <div style="display:flex;flex-direction:column;gap:0">

      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Basic Info</h3>

        <div class="admin-form-group">
          <label class="admin-label">Product Name *</label>
          <input type="text" name="name" class="admin-input" value="<?= htmlspecialchars($product['name']) ?>" required oninput="autoSlug(this.value)">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">URL Slug *</label>
          <input type="text" name="slug" id="slug-input" class="admin-input" value="<?= htmlspecialchars($product['slug']) ?>" required>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Inspired By</label>
          <input type="text" name="inspired_by" class="admin-input" value="<?= htmlspecialchars($product['inspired_by'] ?? '') ?>" placeholder="e.g. Aventus – Creed">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Short Description <span style="color:var(--text-muted);font-weight:400">(shown on product page preview, max 200 chars)</span></label>
          <textarea name="short_description" class="admin-input" rows="2" maxlength="200"><?= htmlspecialchars($product['short_description'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Full Description</label>
          <textarea name="description" class="admin-input" rows="5"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Fragrance Notes</h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Enter notes separated by commas.</p>
        <div class="admin-form-group">
          <label class="admin-label">🔝 Top Notes</label>
          <input type="text" name="top_notes" class="admin-input" value="<?= htmlspecialchars($topDisplay) ?>">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">💛 Heart Notes</label>
          <input type="text" name="heart_notes" class="admin-input" value="<?= htmlspecialchars($heartDisplay) ?>">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">🌑 Base Notes</label>
          <input type="text" name="base_notes" class="admin-input" value="<?= htmlspecialchars($baseDisplay) ?>">
        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Product Images</h3>

        <?php if ($images): ?>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Current images — check box to delete:</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
          <?php foreach ($images as $img): ?>
          <div style="position:relative">
            <img src="<?= htmlspecialchars($img['image_url']) ?>" alt=""
                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid var(--admin-border)"
                 onerror="this.style.opacity='.3'">
            <label style="position:absolute;top:4px;right:4px;background:rgba(220,53,69,.85);border-radius:4px;cursor:pointer;padding:2px 4px;display:flex;align-items:center">
              <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>" style="display:none" onchange="this.closest('label').style.background=this.checked?'rgba(220,53,69,1)':'rgba(220,53,69,.85)'">
              <i class="ph ph-trash" style="color:#fff;font-size:12px"></i>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Upload additional images (JPG/PNG/WebP, max 5MB each):</p>
        <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp" class="admin-input" style="padding:10px" onchange="previewImages(this)">
        <div id="img-preview" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap"></div>
      </div>

      <!-- Content Blocks -->
      <div class="admin-card" style="margin-top:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:6px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">📐 Page Content Blocks</h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:18px">Add image + text sections that appear in the Description tab. Choose image position for each block.</p>
        <div id="blocks-container">
          <?php
          $existingBlocks = json_decode($product['content_blocks'] ?? '[]', true) ?: [];
          foreach ($existingBlocks as $bi => $blk):
          ?>
          <script>document.addEventListener('DOMContentLoaded',()=>addBlock(<?= json_encode($blk) ?>));</script>
          <?php endforeach; ?>
        </div>
        <button type="button" onclick="addBlock()" class="btn-admin-outline" style="width:100%;justify-content:center;margin-top:8px">
          <i class="ph ph-plus"></i> Add Image + Text Block
        </button>
      </div>
    </div>

    <!-- Right -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Pricing & Stock</h3>
        <div class="admin-form-group">
          <label class="admin-label">Price (EGP) *</label>
          <input type="number" name="price" class="admin-input" value="<?= htmlspecialchars($product['price']) ?>" step="0.01" min="0" required>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Compare-at Price (EGP) <span style="color:var(--text-muted);font-weight:400">— original price before discount (shows strikethrough + SAVE badge)</span></label>
          <input type="number" name="compare_at_price" class="admin-input"
                 value="<?= !empty($product['compare_at_price']) ? htmlspecialchars($product['compare_at_price']) : '' ?>"
                 step="0.01" min="0" placeholder="Leave empty if no discount">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Stock Quantity</label>
          <input type="number" name="stock_qty" class="admin-input" value="<?= (int)$product['stock_qty'] ?>" min="0">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">SKU</label>
          <input type="text" name="sku" class="admin-input" value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Labels</h3>
        <div style="display:flex;flex-direction:column;gap:14px">

          <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:14px">Featured on Homepage</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Shows in FEATURED PRODUCTS section</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="is_featured" <?= $product['is_featured'] ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div style="border-top:1px solid var(--admin-border);padding-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:14px">New Drop Badge</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Shows in NEW DROPS section + "NEW" badge</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="is_new_drop" <?= $product['is_new_drop'] ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div style="border-top:1px solid var(--admin-border);padding-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:14px">Pin to Top Rated</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Force into TOP RATED section (auto fills rest by rating)</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="is_top_rated" <?= $product['is_top_rated'] ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Collections</h3>
        <div style="display:flex;flex-direction:column;gap:8px">
          <?php foreach ($collections as $col): ?>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:#ccc">
            <input type="checkbox" name="collections[]" value="<?= $col['id'] ?>"
                   <?= in_array($col['id'], $assignedCols) ? 'checked' : '' ?>
                   style="accent-color:var(--accent);width:16px;height:16px">
            <?= htmlspecialchars($col['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <a href="/admin/products.php" class="btn-admin-outline" style="flex:1;justify-content:center">Cancel</a>
        <button type="submit" class="btn-admin-gold" style="flex:2">Update Product</button>
      </div>

      <div style="padding:12px;background:rgba(220,53,69,.08);border:1px solid rgba(220,53,69,.25);border-radius:8px">
        <p style="font-size:12px;color:var(--danger);margin-bottom:10px;font-weight:600">Danger Zone</p>
        <form method="POST" action="/admin/products.php" onsubmit="return confirm('Delete \'<?= htmlspecialchars(addslashes($product['name'])) ?>\'? This cannot be undone.')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id"     value="<?= $id ?>">
          <button type="submit" class="btn-admin-danger" style="width:100%">Delete Product</button>
        </form>
      </div>

    </div>
  </div>
</form>

<?php
$adminScripts = <<<'JS'
<script>
let slugManual = false;
function autoSlug(name) {
  if (!slugManual) {
    document.getElementById('slug-input').value = name.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').trim();
  }
}
document.getElementById('slug-input').addEventListener('input', () => { slugManual = true; });

function previewImages(input) {
  const preview = document.getElementById('img-preview');
  preview.innerHTML = '';
  Array.from(input.files).slice(0, 5).forEach(file => {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:6px;border:2px solid var(--accent)';
      preview.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
}

// ── Content Block Builder ──────────────────────────────────
let blockIdx = 0;
const layouts = [
  { value:'left',   icon:'◧', label:'Image Left' },
  { value:'right',  icon:'◨', label:'Image Right' },
  { value:'top',    icon:'⬒', label:'Image Top' },
  { value:'bottom', icon:'⬓', label:'Image Bottom' },
];
function addBlock(data = {}) {
  const idx    = blockIdx++;
  const layout = data.layout || 'left';
  const imgUrl = data.image  || '';
  const wrap   = document.createElement('div');
  wrap.className = 'cb-block';
  wrap.innerHTML = `
    <div class="cb-block__header">
      <span style="font-weight:700;font-size:13px;color:#ccc">Block ${idx + 1}</span>
      <button type="button" onclick="removeBlock(this)" style="background:none;border:none;color:#e55;cursor:pointer;font-size:13px"><i class="ph ph-trash"></i> Remove</button>
    </div>
    <div class="admin-form-group" style="margin-bottom:12px">
      <label class="admin-label" style="font-size:12px">Heading (optional)</label>
      <input type="text" name="block_heading[]" class="admin-input" value="${escHtml(data.heading||'')}" placeholder="e.g. Outstanding Features">
    </div>
    <div class="admin-form-group" style="margin-bottom:12px">
      <label class="admin-label" style="font-size:12px">Text Content</label>
      <textarea name="block_text[]" class="admin-input" rows="4" placeholder="Write text to display alongside the image...">${escHtml(data.text||'')}</textarea>
    </div>
    <div class="admin-form-group" style="margin-bottom:12px">
      <label class="admin-label" style="font-size:12px">Image</label>
      ${imgUrl ? `<div class="cb-img-preview"><img src="${escHtml(imgUrl)}" style="height:80px;border-radius:6px;margin-bottom:8px"><br></div>` : ''}
      <input type="file" name="block_image[${idx}]" accept=".jpg,.jpeg,.png,.webp" class="admin-input" style="padding:8px" onchange="previewBlockImg(this)">
      <input type="hidden" name="block_img_existing[]" value="${escHtml(imgUrl)}">
    </div>
    <div class="admin-form-group" style="margin-bottom:4px">
      <label class="admin-label" style="font-size:12px;margin-bottom:8px">Image Position</label>
      <div class="cb-layout-picker">
        ${layouts.map(l=>`
          <label class="cb-layout-opt ${l.value===layout?'active':''}" title="${l.label}">
            <input type="radio" name="block_layout[]" value="${l.value}" ${l.value===layout?'checked':''}
                   style="position:absolute;opacity:0" onchange="this.closest('.cb-layout-picker').querySelectorAll('.cb-layout-opt').forEach(o=>o.classList.remove('active'));this.closest('.cb-layout-opt').classList.add('active')">
            <span class="cb-layout-icon">${l.icon}</span>
            <span class="cb-layout-label">${l.label}</span>
          </label>`).join('')}
      </div>
    </div>`;
  document.getElementById('blocks-container').appendChild(wrap);
}
function removeBlock(btn) { btn.closest('.cb-block').remove(); }
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function previewBlockImg(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    let p = input.parentElement.querySelector('.cb-img-preview');
    if (!p) { p = document.createElement('div'); p.className='cb-img-preview'; input.before(p); }
    p.innerHTML = `<img src="${e.target.result}" style="height:80px;border-radius:6px;margin-bottom:8px"><br>`;
  };
  reader.readAsDataURL(input.files[0]);
}
</script>
<style>
.cb-block{background:rgba(255,255,255,0.04);border:1px solid var(--admin-border);border-radius:8px;padding:16px;margin-bottom:14px}
.cb-block__header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--admin-border)}
.cb-layout-picker{display:flex;gap:8px;flex-wrap:wrap}
.cb-layout-opt{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 14px;background:rgba(255,255,255,0.05);border:1.5px solid var(--admin-border);border-radius:8px;cursor:pointer;transition:all .18s;min-width:80px;position:relative}
.cb-layout-opt:hover{border-color:var(--accent)}
.cb-layout-opt.active{border-color:var(--accent);background:rgba(203,186,156,0.12)}
.cb-layout-icon{font-size:22px;line-height:1}
.cb-layout-label{font-size:11px;color:var(--text-muted);text-align:center}
</style>
JS;
require_once __DIR__ . '/../../admin/includes/footer.php';
?>
