<?php use function Wordpresso\Core\e; ?>
<div class="page-head"><div><span class="section-kicker">STORE OPERATIONS</span><h1>Store orders</h1><p class="muted">Review pending payments and manage order access.</p></div></div>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<table class="table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Transaction</th><th>Created</th><th>Review</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?>
  <tr>
    <td><strong><?= e($order['order_code']) ?></strong></td>
    <td><a href="/admin/customers/<?= (int)$order['customer_id'] ?>"><?= e($order['email']) ?></a></td>
    <td><?= number_format((float)$order['amount'],2) ?> <?= e($order['currency']) ?></td>
    <td><span class="badge badge-<?= e($order['status']) ?>"><?= e($order['status']) ?></span></td>
    <td class="ctx"><?= e($order['tx_hash']??'—') ?></td>
    <td><?= e($order['created_at']) ?></td>
    <td>
      <?php if ($order['status'] === 'pending'): ?>
        <form method="post" action="/admin/orders/<?= (int)$order['id'] ?>/confirm" class="order-review-form">
          <?= \Wordpresso\Core\Csrf::field() ?>
          <input type="text" name="tx_hash" placeholder="Optional transaction hash" aria-label="Optional transaction hash">
          <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('Confirm this payment and activate the order access? Verify the payment before approving.')">Confirm &amp; activate</button>
        </form>
        <form method="post" action="/admin/orders/<?= (int)$order['id'] ?>/reject" class="inline-form">
          <?= \Wordpresso\Core\Csrf::field() ?>
          <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Reject this pending order? It cannot be paid or activated afterward.')">Reject</button>
        </form>
      <?php endif; ?>
      <form method="post" action="/admin/orders/<?= (int)$order['id'] ?>/delete" class="inline-form">
        <?= \Wordpresso\Core\Csrf::field() ?>
        <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this order record? Existing paid access is not revoked.')">Delete</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
<?php if ($orders===[]): ?><tr><td colspan="7" class="muted">No orders have been placed.</td></tr><?php endif; ?>
</tbody></table>
