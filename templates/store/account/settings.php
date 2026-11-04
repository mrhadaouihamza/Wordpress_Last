<?php use function Wordpresso\Core\e; ?>
<h1>Settings</h1>
<p class="account-intro">Manage your profile, password and account.</p>

<div class="settings-stack">
  <form method="post" action="/account/settings/profile" class="card form settings-card">
    <h2>Profile</h2>
    <p>Your name and email are used for order receipts and store communication.</p>
    <?= \Wordpresso\Core\Csrf::field() ?>
    <label>Name
      <input type="text" name="name" value="<?= e($customer['name']) ?>" placeholder="Your name">
    </label>
    <label>Email address
      <input type="email" name="email" value="<?= e($customer['email']) ?>" required autocomplete="email">
    </label>
    <button type="submit" class="btn btn-primary">Save profile</button>
  </form>

  <form method="post" action="/account/settings/password" class="card form settings-card">
    <h2>Change password</h2>
    <p>Choose a new password of at least 10 characters.</p>
    <?= \Wordpresso\Core\Csrf::field() ?>
    <label>Current password
      <input type="password" name="current_password" required autocomplete="current-password">
    </label>
    <label>New password
      <input type="password" name="new_password" required minlength="10" autocomplete="new-password">
    </label>
    <button type="submit" class="btn btn-primary">Change password</button>
  </form>

  <form method="post" action="/account/delete" class="card form settings-card danger-card"
        onsubmit="return confirm('Permanently delete your account? This cannot be undone.')">
    <h2>Delete account</h2>
    <p>Deleting your account permanently removes:</p>
    <ul class="danger-list">
      <li><?= (int) $websiteCount ?> connected website<?= (int) $websiteCount === 1 ? '' : 's' ?> and their API credentials</li>
      <li><?= (int) $subscriptionCount ?> subscription<?= (int) $subscriptionCount === 1 ? '' : 's' ?> and all product access</li>
      <li><?= (int) $orderCount ?> order<?= (int) $orderCount === 1 ? '' : 's' ?> and your download history</li>
    </ul>
    <p>This action is immediate and cannot be undone. Purchased access cannot be restored.</p>
    <?= \Wordpresso\Core\Csrf::field() ?>
    <label>Confirm your password
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <label class="checkbox">
      <input type="checkbox" name="confirm" value="1" required>
      I understand this action is permanent and cannot be undone.
    </label>
    <button type="submit" class="btn btn-danger">Delete my account</button>
  </form>
</div>
