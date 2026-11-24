<h2 style="font-family:Georgia,serif"><?= e(setting('church_name')) ?></h2>
<p><?= e((string)setting('church_address')) ?><?= setting('church_abn') ? '<br>ABN ' . e((string)setting('church_abn')) : '' ?></p>
<h2 class="h3"><?= e(__('Statement of giving: 1 July %d – 30 June %d', $fy, $fy + 1)) ?></h2>
<p><?= e(trim($person['first_name'] . ' ' . $person['last_name'])) ?><br><?= e(trim(implode(', ', array_filter([$person['address_line'], $person['suburb'], trim(($person['state'] ?? '') . ' ' . ($person['postcode'] ?? ''))])))) ?></p>
<table cellpadding="6" style="width:100%;border-collapse:collapse"><thead><tr><th align="left"><?= e(__('Date')) ?></th><th align="left"><?= e(__('Receipt')) ?></th><th align="left"><?= e(__('Fund')) ?></th><th align="right"><?= e(__('Amount')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['received_on'] ?: $r['created_at'])) ?></td><td><?= e($r['receipt_no']) ?></td><td><?= e($r['fund']) ?></td><td align="right"><?= e(fmt_money((int)$r['amount_cents'], $r['currency'])) ?></td></tr><?php endforeach; ?>
<tr><td colspan="3"><strong><?= e(__('Total')) ?></strong></td><td align="right"><strong><?= e(fmt_money($total)) ?></strong></td></tr></tbody></table>
<p style="font-size:12px;color:#555"><?= e((string)setting('giving_dgr_statement')) ?></p>
<p><?= e(__('Thank you for your generous support of our ministry.')) ?></p>
