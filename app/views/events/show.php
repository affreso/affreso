<?php $eid = (int)$ev['id']; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/events')) ?>"><?= e(__('Events & calendar')) ?></a></div>
<h1><?= e($ev['title']) ?> <?= badge($ev['status']) ?></h1><p><?= e(fmt_datetime($ev['start_at'])) ?> – <?= e(fmt_time($ev['end_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/events/' . $eid . '/export')) ?>"><?= e(__('Export CSV')) ?></a><a class="btn btn-primary" href="<?= e(url('/events/' . $eid . '/edit')) ?>"><?= e(__('Edit')) ?></a></div></div>
<div class="split">
<div class="card table-wrap">
  <div class="card-head"><h2><?= e(__('Registrations & attendance')) ?></h2><span class="muted"><?= e($ev['capacity'] ? __('%d of %d places', $taken, (int)$ev['capacity']) : __('%d registered', $taken)) ?></span></div>
  <table><thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('Contact')) ?></th><th><?= e(__('Guests')) ?></th><th><?= e(__('Status')) ?></th><th><span class="sr-only"><?= e(__('Actions')) ?></span></th></tr></thead><tbody>
  <?php foreach ($regs as $r): ?><tr><td><?= $r['person_id'] && can('people.view') ? '<a href="' . e(url('/people/' . $r['person_id'])) . '">' . e($r['name']) . '</a>' : e($r['name']) ?><?= $r['form_submission_id'] && $form && can('forms.manage') ? '<div class="small"><a href="' . e(url('/forms/' . $form['id'] . '/responses/' . $r['form_submission_id'])) . '">' . e(__('Form response')) . '</a></div>' : '' ?></td><td class="small"><?= e($r['email']) ?> <span dir="ltr"><?= e($r['phone']) ?></span></td><td><?= (int)$r['guests'] ?></td><td><?= badge($r['status']) ?></td>
  <td class="actions-cell"><form class="inline-form" method="post" action="<?= e(url('/events/registrations/' . $r['id'])) ?>"><?= csrf_field() ?>
    <?php if ($r['status'] !== 'attended'): ?><button class="btn btn-sm" name="status" value="attended"><?= e(__('Attended')) ?></button><?php endif; ?>
    <?php if ($r['status'] === 'waitlisted'): ?><button class="btn btn-sm" name="status" value="registered"><?= e(__('Confirm place')) ?></button><?php endif; ?>
    <?php if ($r['status'] !== 'cancelled'): ?><button class="btn btn-sm btn-danger" name="status" value="cancelled"><?= e(__('Cancel')) ?></button><?php endif; ?>
  </form></td></tr><?php endforeach; ?>
  <?php if (!$regs): ?><tr><td colspan="5" class="empty"><?= e(__('No registrations yet.')) ?></td></tr><?php endif; ?>
  </tbody></table>
  <h2 class="h3"><?= e(__('Add someone / record walk-in attendance')) ?></h2>
  <form method="post" action="<?= e(url('/events/' . $eid . '/registrations')) ?>" class="form-row"><?= csrf_field() ?>
    <label><?= e(__('Person')) ?><select name="person_id"><?= options(people_options(), null, __('— Guest (enter name) —')) ?></select></label>
    <label><?= e(__('Or guest name')) ?><input name="name"></label><label><?= e(__('Email')) ?><input type="email" name="email"></label>
    <label><?= e(__('Guests')) ?><input type="number" name="guests" value="0" min="0" style="max-width:80px"></label><button class="btn"><?= e(__('Add')) ?></button>
  </form>
</div>
<div>
  <?php if ($series): ?><div class="card"><h2 class="h3"><?= e(__('Repeats')) ?></h2>
    <p><?= e(EventSeries::describe($series, $firstStart)) ?></p>
    <ul class="list-plain small"><?php foreach ($siblings as $o): ?><li><?= (int)$o['id'] === $eid ? '<strong>' . e(fmt_datetime($o['start_at'])) . '</strong>' : '<a href="' . e(url('/events/' . $o['id'])) . '">' . e(fmt_datetime($o['start_at'])) . '</a>' ?> <?= $o['status'] === 'cancelled' ? badge('cancelled') : '' ?></li><?php endforeach; ?></ul>
    <form method="post" action="<?= e(url('/events/' . $eid . '/remove-date')) ?>" data-confirm="<?= e(__('Remove this date from the series?')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Remove just this date')) ?></button></form>
  </div><?php endif; ?>
  <div class="card"><h2 class="h3"><?= e(__('Registration form')) ?></h2>
  <?php if ($form): ?>
    <p><strong><?= e($form['title']) ?></strong> <?= badge($form['status']) ?></p>
    <p class="small"><?= e(Forms::isPaid($form) ? __('Cost: %s', Forms::priceText($form)) . ((int)$form['instalments'] > 1 ? ' · ' . __('or up to %d monthly payments', (int)$form['instalments']) : '') : __('Free')) ?><?= $form['capacity'] ? ' · ' . e(__('Limit (places)')) . ': ' . (int)$form['capacity'] : '' ?></p>
    <p class="small muted"><?= e(__('People register for this event by filling in the form. Each confirmed response (paid, or submitted if free) appears in the list here; cancelling or fully refunding the response removes it.')) ?></p>
    <?php if (can('forms.manage')): ?><p><a class="btn btn-sm" href="<?= e(url('/forms/' . $form['id'] . '/edit')) ?>"><?= e(__('Edit form')) ?></a> <a class="btn btn-sm" href="<?= e(url('/forms/' . $form['id'] . '/responses')) ?>"><?= e(__('Responses')) ?></a></p><?php endif; ?>
  <?php else: ?>
    <p class="small muted"><?= e(__('To charge for this event, collect extra details or offer instalments, create a form and choose this event as its linked event.')) ?></p>
    <?php if (can('forms.manage')): ?><form method="post" action="<?= e(url('/forms')) ?>"><?= csrf_field() ?><input type="hidden" name="template" value="blank"><input type="hidden" name="title" value="<?= e($ev['title']) ?>"><input type="hidden" name="event_id" value="<?= $eid ?>"><button class="btn btn-sm"><?= e(__('Create a registration form')) ?></button></form><?php endif; ?>
  <?php endif; ?></div>
  <?php if ($ev['description']): ?><div class="card"><h2 class="h3"><?= e(__('About')) ?></h2><p><?= nl2br(e($ev['description'])) ?></p></div><?php endif; ?>
  <div class="card"><h2 class="h3"><?= e(__('Rooms & equipment')) ?></h2><ul class="list-plain small"><?php foreach ($bookings as $b): ?><li><?= e($b['room']) ?> <?= badge($b['status']) ?></li><?php endforeach; ?></ul><?php if (!$bookings): ?><p class="empty"><?= e(__('None booked.')) ?></p><?php endif; ?></div>
  <div class="card"><h2 class="h3"><?= e(__('Share')) ?></h2><?php if ($ev['is_public']): ?><input readonly value="<?= e(url('/public/events/' . $eid, [], true)) ?>" aria-label="<?= e(__('Public link')) ?>"><?php else: ?><p class="small muted"><?= e(__('Internal event — members register from My Affreso → Events.')) ?></p><?php endif; ?></div>
</div>
</div>
