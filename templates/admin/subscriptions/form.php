<?php use function Wordpresso\Core\e; $editing = !empty($plan); $active = $editing ? !empty($plan['active']) : true; ?>
<div class="subscription-form-page">
  <div class="page-head product-form-heading"><div><span class="section-kicker">STORE MEMBERSHIPS</span><h1><?= $editing ? 'Edit subscription plan' : 'Create subscription plan' ?></h1><p class="muted">Set the price, access period, and website limit. Use 0 for unlimited websites.</p></div><a class="btn" href="/admin/subscriptions">← Back to plans</a></div>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form class="product-editor form" method="post" enctype="multipart/form-data" action="<?= $editing ? '/admin/subscriptions/'.(int)$plan['id'] : '/admin/subscriptions' ?>">
    <?= \Wordpresso\Core\Csrf::field() ?>
    <?php if ($editing): ?><input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>"><?php endif; ?>
    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">◈</span><div><h2>Plan details</h2><p>Customers can buy published plans from the Pricing page.</p></div></div>
      <div class="product-image-editor">
        <?php if ($editing && !empty($plan['image_path'])): ?><img src="/products/plan/<?= (int)$plan['id'] ?>/image" alt="Current plan artwork"><?php else: ?><div class="product-image-empty">◈</div><?php endif; ?>
        <label>Plan image<input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"><small>Optional. JPG, PNG, or WebP, up to 5 MB. Leave empty to keep the current image.</small></label>
      </div>
      <div class="product-form-grid">
        <label>Plan name<input type="text" name="name" required value="<?= e($plan['name']??'') ?>"><small class="field-hint"><span>Example</span> Complete WordPress Toolkit</small></label>
        <label>URL slug<input type="text" name="slug" required pattern="[a-z0-9][a-z0-9-]*" value="<?= e($plan['slug']??'') ?>"><small class="field-hint"><span>Format</span> lowercase letters, numbers, and hyphens</small></label>
        <label>Price (USDT)<input type="number" name="price" min="0" step="0.01" required value="<?= e($plan['price']??'') ?>"></label>
        <label>Access period (months)<input type="number" name="term_months" min="0" value="<?= e($plan['term_months']??12) ?>" required><small class="field-hint"><span>Rule</span> 0 means lifetime access</small></label>
        <label>Website limit<input type="number" name="website_limit" min="0" value="<?= e($plan['website_limit']??0) ?>" required><small class="field-hint"><span>Rule</span> 0 means unlimited websites</small></label>
        <label class="product-description-field">Description<textarea name="description" rows="4"><?= e($plan['description']??'') ?></textarea></label>
        <label class="checkbox product-auto-update"><input type="checkbox" name="active" value="1" <?= $active?'checked':'' ?>> Publish this plan on the Pricing page</label>
      </div>
    </section>
    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">✓</span><div><h2>All plugins and resources included</h2><p>Every subscription automatically grants access to all active plugins, themes, and future resources. Plugin access also includes WordPress updater access.</p></div></div>
      <p class="alert alert-credentials">You do not need to select products. New and existing subscription plans use all-access entitlement.</p>
    </section>
    <div class="product-form-actions"><a class="btn" href="/admin/subscriptions">Cancel</a><button class="btn btn-primary" type="submit"><?= $editing ? 'Save plan' : 'Create plan' ?></button></div>
  </form>
</div>
