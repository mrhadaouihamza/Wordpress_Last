<?php use function Wordpresso\Core\e; ?>
<div class="page-head">
  <h1>Plugins</h1>
  <a class="btn btn-primary" href="/admin/plugins/new">Add plugin</a>
</div>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<?php if ($plugins === []): ?>
  <p class="muted">No plugins in the catalog yet. Add your first plugin, then upload a ZIP package for it.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr><th>Name</th><th>Slug</th><th>Technical identifier</th><th>Current version</th><th>Versions</th><th>Distribution</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($plugins as $plugin): ?>
      <tr>
        <td><a href="/admin/plugins/<?= (int) $plugin['id'] ?>"><strong><?= e($plugin['name']) ?></strong></a></td>
        <td><code><?= e($plugin['slug']) ?></code></td>
        <td><code><?= e($plugin['basename']) ?></code></td>
        <td><?= e($plugin['current_version'] ?? '—') ?></td>
        <td><?= (int) $plugin['version_count'] ?></td>
        <td>
          <span class="badge badge-<?= (int) $plugin['enabled'] === 1 ? 'active' : 'revoked' ?>">
            <?= (int) $plugin['enabled'] === 1 ? 'enabled' : 'disabled' ?>
          </span>
          <?php if ((int) $plugin['auto_update_allowed'] === 1): ?>
            <span class="badge badge-active">auto-updates</span>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a class="btn btn-sm" href="/admin/products/plugin/<?= (int)$plugin['id'] ?>">Edit</a>
          <form method="post" action="/admin/plugins/<?= (int) $plugin['id'] ?>/toggle" class="inline-form">
            <?= \Wordpresso\Core\Csrf::field() ?>
            <button class="btn btn-sm"><?= (int) $plugin['enabled'] === 1 ? 'Disable' : 'Enable' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
