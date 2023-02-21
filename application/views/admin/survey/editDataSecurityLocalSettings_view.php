<?php
/**
 * Edit the survey text elements of a survey for one given language
 * It is rendered from editLocalSettings_main_view.
 *
 * @var AdminController $this
 * @var Survey $oSurvey
 */

// DO NOT REMOVE This is for automated testing to validate we see that page
echo viewHelper::getViewTestTag('surveyTexts');

?>

<?php App()->getClientScript()->registerScript("editLocalSettings-view-variables",
    "
    var jsonUrl = '';
    var sAction = '';
    var sParameter = '';
    var sTargetQuestion = '';
    var sNoParametersDefined = '';
    var sAdminEmailAddressNeeded = '" . gT("If you are using token functions or notifications emails you need to set an administrator email address.", 'js') . "'
    var sURLParameters = '';
    var sAddParam = '';
",
    LSYii_ClientScript::POS_BEGIN
); ?>

<div id="editdatasecele-<?php echo $i; ?>" class="tab-pane fade <?php if ($i == 0) {
    echo "show active";
} ?> center-box">
<div class="row">
    <div class="col-12 col-xl-6">
        <!-- Privacy policy checkbox label -->
        <div class="mb-3">
            <label class="control-label"><?php eT("Privacy policy checkbox label:"); ?></label>
            <div class="">
                <?php echo CHtml::textField("dataseclabel_{$aSurveyLanguageSettings['surveyls_language']}",
                    $aSurveyLanguageSettings['surveyls_policy_notice_label'],
                    array('class' => 'form-control', 'size' => "80", 'id' => "dataseclabel_{$aSurveyLanguageSettings['surveyls_language']}")
                ); ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="well">
            <?=gT('If you want to specify a link to the privacy policy, set "Show privacy policy text with mandatory checkbox" to "Collapsible text" and use the placeholders {STARTPOLICYLINK} and {ENDPOLICYLINK} in the "Privacy policy checkbox label" field to define the link that opens the policy popup. If there is no placeholder given, there will be an appendix.')?>
            ) ?>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12 col-xl-6">
                <!-- Privacy policy message -->
        <div class="mb-3">
                    <label class=" control-label" for='datasec_<?php echo $aSurveyLanguageSettings['surveyls_language']; ?>'><?php eT("Privacy policy message:"); ?></label>
            <div class="">
                <div class="htmleditor input-group">
                    <?php echo CHtml::textArea("datasec_{$aSurveyLanguageSettings['surveyls_language']}",
                        $aSurveyLanguageSettings['surveyls_policy_notice'],
                        array('class' => 'form-control', 'cols' => '80', 'rows' => '20', 'id' => "datasec_{$aSurveyLanguageSettings['surveyls_language']}")
                    ); ?>
                    <?php echo getEditor("survey-datasec",
                        "datasec_" . $aSurveyLanguageSettings['surveyls_language'],
                        "[" . gT("Survey data policy:", "js") . "](" . $aSurveyLanguageSettings['surveyls_language'] . ")",
                        $surveyid,
                        '',
                        '',
                        $action
                    ); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
                <!-- Privacy policy error message -->
        <div class="mb-3">
                    <label class=" form-label" for='datasecerror_<?php echo $aSurveyLanguageSettings['surveyls_language']; ?>'><?php eT("Privacy policy error message:"); ?></label>
            <div class="">
                <div class="htmleditor input-group">
                    <?php echo CHtml::textArea("datasecerror_{$aSurveyLanguageSettings['surveyls_language']}",
                        $aSurveyLanguageSettings['surveyls_policy_error'],
                        array('class' => 'form-control', 'cols' => '80', 'rows' => '15', 'id' => "datasecerror_{$aSurveyLanguageSettings['surveyls_language']}")
                    ); ?>
                    <?php echo getEditor("survey-datasec-error",
                        "datasecerror_" . $aSurveyLanguageSettings['surveyls_language'],
                        "[" . gT("Survey data policy error:", "js") . "](" . $aSurveyLanguageSettings['surveyls_language'] . ")",
                        $surveyid,
                        '',
                        '',
                        $action
                    ); ?>
                </div>
            </div>
        </div>
    </div>
</div>
