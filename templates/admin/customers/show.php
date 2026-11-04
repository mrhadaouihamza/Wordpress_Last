<?php use function Wordpresso\Core\e; ?>
<div class="page-head">
  <h1><?= e($customer['email']) ?></h1>
  <div class="actions">
    <a class="btn" href="/admin/customers/<?= (int) $customer['id'] ?>/edit">Edit</a>
    <form method="post" action="/admin/customers/<?= (int)$customer['id'] ?>/delete" class="inline-form">
      <?= \Wordpresso\Core\Csrf::field() ?>
      <button class="btn btn-danger" onclick="return confirm('Delete this customer and all related data?')">Delete customer</button>
    </form>
    <form method="post" action="/admin/customers/<?= (int) $customer['id'] ?>/suspend" class="inline-form">
      <?= \Wordpresso\Core\Csrf::field() ?>
      <button class="btn <?= $customer['status'] === 'active' ? 'btn-danger' : '' ?>">
        <?= $customer['status'] === 'active' ? 'Suspend customer' : 'Reactivate' ?>
      </button>
    </form>
  </div>
</div>
<p class="muted">Status: <span class="badge badge-<?= e($customer['status']) ?>"><?= e($customer['status']) ?></span>
   · Registered <?= e(substr((string) $customer['created_at'], 0, 10)) ?></p>

<?php if (!empty($generatedPassword)): ?>
  <div class="alert alert-credentials">
    Initial password for this customer: <code><?= e($generatedPassword) ?></code> — it will not be shown again.
  </div>
<?php endif; ?>
<?php if (!empty($flash)): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<h2 id="subscriptions">Subscriptions &amp; entitlements</h2>
<?php if ($plans !== []): ?>
  <section class="card form subscription-assignment-card">
    <h3>Assign a subscription plan</h3>
    <p class="muted">Grant this customer a currently published plan. Its all-resource access and access term will be applied immediately.</p>
    <form method="post" action="/admin/customers/<?= (int)$customer['id'] ?>/subscriptions/assign" class="subscription-assignment-form">
      <?= \Wordpresso\Core\Csrf::field() ?>
      <label>Published plan<select name="plan_id" required><option value="">Choose a plan</option><?php foreach ($plans as $plan): ?><option value="<?= (int)$plan['id'] ?>"><?= e($plan['name']) ?> · <?= number_format((float)$plan['price'],2) ?> USDT · <?= (int)$plan['term_months']===0?'lifetime':(int)$plan['term_months'].' months' ?> · <?= (int)($plan['website_limit'] ?? 0) === 0 ? 'unlimited websites' : (int)$plan['website_limit'].' websites' ?></option><?php endforeach; ?></select></label>
      <button type="submit" class="btn btn-primary" onclick="return confirm('Assign this plan and activate its included product access for this customer?')">Assign plan</button>
    </form>
  </section>
<?php else: ?>
  <p class="muted">There are no published plans to assign. Create and publish a plan under Admin → Subscriptions first.</p>
<?php endif; ?>
<?php if ($subscriptions === []): ?>
  <p class="muted">No subscriptions or purchases yet.</p>
<?php else: ?>
  <?php foreach ($subscriptions as $sub): ?>
    <?php
    $isExpired = $sub['expires_at'] !== null && strtotime((string) $sub['expires_at']) <= time();
    ?>
    <div class="card">
      <div class="page-head">
        <h3><?= e($sub['plan_name'] !== '' ? $sub['plan_name'] : 'Subscription #' . $sub['id']) ?>
          <span class="badge badge-<?= e($isExpired && $sub['status'] === 'active' ? 'expired' : $sub['status']) ?>"><?= e($sub['status']) ?></span>
          <?php if ((int) $sub['updates_included'] !== 1): ?><span class="badge badge-revoked">no updates</span><?php endif; ?>
        </h3>
        <form method="post" action="/admin/subscriptions/<?= (int) $sub['id'] ?>/delete" class="inline-form">
          <?= \Wordpresso\Core\Csrf::field() ?>
          <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this subscription?')">Delete</button>
        </form>
      </div>
      <p class="muted"><?= (int)($sub['website_limit'] ?? 0) === 0 ? 'Unlimited websites' : (int)$sub['website_limit'] . ' websites' ?> · All active plugins and resources:
        · Expires: <?= e($sub['expires_at'] !== null ? substr((string) $sub['expires_at'], 0, 10) : 'never') ?>
      </p>
      <details>
        <summary>Edit subscription</summary>
        <form method="post" action="/admin/customers/<?= (int) $customer['id'] ?>/subscriptions" class="form" style="margin-top: 1rem">
          <?= \Wordpresso\Core\Csrf::field() ?>
          <input type="hidden" name="subscription_id" value="<?= (int) $sub['id'] ?>">
          <div class="form-row">
            <label>Plan name
              <input type="text" name="plan_name" value="<?= e($sub['plan_name']) ?>">
            </label>
            <label>Status
              <select name="status">
                <?php foreach (['active', 'expired', 'cancelled'] as $status): ?>
                  <option value="<?= e($status) ?>" <?= $sub['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="form-row">
            <label>Starts
              <input type="date" name="starts_at" required value="<?= e(substr((string) $sub['starts_at'], 0, 10)) ?>">
            </label>
            <label>Expires (empty = never)
              <input type="date" name="expires_at" value="<?= e($sub['expires_at'] !== null ? substr((string) $sub['expires_at'], 0, 10) : '') ?>">
            </label>
          </div>
          <label class="checkbox">
            <input type="checkbox" name="updates_included" value="1" <?= (int) $sub['updates_included'] === 1 ? 'checked' : '' ?>>
            Updates included in this plan
          </label>
          <button type="submit" class="btn btn-primary">Save subscription</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<h2>Websites</h2>
<?php if ($websites === []): ?>
  <p class="muted">No websites registered.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Name</th><th>URL</th><th>Status</th><th>WP / PHP</th><th>Last check</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($websites as $site): ?>
      <tr>
        <td><?= e($site['name']) ?></td>
        <td><?= e($site['url']) ?></td>
        <td><span class="badge badge-<?= e($site['status']) ?>"><?= e($site['status']) ?></span></td>
        <td><?= e($site['wp_version'] ?: '—') ?> / <?= e($site['php_version'] ?: '—') ?></td>
        <td><?= e($site['last_check_at'] ?? 'never') ?></td>
        <td class="actions">
          <?php if ($site['status'] === 'active'): ?>
            <form method="post" action="/admin/websites/<?= (int) $site['id'] ?>/revoke" class="inline-form">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <button class="btn btn-sm btn-danger" onclick="return confirm('Revoke this connection?')">Revoke</button>
            </form>
          <?php else: ?>
            <form method="post" action="/admin/websites/<?= (int) $site['id'] ?>/activate" class="inline-form">
              <?= \Wordpresso\Core\Csrf::field() ?>
              <button class="btn btn-sm">Reactivate</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
