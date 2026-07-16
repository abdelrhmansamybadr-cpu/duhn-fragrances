<?php
/**
 * DUHN FRAGRANCES — Full-Screen Policy/About Page Editor
 * Same concept as page-editor.php but for static pages (About, Shipping, Exchange, Refill)
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$allowed = ['about' => ['📖','About DUHN','/about.php'], 'shipping' => ['🚚','Shipping Policy','/shipping-policy.php'], 'exchange' => ['🔄','Exchange Policy','/exchange-policy.php'], 'refill' => ['♻️','Refill Policy','/refill-policy.php']];
$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'about');
if (!array_key_exists($tab, $allowed)) $tab = 'about';
[$tabIcon, $tabLabel, $tabUrl] = $allowed[$tab];

$db   = Database::getInstance();
$rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
$s    = [];
foreach ($rows as $r) { $s[$r['key']] = $r['value']; }

$defContent = [
    'about'    => '<h2>Who We Are</h2><p>DUHN FRAGRANCES is a premium Egyptian fragrance brand born from a passion for luxury scent and accessible elegance.</p>',
    'shipping' => '<h3>📦 Delivery Timeline</h3><p>Orders are processed and delivered within <strong>2 to 5 business days</strong>.</p>',
    'exchange' => '<p>🚫 <strong>DUHN FRAGRANCES does not accept exchanges or returns</strong> once a purchase is completed.</p>',
    'refill'   => '<p>♻️ <strong>DUHN FRAGRANCES offers a refill service</strong> for eligible bottles.</p>',
];

$titleVal    = htmlspecialchars($s["page_{$tab}_title"]    ?? $tabLabel);
$subtitleVal = htmlspecialchars($s["page_{$tab}_subtitle"] ?? '');
$contentVal  = $s["page_{$tab}_content"]  ?? $defContent[$tab];
$customTpl   = json_decode($s["policy_tpl_{$tab}"] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Page Editor — <?= $tabLabel ?> | DUHN Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gold:#F8C417;--dark:#0C0C0C;--bar:#111;--border:rgba(255,255,255,.08);--text:#e8e8e8;--muted:#666}
html,body{height:100%;background:#f0ede8;font-family:'Jost',sans-serif;overflow-x:hidden}

/* TOP BAR */
.pe-bar{position:fixed;top:0;left:0;right:0;z-index:1000;height:54px;background:var(--dark);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 16px;box-shadow:0 2px 20px rgba(0,0,0,.6)}
.pe-bar__logo{font-family:'Montserrat',sans-serif;font-size:14px;font-weight:700;color:var(--gold);letter-spacing:.12em;margin-right:10px}
.pe-bar__sep{width:1px;height:20px;background:var(--border);margin:0 10px}
.pe-bar__crumb{font-size:12px;color:#666}
.pe-bar__crumb span{color:#ccc;font-weight:500}
.pe-bar__right{display:flex;align-items:center;gap:8px;margin-left:auto}
.pe-status{font-size:11px;color:var(--muted);padding:4px 12px;border:1px solid transparent;border-radius:20px;transition:all .3s;white-space:nowrap}
.pe-status.saved{color:#6fcf97;border-color:rgba(111,207,151,.25);background:rgba(111,207,151,.07)}
.pe-status.dirty{color:#f2c94c;border-color:rgba(242,201,76,.25);background:rgba(242,201,76,.07)}
.pe-status.saving{color:var(--muted)}
.pe-status.error{color:#eb5757;border-color:rgba(235,87,87,.25);background:rgba(235,87,87,.07)}
.pe-btn{display:inline-flex;align-items:center;gap:6px;border:none;border-radius:6px;cursor:pointer;font-family:'Jost',sans-serif;font-size:12px;font-weight:600;letter-spacing:.04em;padding:8px 16px;transition:all .2s;white-space:nowrap;text-decoration:none}
.pe-btn--back{background:rgba(255,255,255,.05);border:1px solid var(--border);color:#888}
.pe-btn--back:hover{color:#fff;background:rgba(255,255,255,.1)}
.pe-btn--preview{background:rgba(248,196,23,.08);border:1px solid rgba(248,196,23,.2);color:var(--gold)}
.pe-btn--preview:hover{background:rgba(248,196,23,.18)}
.pe-btn--save{background:var(--gold);color:#000}
.pe-btn--save:hover{background:#ffd740;box-shadow:0 4px 16px rgba(248,196,23,.35)}

/* PAGE SWITCHER BAR */
.pe-switcher{position:fixed;top:54px;left:0;right:0;z-index:999;height:44px;background:#181818;border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 8px;gap:4px}
.pe-sw-tab{display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;color:#555;text-decoration:none;transition:all .2s;border:1px solid transparent}
.pe-sw-tab:hover{color:#888;background:rgba(255,255,255,.04)}
.pe-sw-tab.active{color:var(--gold);background:rgba(248,196,23,.08);border-color:rgba(248,196,23,.2)}
.pe-sw-sep{width:1px;height:16px;background:var(--border);margin:0 4px;flex-shrink:0}

/* EDITOR TOOLBAR */
.pe-toolbar-wrap{position:fixed;top:98px;left:0;right:0;z-index:998;background:#fff;border-bottom:2px solid #e8e4df;display:flex;align-items:center;padding:0 8px;height:46px;gap:2px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.pe-tb-select{background:transparent;border:1px solid #ddd;border-radius:5px;color:#333;cursor:pointer;font-family:'Jost',sans-serif;font-size:12px;padding:5px 8px;outline:none;min-width:110px}
.pe-tb-sep{width:1px;height:22px;background:#e0ddd8;margin:0 4px;flex-shrink:0}
.pe-tb-btn{background:transparent;border:none;border-radius:5px;color:#555;cursor:pointer;font-size:16px;padding:6px 8px;transition:all .15s;display:flex;align-items:center;justify-content:center;line-height:1}
.pe-tb-btn:hover{background:rgba(200,160,48,.12);color:#C8A030}
.pe-html-btn{margin-left:auto;display:flex;align-items:center;gap:5px;background:#f5f3ef;border:1px solid #ddd;border-radius:5px;color:#777;cursor:pointer;font-size:11px;font-family:'Jost',sans-serif;padding:5px 12px;font-weight:600;letter-spacing:.04em;transition:all .2s}
.pe-html-btn:hover,.pe-html-btn.active{background:#333;border-color:#333;color:#fff}
.pe-wc{font-size:10px;color:#bbb;letter-spacing:.04em;margin-left:6px;white-space:nowrap}

/* MAIN */
.pe-main{padding-top:144px;min-height:100vh;display:flex;justify-content:center}

/* SIDEBAR */
.pe-sidebar{width:220px;flex-shrink:0;padding:14px 12px;position:sticky;top:144px;align-self:flex-start;max-height:calc(100vh - 144px);overflow-y:auto}
.pe-sidebar::-webkit-scrollbar{width:3px}
.pe-sidebar::-webkit-scrollbar-thumb{background:#ddd;border-radius:3px}
.pe-tabs{display:flex;gap:3px;margin-bottom:12px;background:#e8e5e0;border-radius:8px;padding:3px}
.pe-tab{flex:1;border:none;border-radius:6px;cursor:pointer;font-family:'Jost',sans-serif;font-size:11px;font-weight:700;letter-spacing:.04em;padding:7px 4px;transition:all .2s;background:transparent;color:#888}
.pe-tab.active{background:#fff;color:#C8A030;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.pe-sidebar__title{font-size:10px;font-weight:700;letter-spacing:.12em;color:#999;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid rgba(0,0,0,.08);display:flex;align-items:center;gap:6px}
.pe-block-btn{display:flex;align-items:center;gap:9px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:8px;cursor:pointer;padding:9px 10px;margin-bottom:5px;transition:all .18s;font-size:12px;font-weight:500;color:#444;box-shadow:0 1px 4px rgba(0,0,0,.05);width:100%;text-align:left}
.pe-block-btn:hover{border-color:#C8A030;color:#C8A030;box-shadow:0 3px 12px rgba(200,160,48,.15);transform:translateX(2px)}
.pe-block-btn i{font-size:16px;color:#C8A030;flex-shrink:0}
.pe-block-lbl{flex:1}
.pe-block-desc{font-size:10px;color:#aaa;margin-top:1px}

/* Templates */
.tpl-section{font-size:10px;font-weight:700;letter-spacing:.1em;color:#bbb;text-transform:uppercase;margin:10px 0 7px;padding-bottom:5px;border-bottom:1px solid rgba(0,0,0,.07)}
.tpl-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px}
.tpl-card{background:#fff;border:2px solid rgba(0,0,0,.07);border-radius:8px;cursor:pointer;text-align:center;padding:9px 5px;transition:all .18s}
.tpl-card:hover{border-color:#C8A030;transform:translateY(-1px);box-shadow:0 4px 14px rgba(200,160,48,.14)}
.tpl-card__emoji{font-size:18px;display:block;margin-bottom:3px}
.tpl-card__name{font-size:9px;font-weight:700;color:#333;line-height:1.3}
.tpl-card__desc{font-size:8px;color:#aaa;margin-top:2px;line-height:1.3}
.tpl-save-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;background:#f8f6f2;border:1px dashed #C8A030;border-radius:7px;color:#C8A030;cursor:pointer;font-family:'Jost',sans-serif;font-size:11px;font-weight:700;padding:9px;transition:all .2s;letter-spacing:.04em;margin-bottom:8px}
.tpl-save-btn:hover{background:#C8A030;color:#000}
.tpl-custom-item{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid rgba(0,0,0,.07);border-radius:7px;padding:8px 9px;margin-bottom:5px;cursor:pointer;transition:all .18s}
.tpl-custom-item:hover{border-color:#C8A030}
.tpl-custom-name{flex:1;font-size:11px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tpl-custom-date{font-size:9px;color:#bbb;white-space:nowrap}
.tpl-custom-del{background:transparent;border:none;color:#ccc;cursor:pointer;font-size:14px;padding:2px 4px;border-radius:4px;transition:color .15s;flex-shrink:0}
.tpl-custom-del:hover{color:#eb5757}

/* CANVAS */
.pe-canvas{flex:1;max-width:800px;padding:20px 16px 80px}
.pe-editor-box{background:#fff;border-radius:10px;box-shadow:0 4px 32px rgba(0,0,0,.08);overflow:hidden}
.pe-editor{min-height:560px;outline:none;padding:40px 48px;font-family:'Jost',sans-serif;font-size:15px;line-height:1.85;color:#1a1a1a;cursor:text}
.pe-editor:empty::before{content:attr(data-ph);color:#bbb;pointer-events:none}
.pe-editor h2{font-size:22px;font-weight:700;margin:0 0 12px;letter-spacing:.03em}
.pe-editor h3{font-size:17px;font-weight:600;margin:18px 0 8px;color:#1a1a1a}
.pe-editor h4{font-size:14px;font-weight:600;margin:14px 0 6px}
.pe-editor p{margin:0 0 12px}
.pe-editor blockquote{border-left:4px solid #C8A030;padding:14px 20px;margin:18px 0;background:#fdfbf6;border-radius:0 8px 8px 0}
.pe-editor ul,.pe-editor ol{padding-left:22px;margin:0 0 12px}
.pe-editor a{color:#C8A030}
.pe-editor hr{border:none;border-top:1px solid #eee;margin:24px 0}
.pe-editor img{max-width:100%;border-radius:8px;margin:8px 0;display:block}
.pe-statusbar{display:flex;align-items:center;justify-content:space-between;background:#fafaf8;border-top:1px solid #f0ede8;padding:8px 48px}
.pe-statusbar-left{font-size:10px;color:#bbb;letter-spacing:.04em}
.pe-statusbar-right{display:flex;gap:8px}
.pe-action-btn{display:flex;align-items:center;gap:5px;background:transparent;border:1px solid #e8e4df;border-radius:5px;color:#999;cursor:pointer;font-family:'Jost',sans-serif;font-size:11px;padding:4px 10px;transition:all .15s}
.pe-action-btn:hover{border-color:#C8A030;color:#C8A030}
.pe-code{display:none;width:100%;min-height:560px;background:#1a1a1a;border:none;color:#88c8a0;font-family:'Courier New',monospace;font-size:12px;line-height:1.7;outline:none;padding:32px 48px;resize:vertical}

/* RIGHT PANEL */
.pe-panel{width:236px;flex-shrink:0;padding:14px 12px;position:sticky;top:144px;align-self:flex-start;max-height:calc(100vh - 144px);overflow-y:auto}
.pe-panel::-webkit-scrollbar{width:3px}
.pe-panel::-webkit-scrollbar-thumb{background:#ddd;border-radius:3px}
.pe-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:14px;margin-bottom:10px;box-shadow:0 1px 6px rgba(0,0,0,.05)}
.pe-card__title{font-size:10px;font-weight:700;letter-spacing:.1em;color:#999;text-transform:uppercase;margin-bottom:10px;padding-bottom:7px;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;gap:6px}
.pe-card label{font-size:11px;font-weight:600;color:#555;margin-bottom:4px;display:block}
.pe-card input,.pe-card textarea{width:100%;background:#f8f8f8;border:1px solid #e0e0e0;border-radius:5px;color:#222;font-family:'Jost',sans-serif;font-size:12px;outline:none;padding:6px 9px;margin-bottom:9px;transition:border-color .2s}
.pe-card input:focus,.pe-card textarea:focus{border-color:#C8A030;background:#fff}
.pe-card__btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;background:#1a1a1a;color:#fff;border:none;border-radius:7px;cursor:pointer;font-family:'Jost',sans-serif;font-size:12px;font-weight:600;padding:9px;transition:all .2s;text-decoration:none;letter-spacing:.04em;margin-top:4px}
.pe-card__btn:hover{background:#333}
.pe-card__btn.gold{background:#C8A030;color:#000}
.pe-card__btn.gold:hover{background:#f0c040}
.pe-card .shortcut{font-size:11px;color:#888;line-height:2}
.pe-card kbd{background:#f0ede8;border:1px solid #ddd;border-radius:3px;padding:1px 5px;font-size:10px}

/* PAGES NAV in right panel */
.pe-page-nav-item{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:6px;font-size:12px;text-decoration:none;margin-bottom:3px;transition:all .15s}
.pe-page-nav-item.active{color:#C8A030;background:rgba(200,160,48,.07);font-weight:600}
.pe-page-nav-item:not(.active){color:#888}
.pe-page-nav-item:hover:not(.active){color:#555;background:rgba(0,0,0,.03)}

/* TOAST */
.pe-toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(80px);background:#1a1a1a;color:#fff;border-radius:8px;padding:12px 24px;font-size:13px;font-weight:500;box-shadow:0 8px 32px rgba(0,0,0,.4);opacity:0;transition:all .35s;pointer-events:none;z-index:9999;display:flex;align-items:center;gap:10px}
.pe-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.pe-toast.ok i{color:#6fcf97}
.pe-toast.err i{color:#eb5757}
#pe-file-input{display:none}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="pe-bar">
  <a href="/admin/pages.php?tab=<?= $tab ?>" class="pe-btn pe-btn--back"><i class="ph ph-arrow-left"></i></a>
  <div class="pe-bar__sep"></div>
  <div class="pe-bar__logo">DUHN</div>
  <div class="pe-bar__sep"></div>
  <div class="pe-bar__crumb">Pages › <span><?= $tabIcon ?> <?= $tabLabel ?></span></div>
  <div class="pe-bar__right">
    <div class="pe-status saved" id="pe-status"><i class="ph ph-check-circle"></i> Saved</div>
    <a href="<?= $tabUrl ?>" target="_blank" class="pe-btn pe-btn--preview"><i class="ph ph-arrow-square-out"></i> Preview</a>
    <button class="pe-btn pe-btn--save" onclick="savePage()"><i class="ph ph-floppy-disk"></i> Save Page</button>
  </div>
</div>

<!-- PAGE SWITCHER BAR -->
<div class="pe-switcher">
  <?php foreach ($allowed as $key => [$ico, $lbl]): ?>
  <a href="/admin/policy-editor.php?tab=<?= $key ?>" class="pe-sw-tab <?= $key === $tab ? 'active' : '' ?>">
    <?= $ico ?> <?= $lbl ?>
  </a>
  <?php endforeach; ?>
  <div class="pe-sw-sep"></div>
  <a href="/admin/pages.php" class="pe-sw-tab" style="color:#555;font-size:11px"><i class="ph ph-list"></i> All Pages</a>
</div>

<!-- EDITOR TOOLBAR -->
<div class="pe-toolbar-wrap">
  <select class="pe-tb-select" onchange="cmd('formatBlock',this.value);this.value=''">
    <option value="">¶ Style</option>
    <option value="p">Paragraph</option>
    <option value="h2">Heading 2</option>
    <option value="h3">Heading 3</option>
    <option value="h4">Heading 4</option>
    <option value="blockquote">Blockquote</option>
  </select>
  <div class="pe-tb-sep"></div>
  <button type="button" class="pe-tb-btn" onclick="cmd('bold')" title="Bold"><i class="ph-bold ph-text-b"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('italic')" title="Italic"><i class="ph-bold ph-text-italic"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('underline')" title="Underline"><i class="ph-bold ph-text-underline"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('strikeThrough')" title="Strike"><i class="ph-bold ph-text-strikethrough"></i></button>
  <div class="pe-tb-sep"></div>
  <button type="button" class="pe-tb-btn" onclick="cmd('justifyLeft')" title="Left"><i class="ph-bold ph-text-align-left"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('justifyCenter')" title="Center"><i class="ph-bold ph-text-align-center"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('justifyRight')" title="Right"><i class="ph-bold ph-text-align-right"></i></button>
  <div class="pe-tb-sep"></div>
  <button type="button" class="pe-tb-btn" onclick="cmd('insertUnorderedList')" title="Bullets"><i class="ph-bold ph-list-bullets"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('insertOrderedList')" title="Numbers"><i class="ph-bold ph-list-numbers"></i></button>
  <div class="pe-tb-sep"></div>
  <button type="button" class="pe-tb-btn" onclick="insertLink()" title="Link"><i class="ph-bold ph-link"></i></button>
  <button type="button" class="pe-tb-btn" onclick="insertImgUrl()" title="Image URL"><i class="ph-bold ph-image"></i></button>
  <button type="button" class="pe-tb-btn" onclick="document.getElementById('pe-file-input').click()" title="Upload image"><i class="ph-bold ph-upload-simple"></i></button>
  <input type="file" id="pe-file-input" accept="image/*" onchange="uploadImg(this)">
  <button type="button" class="pe-tb-btn" onclick="cmd('insertHorizontalRule')" title="Divider"><i class="ph-bold ph-minus"></i></button>
  <div class="pe-tb-sep"></div>
  <button type="button" class="pe-tb-btn" onclick="cmd('undo')" title="Undo"><i class="ph-bold ph-arrow-u-up-left"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('redo')" title="Redo"><i class="ph-bold ph-arrow-u-up-right"></i></button>
  <button type="button" class="pe-html-btn" id="pe-html-btn" onclick="toggleCode()"><i class="ph ph-code"></i> HTML</button>
  <span class="pe-wc" id="pe-wc">0 words</span>
</div>

<!-- MAIN -->
<div class="pe-main">

  <!-- LEFT: Blocks + Templates -->
  <div class="pe-sidebar">
    <div class="pe-tabs">
      <button class="pe-tab active" onclick="switchTab('blocks')" id="tab-btn-blocks"><i class="ph ph-squares-four"></i> Blocks</button>
      <button class="pe-tab" onclick="switchTab('templates')" id="tab-btn-templates"><i class="ph ph-layout"></i> Templates</button>
    </div>

    <!-- BLOCKS -->
    <div id="tab-blocks">
      <div class="pe-sidebar__title"><i class="ph ph-squares-four"></i> Insert Block</div>
      <?php
      $blocks = [
        ['hero2',    'ph-image-square',     'Dark Hero',      'Full-width dark heading'],
        ['2col',     'ph-columns',          '2 Columns',      'Side-by-side text'],
        ['checklist','ph-check-square',     'Checklist',      'Tick list rows'],
        ['quote',    'ph-quotes',           'Quote Block',    'Highlighted quote'],
        ['info',     'ph-info',             'Info Box',       'Note / highlight box'],
        ['steps',    'ph-list-numbers',     'Step Guide',     'Numbered steps'],
        ['divider',  'ph-minus',            'Gold Divider',   'Section separator'],
        ['cta2',     'ph-megaphone-simple', 'CTA Banner',     'Call-to-action box'],
        ['spacer',   'ph-arrows-out-line-vertical','Spacer',  'Vertical space'],
      ];
      foreach ($blocks as [$key, $icon, $label, $desc]): ?>
      <button type="button" class="pe-block-btn" onclick="insertBlock('<?= $key ?>')">
        <i class="ph <?= $icon ?>"></i>
        <div class="pe-block-lbl"><?= $label ?><div class="pe-block-desc"><?= $desc ?></div></div>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- TEMPLATES -->
    <div id="tab-templates" style="display:none">
      <button type="button" class="tpl-save-btn" onclick="saveAsTemplate()"><i class="ph ph-floppy-disk"></i> Save Current as Template</button>
      <div class="tpl-section">Built-in — <?= $tabLabel ?></div>
      <div class="tpl-grid" id="tpl-built-grid"></div>
      <div class="tpl-section">My Saved Templates</div>
      <div id="tpl-custom-list"></div>
    </div>
  </div>

  <!-- CENTER: Editor -->
  <div class="pe-canvas">
    <div class="pe-editor-box">
      <div class="pe-editor" id="pe-editor" contenteditable="true"
           data-ph="Start writing your <?= $tabLabel ?> content here…"><?= $contentVal ?></div>
      <textarea class="pe-code" id="pe-code"><?= htmlspecialchars($contentVal) ?></textarea>
      <div class="pe-statusbar">
        <div class="pe-statusbar-left"><span id="pe-wc2">0 words</span> &nbsp;·&nbsp; <span id="pe-chars">0 chars</span></div>
        <div class="pe-statusbar-right">
          <button type="button" class="pe-action-btn" onclick="cmd('removeFormat')"><i class="ph ph-eraser"></i> Clear format</button>
          <button type="button" class="pe-action-btn" onclick="cmd('selectAll')"><i class="ph ph-selection-all"></i> Select all</button>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Settings panel -->
  <div class="pe-panel">

    <!-- Page Settings -->
    <div class="pe-card">
      <div class="pe-card__title"><i class="ph ph-gear"></i> Page Settings</div>
      <label>Page Title</label>
      <input type="text" id="pe-title" value="<?= $titleVal ?>" placeholder="<?= $tabLabel ?>">
      <label>Subtitle / Tagline</label>
      <input type="text" id="pe-subtitle" value="<?= $subtitleVal ?>" placeholder="Short tagline (optional)">
      <button type="button" class="pe-card__btn gold" onclick="savePage()"><i class="ph ph-floppy-disk"></i> Save All</button>
      <a href="<?= $tabUrl ?>" target="_blank" class="pe-card__btn" style="margin-top:6px"><i class="ph ph-arrow-square-out"></i> View Live Page</a>
    </div>

    <!-- All Pages -->
    <div class="pe-card">
      <div class="pe-card__title"><i class="ph ph-files"></i> All Pages</div>
      <?php foreach ($allowed as $key => [$ico, $lbl]): ?>
      <a href="/admin/policy-editor.php?tab=<?= $key ?>" class="pe-page-nav-item <?= $key === $tab ? 'active' : '' ?>">
        <?= $ico ?> <?= $lbl ?>
        <?php if ($key === $tab): ?><span style="margin-left:auto;font-size:9px;background:#C8A030;color:#000;border-radius:3px;padding:1px 5px;font-weight:700">EDITING</span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Shortcuts -->
    <div class="pe-card">
      <div class="pe-card__title"><i class="ph ph-keyboard"></i> Shortcuts</div>
      <div class="shortcut">
        <kbd>Ctrl</kbd>+<kbd>S</kbd> — Save<br>
        <kbd>Ctrl</kbd>+<kbd>B</kbd> — Bold<br>
        <kbd>Ctrl</kbd>+<kbd>I</kbd> — Italic<br>
        <kbd>Ctrl</kbd>+<kbd>Z</kbd> — Undo<br>
        <kbd>Ctrl</kbd>+<kbd>Y</kbd> — Redo
      </div>
    </div>
  </div>

</div>
<div class="pe-toast" id="pe-toast"><i class="ph ph-check-circle" id="pe-toast-icon"></i><span id="pe-toast-msg"></span></div>

<script>
const editor  = document.getElementById('pe-editor');
const codeEl  = document.getElementById('pe-code');
const TAB     = '<?= $tab ?>';
let inCode    = false;
let dirty     = false;
let toastTimer;

function focusEditor(){ if(!inCode) editor.focus(); }
function cmd(c,v){ focusEditor(); document.execCommand(c,false,v||null); markDirty(); }
function markDirty(){ dirty=true; setStatus('dirty'); updateWC(); }
function getContent(){ return inCode ? codeEl.value : editor.innerHTML; }

function updateWC(){
  const t=editor.innerText.trim(), w=t?t.split(/\s+/).length:0;
  const wc=w+(w===1?' word':' words');
  document.getElementById('pe-wc').textContent=wc;
  document.getElementById('pe-wc2').textContent=wc;
  document.getElementById('pe-chars').textContent=t.length.toLocaleString()+' chars';
}
function setStatus(s,msg){
  const el=document.getElementById('pe-status');
  el.className='pe-status '+s;
  const m={saved:'<i class="ph ph-check-circle"></i> Saved',dirty:'<i class="ph ph-pencil-simple"></i> Unsaved',saving:'Saving…',error:'<i class="ph ph-warning-circle"></i> '+(msg||'Error')};
  el.innerHTML=m[s]||msg;
}
function showToast(msg,ok=true){
  const t=document.getElementById('pe-toast'),ic=document.getElementById('pe-toast-icon'),tx=document.getElementById('pe-toast-msg');
  t.className='pe-toast show '+(ok?'ok':'err');
  ic.className=ok?'ph ph-check-circle':'ph ph-warning-circle';
  tx.textContent=msg;
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>{t.className='pe-toast';},3500);
}
function toggleCode(){
  const btn=document.getElementById('pe-html-btn');
  if(!inCode){ codeEl.value=editor.innerHTML; editor.style.display='none'; codeEl.style.display='block'; btn.classList.add('active'); inCode=true; codeEl.oninput=()=>{dirty=true;setStatus('dirty');}; }
  else { editor.innerHTML=codeEl.value; editor.style.display='block'; codeEl.style.display='none'; btn.classList.remove('active'); inCode=false; updateWC(); }
}
function insertLink(){
  focusEditor();
  const sel=window.getSelection(); let node=sel?.anchorNode;
  while(node&&node.nodeName!=='A'&&node!==editor) node=node.parentNode;
  if(node&&node.nodeName==='A'){ const nu=prompt('Edit URL:',node.getAttribute('href')||''); if(nu!==null) node.setAttribute('href',nu); markDirty(); }
  else { const url=prompt('Link URL:','https://'); if(!url) return; const text=(sel?.toString())||prompt('Link text:','Click here')||'Click here'; cmd('insertHTML',`<a href="${url.replace(/"/g,'&quot;')}" style="color:#C8A030">${text}</a>`); }
}
function insertImgUrl(){ const url=prompt('Image URL:','https://'); if(!url) return; cmd('insertHTML',`<img src="${url.replace(/"/g,'&quot;')}" style="max-width:100%;border-radius:8px;display:block;margin:12px 0" alt="">`); }
function uploadImg(input){
  if(!input.files[0]) return;
  const fd=new FormData(); fd.append('pb_image',input.files[0]);
  fetch('/admin/actions/pb_upload.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.url) cmd('insertHTML',`<img src="${d.url}" style="max-width:100%;border-radius:8px;display:block;margin:12px 0" alt="">`); else showToast('Upload failed',false); }).catch(()=>showToast('Upload error',false));
  input.value='';
}

/* SIDEBAR TABS */
function switchTab(name){
  ['blocks','templates'].forEach(t=>{ document.getElementById('tab-'+t).style.display=t===name?'block':'none'; document.getElementById('tab-btn-'+t).classList.toggle('active',t===name); });
}

/* BLOCKS */
const BLOCKS={
hero2:`<div style="background:linear-gradient(135deg,#0a0a0a,#1a1410);border-radius:12px;padding:44px 32px;text-align:center;margin:0 0 20px"><p style="color:#F8C417;font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin:0 0 12px">DUHN FRAGRANCES</p><h2 style="color:#fff;font-size:26px;font-weight:700;margin:0 0 12px">Page Heading Here</h2><p style="color:rgba(255,255,255,.65);font-size:15px;max-width:420px;display:inline-block;line-height:1.75;margin:0 0 24px">Add your intro text here.</p><br><a href="/collections.php" style="display:inline-block;background:#F8C417;color:#000;padding:11px 26px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.06em;font-size:13px">Explore Now</a></div>`,
'2col':`<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:16px 0"><div><h3 style="font-size:17px;font-weight:600;margin:0 0 10px">Left Column</h3><p style="color:#555;font-size:14px;line-height:1.75">Content here.</p></div><div><h3 style="font-size:17px;font-weight:600;margin:0 0 10px">Right Column</h3><p style="color:#555;font-size:14px;line-height:1.75">Content here.</p></div></div>`,
checklist:`<ul style="list-style:none;padding:0;margin:20px 0"><li style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f0f0f0"><span style="color:#C8A030;font-size:18px;flex-shrink:0;line-height:1">✓</span><div><strong>Point Title</strong><br><span style="font-size:13px;color:#777">Supporting detail here.</span></div></li><li style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f0f0f0"><span style="color:#C8A030;font-size:18px;flex-shrink:0;line-height:1">✓</span><div><strong>Point Title</strong><br><span style="font-size:13px;color:#777">Supporting detail here.</span></div></li><li style="display:flex;gap:12px;padding:12px 0"><span style="color:#C8A030;font-size:18px;flex-shrink:0;line-height:1">✓</span><div><strong>Point Title</strong><br><span style="font-size:13px;color:#777">Supporting detail here.</span></div></li></ul>`,
quote:`<blockquote style="border-left:4px solid #C8A030;padding:16px 20px;margin:20px 0;background:#fdfbf6;border-radius:0 8px 8px 0"><p style="font-style:italic;color:#333;font-size:16px;line-height:1.7;margin:0 0 8px">"Write your quote here."</p><cite style="font-size:12px;color:#999;font-style:normal">— Name or Source</cite></blockquote>`,
info:`<div style="background:#fff8e6;border:1px solid rgba(200,160,48,.3);border-radius:8px;padding:16px 20px;margin:16px 0;display:flex;gap:12px;align-items:flex-start"><span style="font-size:20px;flex-shrink:0">ℹ️</span><div><strong style="color:#C8A030">Important Note</strong><p style="margin:4px 0 0;font-size:13px;color:#555">Add your important message or notice here.</p></div></div>`,
steps:`<ol style="list-style:none;padding:0;margin:20px 0"><li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><div style="width:36px;height:36px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:15px">1</div><div><strong>Step Title</strong><p style="margin:4px 0 0;font-size:13px;color:#666">Explain this step.</p></div></li><li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><div style="width:36px;height:36px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:15px">2</div><div><strong>Step Title</strong><p style="margin:4px 0 0;font-size:13px;color:#666">Explain this step.</p></div></li><li style="display:flex;gap:14px;padding:14px 0"><div style="width:36px;height:36px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:15px">3</div><div><strong>Step Title</strong><p style="margin:4px 0 0;font-size:13px;color:#666">Explain this step.</p></div></li></ol>`,
divider:`<div style="display:flex;align-items:center;gap:18px;margin:28px 0"><div style="flex:1;height:1px;background:rgba(200,160,48,.3)"></div><span style="font-size:11px;letter-spacing:.14em;color:#C8A030;text-transform:uppercase;font-weight:600">Section Title</span><div style="flex:1;height:1px;background:rgba(200,160,48,.3)"></div></div>`,
cta2:`<div style="background:linear-gradient(135deg,rgba(200,160,48,.10),rgba(200,160,48,.03));border:1px solid rgba(200,160,48,.28);border-radius:12px;padding:32px;text-align:center;margin:24px 0"><h3 style="color:#C8A030;margin:0 0 10px;font-size:20px;font-weight:700">CTA Heading</h3><p style="color:#666;margin:0 0 22px;font-size:14px">Add supporting copy here.</p><a href="/collections.php" style="display:inline-block;background:#C8A030;color:#000;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.05em;font-size:13px">SHOP NOW</a></div>`,
spacer:`<div style="height:40px"></div>`
};
function insertBlock(type){
  const html=BLOCKS[type]; if(!html) return;
  focusEditor();
  if(!inCode) cmd('insertHTML',html);
  else codeEl.value+='\n'+html;
  markDirty();
}

/* BUILT-IN TEMPLATES — contextual per tab */
const ALL_TEMPLATES = {
about:[
  {key:'brand-story',emoji:'🏢',name:'Brand Story',desc:'Full brand narrative',html:`<div style="text-align:center;padding:32px 0 24px"><p style="color:#C8A030;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin:0 0 14px">OUR STORY</p><h2 style="font-size:28px;font-weight:700;color:#1a1a1a;margin:0 0 14px">Born in Egypt. Made for the World.</h2><div style="width:36px;height:2px;background:#C8A030;margin:0 auto 20px"></div><p style="color:#555;font-size:16px;max-width:520px;display:inline-block;line-height:1.8">DUHN FRAGRANCES was born from one simple belief: that every Egyptian deserves to smell extraordinary, every day.</p></div><p style="color:#444;line-height:1.85;margin-bottom:16px">We started with a simple question: why should luxury fragrance cost a fortune? In the ancient streets of Cairo, surrounded by a culture rich in aromatic tradition, we set out to create something different.</p><p style="color:#444;line-height:1.85;margin-bottom:24px">Today, DUHN is Egypt's answer to premium fragrance — crafted with the finest ingredients, inspired by our heritage, and priced for every Egyptian who refuses to compromise on quality.</p><div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin:24px 0"><div style="background:#f8f6f2;border-radius:10px;padding:20px;text-align:center"><div style="font-size:28px;font-weight:800;color:#C8A030;margin-bottom:4px">50+</div><div style="font-size:12px;color:#888">Unique Scents</div></div><div style="background:#f8f6f2;border-radius:10px;padding:20px;text-align:center"><div style="font-size:28px;font-weight:800;color:#C8A030;margin-bottom:4px">12h</div><div style="font-size:12px;color:#888">Longevity</div></div><div style="background:#f8f6f2;border-radius:10px;padding:20px;text-align:center"><div style="font-size:28px;font-weight:800;color:#C8A030;margin-bottom:4px">899</div><div style="font-size:12px;color:#888">EGP — 50ml</div></div></div>`},
  {key:'mission-vision',emoji:'🎯',name:'Mission & Vision',desc:'Two-column layout',html:`<h2 style="font-size:24px;font-weight:700;margin:0 0 6px">What Drives Us</h2><p style="color:#888;margin-bottom:24px">Our purpose, our promise, our path forward</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px"><div style="background:#fdfbf6;border:1px solid rgba(200,160,48,.2);border-radius:12px;padding:24px"><div style="font-size:28px;margin-bottom:12px">🎯</div><h3 style="color:#C8A030;font-size:16px;font-weight:700;margin:0 0 10px">Our Mission</h3><p style="color:#555;font-size:13px;line-height:1.75;margin:0">To make premium, long-lasting fragrance accessible to every Egyptian — without compromise on quality, ingredients, or experience.</p></div><div style="background:#f8f8f8;border-radius:12px;padding:24px"><div style="font-size:28px;margin-bottom:12px">✨</div><h3 style="font-size:16px;font-weight:700;margin:0 0 10px">Our Vision</h3><p style="color:#555;font-size:13px;line-height:1.75;margin:0">To become Egypt's most loved fragrance brand — a symbol of Egyptian luxury recognized across the Arab world and beyond.</p></div></div><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px"><div style="text-align:center;padding:20px 10px"><div style="font-size:24px;margin-bottom:8px">🌹</div><h4 style="font-size:13px;margin:0 0 6px">Craftsmanship</h4><p style="font-size:12px;color:#888;margin:0">Made with precision and care</p></div><div style="text-align:center;padding:20px 10px"><div style="font-size:24px;margin-bottom:8px">💎</div><h4 style="font-size:13px;margin:0 0 6px">Quality</h4><p style="font-size:12px;color:#888;margin:0">Finest ingredients, always</p></div><div style="text-align:center;padding:20px 10px"><div style="font-size:24px;margin-bottom:8px">🤝</div><h4 style="font-size:13px;margin:0 0 6px">Trust</h4><p style="font-size:12px;color:#888;margin:0">Thousands of happy customers</p></div></div>`},
  {key:'brand-values',emoji:'💎',name:'Brand Values',desc:'Core values grid',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">Our Values</h2><p style="color:#888;margin-bottom:24px">The principles that guide everything we do</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:14px"><div style="background:#fdfbf6;border:1px solid rgba(200,160,48,.15);border-radius:10px;padding:20px"><div style="font-size:24px;margin-bottom:10px">🌟</div><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 8px">Excellence</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">We never settle for good enough. Every formula, every bottle must be exceptional.</p></div><div style="background:#f8f8f8;border-radius:10px;padding:20px"><div style="font-size:24px;margin-bottom:10px">🤝</div><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 8px">Authenticity</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">We are proudly Egyptian. Our heritage informs our scents and our values.</p></div><div style="background:#f8f8f8;border-radius:10px;padding:20px"><div style="font-size:24px;margin-bottom:10px">🌍</div><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 8px">Accessibility</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Luxury should not be a privilege. Everyone deserves to smell extraordinary.</p></div><div style="background:#fdfbf6;border:1px solid rgba(200,160,48,.15);border-radius:10px;padding:20px"><div style="font-size:24px;margin-bottom:10px">♻️</div><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 8px">Responsibility</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">We care about our community and the future we're building together.</p></div></div>`},
  {key:'founder',emoji:'👤',name:'Founder Story',desc:'Personal narrative',html:`<div style="display:flex;align-items:center;gap:20px;margin-bottom:24px"><div style="width:72px;height:72px;background:#f0ede8;border:2px solid rgba(200,160,48,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0">👤</div><div><h2 style="font-size:20px;font-weight:700;margin:0 0 4px">A Message from Our Founder</h2><p style="color:#C8A030;font-size:12px;margin:0">Founder & CEO, DUHN FRAGRANCES</p></div></div><blockquote style="border-left:4px solid #C8A030;padding:16px 20px;margin:0 0 24px;background:#fdfbf6;border-radius:0 8px 8px 0"><p style="font-style:italic;color:#333;font-size:16px;line-height:1.8;margin:0">"I grew up surrounded by the rich fragrance culture of Egypt — from the spice markets of Khan el-Khalili to the rose water used in every home. I wanted to bottle that heritage and share it with the world."</p></blockquote><p style="color:#444;line-height:1.85;margin-bottom:14px">Write your founder's personal story here. Be human, authentic, and connect with your customers on a personal level.</p><p style="color:#444;line-height:1.85">Share what drove you to start DUHN, what challenges you overcame, and what you're most proud of. People buy from people they trust — this is where that trust is built.</p>`},
  {key:'timeline',emoji:'📅',name:'Timeline',desc:'Company milestones',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">Our Journey</h2><p style="color:#888;margin-bottom:24px">Key milestones that shaped DUHN FRAGRANCES</p><div style="position:relative;padding-left:28px;border-left:2px solid rgba(200,160,48,.25)"><div style="margin-bottom:24px;position:relative"><div style="position:absolute;left:-36px;top:0;width:16px;height:16px;background:#C8A030;border-radius:50%;border:3px solid #f0ede8"></div><p style="color:#C8A030;font-size:11px;font-weight:700;letter-spacing:.1em;margin:0 0 4px">2022</p><h4 style="margin:0 0 6px;font-size:14px;font-weight:700">The Beginning</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">DUHN FRAGRANCES was founded with a vision to bring premium Egyptian scent to the world.</p></div><div style="margin-bottom:24px;position:relative"><div style="position:absolute;left:-36px;top:0;width:16px;height:16px;background:#C8A030;border-radius:50%;border:3px solid #f0ede8"></div><p style="color:#C8A030;font-size:11px;font-weight:700;letter-spacing:.1em;margin:0 0 4px">2023</p><h4 style="margin:0 0 6px;font-size:14px;font-weight:700">First Collection Launch</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Our first signature collection launched to overwhelming response across Egypt.</p></div><div style="position:relative"><div style="position:absolute;left:-36px;top:0;width:16px;height:16px;background:rgba(200,160,48,.3);border-radius:50%;border:3px solid #f0ede8"></div><p style="color:rgba(200,160,48,.5);font-size:11px;font-weight:700;letter-spacing:.1em;margin:0 0 4px">2025 →</p><h4 style="color:#aaa;margin:0 0 6px;font-size:14px;font-weight:700">What's Next</h4><p style="color:#bbb;font-size:13px;line-height:1.65;margin:0">New collections, new cities — add your upcoming goals here.</p></div></div>`},
  {key:'minimalist',emoji:'✦',name:'Minimalist',desc:'Clean centered text',html:`<div style="max-width:580px;margin:0 auto;text-align:center;padding:24px 0"><p style="color:#C8A030;font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin:0 0 18px">DUHN FRAGRANCES</p><h2 style="font-size:30px;font-weight:300;letter-spacing:.06em;line-height:1.3;margin:0 0 18px;color:#1a1a1a">Scent is the most<br>powerful memory</h2><div style="width:36px;height:2px;background:#C8A030;margin:0 auto 20px"></div><p style="color:#555;font-size:16px;line-height:1.9;margin:0 0 18px">Write your about text here. Let simplicity give your words room to breathe.</p><p style="color:#888;font-size:14px;line-height:1.85;margin:0 0 28px">Add a second paragraph with more context about who you are and what makes DUHN different.</p><a href="/collections.php" style="display:inline-block;border:1px solid rgba(200,160,48,.5);color:#C8A030;padding:11px 28px;border-radius:30px;font-size:13px;font-weight:600;text-decoration:none;letter-spacing:.1em">EXPLORE →</a></div>`},
  {key:'heritage',emoji:'🏺',name:'Egyptian Heritage',desc:'Cultural story',html:`<div style="background:linear-gradient(160deg,#111,#1c1510);border-radius:12px;padding:40px 32px;margin-bottom:24px;text-align:center"><p style="color:#C8A030;font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin:0 0 14px">MADE IN EGYPT</p><h2 style="color:#fff;font-size:26px;font-weight:700;margin:0 0 14px">5,000 Years of Fragrance Tradition</h2><p style="color:rgba(255,255,255,.65);font-size:15px;max-width:460px;display:inline-block;line-height:1.8">Ancient Egyptians were the world's first perfumers. We carry that legacy forward.</p></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:20px"><div><h3 style="color:#C8A030;font-size:15px;font-weight:700;margin:0 0 10px">🏺 The Ancient Roots</h3><p style="color:#444;font-size:13px;line-height:1.75;margin:0">Egyptian temples were the first fragrance laboratories, burning incense to honor their gods with scent.</p></div><div><h3 style="color:#C8A030;font-size:15px;font-weight:700;margin:0 0 10px">🌹 Our Inheritance</h3><p style="color:#444;font-size:13px;line-height:1.75;margin:0">At DUHN, we draw from this incredible legacy — marrying ancient aromatic wisdom with modern perfumery techniques.</p></div></div>`},
  {key:'press',emoji:'🏆',name:'Press & Awards',desc:'Recognition section',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">Recognition</h2><p style="color:#888;margin-bottom:24px">What the world is saying about DUHN</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px"><div style="background:#fdfbf6;border-left:3px solid #C8A030;border-radius:0 8px 8px 0;padding:18px"><p style="font-style:italic;color:#444;font-size:14px;line-height:1.7;margin:0 0 10px">"DUHN represents the best of Egyptian fragrance heritage — modern, accessible, and deeply sophisticated."</p><div style="font-size:11px;font-weight:700;color:#C8A030">Publication Name</div><div style="font-size:10px;color:#aaa">Year</div></div><div style="background:#fdfbf6;border-left:3px solid #C8A030;border-radius:0 8px 8px 0;padding:18px"><p style="font-style:italic;color:#444;font-size:14px;line-height:1.7;margin:0 0 10px">"A fragrance brand that proves you don't have to spend a fortune to smell like a million dollars."</p><div style="font-size:11px;font-weight:700;color:#C8A030">Publication Name</div><div style="font-size:10px;color:#aaa">Year</div></div></div>`}
],
shipping:[
  {key:'standard',emoji:'📦',name:'Standard Policy',desc:'Clean delivery terms',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">Shipping Policy</h2><p style="color:#888;margin-bottom:24px">Everything you need to know about delivery</p><div style="display:flex;flex-direction:column;gap:0"><div style="border-bottom:1px solid #f0f0f0;padding:18px 0;display:flex;gap:16px"><div style="font-size:24px;flex-shrink:0">📦</div><div><h3 style="font-size:15px;font-weight:700;margin:0 0 6px">Delivery Timeline</h3><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Orders are processed within <strong>1 business day</strong> and delivered within <strong>2–5 business days</strong> across Egypt.</p></div></div><div style="border-bottom:1px solid #f0f0f0;padding:18px 0;display:flex;gap:16px"><div style="font-size:24px;flex-shrink:0">📅</div><div><h3 style="font-size:15px;font-weight:700;margin:0 0 6px">Business Days</h3><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Delivery days are <strong>Sunday through Thursday</strong>. Weekends and official holidays are excluded.</p></div></div><div style="border-bottom:1px solid #f0f0f0;padding:18px 0;display:flex;gap:16px"><div style="font-size:24px;flex-shrink:0">🚀</div><div><h3 style="font-size:15px;font-weight:700;margin:0 0 6px">Free Delivery</h3><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Free delivery is available within <strong>Cairo and Giza</strong>. Other governorates have a flat shipping rate.</p></div></div><div style="padding:18px 0;display:flex;gap:16px"><div style="font-size:24px;flex-shrink:0">📍</div><div><h3 style="font-size:15px;font-weight:700;margin:0 0 6px">Order Tracking</h3><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Once shipped you'll receive a tracking link via WhatsApp or email.</p></div></div></div>`},
  {key:'timeline',emoji:'🗓️',name:'Delivery Timeline',desc:'Step-by-step breakdown',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">How Your Order Gets to You</h2><p style="color:#888;margin-bottom:24px">A step-by-step delivery journey</p><div style="position:relative;padding-left:28px;border-left:2px solid rgba(200,160,48,.25)"><div style="margin-bottom:20px;position:relative"><div style="position:absolute;left:-36px;top:2px;width:16px;height:16px;background:#C8A030;border-radius:50%"></div><p style="color:#C8A030;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin:0 0 4px">Day 0 — Order Placed</p><p style="color:#555;font-size:13px;line-height:1.65;margin:0">You place your order. Confirmation via WhatsApp or email within minutes.</p></div><div style="margin-bottom:20px;position:relative"><div style="position:absolute;left:-36px;top:2px;width:16px;height:16px;background:#C8A030;border-radius:50%"></div><p style="color:#C8A030;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin:0 0 4px">Day 1 — Processing</p><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Our team picks, packs, and prepares your order for dispatch.</p></div><div style="margin-bottom:20px;position:relative"><div style="position:absolute;left:-36px;top:2px;width:16px;height:16px;background:#C8A030;border-radius:50%"></div><p style="color:#C8A030;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin:0 0 4px">Days 2–5 — Delivery</p><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Your package is on its way. Cairo & Giza typically arrive in 2 days.</p></div><div style="position:relative"><div style="position:absolute;left:-36px;top:2px;width:16px;height:16px;background:#6fcf97;border-radius:50%"></div><p style="color:#6fcf97;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin:0 0 4px">Delivered! 🎉</p><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Enjoy your DUHN fragrance. Let us know if you need anything!</p></div></div>`},
  {key:'coverage',emoji:'🗺️',name:'Coverage Areas',desc:'Where we deliver',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">Delivery Coverage</h2><p style="color:#888;margin-bottom:24px">We deliver across all 27 governorates of Egypt</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px"><div style="background:#fdfbf6;border:1px solid rgba(200,160,48,.2);border-radius:10px;padding:20px"><div style="font-size:24px;margin-bottom:8px">🏙️</div><h3 style="color:#C8A030;font-size:15px;font-weight:700;margin:0 0 8px">Cairo & Giza</h3><p style="color:#555;font-size:13px;line-height:1.65;margin:0 0 10px">1–2 business days</p><p style="color:#C8A030;font-size:12px;font-weight:700;margin:0">✓ Free Delivery</p></div><div style="background:#f8f8f8;border-radius:10px;padding:20px"><div style="font-size:24px;margin-bottom:8px">🌍</div><h3 style="font-size:15px;font-weight:700;margin:0 0 8px">All Other Governorates</h3><p style="color:#555;font-size:13px;line-height:1.65;margin:0 0 10px">3–5 business days</p><p style="color:#888;font-size:12px;font-weight:700;margin:0">Standard Shipping Rate</p></div></div>`},
  {key:'tracking',emoji:'📍',name:'Tracking & Support',desc:'How to track orders',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 24px">Order Tracking & Support</h2><div style="display:flex;flex-direction:column;gap:14px"><div style="border:1px solid #f0f0f0;border-radius:10px;padding:18px;display:flex;gap:14px"><div style="font-size:28px;flex-shrink:0">📱</div><div><h3 style="font-size:15px;font-weight:700;margin:0 0 8px">WhatsApp Updates</h3><p style="color:#555;font-size:13px;line-height:1.7;margin:0">You'll receive order confirmation and tracking updates on WhatsApp.</p></div></div><div style="border:1px solid #f0f0f0;border-radius:10px;padding:18px;display:flex;gap:14px"><div style="font-size:28px;flex-shrink:0">📧</div><div><h3 style="font-size:15px;font-weight:700;margin:0 0 8px">Email Notifications</h3><p style="color:#555;font-size:13px;line-height:1.7;margin:0">A tracking link will be emailed once your package is dispatched.</p></div></div><div style="border:1px solid #f0f0f0;border-radius:10px;padding:18px;display:flex;gap:14px"><div style="font-size:28px;flex-shrink:0">💬</div><div><h3 style="font-size:15px;font-weight:700;margin:0 0 8px">Need Help?</h3><p style="color:#555;font-size:13px;line-height:1.7;margin:0">Contact us on Instagram <strong style="color:#C8A030">@duhnfragrances</strong> or through our contact form.</p></div></div></div>`},
  {key:'faq-ship',emoji:'❓',name:'Shipping FAQ',desc:'Common questions',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 24px">Shipping FAQ</h2><div style="display:flex;flex-direction:column;gap:0"><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">How long does delivery take?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Cairo and Giza: 1–2 business days. Other governorates: 3–5 business days.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">Is delivery free?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Yes — free delivery for Cairo and Giza orders. Standard shipping fee for other areas.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">Can I track my order?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Yes — you'll receive a tracking link via WhatsApp or email once shipped.</p></div><div style="padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">Do you deliver internationally?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Currently Egypt only. International shipping coming soon.</p></div></div>`}
],
exchange:[
  {key:'no-returns',emoji:'🔒',name:'No Returns Policy',desc:'Clear policy statement',html:`<div style="background:#fff5f5;border:1px solid rgba(235,87,87,.2);border-radius:10px;padding:20px 22px;margin-bottom:24px;display:flex;gap:14px"><span style="font-size:28px;flex-shrink:0">🚫</span><div><h3 style="color:#eb5757;font-size:16px;font-weight:700;margin:0 0 8px">No Returns or Exchanges</h3><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Due to the personal nature of fragrance products, <strong>DUHN FRAGRANCES does not accept returns or exchanges</strong> once a purchase is completed.</p></div></div><p style="color:#444;font-size:14px;line-height:1.85;margin-bottom:16px">We encourage all customers to carefully review the fragrance description, notes, and customer reviews before purchasing. Our team is always available to help you choose the right scent.</p><div style="background:#fdfbf6;border:1px solid rgba(200,160,48,.2);border-radius:8px;padding:16px 20px;display:flex;gap:10px"><span style="font-size:18px;flex-shrink:0">💡</span><p style="font-size:13px;color:#555;margin:0">Not sure which fragrance to choose? Contact us on WhatsApp or Instagram <strong style="color:#C8A030">@duhnfragrances</strong> before ordering and we'll guide you to your perfect match.</p></div>`},
  {key:'defective',emoji:'✅',name:'Defective Items',desc:'Replacement policy',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">Defective Item Policy</h2><p style="color:#888;margin-bottom:24px">We stand behind every bottle we sell</p><div style="background:#f0fff4;border:1px solid rgba(111,207,151,.25);border-radius:10px;padding:18px 20px;margin-bottom:20px;display:flex;gap:12px"><span style="font-size:24px;flex-shrink:0">✅</span><div><h3 style="color:#27ae60;font-size:15px;font-weight:700;margin:0 0 6px">We Replace Defective Items — No Questions Asked</h3><p style="color:#555;font-size:13px;line-height:1.65;margin:0">If you receive a defective product, we will replace it immediately at no cost to you.</p></div></div><h3 style="font-size:15px;font-weight:700;margin:0 0 14px">What qualifies as a defect?</h3><ul style="list-style:none;padding:0;margin:0 0 20px"><li style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid #f0f0f0"><span style="color:#27ae60;font-size:16px;flex-shrink:0;line-height:1.3">✓</span><span style="color:#555;font-size:13px">Broken or leaking atomizer</span></li><li style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid #f0f0f0"><span style="color:#27ae60;font-size:16px;flex-shrink:0;line-height:1.3">✓</span><span style="color:#555;font-size:13px">Wrong fragrance sent</span></li><li style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid #f0f0f0"><span style="color:#27ae60;font-size:16px;flex-shrink:0;line-height:1.3">✓</span><span style="color:#555;font-size:13px">Cracked or damaged bottle on arrival</span></li><li style="display:flex;gap:10px;padding:10px 0"><span style="color:#27ae60;font-size:16px;flex-shrink:0;line-height:1.3">✓</span><span style="color:#555;font-size:13px">Missing item from your order</span></li></ul>`},
  {key:'claims',emoji:'📸',name:'Claims Process',desc:'Step-by-step guide',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">How to Make a Claim</h2><p style="color:#888;margin-bottom:24px">Received a damaged or incorrect item? Follow these steps</p><ol style="list-style:none;padding:0;margin:0"><li style="display:flex;gap:14px;padding:16px 0;border-bottom:1px solid #f0f0f0"><div style="width:36px;height:36px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:15px">1</div><div><h4 style="font-size:14px;font-weight:700;margin:0 0 5px">Contact Us Within 5 Days</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Reach out via Instagram <strong style="color:#C8A030">@duhnfragrances</strong> or our contact form within 5 days of receiving your order.</p></div></li><li style="display:flex;gap:14px;padding:16px 0;border-bottom:1px solid #f0f0f0"><div style="width:36px;height:36px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:15px">2</div><div><h4 style="font-size:14px;font-weight:700;margin:0 0 5px">Share Order Details + Photos</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Provide your order number and clear photos of the defective or incorrect item.</p></div></li><li style="display:flex;gap:14px;padding:16px 0"><div style="width:36px;height:36px;background:#6fcf97;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px">✓</div><div><h4 style="color:#27ae60;font-size:14px;font-weight:700;margin:0 0 5px">We'll Handle the Rest</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Once your claim is approved, we'll send a replacement as quickly as possible.</p></div></li></ol>`},
  {key:'faq-exchange',emoji:'❓',name:'Exchange FAQ',desc:'Common questions',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 24px">Exchange & Returns FAQ</h2><div style="display:flex;flex-direction:column;gap:0"><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">Can I return a fragrance if I don't like it?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">No. We cannot accept returns for preference-based reasons. We encourage reading reviews carefully before purchasing.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">What if my order arrived damaged?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Contact us within 5 days with photos. We replace defective items at no cost.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">What if I received the wrong item?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Contact us immediately with your order number and photos. We'll send the correct item right away.</p></div><div style="padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">How long does a replacement take?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Once approved (usually within 24 hours), replacement follows the standard delivery timeline.</p></div></div>`}
],
refill:[
  {key:'intro',emoji:'♻️',name:'Refill Service',desc:'Basic intro + how-to',html:`<div style="background:#f0fff4;border:1px solid rgba(111,207,151,.25);border-radius:12px;padding:32px;margin-bottom:24px;text-align:center"><div style="font-size:36px;margin-bottom:12px">♻️</div><h2 style="color:#27ae60;font-size:22px;font-weight:700;margin:0 0 10px">DUHN Refill Service</h2><p style="color:#555;font-size:15px;max-width:420px;display:inline-block;line-height:1.75;margin:0">Keep your bottle. Refill the love. Our refill service lets you reuse your DUHN bottle and keep enjoying your favourite scent.</p></div><h3 style="font-size:16px;font-weight:700;margin:0 0 16px">How to Request a Refill</h3><ol style="list-style:none;padding:0;margin:0 0 24px"><li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><div style="width:32px;height:32px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:13px">1</div><div><strong>Contact Us</strong><p style="color:#666;font-size:13px;margin:4px 0 0">Reach out on Instagram @duhnfragrances or via the contact form.</p></div></li><li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><div style="width:32px;height:32px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:13px">2</div><div><strong>Choose Your Scent</strong><p style="color:#666;font-size:13px;margin:4px 0 0">Select the fragrance for refill — same scent or a new one.</p></div></li><li style="display:flex;gap:14px;padding:14px 0"><div style="width:32px;height:32px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:13px">3</div><div><strong>We Handle the Rest</strong><p style="color:#666;font-size:13px;margin:4px 0 0">Arrange pickup or drop-off. We refill, reseal, and return it to you.</p></div></li></ol>`},
  {key:'conditions',emoji:'📋',name:'Conditions & Terms',desc:'Detailed refill terms',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 6px">Refill Service — Terms</h2><p style="color:#888;margin-bottom:24px">Important information before requesting a refill</p><div style="display:flex;flex-direction:column;gap:0"><div style="border-bottom:1px solid #f0f0f0;padding:16px 0;display:flex;gap:12px"><span style="color:#27ae60;font-size:18px;flex-shrink:0;line-height:1.3">✓</span><div><strong style="font-size:14px">Eligible Bottles Only</strong><p style="color:#555;font-size:13px;line-height:1.65;margin:4px 0 0">Refills are only available for original DUHN bottles.</p></div></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0;display:flex;gap:12px"><span style="color:#27ae60;font-size:18px;flex-shrink:0;line-height:1.3">✓</span><div><strong style="font-size:14px">Bottle Must Be Intact</strong><p style="color:#555;font-size:13px;line-height:1.65;margin:4px 0 0">No cracks, chips, or damage to the atomizer. We may refuse damaged bottles.</p></div></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0;display:flex;gap:12px"><span style="color:#27ae60;font-size:18px;flex-shrink:0;line-height:1.3">✓</span><div><strong style="font-size:14px">Bottle Must Be Clean</strong><p style="color:#555;font-size:13px;line-height:1.65;margin:4px 0 0">Mostly empty and free of any foreign substances.</p></div></div><div style="padding:16px 0;display:flex;gap:12px"><span style="color:#27ae60;font-size:18px;flex-shrink:0;line-height:1.3">✓</span><div><strong style="font-size:14px">Any DUHN Fragrance</strong><p style="color:#555;font-size:13px;line-height:1.65;margin:4px 0 0">You can refill with any fragrance from our current collection.</p></div></div></div>`},
  {key:'eco',emoji:'🌱',name:'Eco-Friendly Story',desc:'Sustainability angle',html:`<div style="text-align:center;padding:24px 0 16px"><div style="font-size:40px;margin-bottom:12px">🌱</div><h2 style="color:#27ae60;font-size:24px;font-weight:700;margin:0 0 12px">Fragrance, Reimagined Sustainably</h2><p style="color:#555;font-size:15px;max-width:480px;display:inline-block;line-height:1.8">Our refill service isn't just convenient — it's a commitment to reducing waste and caring for our planet.</p></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:24px 0"><div style="background:#f0fff4;border:1px solid rgba(111,207,151,.2);border-radius:10px;padding:20px"><h4 style="color:#27ae60;font-size:14px;font-weight:700;margin:0 0 8px">Less Packaging Waste</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Every refill means one less bottle. Small steps, big impact.</p></div><div style="background:#f0fff4;border:1px solid rgba(111,207,151,.2);border-radius:10px;padding:20px"><h4 style="color:#27ae60;font-size:14px;font-weight:700;margin:0 0 8px">Same Quality, Less Waste</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Exact same formula. Incredible scent. Sustainably delivered.</p></div></div><blockquote style="border-left:4px solid #27ae60;padding:14px 18px;margin:20px 0;background:#f0fff4;border-radius:0 8px 8px 0"><p style="font-style:italic;color:#333;font-size:15px;line-height:1.7;margin:0">"We believe luxury and responsibility can coexist. Our refill service is one of the ways DUHN is building a more sustainable future."</p></blockquote>`},
  {key:'faq-refill',emoji:'❓',name:'Refill FAQ',desc:'Common questions',html:`<h2 style="font-size:22px;font-weight:700;margin:0 0 24px">Refill Service FAQ</h2><div style="display:flex;flex-direction:column;gap:0"><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">How much does a refill cost?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Contact us for a current price quote on the scent you'd like to refill.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">Can I change the fragrance when I refill?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Yes — you can refill with any fragrance from our current collection.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">How do I send my bottle in?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Contact us first to arrange pickup or drop-off based on your location.</p></div><div style="padding:16px 0"><h4 style="color:#C8A030;font-size:14px;font-weight:700;margin:0 0 7px">How long does the refill take?</h4><p style="color:#555;font-size:13px;line-height:1.65;margin:0">Usually 1–2 business days from when we receive your bottle.</p></div></div>`}
]
};

/* Render built-in templates */
(function(){
  const tpls=ALL_TEMPLATES[TAB]||[];
  document.getElementById('tpl-built-grid').innerHTML=tpls.map(t=>`
    <div class="tpl-card" onclick="loadBuiltIn('${t.key}')" title="${t.desc}">
      <span class="tpl-card__emoji">${t.emoji}</span>
      <div class="tpl-card__name">${t.name}</div>
      <div class="tpl-card__desc">${t.desc}</div>
    </div>
  `).join('');
})();

function loadBuiltIn(key){
  const tpl=(ALL_TEMPLATES[TAB]||[]).find(t=>t.key===key); if(!tpl) return;
  if(!confirm(`Load "${tpl.name}"?\nThis replaces current editor content.`)) return;
  editor.innerHTML=tpl.html; markDirty(); switchTab('blocks');
  showToast(`"${tpl.name}" loaded`);
}

/* Custom templates */
let customTemplates = <?= json_encode(array_values($customTpl)) ?>;
function renderCustom(){
  const el=document.getElementById('tpl-custom-list');
  if(!customTemplates.length){ el.innerHTML='<p style="font-size:11px;color:#bbb;text-align:center;padding:10px 0">No saved templates yet</p>'; return; }
  el.innerHTML=customTemplates.map(t=>`
    <div class="tpl-custom-item" onclick="loadCustom('${t.id}')">
      <i class="ph ph-file-text" style="color:#C8A030;font-size:15px;flex-shrink:0"></i>
      <div style="flex:1;min-width:0"><div class="tpl-custom-name">${t.name}</div><div class="tpl-custom-date">${t.created}</div></div>
      <button class="tpl-custom-del" onclick="event.stopPropagation();delCustom('${t.id}')"><i class="ph ph-trash"></i></button>
    </div>
  `).join('');
}
renderCustom();

function loadCustom(id){
  const tpl=customTemplates.find(t=>t.id===id); if(!tpl) return;
  if(!confirm(`Load "${tpl.name}"?\nThis replaces current editor content.`)) return;
  editor.innerHTML=tpl.html; markDirty(); switchTab('blocks');
  showToast(`"${tpl.name}" loaded`);
}
function saveAsTemplate(){
  const name=prompt('Template name:','My Template'); if(!name) return;
  fetch('/admin/actions/save_policy_template.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save',tab:TAB,name,html:getContent()})})
    .then(r=>r.json()).then(d=>{ if(d.ok){customTemplates=d.templates;renderCustom();showToast(`"${name}" saved!`);} else showToast('Error: '+(d.error||'failed'),false); }).catch(()=>showToast('Network error',false));
}
function delCustom(id){
  if(!confirm('Delete this template?')) return;
  fetch('/admin/actions/save_policy_template.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',tab:TAB,id})})
    .then(r=>r.json()).then(d=>{ if(d.ok){customTemplates=customTemplates.filter(t=>t.id!==id);renderCustom();showToast('Deleted');} }).catch(()=>showToast('Network error',false));
}

/* SAVE */
function savePage(){
  setStatus('saving');
  fetch('/admin/actions/save_policy_page.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({tab:TAB, title:document.getElementById('pe-title').value, subtitle:document.getElementById('pe-subtitle').value, content:getContent()})
  }).then(r=>r.json()).then(d=>{
    if(d.ok){ dirty=false; setStatus('saved'); showToast('<?= $tabLabel ?> saved!'); }
    else { setStatus('error',d.error||'failed'); showToast('Error: '+(d.error||'Save failed'),false); }
  }).catch(()=>{ setStatus('error','Network error'); showToast('Network error — are you logged in?',false); });
}

editor.addEventListener('input',markDirty);
document.addEventListener('keydown',e=>{ if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();savePage();} });
window.addEventListener('beforeunload',e=>{ if(dirty){e.preventDefault();e.returnValue='';} });
updateWC();
</script>
</body>
</html>
