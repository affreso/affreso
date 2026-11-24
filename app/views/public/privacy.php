<?php $church = setting('church_name'); $custom = trim((string)setting('privacy_policy_text', '')); ?>
<div class="card" style="max-width:820px">
<h1><?= e(__('Privacy policy')) ?></h1>
<?php if ($custom !== ''): ?><div class="lesson-body"><?= e($custom) ?></div><?php else: ?>
<p class="callout small"><?= e(__('This is a starting template. Your church should review and adapt it (Administration → Settings → Privacy) before going live.')) ?></p>
<p><?= e(__('%s ("we") respects your privacy. We handle personal information in line with the Australian Privacy Principles where they apply to us.', $church)) ?></p>
<h2><?= e(__('What we collect')) ?></h2><p><?= e(__('Contact details, household and family details, church involvement (groups, serving, training, attendance), giving records, safeguarding clearances for people who work with children, and — only where you share it with pastoral staff — notes about pastoral care.')) ?></p>
<h2><?= e(__('Why we collect it')) ?></h2><p><?= e(__('To care for our church community, organise ministries and volunteers, meet child-safety obligations, issue giving receipts and communicate with you.')) ?></p>
<h2><?= e(__('Who can see it')) ?></h2><p><?= e(__('Access is limited by role. Confidential pastoral notes and safeguarding numbers are encrypted and only visible to authorised people. The directory only lists people who opt in.')) ?></p>
<h2><?= e(__('Your choices')) ?></h2><p><?= e(__('You can update your details, change your consent and notification choices, download a copy of your information, or ask for correction or deletion from My Affreso → Privacy & consent.')) ?></p>
<h2><?= e(__('Keeping and deleting information')) ?></h2><p><?= e(__('We keep information only as long as needed. Some records, especially child-safety records, may need to be kept for long periods by law.')) ?></p>
<h2><?= e(__('Data breaches')) ?></h2><p><?= e(__('If a breach is likely to cause serious harm, we will assess it promptly and notify affected people and, where required, the Office of the Australian Information Commissioner.')) ?></p>
<?php endif; ?>
<h2><?= e(__('Contact')) ?></h2><p><?= e(__('Privacy officer: %s — %s', setting('privacy_officer'), setting('privacy_contact'))) ?></p>
</div>
