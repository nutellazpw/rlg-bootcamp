<?php
$prev = date('Y-m', strtotime('-1 month', $month));
$next = date('Y-m', strtotime('+1 month', $month));
$q = $hotelFilter ? ['hotel' => $hotelFilter] : [];
?>
<div class="page-head">
    <div>
        <h1><?= e(__('Calendar')) ?></h1>
        <p class="muted"><?= e(mb_ucfirst(ldate('F Y', $month))) ?></p>
    </div>
    <div class="head-actions">
        <form method="get" action="<?= e(url('/calendar')) ?>" class="inline-form">
            <input type="hidden" name="m" value="<?= e(date('Y-m', $month)) ?>">
            <select name="hotel" onchange="this.form.submit()">
                <option value=""><?= e(__('All hotels')) ?></option>
                <?php foreach ($hotels as $h): ?><option value="<?= (int) $h['id'] ?>"<?= selected($h['id'], $hotelFilter) ?>><?= e($h['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/calendar', ['m' => $prev] + $q)) ?>">← <?= e(ldate('M', strtotime($prev . '-01'))) ?></a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/calendar', $q)) ?>"><?= e(__('Today')) ?></a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/calendar', ['m' => $next] + $q)) ?>"><?= e(ldate('M', strtotime($next . '-01'))) ?> →</a>
    </div>
</div>

<div class="legend">
    <?php foreach ($hotels as $h): ?>
        <span class="legend-item"><i style="background:<?= e($colors[(int) $h['id']]) ?>"></i><?= e($h['name']) ?></span>
    <?php endforeach; ?>
</div>

<div class="calendar">
    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="cal-dow"><?= e(mb_ucfirst(ldate('D', strtotime('next ' . $d)))) ?></div><?php endforeach; ?>
    <?php for ($day = $gridStart; $day <= $gridEnd; $day = strtotime('+1 day', $day)):
        $ds = date('Y-m-d', $day);
        $inMonth = date('m', $day) === date('m', $month);
        $isToday = $ds === date('Y-m-d');
        ?>
        <div class="cal-day<?= $inMonth ? '' : ' out' ?><?= $isToday ? ' today' : '' ?>">
            <div class="cal-date"><?= date('j', $day) ?></div>
            <?php foreach ($bootcamps as $b):
                if ($b['start_date'] > $ds || $b['end_date'] < $ds) {
                    continue;
                }
                $isStart = $b['start_date'] === $ds;
                $isEnd = $b['end_date'] === $ds;
                ?>
                <a class="cal-ev<?= $isStart ? ' start' : '' ?><?= $isEnd ? ' end' : '' ?>" style="--c:<?= e($colors[(int) $b['hotel_id']] ?? '#FF6700') ?>" href="<?= e(url('/bootcamps/' . $b['id'])) ?>" title="<?= e($b['team_name'] . ' · ' . $b['hotel_name'] . ($b['room'] ? ' · ' . $b['room'] : '')) ?>">
                    <?= e($b['team_name']) ?><?php if ($b['room'] && ($isStart || date('N', $day) === '1')): ?> <span>· <?= e($b['room']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endfor; ?>
</div>
