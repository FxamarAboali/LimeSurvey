<?php
/**
* This view generate the presentation tab inside global settings.
*
*
*/
?>
<div class="container-fluid">
    <div class="ls-flex-column ls-space padding left-5 right-35 col-md-7">
        <div class="form-group">
            <label class=" control-label"  for='repeatheadings'><?php eT("Repeating headings in array questions every X subquestions:"); ?></label>
            <div class="">
                <input class="form-control"  id='repeatheadings' name='repeatheadings' value='<?php echo getGlobalSetting('repeatheadings'); ?>' size='4' maxlength='4' />
            </div>
        </div>

        <div class="form-group">
            <label class=" control-label"  for='pdffontsize'><?php eT("Font size of PDFs:"); ?></label>
            <div class="">
                <input class="form-control"  type='text' id='pdffontsize' name='pdffontsize' value="<?php echo htmlspecialchars(getGlobalSetting('pdffontsize')); ?>" />
            </div>
        </div>


        <div class="form-group">
            <label class=" control-label"  for='pdflogowidth'><?php eT("Width of PDF header logo:"); ?></label>
            <div class="">
                <input class="form-control"  type='text' size='5' id='pdflogowidth' name='pdflogowidth' value="<?php echo htmlspecialchars(getGlobalSetting('pdflogowidth')); ?>" />

            </div>
        </div>

        <div class="form-group">
            <label class=" control-label"  for='pdfheadertitle'><?php eT("PDF header title (if empty, site name will be used):"); ?></label>
            <div class="">
                <input class="form-control"  type='text' id='pdfheadertitle' size='50' maxlength='256' name='pdfheadertitle' value="<?php echo htmlspecialchars(getGlobalSetting('pdfheadertitle')); ?>" />

            </div>
        </div>

        <div class="form-group">
            <label class=" control-label"  for='pdfheaderstring'><?php eT("PDF header string (if empty, survey name will be used):"); ?></label>
            <div class="">
                <input class="form-control"  type='text' id='pdfheaderstring' size='50' maxlength='256' name='pdfheaderstring' value="<?php echo htmlspecialchars(getGlobalSetting('pdfheaderstring')); ?>" />

            </div>
        </div>
    </div>
    
    <div class="ls-flex-column ls-space padding left-5 right-5 col-md-5">
        <div class="form-group">
            <label class=" control-label"  for='pdfshowheader'><?php eT("Show header in answers export PDFs:") ; ?></label>
            <div class="">
                <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                    'name' => 'pdfshowheader',
                    'id'=>'pdfshowheader',
                    'value' => getGlobalSetting('pdfshowheader')=='Y'?'1':0,
                    'onLabel'=>gT('On'),
                    'offLabel' => gT('Off')));
                ?>
            </div>
        </div>

        <div class="form-group">
            <label class=" control-label"  for='bPdfQuestionFill'><?php eT("Add gray background to questions in PDF:"); ?></label>
            <div class="">
                <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                    'name' => 'bPdfQuestionFill',
                    'id'=>'bPdfQuestionFill',
                    'value' => getGlobalSetting('bPdfQuestionFill'),
                    'onLabel'=>gT('On'),
                    'offLabel' => gT('Off')));
                ?>
            </div>
        </div>

        <div class="form-group">
            <label class=" control-label"  for='bPdfQuestionBold'><?php eT("PDF questions in bold:"); ?></label>
            <div class="">
                <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                    'name' => 'bPdfQuestionBold',
                    'id'=>'bPdfQuestionBold',
                    'value' => getGlobalSetting('bPdfQuestionBold'),
                    'onLabel'=>gT('On'),
                    'offLabel' => gT('Off')));
                ?>
            </div>
        </div>

        <div class="form-group">
            <label class=" control-label"  for='bPdfQuestionBorder'><?php eT("Borders around questions in PDF:"); ?></label>
            <div class="">
                <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                    'name' => 'bPdfQuestionBorder',
                    'id'=>'bPdfQuestionBorder',
                    'value' => getGlobalSetting('bPdfQuestionBorder'),
                    'onLabel'=>gT('On'),
                    'offLabel' => gT('Off')));
                ?>
            </div>
        </div>

        <div class="form-group">
            <label class=" control-label"  for='bPdfResponseBorder'><?php eT("Borders around responses in PDF:"); ?></label>
            <div class="">
                <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                    'name' => 'bPdfResponseBorder',
                    'id'=>'bPdfResponseBorder',
                    'value' => getGlobalSetting('bPdfResponseBorder'),
                    'onLabel'=>gT('On'),
                    'offLabel' => gT('Off')));
                ?>
            </div>
        </div>

    </div>
</div>


<?php if (Yii::app()->getConfig("demoMode")==true):?>
<p><?php eT("Note: Demo mode is activated. Marked (*) settings can't be changed."); ?></p>
<?php endif; ?>

