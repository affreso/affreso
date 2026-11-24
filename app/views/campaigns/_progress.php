<?php /** Expects $goal, $raised, $pledged (cents). */ $g = max(1, (int)$goal); $rp = min(100, (int)round($raised / $g * 100)); $pp = min(100, (int)round($pledged / $g * 100)); ?>
<div class="campaign-bar" role="img" aria-label="<?= e(__('%s given and %s pledged of a %s goal', fmt_money((int)$raised), fmt_money((int)$pledged), fmt_money((int)$goal))) ?>">
  <span class="pledged" style="width:<?= max($rp, $pp) ?>%"></span><span class="given" style="width:<?= $rp ?>%"></span>
</div>
<div class="campaign-legend"><span><i class="given"></i><?= e(__('Given %s', fmt_money((int)$raised))) ?></span><span><i class="pledged"></i><?= e(__('Pledged %s', fmt_money((int)$pledged))) ?></span><span><?= e(__('Goal %s', fmt_money((int)$goal))) ?></span></div>
