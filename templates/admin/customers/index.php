<?php use function Wordpresso\Core\e; ?>
<div class="page-head">
  <h1>Customers</h1>
  <a class="btn btn-primary" href="/admin/customers/new">Add customer</a>
</div>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>

<table class="table">
  <thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Subscriptions</th><th>Websites</th><th>Registered</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($customers as $customer): ?>
    <tr>
      <td><a href="/admin/customers/<?= (int) $customer['id'] ?>"><strong><?= e($customer['email']) ?></strong></a></td>
      <td><?= e($customer['name']) ?></td>
      <td><span class="badge badge-<?= e($customer['status']) ?>"><?= e($customer['status']) ?></span></td>
      <td><?= (int) $customer['active_subscriptions'] ?></td>
      <td><?= (int) $customer['website_count'] ?></td>
      <td><?= e(substr((string) $customer['created_at'], 0, 10)) ?></td>
      <td class="actions"><a class="btn btn-sm" href="/admin/customers/<?= (int) $customer['id'] ?>/edit">Edit</a><form method="post" action="/admin/customers/<?= (int)$customer['id'] ?>/delete" class="inline-form"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this customer and all related data?')">Delete</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
