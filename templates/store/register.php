<?php
use function Wordpresso\Core\e;
?>
<div class="narrow">
<h1>Create your account</h1>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="/register" class="card form">
  <?= \Wordpresso\Core\Csrf::field() ?>
  <label>Your name
    <input type="text" name="name" autocomplete="name">
  </label>
  <label>Email
    <input type="email" name="email" required autocomplete="username">
  </label>
  <label>Password (min. 10 characters)
    <input type="password" name="password" required minlength="10" autocomplete="new-password">
  </label>
  <button type="submit" class="btn btn-primary">Create account</button>
  <p class="muted">Already registered? <a href="/login">Sign in</a>.</p>
</form>
</div>
