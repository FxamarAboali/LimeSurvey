<?php

/**
 * Statistic main view
 * @var AdminController $this
 * @var Survey $oSurvey
 */

// DO NOT REMOVE This is for automated testing to validate we see that page
echo viewHelper::getViewTestTag('statisticsIndex');

?>
<!-- Javascript variables  -->
<?php $this->renderPartial('/admin/export/statistics_subviews/_statistics_view_scripts', array('sStatisticsLanguage' => $sStatisticsLanguage, 'surveyid' => $surveyid, 'showtextinline' => $showtextinline)); ?>
<?php echo CHtml::form(array("admin/statistics/sa/index/surveyid/{$surveyid}/"), 'post', array('name' => 'generate-statistics', 'class' => '', 'id' => 'generate-statistics')); ?>
<div id='statisticsview' class='side-body <?php echo getSideBodyClass(false); ?>'>
    <div class="h1 d-print-block d-none text-center"><?php echo flattenText($oSurvey->defaultlanguage->surveyls_title, 1); ?></div>
    <div class="row d-print-none">
        <div class="col-12">
            <div class="col-lg-3 text-start">
                <h4 class="d-print-none">
                    <span class="ri-bar-chart-fill"></span> &nbsp;&nbsp;&nbsp;
                    <?php eT("Statistics"); ?>
                </h4>
            </div>
        </div>
    </div>


<?php
    $submitted = ($filterchoice_state != '' || !empty($summary));
    $this->widget('ext.AccordianWidget.AccordianWidget', [
        'id' => 'filters',
        'class' => '',
        'items' => [
            [
                'id' => 'general-filters-item',
                'title' => 'General filters',
                'content' => $this->renderPartial(
                    '/admin/export/statistics_subviews/_general_filters',
                    array(
                        'error' => $error,
                        'surveyid' => $surveyid,
                        'selectshow' => $selectshow,
                        'selecthide' => $selecthide,
                        'selectinc' => $selectinc,
                        'survlangs' => $survlangs,
                        'sStatisticsLanguage' => $sStatisticsLanguage,
                        'datestamp' => $datestamp,
                        'dateformatdetails' => $dateformatdetails,
                        'submitted' => $submitted
                    ),
                    true
                )
            ],
            [
                'id' => 'response-filters-item',
                'title' => 'Response filters',
                'content' => $this->renderPartial(
                    '/admin/export/statistics_subviews/_response_filters',
                    array(
                        'filterchoice_state' => $filterchoice_state,
                        'filters' => $filters,
                        'aGroups' => $aGroups,
                        'surveyid' => $surveyid,
                        'result' => $result,
                        'fresults' => $fresults,
                        'summary' => $summary,
                        'dateformatdetails' => $dateformatdetails,
                        'oStatisticsHelper' => $oStatisticsHelper,
                        'language' => $language,
                        'dshresults' => $dshresults,
                        'dshresults2' => $dshresults2,
                        'submitted' => $submitted
                    ),
                    true
                )
            ],
            [
                'id' => 'statisticsoutput-item',
                'title' => 'Statistics',
                'content' => $this->renderPartial(
                    '/admin/export/statistics_subviews/_statistics_output',
                    array(
                        'output' => $output
                    ),
                    true
                )
            ]
        ]
    ]);
?>



    <div class="row d-print-none">
        <div class="col-12 content-left">
            <button type="button" id="statisticsExportImages" class="btn btn-info">
                <?= gT('Export images'); ?>
            </button>
            <p><?php eT('Make sure all images on this screen are loaded before clicking on the button.'); ?></p>
        </div>
    </div>
</div>
<?php
App()->getClientScript()->registerScript('StatisticsViewBSSwitcher', "
LS.renderBootstrapSwitch();
", LSYii_ClientScript::POS_POSTSCRIPT);
?>
