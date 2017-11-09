<?php
/* @var $this AdminController */
/* @var QuestionGroup $oQuestionGroup */
/* @var Survey $oSurvey */

// DO NOT REMOVE This is for automated testing to validate we see that page
echo viewHelper::getViewTestTag('addQuestion');

?>
<?php PrepareEditorScript(true, $this); ?>
<?php $this->renderPartial("./survey/Question/question_subviews/_ajax_variables", $ajaxDatas); ?>

<div id='edit-question-body' class='side-body <?php echo getSideBodyClass(false); ?>'>

    <!-- Page Title-->
    <div class="pagetitle h3">
        <?php
        if ($adding) {
            eT("Add a new question");
        } elseif ($copying) {
            eT("Copy question");
        } else {
            eT("Edit question");
            echo ': <em>'.$eqrow['title'].'</em> (ID:'.$qid.')';
        }
        ?>
    </div>

    <div class="row">
        <!-- Form for the whole page-->
        <?php echo CHtml::form(array("admin/database/index"), 'post',array('class'=>'form30 ','id'=>'frmeditquestion','name'=>'frmeditquestion')); ?>

        <?php // if(!$adding):?>

        <!-- The tabs & tab-fanes -->
        <div class="col-sm-12 col-md-7 content-right">
            <?php if($adding):?>
                <?php
                $this->renderPartial(
                    './survey/Question/question_subviews/_tabs',
                    array(
                        'oSurvey'=>$oSurvey,
                        'eqrow'=>$eqrow,
                        'surveyid'=>$surveyid,
                        'gid'=>$groupid, 'qid'=>NULL,
                        'adding'=>$adding,
                        'aqresult'=>$aqresult,
                        'action'=>$action
                    )
                ); ?>
                <?php else:?>
                <?php
                $this->renderPartial(
                    './survey/Question/question_subviews/_tabs',
                    array(
                        'oSurvey'=>$oSurvey,
                        'eqrow'=>$eqrow,
                        'surveyid'=>$surveyid,
                        'gid'=>$gid, 'qid'=>$qid,
                        'adding'=>$adding,
                        'aqresult'=>$aqresult,
                        'action'=>$action
                    )
                ); ?>

                <?php endif;?>
        </div>

        <!-- The Accordion -->
        <div class="col-sm-12 col-md-5" id="accordion-container" style="background-color: #fff; z-index: 2;">
            <?php // TODO : find why the $groups can't be generated from controller?>
            <div id='questionbottom'>
                <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">

                    <!-- Copy options -->
                    <?php if ($copying): ?>
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="heading-copy">
                                <div class="panel-title h4">
                                    <a class="btn btn-default btn-xs hide-button hidden-xs opened handleAccordion">
                                        <span class="fa fa-chevron-left"></span>
					<span class="sr-only"><?php eT("Expand/Collapse");?></span>
                                    </a>
                                    <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse-copy" aria-expanded="false" aria-controls="collapse-copy">
                                        <?php eT("Copy options"); ?>
                                    </a>
                                </div>
                            </div>
                            <div id="collapse-copy" class="panel-collapse collapse  in" role="tabpanel" aria-labelledby="heading-copy">
                                <div class="panel-body">
                                    <div  class="form-group">
                                        <label class=" control-label" for='copysubquestions'><?php eT("Copy subquestions?"); ?></label>
                                        <div class="">
                                            <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                                                'name' => 'copysubquestions',
                                                'id'=>'copysubquestions',
                                                'value' => 'Y',
                                                'onLabel' =>gT('Yes'),
                                                'offLabel' => gT('No')));
                                            ?>
                                        </div>
                                    </div>
                                    <div  class="form-group">
                                        <label class=" control-label" for='copyanswers'><?php eT("Copy answer options?"); ?></label>
                                        <div class="">
                                            <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                                                'name' => 'copyanswers',
                                                'id'=>'copyanswers',
                                                'value' => 'Y',
                                                'onLabel' =>gT('Yes'),
                                                'offLabel' => gT('No')));
                                            ?>
                                        </div>
                                    </div>
                                    <div  class="form-group">
                                        <label class=" control-label" for='copyattributes'><?php eT("Copy advanced settings?"); ?></label>
                                        <div class="">
                                            <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                                                'name' => 'copyattributes',
                                                'id' => 'copyattributes',
                                                'value' => 'Y',
                                                'onLabel' => gT('Yes'),
                                                'offLabel' => gT('No')));
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; // Copying ?>

                    <!-- General Options -->
                    <div class="panel panel-default" id="questionTypeContainer">

                        <!-- General Options : Header  -->
                        <div class="panel-heading" role="tab" id="headingOne">
                            <div class="panel-title h4">
                                <a class="btn btn-default btn-xs hide-button hidden-xs opened handleAccordion">
                                    <span class="fa fa-chevron-left"></span>
				    <span class="sr-only"><?php eT("Expand/Collapse");?></span>
                                </a>
                                <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse-question" aria-expanded="true" aria-controls="collapse-question">
                                    <?php eT("General options");?>
                                </a>
                            </div>
                        </div>

                        <div id="collapse-question" class="panel-collapse collapse <?php if (!$copying){echo ' in '; } ?>" role="tabpanel" aria-labelledby="headingOne">
                            <div class="panel-body">
                                <div>
                                    <div  class="form-group">
                                        <label class=" control-label" for="question_type_button" title="<?php eT("Question type");?>">
                                            <?php
                                            eT("Question type:");
                                            ?>
                                        </label>
                                        <div>
                                        <?php if(isset($selectormodeclass) && $selectormodeclass != "none" && $activated != "Y"): ?>
                                            <?php
                                            $aQuestionTypeList = (array) getQuestionTypeList($eqrow['type'], 'array');
                                            foreach ( $aQuestionTypeList as $key=> $questionType)
                                            {
                                                if (!isset($groups[$questionType['group']]))
                                                {
                                                    $groups[$questionType['group']] = array();
                                                }
                                                $groups[$questionType['group']][$key] = $questionType['description'];
                                            }
                                            ?>
                                            <input type="hidden" id="question_type" name="type" value="<?php echo $eqrow['type']; ?>" />
                                            <div class=" btn-group" id="question_type_button">
                                                <button type="button" class="btn btn-default dropdown-toggle " <?php if ($activated == "Y"){echo " disabled ";} ?>  data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" >
                                                    <?php foreach($groups as $name => $group):?>
                                                        <?php foreach($group as $type => $option):?>
                                                            <?php if($type == $eqrow['type']):?>
                                                                <span class="buttontext">
                                                                    <?php echo $option; ?>
                                                                    <?php if(YII_DEBUG):?>
                                                                        <em class="small">
                                                                            Type code: <?php echo $type; ?>
                                                                        </em>
                                                                        <?php endif;?>
                                                                </span>
                                                                <?php endif; ?>
                                                            <?php endforeach;?>
                                                        <?php endforeach;?>
                                                    &nbsp;&nbsp;&nbsp;
                                                    <span class="caret"></span>
                                                </button>

                                                <ul class="dropdown-menu" style="z-index: 1000">

                                                    <?php foreach($groups as $name => $group):?>
                                                        <small><?php echo $name;?></small>

                                                        <?php foreach($group as $type => $option):?>
                                                            <li>
                                                                <a href="#" class="questionType" data-value="<?php echo $type; ?>" <?php if($type == $eqrow['type']){echo 'active';}?>><?php echo $option;?></a>
                                                                <?php if(Yii::app()->getConfig("debug")===2):?>
                                                                    <em class="small text-info col-sm-offset-1">
                                                                        question type code: <?php echo $type; ?>
                                                                    </em>
                                                                    <?php endif;?>
                                                            </li>
                                                            <?php endforeach;?>

                                                        <li role="separator" class="divider"></li>
                                                        <?php endforeach;?>
                                                </ul>
                                            </div>
                                            <?php elseif($activated == "Y" || (isset($selectormodeclass) && $selectormodeclass == "none")): ?>
                                            <div class=" btn-group" id="question_type_button" style="z-index: 1000">
                                                <?php
                                                $aQtypeData=array();
                                                foreach (getQuestionTypeList($eqrow['type'], 'array') as $key=> $questionType)
                                                {
                                                    $aQtypeData[]=array('code'=>$key,'description'=>$questionType['description'],'group'=>$questionType['group']);
                                                }
                                                echo CHtml::dropDownList(
                                                    'type',
                                                    $eqrow['type'],
                                                    CHtml::listData($aQtypeData,'code','description','group'),
                                                    array(
                                                        'class' => 'form-control',
                                                        'id'=>'question_type',
                                                        'disabled'=>$activated == "Y", // readony is more beautifull : but allow open
                                                    )
                                                );
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        </div>
                                    </div>

                                    <div  class="form-group">
                                        <label class=" control-label" for='gid' title="<?php eT("Set question group");?>"><?php eT("Question group:"); ?></label>
                                        <div class="">
                                            <select name='gid' id='gid' class="form-control" <?php if ($activated == "Y"){echo " disabled ";} ?> >
                                                <?php echo getGroupList3($eqrow['gid'],$surveyid); ?>
                                            </select>
                                            <?php if ($activated == "Y"): ?>
                                                <input type='hidden' name='gid' value='<?php echo $eqrow['gid'];?>' />
                                                <?php endif; ?>
                                        </div>
                                    </div>

                                    <div  class="form-group" id="OtherSelection">
                                        <label class=" control-label" title="<?php eT("Option 'Other':");?>"><?php eT("Option 'Other':"); ?></label>
                                        <?php if ($activated != "Y"): ?>
                                            <div class="">
                                                <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array('name' => 'other', 'value'=> $eqrow['other'] === "Y", 'onLabel'=>gT('On'),'offLabel'=>gT('Off')));?>
                                            </div>
                                            <?php else:?>
                                            <?php eT("Cannot be changed (survey is active)");?>
                                            <input type='hidden' name='other' value="<?php echo ($eqrow['other']=='Y' ? 1 : 0); ?>" />
                                            <?php endif;?>
                                    </div>

                                    <div id='MandatorySelection' class="form-group">
                                        <label class=" control-label" title="<?php eT("Set \"Mandatory\" state");?>"><?php eT("Mandatory:"); ?></label>
                                        <div class="">
                                            <!-- Todo : replace by direct use of bootstrap switch. See statistics -->
                                            <?php $this->widget('yiiwheels.widgets.switch.WhSwitch', array('name' => 'mandatory', 'value'=> $eqrow['mandatory'] === "Y", 'onLabel'=>gT('On'),'offLabel'=>gT('Off')));?>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class=" control-label" for='relevance' title="<?php eT("Relevance equation");?>"><?php eT("Relevance equation:"); ?></label>
                                        <div class="">
                                            <div class="input-group">
                                                <div class="input-group-addon">{</div>
                                                <textarea class="form-control" rows='1' id='relevance' name='relevance' <?php if ($eqrow['conditions_number']) {?> readonly='readonly'<?php } ?> ><?php echo $eqrow['relevance']; ?></textarea>
                                                <div class="input-group-addon">}</div>
                                            </div>
                                            <?php if ($eqrow['conditions_number']) :?>
                                                <div class='help-block text-warning'> <?php eT("Note: You can't edit the relevance equation because there are currently conditions set for this question."); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div id='Validation'  class="form-group">
                                        <label class=" control-label" for='preg'  title="<?php eT("Validation:");?>"><?php eT("Validation:"); ?></label>
                                        <div class="">
                                            <input class="form-control" type='text' id='preg' name='preg' size='50' value="<?php echo $eqrow['preg']; ?>" />
                                        </div>
                                    </div>


                                    <?php if ($adding || $copying ): ?>

                                        <!-- Rendering position widget -->
                                        <?php $this->widget('ext.admin.survey.question.PositionWidget.PositionWidget', array(
                                                    'display'           => 'ajax_form_group',
                                                    'oQuestionGroup'    => $oQuestionGroup,
                                            ));
                                        ?>
                                        <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$copying): ?>
                    <div class="loader-advancedquestionsettings text-center">
                        <span class="fa fa-refresh" style="font-size:3em;" aria-hidden='true'></span>
                    </div>
                        <!-- Advanced settings -->
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <?php if ($adding): ?>
            <input type='hidden' name='action' value='insertquestion' />
            <input type='hidden' id='sid' name='sid' value='<?php echo $surveyid; ?>' />
            <p><input type='submit'  class="hidden" value='<?php eT("Add question"); ?>' /></p>
        <?php elseif ($copying): ?>
            <input type='hidden' name='action' value='copyquestion' />
            <input type='hidden' id='oldqid' name='oldqid' value='<?php echo $qid; ?>' />
            <p><input type='submit'  class="hidden" value='<?php eT("Copy question"); ?>' /></p>
        <?php else: ?>
            <input type='hidden' name='action' value='updatequestion' />
            <input type='hidden' id='qid' name='qid' value='<?php echo $qid; ?>' />
            <p><button type='submit' class="saveandreturn hidden" name="redirection" value="edit"><?php eT("Save") ?> </button></p>
            <input type='submit'  class="hidden" value='<?php eT("Save and close"); ?>' />
        <?php endif; ?>
        <input type='hidden' name='sid' value='<?php echo $surveyid; ?>' />
        <input type='hidden' name='close-after-save' value='true' />
        </form>
    </div>
</div>
