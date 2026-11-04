<?php
use function Wordpresso\Core\e;
?>
<div class="page-intro"><span class="section-kicker">WORDPRESS EXTENSIONS</span><h1>Plugin catalog</h1><p>Explore published plugins, review release details, and connect eligible updates to your WordPress websites.</p></div>
<div class="plugin-grid">
<?php foreach ($catalog as $item): ?>
  <article class="card plugin-card">
    <h3><a href="/plugins/<?= e($item['slug']) ?>"><?= e($item['name']) ?></a></h3>
    <p class="muted">
      <?php if ($item['current_version'] !== null): ?>
        Version <?= e($item['current_version']) ?>
        <?php if ($item['current_published_at'] !== null): ?>
          — released <?= e(substr((string) $item['current_published_at'], 0, 10)) ?>
        <?php endif; ?>
      <?php else: ?>
        Coming soon
      <?php endif; ?>
    </p>
    <p><?= e($item['description']) ?></p>
  </article>
<?php endforeach; ?>
<?php if ($catalog === []): ?>
  <p class="muted">No plugins published yet.</p>
<?php endif; ?>
</div>
