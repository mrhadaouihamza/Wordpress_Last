<?php use function Wordpresso\Core\e; ?>
<h1>Orders</h1>
<p class="account-intro"><?php if (!empty($orders)): ?><?= count($orders) ?> product order<?= count($orders) === 1 ? '' : 's' ?> — payment status, receipts and downloads<?php else: ?>Product purchases appear here — subscriptions are listed under <a href="/account/subscriptions">Subscriptions</a><?php endif; ?></p>

<?php if (empty($orders)): ?>
  <div class="order-empty-state">
    <div class="order-empty-icon">🛍️</div>
    <h3>No product orders yet</h3>
    <p>Browse our catalog of premium WordPress plugins and themes to place your first order. Plan purchases appear under <a href="/account/subscriptions">Subscriptions</a>.</p>
    <a href="/store" class="btn btn-primary btn-sm">Explore catalog →</a>
  </div>
<?php else: ?>
  <div class="order-list">
    <?php foreach ($orders as $order): ?>
      <?php $statusKey = in_array($order['status'], ['paid', 'pending', 'rejected', 'cancelled'], true) ? $order['status'] : ''; ?>
      <article class="order-card<?= $statusKey !== '' ? ' order-card--' . $statusKey : '' ?>">
        <div class="order-card-header">
          <div class="order-header-main">
            <span class="order-code-badge"><?= e($order['order_code']) ?></span>
            <span class="order-date"><?= e(date('M j, Y · H:i', strtotime($order['created_at']))) ?> UTC</span>
          </div>
          <div class="order-header-status">
            <span class="badge badge-<?= e($order['status']) ?>">
              <?php if ($order['status'] === 'paid'): ?>
                ✓ Paid
              <?php elseif ($order['status'] === 'pending'): ?>
                ⏳ Pending
              <?php elseif ($order['status'] === 'rejected'): ?>
                ✕ Rejected
              <?php elseif ($order['status'] === 'cancelled'): ?>
                ✕ Cancelled
              <?php else: ?>
                <?= e(ucfirst($order['status'])) ?>
              <?php endif; ?>
            </span>
            <span class="order-amount">
              $<?= number_format((float)$order['amount'], 2) ?> <small><?= e($order['currency']) ?></small>
            </span>
          </div>
        </div>

        <div class="order-card-body">
          <?php if (!empty($order['items'])): ?>
            <div class="order-items-grid">
              <?php foreach ($order['items'] as $item): ?>
                <div class="order-item-row">
                  <div class="order-item-left">
                    <span class="order-item-cat <?= strtolower(e($item['category'])) ?>"><?= e($item['category']) ?></span>
                    <strong class="order-item-name"><?= e($item['product_name']) ?></strong>
                    <?php if ((int)$item['quantity'] > 1): ?>
                      <span class="order-item-qty">× <?= (int)$item['quantity'] ?></span>
                    <?php endif; ?>
                  </div>
                  <span class="order-item-price">$<?= number_format((float)$item['unit_price'] * (int)$item['quantity'], 2) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php
          // Direct downloads for paid product orders (theme and plugin packages)
          $downloads = [];
          if ($order['status'] === 'paid') {
              foreach ($order['items'] as $item) {
                  if (empty($item['package_path'])) {
                      continue;
                  }
                  $dlType = \Wordpresso\Services\StoreCatalog::typeFromCategory((string) $item['category']);
                  if ($dlType === null) {
                      continue;
                  }
                  $downloads[] = [
                      'type' => $dlType,
                      'id' => (int)$item['product_id'],
                      'name' => $item['product_name'],
                      'category' => $item['category'],
                  ];
              }
          }
          ?>
          <?php if (!empty($downloads)): ?>
            <div class="order-downloads">
              <span class="order-downloads-label">Downloads:</span>
              <?php foreach ($downloads as $dl): ?>
                <a class="btn-download" href="/account/orders/<?= rawurlencode($order['order_code']) ?>/download/<?= e($dl['type']) ?>/<?= (int)$dl['id'] ?>" title="Download <?= e($dl['name']) ?>">
                  ↓ Download <?= e($dl['name']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="order-card-footer">
          <div class="order-meta-info">
            <?php if (!empty($order['tx_hash'])): ?>
              <span class="order-tx-hash" title="<?= e($order['tx_hash']) ?>">
                Tx: <code><?= e(substr($order['tx_hash'], 0, 8) . '...' . substr($order['tx_hash'], -6)) ?></code>
              </span>
            <?php elseif ($order['status'] === 'pending' && !empty($order['payment_expires_at'])): ?>
              <?php
              $isExpired = strtotime((string)$order['payment_expires_at'] . ' UTC') < time();
              ?>
              <span class="<?= $isExpired ? 'order-expired-flag' : 'muted' ?>">
                <?= $isExpired ? 'Payment window expired' : 'Expires ' . e(date('M j, H:i', strtotime($order['payment_expires_at']))) . ' UTC' ?>
              </span>
            <?php elseif ($order['status'] === 'paid' && !empty($order['paid_at'])): ?>
              <span>Paid on <?= e(date('M j, Y', strtotime($order['paid_at']))) ?></span>
            <?php endif; ?>
          </div>

          <div class="order-actions">
            <?php if ($order['status'] === 'pending'): ?>
              <a class="btn btn-sm btn-primary" href="/checkout/<?= rawurlencode($order['order_code']) ?>">
                Complete payment →
              </a>
              <form method="post" action="/account/orders/<?= rawurlencode($order['order_code']) ?>/cancel" class="inline-form" onsubmit="return confirm('Cancel this order? Your cart will keep the items so you can order again.');">
                <?= \Wordpresso\Core\Csrf::field() ?>
                <button class="btn btn-sm btn-danger" type="submit">Cancel order</button>
              </form>
            <?php else: ?>
              <a class="btn btn-sm" href="/checkout/<?= rawurlencode($order['order_code']) ?>">
                <?= $order['status'] === 'cancelled' ? 'View order' : 'View receipt &amp; details' ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
