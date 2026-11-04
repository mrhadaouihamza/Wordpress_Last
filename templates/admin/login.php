<?php use function Wordpresso\Core\e; ?>
<div class="narrow">
<h1>Admin sign in</h1>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="/admin/login" class="card form">
  <?= \Wordpresso\Core\Csrf::field() ?>
  <label>Email
    <input type="email" name="email" required autofocus autocomplete="username">
  </label>
  <label>Password
    <input type="password" name="password" required autocomplete="current-password">
  </label>
  <button type="submit" class="btn btn-primary">Sign in</button>
</form>
</div>
