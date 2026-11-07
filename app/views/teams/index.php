<div class="page-head">
    <h1>Teams</h1>
    <?php if (can_manage()): ?><a class="btn" href="<?= e(url('/teams/new')) ?>">+ New team</a><?php endif; ?>
</div>
<div class="toolbar">
    <form method="get" action="<?= e(url('/teams')) ?>" class="search"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search teams"></form>
</div>
<section class="card card-flush">
    <?php if (!$rows): ?>
        <p class="muted empty-sm">No teams yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Team</th><th>Contact</th><th>Accounts</th><th>Next bootcamp</th><th class="num">Bootcamps</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $t): ?>
                <tr data-href="<?= e(url('/teams/' . $t['id'])) ?>">
                    <td><a href="<?= e(url('/teams/' . $t['id'])) ?>"><strong><?= e($t['name']) ?></strong></a> <span class="tag"><?= e($t['game']) ?></span></td>
                    <td><?= e($t['contact_name'] ?? '—') ?><?php if ($t['contact_phone']): ?> <span class="muted">· <?= e($t['contact_phone']) ?></span><?php endif; ?></td>
                    <td><?= $t['accounts'] ? (int) $t['accounts'] : '<span class="text-warn">none</span>' ?></td>
                    <td><?= e(fmt_date($t['next_start'])) ?></td>
                    <td class="num"><?= (int) $t['bootcamps'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
