<?php

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

/**
 * optout
 *
 * @package LimeSurvey
 * @copyright 2011
 * @access public
 */
class OptoutController extends LSYii_Controller
{
    public $layout = 'bare';
    public $defaultAction = 'tokens';

    /**
     * Display the confirmation for individual survey opt out
     */
    public function actiontokens()
    {
        $iSurveyID     = Yii::app()->request->getQuery('surveyid');
        $sLanguageCode = Yii::app()->request->getQuery('langcode');
        $sToken        = Token::sanitizeToken(Yii::app()->request->getQuery('token'));

        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('sanitize');

        //IF there is no survey id, redirect back to the default public page
        if (!$iSurveyID) {
            $this->redirect(array('/'));
        }

        $iSurveyID = (int) $iSurveyID; //Make sure it's an integer (protect from SQL injects)
        $oSurvey       = Survey::model()->findByPk($iSurveyID);
        //Check that there is a SID
        // Get passed language from form, so that we dont lose this!
        if (!isset($sLanguageCode) || $sLanguageCode == "" || !$sLanguageCode) {
            $sBaseLanguage = $oSurvey->language;
        } else {
            $sBaseLanguage = sanitize_languagecode($sLanguageCode);
        }

        Yii::app()->setLanguage($sBaseLanguage);

        $aSurveyInfo = getSurveyInfo($iSurveyID, $sBaseLanguage);

        if ($aSurveyInfo == false || !tableExists("{{tokens_{$iSurveyID}}}")) {
            throw new CHttpException(404, "The survey in which you are trying to participate does not seem to exist. It may have been deleted or the link you were given is outdated or incorrect.");
        } else {
            $oToken = Token::model($iSurveyID)->findByAttributes(array('token' => $sToken));
            if (substr($oToken->emailstatus, 0, strlen('OptOut')) == 'OptOut') {
                $sMessage = "<p>" . gT('You have already been removed from this survey.') . "</p>";
            } else {
                $sMessage = "<p>" . gT('Please confirm that you want to opt out of this survey by clicking the button below.') . '<br>' . gT("After confirmation you won't receive any invitations or reminders for this survey anymore.") . "</p>";
                $sMessage .= '<p><a href="' . Yii::app()->createUrl('optout/removetokens', array('surveyid' => $iSurveyID, 'langcode' => $sBaseLanguage, 'token' => $sToken)) . '" class="btn btn-default btn-lg">' . gT("I confirm") . '</a><p>';
            }
            $this->renderHtml($sMessage, $aSurveyInfo, $iSurveyID);
        }
    }

    /**
     * Display the confirmation for global opt out
     */
    public function actionparticipants()
    {
        $surveyId = Yii::app()->request->getQuery('surveyid');
        $languageCode = Yii::app()->request->getQuery('langcode');
        $accessToken = Token::sanitizeToken(Yii::app()->request->getQuery('token'));

        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('sanitize');

        //IF there is no survey id, redirect back to the default public page
        if (!$surveyId) {
            $this->redirect(array('/'));
        }

        $surveyId = (int) $surveyId; //Make sure it's an integer (protect from SQL injects)
        $survey = Survey::model()->findByPk($surveyId);
        //Check that there is a SID
        // Get passed language from form, so that we dont lose this!
        if (!isset($languageCode) || $languageCode == "" || !$languageCode) {
            $baseLanguage = $survey->language;
        } else {
            $baseLanguage = sanitize_languagecode($languageCode);
        }

        Yii::app()->setLanguage($baseLanguage);

        $surveyInfo = getSurveyInfo($surveyId, $baseLanguage);

        if ($surveyInfo == false || !tableExists("{{tokens_{$surveyId}}}")) {
            throw new CHttpException(404, "The survey does not seem to exist. It may have been deleted or the link you were given is outdated or incorrect.");
        } else {
            $oToken = Token::model($surveyId)->findByAttributes(array('token' => $accessToken));
            $optedOutFromSurvey = substr($oToken->emailstatus, 0, strlen('OptOut')) == 'OptOut';

            $blacklistHandler = new LimeSurvey\Models\Services\ParticipantBlacklistHandler();
            $participant = $blacklistHandler->getCentralParticipantFromToken($oToken);

            if (!empty($participant) && $participant->blacklisted != 'Y') {
                $message = "<p>" . gT('Please confirm that you want to be removed from the central participants list for this site.') . "</p>";
                $message .= '<p><a href="' . Yii::app()->createUrl('optout/removetokens', array('surveyid' => $surveyId, 'langcode' => $baseLanguage, 'token' => $accessToken, 'global' => true)) . '" class="btn btn-default btn-lg">' . gT("I confirm") . '</a><p>';
            } elseif (!$optedOutFromSurvey) {
                $message = "<p>" . gT('Please confirm that you want to opt out of this survey by clicking the button below.') . '<br>' . gT("After confirmation you won't receive any invitations or reminders for this survey anymore.") . "</p>";
                $message .= '<p><a href="' . Yii::app()->createUrl('optout/removetokens', array('surveyid' => $surveyId, 'langcode' => $baseLanguage, 'token' => $accessToken)) . '" class="btn btn-default btn-lg">' . gT("I confirm") . '</a><p>';
            } else {
                $message = "<p>" . gT('You have already been removed from the central participants list for this site.') . "</p>";
            }

            $this->renderHtml($message, $surveyInfo, $surveyId);
        }
    }

    /**
     * This function is run when opting out of an individual survey participants table. The other function /optout/participants
     * opts the user out of ALL survey invitations from the system
     */
    public function actionremovetokens()
    {
        $surveyId = Yii::app()->request->getQuery('surveyid');
        $language = Yii::app()->request->getQuery('langcode');
        $accessToken = Token::sanitizeToken(Yii::app()->request->getQuery('token'));
        $global = Yii::app()->request->getQuery('global');

        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('sanitize');

        // If there is no survey id, redirect back to the default public page
        if (!$surveyId) {
            $this->redirect(['/']);
        }

        // Make sure it's an integer (protect from SQL injects)
        $surveyId = (int) $surveyId;
        $survey = Survey::model()->findByPk($surveyId);

        // Get passed language from form, so that we dont lose this!
        if (!isset($language) || $language == "" || !$language) {
            $baseLanguage = $survey->language;
        } else {
            $baseLanguage = sanitize_languagecode($language);
        }

        Yii::app()->setLanguage($baseLanguage);

        $surveyInfo = getSurveyInfo($surveyId, $baseLanguage);

        if ($surveyInfo == false || !tableExists("{{tokens_{$surveyId}}}")) {
            throw new CHttpException(404, "The survey in which you are trying to participate does not seem to exist. It may have been deleted or the link you were given is outdated or incorrect.");
        } else {
            LimeExpressionManager::singleton()->loadTokenInformation($surveyId, $accessToken, false);
            $token = Token::model($surveyId)->findByAttributes(['token' => $accessToken]);

            if (!isset($token)) {
                $message = gT('You are not a participant in this survey.');
            } else {
                if (substr($token->emailstatus, 0, strlen('OptOut')) !== 'OptOut') {
                    $token->emailstatus = 'OptOut';
                    $token->save();
                    $message = gT('You have been successfully removed from this survey.');
                } else {
                    $message = gT('You have already been removed from this survey.');
                }
                if ($global) {
                    $blacklistHandler = new LimeSurvey\Models\Services\ParticipantBlacklistHandler();
                    $blacklistResult = $blacklistHandler->addToBlacklist($token);
                    if ($blacklistResult->isBlacklisted()) {
                        foreach ($blacklistResult->getMessages() as $blacklistMessage) {
                            $message .= "<br>" . $blacklistMessage;
                        }
                    }
                }
            }
        }

        $this->renderHtml($message, $surveyInfo, $surveyId);
    }

    /**
     * Render something
     *
     * @param string $html
     * @param array $aSurveyInfo
     * @param int $iSurveyID
     * @return void
     */
    private function renderHtml($html, $aSurveyInfo, $iSurveyID)
    {
        $survey = Survey::model()->findByPk($iSurveyID);

        $aSurveyInfo['include_content'] = 'optout';
        $aSurveyInfo['optin_message'] = $html;
        $aSurveyInfo['aCompleted'] = true;  // Avoid showing the progress bar
        Template::model()->getInstance('', $iSurveyID);

        Yii::app()->twigRenderer->renderTemplateFromFile(
            "layout_global.twig",
            array(
                'oSurvey'     => $survey,
                'aSurveyInfo' => $aSurveyInfo
            ),
            false
        );
        Yii::app()->end();
    }
}
