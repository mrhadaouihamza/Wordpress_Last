<?php
use function Wordpresso\Core\e;
?>
<div class="narrow">
<h1>Sign in</h1>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="/login" class="card form">
  <?= \Wordpresso\Core\Csrf::field() ?>
  <label>Email
    <input type="email" name="email" required autofocus autocomplete="username">
  </label>
  <label>Password
    <input type="password" name="password" required autocomplete="current-password">
  </label>
  <button type="submit" class="btn btn-primary">Sign in</button>
  <p class="muted">No account yet? <a href="/register">Create one</a>.</p>
</form>
</div>
