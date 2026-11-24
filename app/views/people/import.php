<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('/people')) ?>"><?= e(__('Members & families')) ?></a></div><h1><?= e(__('Import people')) ?></h1></div></div>
<div class="split">
<div>
<div class="card"><h2 class="h3"><?= e(__('Import directly from Elvanto or Planning Center')) ?></h2>
<p class="small muted"><?= e(__('Brings across people, families and (optionally) groups in one step. Your key is used for this import only and is never saved. You can run it again safely — people already imported are recognised, not duplicated.')) ?></p>
<?php foreach (ApiImport::SOURCES as $src => $label): ?>
<details class="import-src"<?= $src === 'elvanto' ? ' open' : '' ?>><summary><strong><?= e($label) ?></strong></summary>
<form method="post" action="<?= e(url('/people/import/api')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="source" value="<?= e($src) ?>">
  <?php if ($src === 'elvanto'): ?>
    <label><?= e(__('Elvanto API key')) ?><input name="api_key" required autocomplete="off" spellcheck="false"><span class="help"><?= e(__('In Elvanto: Settings → Account Settings → API → Generate API key.')) ?></span></label>
  <?php else: ?>
    <div class="grid-2"><label><?= e(__('Application ID')) ?><input name="app_id" required autocomplete="off" spellcheck="false"></label><label><?= e(__('Secret')) ?><input name="secret" type="password" required autocomplete="off"></label></div>
    <p class="help"><?= e(__('Create a Personal Access Token at api.planningcenteronline.com (Developer → Personal Access Tokens) while signed in as an organisation administrator.')) ?></p>
  <?php endif; ?>
  <?= Campus::field() ?>
  <label class="check"><input type="checkbox" name="include_groups" value="1" checked> <?= e(__('Also import groups and their members')) ?></label>
  <label class="check"><input type="checkbox" name="update_existing" value="1"> <?= e(__('Update people already imported (overwrites changes made in Affreso)')) ?></label>
  <label class="check"><input type="checkbox" name="consent_confirmed" value="1"> <?= e(__('These people have consented to the church holding their details (records consent on import)')) ?></label>
  <button class="btn" name="mode" value="check"><?= e(__('Check connection')) ?></button>
  <button class="btn btn-primary" name="mode" value="import" data-confirm="<?= e(__('Start the import? Large churches can take a few minutes.')) ?>"><?= e(__('Import now')) ?></button>
</form></details>
<?php endforeach; ?>
<?php if (!empty($runs)): ?><h2 class="h3"><?= e(__('Recent imports')) ?></h2><ul class="list-plain small"><?php foreach ($runs as $r): $sum = json_decode((string)$r['summary'], true); ?>
  <li><?= e(fmt_datetime($r['created_at'])) ?> · <?= e(ApiImport::SOURCES[$r['source']] ?? $r['source']) ?> <?= badge($r['status'] === 'done' ? 'completed' : ($r['status'] === 'failed' ? 'failed' : 'in_progress')) ?>
  <?= is_array($sum) ? e(__('%d new, %d updated, %d groups', $sum['created'] ?? 0, $sum['updated'] ?? 0, $sum['groups'] ?? 0)) : e((string)$r['summary']) ?></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<div class="card"><h2 class="h3"><?= e(__('Import a CSV file')) ?></h2>
<form method="post" action="<?= e(url('/people/import')) ?>" enctype="multipart/form-data" class="form"><?= csrf_field() ?>
  <label><?= e(__('CSV file')) ?><input type="file" name="csv" accept=".csv,text/csv" required></label>
  <label class="check"><input type="checkbox" name="consent_confirmed" value="1"> <?= e(__('Everyone in this file has consented to the church holding their details (records consent on import)')) ?></label>
  <button class="btn btn-primary"><?= e(__('Import')) ?></button>
</form>
</div>
</div>
<div class="card"><h2 class="h3"><?= e(__('File format')) ?></h2>
<p class="small"><?= e(__('The first row must contain column names. Required: first_name, last_name. Optional:')) ?></p>
<p class="small"><code>preferred_name, email, mobile, birthdate, gender, address_line, suburb, state, postcode, membership_status, first_visit, joined_on, household, household_role</code></p>
<p class="small"><strong><?= e(__('Moving from another system?')) ?></strong> <?= e(__('Exports from Elvanto (People → Export), Planning Center (People → Lists → Export CSV), ChurchSuite and Breeze are recognised automatically — common column names such as "First Name", "Mobile Phone Number", "Birthday", "Home Address Street Line 1" and "Household Name" are mapped for you.')) ?></p>
<p class="small muted"><?= e(__('People with the same household value are grouped into one household. Dates can be YYYY-MM-DD or DD-MM-YYYY. Rows with a matching first name and email are skipped.')) ?></p>
</div>
</div>
