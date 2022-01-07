<?php

/*
 * LimeSurvey
 * Copyright (C) 2013 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 *
 *
 */

/**
 * optin
 *
 * @package LimeSurvey
 * @copyright 2011
 * @access public
 */
class OptinController extends LSYii_Controller
{
    public $layout = 'bare';
    public $defaultAction = 'tokens';

    /**
     * Display the confirmation for individual survey opt in
     */
    public function actiontokens()
    {
        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('sanitize');

        $surveyId = Yii::app()->request->getQuery('surveyid');
        $languageCode = Yii::app()->request->getQuery('langcode');
        $accessToken = Token::sanitizeToken(Yii::app()->request->getQuery('token'));

        //IF there is no survey id, redirect back to the default public page
        if (!$surveyId) {
            $this->redirect(['/']);
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

        if (empty($survey) || !$survey->hasTokensTable) {
            throw new CHttpException(404, "This survey does not seem to exist. It may have been deleted or the link you were given is outdated or incorrect.");
        } else {
            LimeExpressionManager::singleton()->loadTokenInformation($surveyId, $accessToken, false);
            $token = Token::model($surveyId)->findByAttributes(['token' => $accessToken]);

            if (!isset($token)) {
                $message = gT('You are not a participant of this survey.');
            } else {
                if ($token->emailstatus != 'OptOut') {
                    $message = gT('You are already a participant of this survey.');
                } else {
                    $message = "<p>" . gT('Please confirm that you want to be added back to this survey by clicking the button below.') . '<br>' . gT("After confirmation you may start receiving invitations and reminders for this survey.") . "</p>";
                    $message .= '<p><a href="' . Yii::app()->createUrl('optin/addtokens', ['surveyid' => $surveyId, 'langcode' => $baseLanguage, 'token' => $accessToken]) . '" class="btn btn-default btn-lg">' . gT("I confirm") . '</a><p>';
                }
            }
        }

        $this->renderHtml($message, $survey);
    }

    /**
     * Display the confirmation for global opt in
     */
    public function actionparticipants()
    {
        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('sanitize');

        $surveyId = Yii::app()->request->getQuery('surveyid');
        $languageCode = Yii::app()->request->getQuery('langcode');
        $accessToken = Token::sanitizeToken(Yii::app()->request->getQuery('token'));

        //IF there is no survey id, redirect back to the default public page
        if (!$surveyId) {
            $this->redirect(['/']);
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

        if (empty($survey) || !$survey->hasTokensTable) {
            throw new CHttpException(404, "This survey does not seem to exist. It may have been deleted or the link you were given is outdated or incorrect.");
        } else {
            LimeExpressionManager::singleton()->loadTokenInformation($surveyId, $accessToken, false);
            $token = Token::model($surveyId)->findByAttributes(['token' => $accessToken]);

            if (!isset($token)) {
                $message = "<p>" . gT('You are not a participant of this survey.') . "</p>";
            } else {
                $optedOutFromSurvey = substr($token->emailstatus, 0, strlen('OptOut')) == 'OptOut';

                $blacklistHandler = new LimeSurvey\Models\Services\ParticipantBlacklistHandler();
                $participant = $blacklistHandler->getCentralParticipantFromToken($token);
                $isBlacklisted = !empty($participant) && $participant->blacklisted == 'Y';

                if (!Yii::app()->getConfig('allowunblacklist') == "Y") {
                    $message = "<p>" . gT('Removing yourself from the blacklist is currently disabled.') . "</p>";
                } elseif ($isBlacklisted) {
                    $message = "<p>" . gT('Please confirm that you want to be added back to the central participants list for this site.') . "</p>";
                    $message .= '<p><a href="' . Yii::app()->createUrl('optin/addtokens', ['surveyid' => $surveyId, 'langcode' => $baseLanguage, 'token' => $accessToken, 'global' => true]) . '" class="btn btn-default btn-lg">' . gT("I confirm") . '</a><p>';
                } elseif ($optedOutFromSurvey) {
                    $message = "<p>" . gT('Please confirm that you want to be added back to this survey by clicking the button below.') . '<br>' . gT("After confirmation you may start receiving invitations and reminders for this survey.") . "</p>";
                    $message .= '<p><a href="' . Yii::app()->createUrl('optin/addtokens', ['surveyid' => $surveyId, 'langcode' => $baseLanguage, 'token' => $accessToken]) . '" class="btn btn-default btn-lg">' . gT("I confirm") . '</a><p>';
                } elseif (empty($participant)) {
                    $message = "<p>" . gT('You are already a participant of this survey.') . "</p>";
                } else {
                    $message = "<p>" . gT('You are already part of the central participants list for this site.') . "</p>";
                }
            }
        }

        $this->renderHtml($message, $survey);
    }

    /**
     * Add token back to the survey (remove 'OptOut' status) and/or add participant back to the CPDB (remove from blacklist).
     * The participant is only removed from the blacklist if the 'global' URL param is true and 'allowunblacklist' is enabled.
     */
    public function actionaddtokens()
    {
        $surveyId = Yii::app()->request->getQuery('surveyid');
        $languageCode = Yii::app()->request->getQuery('langcode');
        $accessToken = Token::sanitizeToken(Yii::app()->request->getQuery('token'));
        $global = Yii::app()->request->getQuery('global');

        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('sanitize');

        if (!$surveyId) {
            $this->redirect(['/']);
        }
        $surveyId = (int) $surveyId; //Make sure it's an integer (protect from SQL injects)
        $survey = Survey::model()->findByPk($surveyId);

        //Check that there is a SID
        // Get passed language from form, so that we dont loose this!
        if (!isset($languageCode) || $languageCode == "" || !$languageCode) {
            $baseLanguage = $survey->language;
        } else {
            $baseLanguage = sanitize_languagecode($languageCode);
        }

        Yii::app()->setLanguage($baseLanguage);

        if (empty($survey) || !$survey->hasTokensTable) {
            throw new CHttpException(404, "This survey does not seem to exist. It may have been deleted or the link you were given is outdated or incorrect.");
        } else {
            LimeExpressionManager::singleton()->loadTokenInformation($surveyId, $accessToken, false);
            $token = Token::model($surveyId)->findByAttributes(['token' => $accessToken]);

            if (!isset($token)) {
                $message = gT('You are not a participant of this survey.');
            } else {
                if ($token->emailstatus == 'OptOut') {
                    $token->emailstatus = 'OK';
                    $token->save();
                    $message = gT('You have been successfully added back to this survey.');
                } elseif ($token->emailstatus == 'OK') {
                    $message = gT('You are already a participant of this survey.');
                } else {
                    $message = gT('You have been already removed from this survey.');
                }
                // If the $global param is true and 'allowunblacklist' is enabled, remove from the blacklist
                if ($global && Yii::app()->getConfig('allowunblacklist') == "Y") {
                    $blacklistHandler = new LimeSurvey\Models\Services\ParticipantBlacklistHandler();
                    $blacklistResult = $blacklistHandler->removeFromBlacklist($token);
                    if (!$blacklistResult->isBlacklisted()) {
                        foreach ($blacklistResult->getMessages() as $blacklistMessage) {
                            $message .= "<br>" . $blacklistMessage;
                        }
                    }
                }
            }
        }

        $this->renderHtml($message, $survey);
    }

    /**
     * Render stuff
     *
     * @param string $html
     * @param Survey $survey
     * @return void
     */
    private function renderHtml($html, $survey)
    {
        $aSurveyInfo = getSurveyInfo($survey->primaryKey);

        $aSurveyInfo['include_content'] = 'optin';
        $aSurveyInfo['optin_message'] = $html;
        $aSurveyInfo['aCompleted'] = true;  // Avoid showing the progress bar
        Template::getInstance('', $survey->primaryKey);

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
