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
*/

use LimeSurvey\Helpers\questionHelper;

use LimeSurvey\Models\Services\Exception\{
    ExceptionPersistError,
    ExceptionNotFound,
    ExceptionPermissionDenied
};

/**
* Database
*
* @package LimeSurvey
* @author
* @copyright 2011
* @access public
*/
class Database extends SurveyCommonAction
{
    /**
     * @var integer Group id
     */
    private $iQuestionGroupID;

    /**
     * @var integer Question id
     */
    private $iQuestionID;

    /**
     * @var integer Survey id
     */
    private $iSurveyID;


    private $updateableFields = [
                'owner_id' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'admin' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'format' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'expires' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'startdate' => ['type' => 'default', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'template' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'assessments' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'anonymized' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'savetimings' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'datestamp' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'ipaddr' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'ipanonymize' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'refurl' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'publicgraphs' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'usecookie' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'allowregister' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'allowsave' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'navigationdelay' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'printanswers' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'publicstatistics' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'autoredirect' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'showxquestions' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'showgroupinfo' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'showqnumcode' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'shownoanswer' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'showwelcome' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'showsurveypolicynotice' => ['type' => '', 'default' => 0, 'dbname' => false, 'active' => true, 'required' => []],
                'allowprev' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'questionindex' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'nokeyboard' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'showprogress' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'listpublic' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'htmlemail' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'sendconfirmation' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'tokenanswerspersistence' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'alloweditaftercompletion' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'emailresponseto' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'emailnotificationto' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'googleanalyticsapikeysetting' => ['type' => 'default', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'googleanalyticsapikey' => ['type' => 'default', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'googleanalyticsstyle' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'tokenlength' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'adminemail' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'bounce_email' => ['type' => '', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'gsid' => ['type' => '', 'default' => 1, 'dbname' => false, 'active' => true, 'required' => []],
                'usecaptcha_surveyaccess' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'usecaptcha_registration' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
                'usecaptcha_saveandload' => ['type' => 'yesno', 'default' => false, 'dbname' => false, 'active' => true, 'required' => []],
            ];
        private $updatedFields = [];

    /**
     * @var object LSYii_Validators
     * @todo : use model (and validate if we do it in model rules)
     */
    private $oFixCKeditor;

    /**
     * Database::index()
     * todo 1591726928167: move called functions to their respective Controllers
     * @return
     */
    public function index()
    {
        $sAction = Yii::app()->request->getPost('action');
        $this->iSurveyID = (isset($_POST['sid'])) ? (int) $_POST['sid'] : (int) returnGlobal('sid');

        $this->iQuestionGroupID = (int) returnGlobal('gid');
        $this->iQuestionID = (int) returnGlobal('qid');

        $this->oFixCKeditor = new LSYii_Validators();
        $this->oFixCKeditor->fixCKeditor = true;
        $this->oFixCKeditor->xssfilter = false;

        if ($sAction == "updatedefaultvalues" && Permission::model()->hasSurveyPermission($this->iSurveyID, 'surveycontent', 'update')) {
            $this->actionUpdateDefaultValues($this->iSurveyID);
        }
        if (($sAction == "updatesurveylocalesettings") && (Permission::model()->hasSurveyPermission($this->iSurveyID, 'surveylocale', 'update') || Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update'))) {
            $this->actionUpdateSurveyLocaleSettings($this->iSurveyID);
        }
        if (
            ($sAction == "updatesurveylocalesettings_generalsettings") &&
            (Permission::model()->hasSurveyPermission($this->iSurveyID, 'surveylocale', 'update') ||
                Permission::model()->hasSurveyPermission($this->iSurveyID, 'surveysettings', 'update'))
        ) {
            $this->actionUpdateSurveyLocaleSettingsGeneralSettings($this->iSurveyID);
        }

        Yii::app()->setFlashMessage(gT("Unknown action or no permission."), 'error');
        $this->getController()->redirect(Yii::app()->request->urlReferrer);
    }

    /**
     * This is a convenience function to update/delete answer default values. If the given
     * $defaultvalue is empty then the entry is removed from table defaultvalues
     *
     * @param integer $qid   Question ID
     * @param integer $scale_id  Scale ID
     * @param string $specialtype  Special type (i.e. for  'Other')
     * @param string $language     Language (defaults are language specific)
     * @param mixed $defaultvalue    The default value itself
     */
    public function updateDefaultValues($qid, $sqid, $scale_id, $specialtype, $language, $defaultvalue)
    {
        $arDefaultValue = DefaultValue::model()
            ->find(
                'specialtype = :specialtype AND qid = :qid AND sqid = :sqid AND scale_id = :scale_id',
                array(
                ':specialtype' => $specialtype,
                ':qid' => $qid,
                ':sqid' => $sqid,
                ':scale_id' => $scale_id,
                )
            );
        $dvid = !empty($arDefaultValue->dvid) ? $arDefaultValue->dvid : null;

        if ($defaultvalue == '') {
            // Remove the default value if it is empty
            if ($dvid !== null) {
                DefaultValueL10n::model()->deleteAllByAttributes(array('dvid' => $dvid, 'language' => $language ));
                $iRowCount = DefaultValueL10n::model()->countByAttributes(array('dvid' => $dvid));
                if ($iRowCount == 0) {
                    DefaultValue::model()->deleteByPk($dvid);
                }
            }
        } else {
            if (is_null($dvid)) {
                $data = array('qid' => $qid, 'sqid' => $sqid, 'scale_id' => $scale_id, 'specialtype' => $specialtype);
                $oDefaultvalue = new DefaultValue();
                $oDefaultvalue->attributes = $data;
                $oDefaultvalue->specialtype = $specialtype;
                $oDefaultvalue->save();
                if (!empty($oDefaultvalue->dvid)) {
                    $dataL10n = array('dvid' => $oDefaultvalue->dvid, 'language' => $language, 'defaultvalue' => $defaultvalue);
                    $oDefaultvalueL10n = new DefaultValueL10n();
                    $oDefaultvalueL10n->attributes = $dataL10n;
                    $oDefaultvalueL10n->save();
                }
            } else {
                if ($dvid !== null) {
                    $arDefaultValue->with('defaultvaluel10ns');
                    $idL10n = !empty($arDefaultValue->defaultvaluel10ns) && array_key_exists($language, $arDefaultValue->defaultvaluel10ns) ? $arDefaultValue->defaultvaluel10ns[$language]->id : null;
                    if ($idL10n !== null) {
                        DefaultValueL10n::model()->updateAll(array('defaultvalue' => $defaultvalue), 'dvid = ' . $dvid . ' AND language = \'' . $language . '\'');
                    } else {
                        $dataL10n = array('dvid' => $dvid, 'language' => $language, 'defaultvalue' => $defaultvalue);
                        $oDefaultvalueL10n = new DefaultValueL10n();
                        $oDefaultvalueL10n->attributes = $dataL10n;
                        $oDefaultvalueL10n->save();
                    }
                }
            }
        }
        $surveyid = $this->iSurveyID;
        updateFieldArray();
    }

    /**
     * action to do when update default value
     * @param integer $iSurveyID
     * @return void (redirect)
     */
    private function actionUpdateDefaultValues($iSurveyID)
    {
        $oSurvey = Survey::model()->findByPk($iSurveyID);
        $aSurveyLanguages = $oSurvey->allLanguages;
        $sBaseLanguage = $oSurvey->language;

        Question::model()->updateAll(array('same_default' => Yii::app()->request->getPost('samedefault') ? 1 : 0), 'sid=:sid ANd qid=:qid', array(':sid' => $iSurveyID, ':qid' => $this->iQuestionID));

        $arQuestion = Question::model()->findByAttributes(array('qid' => $this->iQuestionID));
        $sQuestionType = $arQuestion['type'];

        $questionThemeMetaData = QuestionTheme::findQuestionMetaData($sQuestionType);
        if ((int)$questionThemeMetaData['settings']->answerscales > 0 && $questionThemeMetaData['settings']->subquestions == 0) {
            for ($iScaleID = 0; $iScaleID < (int)$questionThemeMetaData['settings']->answerscales; $iScaleID++) {
                foreach ($aSurveyLanguages as $sLanguage) {
                    if (!is_null(Yii::app()->request->getPost('defaultanswerscale_' . $iScaleID . '_' . $sLanguage))) {
                        $this->updateDefaultValues($this->iQuestionID, 0, $iScaleID, '', $sLanguage, Yii::app()->request->getPost('defaultanswerscale_' . $iScaleID . '_' . $sLanguage));
                    }
                    if (!is_null(Yii::app()->request->getPost('other_' . $iScaleID . '_' . $sLanguage))) {
                        $this->updateDefaultValues($this->iQuestionID, 0, $iScaleID, 'other', $sLanguage, Yii::app()->request->getPost('other_' . $iScaleID . '_' . $sLanguage));
                    }
                }
            }
        }
        if ((int)$questionThemeMetaData['settings']->subquestions > 0) {
            foreach ($aSurveyLanguages as $sLanguage) {
                $arQuestions = Question::model()->with('questionl10ns', array('condition' => 'language = ' . $sLanguage))->findAllByAttributes(array('sid' => $iSurveyID, 'gid' => $this->iQuestionGroupID, 'parent_qid' => $this->iQuestionID, 'scale_id' => 0));

                for ($iScaleID = 0; $iScaleID < (int)$questionThemeMetaData['settings']->subquestions; $iScaleID++) {
                    foreach ($arQuestions as $aSubquestionrow) {
                        if (!is_null(Yii::app()->request->getPost('defaultanswerscale_' . $iScaleID . '_' . $sLanguage . '_' . $aSubquestionrow['qid']))) {
                            $this->updateDefaultValues($this->iQuestionID, $aSubquestionrow['qid'], $iScaleID, '', $sLanguage, Yii::app()->request->getPost('defaultanswerscale_' . $iScaleID . '_' . $sLanguage . '_' . $aSubquestionrow['qid']));
                        }
                    }
                }
            }
        }
        if ($questionThemeMetaData['settings']->answerscales == 0 && $questionThemeMetaData['settings']->subquestions == 0) {
            foreach ($aSurveyLanguages as $sLanguage) {
                // Qick and dirty insert for yes/no defaul value
                // write the the selectbox option, or if "EM" is slected, this value to table
                if ($sQuestionType == 'Y') {
                    /// value for all langs
                    if (Yii::app()->request->getPost('samedefault') == 1) {
                        $sLanguage = $aSurveyLanguages[0]; // turn
                    }

                    if (Yii::app()->request->getPost('defaultanswerscale_0_' . $sLanguage) == 'EM') {
// Case EM, write expression to database
                        $this->updateDefaultValues($this->iQuestionID, 0, 0, '', $sLanguage, Yii::app()->request->getPost('defaultanswerscale_0_' . $sLanguage . '_EM'));
                    } else {
                        // Case "other", write list value to database
                        $this->updateDefaultValues($this->iQuestionID, 0, 0, '', $sLanguage, Yii::app()->request->getPost('defaultanswerscale_0_' . $sLanguage));
                    }
                    ///// end yes/no
                } else {
                    if (!is_null(Yii::app()->request->getPost('defaultanswerscale_0_' . $sLanguage . '_0'))) {
                        $this->updateDefaultValues($this->iQuestionID, 0, 0, '', $sLanguage, Yii::app()->request->getPost('defaultanswerscale_0_' . $sLanguage . '_0'));
                    }
                }
            }
        }
        Yii::app()->session['flashmessage'] = gT("Default value settings were successfully saved.");
        //This is SUPER important! Recalculating the ExpressionScript Engine state!
        LimeExpressionManager::SetDirtyFlag();

        if (Yii::app()->request->getPost('close-after-save') === 'true') {
            $this->getController()->redirect(array('questionAdministration/view/surveyid/' . $iSurveyID . '/gid/' . $this->iQuestionGroupID . '/qid/' . $this->iQuestionID));
        }
        $this->getController()->redirect(['questionAdministration/editdefaultvalues/surveyid/' . $iSurveyID . '/gid/' . $this->iQuestionGroupID . '/qid/' . $this->iQuestionID]);
    }

    /**
     * action to do when update survey seetings + survey language
     * @param integer $iSurveyID
     * @param ?array $input For dependancy injection during testing
     * @return void (redirect)
     */
    private function actionUpdateSurveyLocaleSettings($surveyId, $input = [])
    {
        $diContainer = \LimeSurvey\DI::getContainer();
        $surveyUpdater = $diContainer->get(
            LimeSurvey\Models\Services\SurveyUpdater::class
        );

        $surveyModel = $diContainer->get(Survey::class);

        //@todo  here is something wrong ...
        $oSurvey = $surveyModel->findByPk($surveyId);
        $languageList = $oSurvey->additionalLanguages;
        $languageList[] = $oSurvey->language;

        $request = Yii::app()->request;

        // $input optionally provided to function for unit testing
        // - otherwise we expect data from $_POST
        $post = isset($_POST) ? $_POST : [];
        $input = !empty($input) ? $input : $post;

        // form inputs are named differently from db fields
        // - they have a prefix and a language suffix
        // - we need to convert this to a array of database
        // - fields for each language indexed by lanuage code
        $langFields = [
            'surveyls_url' => 'url_',
            'surveyls_urldescription' => 'urldescrip_',
            'surveyls_title' => 'short_title_',
            'surveyls_alias' => 'alias_',
            'surveyls_description' => 'description_',
            'surveyls_welcometext' => 'welcome_',
            'surveyls_endtext' => 'endtext_',
            'surveyls_policy_notice' => 'datasec_',
            'surveyls_policy_error' => 'datasecerror_',
            'surveyls_policy_notice_label' => 'dataseclabel_',
            'surveyls_dateformat' => 'dateformat_',
            'surveyls_numberformat' => 'numberformat_',
        ];

        foreach ($languageList as $langCode) {
            $langInput = [];
            foreach ($langFields as $field => $inputPrefix) {
                $langInput[$field] = $request->getPost(
                    $inputPrefix . $langCode,
                    null
                );
            }
            if (!empty($langInput)) {
                $input[$langCode] = $langInput;
            }
        }


        $meta = [];
        try {
            $meta = $surveyUpdater->update(
                $surveyId,
                $input
            );
        } catch (ExceptionPersistError $e) {
            Yii::app()->setFlashMessage(
                $e->getMessage(),
                'error'
            );
        }

        LimeExpressionManager::SetDirtyFlag();
        $this->resetEM();

        if (Yii::app()->request->getPost('responsejson', 0) == 1) {
            return Yii::app()->getController()->renderPartial(
                '/admin/super/_renderJson',
                array(
                    'data' => [
                        'success' => true,
                        'updated' => is_array($meta) && !empty($meta['updatedFields']) ? $meta['updatedFields'] : null
                    ],
                ),
                false,
                false
            );
        } else {
            ////////////////////////////////////////
            if (Yii::app()->request->getPost('close-after-save') === 'true') {
                $this->getController()
                    ->redirect(
                        array('surveyAdministration/view/surveyid/' . $surveyId)
                    );
            }

            $referrer = Yii::app()->request->urlReferrer;
            if ($referrer) {
                $this->getController()
                    ->redirect(array($referrer));
            } else {
                $this->getController()
                    ->redirect(array(
                        '/surveyAdministration/rendersidemenulink/subaction/generalsettings/surveyid/' . $surveyId
                    ));
            }
        }
    }

    /*
    private function actionUpdateSurveyLocaleSettings($iSurveyID)
    {

        //@todo  here is something wrong ...
        $oSurvey = Survey::model()->findByPk($iSurveyID);
        $languagelist = $oSurvey->additionalLanguages;
        $languagelist[] = $oSurvey->language;

        Yii::app()->loadHelper('database');

        $hasSurveyLanguageSettingError = false;
        if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveylocale', 'update')) {
            foreach ($languagelist as $langname) {
                if ($langname) {
                    $data = array();
                    $sURLDescription = Yii::app()->request->getPost('urldescrip_' . $langname, null);
                    $sURL = Yii::app()->request->getPost('url_' . $langname, null);
                    $short_title = Yii::app()->request->getPost('short_title_' . $langname, null);
                    $alias = Yii::app()->request->getPost('alias_' . $langname, null);
                    $description = Yii::app()->request->getPost('description_' . $langname, null);
                    $welcome = Yii::app()->request->getPost('welcome_' . $langname, null);
                    $endtext = Yii::app()->request->getPost('endtext_' . $langname, null);
                    $datasec = Yii::app()->request->getPost('datasec_' . $langname, null);
                    $datasecerror = Yii::app()->request->getPost('datasecerror_' . $langname, null);
                    $dataseclabel = Yii::app()->request->getPost('dataseclabel_' . $langname, null);
                    $dateformat = Yii::app()->request->getPost('dateformat_' . $langname, null);
                    $numberformat = Yii::app()->request->getPost('numberformat_' . $langname, null);

                    if ($short_title !== null) {
                        // Fix bug with FCKEditor saving strange BR types
                        $short_title = $this->oFixCKeditor->fixCKeditor($short_title);
                        $data['surveyls_title'] = $short_title;
                    }
                    if ($alias !== null) {
                        $data['surveyls_alias'] = $alias;
                    }
                    if ($description !== null) {
                        // Fix bug with FCKEditor saving strange BR types
                        $description = $this->oFixCKeditor->fixCKeditor($description);
                        $data['surveyls_description'] = $description;
                    }
                    if ($welcome !== null) {
                        // Fix bug with FCKEditor saving strange BR types
                        $welcome = $this->oFixCKeditor->fixCKeditor($welcome);
                        $data['surveyls_welcometext'] = $welcome;
                    }
                    if ($endtext !== null) {
                        // Fix bug with FCKEditor saving strange BR types
                        $endtext = $this->oFixCKeditor->fixCKeditor($endtext);
                        $data['surveyls_endtext'] = $endtext;
                    }
                    if ($datasec !== null) {
                        // Fix bug with FCKEditor saving strange BR types
                        $datasec = $this->oFixCKeditor->fixCKeditor($datasec);
                        $data['surveyls_policy_notice'] = $datasec;
                    }
                    if ($datasecerror !== null) {
                        // Fix bug with FCKEditor saving strange BR types
                        $datasecerror = $this->oFixCKeditor->fixCKeditor($datasecerror);
                        $data['surveyls_policy_error'] = $datasecerror;
                    }
                    if ($dataseclabel !== null) {
                        $data['surveyls_policy_notice_label'] = $dataseclabel;
                    }
                    if ($sURL !== null) {
                        $data['surveyls_url'] = $sURL;
                    }
                    if ($sURLDescription !== null) {
                        $data['surveyls_urldescription'] = $sURLDescription;
                    }
                    if ($dateformat !== null) {
                        $data['surveyls_dateformat'] = $dateformat;
                    }
                    if ($numberformat !== null) {
                        $data['surveyls_numberformat'] = $numberformat;
                    }

                    if (count($data) > 0) {
                        $oSurveyLanguageSetting = SurveyLanguageSetting::model()->findByPk(array('surveyls_survey_id' => $iSurveyID, 'surveyls_language' => $langname));
                        $oSurveyLanguageSetting->setAttributes($data);
                        if (!$oSurveyLanguageSetting->save()) { // save the change to database
                            $languageDescription = getLanguageNameFromCode($langname, false);
                            Yii::app()->setFlashMessage(
                                Chtml::errorSummary(
                                    $oSurveyLanguageSetting,
                                    Chtml::tag(
                                        "p",
                                        ['class' => 'strong'],
                                        sprintf(gT("Texts for language %s could not be updated:"), $languageDescription)
                                    )
                                ),
                                "error"
                            );
                            $hasSurveyLanguageSettingError = true;
                        }
                    }
                }
            }
        }
        ////////////////////////////////////////////////////////////////////////////////////
        // General settings (copy / paste from surveyadmin::update)
        if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update')) {
            // Preload survey
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            $aOldAttributes = $oSurvey->attributes;
            // Save plugin settings : actually leave it before saving core : we are sure core settings is saved in LS way.
            $pluginSettings = App()->request->getPost('plugin', array());
            foreach ($pluginSettings as $plugin => $settings) {
                $settingsEvent = new PluginEvent('newSurveySettings');
                $settingsEvent->set('settings', $settings);
                $settingsEvent->set('survey', $iSurveyID);
                App()->getPluginManager()->dispatchEvent($settingsEvent, $plugin);
            }

            // Start to fix some param before save (TODO : use models directly ?)
            // Date management
            Yii::app()->loadHelper('surveytranslator');
            $formatdata = getDateFormatData(Yii::app()->session['dateformat']);
            Yii::app()->loadLibrary('Date_Time_Converter');

            $unfilteredStartdate = App()->request->getPost('startdate', null);
            $startdate = $this->filterEmptyFields($oSurvey, 'startdate');
            if ($unfilteredStartdate === null) {
                // Not submitted.
            } elseif (trim((string) $unfilteredStartdate) == "") {
                $oSurvey->startdate = "";
            } else {
                Yii::app()->loadLibrary('Date_Time_Converter');
                $datetimeobj = new Date_Time_Converter($startdate, $formatdata['phpdate'] . ' H:i');
                $startdate = $datetimeobj->convert("Y-m-d H:i:s");
                $oSurvey->startdate = $startdate;
            }

            $unfilteredExpires = App()->request->getPost('expires', null);
            $expires = $this->filterEmptyFields($oSurvey, 'expires');
            if ($unfilteredExpires === null) {
                // Not submitted.
            } elseif (trim((string) $unfilteredExpires) == "") {
                // Must not convert if empty.
                $oSurvey->expires = "";
            } else {
                $datetimeobj = new Date_Time_Converter($expires, $formatdata['phpdate'] . ' H:i');
                $expires = $datetimeobj->convert("Y-m-d H:i:s");
                $oSurvey->expires = $expires;
            }

            $oSurvey->assessments = $this->filterEmptyFields($oSurvey, 'assessments');

            if ($oSurvey->active != 'Y') {
                $oSurvey->anonymized = $this->filterEmptyFields($oSurvey, 'anonymized');
                $oSurvey->savetimings = $this->filterEmptyFields($oSurvey, 'savetimings');
                $oSurvey->datestamp = $this->filterEmptyFields($oSurvey, 'datestamp');
                $oSurvey->ipaddr = $this->filterEmptyFields($oSurvey, 'ipaddr');
                //save the new setting ipanonymize
                $oSurvey->ipanonymize = $this->filterEmptyFields($oSurvey, 'ipanonymize');
                //todo: here we have to change the ip-addresses already saved ?!?
                $oSurvey->refurl = $this->filterEmptyFields($oSurvey, 'refurl');
            }

            $oSurvey->publicgraphs = $this->filterEmptyFields($oSurvey, 'publicgraphs');
            $oSurvey->usecookie = $this->filterEmptyFields($oSurvey, 'usecookie');
            $oSurvey->allowregister = $this->filterEmptyFields($oSurvey, 'allowregister');
            $oSurvey->allowsave = $this->filterEmptyFields($oSurvey, 'allowsave');
            $oSurvey->navigationdelay = (int) $this->filterEmptyFields($oSurvey, 'navigationdelay');
            $oSurvey->printanswers = $this->filterEmptyFields($oSurvey, 'printanswers');
            $oSurvey->publicstatistics = $this->filterEmptyFields($oSurvey, 'publicstatistics');
            $oSurvey->autoredirect = $this->filterEmptyFields($oSurvey, 'autoredirect');

            // save into the database only if global settings are off
            //if (getGlobalSetting('showxquestions') === 'choose'){
                $oSurvey->showxquestions = $this->filterEmptyFields($oSurvey, 'showxquestions');
            //}
            //if (getGlobalSetting('showgroupinfo') === 'choose'){
                $oSurvey->showgroupinfo = $this->filterEmptyFields($oSurvey, 'showgroupinfo');
            //}
            //if (getGlobalSetting('showqnumcode') === 'choose'){
                $oSurvey->showqnumcode = $this->filterEmptyFields($oSurvey, 'showqnumcode');
            //}
            //if (getGlobalSetting('shownoanswer') == 2){  // Don't do exact comparison because the value could be from global settings table (string) or from config (integer)
                $oSurvey->shownoanswer = $this->filterEmptyFields($oSurvey, 'shownoanswer');
            //}
            $oSurvey->showwelcome = $this->filterEmptyFields($oSurvey, 'showwelcome');
            $oSurvey->showsurveypolicynotice = $this->filterEmptyFields($oSurvey, 'showsurveypolicynotice');
            $oSurvey->allowprev = $this->filterEmptyFields($oSurvey, 'allowprev');
            $oSurvey->questionindex = (int) $this->filterEmptyFields($oSurvey, 'questionindex');
            $oSurvey->nokeyboard = $this->filterEmptyFields($oSurvey, 'nokeyboard');
            $oSurvey->showprogress = $this->filterEmptyFields($oSurvey, 'showprogress');
            $oSurvey->listpublic = $this->filterEmptyFields($oSurvey, 'listpublic');
            $oSurvey->htmlemail = $this->filterEmptyFields($oSurvey, 'htmlemail');
            $oSurvey->sendconfirmation = $this->filterEmptyFields($oSurvey, 'sendconfirmation');
            $oSurvey->tokenanswerspersistence = $this->filterEmptyFields($oSurvey, 'tokenanswerspersistence');
            $oSurvey->alloweditaftercompletion = $this->filterEmptyFields($oSurvey, 'alloweditaftercompletion');
            $oSurvey->usecaptcha = Survey::saveTranscribeCaptchaOptions($oSurvey);
            $oSurvey->emailresponseto = $this->filterEmptyFields($oSurvey, 'emailresponseto');
            $oSurvey->emailnotificationto = $this->filterEmptyFields($oSurvey, 'emailnotificationto');
            $googleanalyticsapikeysetting = $this->filterEmptyFields($oSurvey, 'googleanalyticsapikeysetting');
            $oSurvey->googleanalyticsapikeysetting = $googleanalyticsapikeysetting;

            if ($googleanalyticsapikeysetting == "Y") {
                $oSurvey->googleanalyticsapikey = $this->filterEmptyFields($oSurvey, 'googleanalyticsapikey');
            } elseif ($googleanalyticsapikeysetting == "G") {
                $oSurvey->googleanalyticsapikey = "9999useGlobal9999";
            } elseif ($googleanalyticsapikeysetting == "N") {
                $oSurvey->googleanalyticsapikey = "";
            }

            $oSurvey->googleanalyticsstyle = $this->filterEmptyFields($oSurvey, 'googleanalyticsstyle');
            $oSurvey->tokenlength = $this->filterEmptyFields($oSurvey, 'tokenlength');
            $event = new PluginEvent('beforeSurveySettingsSave');
            $event->set('modifiedSurvey', $oSurvey);
            App()->getPluginManager()->dispatchEvent($event);
            $aAfterApplyAttributes = $oSurvey->attributes;

            if ($oSurvey->save()) {
                if (!$hasSurveyLanguageSettingError) {
                    Yii::app()->setFlashMessage(gT("Survey settings were successfully saved."));
                } else {
                    Yii::app()->setFlashMessage(gT("Survey settings were saved, but there where errors with some languages."), "warning");
                }
            } else {
                Yii::app()->setFlashMessage(CHtml::errorSummary($oSurvey, CHtml::tag("p", array('class' => 'strong'), gT("Survey could not be updated, please fix the following error:"))), "error");
            }
        }
        $oSurvey->refresh();

        // Url params in json
        if (Yii::app()->request->getPost('allurlparams', false) !== false) {
            $aURLParams = json_decode(Yii::app()->request->getPost('allurlparams', ''), true) ?? [];
            SurveyURLParameter::model()->deleteAllByAttributes(array('sid' => $iSurveyID));
            foreach ($aURLParams as $aURLParam) {
                $aURLParam['parameter'] = trim((string) $aURLParam['parameter']);
                if ($aURLParam['parameter'] == '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $aURLParam['parameter']) || $aURLParam['parameter'] == 'sid' || $aURLParam['parameter'] == 'newtest' || $aURLParam['parameter'] == 'token' || $aURLParam['parameter'] == 'lang') {
                    continue; // this parameter name seems to be invalid - just ignore it
                }
                $aURLParam['targetqid']  = $aURLParam['qid'];
                $aURLParam['targetsqid'] = $aURLParam['sqid'];
                unset($aURLParam['actionBtn']);
                unset($aURLParam['title']);
                unset($aURLParam['id']);
                unset($aURLParam['qid']);
                unset($aURLParam['targetQuestionText']);
                unset($aURLParam['sqid']);
                if ($aURLParam['targetqid'] == '') {
                    $aURLParam['targetqid'] = null;
                }
                if ($aURLParam['targetsqid'] == '') {
                    $aURLParam['targetsqid'] = null;
                }
                $aURLParam['sid'] = $iSurveyID;

                $param = new SurveyURLParameter();
                foreach ($aURLParam as $k => $v) {
                                    $param->$k = $v;
                }
                $param->save();
            }
        }
        //This is SUPER important! Recalculating the ExpressionScript Engine state!
        LimeExpressionManager::SetDirtyFlag();
        $this->resetEM();

        if (Yii::app()->request->getPost('responsejson', 0) == 1) {
            $updatedFields = $this->updatedFields;
            $this->updatedFields = [];
            return Yii::app()->getController()->renderPartial(
                '/admin/super/_renderJson',
                array(
                    'data' => [
                        'success' => true,
                        'updated' => $updatedFields,
                        'DEBUG' => ['POST' => $_POST,
                                    'reloaded' => $oSurvey->attributes,
                                    'aURLParams' => $aURLParams ?? '',
                                    'initial' => $aOldAttributes ?? '',
                                    'afterApply' => $aAfterApplyAttributes ?? '']
                    ],
                ),
                false,
                false
            );
        } else {
            ////////////////////////////////////////
            if (Yii::app()->request->getPost('close-after-save') === 'true') {
                $this->getController()->redirect(array('surveyAdministration/view/surveyid/' . $iSurveyID));
            }

            $referrer = Yii::app()->request->urlReferrer;
            if ($referrer) {
                $this->getController()->redirect(array($referrer));
            } else {
                $this->getController()->redirect(array('/surveyAdministration/rendersidemenulink/subaction/generalsettings/surveyid/' . $iSurveyID));
            }
        }
    }
    */

    /**
     * Action for the page "General settings".
     * @param int $iSurveyID
     * @return void
     */
    protected function actionUpdateSurveyLocaleSettingsGeneralSettings($iSurveyID)
    {
        $oSurvey = Survey::model()->findByPk($iSurveyID);
        $request = Yii::app()->request;
        $oldBaseLanguage = $oSurvey->language;
        $newBaseLanguage = Yii::app()->request->getPost('language', $oldBaseLanguage);
        $oSurvey->language = $newBaseLanguage;
        // On the UI the field is "Survey Languages", but on the db and model the field is "additional_languages".
        $additionalLanguages = Yii::app()->request->getPost('additional_languages', array());
        // Exclude base language from the additional languages array
        if (($newBaseLanguageKey = array_search($newBaseLanguage, $additionalLanguages)) !== false) {
            unset($additionalLanguages[$newBaseLanguageKey]);
        }
        $oSurvey->additional_languages = implode(' ', $additionalLanguages);
        $oSurvey->admin = $request->getPost('admin');
        $oSurvey->adminemail = $request->getPost('adminemail');
        $oSurvey->bounce_email = $request->getPost('bounce_email');
        $oSurvey->gsid = $request->getPost('gsid');
        $oSurvey->format = $request->getPost('format');

        // For the new template system we have to check that the changed template is also applied.
        $current_template = $oSurvey->template;
        $new_template = $request->getPost('template');
        if ($current_template != '' && $current_template !== $new_template) {
            $currentConfiguration = TemplateConfiguration::getInstance(
                $current_template,
                $oSurvey->gsid,
                $oSurvey->sid
            );
        }
        $oSurvey->template = $new_template;

        // Only owner and superadmins may change the survey owner
        if (
            $oSurvey->owner_id == Yii::app()->session['loginID']
            || Permission::model()->hasGlobalPermission('superadmin', 'read')
        ) {
            $oSurvey->owner_id = $request->getPost('owner_id');
        }

        // If the base language is changing, we may need the current title to avoid the survey
        // being listed with an empty title.
        $surveyTitle = $oSurvey->languagesettings[$oldBaseLanguage]->surveyls_title;

        /* Add new language fixLanguageConsistency do it ?*/
        $aAvailableLanguage = $oSurvey->getAllLanguages();
        foreach ($aAvailableLanguage as $sLang) {
            if ($sLang) {
                $oLanguageSettings = SurveyLanguageSetting::model()->find(
                    'surveyls_survey_id=:surveyid AND surveyls_language=:langname',
                    array(':surveyid' => $iSurveyID, ':langname' => $sLang)
                );
                if (!$oLanguageSettings) {
                    $oLanguageSettings = new SurveyLanguageSetting();
                    $languagedetails = getLanguageDetails($sLang);
                    $oLanguageSettings->surveyls_survey_id = $iSurveyID;
                    $oLanguageSettings->surveyls_language = $sLang;
                    if ($sLang == $newBaseLanguage) {
                        $oLanguageSettings->surveyls_title = $surveyTitle;
                    } else {
                        $oLanguageSettings->surveyls_title = ''; // Not in default model ?
                    }
                    $oLanguageSettings->surveyls_dateformat = $languagedetails['dateformat'];
                    if (!$oLanguageSettings->save()) {
                        Yii::app()->setFlashMessage(gT("Survey language could not be created."), "error");
                        tracevar($oLanguageSettings->getErrors());
                    }
                }
            }
        }
        fixLanguageConsistency($iSurveyID, implode(" ", $aAvailableLanguage), $oldBaseLanguage);

        /* Delete removed language cleanLanguagesFromSurvey do it already why redo it (cleanLanguagesFromSurvey must be moved to model) ?*/
        $oCriteria = new CDbCriteria();
        $oCriteria->compare('surveyls_survey_id', $iSurveyID);
        $oCriteria->addNotInCondition('surveyls_language', $aAvailableLanguage);
        SurveyLanguageSetting::model()->deleteAll($oCriteria);

        /* Language fix : remove and add question/group */
        cleanLanguagesFromSurvey($iSurveyID, implode(" ", $oSurvey->additionalLanguages));

        //This is SUPER important! Recalculating the ExpressionScript Engine state!
        LimeExpressionManager::SetDirtyFlag();
        $this->resetEM();

        // This will force the generation of the entry for survey group
        TemplateConfiguration::checkAndcreateSurveyConfig($iSurveyID);

        if ($oSurvey->save()) {
            Yii::app()->setFlashMessage(gT("Survey settings were successfully saved."));
        } else {
            Yii::app()->setFlashMessage(CHtml::errorSummary($oSurvey, CHtml::tag("p", array('class' => 'strong'), gT("Survey could not be updated, please fix the following error:"))), "error");
        }
        Yii::app()->end();
    }

    /**
     * @param Survey $oSurvey
     * @param string $fieldArrayName
     * @param mixed $newValue
     * @return mixed
     */
    private function filterEmptyFields($oSurvey, $fieldArrayName, $newValue = null)
    {
        $aSurvey = $oSurvey->attributes;

        if ($newValue === null) {
            $newValue = App()->request->getPost($fieldArrayName, null);
        }

        if ($newValue === null) {
            $newValue = $aSurvey[$fieldArrayName] ?? $oSurvey->{$fieldArrayName};
        } else {
            $this->updatedFields[] = $fieldArrayName;
        }

        $newValue = trim((string) $newValue);

        $options = $this->updateableFields[$fieldArrayName];

        switch ($options['type']) {
            case 'yesno':
                if ($newValue != 'Y' && $newValue != 'N' && $newValue != 'I') {
                    $newValue = (int) $newValue;
                    $newValue = ($newValue === 1) ? 'Y' : 'N';
                }
                break;
            case 'Int':
                $newValue = (int) $newValue;
                break;
        }

        return $newValue;
    }

    private function resetEM()
    {
        $oSurvey = Survey::model()->findByPk($this->iSurveyID);
        $oEM =& LimeExpressionManager::singleton();
        LimeExpressionManager::SetDirtyFlag(); // UpgradeConditionsToRelevance SetDirtyFlag too
        LimeExpressionManager::UpgradeConditionsToRelevance($this->iSurveyID);
        LimeExpressionManager::SetPreviewMode('database');// Deactivate _UpdateValuesInDatabase
        LimeExpressionManager::StartSurvey($oSurvey->sid, 'survey', $oSurvey->attributes, true);
        LimeExpressionManager::StartProcessingPage(true, true);
        $aGrouplist = QuestionGroup::model()->findAllByAttributes(['sid' => $this->iSurveyID]);
        foreach ($aGrouplist as $iGID => $aGroup) {
            LimeExpressionManager::StartProcessingGroup($aGroup['gid'], $oSurvey->anonymized != 'Y', $this->iSurveyID);
            LimeExpressionManager::FinishProcessingGroup();
        }
        LimeExpressionManager::FinishProcessingPage();

        // Flush emcache when changes are made to the survey.
        EmCacheHelper::init(['sid' => $this->iSurveyID, 'active' => 'Y']);
        EmCacheHelper::flush();
    }
}
