<script type='text/javascript'>
    var duplicatelabelcode='<?php eT('Error: You are trying to use duplicate label codes.', 'js'); ?>';
    var otherisreserved='<?php eT("Error: 'other' is a reserved keyword.", 'js'); ?>';
</script>

<!-- quick add popup -->
<?php $this->renderPartial("./labels/_labelviewquickadd_view", array()); ?>

<div class="col-sm-12 labels">
    <div class="pagetitle h3">
        <?php eT("Labels") ?>
        <?php if (isset($model->label_name)) : ?>
            - <?php echo CHtml::encode($model->label_name); ?>
        <?php endif; ?>
    </div>

    <!-- Left content -->
    <div class="col-sm-12 content-right text-center">

        <!-- tabs -->
        <ul class="nav nav-tabs">
            <?php foreach ($lslanguages as $i => $language) : ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $i === 0 ? 'active' : '' ?>" href='#neweditlblset<?= $i ?>' data-bs-toggle="tab">
                        <?php echo getLanguageNameFromCode($language, false); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- FORM -->
        <?php echo CHtml::form(["admin/labels/sa/process"], 'post', ['id' => 'mainform']); ?>
        <input type='hidden' name='lid' value='<?php echo $lid ?>'/>
        <input type='hidden' name='action' value='modlabelsetanswers'/>

        <!-- tab content -->
        <?php $this->renderPartial("./labels/_labelviewtabcontent_view", ['lslanguages' => $lslanguages, 'results' => $results, 'action' => $action]); ?>
        <?php echo CHtml::endForm() ?>
    </div>

    <!-- Right content -->
    <div class="col-sm-4">
        <?php if (Permission::model()->hasGlobalPermission('labelsets', 'update')) { ?>
            <?php $this->renderPartial("./labels/_labelviewrightcontent_view", ['lid' => $lid]); ?>
        <?php }; ?>
    </div>
</div>
