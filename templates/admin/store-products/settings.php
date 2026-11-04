<?php use function Wordpresso\Core\e; ?>
<div class="store-settings-page">
  <div class="page-head product-form-heading">
    <div><span class="section-kicker">WEBSITE CONFIGURATION</span><h1>Store settings</h1><p class="muted">Manage the title, descriptions, and contact details shown on your storefront.</p></div>
  </div>
  <?php if ($flash): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form class="product-editor form" method="post" action="/admin/store-settings">
    <?= \Wordpresso\Core\Csrf::field() ?>
    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">◉</span><div><h2>Store identity</h2><p>These values appear in the storefront header, home page, and browser metadata.</p></div></div>
      <div class="product-form-grid">
        <label>Website title<input type="text" name="site_title" placeholder="e.g. Wordpresso" maxlength="100" required value="<?= e($settings['site_title']) ?>"><small class="field-hint"><span>Shown in</span> Header, page titles, and footer</small></label>
        <label>Tagline<input type="text" name="site_tagline" placeholder="Premium tools for your WordPress site" maxlength="180" value="<?= e($settings['site_tagline']) ?>"><small class="field-hint"><span>Shown on</span> Home page hero</small></label>
        <label class="product-description-field">Store description<textarea name="site_description" placeholder="Describe your store and what customers can find here." rows="4" maxlength="1200"><?= e($settings['site_description']) ?></textarea><small class="field-hint"><span>Shown on</span> Home page and as the default page description</small></label>
        <label class="product-description-field">Search description<textarea name="meta_description" placeholder="A short description of your website for search results." rows="3" maxlength="320"><?= e($settings['meta_description']) ?></textarea><small class="field-hint"><span>Search</span> Browser and search engine description</small></label>
      </div>
    </section>
    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">✉</span><div><h2>Customer information</h2><p>Set a public support contact and the short line shown in the site footer.</p></div></div>
      <div class="product-form-grid">
        <label>Support email<input type="email" name="support_email" placeholder="support@example.com" maxlength="254" value="<?= e($settings['support_email']) ?>"><small class="field-hint"><span>Optional</span> Leave blank to hide the contact email</small></label>
        <label>Footer description<input type="text" name="footer_text" placeholder="WordPress products and support in one place." maxlength="300" value="<?= e($settings['footer_text']) ?>"></label>
      </div>
    </section>
    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">§</span><div><h2>Legal pages</h2><p>Published at <strong>/privacy</strong> and <strong>/terms</strong> and linked in every footer. HTML is allowed — use paragraphs, headings, lists and links. Until you save text here, a default policy is shown.</p></div></div>
      <div class="product-form-grid">
        <label class="product-description-field">Privacy policy<textarea name="privacy_policy" rows="10" maxlength="60000" placeholder="&lt;p&gt;Explain what information you collect from customers and how it is used…&lt;/p&gt;"><?= e($settings['privacy_policy']) ?></textarea><small class="field-hint"><span>Published at</span> /privacy</small></label>
        <label class="product-description-field">Terms of use<textarea name="terms_of_use" rows="10" maxlength="60000" placeholder="&lt;p&gt;Describe the rules for accounts, purchases, licensing and connected sites…&lt;/p&gt;"><?= e($settings['terms_of_use']) ?></textarea><small class="field-hint"><span>Published at</span> /terms</small></label>
      </div>
    </section>
    <div class="product-form-actions"><button class="btn btn-primary" type="submit">Save store settings</button></div>
  </form>
</div>
