<?php use function Wordpresso\Core\e; ?>
<h1>Dashboard</h1>
<p class="account-intro">
  Signed in as <strong><?= e($customer['email']) ?></strong><?php if ((string) $customer['name'] !== ''): ?> — <?= e($customer['name']) ?><?php endif; ?>
  · Member since <?= e(date('M Y', strtotime((string) $customer['created_at']))) ?>
</p>

<div class="stat-grid">
  <div class="card stat"><span class="stat-num"><?= (int) $stats['active_subscriptions'] ?></span>Active subscriptions</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['websites'] ?></span>Connected websites</div>
  <div class="card stat"><span class="stat-num"><?= (int) $stats['orders'] ?></span>Product orders</div>
  <div class="card stat <?= $stats['pending_orders'] > 0 ? 'stat-alert' : '' ?>"><span class="stat-num"><?= (int) $stats['pending_orders'] ?></span>Pending payments</div>
  <div class="card stat"><span class="stat-num">$<?= number_format((float) $stats['spent'], 2) ?></span>Total spent</div>
</div>

<?php if ((int) $stats['pending_plans'] > 0): ?>
  <p class="alert alert-warn">
    You have <?= (int) $stats['pending_plans'] ?> plan purchase<?= (int) $stats['pending_plans'] === 1 ? '' : 's' ?> awaiting payment.
    <a href="/account/subscriptions">Complete payment →</a>
  </p>
<?php endif; ?>

<h2>Latest product orders</h2>
<?php if ($recentOrders === []): ?>
  <p class="muted">No product orders yet — <a href="/store">browse the store</a> to place your first order.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Order</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recentOrders as $order): ?>
      <tr>
        <td><code><?= e($order['order_code']) ?></code></td>
        <td><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
        <td>$<?= number_format((float) $order['amount'], 2) ?> <?= e($order['currency']) ?></td>
        <td><span class="badge badge-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
        <td><a href="/account/orders">View →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p><a href="/account/orders">All product orders →</a></p>
<?php endif; ?>

<h2>Quick actions</h2>
<div class="account-panel-grid">
  <a class="account-quick" href="/account/websites">
    <strong>Connect a website</strong>
    <span>Generate API credentials for the Wordpresso Auto Updater plugin.</span>
  </a>
  <a class="account-quick" href="/account/subscriptions">
    <strong>Your subscriptions</strong>
    <span>Review plans, included products and expiry dates.</span>
  </a>
  <a class="account-quick" href="/account/settings">
    <strong>Account settings</strong>
    <span>Update your profile, change your password or delete the account.</span>
  </a>
  <a class="account-quick" href="/store">
    <strong>Browse the store</strong>
    <span>Plugins, themes and subscription plans in one catalog.</span>
  </a>
</div>
