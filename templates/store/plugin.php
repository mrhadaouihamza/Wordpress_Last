<?php
use function Wordpresso\Core\e;
?>
<article class="product-detail">
  <a class="back-link" href="/store">← All plugins &amp; themes</a>
  <div class="product-detail-head"><div class="plugin-symbol large" aria-hidden="true">✦</div><div>
  <span class="section-kicker">WORDPRESS PLUGIN</span>
  <h1><?= e($plugin['name']) ?></h1>
  <?php if ($current !== null): ?>
    <p class="muted">Current version: <strong><?= e($current['version']) ?></strong>
      <?php if ($current['published_at'] !== null): ?>
        — published <?= e(substr((string) $current['published_at'], 0, 10)) ?>
      <?php endif; ?>
    </p>
  <?php else: ?>
    <p class="muted">This plugin has not been published yet.</p>
  <?php endif; ?>

  </div></div>
  <div class="product-detail-content"><div class="product-description"><h2>About this plugin</h2><p><?= nl2br(e($plugin['description'])) ?></p>

  <h2>Requirements</h2>
  <ul>
    <?php if ($plugin['min_wp_version'] !== ''): ?>
      <li>WordPress <?= e($plugin['min_wp_version']) ?> or newer</li>
    <?php endif; ?>
    <?php if ($plugin['min_php_version'] !== ''): ?>
      <li>PHP <?= e($plugin['min_php_version']) ?> or newer</li>
    <?php endif; ?>
    <?php if ($plugin['tested_wp_version'] !== ''): ?>
      <li>Tested up to WordPress <?= e($plugin['tested_wp_version']) ?></li>
    <?php endif; ?>
  </ul>

  <?php $changelog = $current !== null && $current['changelog'] !== '' ? $current['changelog'] : $plugin['changelog']; ?>
  <?php if ($changelog !== ''): ?>
    <h2>Changelog</h2>
    <pre class="changelog"><?= e($changelog) ?></pre>
  <?php endif; ?>
  </div><aside class="product-sidebar"><h3>Release information</h3><dl class="release-details"><dt>Latest version</dt><dd><?= $current !== null ? e($current['version']) : 'Not published' ?></dd><?php if ($current !== null && $current['published_at'] !== null): ?><dt>Released</dt><dd><?= e(substr((string) $current['published_at'], 0, 10)) ?></dd><?php endif; ?></dl>

  <p>
    <?php if ($customer === null): ?>
      <a class="btn btn-primary" href="/register">Get updates for this plugin</a>
    <?php else: ?>
      <a class="btn btn-primary" href="/account">Connect a website</a>
    <?php endif; ?>
  </p>
  <p class="sidebar-note">Updates are delivered through the Wordpresso Auto Updater plugin to connected WordPress websites with eligible access.</p></aside></div>
</article>
