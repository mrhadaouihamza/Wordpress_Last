<?php
use function Wordpresso\Core\e;
$creds = null;
if (!empty($newCredentials)) {
    $decoded = json_decode((string) $newCredentials, true);
    if (is_array($decoded)) {
        $creds = $decoded;
    }
}
?>
<h1>Websites</h1>
<p class="account-intro">Connected websites: <?= count($websites) ?> · Website access follows your active plan or product entitlement. Each site gets API credentials for the <em>Wordpresso Auto Updater</em> plugin.</p>

<?php if ($creds !== null): ?>
  <div class="alert alert-credentials">
    <h3>Copy these credentials now — the secret is shown only once</h3>
    <p>Paste them into the <em>Wordpresso Auto Updater</em> plugin settings on your WordPress site.</p>
    <dl class="credentials">
      <dt>Site URL</dt><dd><code><?= e($creds['url']) ?></code></dd>
      <dt>API key</dt><dd><code><?= e($creds['api_key']) ?></code></dd>
      <dt>API secret</dt><dd><code class="secret"><?= e($creds['api_secret']) ?></code></dd>
    </dl>
  </div>
<?php endif; ?>

<?php if ($websites === []): ?>
  <p class="muted">No websites registered yet. Add one below to get your API credentials.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Name</th><th>URL</th><th>Status</th><th>Last check</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($websites as $site): ?>
      <tr>
        <td><?= e($site['name']) ?></td>
        <td><?= e($site['url']) ?></td>
        <td><span class="badge badge-<?= e($site['status']) ?>"><?= e($site['status']) ?></span></td>
        <td><?= e($site['last_check_at'] ?? 'never') ?></td>
        <td class="actions">
          <form method="post" action="/account/websites/<?= (int) $site['id'] ?>/rotate" class="inline-form">
            <?= \Wordpresso\Core\Csrf::field() ?>
            <button class="btn btn-sm" onclick="return confirm('Rotate the API secret? The plugin on the site must be updated with the new secret.')">Rotate secret</button>
          </form>
          <form method="post" action="/account/websites/<?= (int) $site['id'] ?>/delete" class="inline-form">
            <?= \Wordpresso\Core\Csrf::field() ?>
            <button class="btn btn-sm btn-danger" onclick="return confirm('Permanently remove this website and delete its API key and encrypted secret?')">Remove website</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2>Register a website</h2>
<?php if (!$canAdd): ?><p class="muted">You need an active plan, plugin, or theme with available website access before adding a website.</p><?php endif; ?>
<form method="post" action="/account/websites" class="card form">
  <?= \Wordpresso\Core\Csrf::field() ?>
  <label>Name (optional)
    <input type="text" name="name" placeholder="Client site">
  </label>
  <label>Site URL
    <input type="url" name="url" required placeholder="https://example.com">
  </label>
  <button type="submit" class="btn btn-primary" <?= $canAdd ? '' : 'disabled' ?>>Generate credentials</button>
</form>
