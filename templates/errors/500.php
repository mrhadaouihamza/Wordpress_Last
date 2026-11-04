<?php use function Wordpresso\Core\e; ?>
<div class="narrow">
<h1>Something went wrong</h1>
<p class="muted">An unexpected error occurred. The team has been notified via the server log.</p>
<?php if (!empty($message) && \Wordpresso\Core\Config::get('debug', false)): ?>
  <pre><?= e($message) ?></pre>
<?php endif; ?>
</div>
