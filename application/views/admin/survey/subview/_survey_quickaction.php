<?php

/**
 * Survey default view
 * @var AdminController $this
 * @var Survey $oSurvey
 */
$count        = 0;
$templates    = Template::getTemplateListWithPreviews();
$surveylocale = Permission::model()->hasSurveyPermission($oSurvey->sid, 'surveylocale', 'read');
// EDIT SURVEY SETTINGS BUTTON
$surveysettings = Permission::model()->hasSurveyPermission($oSurvey->sid, 'surveysettings', 'read');
$respstatsread  = Permission::model()->hasSurveyPermission($oSurvey->sid, 'responses', 'read')
    || Permission::model()->hasSurveyPermission($oSurvey->sid, 'statistics', 'read')
    || Permission::model()->hasSurveyPermission($oSurvey->sid, 'responses', 'export');
$groups_count   = count($oSurvey->groups);


?>

<!-- Quick Actions -->

<div class="card card-primary h-100">
    <div id="survey-action-title" class="card-header">
        <div class="row">
            <div class="col-2 col-md-1">
                <button id="survey-action-chevron" class="btn btn-outline-secondary btn-tiny" data-active="<?= $quickactionstate ?>" data-url="<?php echo Yii::app()->urlManager->createUrl("surveyAdministration/toggleQuickAction/"); ?>">
                    <i class="<?= ($quickactionstate > 0 ?  'ri-arrow-up-s-fill' : 'ri-arrow-down-s-fill') ?>"></i>
                </button>
            </div>
            <div class="col-10 col-md-11 h4">
                <?php eT('Survey quick actions'); ?>
            </div>
        </div>
    </div>
    <div class="card-body" style="display:<?= ($quickactionstate > 0 ? 'block' : 'none') ?>" id="survey-action-container">
        <div class="row welcome survey-action">
            <div class="col-12 content-right">
                <!-- Alerts, infos... -->
                <div class="row">
                    <div class="col-12">
                        <!-- While survey is activated, you can't add or remove group or question -->
                        <?php if ($oSurvey->isActive) : ?>
                            <div class="alert alert-warning alert-dismissible" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                <strong><?php eT('Warning!'); ?></strong> <?php eT("While the survey is activated, you can't add or remove a group or question."); ?>
                            </div>

                        <?php elseif (!$groups_count > 0) : ?>

                            <!-- To add questions, first, you must add a question group -->
                            <div class="alert alert-warning alert-dismissible" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                <strong><?php eT('Warning!'); ?></strong> <?php eT('Before you can add questions you must add a question group first.'); ?>
                            </div>

                            <!-- If you want a single page survey, just add a single group, and switch on "Show questions group by group -->
                            <div class="alert alert-info alert-dismissible" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                <span class="ri-information-line"></span>&nbsp;&nbsp;&nbsp;
                                <?php eT('Set below if your questions are shown one at a time, group by group or all on one page.'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Boxes and template -->
                <div class="row">

                    <!-- Boxes -->
                    <div class="col-md-6">

                        <!-- Switch : Show questions group by group -->
                        <?php $switchvalue = ($oSurvey->format == 'G') ? 1 : 0; ?>
                        <?php if (Permission::model()->hasSurveyPermission($oSurvey->sid, 'surveycontent', 'update')) : ?>
                            <div class="row">
                                <div class="col-12">
                                    <label for="switch"><?php eT('Format:'); ?></label>
                                    <div id='switchchangeformat' class="btn-group" role="group">
                                        <button id='switch' type="button" data-value='S' class="btn btn-outline-secondary <?php if ($oSurvey->format == 'S') {
                                                                                                                                echo 'active';
                                                                                                                            } ?>"><?php eT('Question by question'); ?></button>
                                        <button type="button" data-value='G' class="btn btn-outline-secondary <?php if ($oSurvey->format == 'G') {
                                                                                                                    echo 'active';
                                                                                                                } ?>"><?php eT('Group by group'); ?></button>
                                        <button type="button" data-value='A' class="btn btn-outline-secondary <?php if ($oSurvey->format == 'A') {
                                                                                                                    echo 'active';
                                                                                                                } ?>"><?php eT('All in one'); ?></button>
                                    </div>
                                    <input type="hidden" id="switch-url" data-url="<?php echo $this->createUrl("surveyAdministration/changeFormat/surveyid/" . $oSurvey->sid); ?>" />
                                    <br /><br />

                                </div>
                            </div>
                        <?php endif; ?>


                        <!-- Add Question / group -->
                        <div class="row row-eq-height">
                            <!-- Survey active, so it's impossible to add new group/question -->
                            <?php if ($oSurvey->isActive) : ?>

                                <!-- Can't add new group to survey  -->
                                <div class="col-md-6">
                                    <div class="card disabled card-primary h-100" id="panel-1">
                                        <div class="card-header ">
                                            <div class=""><?php eT('Add group'); ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="card-body-ico">
                                                <a href="#" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT("This survey is currently active."); ?>" style="display: inline-block" data-bs-toggle="tooltip">
                                                    <span class="ri-add-circle-fill" style="font-size: 3em;"></span>
                                                    <span class="visually-hidden"><?php eT('Add new group'); ?></span>
                                                </a>
                                            </div>
                                            <div class="card-body-link">
                                                <p><a href="#"><?php eT('Add new group'); ?></a></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Can't add a new question -->
                                <div class="col-md-6">
                                    <div class="card disabled card-primary h-100" id="panel-2">
                                        <div class="card-header ">
                                            <div class="disabled"><?php eT('Add question'); ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="card-body-ico">
                                                <a href="#" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT("This survey is currently active."); ?>" style="display: inline-block" data-bs-toggle="tooltip">
                                                    <span class="ri-add-circle-fill" style="font-size: 3em;"></span>
                                                    <span class="visually-hidden"><?php eT('Add new question'); ?></span>
                                                </a>
                                            </div>
                                            <div class="card-body-link">
                                                <p>
                                                    <a href="#" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT("This survey is currently active."); ?>" style="display: inline-block" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT('Survey cannot be activated. Either you have no permission or there are no questions.'); ?>">
                                                        <?php eT("Add new question"); ?>
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- survey is not active, and user has permissions, so buttons are shown and active -->
                            <?php elseif (Permission::model()->hasSurveyPermission($oSurvey->sid, 'surveycontent', 'create')) : ?>

                                <!-- Add group -->
                                <div class="col-md-6">
                                    <div class="card card-clickable card-primary h-100" id="panel-1" data-url="<?php echo $this->createUrl("questionGroupsAdministration/add/surveyid/" . $oSurvey->sid); ?>">
                                        <div class="card-header ">
                                            <div class=""><?php eT('Add group'); ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="card-body-ico">
                                                <a href="<?php echo $this->createUrl("questionGroupsAdministration/add/surveyid/" . $oSurvey->sid); ?>">
                                                    <span class="ri-add-circle-fill" style="font-size: 3em;"></span>
                                                    <span class="visually-hidden"><?php eT('Add new group'); ?></span>
                                                </a>
                                            </div>
                                            <div class="card-body-link">
                                                <p><a href="<?php echo $this->createUrl("questionGroupsAdministration/add/surveyid/" . $oSurvey->sid); ?>"><?php eT('Add new group'); ?></a></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Survey has no group, so can't add a question -->
                                <?php if (!$groups_count > 0) : ?>
                                    <div class="col-md-6">
                                        <div class="card disabled card-primary h-100" id="panel-2">
                                            <div class="card-header ">
                                                <div class="disabled"><?php eT('Add question'); ?></div>
                                            </div>
                                            <div class="card-body">
                                                <div class="card-body-ico">
                                                    <a href="#" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT("You must first create a question group."); ?>" style="display: inline-block" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT('Survey cannot be activated. Either you have no permission or there are no questions.'); ?>">
                                                        <span class="ri-add-circle-fill" style="font-size: 3em;"></span>
                                                        <span class="visually-hidden"><?php eT("You must first create a question group."); ?></span>
                                                    </a>
                                                </div>
                                                <div class="card-body-link">
                                                    <p>
                                                        <a href="#" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT("You must first create a question group."); ?>" style="display: inline-block" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT('Survey cannot be activated. Either you have no permission or there are no questions.'); ?>">
                                                            <?php eT("Add new question"); ?>
                                                            <span class="visually-hidden"><?php eT("Add new question"); ?></span>
                                                        </a>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Survey has a group, so can add a question -->
                                <?php else : ?>
                                    <div class="col-md-6">
                                        <div class="card card-clickable card-primary h-100" id="panel-2" data-url="<?php echo $this->createUrl("questionAdministration/view/surveyid/" . $oSurvey->sid); ?>">
                                            <div class="card-header ">
                                                <div class=""><?php eT('Add question'); ?></div>
                                            </div>
                                            <div class="card-body">
                                                <div class="card-body-ico">
                                                    <a href="<?php echo $this->createUrl("questionAdministration/view/surveyid/" . $oSurvey->sid); ?>">
                                                        <span class="ri-add-circle-fill" style="font-size: 3em;"></span>
                                                        <span class="visually-hidden"><?php eT('Add question'); ?></span>
                                                    </a>
                                                </div>
                                                <div class="card-body-link">
                                                    <p><a href="<?php echo $this->createUrl("questionAdministration/view/surveyid/" . $oSurvey->sid); ?>"><?php eT("Add new question"); ?></a></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="row row-eq-height">
                            <div class="col-md-6">


                                <!-- Edit text elements and general settings -->
                                <?php if ($surveylocale && $surveysettings) : ?>
                                    <div class="card card-clickable card-primary h-100" id="panel-3" data-url="<?php echo $this->createUrl("surveyAdministration/editlocalsettings/surveyid/" . $oSurvey->sid); ?>">
                                        <div class="card-header ">
                                            <div class=""><?php eT('Edit text elements and general settings'); ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="card-body-ico">
                                                <a href="<?php echo $this->createUrl("surveyAdministration/editlocalsettings/surveyid/" . $oSurvey->sid); ?>">
                                                    <span class="ri-pencil-fill" style="font-size: 3em;"></span>
                                                    <span class="visually-hidden"><?php eT('Edit text elements and general settings'); ?></span>
                                                </a>
                                            </div>
                                            <div class="card-body-link">
                                                <p><a href="<?php echo $this->createUrl(
                                                                "surveyAdministration/editlocalsettings/surveyid/" . $oSurvey->sid
                                                            ); ?>"><?php eT('Edit text elements and general settings'); ?></a></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <div class="card disabled card-primary h-100" id="panel-3">
                                        <div class="card-header ">
                                            <div class=""><?php eT('Edit text elements and general settings'); ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="card-body-ico">
                                                <a href="#" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php eT("We are sorry but you don't have permissions to do this."); ?>" style="display: inline-block" data-bs-toggle="tooltip">
                                                    <span class="ri-pencil-fill" style="font-size: 3em;"></span>
                                                    <span class="visually-hidden"><?php eT('Edit text elements and general settings'); ?></span>
                                                </a>
                                            </div>
                                            <div class="card-body-link">
                                                <p><a href="#"><?php eT('Edit text elements and general settings'); ?></a></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>


                            <!-- Stats -->
                            <?php if ($respstatsread && $activated == "Y") : ?>
                                <div class="col-md-6">
                                    <div class="card card-clickable card-primary h-100" id="panel-4" data-url="<?php echo $this->createUrl("admin/statistics/sa/simpleStatistics/surveyid/" . $oSurvey->sid); ?>">
                                        <div class="card-header ">
                                            <div class=""><?php eT("Statistics"); ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="card-body-ico">
                                                <a href="<?php echo $this->createUrl("admin/statistics/sa/simpleStatistics/surveyid/" . $oSurvey->sid); ?>">
                                                    <span class="ri-bar-chart-fill" style="font-size: 3em;"></span>
                                                    <span class="visually-hidden"><?php eT("Statistics"); ?></span>
                                                </a>
                                            </div>
                                            <div class="card-body-link">
                                                <p>
                                                    <a href="<?php echo $this->createUrl("admin/statistics/sa/simpleStatistics/surveyid/" . $oSurvey->sid); ?>">
                                                        <?php eT("Responses & statistics"); ?>
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="col-md-6">
                                    <div class="card disabled card-primary h-100" id="panel-4">
                                        <div class="card-header ">
                                            <div class=""><?php eT("Responses & statistics"); ?></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="card-body-ico">
                                                <a href="#">
                                                    <span class="ri-bar-chart-fill" style="font-size: 3em;"></span>
                                                    <span class="visually-hidden"><?php eT("Responses & statistics"); ?></span>
                                                </a>
                                            </div>
                                            <div class="card-body-link">
                                                <p>
                                                    <a href="#" title="<?php if ($activated != "Y") {
                                                                            eT("This survey is not active - no responses are available.");
                                                                        } else {
                                                                            eT("We are sorry but you don't have permissions to do this.");
                                                                        } ?>" style="display: inline-block">
                                                        <?php eT("Responses & statistics"); ?>
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <?php if (Permission::model()->hasSurveyPermission($oSurvey->sid, 'surveycontent', 'update')) : ?>
                            <!-- Template carroussel -->
                            <?php $this->renderPartial("/admin/survey/subview/_template_carousel", array(
                                'templates' => $templates,
                                'oSurvey' => $oSurvey,
                                'iSurveyId' => $oSurvey->sid,
                            )); ?>
                        <?php endif; ?>
                    </div>

                    <!-- last visited question -->
                    <?php if ($showLastQuestion) : ?>
                        <div class="row text-start">
                            <div class="col-12">
                                <?php eT("Last visited question:"); ?>
                                <a href="<?php echo $last_question_link; ?>" class=""><?php echo viewHelper::flatEllipsizeText($last_question_name, true, 60); ?></a>
                                <br /><br />
                            </div>
                        </div>
                    <?php endif; ?>

                </div> <!-- row boxes and template-->
            </div>
        </div>
    </div>
</div>
<?php
Yii::app()->getClientScript()->registerScript(
    'Quickaction-activate',
    "$('#survey-action-chevron').off('click').on('click', surveyQuickActionTrigger);",
    LSYii_ClientScript::POS_POSTSCRIPT
);
?>
