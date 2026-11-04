<?php use function Wordpresso\Core\e; ?>
<div class="page-head"><div><span class="section-kicker">STORE MEMBERSHIPS</span><h1>Subscription plans</h1><p class="muted">Create plans customers can buy with all resources and a configurable website limit.</p></div><a class="btn btn-primary" href="/admin/subscriptions/new">Create plan</a></div>
<?php if ($flash): ?><p class="alert alert-success"><?= e($flash) ?></p><?php endif; ?>
<div class="subscription-plan-grid">
  <?php foreach ($plans as $plan): ?>
    <article class="subscription-plan-card">
      <div class="subscription-plan-card-top"><span class="plan-mark">◈</span><span class="badge badge-<?= (int)$plan['active']?'active':'draft' ?>"><?= (int)$plan['active']?'Published':'Hidden' ?></span></div>
      <h2><?= e($plan['name']) ?></h2><p class="muted">/<?= e($plan['slug']) ?></p>
      <div class="subscription-plan-price">$<?= number_format((float)$plan['price'],2) ?><small> USDT</small></div>
      <p class="muted"><?= (int)$plan['term_months']===0?'Lifetime access':(int)$plan['term_months'].' months access' ?> · <?= (int)($plan['website_limit'] ?? 0) === 0 ? 'Unlimited websites' : (int)$plan['website_limit'].' websites' ?> · All active plugins and themes</p>
      <div class="subscription-plan-card-actions"><a class="btn btn-sm" href="/admin/subscriptions/<?= (int)$plan['id'] ?>/edit">Edit plan</a><form method="post" action="/admin/subscriptions/<?= (int)$plan['id'] ?>/toggle"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm <?= (int)$plan['active']?'':'btn-primary' ?>" type="submit"><?= (int)$plan['active']?'Hide plan':'Publish plan' ?></button></form><form method="post" action="/admin/subscription-plans/<?= (int)$plan['id'] ?>/delete" class="inline-form"><?= \Wordpresso\Core\Csrf::field() ?><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete this subscription plan?')">Delete</button></form></div>
    </article>
  <?php endforeach; ?>
  <?php if (!$plans): ?><div class="subscription-plan-empty"><span class="plan-mark">◈</span><h2>No plans yet</h2><p>Create a plan to publish it on the Pricing page. Every plan includes all active plugins and themes.</p><a class="btn btn-primary" href="/admin/subscriptions/new">Create your first plan</a></div><?php endif; ?>
</div>
