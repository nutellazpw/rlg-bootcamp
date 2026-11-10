<div class="chairs">
    <?php foreach ($chairs as $no => $current): ?>
        <fieldset class="chair">
            <legend><?= e(__('Chair %d', (int) $no)) ?></legend>
            <div class="chair-opts">
                <?php foreach (ARMREST_OPTIONS as $k => $label): ?>
                    <label class="chair-opt">
                        <input type="radio" name="chair[<?= (int) $no ?>]" value="<?= e($k) ?>"<?= checked($current === $k) ?>>
                        <span class="chair-ico chair-ico-<?= e($k) ?>" aria-hidden="true">
                            <svg viewBox="0 0 40 32" width="40" height="32"><rect class="seat" x="11" y="6" width="18" height="20" rx="3"/><rect class="arm arm-l" x="3" y="10" width="5" height="14" rx="2"/><rect class="arm arm-r" x="32" y="10" width="5" height="14" rx="2"/></svg>
                        </span>
                        <span class="chair-label"><?= e(__($label)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>
</div>
