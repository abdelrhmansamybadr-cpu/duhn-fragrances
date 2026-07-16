<?php
/**
 * DUHN FRAGRANCES — Full-Screen Page Editor
 * Contenteditable + Template library + Hero image controls
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$id = (int)($_GET['id'] ?? 1);
if ($id < 1 || $id > 3) $id = 1;

$db   = Database::getInstance();
$rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
$s    = [];
foreach ($rows as $r) { $s[$r['key']] = $r['value']; }

$postTitle   = htmlspecialchars($s["inspo_{$id}_title"]         ?? "Inspiration Post {$id}");
$postImg     = $s["inspo_{$id}_image"]                          ?? '';
$postImgSize = $s["inspo_{$id}_image_size"]                     ?? 'medium';
$postImgPos  = $s["inspo_{$id}_image_pos"]                      ?? 'center';
$bodyContent = $s["inspo_{$id}_page_body"]                      ?? '';
$ctaText     = htmlspecialchars($s["inspo_{$id}_page_cta_text"] ?? 'Add to Cart Now');
$ctaUrl      = htmlspecialchars($s["inspo_{$id}_page_cta_url"]  ?? '/collections.php');
$isPublished = ($s["inspo_{$id}_mode"] ?? 'url') === 'page';
$templates   = json_decode($s['page_templates'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Page Editor — <?= $postTitle ?> | DUHN Admin</title>
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
.pe-publish-wrap{display:flex;align-items:center;gap:7px;padding:5px 13px;border-radius:20px;border:1px solid rgba(255,255,255,.1);cursor:pointer;transition:all .25s;user-select:none}
.pe-publish-wrap.published{border-color:rgba(111,207,151,.4);background:rgba(111,207,151,.08)}
.pe-publish-dot{width:8px;height:8px;border-radius:50%;transition:background .25s}
.published .pe-publish-dot{background:#6fcf97;box-shadow:0 0 6px #6fcf97}
.unpublished .pe-publish-dot{background:#444}
.pe-publish-label{font-size:11px;font-weight:700;letter-spacing:.06em;transition:color .25s}
.published .pe-publish-label{color:#6fcf97}
.unpublished .pe-publish-label{color:#555}
.pe-btn{display:inline-flex;align-items:center;gap:6px;border:none;border-radius:6px;cursor:pointer;font-family:'Jost',sans-serif;font-size:12px;font-weight:600;letter-spacing:.04em;padding:8px 16px;transition:all .2s;white-space:nowrap;text-decoration:none}
.pe-btn--back{background:rgba(255,255,255,.05);border:1px solid var(--border);color:#888}
.pe-btn--back:hover{color:#fff;background:rgba(255,255,255,.1)}
.pe-btn--preview{background:rgba(248,196,23,.08);border:1px solid rgba(248,196,23,.2);color:var(--gold)}
.pe-btn--preview:hover{background:rgba(248,196,23,.18)}
.pe-btn--save{background:var(--gold);color:#000}
.pe-btn--save:hover{background:#ffd740;box-shadow:0 4px 16px rgba(248,196,23,.35)}

/* META BAR */
.pe-meta{position:fixed;top:54px;left:0;right:0;z-index:999;height:44px;background:#181818;border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 16px;gap:8px}
.pe-meta-label{font-size:10px;font-weight:700;letter-spacing:.1em;color:#555;text-transform:uppercase;white-space:nowrap}
.pe-meta-input{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:5px;color:#ccc;font-family:'Jost',sans-serif;font-size:12px;outline:none;padding:5px 10px;transition:border-color .2s}
.pe-meta-input:focus{border-color:rgba(248,196,23,.4);color:#fff}
.pe-meta-input.w-text{width:180px}
.pe-meta-input.w-url{width:240px}
.pe-meta__sep{width:1px;height:20px;background:var(--border);margin:0 8px}
.pe-meta-link{font-size:11px;color:rgba(248,196,23,.6);text-decoration:none;margin-left:auto;display:flex;align-items:center;gap:5px;white-space:nowrap}
.pe-meta-link:hover{color:var(--gold)}

/* EDITOR TOOLBAR */
.pe-toolbar-wrap{position:fixed;top:98px;left:0;right:0;z-index:998;background:#fff;border-bottom:2px solid #e8e4df;display:flex;align-items:center;padding:0 8px;height:46px;gap:2px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.pe-tb-select{background:transparent;border:1px solid #ddd;border-radius:5px;color:#333;cursor:pointer;font-family:'Jost',sans-serif;font-size:12px;padding:5px 8px;outline:none;min-width:110px}
.pe-tb-sep{width:1px;height:22px;background:#e0ddd8;margin:0 4px;flex-shrink:0}
.pe-tb-btn{background:transparent;border:none;border-radius:5px;color:#555;cursor:pointer;font-size:16px;padding:6px 8px;transition:all .15s;display:flex;align-items:center;justify-content:center;line-height:1}
.pe-tb-btn:hover{background:rgba(200,160,48,.12);color:#C8A030}
.pe-html-btn{margin-left:auto;display:flex;align-items:center;gap:5px;background:#f5f3ef;border:1px solid #ddd;border-radius:5px;color:#777;cursor:pointer;font-size:11px;font-family:'Jost',sans-serif;padding:5px 12px;font-weight:600;letter-spacing:.04em;transition:all .2s}
.pe-html-btn:hover,.pe-html-btn.active{background:#333;border-color:#333;color:#fff}
.pe-wc{font-size:10px;color:#bbb;letter-spacing:.04em;margin-left:6px;white-space:nowrap}

/* MAIN LAYOUT */
.pe-main{padding-top:144px;min-height:100vh;display:flex;justify-content:center}

/* SIDEBAR */
.pe-sidebar{width:220px;flex-shrink:0;padding:14px 12px;position:sticky;top:144px;align-self:flex-start;max-height:calc(100vh - 144px);overflow-y:auto}
.pe-sidebar::-webkit-scrollbar{width:3px}
.pe-sidebar::-webkit-scrollbar-thumb{background:#ddd;border-radius:3px}

/* Sidebar Tabs */
.pe-tabs{display:flex;gap:3px;margin-bottom:12px;background:#e8e5e0;border-radius:8px;padding:3px}
.pe-tab{flex:1;border:none;border-radius:6px;cursor:pointer;font-family:'Jost',sans-serif;font-size:11px;font-weight:700;letter-spacing:.04em;padding:7px 4px;transition:all .2s;background:transparent;color:#888}
.pe-tab.active{background:#fff;color:#C8A030;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.pe-sidebar__title{font-size:10px;font-weight:700;letter-spacing:.12em;color:#999;text-transform:uppercase;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid rgba(0,0,0,.08);display:flex;align-items:center;gap:6px}

/* Block buttons */
.pe-block-btn{display:flex;align-items:center;gap:9px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:8px;cursor:pointer;padding:9px 10px;margin-bottom:5px;transition:all .18s;font-size:12px;font-weight:500;color:#444;box-shadow:0 1px 4px rgba(0,0,0,.05);width:100%;text-align:left}
.pe-block-btn:hover{border-color:#C8A030;color:#C8A030;box-shadow:0 3px 12px rgba(200,160,48,.15);transform:translateX(2px)}
.pe-block-btn i{font-size:16px;color:#C8A030;flex-shrink:0}
.pe-block-lbl{flex:1}
.pe-block-desc{font-size:10px;color:#aaa;margin-top:1px}

/* Template cards */
.tpl-section{font-size:10px;font-weight:700;letter-spacing:.1em;color:#bbb;text-transform:uppercase;margin:12px 0 7px;padding-bottom:5px;border-bottom:1px solid rgba(0,0,0,.07)}
.tpl-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:10px}
.tpl-card{background:#fff;border:2px solid rgba(0,0,0,.07);border-radius:8px;cursor:pointer;overflow:hidden;transition:all .18s;text-align:center;padding:10px 6px}
.tpl-card:hover{border-color:#C8A030;transform:translateY(-1px);box-shadow:0 4px 14px rgba(200,160,48,.14)}
.tpl-card__emoji{font-size:20px;display:block;margin-bottom:4px}
.tpl-card__name{font-size:10px;font-weight:700;color:#333;line-height:1.3}
.tpl-card__desc{font-size:9px;color:#aaa;margin-top:2px;line-height:1.3}
.tpl-save-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;background:#f8f6f2;border:1px dashed #C8A030;border-radius:7px;color:#C8A030;cursor:pointer;font-family:'Jost',sans-serif;font-size:11px;font-weight:700;padding:9px;transition:all .2s;letter-spacing:.04em;margin-bottom:8px}
.tpl-save-btn:hover{background:#C8A030;color:#000}
.tpl-custom-item{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid rgba(0,0,0,.07);border-radius:7px;padding:8px 9px;margin-bottom:5px;cursor:pointer;transition:all .18s}
.tpl-custom-item:hover{border-color:#C8A030}
.tpl-custom-name{flex:1;font-size:11px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tpl-custom-date{font-size:9px;color:#bbb;white-space:nowrap}
.tpl-custom-del{background:transparent;border:none;color:#ccc;cursor:pointer;font-size:14px;padding:2px 4px;border-radius:4px;transition:color .15s;flex-shrink:0}
.tpl-custom-del:hover{color:#eb5757}

/* EDITOR CANVAS */
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
.pe-editor table{width:100%;border-collapse:collapse;margin:16px 0}
.pe-editor td,.pe-editor th{border:1px solid #ddd;padding:8px 12px;font-size:13px}
.pe-editor th{background:#f8f8f8;font-weight:600}
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
.pe-card input{width:100%;background:#f8f8f8;border:1px solid #e0e0e0;border-radius:5px;color:#222;font-family:'Jost',sans-serif;font-size:12px;outline:none;padding:6px 9px;margin-bottom:9px;transition:border-color .2s}
.pe-card input:focus{border-color:#C8A030;background:#fff}
.pe-card__btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;background:#1a1a1a;color:#fff;border:none;border-radius:7px;cursor:pointer;font-family:'Jost',sans-serif;font-size:12px;font-weight:600;padding:9px;transition:all .2s;text-decoration:none;letter-spacing:.04em;margin-top:4px}
.pe-card__btn:hover{background:#333}
.pe-card__btn.gold{background:#C8A030;color:#000}
.pe-card__btn.gold:hover{background:#f0c040}
.pe-card .shortcut{font-size:11px;color:#888;line-height:2}
.pe-card kbd{background:#f0ede8;border:1px solid #ddd;border-radius:3px;padding:1px 5px;font-size:10px}

/* Hero image controls */
.pi-img-wrap{position:relative;border-radius:8px;overflow:hidden;margin-bottom:10px;cursor:pointer}
.pi-img-wrap img{width:100%;height:90px;object-fit:cover;display:block;transition:filter .2s}
.pi-img-wrap:hover img{filter:brightness(.4)}
.pi-img-actions{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:6px;opacity:0;transition:opacity .2s}
.pi-img-wrap:hover .pi-img-actions{opacity:1}
.pi-img-btn{border:none;border-radius:5px;cursor:pointer;font-family:'Jost',sans-serif;font-size:10px;font-weight:700;padding:6px 9px;letter-spacing:.04em;display:flex;align-items:center;gap:3px}
.pi-img-btn.upload{background:rgba(200,160,48,.95);color:#000}
.pi-img-btn.url-btn{background:rgba(255,255,255,.9);color:#333}
.pi-img-btn.remove{background:rgba(235,87,87,.85);color:#fff}
.pi-no-img{border:2px dashed rgba(200,160,48,.3);border-radius:8px;text-align:center;padding:16px 8px;margin-bottom:10px;cursor:pointer;transition:all .2s}
.pi-no-img:hover{border-color:#C8A030;background:rgba(200,160,48,.04)}
.pi-no-img p{font-size:10px;color:#bbb;margin-top:4px}
.pi-ctrl-label{font-size:10px;font-weight:700;letter-spacing:.08em;color:#aaa;text-transform:uppercase;margin-bottom:5px}
.pi-ctrl-row{display:flex;gap:3px;margin-bottom:9px}
.pi-ctrl-btn{flex:1;border:1px solid #e0ddd8;border-radius:5px;background:#faf9f7;color:#666;cursor:pointer;font-family:'Jost',sans-serif;font-size:10px;font-weight:600;padding:5px 2px;transition:all .15s;text-align:center}
.pi-ctrl-btn:hover{border-color:#C8A030;color:#C8A030}
.pi-ctrl-btn.active{background:#C8A030;border-color:#C8A030;color:#000}
#pi-hero-file{display:none}
#pe-file-input{display:none}

/* TOAST */
.pe-toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(80px);background:#1a1a1a;color:#fff;border-radius:8px;padding:12px 24px;font-size:13px;font-weight:500;box-shadow:0 8px 32px rgba(0,0,0,.4);opacity:0;transition:all .35s;pointer-events:none;z-index:9999;display:flex;align-items:center;gap:10px}
.pe-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.pe-toast.ok i{color:#6fcf97}
.pe-toast.err i{color:#eb5757}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="pe-bar">
  <a href="/admin/homepage-sections.php?tab=inspo" class="pe-btn pe-btn--back"><i class="ph ph-arrow-left"></i></a>
  <div class="pe-bar__sep"></div>
  <div class="pe-bar__logo">DUHN</div>
  <div class="pe-bar__sep"></div>
  <div class="pe-bar__crumb">Inspiration #<?= $id ?> › <span><?= $postTitle ?></span></div>
  <div class="pe-bar__right">
    <div class="pe-status saved" id="pe-status"><i class="ph ph-check-circle"></i> Saved</div>
    <div class="pe-publish-wrap <?= $isPublished ? 'published' : 'unpublished' ?>" id="pe-publish-wrap" onclick="togglePublish()">
      <div class="pe-publish-dot"></div>
      <span class="pe-publish-label" id="pe-publish-label"><?= $isPublished ? 'LIVE' : 'DRAFT' ?></span>
    </div>
    <input type="hidden" id="pe-publish-state" value="<?= $isPublished ? '1' : '0' ?>">
    <a href="/inspo.php?id=<?= $id ?>" target="_blank" class="pe-btn pe-btn--preview"><i class="ph ph-arrow-square-out"></i> Preview</a>
    <button class="pe-btn pe-btn--save" onclick="savePage()"><i class="ph ph-floppy-disk"></i> Save Page</button>
  </div>
</div>

<!-- META BAR -->
<div class="pe-meta">
  <span class="pe-meta-label"><i class="ph ph-cursor-click"></i> CTA:</span>
  <input type="text" class="pe-meta-input w-text" id="meta-cta-text" value="<?= $ctaText ?>" placeholder="Button text"
         oninput="document.getElementById('side-cta-text').value=this.value">
  <span style="color:#444;margin:0 4px">→</span>
  <input type="text" class="pe-meta-input w-url" id="meta-cta-url" value="<?= $ctaUrl ?>" placeholder="/collections.php"
         oninput="document.getElementById('side-cta-url').value=this.value">
  <div class="pe-meta__sep"></div>
  <a href="/inspo.php?id=<?= $id ?>" target="_blank" class="pe-meta-link"><i class="ph ph-link"></i> /inspo.php?id=<?= $id ?></a>
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
  <button type="button" class="pe-tb-btn" onclick="cmd('insertUnorderedList')" title="Bullet list"><i class="ph-bold ph-list-bullets"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('insertOrderedList')" title="Numbered list"><i class="ph-bold ph-list-numbers"></i></button>
  <div class="pe-tb-sep"></div>
  <button type="button" class="pe-tb-btn" onclick="insertLink()" title="Link"><i class="ph-bold ph-link"></i></button>
  <button type="button" class="pe-tb-btn" onclick="insertImgUrl()" title="Image by URL"><i class="ph-bold ph-image"></i></button>
  <button type="button" class="pe-tb-btn" onclick="document.getElementById('pe-file-input').click()" title="Upload image"><i class="ph-bold ph-upload-simple"></i></button>
  <input type="file" id="pe-file-input" accept="image/*" onchange="uploadImg(this)">
  <button type="button" class="pe-tb-btn" onclick="cmd('insertHorizontalRule')" title="Divider line"><i class="ph-bold ph-minus"></i></button>
  <div class="pe-tb-sep"></div>
  <button type="button" class="pe-tb-btn" onclick="cmd('undo')" title="Undo"><i class="ph-bold ph-arrow-u-up-left"></i></button>
  <button type="button" class="pe-tb-btn" onclick="cmd('redo')" title="Redo"><i class="ph-bold ph-arrow-u-up-right"></i></button>
  <button type="button" class="pe-html-btn" id="pe-html-btn" onclick="toggleCode()"><i class="ph ph-code"></i> HTML</button>
  <span class="pe-wc" id="pe-wc">0 words</span>
</div>

<!-- MAIN -->
<div class="pe-main">

  <!-- LEFT SIDEBAR: Blocks + Templates -->
  <div class="pe-sidebar">
    <div class="pe-tabs">
      <button class="pe-tab active" onclick="switchTab('blocks')" id="tab-btn-blocks"><i class="ph ph-squares-four"></i> Blocks</button>
      <button class="pe-tab" onclick="switchTab('templates')" id="tab-btn-templates"><i class="ph ph-layout"></i> Templates</button>
    </div>

    <!-- BLOCKS TAB -->
    <div id="tab-blocks">
      <div class="pe-sidebar__title"><i class="ph ph-squares-four"></i> Insert Block</div>
      <?php
      $blocks = [
        ['hero',     'ph-image-square',     'Hero Banner',     'Full-width heading + CTA'],
        ['2col',     'ph-columns',          '2 Columns',       'Side-by-side layout'],
        ['imgtext',  'ph-article',          'Image + Text',    'Photo with copy'],
        ['quote',    'ph-quotes',           'Quote Block',     'Highlighted quote'],
        ['features', 'ph-check-square',     'Feature List',    'Checklist rows'],
        ['cta',      'ph-megaphone-simple', 'CTA Banner',      'Call-to-action box'],
        ['divider',  'ph-minus',            'Section Divider', 'Gold divider + title'],
        ['spacer',   'ph-arrows-out-line-vertical','Spacer',   'Vertical space'],
      ];
      foreach ($blocks as [$key, $icon, $label, $desc]): ?>
      <button type="button" class="pe-block-btn" onclick="insertBlock('<?= $key ?>')">
        <i class="ph <?= $icon ?>"></i>
        <div class="pe-block-lbl"><?= $label ?><div class="pe-block-desc"><?= $desc ?></div></div>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- TEMPLATES TAB -->
    <div id="tab-templates" style="display:none">
      <button type="button" class="tpl-save-btn" onclick="saveAsTemplate()"><i class="ph ph-floppy-disk"></i> Save Current as Template</button>

      <div class="tpl-section">Built-in Templates</div>
      <div class="tpl-grid" id="tpl-built-grid"></div>

      <div class="tpl-section">My Saved Templates</div>
      <div id="tpl-custom-list">
        <p style="font-size:11px;color:#bbb;text-align:center;padding:10px 0">No saved templates yet</p>
      </div>
    </div>
  </div>

  <!-- CENTER: Editor -->
  <div class="pe-canvas">
    <div class="pe-editor-box">
      <div class="pe-editor" id="pe-editor" contenteditable="true"
           data-ph="Start writing your page content here…"><?= $bodyContent ?></div>
      <textarea class="pe-code" id="pe-code"><?= htmlspecialchars($bodyContent) ?></textarea>
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

    <!-- Page Info -->
    <div class="pe-card">
      <div class="pe-card__title"><i class="ph ph-info"></i> Page Info</div>
      <div style="font-size:13px;font-weight:700;color:#222;margin-bottom:3px"><?= $postTitle ?></div>
      <div style="font-size:10px;color:#bbb">Inspiration Post #<?= $id ?></div>
    </div>

    <!-- Hero Image -->
    <div class="pe-card">
      <div class="pe-card__title"><i class="ph ph-image-square"></i> Hero Image</div>

      <!-- Image with hover controls -->
      <div class="pi-img-wrap" id="pi-img-wrap" <?= $postImg ? '' : 'style="display:none"' ?>>
        <img id="pi-hero-img" src="<?= htmlspecialchars($postImg) ?>" alt="">
        <div class="pi-img-actions">
          <button class="pi-img-btn upload" onclick="document.getElementById('pi-hero-file').click()" title="Upload new image">
            <i class="ph ph-upload-simple"></i> Upload
          </button>
          <button class="pi-img-btn url-btn" onclick="heroImgUrl()" title="Enter image URL">
            <i class="ph ph-link"></i> URL
          </button>
          <button class="pi-img-btn remove" onclick="removeHeroImg()" title="Remove image">
            <i class="ph ph-trash"></i>
          </button>
        </div>
      </div>

      <!-- No image placeholder -->
      <div class="pi-no-img" id="pi-no-img" <?= $postImg ? 'style="display:none"' : '' ?>>
        <i class="ph ph-image" style="font-size:26px;color:#C8A030"></i>
        <p>Click to add hero image</p>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:8px">
          <button class="pi-img-btn upload" onclick="document.getElementById('pi-hero-file').click()" style="font-size:11px;padding:5px 10px">
            <i class="ph ph-upload-simple"></i> Upload
          </button>
          <button class="pi-img-btn url-btn" onclick="heroImgUrl()" style="font-size:11px;padding:5px 10px">
            <i class="ph ph-link"></i> URL
          </button>
        </div>
      </div>
      <input type="file" id="pi-hero-file" accept="image/*" onchange="uploadHeroImg(this)">

      <!-- Size control -->
      <div class="pi-ctrl-label">Image Height</div>
      <div class="pi-ctrl-row" id="pi-size-row">
        <button class="pi-ctrl-btn <?= $postImgSize==='small'?'active':'' ?>" onclick="setImgSize('small')">Small</button>
        <button class="pi-ctrl-btn <?= $postImgSize==='medium'?'active':'' ?>" onclick="setImgSize('medium')">Medium</button>
        <button class="pi-ctrl-btn <?= $postImgSize==='full'?'active':'' ?>" onclick="setImgSize('full')">Full</button>
      </div>

      <!-- Position control -->
      <div class="pi-ctrl-label">Focus Point</div>
      <div class="pi-ctrl-row" id="pi-pos-row">
        <button class="pi-ctrl-btn <?= $postImgPos==='top'?'active':'' ?>" onclick="setImgPos('top')">Top</button>
        <button class="pi-ctrl-btn <?= $postImgPos==='center'?'active':'' ?>" onclick="setImgPos('center')">Center</button>
        <button class="pi-ctrl-btn <?= $postImgPos==='bottom'?'active':'' ?>" onclick="setImgPos('bottom')">Bottom</button>
      </div>
    </div>

    <!-- CTA Button -->
    <div class="pe-card">
      <div class="pe-card__title"><i class="ph ph-cursor-click"></i> CTA Button</div>
      <label>Button Text</label>
      <input type="text" id="side-cta-text" value="<?= $ctaText ?>" placeholder="Add to Cart Now"
             oninput="document.getElementById('meta-cta-text').value=this.value">
      <label>Button URL</label>
      <input type="text" id="side-cta-url" value="<?= $ctaUrl ?>" placeholder="/collections.php"
             oninput="document.getElementById('meta-cta-url').value=this.value">
      <button type="button" class="pe-card__btn gold" onclick="savePage()"><i class="ph ph-floppy-disk"></i> Save All</button>
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

</div><!-- /.pe-main -->

<div class="pe-toast" id="pe-toast"><i class="ph ph-check-circle" id="pe-toast-icon"></i><span id="pe-toast-msg"></span></div>

<script>
const editor  = document.getElementById('pe-editor');
const codeEl  = document.getElementById('pe-code');
let inCode    = false;
let dirty     = false;
let toastTimer;

/* ── Exec / Focus ─────────────────────────────────────── */
function focusEditor(){ if(!inCode) editor.focus(); }
function cmd(command,val){ focusEditor(); document.execCommand(command,false,val||null); markDirty(); }

/* ── State ────────────────────────────────────────────── */
function markDirty(){ dirty=true; setStatus('dirty'); updateWC(); }
function updateWC(){
  const text=editor.innerText.trim();
  const words=text?text.split(/\s+/).length:0;
  const chars=text.length;
  const wc=words+(words===1?' word':' words');
  document.getElementById('pe-wc').textContent=wc;
  document.getElementById('pe-wc2').textContent=wc;
  document.getElementById('pe-chars').textContent=chars.toLocaleString()+' chars';
}
function setStatus(state,msg){
  const el=document.getElementById('pe-status');
  el.className='pe-status '+state;
  const m={saved:'<i class="ph ph-check-circle"></i> Saved',dirty:'<i class="ph ph-pencil-simple"></i> Unsaved',saving:'Saving…',error:'<i class="ph ph-warning-circle"></i> '+(msg||'Error')};
  el.innerHTML=m[state]||msg;
}
function showToast(msg,ok=true){
  const t=document.getElementById('pe-toast'),ic=document.getElementById('pe-toast-icon'),tx=document.getElementById('pe-toast-msg');
  t.className='pe-toast show '+(ok?'ok':'err');
  ic.className=ok?'ph ph-check-circle':'ph ph-warning-circle';
  tx.textContent=msg;
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>{t.className='pe-toast';},3500);
}

/* ── HTML toggle ──────────────────────────────────────── */
function toggleCode(){
  const btn=document.getElementById('pe-html-btn');
  if(!inCode){
    codeEl.value=editor.innerHTML;
    editor.style.display='none'; codeEl.style.display='block';
    btn.classList.add('active'); inCode=true;
    codeEl.oninput=()=>{dirty=true;setStatus('dirty');};
  } else {
    editor.innerHTML=codeEl.value;
    editor.style.display='block'; codeEl.style.display='none';
    btn.classList.remove('active'); inCode=false; updateWC();
  }
}
function getContent(){ return inCode ? codeEl.value : editor.innerHTML; }

/* ── Insert link ──────────────────────────────────────── */
function insertLink(){
  focusEditor();
  const sel=window.getSelection();
  let node=sel?.anchorNode;
  while(node&&node.nodeName!=='A'&&node!==editor) node=node.parentNode;
  if(node&&node.nodeName==='A'){
    const nu=prompt('Edit URL:',node.getAttribute('href')||''); if(nu!==null) node.setAttribute('href',nu);
    const nt=prompt('Edit text (blank=keep):',node.textContent||''); if(nt) node.textContent=nt;
    markDirty();
  } else {
    const url=prompt('Link URL:','https://'); if(!url) return;
    const text=(sel?.toString())||prompt('Link text:','Click here')||'Click here';
    cmd('insertHTML',`<a href="${url.replace(/"/g,'&quot;')}" style="color:#C8A030">${text}</a>`);
  }
}

/* ── Insert image by URL ──────────────────────────────── */
function insertImgUrl(){
  const url=prompt('Image URL:','https://'); if(!url) return;
  cmd('insertHTML',`<img src="${url.replace(/"/g,'&quot;')}" style="max-width:100%;border-radius:8px;display:block;margin:12px 0" alt="">`);
}

/* ── Upload image into editor ─────────────────────────── */
function uploadImg(input){
  if(!input.files[0]) return;
  const fd=new FormData(); fd.append('pb_image',input.files[0]);
  fetch('/admin/actions/pb_upload.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.url) cmd('insertHTML',`<img src="${d.url}" style="max-width:100%;border-radius:8px;display:block;margin:12px 0" alt="">`);
      else showToast('Upload failed: '+(d.error||'unknown'),false);
    }).catch(()=>showToast('Upload error',false));
  input.value='';
}

/* ── SIDEBAR TABS ─────────────────────────────────────── */
function switchTab(name){
  ['blocks','templates'].forEach(t=>{
    document.getElementById('tab-'+t).style.display=t===name?'block':'none';
    document.getElementById('tab-btn-'+t).classList.toggle('active',t===name);
  });
}

/* ── HERO IMAGE CONTROLS ──────────────────────────────── */
function uploadHeroImg(input){
  if(!input.files[0]) return;
  const fd=new FormData(); fd.append('pb_image',input.files[0]);
  showToast('Uploading image…',true);
  fetch('/admin/actions/pb_upload.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.url){
        document.getElementById('pi-hero-img').src=d.url;
        document.getElementById('pi-img-wrap').style.display='block';
        document.getElementById('pi-no-img').style.display='none';
        saveHeroMeta({image_url:d.url});
        showToast('Hero image updated!');
      } else showToast('Upload failed: '+(d.error||'error'),false);
    }).catch(()=>showToast('Upload error',false));
  input.value='';
}
function heroImgUrl(){
  const url=prompt('Hero image URL:','https://'); if(!url) return;
  document.getElementById('pi-hero-img').src=url;
  document.getElementById('pi-img-wrap').style.display='block';
  document.getElementById('pi-no-img').style.display='none';
  saveHeroMeta({image_url:url});
  showToast('Hero image updated!');
}
function removeHeroImg(){
  if(!confirm('Remove the hero image?')) return;
  document.getElementById('pi-img-wrap').style.display='none';
  document.getElementById('pi-no-img').style.display='block';
  saveHeroMeta({remove:true});
  showToast('Hero image removed');
}
function setImgSize(sz){
  document.querySelectorAll('#pi-size-row .pi-ctrl-btn').forEach(b=>{
    b.classList.toggle('active',b.textContent.trim().toLowerCase()===sz);
  });
  saveHeroMeta({image_size:sz});
}
function setImgPos(pos){
  document.querySelectorAll('#pi-pos-row .pi-ctrl-btn').forEach(b=>{
    b.classList.toggle('active',b.textContent.trim().toLowerCase()===pos);
  });
  saveHeroMeta({image_pos:pos});
}
function saveHeroMeta(extra={}){
  fetch('/admin/actions/save_hero_meta.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id:<?= $id ?>,...extra})
  }).then(r=>r.json())
    .then(d=>{if(!d.ok) showToast('Error: '+(d.error||'failed'),false);})
    .catch(()=>showToast('Network error',false));
}

/* ── LAYOUT BLOCKS ────────────────────────────────────── */
const BLOCKS = {
hero:`<div style="background:linear-gradient(135deg,#111 0%,#1c1c1c 100%);border-radius:12px;padding:52px 36px;text-align:center;margin:0 0 24px">
  <p style="font-size:10px;letter-spacing:.18em;color:#C8A030;text-transform:uppercase;margin:0 0 10px">DUHN FRAGRANCES</p>
  <h2 style="font-size:28px;font-weight:700;color:#fff;margin:0 0 14px;letter-spacing:.04em">Hero Heading Here</h2>
  <p style="color:#aaa;margin:0 0 26px;max-width:460px;display:inline-block">Add your intro paragraph here.</p><br>
  <a href="/collections.php" style="display:inline-block;background:#C8A030;color:#000;padding:12px 30px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.06em;font-size:13px">EXPLORE COLLECTION</a>
</div>`,
'2col':`<div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;margin:20px 0">
  <div><h3 style="font-size:17px;font-weight:600;margin:0 0 10px">Left Heading</h3><p style="color:#555;font-size:14px;line-height:1.75">Left column content here.</p></div>
  <div><h3 style="font-size:17px;font-weight:600;margin:0 0 10px">Right Heading</h3><p style="color:#555;font-size:14px;line-height:1.75">Right column content here.</p></div>
</div>`,
imgtext:`<div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:center;margin:20px 0">
  <img src="https://via.placeholder.com/400x280/f0ede8/C8A030?text=Your+Image" style="width:100%;border-radius:10px;display:block" alt="">
  <div>
    <h3 style="font-size:18px;font-weight:700;margin:0 0 12px">Section Heading</h3>
    <p style="color:#555;font-size:14px;line-height:1.75;margin:0 0 16px">Descriptive text here.</p>
    <a href="/collections.php" style="display:inline-block;background:#1a1a1a;color:#fff;padding:10px 22px;border-radius:6px;font-weight:600;text-decoration:none;font-size:13px">Learn More →</a>
  </div>
</div>`,
quote:`<blockquote style="border-left:4px solid #C8A030;padding:18px 24px;margin:24px 0;background:#fdfbf6;border-radius:0 8px 8px 0">
  <p style="font-style:italic;color:#333;margin:0 0 10px;font-size:17px;line-height:1.7">"Write your quote or key sentence here."</p>
  <cite style="font-size:12px;color:#999;font-style:normal">— Name or Source</cite>
</blockquote>`,
features:`<ul style="list-style:none;padding:0;margin:20px 0">
  <li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><span style="color:#C8A030;font-size:20px;flex-shrink:0;line-height:1">✓</span><div><strong>Feature Title</strong><br><span style="font-size:13px;color:#777">Feature description here.</span></div></li>
  <li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><span style="color:#C8A030;font-size:20px;flex-shrink:0;line-height:1">✓</span><div><strong>Feature Title</strong><br><span style="font-size:13px;color:#777">Feature description here.</span></div></li>
  <li style="display:flex;gap:14px;padding:14px 0"><span style="color:#C8A030;font-size:20px;flex-shrink:0;line-height:1">✓</span><div><strong>Feature Title</strong><br><span style="font-size:13px;color:#777">Feature description here.</span></div></li>
</ul>`,
cta:`<div style="background:linear-gradient(135deg,rgba(200,160,48,.10),rgba(200,160,48,.03));border:1px solid rgba(200,160,48,.28);border-radius:12px;padding:32px;text-align:center;margin:24px 0">
  <h3 style="color:#C8A030;margin:0 0 10px;font-size:20px;font-weight:700">Your CTA Heading</h3>
  <p style="color:#666;margin:0 0 22px;font-size:14px">Add supporting copy here.</p>
  <a href="/collections.php" style="display:inline-block;background:#C8A030;color:#000;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.05em;font-size:13px">SHOP NOW</a>
</div>`,
divider:`<div style="display:flex;align-items:center;gap:18px;margin:28px 0">
  <div style="flex:1;height:1px;background:rgba(200,160,48,.3)"></div>
  <span style="font-size:11px;letter-spacing:.14em;color:#C8A030;text-transform:uppercase;font-weight:600">Section Title</span>
  <div style="flex:1;height:1px;background:rgba(200,160,48,.3)"></div>
</div>`,
spacer:`<div style="height:40px"></div>`
};

function insertBlock(type){
  let html=BLOCKS[type]; if(!html) return;
  if(type==='cta'){
    const url=prompt('Button URL:','/collections.php'); if(url===null)return;
    const text=prompt('Button Text:','SHOP NOW'); if(text===null)return;
    const head=prompt('CTA Heading:','Your CTA Heading');
    html=html.replace('href="/collections.php"',`href="${url||'/collections.php'}"`)
             .replace('>SHOP NOW<',`>${text||'SHOP NOW'}<`)
             .replace('Your CTA Heading',head||'Your CTA Heading');
  }
  if(type==='hero'){
    const url=prompt('Button URL:','/collections.php'); if(url===null)return;
    const text=prompt('Button Text:','EXPLORE COLLECTION'); if(text===null)return;
    html=html.replace('href="/collections.php"',`href="${url||'/collections.php'}"`)
             .replace('>EXPLORE COLLECTION<',`>${text||'EXPLORE COLLECTION'}<`);
  }
  focusEditor();
  if(!inCode) cmd('insertHTML',html);
  else codeEl.value+='\n'+html;
  markDirty();
}

/* ── BUILT-IN TEMPLATES ───────────────────────────────── */
const BUILT_IN_TEMPLATES = [
  {key:'product-story',emoji:'📖',name:'Product Story',desc:'Image + story + features',html:`<div style="background:#111;border-radius:12px;padding:48px 36px;text-align:center;margin-bottom:32px"><p style="color:#C8A030;font-size:11px;letter-spacing:.16em;text-transform:uppercase;margin:0 0 12px">DUHN FRAGRANCES</p><h2 style="color:#fff;font-size:30px;font-weight:700;margin:0 0 16px">The Story Behind the Scent</h2><p style="color:rgba(255,255,255,.7);max-width:500px;display:inline-block;line-height:1.75">Every fragrance tells a story. This one begins with you.</p></div><h3 style="font-size:20px;font-weight:700;margin:28px 0 14px">The Inspiration</h3><p style="color:#444;line-height:1.85;margin-bottom:16px">Write the story of this fragrance — its origins, its inspiration, the emotions it was designed to evoke. This is where your brand voice shines.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:32px 0"><div style="background:#fdfbf6;border-radius:10px;padding:24px"><h4 style="color:#C8A030;margin:0 0 10px">Top Notes</h4><p style="color:#555;font-size:14px;line-height:1.7">Bergamot · Lemon · Pink Pepper</p></div><div style="background:#fdfbf6;border-radius:10px;padding:24px"><h4 style="color:#C8A030;margin:0 0 10px">Base Notes</h4><p style="color:#555;font-size:14px;line-height:1.7">Oud · Sandalwood · Amber</p></div></div><ul style="list-style:none;padding:0;margin:24px 0"><li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><span style="color:#C8A030;font-size:20px;flex-shrink:0">✓</span><div><strong>Long-lasting formula</strong><br><span style="font-size:13px;color:#777">12+ hours on skin</span></div></li><li style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0"><span style="color:#C8A030;font-size:20px;flex-shrink:0">✓</span><div><strong>Premium ingredients</strong><br><span style="font-size:13px;color:#777">Sourced from around the world</span></div></li><li style="display:flex;gap:14px;padding:14px 0"><span style="color:#C8A030;font-size:20px;flex-shrink:0">✓</span><div><strong>50ml · 899 EGP</strong><br><span style="font-size:13px;color:#777">Free delivery in Cairo</span></div></li></ul><div style="background:linear-gradient(135deg,rgba(200,160,48,.1),rgba(200,160,48,.03));border:1px solid rgba(200,160,48,.3);border-radius:12px;padding:32px;text-align:center;margin-top:32px"><h3 style="color:#C8A030;margin:0 0 10px;font-size:20px">Ready to make it yours?</h3><p style="color:#666;margin:0 0 20px;font-size:14px">Join thousands who have found their signature scent.</p><a href="/collections.php" style="display:inline-block;background:#C8A030;color:#000;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none;font-size:13px">SHOP NOW</a></div>`},

  {key:'lookbook',emoji:'🖼️',name:'Lookbook',desc:'Editorial image layout',html:`<h2 style="font-size:26px;font-weight:700;letter-spacing:.04em;margin-bottom:8px">Lookbook 2025</h2><p style="color:#777;font-size:15px;margin-bottom:32px">A visual journey through scent and style.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px"><img src="https://via.placeholder.com/400x500/1a1a1a/C8A030?text=Look+01" style="width:100%;border-radius:10px;display:block" alt=""><div style="display:flex;flex-direction:column;gap:16px"><img src="https://via.placeholder.com/400x230/f0ede8/C8A030?text=Look+02" style="width:100%;border-radius:10px;display:block" alt=""><img src="https://via.placeholder.com/400x230/111/C8A030?text=Look+03" style="width:100%;border-radius:10px;display:block" alt=""></div></div><blockquote style="border-left:4px solid #C8A030;padding:18px 24px;margin:24px 0;background:#fdfbf6;border-radius:0 8px 8px 0"><p style="font-style:italic;color:#333;font-size:17px;line-height:1.7;margin:0 0 8px">"Your fragrance is the finishing touch that completes every look."</p><cite style="font-size:12px;color:#999;font-style:normal">— DUHN Editorial</cite></blockquote><p style="color:#444;line-height:1.85">Describe the mood, the season, the occasions that inspired this collection. Tell the story behind each look and how the fragrance completes it.</p>`},

  {key:'magazine',emoji:'📰',name:'Magazine',desc:'2-col intro + pull quote',html:`<div style="display:flex;align-items:center;gap:6px;margin-bottom:20px"><div style="height:2px;width:32px;background:#C8A030"></div><span style="font-size:10px;font-weight:700;letter-spacing:.16em;color:#C8A030;text-transform:uppercase">Feature Story</span></div><h2 style="font-size:28px;font-weight:700;line-height:1.2;margin-bottom:24px">The Art of Smelling Extraordinary</h2><div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:28px"><p style="color:#444;font-size:15px;line-height:1.85">Your fragrance communicates who you are before you speak. It lingers in a room long after you've left. It triggers memories in strangers who catch a brief whiff as you pass by.</p><p style="color:#444;font-size:15px;line-height:1.85">At DUHN, we believe fragrance is not a luxury — it's an essential expression of your identity. That's why every bottle we create begins with a question: who do you want to be today?</p></div><div style="background:#1a1a1a;border-radius:12px;padding:32px;margin:28px 0;text-align:center"><p style="font-size:22px;font-weight:300;color:#fff;line-height:1.6;font-style:italic;margin:0">"A great fragrance is not worn — it is inhabited."</p></div><h3 style="font-size:18px;font-weight:700;margin:28px 0 14px">The Making</h3><p style="color:#444;line-height:1.85;margin-bottom:16px">Add your brand story, production details, or the inspiration behind this fragrance. This section supports multiple paragraphs with rich formatting.</p>`},

  {key:'minimalist',emoji:'✦',name:'Minimalist',desc:'Clean centered text',html:`<div style="text-align:center;max-width:540px;margin:0 auto;padding:24px 0"><p style="font-size:10px;letter-spacing:.2em;color:#C8A030;text-transform:uppercase;margin:0 0 20px">DUHN FRAGRANCES</p><h2 style="font-size:32px;font-weight:300;letter-spacing:.08em;line-height:1.3;margin:0 0 20px;color:#1a1a1a">Elegance in every drop</h2><div style="width:40px;height:1px;background:#C8A030;margin:0 auto 20px"></div><p style="color:#666;font-size:16px;line-height:1.9;margin-bottom:24px">Your words here. Write with simplicity and let the fragrance speak for itself. This minimalist template is perfect for letting your message breathe.</p><p style="color:#888;font-size:14px;line-height:1.85;margin-bottom:32px">Add a second paragraph here with supporting details, testimonials, or a compelling description of the experience your fragrance creates.</p><a href="/collections.php" style="display:inline-block;border:1px solid #1a1a1a;color:#1a1a1a;padding:12px 32px;border-radius:40px;font-size:13px;font-weight:600;text-decoration:none;letter-spacing:.1em;transition:all .2s">EXPLORE →</a></div>`},

  {key:'fragrance-notes',emoji:'🌹',name:'Fragrance Profile',desc:'Top/Mid/Base notes',html:`<h2 style="font-size:22px;font-weight:700;margin-bottom:6px">Fragrance Profile</h2><p style="color:#888;font-size:14px;margin-bottom:32px">A detailed olfactory journey</p><div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:32px"><div style="background:#f8f6f2;border-radius:12px;padding:20px;text-align:center"><div style="font-size:28px;margin-bottom:8px">🍋</div><div style="font-size:10px;font-weight:700;letter-spacing:.12em;color:#C8A030;text-transform:uppercase;margin-bottom:10px">Top Notes</div><p style="font-size:13px;color:#555;line-height:1.7">Bergamot<br>Lemon<br>Pink Pepper</p></div><div style="background:#f0ede8;border-radius:12px;padding:20px;text-align:center"><div style="font-size:28px;margin-bottom:8px">🌸</div><div style="font-size:10px;font-weight:700;letter-spacing:.12em;color:#C8A030;text-transform:uppercase;margin-bottom:10px">Heart Notes</div><p style="font-size:13px;color:#555;line-height:1.7">Rose<br>Jasmine<br>Iris</p></div><div style="background:#1a1a1a;border-radius:12px;padding:20px;text-align:center"><div style="font-size:28px;margin-bottom:8px">🪵</div><div style="font-size:10px;font-weight:700;letter-spacing:.12em;color:#C8A030;text-transform:uppercase;margin-bottom:10px">Base Notes</div><p style="font-size:13px;color:rgba(255,255,255,.7);line-height:1.7">Oud<br>Sandalwood<br>Amber</p></div></div><p style="color:#444;line-height:1.85;margin-bottom:20px">Describe the overall character and journey of this fragrance. What does it smell like when first applied? How does it evolve throughout the day?</p><div style="background:linear-gradient(to right,rgba(200,160,48,.1),transparent);border-left:4px solid #C8A030;padding:16px 20px;border-radius:0 8px 8px 0;margin-bottom:24px"><p style="font-size:13px;color:#555;margin:0"><strong>Concentration:</strong> Eau de Parfum &nbsp;·&nbsp; <strong>Size:</strong> 50ml &nbsp;·&nbsp; <strong>Longevity:</strong> 10-12 hours &nbsp;·&nbsp; <strong>Sillage:</strong> Moderate</p></div>`},

  {key:'dark-luxury',emoji:'🖤',name:'Dark Luxury',desc:'Dark hero + gold accents',html:`<div style="background:linear-gradient(160deg,#0a0a0a,#1a1410);border-radius:16px;padding:56px 40px;margin-bottom:28px;position:relative;overflow:hidden"><div style="position:absolute;top:-40px;right:-40px;width:200px;height:200px;background:radial-gradient(circle,rgba(200,160,48,.15),transparent 70%);border-radius:50%"></div><p style="color:#C8A030;font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin:0 0 14px">Exclusive Collection</p><h2 style="color:#fff;font-size:32px;font-weight:700;line-height:1.2;margin:0 0 16px;max-width:400px">Born from the Rarest Ingredients on Earth</h2><p style="color:rgba(255,255,255,.6);font-size:15px;line-height:1.75;max-width:460px;margin:0 0 28px">A fragrance this rare deserves only the finest moments. Wear it when nothing but extraordinary will do.</p><a href="/collections.php" style="display:inline-block;background:transparent;border:1px solid rgba(200,160,48,.6);color:#C8A030;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.08em;font-size:13px">DISCOVER MORE</a></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px"><div style="background:#111;border-radius:10px;padding:24px"><h4 style="color:#C8A030;margin:0 0 8px;font-size:15px">The Oud</h4><p style="color:rgba(255,255,255,.6);font-size:13px;line-height:1.7;margin:0">Aged for decades in the heart of Southeast Asia, this oud is liquid gold.</p></div><div style="background:#111;border-radius:10px;padding:24px"><h4 style="color:#C8A030;margin:0 0 8px;font-size:15px">The Amber</h4><p style="color:rgba(255,255,255,.6);font-size:13px;line-height:1.7;margin:0">Warm, rich, and lasting — our amber stays with you from dusk till dawn.</p></div></div>`},

  {key:'editorial',emoji:'⚡',name:'Bold Editorial',desc:'Large heading + stats',html:`<div style="border-bottom:3px solid #C8A030;padding-bottom:16px;margin-bottom:28px"><span style="font-size:10px;font-weight:700;letter-spacing:.16em;color:#C8A030;text-transform:uppercase">DUHN · Editorial</span></div><h2 style="font-size:38px;font-weight:900;line-height:1.1;letter-spacing:-.01em;margin-bottom:24px;color:#1a1a1a">What Makes a Fragrance<br><em style="color:#C8A030;font-style:normal">Truly Unforgettable?</em></h2><p style="font-size:18px;color:#555;line-height:1.75;margin-bottom:32px;max-width:560px">The answer isn't just in the bottle. It's in the story, the craftsmanship, and the moment it finds its owner.</p><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid #eee;border-radius:12px;overflow:hidden;margin-bottom:32px"><div style="padding:24px;text-align:center;border-right:1px solid #eee"><div style="font-size:32px;font-weight:800;color:#C8A030">50+</div><div style="font-size:12px;color:#888;margin-top:4px">Unique Scents</div></div><div style="padding:24px;text-align:center;border-right:1px solid #eee"><div style="font-size:32px;font-weight:800;color:#C8A030">12h</div><div style="font-size:12px;color:#888;margin-top:4px">Long Lasting</div></div><div style="padding:24px;text-align:center"><div style="font-size:32px;font-weight:800;color:#C8A030">899</div><div style="font-size:12px;color:#888;margin-top:4px">EGP — All 50ml</div></div></div><p style="color:#444;line-height:1.85">Continue your editorial here. This template is designed to make a bold statement — perfect for launches, seasonal features, or brand stories.</p>`},

  {key:'collection',emoji:'💎',name:'Collection Showcase',desc:'3-product grid style',html:`<h2 style="font-size:24px;font-weight:700;margin-bottom:6px">This Season's Picks</h2><p style="color:#888;margin-bottom:28px">Our curated selection, chosen for you.</p><div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:32px"><div style="background:#fdfbf6;border-radius:12px;overflow:hidden"><img src="https://via.placeholder.com/300x200/1a1a1a/C8A030?text=Scent+01" style="width:100%;display:block;height:120px;object-fit:cover" alt=""><div style="padding:16px"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Midnight Oud</div><div style="font-size:12px;color:#888;margin-bottom:8px">Warm · Woody · Deep</div><div style="font-size:13px;font-weight:700;color:#C8A030">899 EGP</div></div></div><div style="background:#fdfbf6;border-radius:12px;overflow:hidden"><img src="https://via.placeholder.com/300x200/f0ede8/C8A030?text=Scent+02" style="width:100%;display:block;height:120px;object-fit:cover" alt=""><div style="padding:16px"><div style="font-weight:700;font-size:14px;margin-bottom:4px">White Rose</div><div style="font-size:12px;color:#888;margin-bottom:8px">Floral · Fresh · Soft</div><div style="font-size:13px;font-weight:700;color:#C8A030">899 EGP</div></div></div><div style="background:#fdfbf6;border-radius:12px;overflow:hidden"><img src="https://via.placeholder.com/300x200/111/C8A030?text=Scent+03" style="width:100%;display:block;height:120px;object-fit:cover" alt=""><div style="padding:16px"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Amber Noir</div><div style="font-size:12px;color:#888;margin-bottom:8px">Spicy · Bold · Rich</div><div style="font-size:13px;font-weight:700;color:#C8A030">899 EGP</div></div></div></div><p style="color:#444;line-height:1.85;margin-bottom:20px">Add a description of this collection below the showcase. Tell your customers what makes these fragrances special and why they were chosen for this feature.</p>`},

  {key:'behind-scent',emoji:'🏺',name:'Behind the Scent',desc:'Brand story format',html:`<div style="display:flex;align-items:center;gap:16px;margin-bottom:28px"><div style="width:56px;height:56px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0"><span style="font-size:24px">🏺</span></div><div><h2 style="font-size:20px;font-weight:700;margin:0 0 4px">Behind the Scent</h2><p style="color:#888;font-size:13px;margin:0">How this fragrance was created</p></div></div><p style="color:#444;line-height:1.85;margin-bottom:20px">Every great fragrance begins with a vision. Ours started in the souks of Cairo, where ancient spice traders mixed oud, rose, and amber in time-honored rituals passed down through generations.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:28px 0"><div><h4 style="font-size:14px;font-weight:700;color:#C8A030;margin:0 0 8px">🌍 The Origin</h4><p style="color:#555;font-size:13px;line-height:1.7">Describe where the inspiration came from — a place, a person, a moment in time.</p></div><div><h4 style="font-size:14px;font-weight:700;color:#C8A030;margin:0 0 8px">⚗️ The Craft</h4><p style="color:#555;font-size:13px;line-height:1.7">Explain the process, the time it took, the decisions made to perfect the formula.</p></div><div><h4 style="font-size:14px;font-weight:700;color:#C8A030;margin:0 0 8px">🌹 The Ingredients</h4><p style="color:#555;font-size:13px;line-height:1.7">Highlight the hero ingredients and why they were chosen for this composition.</p></div><div><h4 style="font-size:14px;font-weight:700;color:#C8A030;margin:0 0 8px">✨ The Result</h4><p style="color:#555;font-size:13px;line-height:1.7">Share how the final fragrance feels, performs, and makes the wearer feel.</p></div></div>`},

  {key:'gift-guide',emoji:'🎁',name:'Gift Guide',desc:'Gift recommendations',html:`<div style="background:linear-gradient(135deg,#111,#1c1008);border-radius:12px;padding:40px 32px;text-align:center;margin-bottom:28px"><p style="color:#C8A030;font-size:11px;letter-spacing:.16em;text-transform:uppercase;margin:0 0 12px">DUHN GIFT GUIDE</p><h2 style="color:#fff;font-size:26px;font-weight:700;margin:0 0 12px">The Perfect Gift for Every Person</h2><p style="color:rgba(255,255,255,.65);font-size:15px;max-width:400px;display:inline-block;line-height:1.75">Because the right scent is the most personal gift of all.</p></div><div style="margin-bottom:12px"><h3 style="font-size:16px;font-weight:700;margin-bottom:6px">🎁 For Him — The Bold Choice</h3><p style="color:#555;font-size:14px;line-height:1.75;margin-bottom:4px">Deep, confident, and powerful. Our woody oud collection commands attention.</p><p style="font-size:13px;color:#C8A030;font-weight:600">Best pick: Midnight Oud · 899 EGP</p></div><hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0"><div style="margin-bottom:12px"><h3 style="font-size:16px;font-weight:700;margin-bottom:6px">💐 For Her — The Elegant Choice</h3><p style="color:#555;font-size:14px;line-height:1.75;margin-bottom:4px">Soft, feminine, and unforgettable. Floral meets warm amber in perfect harmony.</p><p style="font-size:13px;color:#C8A030;font-weight:600">Best pick: White Rose · 899 EGP</p></div><hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0"><div style="margin-bottom:28px"><h3 style="font-size:16px;font-weight:700;margin-bottom:6px">💫 For Anyone — The Universal Choice</h3><p style="color:#555;font-size:14px;line-height:1.75;margin-bottom:4px">A crowd-pleaser that works for every personality, mood, and occasion.</p><p style="font-size:13px;color:#C8A030;font-weight:600">Best pick: Golden Amber · 899 EGP</p></div>`},

  {key:'seasonal',emoji:'🌙',name:'Seasonal / Occasion',desc:'Occasion-based layout',html:`<div style="display:flex;align-items:center;gap:18px;margin:0 0 28px"><div style="flex:1;height:1px;background:rgba(200,160,48,.3)"></div><span style="font-size:11px;letter-spacing:.14em;color:#C8A030;text-transform:uppercase;font-weight:600">Winter Collection</span><div style="flex:1;height:1px;background:rgba(200,160,48,.3)"></div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px"><div style="background:#f8f6f2;border-radius:12px;padding:24px"><h4 style="margin:0 0 12px;font-size:15px;font-weight:700">🌙 Evening</h4><p style="color:#555;font-size:13px;line-height:1.7;margin:0">Heavy, rich, and sensual — designed for dimly lit rooms and memorable nights.</p></div><div style="background:#111;border-radius:12px;padding:24px"><h4 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#fff">☀️ Daytime</h4><p style="color:rgba(255,255,255,.65);font-size:13px;line-height:1.7;margin:0">Fresh, energetic, and clean — perfect for long days and outdoor meetings.</p></div><div style="background:#111;border-radius:12px;padding:24px"><h4 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#fff">💼 Office</h4><p style="color:rgba(255,255,255,.65);font-size:13px;line-height:1.7;margin:0">Professional yet distinctive — subtle enough to impress without overwhelming.</p></div><div style="background:#f8f6f2;border-radius:12px;padding:24px"><h4 style="margin:0 0 12px;font-size:15px;font-weight:700">🎉 Special Events</h4><p style="color:#555;font-size:13px;line-height:1.7;margin:0">Turn heads at weddings, celebrations, and every moment worth remembering.</p></div></div><p style="color:#444;line-height:1.85">Add your seasonal message here — what occasions inspired this lineup? How should customers choose the right scent for the right moment?</p>`},

  {key:'testimonials',emoji:'⭐',name:'Testimonials',desc:'Customer reviews grid',html:`<h2 style="font-size:22px;font-weight:700;margin-bottom:6px;text-align:center">What Our Customers Say</h2><p style="color:#888;text-align:center;margin-bottom:28px">Thousands of happy customers across Egypt</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px"><div style="background:#fdfbf6;border-radius:12px;padding:20px;border-left:3px solid #C8A030"><p style="font-style:italic;color:#444;font-size:14px;line-height:1.7;margin:0 0 12px">"I get compliments every single day I wear this. People always ask what I'm wearing — the answer is always DUHN."</p><div style="font-size:12px;font-weight:700;color:#333">Ahmed K.</div><div style="font-size:11px;color:#aaa">Cairo · ⭐⭐⭐⭐⭐</div></div><div style="background:#fdfbf6;border-radius:12px;padding:20px;border-left:3px solid #C8A030"><p style="font-style:italic;color:#444;font-size:14px;line-height:1.7;margin:0 0 12px">"Worth every pound. I've tried many imported brands at triple the price and none compare to the quality of DUHN."</p><div style="font-size:12px;font-weight:700;color:#333">Sara M.</div><div style="font-size:11px;color:#aaa">Alexandria · ⭐⭐⭐⭐⭐</div></div><div style="background:#fdfbf6;border-radius:12px;padding:20px;border-left:3px solid #C8A030"><p style="font-style:italic;color:#444;font-size:14px;line-height:1.7;margin:0 0 12px">"The longevity is incredible. Sprayed once in the morning and I could still smell it at midnight. Impressive."</p><div style="font-size:12px;font-weight:700;color:#333">Omar F.</div><div style="font-size:11px;color:#aaa">Giza · ⭐⭐⭐⭐⭐</div></div><div style="background:#fdfbf6;border-radius:12px;padding:20px;border-left:3px solid #C8A030"><p style="font-style:italic;color:#444;font-size:14px;line-height:1.7;margin:0 0 12px">"Finally a local brand that takes fragrance seriously. The packaging is beautiful and the scent is even better."</p><div style="font-size:12px;font-weight:700;color:#333">Nour A.</div><div style="font-size:11px;color:#aaa">Mansoura · ⭐⭐⭐⭐⭐</div></div></div>`},

  {key:'how-to-wear',emoji:'👔',name:'How to Wear',desc:'Step-by-step guide',html:`<h2 style="font-size:22px;font-weight:700;margin-bottom:6px">How to Wear It Right</h2><p style="color:#888;margin-bottom:28px">Get the most from your fragrance with these expert tips</p><div style="display:flex;flex-direction:column;gap:0"><div style="display:flex;gap:20px;padding:20px 0;border-bottom:1px solid #f0f0f0"><div style="width:40px;height:40px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:16px">1</div><div><h4 style="margin:0 0 6px;font-size:15px;font-weight:700">Apply to Pulse Points</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Spray on wrists, neck, behind ears, and inner elbows. These warm areas diffuse fragrance throughout the day.</p></div></div><div style="display:flex;gap:20px;padding:20px 0;border-bottom:1px solid #f0f0f0"><div style="width:40px;height:40px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:16px">2</div><div><h4 style="margin:0 0 6px;font-size:15px;font-weight:700">Don't Rub — Let It Dry</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Rubbing breaks the molecular structure of the fragrance and alters the scent. Spray and let it settle naturally.</p></div></div><div style="display:flex;gap:20px;padding:20px 0;border-bottom:1px solid #f0f0f0"><div style="width:40px;height:40px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:16px">3</div><div><h4 style="margin:0 0 6px;font-size:15px;font-weight:700">Moisturize First</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Fragrance lasts longer on moisturized skin. Apply an unscented lotion before your fragrance for extended wear.</p></div></div><div style="display:flex;gap:20px;padding:20px 0"><div style="width:40px;height:40px;background:#C8A030;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:800;color:#000;font-size:16px">4</div><div><h4 style="margin:0 0 6px;font-size:15px;font-weight:700">Layer for Impact</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Use matching body wash or lotion to build a fuller scent experience that stays with you all day.</p></div></div></div>`},

  {key:'ingredients',emoji:'🌿',name:'Ingredients',desc:'Detailed ingredient list',html:`<h2 style="font-size:22px;font-weight:700;margin-bottom:6px">What's Inside</h2><p style="color:#888;margin-bottom:28px">Transparency is our commitment. Every ingredient, explained.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px"><div style="border:1px solid #e8e4df;border-radius:10px;padding:18px"><div style="font-size:20px;margin-bottom:8px">🌹</div><h4 style="font-size:14px;font-weight:700;margin-bottom:6px">Rose Absolute</h4><p style="font-size:12px;color:#777;line-height:1.65;margin:0">Sourced from Bulgarian rose fields. Adds a rich, velvety floral heart that is warm and deeply feminine.</p></div><div style="border:1px solid #e8e4df;border-radius:10px;padding:18px"><div style="font-size:20px;margin-bottom:8px">🪵</div><h4 style="font-size:14px;font-weight:700;margin-bottom:6px">Oud Oil</h4><p style="font-size:12px;color:#777;line-height:1.65;margin:0">Aged agarwood oil from Southeast Asia. Forms the deep, resinous base that defines Arabic fragrance luxury.</p></div><div style="border:1px solid #e8e4df;border-radius:10px;padding:18px"><div style="font-size:20px;margin-bottom:8px">🌾</div><h4 style="font-size:14px;font-weight:700;margin-bottom:6px">Ambergris Accord</h4><p style="font-size:12px;color:#777;line-height:1.65;margin:0">A warm, sweet, radiant base that gives this fragrance its incredible longevity and skin warmth.</p></div><div style="border:1px solid #e8e4df;border-radius:10px;padding:18px"><div style="font-size:20px;margin-bottom:8px">🍋</div><h4 style="font-size:14px;font-weight:700;margin-bottom:6px">Bergamot</h4><p style="font-size:12px;color:#777;line-height:1.65;margin:0">Bright Italian bergamot opens the fragrance with a sparkling citrus burst that energizes and uplifts.</p></div></div><p style="font-size:12px;color:#aaa;text-align:center">Full INCI ingredient list available on the bottle. Alcohol Denat., Parfum, Aqua...</p>`},

  {key:'faq',emoji:'❓',name:'FAQ Page',desc:'Questions & answers',html:`<h2 style="font-size:22px;font-weight:700;margin-bottom:6px">Frequently Asked Questions</h2><p style="color:#888;margin-bottom:28px">Everything you need to know</p><div style="display:flex;flex-direction:column;gap:0"><div style="border-bottom:1px solid #f0f0f0;padding:18px 0"><h4 style="font-size:15px;font-weight:700;margin:0 0 8px;color:#1a1a1a">How long does the fragrance last?</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Our Eau de Parfum concentration typically lasts 10-14 hours on skin. Longevity can vary depending on skin type and weather conditions.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:18px 0"><h4 style="font-size:15px;font-weight:700;margin:0 0 8px;color:#1a1a1a">Is it alcohol-based or oil-based?</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">DUHN fragrances are alcohol-based Eau de Parfum, ensuring optimal projection and a clean, modern wearing experience.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:18px 0"><h4 style="font-size:15px;font-weight:700;margin:0 0 8px;color:#1a1a1a">Do you offer free delivery?</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Yes — free delivery is available within Cairo and Giza on all orders. Delivery to other governorates is available at a flat rate.</p></div><div style="border-bottom:1px solid #f0f0f0;padding:18px 0"><h4 style="font-size:15px;font-weight:700;margin:0 0 8px;color:#1a1a1a">Can I return a fragrance?</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Due to the nature of the product, opened fragrances cannot be returned. However, if you receive a damaged or incorrect item, we will replace it immediately.</p></div><div style="padding:18px 0"><h4 style="font-size:15px;font-weight:700;margin:0 0 8px;color:#1a1a1a">How should I store my fragrance?</h4><p style="color:#555;font-size:14px;line-height:1.7;margin:0">Store away from direct sunlight and heat. A cool, dark place like a drawer or cabinet is ideal to preserve the fragrance quality.</p></div></div>`},

  {key:'announcement',emoji:'📣',name:'Announcement',desc:'Bold banner announcement',html:`<div style="background:#C8A030;border-radius:12px;padding:40px 32px;text-align:center;margin-bottom:28px"><h2 style="color:#000;font-size:28px;font-weight:900;letter-spacing:.04em;margin:0 0 12px">NEW DROP — LIMITED STOCK</h2><p style="color:rgba(0,0,0,.7);font-size:16px;margin:0 0 24px">Our most requested fragrance is finally back. Don't miss it this time.</p><a href="/collections.php" style="display:inline-block;background:#000;color:#C8A030;padding:13px 32px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.08em;font-size:14px">SHOP NOW — 899 EGP</a></div><div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;text-align:center;border:1px solid #eee;border-radius:10px;overflow:hidden;margin-bottom:24px"><div style="padding:20px;border-right:1px solid #eee"><div style="font-size:24px;font-weight:800;color:#C8A030">⚡</div><div style="font-size:12px;color:#555;margin-top:4px;font-weight:600">Limited Qty</div></div><div style="padding:20px;border-right:1px solid #eee"><div style="font-size:24px;font-weight:800;color:#C8A030">🚀</div><div style="font-size:12px;color:#555;margin-top:4px;font-weight:600">Fast Delivery</div></div><div style="padding:20px"><div style="font-size:24px;font-weight:800;color:#C8A030">💯</div><div style="font-size:12px;color:#555;margin-top:4px;font-weight:600">Guaranteed</div></div></div><p style="color:#444;line-height:1.85">Add more details about this announcement below — what's special about this drop, why it's limited, and what customers can expect.</p>`},

  {key:'full-dark',emoji:'🌑',name:'Full Dark Theme',desc:'Dark background content',html:`<div style="background:#0a0a0a;border-radius:16px;padding:48px 36px;color:#e8e8e8"><p style="color:#C8A030;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin:0 0 14px">DUHN FRAGRANCES</p><h2 style="color:#fff;font-size:28px;font-weight:700;margin:0 0 16px">Your Headline Here</h2><p style="color:rgba(255,255,255,.7);font-size:15px;line-height:1.85;margin:0 0 24px">Write your introductory paragraph here. Dark backgrounds create a premium, luxurious feel that works perfectly for oud and oriental fragrance stories.</p><div style="display:flex;align-items:center;gap:18px;margin:24px 0"><div style="flex:1;height:1px;background:rgba(200,160,48,.25)"></div><span style="font-size:10px;letter-spacing:.14em;color:#C8A030;text-transform:uppercase;font-weight:600;white-space:nowrap">The Details</span><div style="flex:1;height:1px;background:rgba(200,160,48,.25)"></div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:0 0 28px"><div><h4 style="color:#C8A030;font-size:13px;margin:0 0 6px">Concentration</h4><p style="color:rgba(255,255,255,.6);font-size:13px;line-height:1.7;margin:0">Eau de Parfum — 50ml</p></div><div><h4 style="color:#C8A030;font-size:13px;margin:0 0 6px">Longevity</h4><p style="color:rgba(255,255,255,.6);font-size:13px;line-height:1.7;margin:0">10-14 hours on skin</p></div></div><a href="/collections.php" style="display:inline-block;border:1px solid rgba(200,160,48,.5);color:#C8A030;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none;letter-spacing:.08em;font-size:13px">SHOP THIS FRAGRANCE</a></div>`},

  {key:'split-hero',emoji:'↔️',name:'Split Hero',desc:'Left text, right image',html:`<div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:14px;overflow:hidden;margin-bottom:28px;box-shadow:0 8px 40px rgba(0,0,0,.12)"><div style="background:#111;padding:48px 36px;display:flex;flex-direction:column;justify-content:center"><p style="color:#C8A030;font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin:0 0 14px">New Arrival</p><h2 style="color:#fff;font-size:26px;font-weight:700;line-height:1.2;margin:0 0 16px">The Scent That Changes Everything</h2><p style="color:rgba(255,255,255,.65);font-size:14px;line-height:1.75;margin:0 0 24px">Bold. Unique. Unmistakable. This is the fragrance designed for those who refuse to blend in.</p><a href="/collections.php" style="display:inline-block;background:#C8A030;color:#000;padding:11px 24px;border-radius:6px;font-weight:700;text-decoration:none;font-size:13px;letter-spacing:.06em;width:fit-content">SHOP NOW</a></div><img src="https://via.placeholder.com/400x400/1a1a1a/C8A030?text=Your+Image" style="width:100%;height:100%;object-fit:cover;display:block;min-height:280px" alt=""></div><p style="color:#444;line-height:1.85">Continue your content below the split hero. Add more sections, stories, or feature blocks to complete the page.</p>`},

  {key:'video-text',emoji:'🎬',name:'Video + Text',desc:'Video embed placeholder',html:`<h2 style="font-size:22px;font-weight:700;margin-bottom:16px">See It in Action</h2><div style="background:#111;border-radius:12px;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;margin-bottom:24px;cursor:pointer"><div style="text-align:center"><div style="width:64px;height:64px;background:rgba(200,160,48,.9);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px">▶</div><p style="color:rgba(255,255,255,.6);font-size:13px;margin:0">Replace this block with your video embed code</p><code style="color:rgba(200,160,48,.7);font-size:11px;display:block;margin-top:6px">&lt;iframe src="youtube/vimeo URL"&gt;&lt;/iframe&gt;</code></div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:20px"><div><h3 style="font-size:17px;font-weight:700;margin:0 0 10px">What You'll See</h3><p style="color:#555;font-size:14px;line-height:1.7">Describe what the video shows — a behind-the-scenes look, a review, or a product feature walkthrough.</p></div><div><h3 style="font-size:17px;font-weight:700;margin:0 0 10px">Why Watch</h3><p style="color:#555;font-size:14px;line-height:1.7">Explain the value a customer gets from watching — what they'll learn, feel, or discover about the fragrance.</p></div></div>`},

  {key:'compare',emoji:'⚖️',name:'Compare Table',desc:'Side-by-side comparison',html:`<h2 style="font-size:22px;font-weight:700;margin-bottom:6px">How We Compare</h2><p style="color:#888;margin-bottom:24px">See why DUHN is the smart choice</p><table style="width:100%;border-collapse:collapse;margin-bottom:24px"><thead><tr><th style="background:#f8f6f2;padding:12px 16px;text-align:left;font-size:12px;color:#999;text-transform:uppercase;letter-spacing:.08em;border-bottom:2px solid #eee">Feature</th><th style="background:#1a1a1a;padding:12px 16px;text-align:center;font-size:12px;color:#C8A030;text-transform:uppercase;letter-spacing:.08em;border-bottom:2px solid #C8A030">DUHN</th><th style="background:#f8f6f2;padding:12px 16px;text-align:center;font-size:12px;color:#999;text-transform:uppercase;letter-spacing:.08em;border-bottom:2px solid #eee">Others</th></tr></thead><tbody><tr><td style="padding:14px 16px;font-size:14px;color:#444;border-bottom:1px solid #f0f0f0">Price for 50ml</td><td style="padding:14px 16px;text-align:center;font-weight:700;color:#C8A030;background:rgba(200,160,48,.04);border-bottom:1px solid rgba(200,160,48,.1)">899 EGP</td><td style="padding:14px 16px;text-align:center;color:#aaa;border-bottom:1px solid #f0f0f0">2000–4000 EGP</td></tr><tr><td style="padding:14px 16px;font-size:14px;color:#444;border-bottom:1px solid #f0f0f0">Longevity</td><td style="padding:14px 16px;text-align:center;font-weight:700;color:#C8A030;background:rgba(200,160,48,.04);border-bottom:1px solid rgba(200,160,48,.1)">10-14 hours</td><td style="padding:14px 16px;text-align:center;color:#aaa;border-bottom:1px solid #f0f0f0">6-8 hours</td></tr><tr><td style="padding:14px 16px;font-size:14px;color:#444;border-bottom:1px solid #f0f0f0">Made in Egypt</td><td style="padding:14px 16px;text-align:center;font-weight:700;color:#C8A030;background:rgba(200,160,48,.04);border-bottom:1px solid rgba(200,160,48,.1)">✓ Yes</td><td style="padding:14px 16px;text-align:center;color:#aaa;border-bottom:1px solid #f0f0f0">✗ No</td></tr><tr><td style="padding:14px 16px;font-size:14px;color:#444">Free Delivery</td><td style="padding:14px 16px;text-align:center;font-weight:700;color:#C8A030;background:rgba(200,160,48,.04)">✓ Cairo & Giza</td><td style="padding:14px 16px;text-align:center;color:#aaa">✗ Paid only</td></tr></tbody></table>`}
];

/* Render built-in template grid */
(function renderBuiltIn(){
  const grid=document.getElementById('tpl-built-grid');
  grid.innerHTML=BUILT_IN_TEMPLATES.map(t=>`
    <div class="tpl-card" onclick="loadBuiltIn('${t.key}')" title="${t.desc}">
      <span class="tpl-card__emoji">${t.emoji}</span>
      <div class="tpl-card__name">${t.name}</div>
      <div class="tpl-card__desc">${t.desc}</div>
    </div>
  `).join('');
})();

function loadBuiltIn(key){
  const tpl=BUILT_IN_TEMPLATES.find(t=>t.key===key); if(!tpl) return;
  if(!confirm(`Load "${tpl.name}" template?\nThis replaces current editor content.`)) return;
  editor.innerHTML=tpl.html;
  markDirty(); switchTab('blocks');
  showToast(`"${tpl.name}" template loaded`);
}

/* ── CUSTOM TEMPLATES ─────────────────────────────────── */
let customTemplates = <?= json_encode(array_values($templates)) ?>;

function renderCustomTemplates(){
  const el=document.getElementById('tpl-custom-list');
  if(!customTemplates.length){
    el.innerHTML='<p style="font-size:11px;color:#bbb;text-align:center;padding:10px 0">No saved templates yet</p>';
    return;
  }
  el.innerHTML=customTemplates.map(t=>`
    <div class="tpl-custom-item" onclick="loadCustomTemplate('${t.id}')">
      <i class="ph ph-file-text" style="color:#C8A030;font-size:15px;flex-shrink:0"></i>
      <div style="flex:1;min-width:0">
        <div class="tpl-custom-name">${t.name}</div>
        <div class="tpl-custom-date">${t.created}</div>
      </div>
      <button class="tpl-custom-del" onclick="event.stopPropagation();deleteTemplate('${t.id}')" title="Delete"><i class="ph ph-trash"></i></button>
    </div>
  `).join('');
}
renderCustomTemplates();

function loadCustomTemplate(id){
  const tpl=customTemplates.find(t=>t.id===id); if(!tpl) return;
  if(!confirm(`Load "${tpl.name}"?\nThis replaces current editor content.`)) return;
  editor.innerHTML=tpl.html;
  markDirty(); switchTab('blocks');
  showToast(`"${tpl.name}" loaded`);
}
function saveAsTemplate(){
  const name=prompt('Template name:','My Template'); if(!name) return;
  const html=getContent();
  fetch('/admin/actions/save_template.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'save',name,html})
  }).then(r=>r.json())
    .then(d=>{
      if(d.ok){ customTemplates=d.templates; renderCustomTemplates(); showToast(`"${name}" saved as template!`); }
      else showToast('Error: '+(d.error||'failed'),false);
    }).catch(()=>showToast('Network error',false));
}
function deleteTemplate(id){
  if(!confirm('Delete this template?')) return;
  fetch('/admin/actions/save_template.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'delete',id})
  }).then(r=>r.json())
    .then(d=>{
      if(d.ok){ customTemplates=customTemplates.filter(t=>t.id!==id); renderCustomTemplates(); showToast('Template deleted'); }
    }).catch(()=>showToast('Network error',false));
}

/* ── PUBLISH TOGGLE ───────────────────────────────────── */
function togglePublish(){
  const stateEl=document.getElementById('pe-publish-state');
  const next=stateEl.value!=='1';
  stateEl.value=next?'1':'0';
  document.getElementById('pe-publish-wrap').className='pe-publish-wrap '+(next?'published':'unpublished');
  document.getElementById('pe-publish-label').textContent=next?'LIVE':'DRAFT';
  savePage(next);
  showToast(next?'✅ Page is LIVE — READ MORE opens this page':'🔒 Set to DRAFT',next);
}

/* ── SAVE PAGE ────────────────────────────────────────── */
function savePage(publishOverride){
  setStatus('saving');
  const content=getContent();
  const ctaText=document.getElementById('meta-cta-text').value;
  const ctaUrl=document.getElementById('meta-cta-url').value;
  const stateEl=document.getElementById('pe-publish-state');
  const published=publishOverride!==undefined?publishOverride:(stateEl.value==='1');
  fetch('/admin/actions/save_page_content.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id:<?= $id ?>,content,cta_text:ctaText,cta_url:ctaUrl,publish:published})
  }).then(r=>r.json())
    .then(d=>{
      if(d.ok){
        dirty=false; setStatus('saved');
        if(publishOverride===undefined) showToast('Page saved!');
        if(d.mode){
          const live=d.mode==='page';
          stateEl.value=live?'1':'0';
          document.getElementById('pe-publish-wrap').className='pe-publish-wrap '+(live?'published':'unpublished');
          document.getElementById('pe-publish-label').textContent=live?'LIVE':'DRAFT';
        }
      } else { setStatus('error',d.error||'Save failed'); showToast('Error: '+(d.error||'Save failed'),false); }
    }).catch(()=>{ setStatus('error','Network error'); showToast('Network error — are you logged in?',false); });
}

/* ── INIT ─────────────────────────────────────────────── */
editor.addEventListener('input',markDirty);
document.addEventListener('keydown',e=>{
  if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();savePage();}
});
window.addEventListener('beforeunload',e=>{ if(dirty){e.preventDefault();e.returnValue='';} });
updateWC();
</script>
</body>
</html>
