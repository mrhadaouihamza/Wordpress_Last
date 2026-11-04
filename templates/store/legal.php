<?php use function Wordpresso\Core\e; ?>
<div class="legal-page">
  <h1><?= e($heading) ?></h1>
  <div class="card legal-card">
    <?php // $content is trusted HTML written by the store operator in Admin → Store settings. ?>
    <div class="legal-content"><?= $content ?></div>
  </div>
</div>
