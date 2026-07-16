<?php
require_once __DIR__ . '/../../admin/includes/auth_check.php';
require_once __DIR__ . '/../../api/config/config.php';
require_once __DIR__ . '/../../api/config/database.php';

$db          = Database::getInstance();
// Auto-migrate is_top_rated column if missing
try { $db->exec("ALTER TABLE products ADD COLUMN is_top_rated TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}
$collections = $db->query("SELECT * FROM collections ORDER BY sort_order")->fetchAll();
$error       = '';
$success     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $slug           = trim($_POST['slug'] ?? '');
    $inspiredBy     = trim($_POST['inspired_by'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $shortDesc      = trim($_POST['short_description'] ?? '');
    $topNotes       = trim($_POST['top_notes'] ?? '');
    $heartNotes     = trim($_POST['heart_notes'] ?? '');
    $baseNotes      = trim($_POST['base_notes'] ?? '');
    $price          = (float)($_POST['price'] ?? 899);
    $comparePrice   = $_POST['compare_at_price'] !== '' ? (float)$_POST['compare_at_price'] : null;
    $stock          = (int)($_POST['stock_qty'] ?? 100);
    $sku            = trim($_POST['sku'] ?? '');
    $isFeatured     = isset($_POST['is_featured'])  ? 1 : 0;
    $isNewDrop      = isset($_POST['is_new_drop'])   ? 1 : 0;
    $isTopRated     = isset($_POST['is_top_rated'])  ? 1 : 0;
    $colIds         = array_map('intval', $_POST['collections'] ?? []);

    // Always sanitize slug: strip anything that isn't a letter, digit or hyphen
    if (!$slug) {
        $slug = $name;
    }
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    // Parse notes (comma-separated → JSON array)
    $topArr    = array_filter(array_map('trim', explode(',', $topNotes)));
    $heartArr  = array_filter(array_map('trim', explode(',', $heartNotes)));
    $baseArr   = array_filter(array_map('trim', explode(',', $baseNotes)));

    if (!$name || !$slug) {
        $error = 'Name and Slug are required.';
    } else {
        try {
            $db->beginTransaction();

            $ins = $db->prepare("
                INSERT INTO products (slug, name, inspired_by, description, short_description,
                                      top_notes, heart_notes, base_notes,
                                      price, compare_at_price, stock_qty, sku,
                                      is_featured, is_new_drop, is_top_rated)
                VALUES (:slug,:name,:insp,:desc,:sdesc,:top,:heart,:base,:price,:cprice,:stock,:sku,:feat,:new,:toprated)
            ");
            $ins->execute([
                ':slug'     => $slug,
                ':name'     => htmlspecialchars($name),
                ':insp'     => htmlspecialchars($inspiredBy),
                ':desc'     => htmlspecialchars($description),
                ':sdesc'    => htmlspecialchars($shortDesc),
                ':top'      => json_encode(array_values($topArr)),
                ':heart'    => json_encode(array_values($heartArr)),
                ':base'     => json_encode(array_values($baseArr)),
                ':price'    => $price,
                ':cprice'   => $comparePrice,
                ':stock'    => $stock,
                ':sku'      => htmlspecialchars($sku),
                ':feat'     => $isFeatured,
                ':new'      => $isNewDrop,
                ':toprated' => $isTopRated,
            ]);

            $productId = (int)$db->lastInsertId();
            $uploadDir = __DIR__ . '/../../api/uploads/products/';

            // Handle product images
            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                    if (!$tmpName || $_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext  = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                    if ($_FILES['images']['size'][$i] > MAX_FILE_SIZE) continue;
                    $filename = uniqid('product_', true) . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        $db->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (:pid, :url, :ord)")
                           ->execute([':pid' => $productId, ':url' => '/api/uploads/products/' . $filename, ':ord' => $i]);
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
                        $bFn = 'block_' . $productId . '_' . $bi . '_' . uniqid() . '.' . $bExt;
                        if (move_uploaded_file($bFiles['tmp_name'][$bi], $uploadDir . $bFn))
                            $bImg = '/api/uploads/products/' . $bFn;
                    }
                }
                if ($bText || $bImg)
                    $blocks[] = ['heading'=>htmlspecialchars(trim($bHeading)),'text'=>htmlspecialchars($bText),'image'=>$bImg,'layout'=>$bLayout];
            }
            $db->prepare("UPDATE products SET content_blocks=:cb WHERE id=:id")
               ->execute([':cb'=>json_encode($blocks),':id'=>$productId]);

            // Collections
            if ($colIds) {
                $pivotIns = $db->prepare("INSERT IGNORE INTO product_collections (product_id, collection_id) VALUES (:pid,:cid)");
                foreach ($colIds as $cid) {
                    $pivotIns->execute([':pid' => $productId, ':cid' => $cid]);
                }
            }

            $db->commit();
            header('Location: /admin/products.php?added=1');
            exit;

        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Failed to add product. ' . (APP_ENV === 'development' ? $e->getMessage() : '');
        }
    }
}

$adminTitle = 'Add Product';
require_once __DIR__ . '/../../admin/includes/header.php';
?>

<?php if ($error): ?><div class="admin-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <div style="display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start">

    <!-- Left: Main Info -->
    <div style="display:flex;flex-direction:column;gap:0">

      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Basic Info</h3>

        <div class="admin-form-group">
          <label class="admin-label">Product Name *</label>
          <input type="text" name="name" class="admin-input" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. Carnation" required oninput="autoSlug(this.value)">
        </div>

        <div class="admin-form-group">
          <label class="admin-label">URL Slug *</label>
          <input type="text" name="slug" id="slug-input" class="admin-input" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>" placeholder="e.g. carnation-50ml" required>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Inspired By</label>
          <input type="text" name="inspired_by" class="admin-input" value="<?= htmlspecialchars($_POST['inspired_by'] ?? '') ?>" placeholder="e.g. Aventus – Creed">
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Short Description <span style="color:var(--text-muted);font-weight:400">(shown on product page preview, max 200 chars)</span></label>
          <textarea name="short_description" class="admin-input" rows="2" maxlength="200" placeholder="Brief teaser shown above Add to Cart..."><?= htmlspecialchars($_POST['short_description'] ?? '') ?></textarea>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Full Description</label>
          <textarea name="description" class="admin-input" rows="5" placeholder="Full fragrance description shown in the Description tab..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="admin-card" style="margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Fragrance Notes</h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Enter notes separated by commas. E.g: Bergamot, Citron, Pineapple</p>

        <div class="admin-form-group">
          <label class="admin-label">🔝 Top Notes</label>
          <input type="text" name="top_notes" class="admin-input" value="<?= htmlspecialchars($_POST['top_notes'] ?? '') ?>" placeholder="Bergamot, Citron, Pineapple">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">💛 Heart Notes</label>
          <input type="text" name="heart_notes" class="admin-input" value="<?= htmlspecialchars($_POST['heart_notes'] ?? '') ?>" placeholder="Rose, Jasmine, Iris">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">🌑 Base Notes</label>
          <input type="text" name="base_notes" class="admin-input" value="<?= htmlspecialchars($_POST['base_notes'] ?? '') ?>" placeholder="Sandalwood, Musk, Amber">
        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Product Images</h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Upload up to 5 images. JPG/PNG/WebP, max 5MB each. First image = main photo.</p>
        <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp" class="admin-input" style="padding:10px" onchange="previewImages(this)">
        <div id="img-preview" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap"></div>
      </div>

      <!-- Content Blocks -->
      <div class="admin-card" style="margin-top:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:6px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">📐 Page Content Blocks</h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:18px">Add image + text sections that appear in the Description tab on the product page. Control the layout for each block.</p>
        <div id="blocks-container"></div>
        <button type="button" onclick="addBlock()" class="btn-admin-outline" style="width:100%;justify-content:center;margin-top:8px">
          <i class="ph ph-plus"></i> Add Image + Text Block
        </button>
      </div>
    </div>

    <!-- Right: Sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <div class="admin-card">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase">Pricing & Stock</h3>
        <div class="admin-form-group">
          <label class="admin-label">Price (EGP) *</label>
          <input type="number" name="price" class="admin-input" value="<?= htmlspecialchars($_POST['price'] ?? '899') ?>" step="0.01" min="0" required>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Compare-at Price (EGP) <span style="color:var(--text-muted);font-weight:400">— original price before discount (shows strikethrough + SAVE badge)</span></label>
          <input type="number" name="compare_at_price" class="admin-input" value="<?= htmlspecialchars($_POST['compare_at_price'] ?? '') ?>" step="0.01" min="0" placeholder="Leave empty if no discount">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Stock Quantity</label>
          <input type="number" name="stock_qty" class="admin-input" value="<?= htmlspecialchars($_POST['stock_qty'] ?? '100') ?>" min="0">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">SKU</label>
          <input type="text" name="sku" class="admin-input" value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>" placeholder="e.g. 2017">
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
              <input type="checkbox" name="is_featured" <?= !empty($_POST['is_featured']) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div style="border-top:1px solid var(--admin-border);padding-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:14px">New Drop Badge</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Shows in NEW DROPS section + "NEW" badge</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="is_new_drop" <?= !empty($_POST['is_new_drop']) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div style="border-top:1px solid var(--admin-border);padding-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:14px">Pin to Top Rated</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Force into TOP RATED section (auto fills rest by rating)</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="is_top_rated" <?= !empty($_POST['is_top_rated']) ? 'checked' : '' ?>>
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
                   <?= in_array($col['id'], $_POST['collections'] ?? []) ? 'checked' : '' ?>
                   style="accent-color:var(--accent);width:16px;height:16px">
            <?= htmlspecialchars($col['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <a href="/admin/products.php" class="btn-admin-outline" style="flex:1;justify-content:center">Cancel</a>
        <button type="submit" class="btn-admin-gold" style="flex:2">Save Product</button>
      </div>
    </div>
  </div>
</form>

<?php
$adminScripts = <<<'JS'
<script>
function autoSlug(name) {
  const slugInput = document.getElementById('slug-input');
  if (!slugInput.dataset.manual) {
    slugInput.value = name.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim();
  }
}
document.getElementById('slug-input').addEventListener('input', function() { this.dataset.manual = 'true'; });

function previewImages(input) {
  const preview = document.getElementById('img-preview');
  preview.innerHTML = '';
  Array.from(input.files).slice(0,5).forEach(file => {
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
  const idx     = blockIdx++;
  const heading = data.heading || '';
  const text    = data.text    || '';
  const layout  = data.layout  || 'left';
  const imgUrl  = data.image   || '';

  const wrap = document.createElement('div');
  wrap.className = 'cb-block';
  wrap.dataset.idx = idx;
  wrap.innerHTML = `
    <div class="cb-block__header">
      <span style="font-weight:700;font-size:13px;color:#ccc">Block ${idx + 1}</span>
      <button type="button" onclick="removeBlock(this)" style="background:none;border:none;color:#e55;cursor:pointer;font-size:13px"><i class="ph ph-trash"></i> Remove</button>
    </div>
    <div class="admin-form-group" style="margin-bottom:12px">
      <label class="admin-label" style="font-size:12px">Heading (optional)</label>
      <input type="text" name="block_heading[]" class="admin-input" value="${escHtml(heading)}" placeholder="e.g. Outstanding Features">
    </div>
    <div class="admin-form-group" style="margin-bottom:12px">
      <label class="admin-label" style="font-size:12px">Text Content</label>
      <textarea name="block_text[]" class="admin-input" rows="4" placeholder="Write text to display alongside the image...">${escHtml(text)}</textarea>
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
        ${layouts.map(l => `
          <label class="cb-layout-opt ${l.value === layout ? 'active' : ''}" title="${l.label}">
            <input type="radio" name="block_layout[]" value="${l.value}" ${l.value === layout ? 'checked' : ''}
                   style="position:absolute;opacity:0" onchange="this.closest('.cb-layout-picker').querySelectorAll('.cb-layout-opt').forEach(o=>o.classList.remove('active'));this.closest('.cb-layout-opt').classList.add('active')">
            <span class="cb-layout-icon">${l.icon}</span>
            <span class="cb-layout-label">${l.label}</span>
          </label>`).join('')}
      </div>
    </div>
  `;
  document.getElementById('blocks-container').appendChild(wrap);
}

function removeBlock(btn) { btn.closest('.cb-block').remove(); }
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function previewBlockImg(input) {
  const preview = input.previousElementSibling?.tagName === 'INPUT' ? null : input.parentElement.querySelector('.cb-img-preview');
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
