<?php $rid = (int)$r['id']; ?>
<div class="page-head">
  <div><div class="breadcrumb"><a href="<?= e(url('/rosters')) ?>"><?= e(__('Rosters')) ?></a> · <a href="<?= e(url('/groups/' . $r['group_id'])) ?>"><?= e($r['group_name']) ?></a></div>
  <h1><?= e($r['title']) ?> <?= badge($r['status']) ?></h1>
  <p><?= e(fmt_date($r['service_date'], 'l j F Y')) ?><?= $r['start_time'] ? ' · ' . e(fmt_time($r['start_time'])) . '–' . e(fmt_time($r['end_time'])) : '' ?></p></div>
  <?php if ($manage): ?>
  <div class="actions">
    <form method="post" action="<?= e(url('/rosters/' . $rid . '/autofill')) ?>"><?= csrf_field() ?><button class="btn"><?= e(__('Suggest volunteers')) ?></button></form>
    <form method="post" action="<?= e(url('/rosters/' . $rid . '/copy')) ?>"><?= csrf_field() ?><button class="btn"><?= e(__('Copy to next week')) ?></button></form>
    <form method="post" action="<?= e(url('/rosters/' . $rid . '/publish')) ?>" data-confirm="<?= e(__('Publish and send invitations to everyone rostered?')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= e($r['status'] === 'published' ? __('Re-send pending invitations') : __('Approve & publish')) ?></button></form>
  </div>
  <?php endif; ?>
</div>
<?php if ($r['status'] === 'draft' && $manage): ?><div class="callout"><?= e(__('Draft: volunteers are not notified until a ministry leader approves and publishes this roster.')) ?></div><?php endif; ?>
<?php if ($r['notes']): ?><p><?= nl2br(e($r['notes'])) ?></p><?php endif; ?>
<div class="split">
<div class="card">
  <h2><?= e(__('Positions')) ?></h2>
  <?php foreach ($slots as $s): $cands = $suggest[$s['position_id']] ?? []; ?>
  <div class="slot">
    <div class="pos"><?= e($s['position']) ?><?= (int)$s['requires_wwcc'] ? ' <span class="badge badge-gold">WWCC</span>' : '' ?></div>
    <div>
      <?php if ($s['person_id']): ?><strong><?= e(person_name($s)) ?></strong> <?= badge($s['status']) ?><?php if ($s['responded_at']): ?> <span class="small muted"><?= e(fmt_datetime($s['responded_at'])) ?></span><?php endif; ?>
      <?php else: ?><span class="badge badge-red"><?= e(__('Unfilled')) ?></span><?php endif; ?>
      <?php if ($manage && (!$s['person_id'] || in_array($s['status'], ['declined', 'swap_requested'], true))): ?>
        <form method="post" action="<?= e(url('/rosters/' . $rid . '/assign')) ?>" class="form-row" style="margin-top:6px"><?= csrf_field() ?>
          <input type="hidden" name="assignment_id" value="<?= (int)$s['id'] ?>">
          <label class="sr-only"><select name="person_id" aria-label="<?= e(__('Choose volunteer')) ?>"><option value="">— <?= e(__('Choose volunteer')) ?> —</option>
            <?php foreach ($cands as $c): ?><option value="<?= (int)$c['person_id'] ?>" <?= $c['eligible'] ? '' : 'disabled' ?>><?= e($c['name']) ?><?= $c['preferred'] ? ' ★' : '' ?> (<?= e(__('%d in 30 days', $c['recent'])) ?>)<?= $c['eligible'] ? ($c['burnout'] ? ' ⚠ ' . e(__('serving often')) : '') : ' — ' . e($c['warnings'][0] ?? '') ?></option><?php endforeach; ?>
          </select></label>
          <button class="btn btn-sm"><?= e(__('Assign')) ?></button>
        </form>
        <details class="suggest"><summary><?= e(__('Why these suggestions?')) ?></summary>
          <?php foreach ($cands as $c): ?><div class="cand<?= $c['eligible'] ? '' : ' ineligible' ?>"><span><?= e($c['name']) ?><?= $c['preferred'] ? ' <span class="badge badge-gold">' . e(__('Preferred')) . '</span>' : '' ?></span><span class="small"><?= $c['warnings'] ? e(implode(' · ', $c['warnings'])) : '<span class="badge badge-green">' . e(__('Available')) . '</span>' ?></span></div><?php endforeach; ?>
          <?php if (!$cands): ?><p class="small muted"><?= e(__('Nobody is approved for this position yet.')) ?> <a href="<?= e(url('/volunteers')) ?>"><?= e(__('Approve volunteers')) ?></a></p><?php endif; ?>
        </details>
      <?php endif; ?>
    </div>
    <div class="nowrap"><?php if ($manage): ?>
      <?php if ($s['person_id']): ?><form class="inline-form" method="post" action="<?= e(url('/rosters/assignments/' . $s['id'] . '/clear')) ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= e(__('Clear')) ?></button></form><?php endif; ?>
      <form class="inline-form" method="post" action="<?= e(url('/rosters/assignments/' . $s['id'] . '/remove')) ?>" data-confirm="<?= e(__('Remove this slot?')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" aria-label="<?= e(__('Remove slot')) ?>">✕</button></form>
    <?php endif; ?></div>
  </div>
  <?php endforeach; ?>
  <?php if (!$slots): ?><p class="empty"><?= e(__('No slots yet.')) ?></p><?php endif; ?>
  <?php if ($manage && $positions): ?>
  <form method="post" action="<?= e(url('/rosters/' . $rid . '/slots')) ?>" class="form-row" style="margin-top:12px"><?= csrf_field() ?>
    <label><?= e(__('Add slot')) ?><select name="position_id"><?= options($positions) ?></select></label><label><?= e(__('How many')) ?><input type="number" name="qty" value="1" min="1" max="20"></label><button class="btn btn-sm"><?= e(__('Add')) ?></button>
  </form>
  <?php endif; ?>
</div>
<div>
  <?php if ($siblings): ?><div class="card"><h2 class="h3"><?= e(__('Other teams on this date')) ?></h2><ul class="list-plain small"><?php foreach ($siblings as $s): ?><li><a href="<?= e(url('/rosters/' . $s['id'])) ?>"><?= e($s['group_name']) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if ($manage): ?>
  <div class="card"><h2 class="h3"><?= e(__('Roster details')) ?></h2>
  <form method="post" action="<?= e(url('/rosters/' . $rid)) ?>" class="form"><?= csrf_field() ?>
    <label><?= e(__('Title')) ?><input name="title" value="<?= e($r['title']) ?>"></label>
    <label><?= e(__('Date')) ?><input type="date" name="service_date" value="<?= e($r['service_date']) ?>"></label>
    <div class="grid-2"><label><?= e(__('Start')) ?><input type="time" name="start_time" value="<?= e(substr((string)$r['start_time'], 0, 5)) ?>"></label><label><?= e(__('End')) ?><input type="time" name="end_time" value="<?= e(substr((string)$r['end_time'], 0, 5)) ?>"></label></div>
    <label><?= e(__('Notes for volunteers')) ?><textarea name="notes"><?= e($r['notes']) ?></textarea></label>
    <button class="btn btn-sm btn-primary"><?= e(__('Save')) ?></button>
  </form>
  <form method="post" action="<?= e(url('/rosters/' . $rid . '/delete')) ?>" data-confirm="<?= e(__('Delete this roster? Rostered volunteers will be told it is cancelled.')) ?>" style="margin-top:10px"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Delete roster')) ?></button></form>
  </div>
  <?php endif; ?>
  <div class="card small"><h2 class="h3"><?= e(__('How suggestions work')) ?></h2><p class="muted"><?= e(__('Affreso only offers people approved for the position who have completed required training, hold a current WWCC where needed, meet the minimum age, are not marked unavailable and are not serving elsewhere at the same time. It favours preferred roles and people who have served less recently, and flags anyone serving often to help prevent burnout.')) ?></p></div>
</div>
</div>
