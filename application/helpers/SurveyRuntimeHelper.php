<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*/

class SurveyRuntimeHelper {
    /**
    * Main function
    *
    * @param mixed $surveyid
    * @param mixed $args
    */
    function run($surveyid,$args)
    {
        global $errormsg;
        extract($args);

        if (!$thissurvey)
        {
            $thissurvey = getSurveyInfo($surveyid);
        }

        $LEMsessid = 'survey_' . $surveyid;
        $this->setJavascriptVar($surveyid);

        $oTemplate = Template::model()->getInstance('', $surveyid);
        $sTemplatePath = $oTemplate->path;
        $sTemplateViewPath = $oTemplate->pstplPath;

        $flashmessage = makeFlashMessage();

        // $LEMdebugLevel - customizable debugging for Lime Expression Manager
        $LEMdebugLevel = 0;   // LEM_DEBUG_TIMING;    // (LEM_DEBUG_TIMING + LEM_DEBUG_VALIDATION_SUMMARY + LEM_DEBUG_VALIDATION_DETAIL);
        $LEMskipReprocessing=false; // true if used GetLastMoveResult to avoid generation of unneeded extra JavaScript
        switch ($thissurvey['format'])
        {
            case "A": //All in one
                $surveyMode = 'survey';
                break;
            default:
            case "S": //One at a time
                $surveyMode = 'question';
                break;
            case "G": //Group at a time
                $surveyMode = 'group';
                break;
        }
        $radix=getRadixPointData($thissurvey['surveyls_numberformat']);
        $radix = $radix['separator'];

        $surveyOptions = array(
            'active' => ($thissurvey['active'] == 'Y'),
            'allowsave' => ($thissurvey['allowsave'] == 'Y'),
            'anonymized' => ($thissurvey['anonymized'] != 'N'),
            'assessments' => ($thissurvey['assessments'] == 'Y'),
            'datestamp' => ($thissurvey['datestamp'] == 'Y'),
            'deletenonvalues'=>Yii::app()->getConfig('deletenonvalues'),
            'hyperlinkSyntaxHighlighting' => (($LEMdebugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY), // TODO set this to true if in admin mode but not if running a survey
            'ipaddr' => ($thissurvey['ipaddr'] == 'Y'),
            'radix'=>$radix,
            'refurl' => (($thissurvey['refurl'] == "Y" && isset($_SESSION[$LEMsessid]['refurl'])) ? $_SESSION[$LEMsessid]['refurl'] : NULL),
            'savetimings' => ($thissurvey['savetimings'] == "Y"),
            'surveyls_dateformat' => (isset($thissurvey['surveyls_dateformat']) ? $thissurvey['surveyls_dateformat'] : 1),
            'startlanguage'=>(isset(App()->language) ? App()->language : $thissurvey['language']),
            'target' => Yii::app()->getConfig('uploaddir').DIRECTORY_SEPARATOR.'surveys'.DIRECTORY_SEPARATOR.$thissurvey['sid'].DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR,
            'tempdir' => Yii::app()->getConfig('tempdir').DIRECTORY_SEPARATOR,
            'timeadjust' => (isset($timeadjust) ? $timeadjust : 0),
            'token' => (isset($clienttoken) ? $clienttoken : NULL),
        );

        //Security Checked: POST, GET, SESSION, REQUEST, returnGlobal, DB
        $previewgrp = false;
        if ($surveyMode == 'group' && isset($param['action']) && ($param['action'] == 'previewgroup'))
        {
            $previewgrp = true;
        }
        $previewquestion = false;
        if ($surveyMode == 'question' && isset($param['action']) && ($param['action'] == 'previewquestion'))
        {
            $previewquestion = true;
        }

        //        if (isset($param['newtest']) && $param['newtest'] == "Y")
        //            setcookie("limesurvey_timers", "0");   //@todo fix - sometimes results in headers already sent error
        $show_empty_group = false;

        if ($previewgrp || $previewquestion)
        {
            $_SESSION[$LEMsessid]['prevstep'] = 2;
            $_SESSION[$LEMsessid]['maxstep'] = 0;
        }
        else
        {
            //RUN THIS IF THIS IS THE FIRST TIME , OR THE FIRST PAGE ########################################
            if (!isset($_SESSION[$LEMsessid]['step']))
            {
                buildsurveysession($surveyid);
                randomizationGroupsAndQuestions($surveyid);
                initFieldArray($surveyid, $_SESSION['survey_' . $surveyid]['fieldmap']);

                if($surveyid != LimeExpressionManager::getLEMsurveyId())
                    LimeExpressionManager::SetDirtyFlag();

                LimeExpressionManager::StartSurvey($surveyid, $surveyMode, $surveyOptions, false, $LEMdebugLevel);
                $_SESSION[$LEMsessid]['step'] = 0;
                if ($surveyMode == 'survey')
                {
                    LimeExpressionManager::JumpTo(1, false, false, true);
                }
                elseif (isset($thissurvey['showwelcome']) && $thissurvey['showwelcome'] == 'N')
                {
                    $moveResult = LimeExpressionManager::NavigateForwards();
                    $_SESSION[$LEMsessid]['step'] = 1;
                }
            }
            elseif($surveyid != LimeExpressionManager::getLEMsurveyId())
            {
                $_SESSION[$LEMsessid]['step']=$_SESSION[$LEMsessid]['step']<0 ? 0 : $_SESSION[$LEMsessid]['step'];//$_SESSION[$LEMsessid]['step'] can not be less than 0, fix it always #09772
                LimeExpressionManager::StartSurvey($surveyid, $surveyMode, $surveyOptions, false, $LEMdebugLevel);
                LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['step'], false, false);
            }
            $totalquestions = $_SESSION['survey_'.$surveyid]['totalquestions'];

            if (!isset($_SESSION[$LEMsessid]['totalsteps']))
            {
                $_SESSION[$LEMsessid]['totalsteps'] = 0;
            }
            if (!isset($_SESSION[$LEMsessid]['maxstep']))
            {
                $_SESSION[$LEMsessid]['maxstep'] = 0;
            }

            if (isset($_SESSION[$LEMsessid]['LEMpostKey']) && App()->request->getPost('LEMpostKey',$_SESSION[$LEMsessid]['LEMpostKey']) != $_SESSION[$LEMsessid]['LEMpostKey'])
            {
                // then trying to resubmit (e.g. Next, Previous, Submit) from a cached copy of the page
                $moveResult = LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['step'], false, false, true);// We JumpTo current step without saving: see bug #11404
                if (isset($moveResult['seq']) &&  App()->request->getPost('thisstep',$moveResult['seq']) == $moveResult['seq'])
                {

                    /* then pressing F5 or otherwise refreshing the current page, which is OK
                     * Seems OK only when movenext but not with move by index : same with $moveResult = LimeExpressionManager::GetLastMoveResult(true);
                     */
                    $LEMskipReprocessing=true;
                    $move = "movenext"; // so will re-display the survey
                }
                else
                {
                    // trying to use browser back buttons, which may be disallowed if no 'previous' button is present
                    $LEMskipReprocessing=true;
                    $move = "movenext"; // so will re-display the survey
                    $invalidLastPage=true;
                    $backpopup=gT("Please use the LimeSurvey navigation buttons or index.  It appears you attempted to use the browser back button to re-submit a page.");
                }
            }
            if(isset($move) && $move=="clearcancel")
            {
                $moveResult = LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['step'], false, true, false, true);
                //$backpopup=gT("Clear all need confirmation.");
            }
            if (isset($move))
            {
                if(!in_array($move,array("clearall","changelang","saveall","reload")))
                    $_SESSION[$LEMsessid]['prevstep'] = $_SESSION[$LEMsessid]['step'];
                else // Accepted $move without error
                    $_SESSION[$LEMsessid]['prevstep']= $move;
            }
            else
            {
                //$_SESSION[$LEMsessid]['prevstep'] = $_SESSION[$LEMsessid]['step']-1; // Is this needed ?
            }
            if (!isset($_SESSION[$LEMsessid]['prevstep']))
            {
                $_SESSION[$LEMsessid]['prevstep']=$_SESSION[$LEMsessid]['step']-1;   // this only happens on re-load
            }

            /* quota submitted */
            if(isset($move) && $move=='confirmquota'){
                checkCompletedQuota($surveyid);
            }

            if (isset($_SESSION[$LEMsessid]['LEMtokenResume']))
            {
                LimeExpressionManager::StartSurvey($thissurvey['sid'], $surveyMode, $surveyOptions, false,$LEMdebugLevel);
                if(isset($_SESSION[$LEMsessid]['maxstep']) && $_SESSION[$LEMsessid]['maxstep']>$_SESSION[$LEMsessid]['step'] && $thissurvey['questionindex'] )// Do it only if needed : we don't need it if we don't have index
                {
                    LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['maxstep'], false, false);
                }
                $moveResult = LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['step'],false,false);   // if late in the survey, will re-validate contents, which may be overkill
                unset($_SESSION[$LEMsessid]['LEMtokenResume']);
            }
            else if (!$LEMskipReprocessing)
            {
                //Move current step ###########################################################################
                if (isset($move) && $move == 'moveprev' && ($thissurvey['allowprev'] == 'Y' || $thissurvey['questionindex'] > 0))
                {
                    $moveResult = LimeExpressionManager::NavigateBackwards();
                    if ($moveResult['at_start'])
                    {
                        $_SESSION[$LEMsessid]['step'] = 0;
                        unset($moveResult); // so display welcome page again
                    }
                }
                if (isset($move) && $move == "movenext")
                {
                    $moveResult = LimeExpressionManager::NavigateForwards();
                }
                if (isset($move) && ($move == 'movesubmit'))
                {
                    if ($surveyMode == 'survey')
                    {
                        $moveResult = LimeExpressionManager::NavigateForwards();
                    }
                    else
                    {
                        // may be submitting from the navigation bar, in which case need to process all intervening questions
                        // in order to update equations and ensure there are no intervening relevant mandatory or relevant invalid questions
                        if($thissurvey['questionindex']==2) // Must : save actual page , review whole before set finished to true (see #09906), index==1 seems to don't need it : (don't force move)
                            LimeExpressionManager::StartSurvey($surveyid, $surveyMode, $surveyOptions);
                        $moveResult = LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['totalsteps'] + 1, false);
                    }
                }
                if (isset($move) && $move=='changelang')
                {
                    // jump to current step using new language, processing POST values
                    $moveResult = LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['step'], false, true, true, true);  // do process the POST data
                }
                if (isset($move) && isNumericInt($move) && $thissurvey['questionindex'] == 1)
                {
                    $move = (int) $move;
                    if ($move > 0 && (($move <= $_SESSION[$LEMsessid]['step']) || (isset($_SESSION[$LEMsessid]['maxstep']) && $move <= $_SESSION[$LEMsessid]['maxstep'])))
                    {
                        $moveResult = LimeExpressionManager::JumpTo($move, false);
                    }
                }
                elseif (isset($move) && isNumericInt($move) && $thissurvey['questionindex'] == 2)
                {
                    $move = (int) $move;
                    $moveResult = LimeExpressionManager::JumpTo($move, false, true, true);
                }
                if (!isset($moveResult) && !($surveyMode != 'survey' && $_SESSION[$LEMsessid]['step'] == 0))
                {
                    // Just in case not set via any other means, but don't do this if it is the welcome page
                    $moveResult = LimeExpressionManager::GetLastMoveResult(true);
                    $LEMskipReprocessing=true;
                }
            }
            if (isset($moveResult) && isset($moveResult['seq']) )// Reload at first page (welcome after click previous fill an empty $moveResult array
            {
                // With complete index, we need to revalidate whole group bug #08806. It's actually the only mode where we JumpTo with force
                if($moveResult['finished'] == true && $move != 'movesubmit' && $thissurvey['questionindex']==2)// we already done if move == 'movesubmit', don't do it again
                {
                    //LimeExpressionManager::JumpTo(-1, false, false, true);
                    LimeExpressionManager::StartSurvey($surveyid, $surveyMode, $surveyOptions);
                    $moveResult = LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['totalsteps']+1, false, false, false);// no preview, no save data and NO force
                    if(!$moveResult['mandViolation'] && $moveResult['valid'] && empty($moveResult['invalidSQs']))
                        $moveResult['finished'] = true;
                }
                if ($moveResult['finished'] == true)
                {
                    $move = 'movesubmit';
                }
                else
                {
                    $_SESSION[$LEMsessid]['step'] = $moveResult['seq'] + 1;  // step is index base 1
                    $stepInfo = LimeExpressionManager::GetStepIndexInfo($moveResult['seq']);
                }
                if ($move == "movesubmit" && $moveResult['finished'] == false)
                {
                    // then there are errors, so don't finalize the survey
                    $move = "movenext"; // so will re-display the survey
                    $invalidLastPage = true;
                }
            }

            // We do not keep the participant session anymore when the same browser is used to answer a second time a survey (let's think of a library PC for instance).
            // Previously we used to keep the session and redirect the user to the
            // submit page.

            if ($surveyMode != 'survey' && $_SESSION[$LEMsessid]['step'] == 0)
            {
                $_SESSION[$LEMsessid]['test']=time();
                display_first_page();
                Yii::app()->end(); // So we can still see debug messages
            }
            // TODO FIXME
            if ($thissurvey['active'] == "Y" && Yii::app()->request->getPost('saveall')) // Don't test if save is allowed
            {
                $bTokenAnswerPersitance = $thissurvey['tokenanswerspersistence'] == 'Y' && isset($surveyid) && tableExists('tokens_'.$surveyid);
                // must do this here to process the POSTed values
                $moveResult = LimeExpressionManager::JumpTo($_SESSION[$LEMsessid]['step'], false);   // by jumping to current step, saves data so far
                if (!isset($_SESSION[$LEMsessid]['scid']) && !$bTokenAnswerPersitance )
                {
                    Yii::import("application.libraries.Save");
                    $cSave = new Save();
                    $cSave->showsaveform($thissurvey['sid']); // generates a form and exits, awaiting input
                }
                else
                {
                    // Intentional retest of all conditions to be true, to make sure we do have tokens and surveyid
                    // Now update lastpage to $_SESSION[$LEMsessid]['step'] in SurveyDynamic, otherwise we land on
                    // the previous page when we return.
                    $iResponseID = $_SESSION[$LEMsessid]['srid'];
                    $oResponse = SurveyDynamic::model($surveyid)->findByPk($iResponseID);
                    $oResponse->lastpage = $_SESSION[$LEMsessid]['step'];
                    $oResponse->save();
                }
            }

            if ($thissurvey['active'] == "Y" && Yii::app()->request->getParam('savesubmit') )
            {
                // The response from the save form
                // CREATE SAVED CONTROL RECORD USING SAVE FORM INFORMATION
                Yii::import("application.libraries.Save");
                $cSave = new Save();
                $popup = $cSave->savedcontrol();
                if (!empty($cSave->aSaveErrors))
                {
                    $cSave->showsaveform($thissurvey['sid']); // reshow the form if there is an error
                }

                $moveResult = LimeExpressionManager::GetLastMoveResult(true);
                $LEMskipReprocessing=true;

                // TODO - does this work automatically for token answer persistence? Used to be savedsilent()
            }

            //Now, we check mandatory questions if necessary
            //CHECK IF ALL CONDITIONAL MANDATORY QUESTIONS THAT APPLY HAVE BEEN ANSWERED
            global $notanswered;

            if (isset($moveResult) && !$moveResult['finished'])
            {
                $unansweredSQList = $moveResult['unansweredSQs'];
                if (strlen($unansweredSQList) > 0)
                {
                    $notanswered = explode('|', $unansweredSQList);
                }
                else
                {
                    $notanswered = array();
                }

                //CHECK INPUT
                $invalidSQList = $moveResult['invalidSQs'];
                if (strlen($invalidSQList) > 0)
                {
                    $notvalidated = explode('|', $invalidSQList);
                }
                else
                {
                    $notvalidated = array();
                }
            }

            // CHECK UPLOADED FILES
            // TMSW - Move this into LEM::NavigateForwards?
            $filenotvalidated = checkUploadedFileValidity($surveyid, $move);

            //SEE IF THIS GROUP SHOULD DISPLAY
            $show_empty_group = false;

            if ($_SESSION[$LEMsessid]['step'] == 0)
                $show_empty_group = true;

            $redata = compact(array_keys(get_defined_vars()));

            //SUBMIT ###############################################################################
            if ((isset($move) && $move == "movesubmit"))
            {
                //                setcookie("limesurvey_timers", "", time() - 3600); // remove the timers cookies   //@todo fix - sometimes results in headers already sent error
                if ($thissurvey['refurl'] == "Y")
                {
                    if (!in_array("refurl", $_SESSION[$LEMsessid]['insertarray'])) //Only add this if it doesn't already exist
                    {
                        $_SESSION[$LEMsessid]['insertarray'][] = "refurl";
                    }
                }
                resetTimers();

                //Before doing the "templatereplace()" function, check the $thissurvey['url']
                //field for limereplace stuff, and do transformations!
                $thissurvey['surveyls_url'] = passthruReplace($thissurvey['surveyls_url'], $thissurvey);
                $thissurvey['surveyls_url'] = templatereplace($thissurvey['surveyls_url'], array(), $redata, 'URLReplace', false, NULL, array(), true );   // to do INSERTANS substitutions

                //END PAGE - COMMIT CHANGES TO DATABASE
                if ($thissurvey['active'] != "Y") //If survey is not active, don't really commit
                {
                    if ($thissurvey['assessments'] == "Y")
                    {
                        $assessments = doAssessment($surveyid);
                    }
                    sendCacheHeaders();
                    doHeader();


                    echo templatereplace(file_get_contents($sTemplateViewPath."startpage.pstpl"), array(), $redata, 'SubmitStartpageI', false, NULL, array(), true );

                    //Check for assessments
                    if ($thissurvey['assessments'] == "Y" && $assessments)
                    {
                        echo templatereplace(file_get_contents($sTemplateViewPath."assessment.pstpl"), array(), $redata, 'SubmitAssessmentI', false, NULL, array(), true );
                    }

                    // fetch all filenames from $_SESSIONS['files'] and delete them all
                    // from the /upload/tmp/ directory
                    /* echo "<pre>";print_r($_SESSION);echo "</pre>";
                    for($i = 1; isset($_SESSION[$LEMsessid]['files'][$i]); $i++)
                    {
                    unlink('upload/tmp/'.$_SESSION[$LEMsessid]['files'][$i]['filename']);
                    }
                    */
                    // can't kill session before end message, otherwise INSERTANS doesn't work.
                    $completed = templatereplace($thissurvey['surveyls_endtext'], array(), $redata, 'SubmitEndtextI', false, NULL, array(), true );
                    $completed .= "<br /><strong><font size='2' color='red'>" . gT("Did Not Save") . "</font></strong><br /><br />\n\n";
                    $completed .= gT("Your survey responses have not been recorded. This survey is not yet active.") . "<br /><br />\n";
                    if ($thissurvey['printanswers'] == 'Y')
                    {
                        // 'Clear all' link is only relevant for survey with printanswers enabled
                        // in other cases the session is cleared at submit time
                        $completed .= "<a href='" . Yii::app()->getController()->createUrl("survey/index/sid/{$surveyid}/move/clearall") . "'>" . gT("Clear Responses") . "</a><br /><br />\n";
                    }


                }
                else //THE FOLLOWING DEALS WITH SUBMITTING ANSWERS AND COMPLETING AN ACTIVE SURVEY
                {
                    if ($thissurvey['usecookie'] == "Y" && $tokensexist != 1) //don't use cookies if tokens are being used
                    {
                        setcookie("LS_" . $surveyid . "_STATUS", "COMPLETE", time() + 31536000); //Cookie will expire in 365 days
                    }


                    $content = '';
                    $content .= templatereplace(file_get_contents($sTemplateViewPath."startpage.pstpl"), array(), $redata, 'SubmitStartpage', false, NULL, array(), true );

                    //Check for assessments
                    if ($thissurvey['assessments'] == "Y")
                    {
                        $assessments = doAssessment($surveyid);
                        if ($assessments)
                        {
                            $content .= templatereplace(file_get_contents($sTemplateViewPath."assessment.pstpl"), array(), $redata, 'SubmitAssessment', false, NULL, array(), true );
                        }
                    }

                    //Update the token if needed and send a confirmation email
                    if (isset($_SESSION['survey_'.$surveyid]['token']))
                    {
                        submittokens();
                    }

                    //Send notifications

                    sendSubmitNotifications($surveyid);


                    $content = '';

                    $content .= templatereplace(file_get_contents($sTemplateViewPath."startpage.pstpl"), array(), $redata, 'SubmitStartpage', false, NULL, array(), true );

                    //echo $thissurvey['url'];
                    //Check for assessments
                    if ($thissurvey['assessments'] == "Y")
                    {
                        $assessments = doAssessment($surveyid);
                        if ($assessments)
                        {
                            $content .= templatereplace(file_get_contents($sTemplateViewPath."assessment.pstpl"), array(), $redata, 'SubmitAssessment', false, NULL, array(), true );
                        }
                    }


                    if (trim(str_replace(array('<p>','</p>'),'',$thissurvey['surveyls_endtext'])) == '')
                    {
                        $completed = "<p>".gT("Thank you!")."</p>";
                        $completed.= "<p>".gT("Your survey responses have been recorded.")."</p>";
                      }
                    else
                    {
                        $completed = templatereplace($thissurvey['surveyls_endtext'], array(), $redata, 'SubmitAssessment', false, NULL, array(), true );
                    }

                    // Link to Print Answer Preview  **********
                    if ($thissurvey['printanswers'] == 'Y')
                    {
                        $completed.= App()->getController()->renderPartial("/survey/system/url",array(
                            'url'=>Yii::app()->getController()->createUrl("/printanswers/view",array('surveyid'=>$surveyid)),
                            'description'=>gT("Print your answers."),
                            'type'=>"survey-print",
                            'coreClass'=>"ls-print",
                        ),true);
                    }

                    // Link to Public statistics  **********
                    if ($thissurvey['publicstatistics'] == 'Y')
                    {
                        $completed.= App()->getController()->renderPartial("/survey/system/url",array(
                            'url'=>Yii::app()->getController()->createUrl("/statistics_user/action/",array('surveyid'=>$surveyid,'language'=>App()->getLanguage())),
                            'description'=>gT("View the statistics for this survey."),
                            'type'=>"survey-statistics",
                            'coreClass'=>"ls-statistics",
                        ),true);
                    }
                    //*****************************************

                    $_SESSION[$LEMsessid]['finished'] = true;
                    $_SESSION[$LEMsessid]['sid'] = $surveyid;

                    sendCacheHeaders();
                    if (isset($thissurvey['autoredirect']) && $thissurvey['autoredirect'] == "Y" && $thissurvey['surveyls_url'])
                    {
                        //Automatically redirect the page to the "url" setting for the survey
                        header("Location: {$thissurvey['surveyls_url']}");
                    }

                    doHeader();
                    echo $content;
                }
                $redata['completed'] = $completed;

                // @todo Remove direct session access.
                $event = new PluginEvent('afterSurveyComplete');
                if (isset($_SESSION[$LEMsessid]['srid']))
                {
                    $event->set('responseId', $_SESSION[$LEMsessid]['srid']);
                }
                $event->set('surveyId', $surveyid);
                App()->getPluginManager()->dispatchEvent($event);
                $blocks = array();

                foreach ($event->getAllContent() as $blockData)
                {
                    /* @var $blockData PluginEventContent */
                    $blocks[] = CHtml::tag('div', array('id' => $blockData->getCssId(), 'class' => $blockData->getCssClass()), $blockData->getContent());
                }

                $redata['completed'] = implode("\n", $blocks) ."\n". $redata['completed'];
                $redata['thissurvey']['surveyls_url'] = $thissurvey['surveyls_url'];

                echo templatereplace(file_get_contents($sTemplateViewPath."completed.pstpl"), array('completed' => $completed), $redata, 'SubmitCompleted', false, NULL, array(), true );
                echo "\n";
                if ((($LEMdebugLevel & LEM_DEBUG_TIMING) == LEM_DEBUG_TIMING))
                {
                    echo LimeExpressionManager::GetDebugTimingMessage();
                }
                if ((($LEMdebugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY))
                {
                    echo "<table><tr><td align='left'><b>Group/Question Validation Results:</b>" . $moveResult['message'] . "</td></tr></table>\n";
                }
                echo templatereplace(file_get_contents($sTemplateViewPath."endpage.pstpl"), array(), $redata, 'SubmitEndpage', false, NULL, array(), true );
                doFooter();

                // The session cannot be killed until the page is completely rendered
                if ($thissurvey['printanswers'] != 'Y')
                {
                    killSurveySession($surveyid);
                }
                exit;
            }
        }

        $redata = compact(array_keys(get_defined_vars()));

        // IF GOT THIS FAR, THEN DISPLAY THE ACTIVE GROUP OF QUESTIONSs
        //SEE IF $surveyid EXISTS ####################################################################
        if ($surveyExists < 1)
        {
            //SURVEY DOES NOT EXIST. POLITELY EXIT.
            echo templatereplace(file_get_contents($sTemplateViewPath."startpage.pstpl"), array(), $redata);
            echo "\t<center><br />\n";
            echo "\t" . gT("Sorry. There is no matching survey.") . "<br /></center>&nbsp;\n";
            echo templatereplace(file_get_contents($sTemplateViewPath."endpage.pstpl"), array(), $redata);
            doFooter();
            exit;
        }
        createFieldMap($surveyid,'full',false,false,$_SESSION[$LEMsessid]['s_lang']);
        //GET GROUP DETAILS

        if ($surveyMode == 'group' && $previewgrp)
        {
            //            setcookie("limesurvey_timers", "0"); //@todo fix - sometimes results in headers already sent error
            $_gid = sanitize_int($param['gid']);

            LimeExpressionManager::StartSurvey($thissurvey['sid'], 'group', $surveyOptions, false, $LEMdebugLevel);
            $gseq = LimeExpressionManager::GetGroupSeq($_gid);
            if ($gseq == -1)
            {
                echo gT('Invalid group number for this survey: ') . $_gid;
                exit;
            }
            $moveResult = LimeExpressionManager::JumpTo($gseq + 1, true);
            if (is_null($moveResult))
            {
                echo gT('This group contains no questions.  You must add questions to this group before you can preview it');
                exit;
            }
            if (isset($moveResult))
            {
                $_SESSION[$LEMsessid]['step'] = $moveResult['seq'] + 1;  // step is index base 1?
            }

            $stepInfo = LimeExpressionManager::GetStepIndexInfo($moveResult['seq']);
            $gid = $stepInfo['gid'];
            $groupname = $stepInfo['gname'];
            $groupdescription = $stepInfo['gtext'];
        }
        else
        {
            if (($show_empty_group) || !isset($_SESSION[$LEMsessid]['grouplist']))
            {
                $gid = -1; // Make sure the gid is unused. This will assure that the foreach (fieldarray as ia) has no effect.
                $groupname = gT("Submit your answers");
                $groupdescription = gT("There are no more questions. Please press the <Submit> button to finish this survey.");
            }
            else if ($surveyMode != 'survey')
            {
                if ($previewquestion) {
                    $_qid = sanitize_int($param['qid']);
                    LimeExpressionManager::StartSurvey($surveyid, 'question', $surveyOptions, false, $LEMdebugLevel);
                    $qSec       = LimeExpressionManager::GetQuestionSeq($_qid);
                    $moveResult = LimeExpressionManager::JumpTo($qSec+1,true,false,true);
                    $stepInfo   = LimeExpressionManager::GetStepIndexInfo($moveResult['seq']);
                } else {
                    $stepInfo = LimeExpressionManager::GetStepIndexInfo($moveResult['seq']);
                }

                $gid = $stepInfo['gid'];
                $groupname = $stepInfo['gname'];
                $groupdescription = $stepInfo['gtext'];
            }
        }
        if ($previewquestion)
        {
            $_SESSION[$LEMsessid]['step'] = 0; //maybe unset it after the question has been displayed?
        }

        if ($_SESSION[$LEMsessid]['step'] > $_SESSION[$LEMsessid]['maxstep'])
        {
            $_SESSION[$LEMsessid]['maxstep'] = $_SESSION[$LEMsessid]['step'];
        }

        // If the survey uses answer persistence and a srid is registered in SESSION
        // then loadanswers from this srid
        /* Only survey mode used this - should all?
        if ($thissurvey['tokenanswerspersistence'] == 'Y' &&
        $thissurvey['anonymized'] == "N" &&
        isset($_SESSION[$LEMsessid]['srid']) &&
        $thissurvey['active'] == "Y")
        {
        loadanswers();
        }
        */

        //******************************************************************************************************
        //PRESENT SURVEY
        //******************************************************************************************************

        $okToShowErrors = (!$previewgrp && (isset($invalidLastPage) || $_SESSION[$LEMsessid]['prevstep'] == $_SESSION[$LEMsessid]['step']));

        Yii::app()->getController()->loadHelper('qanda');
        setNoAnswerMode($thissurvey);

        //Iterate through the questions about to be displayed:
        $inputnames = array();

        foreach ($_SESSION[$LEMsessid]['grouplist'] as $gl)
        {
            $gid = $gl['gid'];
            $qnumber = 0;

            if ($surveyMode != 'survey')
            {
                $onlyThisGID = $stepInfo['gid'];
                if ($onlyThisGID != $gid)
                {
                    continue;
                }
            }

            // TMSW - could iterate through LEM::currentQset instead

            //// To diplay one question, all the questions are processed ?
            if (!isset($qanda))
            {
                $qanda=array();
            }
            foreach ($_SESSION[$LEMsessid]['fieldarray'] as $key => $ia)
            {
                ++$qnumber;
                $ia[9] = $qnumber; // incremental question count;

                if ((isset($ia[10]) && $ia[10] == $gid) || (!isset($ia[10]) && $ia[5] == $gid))// Make $qanda only for needed question $ia[10] is the randomGroup and $ia[5] the real group
                {
                    if ($surveyMode == 'question' && $ia[0] != $stepInfo['qid'])
                    {
                        continue;
                    }
                    $qidattributes = getQuestionAttributeValues($ia[0]);
                    if ($ia[4] != '*' && ($qidattributes === false || !isset($qidattributes['hidden']) || $qidattributes['hidden'] == 1))
                    {
                        continue;
                    }

                    //Get the answers/inputnames
                    // TMSW - can content of retrieveAnswers() be provided by LEM?  Review scope of what it provides.
                    // TODO - retrieveAnswers is slow - queries database separately for each question. May be fixed in _CI or _YII ports, so ignore for now
                    list($plus_qanda, $plus_inputnames) = retrieveAnswers($ia, $surveyid);
                    if ($plus_qanda)
                    {
                        $plus_qanda[] = $ia[4];
                        $plus_qanda[] = $ia[6]; // adds madatory identifyer for adding mandatory class to question wrapping div
                        // Add a finalgroup in qa array , needed for random attribute : TODO: find a way to have it in new quanda_helper in 2.1
                        if(isset($ia[10]))
                            $plus_qanda['finalgroup']=$ia[10];
                        else
                            $plus_qanda['finalgroup']=$ia[5];
                        $qanda[] = $plus_qanda;
                    }
                    if ($plus_inputnames)
                    {
                        $inputnames = addtoarray_single($inputnames, $plus_inputnames);
                    }

                    //Display the "mandatory" popup if necessary
                    // TMSW - get question-level error messages - don't call **_popup() directly
                    if ($okToShowErrors && $stepInfo['mandViolation'])
                    {
                        list($mandatorypopup, $popup) = mandatory_popup($ia, $notanswered);
                    }

                    //Display the "validation" popup if necessary
                    if ($okToShowErrors && !$stepInfo['valid'])
                    {
                        list($validationpopup, $vpopup) = validation_popup($ia, $notvalidated);
                    }

                    // Display the "file validation" popup if necessary
                    if ($okToShowErrors && isset($filenotvalidated))
                    {
                        list($filevalidationpopup, $fpopup) = file_validation_popup($ia, $filenotvalidated);
                    }
                }
                if ($ia[4] == "|")
                    $upload_file = TRUE;
            } //end iteration
        }

        if ($surveyMode != 'survey' && isset($thissurvey['showprogress']) && $thissurvey['showprogress'] == 'Y')
        {
            if ($show_empty_group)
            {
                $percentcomplete = makegraph($_SESSION[$LEMsessid]['totalsteps'] + 1, $_SESSION[$LEMsessid]['totalsteps']);
            }
            else
            {
                $percentcomplete = makegraph($_SESSION[$LEMsessid]['step'], $_SESSION[$LEMsessid]['totalsteps']);
            }
        }
        if (!(isset($languagechanger) && strlen($languagechanger) > 0) && function_exists('makeLanguageChangerSurvey'))
        {
            $languagechanger = makeLanguageChangerSurvey($_SESSION[$LEMsessid]['s_lang']);
        }

        //READ TEMPLATES, INSERT DATA AND PRESENT PAGE
        /**
         * create question index only in SurveyRuntime, not needed elsewhere, add it to GlobalVar : must be always set even if empty
         *
         */
        if(!$previewquestion && !$previewgrp){
            $questionindex = ls\helpers\questionIndexHelper::getInstance()->getIndexButton();
            $questionindexmenu = ls\helpers\questionIndexHelper::getInstance()->getIndexLink();
        }

        sendCacheHeaders();
        doHeader();

        /////////////////////////////////
        // First call to templatereplace

        echo "<!-- SurveyRunTimeHelper -->";
        $redata = compact(array_keys(get_defined_vars()));
        echo templatereplace(file_get_contents($sTemplateViewPath."startpage.pstpl"), array(), $redata);
        $aPopup=array(); // We can move this part where we want now
        if (isset($backpopup))
        {
            $aPopup[]=$backpopup;// If user click reload: no need other popup
        }
        else
        {
            if (isset($popup))
            {
                $aPopup[]=$popup;
            }
            if (isset($vpopup))
            {
                $aPopup[]=$vpopup;
            }
            if (isset($fpopup))
            {
                $aPopup[]=$fpopup;
            }
        }
        Yii::app()->clientScript->registerScript('startPopup',"LSvar.startPopups=".json_encode($aPopup).";",CClientScript::POS_HEAD);
        Yii::app()->clientScript->registerScript('showStartPopups',"showStartPopups();",CClientScript::POS_END);
        $hiddenfieldnames = implode("|", $inputnames);

        if (isset($upload_file) && $upload_file)
            echo CHtml::form(array("/survey/index","sid"=>$surveyid), 'post',array('enctype'=>'multipart/form-data','id'=>'limesurvey','name'=>'limesurvey', 'autocomplete'=>'off', 'class'=>'survey-form-container surveyRunTimeUploadFile'))."\n
            <!-- INPUT NAMES -->
            <input type='hidden' name='fieldnames' value='{$hiddenfieldnames}' id='fieldnames' />\n";
        else
            echo CHtml::form(array("/survey/index","sid"=>$surveyid), 'post',array('id'=>'limesurvey', 'name'=>'limesurvey', 'autocomplete'=>'off', 'class'=>'survey-form-container  surveyRunTime'))."\n
            <!-- INPUT NAMES -->
            <input type='hidden' name='fieldnames' value='{$hiddenfieldnames}' id='fieldnames' />\n";
        // <-- END FEATURE - SAVE

        // The default submit button
        echo CHtml::htmlButton("default",array('type'=>'submit','id'=>"defaultbtn",'value'=>"default",'name'=>'move','class'=>"submit hidden",'style'=>'display:none'));
        if ($surveyMode == 'survey')
        {
            if (isset($thissurvey['showwelcome']) && $thissurvey['showwelcome'] == 'N')
            {
                //Hide the welcome screen if explicitly set
            }
            else
            {
                echo templatereplace(file_get_contents($sTemplateViewPath."welcome.pstpl"), array(), $redata) . "\n";
            }

            if ($thissurvey['anonymized'] == "Y")
            {
                echo templatereplace(file_get_contents($sTemplateViewPath."privacy.pstpl"), array(), $redata) . "\n";
            }
        }

        // <-- START THE SURVEY -->
        if ($surveyMode != 'survey')
        {
            /* Why survey.pstpl is not included in all in one mode ?*/
            echo templatereplace(file_get_contents($sTemplateViewPath."survey.pstpl"), array(), $redata);
        }

        // runonce element has been changed from a hidden to a text/display:none one. In order to workaround an not-reproduced issue #4453 (lemeur)
        // We don't need runonce actually (140228): the script was updated and replaced by EM see #08783 (grep show no other runonce)
        // echo "<input type='text' id='runonce' value='0' style='display: none;'/>";

        $showpopups=Yii::app()->getConfig('showpopups');

        //Display the "mandatory" message on page if necessary
        if (!$showpopups && $stepInfo['mandViolation'] && $okToShowErrors)
        {
            echo "<p class='errormandatory alert alert-danger' role='alert'>" . gT("One or more mandatory questions have not been answered. You cannot proceed until these have been completed.") . "</p>";
        }

        //Display the "validation" message on page if necessary
        if (!$showpopups && !$stepInfo['valid'] && $okToShowErrors)
        {
            echo "<p class='errormandatory alert alert-danger' role='alert'>" . gT("One or more questions have not been answered in a valid manner. You cannot proceed until these answers are valid.") . "</p>";
        }

        //Display the "file validation" message on page if necessary
        if (!$showpopups && isset($filenotvalidated) && $filenotvalidated == true && $okToShowErrors)
        {
            echo "<p class='errormandatory alert alert-danger' role='alert'>" . gT("One or more uploaded files are not in proper format/size. You cannot proceed until these files are valid.") . "</p>";
        }

        $_gseq = -1;
        foreach ($_SESSION[$LEMsessid]['grouplist'] as $gl)
        {
            $gid = $gl['gid'];
            ++$_gseq;
            $groupname = $gl['group_name'];
            $groupdescription = $gl['description'];

            if ($surveyMode != 'survey' && $gid != $onlyThisGID)
            {
                continue;
            }

            $redata = compact(array_keys(get_defined_vars()));
            Yii::app()->setConfig('gid',$gid);// To be used in templaterplace in whole group. Attention : it's the actual GID (not the GID of the question)
            echo "\n\n<!-- START THE GROUP (in SurveyRunTime ) -->\n";
            echo "\n\n<div id='group-$_gseq'";
            $gnoshow = LimeExpressionManager::GroupIsIrrelevantOrHidden($_gseq);
            if  ($gnoshow && !$previewgrp)
            {
                echo " class='hidden'";
            }
            echo ">\n";
            echo templatereplace(file_get_contents($sTemplateViewPath."startgroup.pstpl"), array(), $redata);
            echo "\n";

            $showgroupinfo_global_ = getGlobalSetting('showgroupinfo');
            $aSurveyinfo = getSurveyInfo($surveyid);

            // Look up if there is a global Setting to hide/show the Questiongroup => In that case Globals will override Local Settings
            if(($aSurveyinfo['showgroupinfo'] == $showgroupinfo_global_) || ($showgroupinfo_global_ == 'choose')){
                $showgroupinfo_ = $aSurveyinfo['showgroupinfo'];
            } else {
                $showgroupinfo_ = $showgroupinfo_global_;
            }

            $showgroupdesc_ = $showgroupinfo_ == 'B' /* both */ || $showgroupinfo_ == 'D'; /* (group-) description */

            if (!$previewquestion && trim($redata['groupdescription'])!="" && $showgroupdesc_)
            {
                echo templatereplace(file_get_contents($sTemplateViewPath."groupdescription.pstpl"), array(), $redata);
            }
            echo "\n";

            echo "\n\n<!-- PRESENT THE QUESTIONS (in SurveyRunTime )  -->\n";

            foreach ($qanda as $qa) // one entry per QID
            {
                // Test if finalgroup is in this qid (for all in one survey, else we do only qanda for needed question (in one by one or group by goup)
                if ($gid != $qa['finalgroup']) {
                    continue;
                }
                $qid = $qa[4];
                $qinfo = LimeExpressionManager::GetQuestionStatus($qid);
                $lastgrouparray = explode("X", $qa[7]);
                $lastgroup = $lastgrouparray[0] . "X" . $lastgrouparray[1]; // id of the last group, derived from question id
                $lastanswer = $qa[7];




                $n_q_display = '';
                if ($qinfo['hidden'] && $qinfo['info']['type'] != '*')
                {
                    continue; // skip this one
                }


                $aReplacement=array();
                $question = $qa[0];
                //===================================================================
                // The following four variables offer the templating system the
                // capacity to fully control the HTML output for questions making the
                // above echo redundant if desired.
                $question['sgq'] = $qa[7];
                $question['aid'] = !empty($qinfo['info']['aid']) ? $qinfo['info']['aid'] : 0;
                $question['sqid'] = !empty($qinfo['info']['sqid']) ? $qinfo['info']['sqid'] : 0;
                //===================================================================

                $question_template = file_get_contents($sTemplateViewPath.'question.pstpl');
                // Fix old template : can we remove it ? Old template are surely already broken by another issue
                if (preg_match('/\{QUESTION_ESSENTIALS\}/', $question_template) === false || preg_match('/\{QUESTION_CLASS\}/', $question_template) === false)
                {
                    // if {QUESTION_ESSENTIALS} is present in the template but not {QUESTION_CLASS} remove it because you don't want id="" and display="" duplicated.
                    $question_template = str_replace('{QUESTION_ESSENTIALS}', '', $question_template);
                    $question_template = str_replace('{QUESTION_CLASS}', '', $question_template);
                    $question_template ="<div {QUESTION_ESSENTIALS} class='{QUESTION_CLASS} {QUESTION_MAN_CLASS} {QUESTION_INPUT_ERROR_CLASS}'"
                                        . $question_template
                                        . "</div>";
                }
                $redata = compact(array_keys(get_defined_vars()));
                $aQuestionReplacement=$this->getQuestionReplacement($qa);
                echo templatereplace($question_template, $aQuestionReplacement, $redata, false, false, $qa[4]);

            }
            if (!empty($qanda))
            {
                if ($surveyMode == 'group') {
                    echo "<input type='hidden' name='lastgroup' value='$lastgroup' id='lastgroup' />\n"; // for counting the time spent on each group
                }
                if ($surveyMode == 'question') {
                    echo "<input type='hidden' name='lastanswer' value='$lastanswer' id='lastanswer' />\n";
                }
            }

            echo "\n\n<!-- END THE GROUP -->\n";
            echo templatereplace(file_get_contents($sTemplateViewPath."endgroup.pstpl"), array(), $redata);
            echo "\n\n</div>\n";
            Yii::app()->setConfig('gid','');
        }

        LimeExpressionManager::FinishProcessingGroup($LEMskipReprocessing);
        echo LimeExpressionManager::GetRelevanceAndTailoringJavaScript();
        LimeExpressionManager::FinishProcessingPage();

        /**
        * Navigator
        */
        if (!$previewgrp && !$previewquestion)
        {
            $aNavigator = surveymover();
            $moveprevbutton = $aNavigator['sMovePrevButton'];
            $movenextbutton = $aNavigator['sMoveNextButton'];
            $navigator = $moveprevbutton.' '.$movenextbutton;

            $redata = compact(array_keys(get_defined_vars()));

            echo "\n\n<!-- PRESENT THE NAVIGATOR -->\n";
            echo templatereplace(file_get_contents($sTemplateViewPath."navigator.pstpl"), array(), $redata);
            echo "\n";

            if ($thissurvey['active'] != "Y")
            {
                echo "<p style='text-align:center' class='error'>" . gT("This survey is currently not active. You will not be able to save your responses.") . "</p>\n";
            }

            echo "<!-- generated in SurveyRuntimeHelper -->";
            echo "<input type='hidden' name='thisstep' value='{$_SESSION[$LEMsessid]['step']}' id='thisstep' />\n";
            echo "<input type='hidden' name='sid' value='$surveyid' id='sid' />\n";
            echo "<input type='hidden' name='start_time' value='" . time() . "' id='start_time' />\n";
            $_SESSION[$LEMsessid]['LEMpostKey'] = mt_rand();
            echo "<input type='hidden' name='LEMpostKey' value='{$_SESSION[$LEMsessid]['LEMpostKey']}' id='LEMpostKey' />\n";

            if (isset($token) && !empty($token))
            {
                echo "\n<input type='hidden' name='token' value='$token' id='token' />\n";
            }
        }

        if (($LEMdebugLevel & LEM_DEBUG_TIMING) == LEM_DEBUG_TIMING)
        {
            echo LimeExpressionManager::GetDebugTimingMessage();
        }
        if (($LEMdebugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY)
        {
            echo "<table><tr><td align='left'><b>Group/Question Validation Results:</b>" . $moveResult['message'] . "</td></tr></table>\n";
        }
        echo "</form>\n";

        echo templatereplace(file_get_contents($sTemplateViewPath."endpage.pstpl"), array(), $redata);

        echo "\n";

        doFooter();

    }

    /**
    * setJavascriptVar
    *
    * @return @void
    * @param integer $iSurveyId : the survey id for the script
    */
    public function setJavascriptVar($iSurveyId)
    {
        $aSurveyinfo=getSurveyInfo($iSurveyId, App()->getLanguage());
        if(isset($aSurveyinfo['surveyls_numberformat']))
        {
            $aLSJavascriptVar=array();
            $aLSJavascriptVar['bFixNumAuto']=(int)(bool)Yii::app()->getConfig('bFixNumAuto',1);
            $aLSJavascriptVar['bNumRealValue']=(int)(bool)Yii::app()->getConfig('bNumRealValue',0);
            $aRadix=getRadixPointData($aSurveyinfo['surveyls_numberformat']);
            $aLSJavascriptVar['sLEMradix']=$aRadix['separator'];
            $aLSJavascriptVar['lang']=new stdClass; // To add more easily some lang string here
            $aLSJavascriptVar['showpopup']=(int)Yii::app()->getConfig('showpopups');
            $aLSJavascriptVar['startPopups']=new stdClass;
            $sLSJavascriptVar="LSvar=".json_encode($aLSJavascriptVar) . ';';
            /*
            $aCfieldnameWithDependences = Condition::model()->getAllCfieldnameWithDependenciesForOneSurvey($iSurveyId);
            foreach($aCfieldnameWithDependences as $sCfieldname)
            {
                $aLSJavascriptVar['aFieldWithDependencies'][] = $sCfieldname;
            }
            */

            $sLSJavascriptVar="LSvar=".json_encode($aLSJavascriptVar) . ';';
            App()->clientScript->registerScript('sLSJavascriptVar',$sLSJavascriptVar,CClientScript::POS_HEAD);
        }
        // Maybe remove one from index and allow empty $surveyid here.
    }

    /**
    * Construction of replacement array, actually doing it with redata
    *
    * @param $aQuestionQanda : array from qanda helper
    * @return aray of replacement for question.psptl
    **/
    public static function getQuestionReplacement($aQuestionQanda)
    {

        // Get the default replacement and set empty value by default
        $aReplacement=array(
            "QID"=>"",
            //"GID"=>"", // Attention : set in replacement helper too (by gid).
            "SGQ"=>"",
            "AID"=>"",
            "QUESTION_CODE"=>"",
            "QUESTION_NUMBER"=>"",
            "QUESTION"=>"",
            "QUESTION_TEXT"=>"",
            "QUESTIONHELP"=>"", // User help
            "QUESTIONHELPPLAINTEXT"=>"",
            "QUESTION_CLASS"=>"",
            "QUESTION_MAN_CLASS"=>"",
            "QUESTION_INPUT_ERROR_CLASS"=>"",
            "ANSWER"=>"",
            "QUESTION_HELP"=>"", // Core help
            "QUESTION_VALID_MESSAGE"=>"",
            "QUESTION_FILE_VALID_MESSAGE"=>"",
            "QUESTION_MAN_MESSAGE"=>"",
            "QUESTION_MANDATORY"=>"",
            "QUESTION_ESSENTIALS"=>"",
        );
        if(!is_array($aQuestionQanda) || empty($aQuestionQanda[0]))
        {
            return $aReplacement;
        }
        $iQid=$aQuestionQanda[4];
        $lemQuestionInfo = LimeExpressionManager::GetQuestionStatus($iQid);
        $iSurveyId=Yii::app()->getConfig('surveyID');// Or : by SGQA of question ? by Question::model($iQid)->sid;
        $oSurveyId=Survey::model()->findByPk($iSurveyId);
        $sType=$lemQuestionInfo['info']['type'];

        // Core value : not replaced
        $aReplacement['QID']=$iQid;
        $aReplacement['GID']=$aQuestionQanda[6];// Not sure for aleatory : it's the real gid or the updated gid ? We need original gid or updated gid ?
        $aReplacement['SGQ']=$aQuestionQanda[7];
        $aReplacement['AID']=isset($aQuestionQanda[0]['aid']) ? $aQuestionQanda[0]['aid'] : "" ;
        $sCode=$aQuestionQanda[5];
        $iNumber=$aQuestionQanda[0]['number'];

        $showqnumcode_global_ = getGlobalSetting('showqnumcode');
        $aSurveyinfo = getSurveyInfo($iSurveyId);

        // Check global setting to see if survey level setting should be applied
        if($showqnumcode_global_ == 'choose') { // Use survey level settings
            $showqnumcode_ = $aSurveyinfo['showqnumcode']; //B, N, C, or X
        } else { // Use global setting
            $showqnumcode_ = $showqnumcode_global_; //both, number, code, or none
        }

        switch ($showqnumcode_)
        {
            case 'both':
            case 'B': // Both
                $aReplacement['QUESTION_CODE']=$sCode;
                $aReplacement['QUESTION_NUMBER']=$iNumber;
                break;
            case 'number':
            case 'N': // Number only
                $aReplacement['QUESTION_CODE']="";
                $aReplacement['QUESTION_NUMBER']=$iNumber;
                break;
            case 'code':
            case 'C': // Code only
                $aReplacement['QUESTION_CODE']=$sCode;
                $aReplacement['QUESTION_NUMBER']="";
                break;
            case 'none':
            case 'X':
            default: // Neither
                $aReplacement['QUESTION_CODE']="";
                $aReplacement['QUESTION_NUMBER']="";
                break;
        }

        $aReplacement['QUESTION']=$aQuestionQanda[0]['all'] ; // Deprecated : only used in old template (very old)
        // Core value : user text
        $aReplacement['QUESTION_TEXT'] = $aQuestionQanda[0]['text'];
        $aReplacement['QUESTIONHELP']=$lemQuestionInfo['info']['help'];// User help
        // To be moved in a extra plugin : QUESTIONHELP img adding
        $sTemplateDir=Template::model()->getTemplatePath($oSurveyId->template);
        $sTemplateUrl=Template::model()->getTemplateURL($oSurveyId->template);
        if(flattenText($aReplacement['QUESTIONHELP'], true,true) != '')
        {
            $aReplacement['QUESTIONHELP']= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/questionhelp', array('questionHelp'=>$aReplacement['QUESTIONHELP']), true);;

        }
        // Core value :the classes
        $aReplacement['QUESTION_CLASS'] = Question::getQuestionClass($sType);

        //get additional question classes from question attribute
        $aQuestionAttributes = getQuestionAttributeValues($aQuestionQanda[4]);

        //add additional classes
        if(isset($aQuestionAttributes['cssclass']))
        {
            $aReplacement['QUESTION_CLASS'] .= " ".$aQuestionAttributes['cssclass'];
        }

        $aMandatoryClass = array();
        if ($lemQuestionInfo['info']['mandatory'] == 'Y')// $aQuestionQanda[0]['mandatory']=="*"
        {
            $aMandatoryClass[]= 'mandatory';
        }
        if ($lemQuestionInfo['anyUnanswered'] && $_SESSION['survey_' . $iSurveyId]['maxstep'] != $_SESSION['survey_' . $iSurveyId]['step'])// This is working ?
        {
            $aMandatoryClass[]= 'missing';
        }
        $aReplacement['QUESTION_MAN_CLASS']=!empty($aMandatoryClass) ? " ".implode(" ",$aMandatoryClass) : "";
        $aReplacement['QUESTION_INPUT_ERROR_CLASS']=$aQuestionQanda[0]['input_error_class'];
        // Core value : LS text : EM and not
        $aReplacement['ANSWER']=$aQuestionQanda[1];
        $aReplacement['QUESTION_HELP']=$aQuestionQanda[0]['help'];// Core help only, not EM
        $aReplacement['QUESTION_VALID_MESSAGE']=$aQuestionQanda[0]['valid_message'];// $lemQuestionInfo['validTip']
        $aReplacement['QUESTION_FILE_VALID_MESSAGE']=$aQuestionQanda[0]['file_valid_message'];// $lemQuestionInfo['??']
        $aReplacement['QUESTION_MAN_MESSAGE']=$aQuestionQanda[0]['man_message'];
        $aReplacement['QUESTION_MANDATORY']=$aQuestionQanda[0]['mandatory'];
        // For QUESTION_ESSENTIALS
        $aHtmlOptions=array();
        if ((!$lemQuestionInfo['relevant']) || ($lemQuestionInfo['hidden']))// && $lemQuestionInfo['info']['type'] == '*'))
        {
            $aHtmlOptions['style'] = 'display: none;';
        }

        // Launch the event
        $event = new PluginEvent('beforeQuestionRender');
        // Some helper
        $event->set('surveyId', $iSurveyId);
        $event->set('type', $sType);
        $event->set('code', $sCode);
        $event->set('qid', $iQid);
        $event->set('gid', $aReplacement['GID']);
        // User text
        $event->set('text', $aReplacement['QUESTION_TEXT']);
        $event->set('questionhelp', $aReplacement['QUESTIONHELP']);
        // The classes
        $event->set('class', $aReplacement['QUESTION_CLASS']);
        $event->set('man_class', $aReplacement['QUESTION_MAN_CLASS']);
        $event->set('input_error_class', $aReplacement['QUESTION_INPUT_ERROR_CLASS']);
        // LS core text
        $event->set('answers', $aReplacement['ANSWER']);
        $event->set('help', $aReplacement['QUESTION_HELP']);
        $event->set('man_message', $aReplacement['QUESTION_MAN_MESSAGE']);
        $event->set('valid_message', $aReplacement['QUESTION_VALID_MESSAGE']);
        $event->set('file_valid_message', $aReplacement['QUESTION_FILE_VALID_MESSAGE']);
        // htmlOptions for container
        $event->set('aHtmlOptions', $aHtmlOptions);

        App()->getPluginManager()->dispatchEvent($event);
        // User text
        $aReplacement['QUESTION_TEXT'] = $event->get('text');
        $aReplacement['QUESTIONHELP'] = $event->get('questionhelp');
        $aReplacement['QUESTIONHELPPLAINTEXT']=strip_tags(addslashes($aReplacement['QUESTIONHELP']));
        // The classes
        $aReplacement['QUESTION_CLASS'] = $event->get('class');
        $aReplacement['QUESTION_MAN_CLASS'] = $event->get('man_class');
        $aReplacement['QUESTION_INPUT_ERROR_CLASS'] = $event->get('input_error_class');
        // LS core text
        $aReplacement['ANSWER'] = $event->get('answers');
        $aReplacement['QUESTION_HELP'] = $event->get('help');
        $aReplacement['QUESTION_MAN_MESSAGE'] = $event->get('man_message');
        $aReplacement['QUESTION_VALID_MESSAGE'] = $event->get('valid_message');
        $aReplacement['QUESTION_FILE_VALID_MESSAGE'] = $event->get('file_valid_message');
        $aReplacement['QUESTION_MANDATORY'] = $event->get('mandatory',$aReplacement['QUESTION_MANDATORY']);
        // Always add id for QUESTION_ESSENTIALS
        $aHtmlOptions['id']="question{$iQid}";
        $aReplacement['QUESTION_ESSENTIALS']=CHtml::renderAttributes($aHtmlOptions);

        return $aReplacement;
    }
}
