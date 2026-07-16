<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();

// Auto-migrate is_hidden column
try { $db->exec("ALTER TABLE collections ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $_) {}

// Handle toggle hidden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_hidden') {
    $tid = (int)($_POST['id'] ?? 0);
    if ($tid > 0) {
        $db->prepare("UPDATE collections SET is_hidden = IF(is_hidden=1,0,1) WHERE id = :id")
           ->execute([':id' => $tid]);
    }
    header('Location: /admin/collections.php');
    exit;
}

// Handle delete via POST form (no JS required)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        $colRow = $db->prepare("SELECT cover_image_url FROM collections WHERE id = :id");
        $colRow->execute([':id' => $delId]);
        $colRow = $colRow->fetch();
        if ($colRow) {
            if (!empty($colRow['cover_image_url'])) {
                $imgPath = __DIR__ . '/../' . ltrim($colRow['cover_image_url'], '/');
                if (file_exists($imgPath)) @unlink($imgPath);
            }
            $db->prepare("DELETE FROM product_collections WHERE collection_id = :id")->execute([':id' => $delId]);
            $db->prepare("DELETE FROM collections WHERE id = :id")->execute([':id' => $delId]);
        }
    }
    header('Location: /admin/collections.php?deleted=1');
    exit;
}

// Handle inline sort-order save (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sort') {
    header('Content-Type: application/json');
    $id    = (int)($_POST['id']    ?? 0);
    $order = (int)($_POST['order'] ?? 0);
    if ($id > 0) {
        $db->prepare("UPDATE collections SET sort_order = :o WHERE id = :id")
           ->execute([':o' => $order, ':id' => $id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// Handle up/down sort swap
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move') {
    $id        = (int)($_POST['id']  ?? 0);
    $direction = $_POST['dir'] ?? '';  // 'up' or 'down'
    if ($id > 0 && in_array($direction, ['up','down'])) {
        $current = $db->prepare("SELECT id, sort_order FROM collections WHERE id = :id");
        $current->execute([':id' => $id]);
        $row = $current->fetch();
        if ($row) {
            $op  = $direction === 'up' ? '<' : '>';
            $ord = $direction === 'up' ? 'DESC' : 'ASC';
            $neighbor = $db->prepare("SELECT id, sort_order FROM collections WHERE sort_order {$op} :so ORDER BY sort_order {$ord} LIMIT 1");
            $neighbor->execute([':so' => $row['sort_order']]);
            $nb = $neighbor->fetch();
            if ($nb) {
                $db->prepare("UPDATE collections SET sort_order = :so WHERE id = :id")
                   ->execute([':so' => $nb['sort_order'], ':id' => $row['id']]);
                $db->prepare("UPDATE collections SET sort_order = :so WHERE id = :id")
                   ->execute([':so' => $row['sort_order'], ':id' => $nb['id']]);
            }
        }
    }
    header('Location: /admin/collections.php?moved=1');
    exit;
}

$collections = $db->query("
    SELECT c.*, COUNT(pc.product_id) AS product_count
    FROM collections c
    LEFT JOIN product_collections pc ON pc.collection_id = c.id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.id ASC
")->fetchAll();

$adminTitle = 'Collections';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['added'])): ?>
<div class="admin-alert success" id="flash-msg">✅ Collection added successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
<div class="admin-alert success" id="flash-msg">✅ Collection updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="admin-alert success" id="flash-msg">🗑 Collection deleted.</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px">
  <div>
    <h1 style="font-size:18px;font-weight:700;margin:0"><?= count($collections) ?> Collections</h1>
    <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0">Drag or use ↑↓ to reorder. Changes are saved instantly.</p>
  </div>
  <a href="/admin/collections/add.php" class="btn-admin-gold"><i class="ph ph-plus"></i> Add Collection</a>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table" id="collections-table">
    <thead>
      <tr>
        <th width="48" style="text-align:center">#</th>
        <th width="80">Cover</th>
        <th>Name</th>
        <th>Slug</th>
        <th style="text-align:center">Products</th>
        <th style="text-align:center;width:90px">Visible</th>
        <th style="text-align:center;width:130px">Sort Order</th>
        <th style="width:220px">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($collections)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:48px">No collections yet. <a href="/admin/collections/add.php" style="color:var(--accent)">Add one</a>.</td></tr>
      <?php endif; ?>
      <?php foreach ($collections as $i => $col): ?>
      <tr data-id="<?= $col['id'] ?>" data-sort="<?= (int)$col['sort_order'] ?>">

        <!-- Reorder arrows -->
        <td style="text-align:center;padding:8px 4px">
          <div style="display:flex;flex-direction:column;align-items:center;gap:2px">
            <?php if ($i > 0): ?>
            <form method="POST" style="margin:0">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= $col['id'] ?>">
              <input type="hidden" name="dir" value="up">
              <button type="submit" class="sort-arrow" title="Move up">▲</button>
            </form>
            <?php else: ?>
            <span style="display:inline-block;width:22px;height:18px"></span>
            <?php endif; ?>
            <?php if ($i < count($collections) - 1): ?>
            <form method="POST" style="margin:0">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= $col['id'] ?>">
              <input type="hidden" name="dir" value="down">
              <button type="submit" class="sort-arrow" title="Move down">▼</button>
            </form>
            <?php else: ?>
            <span style="display:inline-block;width:22px;height:18px"></span>
            <?php endif; ?>
          </div>
        </td>

        <!-- Cover image -->
        <td>
          <?php if ($col['cover_image_url']): ?>
          <img src="<?= htmlspecialchars($col['cover_image_url']) ?>" alt=""
               style="width:64px;height:46px;object-fit:cover;border-radius:6px;border:1px solid var(--admin-border)"
               onerror="this.style.opacity='.2'">
          <?php else: ?>
          <div style="width:64px;height:46px;background:var(--admin-border);border-radius:6px;display:flex;align-items:center;justify-content:center;cursor:pointer"
               onclick="document.location='/admin/collections/edit.php?id=<?= $col['id'] ?>'"
               title="Click to add cover image">
            <i class="ph ph-image-plus" style="color:var(--text-muted);font-size:20px"></i>
          </div>
          <?php endif; ?>
        </td>

        <!-- Name -->
        <td>
          <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($col['name']) ?></div>
          <?php if ($col['description']): ?>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($col['description']) ?>
          </div>
          <?php endif; ?>
        </td>

        <!-- Slug -->
        <td style="font-size:12px;color:var(--text-muted);font-family:monospace"><?= htmlspecialchars($col['slug']) ?></td>

        <!-- Product count -->
        <td style="text-align:center">
          <span style="font-weight:700;color:var(--accent);font-size:16px"><?= (int)$col['product_count'] ?></span>
          <div style="font-size:10px;color:var(--text-muted)">items</div>
        </td>

        <!-- Visible toggle -->
        <td style="text-align:center">
          <form method="POST" style="margin:0;display:inline">
            <input type="hidden" name="action" value="toggle_hidden">
            <input type="hidden" name="id" value="<?= $col['id'] ?>">
            <button type="submit"
                    style="background:none;border:none;cursor:pointer;padding:4px"
                    title="<?= $col['is_hidden'] ? 'Hidden — click to show' : 'Visible — click to hide' ?>">
              <?php if ($col['is_hidden']): ?>
              <span style="font-size:20px" title="Hidden">🔴</span>
              <?php else: ?>
              <span style="font-size:20px" title="Visible">🟢</span>
              <?php endif; ?>
            </button>
          </form>
          <div style="font-size:10px;color:var(--text-muted);margin-top:2px">
            <?= $col['is_hidden'] ? 'Hidden' : 'Visible' ?>
          </div>
        </td>

        <!-- Sort order (inline editable) -->
        <td style="text-align:center">
          <div style="display:flex;align-items:center;justify-content:center;gap:6px">
            <input type="number"
                   class="admin-input sort-input"
                   value="<?= (int)$col['sort_order'] ?>"
                   min="0"
                   data-id="<?= $col['id'] ?>"
                   style="width:60px;text-align:center;padding:6px 8px;font-size:13px"
                   title="Type a number and press Enter to save">
            <span class="sort-save-icon" data-id="<?= $col['id'] ?>" title="Click to save" style="cursor:pointer;color:var(--accent);font-size:18px;display:none">✓</span>
          </div>
        </td>

        <!-- Actions -->
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="/collections.php?slug=<?= urlencode($col['slug']) ?>" target="_blank" class="btn-admin-outline" title="View on site" style="padding:6px 10px">
              <i class="ph ph-arrow-square-out"></i>
            </a>
            <a href="/admin/collections/edit.php?id=<?= $col['id'] ?>" class="btn-admin-outline">
              <i class="ph ph-pencil"></i> Edit
            </a>
            <button type="button" class="btn-admin-danger del-trigger"
                    data-id="<?= $col['id'] ?>"
                    data-name="<?= htmlspecialchars($col['name'], ENT_QUOTES) ?>"
                    title="Delete">
              <i class="ph ph-trash"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Collection preview cards (visual overview) -->
<?php if (!empty($collections)): ?>
<div style="margin-top:28px">
  <h3 style="font-size:13px;font-weight:700;color:var(--text-muted);letter-spacing:0.08em;text-transform:uppercase;margin-bottom:14px">
    Visual Preview — How they appear on the website
  </h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
    <?php foreach ($collections as $col): ?>
    <a href="/admin/collections/edit.php?id=<?= $col['id'] ?>" style="text-decoration:none;display:block;position:relative;border-radius:10px;overflow:hidden;border:1px solid var(--admin-border);transition:border-color 0.2s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--admin-border)'">
      <div style="position:relative;aspect-ratio:4/3;background:#2a2a2a">
        <?php if ($col['cover_image_url']): ?>
        <img src="<?= htmlspecialchars($col['cover_image_url']) ?>" alt=""
             style="width:100%;height:100%;object-fit:cover"
             onerror="this.style.opacity='.1'">
        <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px">
          <i class="ph ph-image" style="font-size:28px;color:var(--text-muted)"></i>
          <span style="font-size:10px;color:var(--text-muted)">No image</span>
        </div>
        <?php endif; ?>
        <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,0.7) 0%,transparent 60%)"></div>
        <div style="position:absolute;bottom:8px;left:0;right:0;text-align:center">
          <div style="color:#fff;font-size:12px;font-weight:700;letter-spacing:0.06em;text-shadow:0 1px 3px rgba(0,0,0,0.8)">
            <?= htmlspecialchars($col['name']) ?>
          </div>
          <?php if ($col['product_count'] > 0): ?>
          <div style="color:rgba(255,255,255,0.6);font-size:10px"><?= $col['product_count'] ?> products</div>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Delete confirmation modal -->
<div id="del-modal-overlay" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.65);backdrop-filter:blur(3px);align-items:center;justify-content:center">
  <div style="background:#1A1A1A;border:1px solid var(--danger);border-radius:12px;padding:28px 32px;width:340px;max-width:90vw;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.7)">
    <div style="width:52px;height:52px;background:rgba(220,53,69,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <i class="ph ph-trash" style="font-size:24px;color:var(--danger)"></i>
    </div>
    <h3 style="color:#fff;font-size:16px;font-weight:700;margin:0 0 8px">Delete Collection?</h3>
    <p id="del-modal-name" style="color:var(--accent);font-size:14px;font-weight:600;margin:0 0 8px"></p>
    <p style="color:var(--text-muted);font-size:13px;margin:0 0 24px">This will permanently remove the collection and unlink all its products. This cannot be undone.</p>
    <form method="POST" id="del-modal-form">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="del-modal-id" value="">
      <div style="display:flex;gap:10px">
        <button type="button" id="del-modal-cancel" style="flex:1;padding:10px;background:rgba(255,255,255,0.06);color:#aaa;border:1px solid #333;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">Cancel</button>
        <button type="submit" style="flex:1;padding:10px;background:var(--danger);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-dismiss flash message
const flash = document.getElementById('flash-msg');
if (flash) setTimeout(() => flash.style.display = 'none', 4000);

// Delete modal
const delOverlay  = document.getElementById('del-modal-overlay');
const delName     = document.getElementById('del-modal-name');
const delId       = document.getElementById('del-modal-id');
const delCancel   = document.getElementById('del-modal-cancel');

function openDelModal(id, name) {
  delId.value     = id;
  delName.textContent = '"' + name + '"';
  delOverlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeDelModal() {
  delOverlay.style.display = 'none';
  document.body.style.overflow = '';
}

document.addEventListener('click', function(e) {
  const trigger = e.target.closest('.del-trigger');
  if (trigger) { openDelModal(trigger.dataset.id, trigger.dataset.name); return; }
});
delCancel.addEventListener('click', closeDelModal);
delOverlay.addEventListener('click', function(e) {
  if (e.target === delOverlay) closeDelModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDelModal();
});

// Inline sort order — show checkmark on change, save on Enter or click checkmark
document.querySelectorAll('.sort-input').forEach(input => {
  const id   = input.dataset.id;
  const icon = document.querySelector(`.sort-save-icon[data-id="${id}"]`);

  input.addEventListener('input', () => {
    if (icon) icon.style.display = 'inline';
  });

  input.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); saveSort(id, input.value, icon, input); }
  });

  if (icon) {
    icon.addEventListener('click', () => saveSort(id, input.value, icon, input));
  }
});

function saveSort(id, val, icon, input) {
  const body = new URLSearchParams({ action: 'sort', id, order: parseInt(val) });
  fetch('/admin/collections.php', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.ok && icon) {
        icon.style.color = '#28A745';
        icon.textContent = '✓';
        setTimeout(() => { icon.style.display = 'none'; icon.style.color = 'var(--accent)'; }, 1200);
      }
    })
    .catch(() => {});
}
</script>

<style>
.sort-arrow {
  background: transparent;
  border: 1px solid var(--admin-border);
  color: var(--text-muted);
  width: 22px;
  height: 18px;
  font-size: 9px;
  border-radius: 3px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  line-height: 1;
  transition: all 0.15s;
}
.sort-arrow:hover { background: var(--accent); color: #000; border-color: var(--accent); }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
