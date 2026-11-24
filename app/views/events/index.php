<?php $prev = (clone $first)->modify('-1 month')->format('Y-m'); $next = (clone $first)->modify('+1 month')->format('Y-m'); ?>
<div class="page-head"><div><h1><?= e(__('Events & calendar')) ?></h1><p><?= e(__('Public events can be shown on your church website:')) ?> <a href="<?= e(url('/public/events')) ?>" target="_blank"><?= e(url('/public/events', [], true)) ?></a></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/rooms')) ?>"><?= e(__('Rooms & bookings')) ?></a><a class="btn btn-primary" href="<?= e(url('/events/new')) ?>">+ <?= e(__('New event')) ?></a></div></div>
<div class="card-head"><h2><?= e(fmt_date($first->format('Y-m-d'), 'F Y')) ?></h2><div class="actions"><a class="btn btn-sm" href="<?= e(url('/events', ['month' => $prev])) ?>">‹</a><a class="btn btn-sm" href="<?= e(url('/events')) ?>"><?= e(__('Today')) ?></a><a class="btn btn-sm" href="<?= e(url('/events', ['month' => $next])) ?>">›</a></div></div>
<div class="calendar">
  <?php foreach ([__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')] as $d): ?><div class="dow"><?= e($d) ?></div><?php endforeach; ?>
  <?php for ($d = clone $start; $d <= $end; $d->modify('+1 day')): $k = $d->format('Y-m-d'); ?>
    <div class="day<?= $d->format('m') !== $first->format('m') ? ' other' : '' ?><?= $k === date('Y-m-d') ? ' today' : '' ?>">
      <a class="dnum" href="<?= e(url('/events/new', ['date' => $k])) ?>" title="<?= e(__('Add event')) ?>"><?= $d->format('j') ?></a>
      <?php foreach ($byDay[$k] ?? [] as $item): $r = $item['row']; ?>
        <?php if ($item['type'] === 'event'): ?><a class="ev<?= $r['is_public'] ? '' : ' private' ?>" href="<?= e(url('/events/' . $r['id'])) ?>"><?= e(fmt_time($r['start_at'])) ?> <?= e($r['title']) ?></a>
        <?php else: ?><span class="ev booking" title="<?= e($r['room']) ?>"><?= e(fmt_time($r['start_at'])) ?> <?= e($r['title']) ?></span><?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>
<p class="small muted"><span class="badge badge-blue"><?= e(__('Public')) ?></span> <span class="badge badge-gold"><?= e(__('Internal')) ?></span> <span class="badge badge-grey"><?= e(__('Room booking')) ?></span></p>
<div class="card table-wrap"><h2><?= e(__('Upcoming')) ?></h2><table><thead><tr><th><?= e(__('When')) ?></th><th><?= e(__('Event')) ?></th><th><?= e(__('Registrations')) ?></th><th><?= e(__('Status')) ?></th></tr></thead><tbody>
<?php foreach ($upcoming as $ev): ?><tr><td class="nowrap"><?= e(fmt_datetime($ev['start_at'])) ?></td><td><a href="<?= e(url('/events/' . $ev['id'])) ?>"><?= e($ev['title']) ?></a> <?= $ev['is_public'] ? '' : '<span class="badge badge-grey">' . e(__('Internal')) . '</span>' ?></td><td><?= (int)$ev['registration_enabled'] ? (int)$ev['taken'] . ($ev['capacity'] ? ' / ' . (int)$ev['capacity'] : '') . ((int)$ev['waiting'] ? ' · ' . e(__('%d waiting', (int)$ev['waiting'])) : '') : '—' ?></td><td><?= badge($ev['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
