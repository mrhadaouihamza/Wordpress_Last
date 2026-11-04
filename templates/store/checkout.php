<?php
use function Wordpresso\Core\e;

$status = (string) $order['status'];
$introKicker = 'SECURE CRYPTO CHECKOUT';
$introText = 'Send the exact amount in USDT on BNB Smart Chain (BEP20), then submit your transaction hash for confirmation.';
if ($status === 'paid') {
    $introKicker = 'PAYMENT CONFIRMED';
    $introText = 'Your payment was verified and your purchase access is active.';
} elseif ($status === 'rejected') {
    $introKicker = 'ORDER CLOSED';
    $introText = 'This order was rejected by the store administrator and cannot be paid or activated.';
} elseif ($status === 'cancelled') {
    $introKicker = 'ORDER CANCELLED';
    $introText = 'This order was cancelled and will not be processed.';
}
?>
<div class="page-intro"><span class="section-kicker"><?= $introKicker ?></span><h1>Order <?= e($order['order_code']) ?></h1><p><?= e($introText) ?></p></div>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?php if ($order['status'] === 'paid'): ?>
  <div class="payment-success"><span>✓</span><h2>Payment confirmed</h2><p>Your purchase access has been activated. <a href="/account">View your account and subscriptions →</a></p><?php if (!empty($order['tx_hash'])): ?><p class="muted">Transaction: <code><?= e($order['tx_hash']) ?></code></p><?php else: ?><p class="muted">This order was confirmed by the store administrator.</p><?php endif; ?>
  <?php foreach ($order['items'] as $purchased): ?><?php if (!empty($purchased['package_path'])): $dlType = \Wordpresso\Services\StoreCatalog::typeFromCategory((string) $purchased['category']); ?><?php if ($dlType !== null): ?><p><a class="btn" href="/account/orders/<?= rawurlencode($order['order_code']) ?>/download/<?= e($dlType) ?>/<?= (int)$purchased['product_id'] ?>">Download <?= e($purchased['product_name']) ?></a></p><?php endif; ?><?php endif; ?><?php endforeach; ?>
  <?php foreach (($order['included_products']??[]) as $included): ?><p><a class="btn" href="/account/orders/<?= rawurlencode($order['order_code']) ?>/download/<?= e($included['type']) ?>/<?= (int)$included['id'] ?>">Download <?= e($included['name']) ?></a></p><?php endforeach; ?></div>
<?php elseif ($order['status'] === 'rejected'): ?>
  <div class="alert alert-error"><h2>Order rejected</h2><p>This order was rejected by the store administrator and cannot be paid or activated. If you believe this is a mistake, contact store support.</p><a class="btn" href="/store">Return to store</a></div>
<?php elseif ($order['status'] === 'cancelled'): ?>
  <div class="order-cancelled-state">
    <span class="order-cancelled-icon" aria-hidden="true">✕</span>
    <h2>Order cancelled</h2>
    <p>No payment is expected for this order. Your cart still contains the items, so you can place a new order whenever you are ready.</p>
    <div class="order-cancelled-actions">
      <a class="btn" href="/cart">Return to cart</a>
      <a class="btn btn-primary" href="/store">Browse the store</a>
    </div>
  </div>
<?php else: ?>
  <div class="checkout-layout"><section class="payment-card"><div class="payment-total"><span>Amount due</span><strong><?= number_format((float)$order['amount'],2) ?> <small>USDT</small></strong><span>BNB Smart Chain · BEP20</span></div>
  <?php if ($wallet === ''): ?><div class="alert alert-error">Checkout is not configured yet. The store administrator needs to set a receiving wallet in Admin → Payments.</div><?php else: ?>
    <div class="payment-address"><label>Send USDT to this wallet</label><code><?= e($wallet) ?></code></div><div class="payment-address"><label>USDT token contract</label><code><?= e($contract) ?></code></div><p class="payment-warning">Only send USDT using the BEP20 network. Transfers on another network cannot be verified here.</p>
    <form method="post" action="/checkout/<?= rawurlencode($order['order_code']) ?>/verify" class="form verify-form"><?= \Wordpresso\Core\Csrf::field() ?><label>Transaction hash (TXID)<input type="text" name="tx_hash" required minlength="66" maxlength="66" pattern="0x[a-fA-F0-9]{64}" placeholder="0x…" autocomplete="off"></label><button type="submit" class="btn btn-primary">Verify payment</button></form>
  <?php endif; ?></section><aside class="cart-summary"><span class="section-kicker">ORDER ITEMS</span><?php foreach($order['items'] as $item): ?><div class="checkout-item"><span><?= e($item['product_name']) ?> × <?= (int)$item['quantity'] ?></span><strong>$<?= number_format((float)$item['unit_price']*(int)$item['quantity'],2) ?></strong></div><?php endforeach; ?><div class="summary-total"><span>Total</span><strong>$<?= number_format((float)$order['amount'],2) ?> USDT</strong></div><p>Order expires <?= e($order['payment_expires_at']) ?> UTC.</p>
  <form method="post" action="/checkout/<?= rawurlencode($order['order_code']) ?>/cancel" class="order-cancel-form" onsubmit="return confirm('Cancel this order? Your cart will keep the items so you can order again.');">
    <?= \Wordpresso\Core\Csrf::field() ?>
    <button class="btn btn-danger" type="submit">Cancel order</button>
  </form>
  <p class="sidebar-note">Changed your mind? Cancelling keeps the items in your cart.</p></aside></div>
<?php endif; ?>
