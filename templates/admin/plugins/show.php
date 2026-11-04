<?php use function Wordpresso\Core\e; ?>
<div class="page-head">
  <h1><?= e($plugin['name']) ?></h1>
  <div class="actions">
    <a class="btn" href="/admin/products/plugin/<?= (int) $plugin['id'] ?>">Edit</a>
    <form method="post" action="/admin/plugins/<?= (int) $plugin['id'] ?>/toggle" class="inline-form">
      <?= \Wordpresso\Core\Csrf::field() ?>
      <button class="btn"><?= (int) $plugin['enabled'] === 1 ? 'Disable distribution' : 'Enable distribution' ?></button>
    </form>
  </div>
</div>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<div class="card">
  <dl class="detail">
    <dt>Slug</dt><dd><code><?= e($plugin['slug']) ?></code></dd>
    <dt>Technical identifier</dt><dd><code><?= e($plugin['basename']) ?></code></dd>
    <dt>Distribution</dt><dd><?= (int) $plugin['enabled'] === 1 ? 'enabled' : 'disabled' ?></dd>
    <dt>Auto-updates</dt><dd><?= (int) $plugin['auto_update_allowed'] === 1 ? 'allowed' : 'blocked' ?></dd>
    <dt>Requirements</dt>
    <dd>
      WP <?= e($plugin['min_wp_version'] ?: 'any') ?> ·
      PHP <?= e($plugin['min_php_version'] ?: 'any') ?> ·
      tested to WP <?= e($plugin['tested_wp_version'] ?: '?') ?>
    </dd>
  </dl>
</div>

<h2>Upload a new version</h2>
<form method="post" action="/admin/plugins/<?= (int) $plugin['id'] ?>/versions" enctype="multipart/form-data" class="card form">
  <?= \Wordpresso\Core\Csrf::field() ?>
  <label>ZIP package
    <input type="file" name="package" accept=".zip" required>
    <small>Validated on upload: must contain <code><?= e($plugin['basename']) ?></code> with a matching <code>Version:</code> header. Stored in protected storage as a draft.</small>
  </label>
  <div class="form-row">
    <label>Version number
      <input type="text" name="version" required pattern="\d+(\.\d+)*([.-][A-Za-z0-9]+)?" placeholder="1.2.3">
    </label>
    <label>Min WP (fallback to plugin default if empty)
      <input type="text" name="min_wp_version" placeholder="<?= e($plugin['min_wp_version']) ?>">
    </label>
    <label>Min PHP
      <input type="text" name="min_php_version" placeholder="<?= e($plugin['min_php_version']) ?>">
    </label>
    <label>Tested up to WP
      <input type="text" name="tested_wp_version" placeholder="<?= e($plugin['tested_wp_version']) ?>">
    </label>
  </div>
  <label>Changelog for this version
    <textarea name="changelog" rows="5" placeholder="= 1.2.3 =&#10;* Fixed ...&#10;* Added ..."></textarea>
  </label>
  <button type="submit" class="btn btn-primary">Upload &amp; validate</button>
</form>

<h2>Versions</h2>
<?php if ($versions === []): ?>
  <p class="muted">No versions uploaded yet.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Version</th><th>Status</th><th>Size</th><th>SHA-256</th><th>Uploaded</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $version): ?>
      <tr class="<?= (int) $version['is_current'] === 1 ? 'row-current' : '' ?>">
        <td>
          <strong><?= e($version['version']) ?></strong>
          <?php if ((int) $version['is_current'] === 1): ?>
            <span class="badge badge-active">current</span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?= e($version['status']) ?>"><?= e($version['status']) ?></span></td>
        <td><?= number_format((int) $version['package_size'] / 1048576, 2) ?> MB</td>
        <td><code class="sha" title="<?= e($version['package_sha256']) ?>"><?= e(substr((string) $version['package_sha256'], 0, 12)) ?>…</code></td>
        <td><?= e(substr((string) $version['created_at'], 0, 16)) ?></td>
        <td class="actions">
          <?php if ($version['status'] !== 'published' || (int) $version['is_current'] !== 1): ?>
            <form method="post" action="/admin/versions/<?= (int) $version['id'] ?>/publish" class="inline-form">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <button class="btn btn-sm btn-primary" onclick="return confirm('Publish version <?= e($version['version']) ?>? Connected sites will receive it as an update.')">Publish</button>
            </form>
          <?php endif; ?>
          <?php if ((int) $version['is_current'] === 1): ?>
            <form method="post" action="/admin/versions/<?= (int) $version['id'] ?>/withdraw" class="inline-form">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <button class="btn btn-sm btn-danger" onclick="return confirm('Withdraw version <?= e($version['version']) ?>? No version will be served until you publish one.')">Withdraw</button>
            </form>
          <?php elseif ($version['status'] === 'published'): ?>
            <form method="post" action="/admin/versions/<?= (int) $version['id'] ?>/rollback" class="inline-form">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <button class="btn btn-sm" onclick="return confirm('Roll back to version <?= e($version['version']) ?>?')">Roll back</button>
            </form>
          <?php endif; ?>
          <?php if ((int) $version['is_current'] !== 1): ?>
            <form method="post" action="/admin/versions/<?= (int) $version['id'] ?>/delete" class="inline-form">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this release and its package?')">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <td colspan="6" class="changelog-cell">
          <details>
            <summary>Changelog &amp; edit</summary>
            <form method="post" action="/admin/versions/<?= (int) $version['id'] ?>/changelog" class="form">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <textarea name="changelog" rows="4"><?= e($version['changelog']) ?></textarea>
              <button class="btn btn-sm">Save changelog</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
