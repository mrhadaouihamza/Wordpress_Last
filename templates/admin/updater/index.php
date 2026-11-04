<?php use function Wordpresso\Core\e; ?>
<div class="page-head updater-heading">
  <div><span class="section-kicker">WORDPRESS DELIVERY</span><h1>Auto Updater</h1><p class="muted">Control update eligibility and manage the releases delivered to connected WordPress sites.</p></div>
  <a class="btn" href="/admin/products">Manage products</a>
</div>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<div class="updater-info card"><span class="updater-info-icon">↻</span><div><strong>How updates are delivered</strong><p>Upload a validated ZIP in a plugin’s release page, then publish it to offer that version. Connected sites see the release when they check for updates, provided their account has access.</p></div></div>
<?php if ($plugins === []): ?>
  <div class="card updater-empty"><h2>No updater plugins registered</h2><p class="muted">Create a plugin listing first, then manage its versions here.</p><a class="btn btn-primary" href="/admin/products/new">Add plugin</a></div>
<?php else: ?>
  <div class="updater-plugin-list">
  <?php foreach ($plugins as $plugin): ?>
    <article class="card updater-plugin-card">
      <div class="updater-plugin-main">
        <div class="updater-plugin-icon">⌘</div>
        <div class="updater-plugin-name"><h2><?= e($plugin['name']) ?></h2><code><?= e($plugin['basename']) ?></code><span class="muted">Slug: <?= e($plugin['slug']) ?></span></div>
        <div class="updater-release"><small>LIVE RELEASE</small><strong><?= e($plugin['current_release']['version'] ?? 'None published') ?></strong><?php if (!empty($plugin['current_release']['published_at'])): ?><span><?= e(substr((string)$plugin['current_release']['published_at'], 0, 10)) ?></span><?php endif; ?></div>
        <div class="updater-release"><small>UPLOAD WAITING</small><strong><?= e($plugin['draft_release']['version'] ?? '—') ?></strong><span><?= $plugin['draft_release'] ? 'Draft ready to publish' : 'No pending draft' ?></span></div>
        <div class="updater-status"><span class="badge badge-<?= (int)$plugin['enabled'] === 1 ? 'active' : 'revoked' ?>"><?= (int)$plugin['enabled'] === 1 ? 'Distribution on' : 'Distribution off' ?></span><span class="badge badge-<?= (int)$plugin['auto_update_allowed'] === 1 ? 'active' : 'revoked' ?>"><?= (int)$plugin['auto_update_allowed'] === 1 ? 'Auto updates allowed' : 'Auto updates blocked' ?></span></div>
      </div>
      <div class="updater-plugin-actions">
        <span class="muted"><?= count($plugin['versions']) ?> release<?= count($plugin['versions']) === 1 ? '' : 's' ?></span>
        <form method="post" action="/admin/plugins/<?= (int)$plugin['id'] ?>/auto-updates"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm" type="submit"><?= (int)$plugin['auto_update_allowed'] === 1 ? 'Block auto updates' : 'Allow auto updates' ?></button></form>
        <a class="btn btn-sm btn-primary" href="/admin/plugins/<?= (int)$plugin['id'] ?>"><?= $plugin['draft_release'] ? 'Review &amp; publish draft' : 'Manage releases' ?></a>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
