<div class="mb-3 <?= $this->setting['hidden'] ? 'd-none' : '' ?>">
    <div class="question-option-general-setting-block">
        <?php if (in_array($this->setting['inputtype'], AdvancedSettingWidget::SINGLEINPUTTYPE) && !$this->setting['i18n']): ?>
            <label class="form-label" for="<?= CHtml::getIdByName($inputBaseName); ?>">
                <?= gT($this->setting['caption']) ?>
            </label>
        <?php else: ?>
            <strong class="form-label" id="label-<?= CHtml::getIdByName($inputBaseName); ?>">
                <?= gT($this->setting['caption']) ?>
            </strong>
        <?php endif; ?>
        <!-- TODO: Object method $setting->isLocalized(). -->
        <?php if ($this->setting['i18n']): ?>
            <i
                class="fa fa-globe"
                data-bs-toggle="tooltip"
                title="<?= gT("This setting is localized") ?>"
            ></i>
        <?php endif; ?>
        <?php if ($this->setting['help']): ?>
            <a
                role="button"
                data-toggle="collapse"
                href="#help-<?= CHtml::getIdByName($inputBaseName); ?>"
            ><i
                class="fa fa-question-circle text-info"
                data-bs-toggle="tooltip"
                title="<?= CHtml::encode(strip_tags($this->setting['help'])) ?>"
            > </i><span class="visually-hidden"><?= gT("Show help"); ?></span> </a>
            <div class="help-block collapse" id="help-<?= CHtml::getIdByName($inputBaseName); ?>" aria-expanded="false"><?= $this->setting['help']; ?></div>
        <?php endif; ?>
        <?= $content ?>
    </div>
</div>
<script>
function getNames(){
    return 'testing';
}
</script>
