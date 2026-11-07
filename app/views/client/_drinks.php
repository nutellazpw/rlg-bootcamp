<div class="drink-grid">
    <?php foreach ($drinks as $d): ?>
        <label class="drink">
            <input type="checkbox" name="drinks[]" value="<?= (int) $d['id'] ?>"<?= checked(in_array((int) $d['id'], $drinkIds, true)) ?>>
            <span><?= e($d['name']) ?></span>
        </label>
    <?php endforeach; ?>
    <?php if (!$drinks): ?><p class="muted">No drinks on the list yet.</p><?php endif; ?>
</div>
