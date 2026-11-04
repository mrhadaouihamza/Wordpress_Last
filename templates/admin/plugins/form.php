<?php use function Wordpresso\Core\e; $isEdit = $plugin !== null; ?>
<div class="product-form-page">
  <div class="page-head product-form-heading"><div><span class="section-kicker">PLUGIN UPDATER</span><h1><?= $isEdit ? 'Edit plugin details' : 'Add plugin' ?></h1><p class="muted">Set the plugin identity used by WordPress to match and deliver updates.</p></div><div class="product-form-heading-actions"><a class="btn" href="<?= $isEdit ? '/admin/plugins/'.(int)$plugin['id'] : '/admin/products' ?>">← Back</a></div></div>
  <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="<?= $isEdit ? '/admin/plugins/'.(int)$plugin['id'] : '/admin/plugins' ?>" class="product-editor form">
    <?= \Wordpresso\Core\Csrf::field() ?>
    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">↻</span><div><h2>Plugin identity</h2><p>Match the exact directory and main file used on customer websites.</p></div></div>
      <div class="product-form-grid">
        <label>Display name<input type="text" name="name" placeholder="e.g. Elementor Pro" required value="<?= e($plugin['name'] ?? '') ?>"><small class="field-hint"><span>Example</span> Elementor Pro</small></label>
        <label>Slug (public identifier)<input type="text" name="slug" placeholder="e.g. elementor-pro" required pattern="[a-z0-9][a-z0-9-]*" value="<?= e($plugin['slug'] ?? '') ?>"><small class="field-hint"><span>Format</span> lowercase letters, numbers, and hyphens</small></label>
        <label class="product-description-field">Technical identifier — plugin basename<input type="text" name="basename" placeholder="elementor-pro/elementor-pro.php" required value="<?= e($plugin['basename'] ?? '') ?>"><small class="field-hint"><span>Example</span> <code>elementor-pro/elementor-pro.php</code> inside <code>wp-content/plugins</code></small></label>
        <label class="product-description-field">Description (public)<textarea name="description" placeholder="Describe what the plugin does." rows="3"><?= e($plugin['description'] ?? '') ?></textarea></label>
        <label>Minimum WordPress<input type="text" name="min_wp_version" placeholder="e.g. 6.0" value="<?= e($plugin['min_wp_version'] ?? '') ?>"><small class="field-hint"><span>Example</span> 6.0</small></label>
        <label>Minimum PHP<input type="text" name="min_php_version" placeholder="e.g. 8.0" value="<?= e($plugin['min_php_version'] ?? '') ?>"><small class="field-hint"><span>Example</span> 8.0</small></label>
        <label>Tested up to WP<input type="text" name="tested_wp_version" placeholder="e.g. 6.8" value="<?= e($plugin['tested_wp_version'] ?? '') ?>"><small class="field-hint"><span>Example</span> 6.8</small></label>
      </div>
      <label class="checkbox product-auto-update"><input type="checkbox" name="auto_update_allowed" value="1" <?= (int)($plugin['auto_update_allowed'] ?? 1)===1?'checked':'' ?>> Allow eligible customer sites to auto-update this plugin</label>
    </section>
    <div class="product-form-actions"><button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save plugin details' : 'Create plugin' ?></button></div>
  </form>
</div>
