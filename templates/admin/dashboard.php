<?php use function Wordpresso\Core\e; ?>
<h1>Dashboard</h1>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>

<div class="stat-grid">
  <div class="card stat"><span class="stat-num"><?= (int) $stats['plugins'] ?></span>Plugins</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['themes'] ?></span>Themes</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['published_versions'] ?></span>Published versions</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['customers'] ?></span>Customers</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['active_subscriptions'] ?></span>Active subscriptions</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['active_websites'] ?>/<?= (int) $stats['websites'] ?></span>Websites active</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['checks_24h'] ?></span>Checks (24h)</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['downloads_24h'] ?></span>Downloads (24h)</div>
  <div class="card stat <?= $stats['failures_24h'] > 0 ? 'stat-alert' : '' ?>"><span class="stat-num"><?= (int) $stats['failures_24h'] ?></span>Failures (24h)</div>
</div>

<h2>Sites that stopped checking</h2>
<?php if ($staleSites === []): ?>
  <p class="muted">All connected sites checked in recently. 🎉</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Site</th><th>Customer</th><th>Last check</th><th>Connected</th></tr></thead>
    <tbody>
    <?php foreach ($staleSites as $site): ?>
      <tr>
        <td><?= e($site['url']) ?> <?php if ($site['last_check_at'] === null): ?><span class="badge badge-failure">never</span><?php endif; ?></td>
        <td><?= e($site['customer_email']) ?></td>
        <td><?= e($site['last_check_at'] ?? '—') ?></td>
        <td><?= e(substr((string) $site['connected_at'], 0, 10)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2>Recent failures</h2>
<?php if ($recentFailures === []): ?>
  <p class="muted">No failures logged recently.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>When</th><th>Action</th><th>Site</th><th>Message</th></tr></thead>
    <tbody>
    <?php foreach ($recentFailures as $row): ?>
      <tr>
        <td><?= e($row['created_at']) ?></td>
        <td><span class="badge badge-failure"><?= e($row['action']) ?></span></td>
        <td><?= e($row['website_url'] ?? '—') ?></td>
        <td><?= e($row['message']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p><a href="/admin/logs?outcome=failure">All failures in the log →</a></p>
<?php endif; ?>
