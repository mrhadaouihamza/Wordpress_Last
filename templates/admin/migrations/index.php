<?php use function Wordpresso\Core\e; ?>
<div class="page-head"><div><h1>Database migrations</h1><p class="muted">Review and apply database changes for this store installation.</p></div></div>
<?php if ($flash): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-danger"><?= e($error) ?></p><?php endif; ?>
<div class="card">
  <h2>Pending migrations</h2>
  <?php if ($pending): ?>
    <ul><?php foreach ($pending as $migration): ?><li><?= e($migration) ?></li><?php endforeach; ?></ul>
    <form method="post" action="/admin/migrations/run"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-primary" type="submit">Run migrations</button></form>
  <?php else: ?><p class="muted">The database is up to date.</p><?php endif; ?>
</div>
<div class="card">
  <h2>Applied migrations</h2>
  <?php if ($applied): ?><ul><?php foreach ($applied as $migration): ?><li><?= e($migration) ?></li><?php endforeach; ?></ul><?php else: ?><p class="muted">No migrations have been recorded yet.</p><?php endif; ?>
</div>
