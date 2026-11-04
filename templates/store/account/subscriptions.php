<?php use function Wordpresso\Core\e; ?>
<h1>Subscriptions</h1>
<p class="account-intro">Plan purchases and the access they grant appear here. Product purchases are listed under <a href="/account/orders">Orders</a>.</p>

<h2>Plan purchases</h2>
<?php if ($planOrders === []): ?>
  <p class="muted">No plan purchases yet. <a href="/plans">See available plans →</a></p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Order</th><th>Plan</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($planOrders as $planOrder): ?>
      <tr>
        <td><code><?= e($planOrder['order_code']) ?></code></td>
        <td><?= e($planOrder['plan_name'] !== '' ? $planOrder['plan_name'] : 'Subscription plan') ?></td>
        <td><?= e(date('M j, Y', strtotime($planOrder['created_at']))) ?></td>
        <td>$<?= number_format((float) $planOrder['amount'], 2) ?> <?= e($planOrder['currency']) ?></td>
        <td><span class="badge badge-<?= e($planOrder['status']) ?>"><?= e(ucfirst($planOrder['status'])) ?></span></td>
        <td class="actions">
          <?php if ($planOrder['status'] === 'pending'): ?>
            <a class="btn btn-sm btn-primary" href="/checkout/<?= rawurlencode($planOrder['order_code']) ?>">Complete payment →</a>
            <form method="post" action="/account/orders/<?= rawurlencode($planOrder['order_code']) ?>/cancel" class="inline-form" onsubmit="return confirm('Cancel this plan order? Your cart will keep the plan so you can order again.');">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <button class="btn btn-sm btn-danger" type="submit">Cancel</button>
            </form>
          <?php else: ?>
            <a class="btn btn-sm" href="/checkout/<?= rawurlencode($planOrder['order_code']) ?>"><?= $planOrder['status'] === 'cancelled' ? 'View order' : 'View receipt' ?></a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2>Your subscriptions</h2>
<?php if ($subscriptions === []): ?>
  <p class="muted">No subscriptions yet — they appear here after a plan purchase is confirmed by the store.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Plan</th><th>Status</th><th>Started</th><th>Expires</th><th>Updates</th></tr></thead>
    <tbody>
    <?php foreach ($subscriptions as $sub): ?>
      <tr>
        <td>
          <?= e($sub['display_name'] !== '' ? $sub['display_name'] : 'Subscription #' . $sub['id']) ?>
          <?php if ($sub['order'] !== null): ?>
            <span class="sub-order-note"><?= e($sub['order']['order_code']) ?> · $<?= number_format((float) $sub['order']['amount'], 2) ?> <?= e($sub['order']['currency']) ?></span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?= e($sub['status']) ?>"><?= e($sub['status']) ?></span></td>
        <td><?= e(substr((string) $sub['starts_at'], 0, 10)) ?></td>
        <td><?= e($sub['expires_at'] !== null ? substr((string) $sub['expires_at'], 0, 10) : 'never') ?></td>
        <td><?= (int) $sub['updates_included'] === 1 ? 'included' : 'not included' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
