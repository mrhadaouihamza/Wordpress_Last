<?php use function Wordpresso\Core\e; ?>
<h1>Logs</h1>

<?php if (!empty($flash)): ?>
  <div class="alert alert-success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="margin-bottom: 1.5rem; padding: 1.1rem 1.35rem; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-xs);">
  <div>
    <strong style="font-size: 0.95rem; color: var(--ink);">Database Log Retention</strong>
    <p class="muted" style="margin: .15rem 0 0; font-size: .83rem;">Clean up old update checks, authorization records, and operational logs to keep your database fast and reduce disk usage.</p>
  </div>
  <form method="post" action="/admin/logs/prune" class="inline-form" onsubmit="return confirm('Are you sure you want to permanently delete these logs from the database?');" style="display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin: 0;">
    <?= \Wordpresso\Core\Csrf::field() ?>
    <label for="prune-days" class="muted" style="font-size: .85rem; font-weight: 600;">Delete logs:</label>
    <select name="days" id="prune-days" style="padding: .45rem .65rem; border: 1px solid var(--line-strong); border-radius: var(--radius-sm); font: inherit; font-size: .85rem; background: #fff;">
      <option value="7">Older than 7 days</option>
      <option value="14">Older than 14 days</option>
      <option value="30" selected>Older than 30 days</option>
      <option value="60">Older than 60 days</option>
      <option value="90">Older than 90 days</option>
      <option value="all">All logs (clear all)</option>
    </select>
    <button type="submit" class="btn btn-sm btn-danger">Prune old logs</button>
  </form>
</div>

<form method="get" action="/admin/logs" class="filter-bar">
  <input type="text" name="q" placeholder="Search message, site or email…" value="<?= e($filters['q']) ?>">
  <select name="action">
    <option value="">All actions</option>
    <?php foreach ($actions as $action): ?>
      <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= e($action) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="outcome">
    <option value="">Any outcome</option>
    <option value="success" <?= $filters['outcome'] === 'success' ? 'selected' : '' ?>>success</option>
    <option value="failure" <?= $filters['outcome'] === 'failure' ? 'selected' : '' ?>>failure</option>
  </select>
  <select name="website_id">
    <option value="">All websites</option>
    <?php foreach ($websites as $site): ?>
      <option value="<?= (int) $site['id'] ?>" <?= $filters['website_id'] === (string) $site['id'] ? 'selected' : '' ?>>
        <?= e($site['url']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary">Filter</button>
</form>

<p class="muted"><?= (int) $total ?> event(s)<?php if ($total > $perPage): ?>, page <?= (int) $page ?><?php endif; ?></p>

<table class="table table-logs">
  <thead><tr><th>When (UTC)</th><th>Action</th><th>Outcome</th><th>Website</th><th>Customer</th><th>Plugin</th><th>Message</th><th>Context</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr class="<?= $row['outcome'] === 'failure' ? 'row-failure' : '' ?>">
      <td class="nowrap"><?= e($row['created_at']) ?></td>
      <td><span class="badge badge-<?= e($row['outcome'] === 'failure' ? 'failure' : 'active') ?>"><?= e($row['action']) ?></span></td>
      <td><?= e($row['outcome']) ?></td>
      <td><?= e($row['website_url'] ?? '—') ?></td>
      <td><?= e($row['customer_email'] ?? '—') ?></td>
      <td><?= e($row['plugin_name'] ?? '—') ?></td>
      <td><?= e($row['message']) ?></td>
      <td><?php if ($row['context'] !== '{}' && $row['context'] !== ''): ?><code class="ctx"><?= e($row['context']) ?></code><?php else: ?>—<?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($rows === []): ?>
    <tr><td colspan="8" class="muted">No log entries match the filters.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php
$pages = (int) ceil($total / max(1, $perPage));
if ($pages > 1):
  $qs = $filters;
?>
<nav class="pagination">
  <?php for ($p = 1; $p <= $pages; $p++): ?>
    <?php $qs['page'] = $p; ?>
    <a class="<?= $p === $page ? 'active' : '' ?>" href="/admin/logs?<?= e(http_build_query($qs)) ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
