<?php use function Wordpresso\Core\e; ?>
<h1><?= $customer !== null ? 'Edit customer' : 'Add customer' ?></h1>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<form method="post" action="<?= $customer !== null ? '/admin/customers/' . (int) $customer['id'] : '/admin/customers' ?>" class="card form">
  <?= \Wordpresso\Core\Csrf::field() ?>
  <label>Email
    <input type="email" name="email" required value="<?= e($customer['email'] ?? '') ?>">
  </label>
  <label>Name
    <input type="text" name="name" value="<?= e($customer['name'] ?? '') ?>">
  </label>
  <?php if ($customer !== null): ?>
    <label>New password (leave empty to keep the current one)
      <input type="text" name="new_password" placeholder="min. 10 characters" autocomplete="off">
    </label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary"><?= $customer !== null ? 'Save' : 'Create' ?></button>
</form>
