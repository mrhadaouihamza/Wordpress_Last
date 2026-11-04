<?php use function Wordpresso\Core\e; ?>
<div class="store-settings-page">
  <div class="page-head product-form-heading">
    <div><span class="section-kicker">CHECKOUT CONFIGURATION</span><h1>Payment settings</h1><p class="muted">Configure the wallet and BNB Smart Chain RPC used to verify customer payments.</p></div>
  </div>
  <?php if ($flash): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
  <form class="product-editor form" method="post" action="/admin/payments">
    <?= \Wordpresso\Core\Csrf::field() ?>
    <section class="product-editor-section">
      <div class="product-section-heading"><span class="product-section-icon">₿</span><div><h2>Crypto checkout</h2><p>Payments are accepted in USDT on BNB Smart Chain (BEP20).</p></div></div>
      <div class="product-form-grid">
        <label>Store wallet address<input type="text" name="wallet" placeholder="0x followed by 40 hexadecimal characters" required pattern="0x[a-fA-F0-9]{40}" value="<?= e($settings['wallet']) ?>"></label>
        <label>USDT token contract<input type="text" name="contract" placeholder="0x followed by 40 hexadecimal characters" required pattern="0x[a-fA-F0-9]{40}" value="<?= e($settings['contract']) ?>"></label>
        <label class="product-description-field">BNB Smart Chain RPC URL<input type="url" name="rpc" placeholder="https://your-bsc-rpc.example" required value="<?= e($settings['rpc']) ?>"><small class="field-hint"><span>Required</span> Use a reliable HTTPS BNB Smart Chain endpoint</small></label>
      </div>
    </section>
    <div class="product-form-actions"><button class="btn btn-primary" type="submit">Save payment settings</button></div>
  </form>
</div>
