<?php use function Wordpresso\Core\e; ?>
<h1>Websites</h1>
<p class="muted">All website connections across all customers.</p>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>

<table class="table">
  <thead>
    <tr><th>Site</th><th>Customer</th><th>Status</th><th>WP / PHP</th><th>Last check</th><th>Checks</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($websites as $site): ?>
    <tr class="<?= !empty($site['is_stale']) ? 'row-stale' : '' ?>">
      <td>
        <strong><?= e($site['name']) ?></strong><br>
        <span class="muted"><?= e($site['url']) ?></span>
        <?php if (!empty($site['is_stale'])): ?><span class="badge badge-failure">stale</span><?php endif; ?>
      </td>
      <td>
        <a href="/admin/customers/<?= (int) $site['customer_id'] ?>"><?= e($site['customer_email']) ?></a>
        <?php if ($site['customer_status'] !== 'active'): ?>
          <span class="badge badge-revoked"><?= e($site['customer_status']) ?></span>
        <?php endif; ?>
      </td>
      <td><span class="badge badge-<?= e($site['status']) ?>"><?= e($site['status']) ?></span></td>
      <td><?= e($site['wp_version'] ?: '—') ?> / <?= e($site['php_version'] ?: '—') ?></td>
      <td><?= e($site['last_check_at'] ?? 'never') ?></td>
      <td><a href="/admin/logs?website_id=<?= (int) $site['id'] ?>"><?= (int) $site['plugin_count'] ?></a></td>
      <td class="actions">
        <?php if ($site['status'] === 'active'): ?>
          <form method="post" action="/admin/websites/<?= (int) $site['id'] ?>/revoke" class="inline-form">
            <?= \Wordpresso\Core\Csrf::field() ?>
            <button class="btn btn-sm btn-danger" onclick="return confirm('Revoke this connection? The site stops receiving updates immediately.')">Revoke</button>
          </form>
        <?php else: ?>
          <form method="post" action="/admin/websites/<?= (int) $site['id'] ?>/activate" class="inline-form">
            <?= \Wordpresso\Core\Csrf::field() ?>
            <button class="btn btn-sm">Reactivate</button>
          </form>
        <?php endif; ?>
        <form method="post" action="/admin/websites/<?= (int)$site['id'] ?>/delete" class="inline-form">
          <?= \Wordpresso\Core\Csrf::field() ?>
          <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Permanently remove this website and delete its API key and encrypted secret?')">Remove</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if ($websites === []): ?>
    <tr><td colspan="7" class="muted">No websites registered yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
