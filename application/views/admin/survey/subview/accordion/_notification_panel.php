<?php
/**
 * Notificatin panel
 * @var AdminController $this
 * @var Survey $oSurvey
 */
?>
<script type="text/javascript">
    var jsonUrl = '';
    var sAction = '';
    var sParameter = '';
    var sTargetQuestion = '';
    var sNoParametersDefined = '';
    var sAdminEmailAddressNeeded = '<?php  eT("If you are using token functions or notifications emails you need to set an administrator email address.",'js'); ?>'
    var sURLParameters = '';
    var sAddParam = '';
</script>
<!-- Notification panel -->
<div id='notification'  class="container-fluid">

    <div class="col-sm-12 col-md-6">
        <!-- Date Stamp -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='datestamp'><?php  eT("Date stamp:"); ?></label>
            <div class="col-sm-7">
                <?php if ($oSurvey->isActive) { ?>
                    <?php if ($oSurvey->datestamp != "Y") {
                            eT("Responses will not be date stamped.");
                        } else {
                            eT("Responses will be date stamped.");
                    } ?>
                    <span class='annotation'> <?php  eT("Cannot be changed"); ?></span>
                    <?php echo CHtml::hiddenField('datestamp',$oSurvey->datestamp); // Maybe use a readonly dropdown? ?>
                    <?php }
                    else {
                        $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                        'name' => 'datestamp',
                        'value'=> $oSurvey->isDateStamp,
                        'onLabel'=>gT('On'),
                        'offLabel'=>gT('Off'),
                        'events'=>array('switchChange.bootstrapSwitch'=>"function(event,state){
                            if ($('#anonymized').is(':checked') == true) {
                            $('#datestampModal').modal();
                            }
                        }")
                        ));
                        $this->widget('bootstrap.widgets.TbModal', array(
                            'id' => 'datestampModal',
                            'header' => gt('Warning','unescaped'),
                            'content' => '<p>'.gT("If the option -Anonymized responses- is activated only a dummy date stamp (1980-01-01) will be used for all responses to ensure the anonymity of your participants.").'</p>',
                            'footer' => TbHtml::button('Close', array('data-dismiss' => 'modal'))
                        ));
                        }
                    ?>
            </div>
        </div>
        <!-- Save IP Address -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='ipaddr'><?php  eT("Save IP address:"); ?></label>
            <div class="col-sm-7">
                <?php if ($oSurvey->isActive) {
                    if ($oSurvey->ipaddr!= "Y") {
                        eT("Responses will not have the IP address logged.");
                    } else {
                        eT("Responses will have the IP address logged");
                    } ?>
                    <span class='annotation'> <?php  eT("Cannot be changed"); ?></span>
                    <?php echo CHtml::hiddenField('ipaddr',$oSurvey->ipaddr);
                } else {
                    $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                        'name' => 'ipaddr',
                        'value'=> $oSurvey->isIpAddr,
                        'onLabel'=>gT('On'),
                        'offLabel'=>gT('Off')
                    ));
                } ?>
            </div>
        </div>

        <!-- Save referrer URL -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='refurl'><?php  eT("Save referrer URL:"); ?></label>
            <div class="col-sm-7">
                <?php if ($oSurvey->isActive) { ?>
                    <?php  if ($oSurvey->refurl != "Y") {
                            eT("Responses will not have their referring URL logged.");
                        } else {
                            eT("Responses will have their referring URL logged.");
                    } ?>
                    <span class='annotation'> <?php  eT("Cannot be changed"); ?></span>
                    <?php echo CHtml::hiddenField('refurl',$oSurvey->refurl);?>
                    <?php } else {
                        $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                        'name' => 'refurl',
                        'value'=> $oSurvey->isRefUrl,
                        'onLabel'=>gT('On'),
                        'offLabel'=>gT('Off')
                        ));
                } ?>
            </div>
        </div>

        <!-- Save timings -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='savetimings'><?php  eT("Save timings:"); ?></label>
            <div class="col-sm-7">
                <?php if ($oSurvey->isActive): ?>
                    <?php if ($oSurvey->savetimings != "Y"): ?>
                        <?php  eT("Timings will not be saved."); ?>
                    <?php else: ?>
                        <?php  eT("Timings will be saved."); ?>
                        <span class='annotation'> <?php  eT("Cannot be changed"); ?></span>
                        <?php echo CHtml::hiddenField('savetimings',$oSurvey->savetimings);  // Maybe use a readonly dropdown? ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php
                        $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                            'name' => 'savetimings',
                            'value'=> $oSurvey->isSaveTimings,
                            'onLabel'=>gT('On'),
                            'offLabel'=>gT('Off')
                        ));
                    ?>
                <?php endif;?>
            </div>
        </div>

        <!-- Enable assessment mode -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='assessments'><?php  eT("Enable assessment mode:"); ?></label>
            <div class="col-sm-7"><?php
                $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                    'name' => 'assessments',
                    'value'=> $oSurvey->isAssessments,
                    'onLabel'=>gT('On'),
                    'offLabel'=>gT('Off')
                ));
            ?></div>
        </div>

        <!-- Participant may save and resume  -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='allowsave'><?php  eT("Participant may save and resume later:"); ?></label>
            <div class="col-sm-7">
            <?php
                $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                    'name' => 'allowsave',
                    'value'=> $oSurvey->isAllowSave,
                    'onLabel'=>gT('On'),
                    'offLabel'=>gT('Off')
                ));
            ?>
            </div>
        </div>
    </div>
    <div class="col-sm-12 col-md-6">
        <!-- email basic to -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='emailnotificationto'><?php  eT("Send basic admin notification email to:"); ?></label>
            <div class="col-sm-7">
                <?php echo CHtml::textField('emailnotificationto',$oSurvey->emailnotificationto,array('size'=>70, 'class'=>"form-control")); ?>
            </div>
        </div>

        <!-- email detail to  -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='emailresponseto'><?php  eT("Send detailed admin notification email to:"); ?></label>
            <div class="col-sm-7">
                <?php echo CHtml::textField('emailresponseto',$oSurvey->emailresponseto,array('size'=>70, 'class'=>"form-control")) ?>
            </div>
        </div>

        <!-- GoogleAnalytics settings to be used -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for="googleanalyticsapikeysetting">
                <?php echo gT('Google Analytics settings:');?>
            </label>
            <div class="col-sm-7">
                <?php $this->widget('yiiwheels.widgets.buttongroup.WhButtonGroup', array(
                    'name' => 'googleanalyticsapikeysetting',
                    'value'=>  $oSurvey->googleanalyticsapikeysetting,
                    'selectOptions'=>array(
                        "N"=>gT("None",'unescaped'),
                        "Y"=>gT("Use settings below",'unescaped'),
                        "G"=>gT("Use global settings",'unescaped')
                    )
                ));?>
            </div>
        </div>
        <!-- Google Analytics -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='googleanalyticsapikey'><?php  eT("Google Analytics Tracking ID:"); ?></label>
            <div class="col-sm-7">
                <?php echo CHtml::textField('googleanalyticsapikey',$oSurvey->googleanalyticsapikey,array('size'=>20), array('class'=>"form-control")); ?>
            </div>
        </div>
        <!-- Google Analytics style -->
        <div class="form-group">
            <label class="col-sm-5 control-label" for='googleanalyticsstyle'><?php  eT("Google Analytics style:"); ?></label>
            <div class="col-sm-7">
            <?php $this->widget('yiiwheels.widgets.buttongroup.WhButtonGroup', array(
                    'name' => 'googleanalyticsstyle',
                    'value'=> $oSurvey->googleanalyticsstyle ,
                    'selectOptions'=>array(
                    "0"=>gT("Off",'unescaped'),
                    "1"=>gT("Default",'unescaped'),
                    "2"=>gT("Survey-SID/Group",'unescaped'))
                    ));?>
            </div>
        </div>
    </div>
</div>
<?php App()->getClientScript()->registerScriptFile( App()->getConfig('adminscripts') . 'survey_edit_notificationpanel.js'); ?>
