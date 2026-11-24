<?php $gid = (int)$g['id']; ?>
<div class="page-head">
  <div><div class="breadcrumb"><a href="<?= e(url('/groups')) ?>"><?= e(__('Ministries & groups')) ?></a></div>
  <h1><?= e($g['name']) ?></h1>
  <p><?= e(GroupsController::types()[$g['group_type']]) ?><?= $g['meeting_schedule'] ? ' · ' . e($g['meeting_schedule']) : '' ?><?= $g['location'] ? ' · ' . e($g['location']) : '' ?></p></div>
  <div class="actions"><?php if (can_manage_roster_for($gid) && $positions): ?><a class="btn btn-primary" href="<?= e(url('/rosters/new', ['group' => $gid])) ?>">+ <?= e(__('New roster')) ?></a><?php endif; ?></div>
</div>
<?php if ($g['description']): ?><p><?= nl2br(e($g['description'])) ?></p><?php endif; ?>
<div class="split">
<div>
  <div class="card">
    <div class="card-head"><h2><?= e(__('People (%d)', count($members))) ?></h2></div>
    <table><tbody>
    <?php foreach ($members as $m): ?>
      <tr><td><?= can('people.view') ? '<a href="' . e(url('/people/' . $m['id'])) . '">' . e(person_name($m)) . '</a>' : e(person_name($m)) ?> <?= $m['member_role'] !== 'member' ? '<span class="badge badge-gold">' . e(__(ucfirst($m['member_role']))) . '</span>' : '' ?></td>
      <td class="small"><?= $manage ? '<span dir="ltr">' . e($m['mobile']) . '</span>' : '' ?></td><td class="small"><?= $manage ? e($m['email']) : '' ?></td>
      <td class="actions-cell"><?php if ($manage): ?><form class="inline-form" method="post" action="<?= e(url('/groups/' . $gid . '/members/' . $m['id'] . '/remove')) ?>" data-confirm="<?= e(__('Remove from group?')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Remove')) ?></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php if ($manage): ?>
    <form method="post" action="<?= e(url('/groups/' . $gid . '/members')) ?>" class="form-row" style="margin-top:12px"><?= csrf_field() ?>
      <label><?= e(__('Add person')) ?><select name="person_id" required><?= options(people_options_scoped(), null, '—') ?></select></label>
      <?php if (can('groups.manage')): ?><label><?= e(__('Role')) ?><select name="member_role"><?= options(['member' => __('Member'), 'assistant' => __('Assistant leader'), 'leader' => __('Leader')]) ?></select></label><?php endif; ?>
      <button class="btn"><?= e(__('Add')) ?></button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($g['group_type'] !== 'small_group' || $positions): ?>
  <div class="card" id="positions">
    <h2><?= e(__('Serving positions')) ?></h2>
    <p class="muted small"><?= e(__('Positions are the roles people are rostered into. Required training and WWCC rules are checked automatically before anyone can be rostered.')) ?></p>
    <?php foreach ($positions as $po): ?>
      <details class="slot" style="display:block">
        <summary><strong><?= e($po['name']) ?></strong> · <?= e(__('%d approved volunteers', (int)$po['volunteers'])) ?><?= (int)$po['requires_wwcc'] ? ' <span class="badge badge-gold">WWCC</span>' : '' ?><?php foreach ($po['courses'] as $cid): ?> <span class="badge badge-blue"><?= e($courses[$cid] ?? '') ?></span><?php endforeach; ?></summary>
        <?php if (can('groups.manage') || can('rosters.manage')): ?>
        <form method="post" action="<?= e(url('/positions/' . $po['id'])) ?>" class="form" style="margin-top:10px"><?= csrf_field() ?>
          <?php partial('groups/position_fields', ['po' => $po, 'courses' => $courses]); ?>
          <button class="btn btn-sm btn-primary"><?= e(__('Save position')) ?></button>
        </form>
        <?php if (can('groups.manage')): ?><form method="post" action="<?= e(url('/positions/' . $po['id'] . '/delete')) ?>" data-confirm="<?= e(__('Delete this position?')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger"><?= e(__('Delete')) ?></button></form><?php endif; ?>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
    <?php if (can('groups.manage') || can('rosters.manage')): ?>
      <h2 class="h3"><?= e(__('Add a position')) ?></h2>
      <form method="post" action="<?= e(url('/groups/' . $gid . '/positions')) ?>" class="form"><?= csrf_field() ?>
        <?php partial('groups/position_fields', ['po' => ['requires_wwcc' => (int)$g['child_related'], 'max_per_month' => 3, 'courses' => []], 'courses' => $courses]); ?>
        <button class="btn btn-sm"><?= e(__('Add position')) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<div>
  <?php if ($rosters): ?>
  <div class="card"><h2 class="h3"><?= e(__('Upcoming rosters')) ?></h2><ul class="list-plain"><?php foreach ($rosters as $r): ?><li><a href="<?= e(url('/rosters/' . $r['id'])) ?>"><?= e(fmt_date($r['service_date'], 'D j M')) ?> — <?= e($r['title']) ?></a> <?= badge($r['status']) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if ($manage): ?>
  <div class="card"><h2 class="h3"><?= e(__('Meetings & attendance')) ?></h2>
    <form method="post" action="<?= e(url('/groups/' . $gid . '/meetings')) ?>" class="form"><?= csrf_field() ?>
      <div class="form-row"><label><?= e(__('Date')) ?><input type="date" name="meeting_date" value="<?= e(date('Y-m-d')) ?>"></label></div>
      <label><?= e(__('Topic')) ?><input name="topic"></label>
      <button class="btn btn-sm btn-primary"><?= e(__('Record attendance')) ?></button>
    </form>
    <ul class="list-plain small"><?php foreach ($meetings as $m): ?><li><a href="<?= e(url('/groups/' . $gid . '/meetings/' . $m['id'])) ?>"><?= e(fmt_date($m['meeting_date'])) ?></a> <?= e($m['topic']) ?> · <?= e(__('%d present', (int)$m['present'])) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>
  <?php if ($canEdit): ?><div class="card"><h2 class="h3"><?= e(__('Group settings')) ?></h2><?php partial('groups/form', ['g' => $g, 'embedded' => true, 'title' => '']); ?></div><?php endif; ?>
</div>
</div>
