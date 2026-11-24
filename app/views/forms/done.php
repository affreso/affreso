<div class="form-page"><div class="card center">
<h1><?= e(__('Thank you!')) ?></h1>
<p><?= trim((string)$f['confirmation']) !== '' ? nl2br(e((string)$f['confirmation'])) : e(__('Thank you — we have received your response.')) ?></p>
<?php if (!empty($sub) && (int)$sub['instalments'] > 1): ?><p><?= e(__('Thank you for setting up %1$d monthly payments of %2$s. The first is being taken now, and the rest will come out on the same day each month until %3$s is paid. They stop by themselves after the last one.', (int)$sub['instalments'], fmt_money(intdiv((int)$sub['amount_cents'], max(1, (int)$sub['instalments']))), fmt_money((int)$sub['amount_cents']))) ?></p><?php endif; ?>
<p class="small muted"><?= e(__('If you gave an email address, a copy of your answers is on its way.')) ?></p>
<?php if ((Auth::id() !== null)): ?><a class="btn" href="<?= e(url('/me/forms')) ?>"><?= e(__('Back to forms')) ?></a><?php endif; ?>
</div></div>
