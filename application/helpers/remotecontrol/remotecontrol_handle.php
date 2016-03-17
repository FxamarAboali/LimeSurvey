<?php
/**
* This class handles all methods of the RPCs
*/
class remotecontrol_handle
{
    /**
    * @var AdminController
    */
    protected $controller;

    /**
    * Constructor, stores the action instance into this handle class
    *
    * @access public
    * @param AdminController $controller
    * @return void
    */
    public function __construct(AdminController $controller)
    {
        $this->controller = $controller;
    }


    /**
    * RPC routine to create a session key.
    * Using this function you can create a new XML/JSON-RPC session key.
    * This is mandatory for all following LSRC2 function calls.
    * @access public
    * @param string $username
    * @param string $password
    * @return string
    */
    public function get_session_key($username, $password)
    {
        if ($this->_doLogin($username, $password))
        {
            $this->_jumpStartSession($username);
            $sSessionKey = randomChars(32);
            $sDatabasetype = Yii::app()->db->getDriverName();

            $session = new Session;
            $session->id = $sSessionKey;
            $session->expire = time() + Yii::app()->getConfig('iSessionExpirationTime');
            if($sDatabasetype=='sqlsrv' || $sDatabasetype=='mssql' || $sDatabasetype=='dblib')
                $username=new CDbExpression('CONVERT(VARBINARY(MAX), '.Yii::app()->db->quoteValue($username).')');
            $session->data = $username;
            $session->save();
            return $sSessionKey;
        }
        else
            return array('status' => 'Invalid user name or password');
    }

    /**
    * Closes the RPC session
    *
    * @access public
    * @param string $sSessionKey
    * @return string
    */
    public function release_session_key($sSessionKey)
    {
        Session::model()->deleteAllByAttributes(array('id' => $sSessionKey));
        $criteria = new CDbCriteria;
        $criteria->condition = 'expire < ' . time();
        Session::model()->deleteAll($criteria);
        return 'OK';
    }

    /**
    * RPC Routine to get settings.
    *
    * @access public
    * @param string $sSessionKey Auth Credentials
    * @param string $sSetttingName Name of the setting to get
    * @return string The requested value
    */
    public function get_site_settings($sSessionKey,$sSetttingName)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            if(Permission::model()->hasGlobalPermission('superadmin','read'))
            {
                if (Yii::app()->getConfig($sSetttingName) !== false)
                    return Yii::app()->getConfig($sSetttingName);
                else
                    return array('status' => 'Invalid setting');
            }
            else
                return array('status' => 'Invalid setting');
        }
        else
            return array('status' => 'Invalid session key');
    }


    /* Survey specific functions */

    /**
    * RPC Routine to add an empty survey with minimum details.
    * Used as a placeholder for importing groups and/or questions.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID The wish id of the Survey to add
    * @param string $sSurveyTitle Title of the new Survey
    * @param string $sSurveyLanguage    Default language of the Survey
    * @param string $sformat Question appearance format
    * @return array|string|int
    */
    public function add_survey($sSessionKey, $iSurveyID, $sSurveyTitle, $sSurveyLanguage, $sformat = 'G')
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey))
        {
            if (Permission::model()->hasGlobalPermission('surveys','create'))
            {
                if( $sSurveyTitle=='' || $sSurveyLanguage=='' || !array_key_exists($sSurveyLanguage,getLanguageDataRestricted()) || !in_array($sformat, array('A','G','S')))
                    return array('status' => 'Faulty parameters');

                $aInsertData = array('template' => 'default',
                    'owner_id' => Yii::app()->session['loginID'],
                    'active' => 'N',
                    'language'=>$sSurveyLanguage,
                    'format' => $sformat
                );

                if (!is_null($iSurveyID))
                $aInsertData['wishSID'] = $iSurveyID;

                try
                {
                    $iNewSurveyid = Survey::model()->insertNewSurvey($aInsertData);
                    if (!$iNewSurveyid)
                        return array('status' => 'Creation Failed');

                    $sTitle = html_entity_decode($sSurveyTitle, ENT_QUOTES, "UTF-8");

                    // Load default email templates for the chosen language
                    $oLanguage = new Limesurvey_lang($sSurveyLanguage);
                    $aDefaultTexts = templateDefaultTexts($oLanguage, 'unescaped');
                    unset($oLanguage);

                    $bIsHTMLEmail = false;

                    $aInsertData = array(
                        'surveyls_survey_id' => $iNewSurveyid,
                        'surveyls_title' => $sTitle,
                        'surveyls_language' => $sSurveyLanguage,
                    );

                    $langsettings = new SurveyLanguageSetting;
                    $langsettings->insertNewSurvey($aInsertData);
                    Permission::model()->giveAllSurveyPermissions(Yii::app()->session['loginID'], $iNewSurveyid);

                    return (int)$iNewSurveyid;
                }
                catch(Exception $e)
                {
                    return array('status' => $e->getmessage());
                }
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC Routine to delete a survey.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID The id of the Survey to be deleted
    * @return array Returns Status
    */
    public function delete_survey($sSessionKey, $iSurveyID)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'delete'))
            {
                Survey::model()->deleteSurvey($iSurveyID,true);
                return array('status' => 'OK');
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC Routine to import a survey - imports lss,csv,xls or survey zip archive.
    *
    * @access public
    * @param string $sSessionKey Auth Credentials
    * @param string $sImportData String containing the BASE 64 encoded data of a lss,csv,xls or survey zip archive
    * @param string $sImportDataType  lss,csv,txt or zip
    * @param string $sNewSurveyName The optional new name of the survey
    * @param integer $DestSurveyID This is the new ID of the survey - if already used a random one will be taken instead
    * @return array|integer iSurveyID  - ID of the new survey
    */
    public function import_survey($sSessionKey, $sImportData, $sImportDataType, $sNewSurveyName=NULL, $DestSurveyID=NULL)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            if (Permission::model()->hasGlobalPermission('surveys','create'))
            {
                if (!in_array($sImportDataType,array('lsa','csv','txt','lss'))) return array('status' => 'Invalid extension');
                Yii::app()->loadHelper('admin/import');
                // First save the data to a temporary file
                $sFullFilePath = Yii::app()->getConfig('tempdir') . DIRECTORY_SEPARATOR . randomChars(40).'.'.$sImportDataType;
                file_put_contents($sFullFilePath,base64_decode(chunk_split($sImportData)));
                $aImportResults = importSurveyFile($sFullFilePath, true, $sNewSurveyName, $DestSurveyID);
                unlink($sFullFilePath);
                if (isset($aImportResults['error'])) return array('status' => 'Error: '.$aImportResults['error']);
                else
                {
                    return (int)$aImportResults['newsid'];
                }
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC Routine to get survey properties.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID The id of the Survey to be checked
    * @param array $aSurveySettings The properties to get
    * @return array
    */
    public function get_survey_properties($sSessionKey,$iSurveyID, $aSurveySettings)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
            {
                return array('status' => 'Error: Invalid survey ID');
            }
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'read'))
            {
                $aBasicDestinationFields=Survey::model()->tableSchema->columnNames;
                $aSurveySettings=array_intersect($aSurveySettings,$aBasicDestinationFields);

                if (empty($aSurveySettings))
                    return array('status' => 'No valid Data');
                $aResult = array();
                foreach($aSurveySettings as $sPropertyName)
                {
                    $aResult[$sPropertyName]=$oSurvey->$sPropertyName;
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session key');
    }

    /**
    * RPC Routine to set survey properties.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param integer $iSurveyID  - ID of the survey
    * @param array|struct $aSurveyData - An array with the particular fieldnames as keys and their values to set on that particular survey
    * @return array Of succeeded and failed nodifications according to internal validation.
    */
    public function set_survey_properties($sSessionKey, $iSurveyID, $aSurveyData)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey=Survey::model()->findByPk($iSurveyID);
            if (is_null($oSurvey))
            {
                return array('status' => 'Error: Invalid survey ID');
            }
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update'))
            {
                // Remove fields that may not be modified
                unset($aSurveyData['sid']);
                unset($aSurveyData['owner_id']);
                unset($aSurveyData['active']);
                unset($aSurveyData['language']);
                unset($aSurveyData['additional_languages']);
                // Remove invalid fields
                $aDestinationFields=array_flip(Survey::model()->tableSchema->columnNames);
                $aSurveyData=array_intersect_key($aSurveyData,$aDestinationFields);
                $oSurvey=Survey::model()->findByPk($iSurveyID);
                $aBasicAttributes = $oSurvey->getAttributes();
                $aResult = array();

                if ($oSurvey->active=='Y')
                {
                    // remove all fields that may not be changed when a survey is active
                    unset($aSurveyData['anonymized']);
                    unset($aSurveyData['datestamp']);
                    unset($aSurveyData['savetimings']);
                    unset($aSurveyData['ipaddr']);
                    unset($aSurveyData['refurl']);
                }

                if (empty($aSurveyData))
                    return array('status' => 'No valid Data');

                foreach($aSurveyData as $sFieldName=>$sValue)
                {
                    $oSurvey->$sFieldName=$sValue;
                    try
                    {
                        $bSaveResult=$oSurvey->save(); // save the change to database
                        //unset the value if it fails, so as to prevent future fails
                        $aResult[$sFieldName]=$bSaveResult;
                        if (!$bSaveResult)
                        $oSurvey->$sFieldName=$aBasicAttributes[$sFieldName];
                    }
                    catch(Exception $e)
                    {
                        //unset the value that caused the exception
                        $oSurvey->$sFieldName=$aBasicAttributes[$sFieldName];
                    }
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session key');
    }


    /**
    * RPC Routine that launches a newly created survey.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID The id of the survey to be activated
    * @return array The result of the activation
    */
    public function activate_survey($sSessionKey, $iSurveyID)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey=Survey::model()->findByPk($iSurveyID);
            if (is_null($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveyactivation', 'update'))
            {
                Yii::app()->loadHelper('admin/activate');
                $aActivateResults = activateSurvey($iSurveyID);

                if (isset($aActivateResults['error'])) return array('status' => 'Error: '.$aActivateResults['error']);
                else
                {
                    return $aActivateResults;
                }
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC routine to export statistics of a survey to a user.
    * Returns string - base64 encoding of the statistics.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey
    * @param string $docType Type of documents the exported statistics should be
    * @param string $sLanguage Optional language of the survey to use
    * @param string $graph Create graph option
    * @param int|array $groupIDs An OPTIONAL array (ot a single int) containing the groups we choose to generate statistics from
    * @return string Base64 encoded string with the statistics file
    */
    public function export_statistics($sSessionKey, $iSurveyID,  $docType='pdf', $sLanguage=null, $graph='0', $groupIDs=null)
    {
        Yii::app()->loadHelper('admin/statistics');

        $tempdir = Yii::app()->getConfig("tempdir");
        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');

        $oSurvey = Survey::model()->findByPk($iSurveyID);
        if (!isset($oSurvey))
            return array('status' => 'Error: Invalid survey ID');;

        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'statistics', 'read'))
            return array('status' => 'Error: No Permission');

        $aAdditionalLanguages = array_filter(explode(" ", $oSurvey->additional_languages));

        if (is_null($sLanguage)|| !in_array($sLanguage,$aAdditionalLanguages))
            $sLanguage = $oSurvey->language;

        $oAllQuestions =Question::model()->getQuestionList($iSurveyID, $sLanguage);
        if (!isset($oAllQuestions))
            return array('status' => 'No available data');

        if($groupIDs!=null)
        {
            if(is_int($groupIDs))
                $groupIDs = array($groupIDs);

            if(is_array($groupIDs))
            {
                //check that every value of the array belongs to the survey defined
                $aGroups = QuestionGroup::model()->findAllByAttributes(array('sid' => $iSurveyID));

                foreach( $aGroups as $group)
                    $validGroups[] = $group['gid'];

                $groupIDs=array_intersect($groupIDs,$validGroups);

                if (empty($groupIDs))
                    return array('status' => 'Error: Invalid group ID');

                foreach($oAllQuestions as $key => $aQuestion)
                {
                    if(!in_array($aQuestion['gid'],$groupIDs))
                        unset($oAllQuestions[$key]);
                }
            }
            else
                return array('status' => 'Error: Invalid group ID');
        }

        if (!isset($oAllQuestions))
        return array('status' => 'No available data');

        usort($oAllQuestions, 'groupOrderThenQuestionOrder');

        $aSummary = createCompleteSGQA($iSurveyID,$oAllQuestions,$sLanguage);

        $helper = new statistics_helper();
        switch ($docType)
        {
            case 'pdf':
                $sTempFile = $helper->generate_statistics($iSurveyID,$aSummary,$aSummary,$graph,$docType,'F',$sLanguage);
                $sResult = file_get_contents($sTempFile);
                unlink($sTempFile);
                break;
            case 'xls':
                $sTempFile = $helper->generate_statistics($iSurveyID,$aSummary,$aSummary,'0',$docType, 'F',$sLanguage);
                $sResult = file_get_contents($sTempFile);
                unlink($sTempFile);
                break;
            case 'html':
                $sResult = $helper->generate_statistics($iSurveyID,$aSummary,$aSummary,'0',$docType, 'DD',$sLanguage);
                break;
        }

        return base64_encode($sResult);

    }

    /**
    * RPC Routine to export submission timeline.
    * Returns an array of values (count and period)
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey
    * @param string $sType (day|hour)
    * @param string $dStart
    * @param string $dEnd
    * @return array On success: The timeline. On failure array with error information
    * */
    public function export_timeline($sSessionKey, $iSurveyID, $sType, $dStart, $dEnd)
    {
        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');
        if (!in_array($sType, array('day','hour'))) return array('status' => 'Invalid Period');
        if (!hasSurveyPermission($iSurveyID, 'responses', 'read')) return array('status' => 'No permission');
        $oSurvey=Survey::model()->findByPk($iSurveyID);
        if (is_null($oSurvey)) return array('status' => 'Error: Invalid survey ID');
        if (!tableExists('{{survey_' . $iSurveyID . '}}')) return array('status' => 'No available data');

        $oResponses = SurveyDynamic::model($iSurveyID)->timeline($sType, $dStart, $dEnd);
        if (empty($oResponses))  return array('status' => 'No valid Data');

        return $oResponses;

    }

    /**
    * RPC routine to get survey summary, regarding token usage and survey participation.
    * Returns the requested value as string.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey to get summary
    * @param string $sStatName Name of the summary option - valid values are 'token_count', 'token_invalid', 'token_sent', 'token_opted_out', 'token_completed', 'completed_responses', 'incomplete_responses', 'full_responses' or 'all'
    * @return string The requested value or an array of all values when $sStatName = 'all'
    */
    public function get_summary($sSessionKey,$iSurveyID, $sStatName)
    {
        $aPermittedStats = array();
        if ($this->_checkSessionKey($sSessionKey))
        {
            $aPermittedTokenStats = array('token_count',
                'token_invalid',
                'token_sent',
                'token_opted_out',
                'token_completed');
            $aPermittedSurveyStats  = array('completed_responses',
                'incomplete_responses',
                'full_responses');
            $aPermittedStats = array_merge($aPermittedSurveyStats, $aPermittedTokenStats, array('all'));
            // Check if survey exists
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Invalid surveyid');

            if (!in_array($sStatName, $aPermittedStats))
                return array('status' => 'Invalid summary key');

            //Check permissions to access this survey
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'read'))
            {
                $aSummary=array();

                if (in_array($sStatName, $aPermittedTokenStats) || $sStatName=='all')
                {
                    if (tableExists('{{tokens_' . $iSurveyID . '}}'))
                    {
                        $aTokenSummary = Token::model($iSurveyID)->summary();
                        if ($aTokenSummary)
                        {
                            $aSummary['token_count']=$aTokenSummary['count'];
                            $aSummary['token_invalid']=$aTokenSummary['invalid'];
                            $aSummary['token_sent']=$aTokenSummary['sent'];
                            $aSummary['token_opted_out']=$aTokenSummary['optout'];
                            $aSummary['token_completed']=$aTokenSummary['completed'];
                        }
                    }
                    elseif ($sStatName!='all')
                    {
                        return array('status' => 'No available data');
                    }
                }

                if (in_array($sStatName, $aPermittedSurveyStats) || $sStatName=='all')
                {
                    if (tableExists('{{survey_' . $iSurveyID . '}}'))
                    {
                        $aSummary['completed_responses']=SurveyDynamic::model($iSurveyID)->count('submitdate is NOT NULL');
                        $aSummary['incomplete_responses']=SurveyDynamic::model($iSurveyID)->countByAttributes(array('submitdate' => null));
                        $aSummary['full_responses']=SurveyDynamic::model($iSurveyID)->count();
                    }
                    elseif ($sStatName!='all')
                    {
                        return array('status' => 'No available data');
                    }
                }

                if ($sStatName=='all')
                {
                    return $aSummary;
                }
                else
                {
                    return $aSummary[$sStatName];
                }
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /*Survey language specific functions */

    /**
    * RPC Routine to add a survey language.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param integer $iSurveyID ID of the survey where a token table will be created for
    * @param string $sLanguage  A valid language shortcut to add to the current survey. If the language already exists no error will be given.
    * @return array Status=>OK when successfull, otherwise the error description
    */
    public function add_language($sSessionKey, $iSurveyID, $sLanguage)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey=Survey::model()->findByPk($iSurveyID);
            if (is_null($oSurvey))
            {
                return array('status' => 'Error: Invalid survey ID');
            }
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update'))
            {
                Yii::app()->loadHelper('surveytranslator');
                $aLanguages=getLanguageData();

                if(!isset($aLanguages[$sLanguage]))
                {
                    return array('status' => 'Invalid language');
                }
                $oSurvey=Survey::model()->findByPk($iSurveyID);
                if ($sLanguage==$oSurvey->language)
                {
                    return array('status' => 'OK');
                }
                $aLanguages=$oSurvey->getAdditionalLanguages();
                $aLanguages[]=$sLanguage;
                $aLanguages=array_unique($aLanguages);
                $oSurvey->additional_languages=implode(' ',$aLanguages);
                try
                {
                    $oSurvey->save(); // save the change to database
                    $languagedetails=getLanguageDetails($sLanguage);

                    $insertdata = array(
                        'surveyls_survey_id' => $iSurveyID,
                        'surveyls_language' => $sLanguage,
                        'surveyls_title' => '',
                        'surveyls_dateformat' => $languagedetails['dateformat']
                    );
                    $setting= new SurveyLanguageSetting;
                    foreach ($insertdata as $k => $v)
                    $setting->$k = $v;
                    $setting->save();
                    fixLanguageConsistency($iSurveyID,$sLanguage);
                    return array('status' => 'OK');
                }
                catch(Exception $e)
                {
                    return array('status' => 'Error');
                }

            }
            else
                return array('status' => 'No permission');
        }
    }

    /**
    * RPC Routine to delete a survey language.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param integer $iSurveyID ID of the survey where a token table will be created for
    * @param string $sLanguage  A valid language shortcut to delete from the current survey. If the language does not exist in that survey no error will be given.
    * @return array Status=>OK when successfull, otherwise the error description
    */
    public function delete_language($sSessionKey, $iSurveyID, $sLanguage)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey=Survey::model()->findByPk($iSurveyID);
            if (is_null($oSurvey))
            {
                return array('status' => 'Error: Invalid survey ID');
            }

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update'))
            {

                Yii::app()->loadHelper('surveytranslator');
                $aLanguages=getLanguageData();

                if(!isset($aLanguages[$sLanguage]))
                {
                    return array('status' => 'Invalid language');
                }
                $oSurvey=Survey::model()->findByPk($iSurveyID);
                if ($sLanguage==$oSurvey->language)
                {
                    return array('status' => 'Cannot remove base language');
                }
                $aLanguages=$oSurvey->getAdditionalLanguages();
                unset($aLanguages[$sLanguage]);
                $oSurvey->additional_languages=implode(' ',$aLanguages);
                try
                {
                    $oSurvey->save(); // save the change to database
                    SurveyLanguageSetting::model()->deleteByPk(array('surveyls_survey_id' => $iSurveyID, 'surveyls_language' => $sLanguage));
                    cleanLanguagesFromSurvey($iSurveyID,$oSurvey->additional_languages);
                    return array('status' => 'OK');
                }
                catch(Exception $e)
                {
                    return array('status' => 'Error');
                }

            }
            else
                return array('status' => 'No permission');
        }
    }


    /**
    * RPC Routine to get survey language properties.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Dd of the Survey
    * @param array $aSurveyLocaleSettings Properties to get
    * @param string $sLang Language to use
    * @return array The requested values
    */
    public function get_language_properties($sSessionKey,$iSurveyID, $aSurveyLocaleSettings, $sLang=NULL)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
            {
                return array('status' => 'Error: Invalid survey ID');
            }
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'read'))
            {
                $aBasicDestinationFields=SurveyLanguageSetting::model()->tableSchema->columnNames;
                $aSurveyLocaleSettings=array_intersect($aSurveyLocaleSettings,$aBasicDestinationFields);

                if ($sLang == NULL || !array_key_exists($sLang,getLanguageDataRestricted()))
                    $sLang = $oSurvey->language;


                $oSurveyLocale=SurveyLanguageSetting::model()->findByAttributes(array('surveyls_survey_id' => $iSurveyID, 'surveyls_language' => $sLang));
                $aResult = array();

                if (empty($aSurveyLocaleSettings))
                    return array('status' => 'No valid Data');

                foreach($aSurveyLocaleSettings as $sPropertyName)
                {
                    $aResult[$sPropertyName]=$oSurveyLocale->$sPropertyName;
                    //$aResult[$sPropertyName]=$aLangAttributes[$sPropertyName];
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session key');
    }

    /**
    * RPC Routine to set survey language properties.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param integer $iSurveyID  - ID of the survey
    * @param array|struct $aSurveyLocaleData - An array with the particular fieldnames as keys and their values to set on that particular survey
    * @param string $sLanguage - Optional - Language to update  - if not give the base language of the particular survey is used
    * @return array Status=>OK, when save successful otherwise error text.
    */
    public function set_language_properties($sSessionKey, $iSurveyID, $aSurveyLocaleData, $sLanguage=NULL)
    {
        Yii::app()->loadHelper("surveytranslator");
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey=Survey::model()->findByPk($iSurveyID);
            if (is_null($oSurvey))
            {
                return array('status' => 'Error: Invalid survey ID');
            }

            if (is_null($sLanguage))
            {
                $sLanguage=$oSurvey->language;
            }

            if (!array_key_exists($sLanguage,getLanguageDataRestricted()))
                return array('status' => 'Error: Invalid language');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveylocale', 'update'))
            {
                // Remove fields that may not be modified
                unset($aSurveyLocaleData['surveyls_language']);
                unset($aSurveyLocaleData['surveyls_survey_id']);

                // Remove invalid fields
                $aDestinationFields=array_flip(SurveyLanguageSetting::model()->tableSchema->columnNames);

                $aSurveyLocaleData=array_intersect_key($aSurveyLocaleData,$aDestinationFields);
                $oSurveyLocale = SurveyLanguageSetting::model()->findByPk(array('surveyls_survey_id' => $iSurveyID, 'surveyls_language' => $sLanguage));

                $aLangAttributes = $oSurveyLocale->getAttributes();
                $aResult = array();

                if (empty($aSurveyLocaleData))
                    return array('status' => 'No valid Data');

                foreach($aSurveyLocaleData as $sFieldName=>$sValue)
                {
                    $oSurveyLocale->$sFieldName=$sValue;
                    try
                    {
                        // save the change to database - Every single change alone - to allow for validation to work
                        $bSaveResult=$oSurveyLocale->save();
                        $aResult[$sFieldName]=$bSaveResult;
                        //unset failed values
                        if (!$bSaveResult)
                        $oSurveyLocale->$sFieldName=$aLangAttributes[$sFieldName];
                    }
                    catch(Exception $e)
                    {
                        $oSurveyLocale->$sFieldName=$aLangAttributes[$sFieldName];
                    }
                }
                $aResult['status'] = 'OK';
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session key');
    }

    /* Group specific functions */

    /**
    * RPC Routine to add an empty group with minimum details.
    * Used as a placeholder for importing questions.
    * Returns the groupid of the created group.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Dd of the Survey to add the group
    * @param string $sGroupTitle Name of the group
    * @param string $sGroupDescription     Optional description of the group
    * @return array|int The id of the new group - Or status
    */
    public function add_group($sSessionKey, $iSurveyID, $sGroupTitle, $sGroupDescription='')
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'update'))
            {
                $oSurvey = Survey::model()->findByPk($iSurveyID);
                if (!isset($oSurvey))
                    return array('status' => 'Error: Invalid survey ID');

                if($oSurvey['active']=='Y')
                    return array('status' => 'Error:Survey is active and not editable');

                $oGroup = new QuestionGroup;
                $oGroup->sid = $iSurveyID;
                $oGroup->group_name =  $sGroupTitle;
                $oGroup->description = $sGroupDescription;
                $oGroup->group_order = getMaxGroupOrder($iSurveyID);
                $oGroup->language =  Survey::model()->findByPk($iSurveyID)->language;
                if($oGroup->save())
                    return (int)$oGroup->gid;
                else
                    return array('status' => 'Creation Failed');
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session Key');
    }

    /**
    * RPC Routine to delete a group of a survey .
    * Returns the id of the deleted group.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the survey that the group belongs
    * @param int $iGroupID Id of the group to delete
    * @return array|int The id of the deleted group or status
    */
    public function delete_group($sSessionKey, $iSurveyID, $iGroupID)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $iSurveyID = sanitize_int($iSurveyID);
            $iGroupID = sanitize_int($iGroupID);
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'delete'))
            {
                $oGroup = QuestionGroup::model()->findByAttributes(array('gid' => $iGroupID));
                if (!isset($oGroup))
                    return array('status' => 'Error: Invalid group ID');

                if($oSurvey['active']=='Y')
                    return array('status' => 'Error:Survey is active and not editable');

                $depented_on = getGroupDepsForConditions($oGroup->sid,"all",$iGroupID,"by-targgid");
                if(isset($depented_on))
                    return array('status' => 'Group with depencdencies - deletion not allowed');

                $iGroupsDeleted = QuestionGroup::deleteWithDependency($iGroupID, $iSurveyID);

                if ($iGroupsDeleted === 1)
                {
                    fixSortOrderGroups($iSurveyID);
                    return (int)$iGroupID;
                }
                else
                    return array('status' => 'Group deletion failed');
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session Key');
    }

    /**
    * RPC Routine to import a group - imports lsg,csv
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID The id of the survey that the group will belong
    * @param string $sImportData String containing the BASE 64 encoded data of a lsg,csv
    * @param string $sImportDataType  lsg,csv
    * @param string $sNewGroupName  Optional new name for the group
    * @param string $sNewGroupDescription  Optional new description for the group
    * @return array|integer iGroupID  - ID of the new group or status
    */
    public function import_group($sSessionKey, $iSurveyID, $sImportData, $sImportDataType, $sNewGroupName=NULL, $sNewGroupDescription=NULL)
    {

        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'update'))
            {
                if($oSurvey->getAttribute('active') =='Y')
                    return array('status' => 'Error:Survey is active and not editable');

                if (!in_array($sImportDataType,array('csv','lsg'))) return array('status' => 'Invalid extension');
                libxml_use_internal_errors(true);
                Yii::app()->loadHelper('admin/import');
                // First save the data to a temporary file
                $sFullFilePath = Yii::app()->getConfig('tempdir') . DIRECTORY_SEPARATOR . randomChars(40).'.'.$sImportDataType;
                file_put_contents($sFullFilePath,base64_decode(chunk_split($sImportData)));

                if ( strtolower($sImportDataType)=='lsg')
                {
                    $sXMLdata = file_get_contents($sFullFilePath);
                    $xml = @simplexml_load_string($sXMLdata,'SimpleXMLElement',LIBXML_NONET);
                    if(!$xml)
                    {
                        unlink($sFullFilePath);
                        return array('status' => 'Error: Invalid LimeSurvey group structure XML ');
                    }
                    $aImportResults = XMLImportGroup($sFullFilePath, $iSurveyID);
                }
                else
                    return array('status' => 'Invalid extension'); //just for symmetry!

                unlink($sFullFilePath);

                if (isset($aImportResults['fatalerror'])) return array('status' => 'Error: '.$aImportResults['fatalerror']);
                else
                {
                    $iNewgid = $aImportResults['newgid'];

                    $oGroup = QuestionGroup::model()->findByAttributes(array('gid' => $iNewgid));
                    $slang=$oGroup['language'];
                    if($sNewGroupName!='')
                        $oGroup->setAttribute('group_name',$sNewGroupName);
                    if($sNewGroupDescription!='')
                    $oGroup->setAttribute('description',$sNewGroupDescription);
                    try
                    {
                        $oGroup->save();
                    }
                    catch(Exception $e)
                    {
                        // no need to throw exception
                    }
                    return (int)$aImportResults['newgid'];
                }
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC Routine to find response IDs given a survey ID and a token.
    * @param string $sSessionKey
    * @param int $iSurveyID
    * @param string $sToken
    */
    public function get_response_ids($sSessionKey, $iSurveyID, $sToken)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $responses = SurveyDynamic::model($iSurveyID)->findAllByAttributes(array('token' => $sToken));
            $result = array();
            foreach ($responses as $response)
            {
                $result[] = (int) $response->id;
            }
            return $result;
        }
        else
        {
            return array('status' => 'Invalid Session Key');
        }

    }

    /**
    * RPC Routine to return properties of a group of a survey .
    * Returns array of properties
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iGroupID Id of the group to get properties
    * @param array  $aGroupSettings The properties to get
    * @return array The requested values
    */
    public function get_group_properties($sSessionKey, $iGroupID, $aGroupSettings)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oGroup = QuestionGroup::model()->findByAttributes(array('gid' => $iGroupID));
            if (!isset($oGroup))
                return array('status' => 'Error: Invalid group ID');

            if (Permission::model()->hasSurveyPermission($oGroup->sid, 'survey', 'read'))
            {
                $aBasicDestinationFields=QuestionGroup::model()->tableSchema->columnNames;
                $aGroupSettings=array_intersect($aGroupSettings,$aBasicDestinationFields);

                if (empty($aGroupSettings))
                    return array('status' => 'No valid Data');

                foreach($aGroupSettings as $sGroupSetting)
                {
                    $aResult[$sGroupSetting] = $oGroup->$sGroupSetting;
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session Key');
    }


    /**
    * RPC Routine to set group properties.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param integer $iGroupID  - ID of the survey
    * @param array|struct $aGroupData - An array with the particular fieldnames as keys and their values to set on that particular survey
    * @return array Of succeeded and failed modifications according to internal validation.
    */
    public function set_group_properties($sSessionKey, $iGroupID, $aGroupData)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oGroup=QuestionGroup::model()->findByAttributes(array('gid' => $iGroupID));
            if (is_null($oGroup))
            {
                return array('status' => 'Error: Invalid group ID');
            }
            if (Permission::model()->hasSurveyPermission($oGroup->sid, 'survey', 'update'))
            {
                $aResult = array();
                // Remove fields that may not be modified
                unset($aGroupData['sid']);
                unset($aGroupData['gid']);
                // Remove invalid fields
                $aDestinationFields=array_flip(QuestionGroup::model()->tableSchema->columnNames);
                $aGroupData=array_intersect_key($aGroupData,$aDestinationFields);
                $aGroupAttributes = $oGroup->getAttributes();
                if (empty($aGroupData))
                    return array('status' => 'No valid Data');

                foreach($aGroupData as $sFieldName=>$sValue)
                {
                    //all dependencies this group has
                    $has_dependencies=getGroupDepsForConditions($oGroup->sid,$iGroupID);
                    //all dependencies on this group
                    $depented_on = getGroupDepsForConditions($oGroup->sid,"all",$iGroupID,"by-targgid");
                    //We do not allow groups with dependencies to change order - that would lead to broken dependencies

                    if((isset($has_dependencies) || isset($depented_on))  && $sFieldName == 'group_order')
                    {
                        $aResult[$sFieldName]='Group with dependencies - Order cannot be changed';
                        continue;
                    }
                    $oGroup->setAttribute($sFieldName,$sValue);

                    try
                    {
                        // save the change to database - one by one to allow for validation to work
                        $bSaveResult=$oGroup->save();
                        fixSortOrderGroups($oGroup->sid);
                        $aResult[$sFieldName] = $bSaveResult;
                        //unset failed values
                        if (!$bSaveResult)
                        $oGroup->$sFieldName=$aGroupAttributes[$sFieldName];
                    }
                    catch(Exception $e)
                    {
                        //unset values that cause exception
                        $oGroup->$sFieldName=$aGroupAttributes[$sFieldName];
                    }
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session key');
    }

    /* Question specific functions */


    /**
    * RPC Routine to delete a question of a survey .
    * Returns the id of the deleted question.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int iQuestionID Id of the question to delete
    * @return array|int Id of the deleted Question or status
    */
    public function delete_question($sSessionKey, $iQuestionID)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oQuestion = Question::model()->findByAttributes(array('qid' => $iQuestionID));
            if (!isset($oQuestion))
                return array('status' => 'Error: Invalid question ID');

            $iSurveyID = $oQuestion['sid'];

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'surveycontent', 'delete'))
            {
                $oSurvey = Survey::model()->findByPk($iSurveyID);

                if($oSurvey['active']=='Y')
                    return array('status' => 'Survey is active and not editable');
                $iGroupID=$oQuestion['gid'];

                $oCondition = Condition::model()->findAllByAttributes(array('cqid' => $iQuestionID));
                if(count($oCondition)>0)
                return array('status' => 'Cannot delete Question. Others rely on this question');

                LimeExpressionManager::RevertUpgradeConditionsToRelevance(NULL,$iQuestionID);

                try
                {
                    Condition::model()->deleteAllByAttributes(array('qid' => $iQuestionID));
                    QuestionAttribute::model()->deleteAllByAttributes(array('qid' => $iQuestionID));
                    Answer::model()->deleteAllByAttributes(array('qid' => $iQuestionID));

                    $sCriteria = new CDbCriteria;
                    $sCriteria->addCondition('qid = :qid or parent_qid = :qid');
                    $sCriteria->params[':qid'] = $iQuestionID;
                    Question::model()->deleteAll($sCriteria);

                    DefaultValue::model()->deleteAllByAttributes(array('qid' => $iQuestionID));
                    QuotaMember::model()->deleteAllByAttributes(array('qid' => $iQuestionID));
                    Question::updateSortOrder($iGroupID, $iSurveyID);

                    return (int)$iQuestionID;
                }
                catch(Exception $e)
                {
                    return array('status' => 'Error');
                }

            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }


    /**
    * RPC Routine to import a question - imports lsq,csv.
    *
    * @access public
    * @param string $sSessionKey
    * @param int $iSurveyID The id of the survey that the question will belong
    * @param int $iGroupID The id of the group that the question will belong
    * @param string $sImportData String containing the BASE 64 encoded data of a lsg,csv
    * @param string $sImportDataType  lsq,csv
    * @param string $sMandatory Optional Mandatory question option (default to No)
    * @param string $sNewQuestionTitle  Optional new title for the question
    * @param string $sNewqQuestion An optional new question
    * @param string $sNewQuestionHelp An optional new question help text
    * @return array|integer iQuestionID  - ID of the new question - Or status
    */
    public function import_question($sSessionKey, $iSurveyID,$iGroupID, $sImportData, $sImportDataType, $sMandatory='N', $sNewQuestionTitle=NULL, $sNewqQuestion=NULL, $sNewQuestionHelp=NULL)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'update'))
            {
                if($oSurvey->getAttribute('active') =='Y')
                    return array('status' => 'Error:Survey is Active and not editable');

                $oGroup = QuestionGroup::model()->findByAttributes(array('gid' => $iGroupID));
                if (!isset($oGroup))
                    return array('status' => 'Error: Invalid group ID');

                $sGroupSurveyID = $oGroup['sid'];
                if($sGroupSurveyID != $iSurveyID)
                    return array('status' => 'Error: Missmatch in surveyid and groupid');

                if (!in_array($sImportDataType,array('csv','lsq'))) return array('status' => 'Invalid extension');
                libxml_use_internal_errors(true);
                Yii::app()->loadHelper('admin/import');
                // First save the data to a temporary file
                $sFullFilePath = Yii::app()->getConfig('tempdir') . DIRECTORY_SEPARATOR . randomChars(40).'.'.$sImportDataType;
                file_put_contents($sFullFilePath,base64_decode(chunk_split($sImportData)));

                if ( strtolower($sImportDataType)=='lsq')
                {
                    $sXMLdata = file_get_contents($sFullFilePath);
                    $xml = @simplexml_load_string($sXMLdata,'SimpleXMLElement',LIBXML_NONET);
                    if(!$xml)
                    {
                        unlink($sFullFilePath);
                        return array('status' => 'Error: Invalid LimeSurvey question structure XML ');
                    }
                    $aImportResults =  XMLImportQuestion($sFullFilePath, $iSurveyID, $iGroupID);
                }
                else
                    return array('status' => 'Really Invalid extension'); //just for symmetry!

                unlink($sFullFilePath);

                if (isset($aImportResults['fatalerror'])) return array('status' => 'Error: '.$aImportResults['fatalerror']);
                else
                {
                    fixLanguageConsistency($iSurveyID);
                    $iNewqid = $aImportResults['newqid'];

                    $oQuestion = Question::model()->findByAttributes(array('sid' => $iSurveyID, 'gid' => $iGroupID, 'qid' => $iNewqid));
                    if($sNewQuestionTitle!=NULL)
                        $oQuestion->setAttribute('title',$sNewQuestionTitle);
                    if($sNewqQuestion!='')
                        $oQuestion->setAttribute('question',$sNewqQuestion);
                    if($sNewQuestionHelp!='')
                        $oQuestion->setAttribute('help',$sNewQuestionHelp);
                    if(in_array($sMandatory, array('Y','N')))
                        $oQuestion->setAttribute('mandatory',$sMandatory);
                    else
                    $oQuestion->setAttribute('mandatory','N');

                    try
                    {
                        $oQuestion->save();
                    }
                    catch(Exception $e)
                    {
                        // no need to throw exception
                    }
                    return (int)$aImportResults['newqid'];
                }
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }


    /**
    * RPC Routine to return properties of a question of a survey.
    * Returns string
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iQuestionID Id of the question to get properties
    * @param array $aQuestionSettings The properties to get
    * @param string $sLanguage Optional parameter language for multilingual questions
    * @return array The requested values
    */
    public function get_question_properties($sSessionKey, $iQuestionID, $aQuestionSettings, $sLanguage=NULL)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            Yii::app()->loadHelper("surveytranslator");
            $oQuestion = Question::model()->findByAttributes(array('qid' => $iQuestionID));
            if (!isset($oQuestion))
                return array('status' => 'Error: Invalid questionid');

            $iSurveyID = $oQuestion->sid;

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'read'))
            {
                if (is_null($sLanguage))
                    $sLanguage=Survey::model()->findByPk($iSurveyID)->language;

                if (!array_key_exists($sLanguage,getLanguageDataRestricted()))
                    return array('status' => 'Error: Invalid language');

                $oQuestion = Question::model()->findByAttributes(array('qid' => $iQuestionID, 'language'=>$sLanguage));
                if (!isset($oQuestion))
                    return array('status' => 'Error: Invalid questionid');

                $aBasicDestinationFields=Question::model()->tableSchema->columnNames;
                array_push($aBasicDestinationFields,'available_answers')    ;
                array_push($aBasicDestinationFields,'subquestions')    ;
                array_push($aBasicDestinationFields,'attributes')    ;
                array_push($aBasicDestinationFields,'attributes_lang')    ;
                array_push($aBasicDestinationFields,'answeroptions')    ;
                array_push($aBasicDestinationFields,'defaultvalue');
                $aQuestionSettings=array_intersect($aQuestionSettings,$aBasicDestinationFields);

                if (empty($aQuestionSettings))
                    return array('status' => 'No valid Data');

                $aResult=array();
                foreach ($aQuestionSettings as $sPropertyName )
                {
                    if ($sPropertyName == 'available_answers' || $sPropertyName == 'subquestions')
                    {
                        $oSubQuestions =  Question::model()->findAllByAttributes(array('parent_qid' => $iQuestionID,'language'=>$sLanguage ),array('order'=>'title') );
                        if (count($oSubQuestions)>0)
                        {
                            $aData = array();
                            foreach($oSubQuestions as $oSubQuestion)
                            {
                                if($sPropertyName == 'available_answers')
                                    $aData[$oSubQuestion['title']]= $oSubQuestion['question'];
                                else
                                {
                                    $aData[$oSubQuestion['qid']]['title']= $oSubQuestion['title'];
                                    $aData[$oSubQuestion['qid']]['question']= $oSubQuestion['question'];
                                    $aData[$oSubQuestion['qid']]['scale_id']= $oSubQuestion['scale_id'];
                                }

                            }

                            $aResult[$sPropertyName]=$aData;
                        }
                        else
                            $aResult[$sPropertyName]='No available answers';
                    }
                    else if ($sPropertyName == 'attributes')
                    {
                        $oAttributes =  QuestionAttribute::model()->findAllByAttributes(array('qid' => $iQuestionID, 'language'=> null ),array('order'=>'attribute') );
                        if (count($oAttributes)>0)
                        {
                            $aData = array();
                            foreach($oAttributes as $oAttribute)
                                $aData[$oAttribute['attribute']]= $oAttribute['value'];

                            $aResult['attributes']=$aData;
                        }
                        else
                            $aResult['attributes']='No available attributes';
                    }
                    else if ($sPropertyName == 'attributes_lang')
                    {
                        $oAttributes =  QuestionAttribute::model()->findAllByAttributes(array('qid' => $iQuestionID, 'language'=> $sLanguage ),array('order'=>'attribute') );
                        if (count($oAttributes)>0)
                        {
                            $aData = array();
                            foreach($oAttributes as $oAttribute)
                                $aData[$oAttribute['attribute']]= $oAttribute['value'];

                            $aResult['attributes_lang']=$aData;
                        }
                        else
                            $aResult['attributes_lang']='No available attributes';
                    }
                    else if ($sPropertyName == 'answeroptions')
                    {
                        $oAttributes = Answer::model()->findAllByAttributes(array('qid' => $iQuestionID, 'language'=> $sLanguage ),array('order'=>'sortorder') );
                        if (count($oAttributes)>0)
                        {
                            $aData = array();
                            foreach($oAttributes as $oAttribute) {
                                $aData[$oAttribute['code']]['answer']=$oAttribute['answer'];
                                $aData[$oAttribute['code']]['assessment_value']=$oAttribute['assessment_value'];
                                $aData[$oAttribute['code']]['scale_id']=$oAttribute['scale_id'];
                            }
                            $aResult['answeroptions']=$aData;
                        }
                        else
                            $aResult['answeroptions']='No available answer options';
                    }
                    else if ($sPropertyName == 'defaultvalue')
                    {
					    $aResult['defaultvalue'] = DefaultValue::model()->findByAttributes(array('qid' => $iQuestionID, 'language'=> $sLanguage))->defaultvalue;
					}
					else
                    {
                        $aResult[$sPropertyName]=$oQuestion->$sPropertyName;
                    }
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC Routine to set question properties.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param integer $iQuestionID  - ID of the question
    * @param array|struct $aQuestionData - An array with the particular fieldnames as keys and their values to set on that particular question
    * @param string $sLanguage Optional parameter language for multilingual questions
    * @return array Of succeeded and failed modifications according to internal validation.
    */
    public function set_question_properties($sSessionKey, $iQuestionID, $aQuestionData,$sLanguage=NULL)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            Yii::app()->loadHelper("surveytranslator");
            $oQuestion=Question::model()->findByAttributes(array('qid' => $iQuestionID));
            if (is_null($oQuestion))
                return array('status' => 'Error: Invalid group ID');

            $iSurveyID = $oQuestion->sid;

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'update'))
            {
                if (is_null($sLanguage))
                    $sLanguage=Survey::model()->findByPk($iSurveyID)->language;

                if (!array_key_exists($sLanguage,getLanguageDataRestricted()))
                    return array('status' => 'Error: Invalid language');

                $oQuestion = Question::model()->findByAttributes(array('qid' => $iQuestionID, 'language'=>$sLanguage));
                if (!isset($oQuestion))
                    return array('status' => 'Error: Invalid questionid');

                // Remove fields that may not be modified
                unset($aQuestionData['qid']);
                unset($aQuestionData['gid']);
                unset($aQuestionData['sid']);
                unset($aQuestionData['parent_qid']);
                unset($aQuestionData['language']);
                unset($aQuestionData['type']);
                // Remove invalid fields
                $aDestinationFields=array_flip(Question::model()->tableSchema->columnNames);
                $aQuestionData=array_intersect_key($aQuestionData,$aDestinationFields);
                $aQuestionAttributes = $oQuestion->getAttributes();

                if (empty($aQuestionData))
                    return array('status' => 'No valid Data');

                foreach($aQuestionData as $sFieldName=>$sValue)
                {
                    //all the dependencies that this question has to other questions
                    $dependencies=getQuestDepsForConditions($oQuestion->sid,$oQuestion->gid,$iQuestionID);
                    //all dependencies by other questions to this question
                    $is_criteria_question=getQuestDepsForConditions($oQuestion->sid,$oQuestion->gid,"all",$iQuestionID,"by-targqid");
                    //We do not allow questions with dependencies in the same group to change order - that would lead to broken dependencies

                    if((isset($dependencies) || isset($is_criteria_question))  && $sFieldName == 'question_order')
                    {
                        $aResult[$sFieldName]='Questions with dependencies - Order cannot be changed';
                        continue;
                    }
                    $oQuestion->setAttribute($sFieldName,$sValue);

                    try
                    {
                        $bSaveResult=$oQuestion->save(); // save the change to database
                        Question::model()->updateQuestionOrder($oQuestion->gid, $oQuestion->language);
                        $aResult[$sFieldName]=$bSaveResult;
                        //unset fields that failed
                        if (!$bSaveResult)
                        $oQuestion->$sFieldName=$aQuestionAttributes[$sFieldName];
                    }
                    catch(Exception $e)
                    {
                        //unset fields that caused exception
                        $oQuestion->$sFieldName=$aQuestionAttributes[$sFieldName];
                    }
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session key');
    }




    /* Participant-Token specific functions */



    /**
    * RPC Routine to add participants to the tokens collection of the survey.
    * Returns the inserted data including additional new information like the Token entry ID and the token string.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey
    * @param struct $aParticipantData Data of the participants to be added
    * @param bool Optional - Defaults to true and determins if the access token automatically created
    * @return array The values added
    */
    public function add_participants($sSessionKey, $iSurveyID, $aParticipantData, $bCreateToken=true)
    {
        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');
        $oSurvey=Survey::model()->findByPk($iSurveyID);
        if (is_null($oSurvey))
        {
            return array('status' => 'Error: Invalid survey ID');
        }

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'create'))
        {
            if (!Yii::app()->db->schema->getTable('{{tokens_' . $iSurveyID . '}}'))
                return array('status' => 'No token table');
            $aDestinationFields = array_flip(Token::model($iSurveyID)->getMetaData()->tableSchema->columnNames);
            foreach ($aParticipantData as &$aParticipant)
            {
                $token = Token::create($iSurveyID);
                $token->setAttributes(array_intersect_key($aParticipant,$aDestinationFields));
                if  ($bCreateToken)
                {
                    $token->generateToken();
                }
                if ($token->save())
                {
                    $aParticipant = $token->getAttributes();
                }
                else
                {
                    $aParticipant["errors"] = $token->errors;
                }
            }
            return $aParticipantData;
        }
        else
            return array('status' => 'No permission');
    }

    /**
    * RPC Routine to delete multiple participants of a Survey.
    * Returns the id of the deleted token
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey that the participants belong to
    * @param array $aTokenIDs Id of the tokens/participants to delete
    * @return array Result of deletion
    */
    public function delete_participants($sSessionKey, $iSurveyID, $aTokenIDs)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $iSurveyID = sanitize_int($iSurveyID);

            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'delete'))
            {
                if(!tableExists("{{tokens_$iSurveyID}}"))
                    return array('status' => 'Error: No token table');

                $aResult=array();
                foreach($aTokenIDs as $iTokenID)
                {
                    $token = Token::model($iSurveyID)->findByPk($iTokenID);
                    if (!isset($token))
                        $aResult[$iTokenID]='Invalid token ID';
                    elseif($token->delete())
                        $aResult[$iTokenID]='Deleted';
                    else
                        $aResult[$iTokenID]='Deletion went wrong';
                }
                return $aResult;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session Key');
    }


    /**
    * RPC Routine to return settings of a token/participant of a survey .
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey to get token properties
    * @param int $iTokenID Id of the participant to check
    * @param array $aTokenProperties The properties to get
    * @return array The requested values
    */
    public function get_participant_properties($sSessionKey, $iSurveyID, $iTokenID, $aTokenProperties)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $surveyidExists = Survey::model()->findByPk($iSurveyID);
            if (!isset($surveyidExists))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'read'))
            {
                if(!tableExists("{{tokens_$iSurveyID}}"))
                    return array('status' => 'Error: No token table');

                $token = Token::model($iSurveyID)->findByPk($iTokenID);
                if (!isset($token))
                    return array('status' => 'Error: Invalid tokenid');

                $result = array_intersect_key($token->attributes, array_flip($aTokenProperties));
                if (empty($result))
                {
                    return array('status' => 'No valid Data');
                }
                else
                {
                    return $result;
                }


            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session Key');
    }

    /**
    * RPC Routine to set properties of a survey participant/token.
    * Returns array
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the survey that participants belong
    * @param int $iTokenID Id of the participant to alter
    * @param array|struct $aTokenData Data to change
    * @return array Result of the change action
    */
    public function set_participant_properties($sSessionKey, $iSurveyID, $iTokenID, $aTokenData)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'update'))
            {
                if(!tableExists("{{tokens_$iSurveyID}}"))
                    return array('status' => 'Error: No token table');

                $oToken = Token::model($iSurveyID)->findByPk($iTokenID);
                if (!isset($oToken))
                    return array('status' => 'Error: Invalid tokenid');

                $aResult = array();
                // Remove fields that may not be modified
                unset($aTokenData['tid']);

                $aBasicDestinationFields = array_flip($oToken->getTableSchema()->columnNames);
                $aTokenData = array_intersect_key($aTokenData,$aBasicDestinationFields);

                if (empty($aTokenData))
                    return array('status' => 'No valid Data');

                $oToken->setAttributes($aTokenData, false);
                if ($oToken->save())
                {
                    return $oToken->attributes;
                }
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session Key');
    }


    /**
    * RPC Routine to return the ids and info of groups belonging to survey .
    * Returns array of ids and info.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey containing the groups
    * @return array The list of groups
    */
    public function list_groups($sSessionKey, $iSurveyID)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'read'))
            {
                $oGroupList = QuestionGroup::model()->findAllByAttributes(array("sid"=>$iSurveyID));
                if(count($oGroupList)==0)
                    return array('status' => 'No groups found');

                foreach ($oGroupList as $oGroup)
                {
                    $aData[]= array('id'=>$oGroup->primaryKey) + $oGroup->attributes;
                }
                return $aData;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid S ession Key');
    }

    /**
    * RPC Routine to return the ids and info  of token/participants of a survey.
    * if $bUnused is true, user will get the list of not completed tokens (token_return functionality).
    * Parameters iStart and ilimit are used to limit the number of results of this call.
    * Parameter aAttributes is an optional array containing more attribute that may be requested
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the survey to list participants
    * @param int $iStart Start id of the token list
    * @param int  $iLimit Number of participants to return
    * @param bool $bUnused If you want unused tokens, set true
    * @param bool|array $aAttributes The extented attributes that we want
    * @param array|struct $aConditions Optional conditions to limit the list, e.g. with array('email' => 'info@example.com')
    * @return array The list of tokens
    */
    public function list_participants($sSessionKey, $iSurveyID, $iStart=0, $iLimit=10, $bUnused=false, $aAttributes=false, $aConditions=array() )
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'read'))
            {
                if(!tableExists("{{tokens_$iSurveyID}}"))
                    return array('status' => 'Error: No token table');

                $aAttributeValues = array();
                if (count($aConditions)) {
                    $aConditionFields = array_flip(Token::model($iSurveyID)->getMetaData()->tableSchema->columnNames);
                    $aAttributeValues = array_intersect_key($aConditions, $aConditionFields);
                }

                if($bUnused)
                    $oTokens = Token::model($iSurveyID)->incomplete()->findAllByAttributes($aAttributeValues, array('order' => 'tid', 'limit' => $iLimit, 'offset' => $iStart));
                else
                    $oTokens = Token::model($iSurveyID)->findAllByAttributes($aAttributeValues, array('order' => 'tid', 'limit' => $iLimit, 'offset' => $iStart));

                if(count($oTokens)==0)
                    return array('status' => 'No Tokens found');

                $extendedAttributes = array();
                if($aAttributes) {
                    $aBasicDestinationFields=Token::model($iSurveyID)->tableSchema->columnNames;
                    $aTokenProperties=array_intersect($aAttributes,$aBasicDestinationFields);
                    $currentAttributes = array('tid','token','firstname','lastname','email');
                    $extendedAttributes = array_diff($aTokenProperties, $currentAttributes);
                }

                foreach ($oTokens as $token)
                {
                    $aTempData = array(
                        'tid'=>$token->primarykey,
                        'token'=>$token->attributes['token'],
                        'participant_info'=>array(
                            'firstname'=>$token->attributes['firstname'],
                            'lastname'=>$token->attributes['lastname'],
                            'email'=>$token->attributes['email'],
                    ));
                    foreach($extendedAttributes as $sAttribute)
                    {
                        $aTempData[$sAttribute]=$token->attributes[$sAttribute];
                    }
                    $aData[]= $aTempData;
                }
                return $aData;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid Session Key');
    }

    /**
    * RPC Routine to return the ids and info of (sub-)questions of a survey/group.
    * Returns array of ids and info.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the survey to list questions
    * @param int $iGroupID Optional id of the group to list questions
    * @param string $sLanguage Optional parameter language for multilingual questions
    * @return array The list of questions
    */
    public function list_questions($sSessionKey, $iSurveyID, $iGroupID=NULL, $sLanguage=NULL)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            Yii::app()->loadHelper("surveytranslator");
            $oSurvey = Survey::model()->findByPk($iSurveyID);
            if (!isset($oSurvey))
                return array('status' => 'Error: Invalid survey ID');

            if (Permission::model()->hasSurveyPermission($iSurveyID, 'survey', 'read'))
            {
                if (is_null($sLanguage))
                    $sLanguage=$oSurvey->language;

                if (!array_key_exists($sLanguage,getLanguageDataRestricted()))
                    return array('status' => 'Error: Invalid language');

                if($iGroupID!=NULL)
                {
                    $oGroup = QuestionGroup::model()->findByAttributes(array('gid' => $iGroupID));
                    $sGroupSurveyID = $oGroup['sid'];

                    if($sGroupSurveyID != $iSurveyID)
                        return array('status' => 'Error: IMissmatch in surveyid and groupid');
                    else
                        $aQuestionList = Question::model()->findAllByAttributes(array("sid"=>$iSurveyID, "gid"=>$iGroupID,"language"=>$sLanguage));
                }
                else
                    $aQuestionList = Question::model()->findAllByAttributes(array("sid"=>$iSurveyID, "language"=>$sLanguage));

                if(count($aQuestionList)==0)
                    return array('status' => 'No questions found');

                foreach ($aQuestionList as $oQuestion)
                {
                    $aData[]= array('id'=>$oQuestion->primaryKey) + $oQuestion->attributes;
                }
                return $aData;
            }
            else
                return array('status' => 'No permission');
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC Routine to list the ids and info of surveys belonging to a user.
    * Returns array of ids and info.
    * If user is admin he can get surveys of every user (parameter sUser) or all surveys (sUser=null)
    * Else only the syrveys belonging to the user requesting will be shown.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param string $sUser Optional username to get list of surveys
    * @return array The list of surveys
    */
    public function list_surveys($sSessionKey, $sUsername=NULL)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            $oSurvey = new Survey;
            if (!Permission::model()->hasGlobalPermission('superadmin','read') && ($sUsername == null))
            {
                $oSurvey->permission(Yii::app()->user->getId());
            }
            elseif ($sUsername!=null)
            {
                $aUserData = User::model()->findByAttributes(array('users_name' => $sUsername));
                if (!isset($aUserData))
                    return array('status' => 'Invalid user');
                else
                    $sUid = $aUserData->attributes['uid'];
                $oSurvey->permission($sUid);
            }

            $aUserSurveys = $oSurvey->with(array('languagesettings'=>array('condition'=>'surveyls_language=language'), 'owner'))->findAll();
            if(count($aUserSurveys)==0)
                return array('status' => 'No surveys found');

            foreach ($aUserSurveys as $oSurvey)
            {
                $oSurveyLanguageSettings = SurveyLanguageSetting::model()->findByAttributes(array('surveyls_survey_id' => $oSurvey->primaryKey, 'surveyls_language' => $oSurvey->language));
                if (!isset($oSurveyLanguageSettings))
                    $aSurveyTitle = '';
                else
                    $aSurveyTitle = $oSurveyLanguageSettings->attributes['surveyls_title'];
                $aData[]= array('sid'=>$oSurvey->primaryKey,'surveyls_title'=>$aSurveyTitle,'startdate'=>$oSurvey->attributes['startdate'],'expires'=>$oSurvey->attributes['expires'],'active'=>$oSurvey->attributes['active']);
            }
            return $aData;
        }
        else
            return array('status' => 'Invalid session key');
    }

    /**
    * RPC Routine to list the ids and info of users.
    * Returns array of ids and info.
    * @param string $sSessionKey Auth credentials
    * @return array The list of users
    */

    public function list_users($sSessionKey = null)
    {
        if ($this->_checkSessionKey($sSessionKey))
        {
            if( Permission::model()->hasGlobalPermission('superadmin','read') )
            {
                $users = User::model()->findAll();

                if(count($users)==0)
                    return array('status' => 'No surveys found');

                foreach ($users as $user)
                {
                    $attributes = $user->attributes;
                    $attributes['permissions'] = array();
                    foreach ($user->permissions as $permission)
                    {
                        $attributes['permissions'][] = $permission->attributes;
                    }
                    unset($attributes['password']);
                    $data[] = $attributes;
                }
                return $data;
            }
            else
            {
                return array('status' => 'Permission denied.');
            }
        }
        else
        {
            return array('status' => 'Invalid session key');
        }
    }
    /**
    * RPC routine to to initialise the survey's collection of tokens where new participant tokens may be later added.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param integer $iSurveyID ID of the survey where a token table will be created for
    * @param array $aAttributeFields  An array of integer describing any additional attribute fields
    * @return array Status=>OK when successfull, otherwise the error description
    */
    public function activate_tokens($sSessionKey, $iSurveyID, $aAttributeFields=array())
    {
        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');
        if (Permission::model()->hasGlobalPermission('surveys','create'))
        {
            $oSurvey=Survey::model()->findByPk($iSurveyID);
            if (is_null($oSurvey))
            {
                return array('status' => 'Error: Invalid survey ID');
            }
            if (is_array($aAttributeFields) && count($aAttributeFields)>0)
            {
                foreach ($aAttributeFields as &$sField)
                {
                    $sField= intval($sField);
                    $sField='attribute_'.$sField;
                }
                $aAttributeFields=array_unique($aAttributeFields);
            }
            Yii::app()->loadHelper('admin/token');
            if (Token::createTable($iSurveyID, $aAttributeFields))
            {
                return array('status' => 'OK');
            }
            else
            {
                return array('status' => 'Token table could not be created');
            }
        }
        else
            return array('status' => 'No permission');
    }

    /**
     * RPC Routine to send register mails to participants in a survey
     * Returns array of results of sending
     *
     * @access public
     * @param string $sSessionKey Auth credentials
     * @param int $iSurveyID ID of the survey that participants belong
     * @param array $overrideAllConditions replace the default conditions, like this:
     *   $overrideAllConditions = Array();
     *   $overrideAllConditions[] = 'tid = 2';
     *   $response = $myJSONRPCClient->mail_registered_participants( $sessionKey, $survey_id, $overrideAllConditions );
     * @return array Result of the action
     */
    public function mail_registered_participants($sSessionKey, $iSurveyID, $overrideAllConditions=Array() )
    {
        Yii::app()->loadHelper('admin/token');
        if (!$this->_checkSessionKey($sSessionKey))
            return array('status' => 'Invalid session key');

        $oSurvey = Survey::model()->findByPk($iSurveyID);
        if (!isset($oSurvey))
            return array('status' => 'Error: Invalid survey ID');

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'update'))
        {

            if(!tableExists("{{tokens_$iSurveyID}}"))
                return array('status' => 'Error: No token table');

			$command = new CDbCriteria();
			$command->condition = '';
			if (count($overrideAllConditions)) {
				foreach($overrideAllConditions as $condition)
				{
					$command->addCondition($condition);
				}
			}
			else
			{
				$command->addCondition('usesleft > 0');
				$command->addCondition("sent = 'N'");
				$command->addCondition("remindersent = 'N'");
				$command->addCondition("(completed ='N') or (completed='')");
				$command->addCondition('ISNULL(validfrom) OR validfrom < NOW()');
				$command->addCondition('ISNULL(validuntil) OR validuntil > NOW()');
				$command->addCondition('emailstatus = "OK"');
			}
			$command->order = 'tid';

            $aAllTokens = Token::model($iSurveyID)->findAll( $command );
            $iAllTokensCount=count($aAllTokens);
            unset($aAllTokens);

            $iMaxEmails = (int)Yii::app()->getConfig("maxemails");
			$command->limit = $iMaxEmails;
			$aResultTokens = Token::model($iSurveyID)->findAll( $command );

            if (empty($aResultTokens))
                return array('status' => 'Error: No candidate tokens');

            foreach($aResultTokens as $key=>$oToken)
            {
                //pattern taken from php_filter_validate_email PHP_5_4/ext/filter/logical_filters.c
                $pattern = '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD';

                //if(!filter_var($emailaddress, FILTER_VALIDATE_EMAIL))
                if (preg_match($pattern, $oToken['email']) !== 1)
				{
					unset($aResultTokens[$key]);
					//subtract from 'left to send'
					$iAllTokensCount--;
				}
            }

            if (empty($aResultTokens))
                return array('status' => 'Error: No candidate tokens');
            $aResult = emailTokens($iSurveyID,$aResultTokens,'register');
            $iLeft = $iAllTokensCount - count($aResultTokens);
            $aResult['status'] = $iLeft . " left to send";

            return $aResult;
        }
        else
            return array('status' => 'No permission');
    }

    /**
    * RPC Routine to invite participants in a survey
    * Returns array of results of sending
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID ID of the survey that participants belong
    * @return array Result of the action
    */
    public function invite_participants($sSessionKey, $iSurveyID )
    {
        Yii::app()->loadHelper('admin/token');
        if (!$this->_checkSessionKey($sSessionKey))
            return array('status' => 'Invalid session key');

        $oSurvey = Survey::model()->findByPk($iSurveyID);
        if (!isset($oSurvey))
            return array('status' => 'Error: Invalid survey ID');

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'update'))
        {

            if(!tableExists("{{tokens_$iSurveyID}}"))
                return array('status' => 'Error: No token table');

            $iMaxEmails = (int)Yii::app()->getConfig("maxemails");
            $SQLemailstatuscondition = "emailstatus = 'OK'";

            $oTokens = TokenDynamic::model($iSurveyID);
            $aResultTokens = $oTokens->findUninvited(false, $iMaxEmails, true, $SQLemailstatuscondition);
            $aAllTokens = $oTokens->findUninvitedIDs(false, 0, true, $SQLemailstatuscondition);
            $iAllTokensCount=count($aAllTokens);
            unset($aAllTokens);
            if (empty($aResultTokens))
                return array('status' => 'Error: No candidate tokens');

            foreach($aResultTokens as $key=>$oToken)
            {
                //pattern taken from php_filter_validate_email PHP_5_4/ext/filter/logical_filters.c
                $pattern = '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD';

                //if(!filter_var($emailaddress, FILTER_VALIDATE_EMAIL))
                if (preg_match($pattern, $oToken['email']) !== 1)
                    unset($aResultTokens[$key]);
            }

            if (empty($aResultTokens))
                return array('status' => 'Error: No candidate tokens');
            $aResult = emailTokens($iSurveyID,$aResultTokens,'invite');
            $iLeft = $iAllTokensCount - count($aResultTokens);
            $aResult['status'] =$iLeft. " left to send";

            return $aResult;
        }
        else
            return array('status' => 'No permission');
    }


    /**
    * RPC Routine to send reminder for participants in a survey
    * Returns array of results of sending
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID ID of the survey that participants belong
    * @param int $iMinDaysBetween Optional parameter days from last reminder
    * @param int $iMaxReminders Optional parameter Maximum reminders count
    * @return array Result of the action
    */
    public function remind_participants($sSessionKey, $iSurveyID, $iMinDaysBetween=null, $iMaxReminders=null )
    {
        Yii::app()->loadHelper('admin/token');
        if (!$this->_checkSessionKey($sSessionKey))
            return array('status' => 'Invalid session key');

        $oSurvey = Survey::model()->findByPk($iSurveyID);
        if (!isset($oSurvey))
            return array('status' => 'Error: Invalid survey ID');

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'tokens', 'update'))
        {
            $timeadjust = Yii::app()->getConfig("timeadjust");

            if(!tableExists("{{tokens_$iSurveyID}}"))
                return array('status' => 'Error: No token table');

            if (getEmailFormat($iSurveyID) == 'html')
                $bHtml = true;
            else
                $bHtml = false;

            $SQLemailstatuscondition = "emailstatus = 'OK'";
            $SQLremindercountcondition = '';
            $SQLreminderdelaycondition = '';
            $iMaxEmails = (int)Yii::app()->getConfig("maxemails");

            if(!is_null($iMinDaysBetween))
            {
                $compareddate = dateShift(date("Y-m-d H:i:s", time() - 86400 * $iMinDaysBetween), "Y-m-d H:i", $timeadjust);
                $SQLreminderdelaycondition = " ((remindersent = 'N' AND sent < '" . $compareddate . "')  OR  (remindersent < '" . $compareddate . "'))";
            }

            if(!is_null($iMaxReminders))
                $SQLremindercountcondition = "remindercount < " . $iMaxReminders;

            $oTokens = TokenDynamic::model($iSurveyID);
            $aAllTokens = $oTokens->findUninvitedIDs(false, 0, false, $SQLemailstatuscondition, $SQLremindercountcondition, $SQLreminderdelaycondition);
            $iAllTokensCount=count($aAllTokens);
            unset($aAllTokens); // save some memory before the next query

            $aResultTokens = $oTokens->findUninvited(false, $iMaxEmails, false, $SQLemailstatuscondition, $SQLremindercountcondition, $SQLreminderdelaycondition);

            if (empty($aResultTokens))
                return array('status' => 'Error: No candidate tokens');

            $aResult = emailTokens($iSurveyID, $aResultTokens, 'remind');

            $iLeft = $iAllTokensCount - count($aResultTokens);
            $aResult['status'] =$iLeft. " left to send";
            return $aResult;
        }
        else
            return array('status' => 'No permission');

    }


    /* Response specific functions */


    /**
    * RPC Routine to add a response to the survey responses collection.
    * Returns the id of the inserted survey response
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey to insert responses
    * @param struct $aResponseData The actual response
    * @return int The response ID
    */
    public function add_response($sSessionKey, $iSurveyID, $aResponseData)
    {
        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');
        $oSurvey=Survey::model()->findByPk($iSurveyID);
        if (is_null($oSurvey))
        {
            return array('status' => 'Error: Invalid survey ID');
        }

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'create'))
        {
            if (!Yii::app()->db->schema->getTable('{{survey_' . $iSurveyID . '}}'))
                return array('status' => 'No survey response table');

            //set required values if not set

            // @todo: Some of this is part of the validation and should be done in the model instead
            if (array_key_exists('submitdate', $aResponseData) && empty($aResponseData['submitdate']))
                unset($aResponseData['submitdate']);
            else if (!isset($aResponseData['submitdate']))
                $aResponseData['submitdate'] = date("Y-m-d H:i:s");
                if (!isset($aResponseData['startlanguage']))
                $aResponseData['startlanguage'] = getBaseLanguageFromSurveyID($iSurveyID);

            if ($oSurvey->datestamp=='Y')
            {
                if (array_key_exists('datestamp', $aResponseData) && empty($aResponseData['datestamp']))
                    unset($aResponseData['datestamp']);
                else if (!isset($aResponseData['datestamp']))
                    $aResponseData['datestamp'] = date("Y-m-d H:i:s");
                    if (array_key_exists('startdate', $aResponseData) && empty($aResponseData['startdate']))
                    unset($aResponseData['startdate']);
                else if (!isset($aResponseData['startdate']))
                    $aResponseData['startdate'] = date("Y-m-d H:i:s");
            }

            SurveyDynamic::sid($iSurveyID);
            $survey_dynamic = new SurveyDynamic;
            $aBasicDestinationFields=$survey_dynamic->tableSchema->columnNames;
            $aResponseData=array_intersect_key($aResponseData, array_flip($aBasicDestinationFields));
            $result_id = $survey_dynamic->insertRecords($aResponseData);

            if ($result_id)
                return $result_id;
            else
                return array('status' => 'Unable to add response');
        }
        else
            return array('status' => 'No permission');

    }

    /**
    * RPC Routine to update a response in a given survey.
    * Routine supports only single response updates.
    * Response to update will be identified either by the response id, or the token if response id is missing.
    * Routine is only applicable for active surveys with alloweditaftercompletion = Y.
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey to update response
    * @param struct $aResponseData The actual response
    * @return mixed TRUE(bool) on success. errormessage on error
    */
    public function update_response($sSessionKey, $iSurveyID, $aResponseData)
    {
        if (!$this->_checkSessionKey($sSessionKey)) return 'Invalid session key';
        $oSurvey=Survey::model()->findByPk($iSurveyID);
        if (is_null($oSurvey))
        {
            return 'Error: Invalid survey ID';
        }
        if ($oSurvey->getAttribute('active') !== 'Y') {
            return 'Error: Survey is not active.';
        }

        if ($oSurvey->getAttribute('alloweditaftercompletion') !== 'Y') {
            return 'Error: Survey does not allow edit after completion.';
        }

        if (Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'update'))
        {
            if (!Yii::app()->db->schema->getTable('{{survey_' . $iSurveyID . '}}'))
                return 'Error: No survey response table';

            if (
            !isset($aResponseData['id'])
            && ! isset($aResponseData['token'])
            ) {
                return 'Error: Missing response identifier (id|token).';
            }

            SurveyDynamic::sid($iSurveyID);
            $oSurveyDynamic = new SurveyDynamic;

            if (isset($aResponseData['id'])) {
                $aResponses = $oSurveyDynamic->findAllByPk($aResponseData['id']);
            } else {
                $aResponses = $oSurveyDynamic->findAllByAttributes(array('token' => $aResponseData['token']));
            }

            if(empty($aResponses))
                return 'Error: No matching Response.';
            if(count($aResponses) > 1)
                return 'Error: More then one matching response, updateing multiple responses at once is not supported.';

            $aBasicDestinationFields=$oSurveyDynamic->tableSchema->columnNames;
            $aInvalidFields= array_diff_key($aResponseData, array_flip($aBasicDestinationFields));
            if(count($aInvalidFields) > 0)
                return 'Error: Invalid Column names supplied: ' . implode(', ', array_keys($aInvalidFields));

            unset($aResponseData['token']);

            foreach ($aResponseData as $sAtributeName => $value) {
                $aResponses[0]->setAttribute($sAtributeName, $value);
            }

            $bResult = $aResponses[0]->save(true);

            if ($bResult) {
                return $bResult;
            } else {
                return 'Unable to edit response';
            }
        } else {
            return 'No permission';
        }
    }

    /**
    * RPC Routine to export responses.
    * Returns the requested file as base64 encoded string
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey
    * @param string $sDocumentType pdf,csv,xls,doc,json
    * @param string $sLanguageCode The language to be used
    * @param string $sCompletionStatus Optional 'complete','incomplete' or 'all' - defaults to 'all'
    * @param string $sHeadingType 'code','full' or 'abbreviated' Optional defaults to 'code'
    * @param string $sResponseType 'short' or 'long' Optional defaults to 'short'
    * @param integer $iFromResponseID Optional
    * @param integer $iToResponseID Optional
    * @param array $aFields Optional Selected fields
    * @return array|string On success: Requested file as base 64-encoded string. On failure array with error information
    * */
    public function export_responses($sSessionKey, $iSurveyID, $sDocumentType, $sLanguageCode=null, $sCompletionStatus='all', $sHeadingType='code', $sResponseType='short', $iFromResponseID=null, $iToResponseID=null, $aFields=null)
    {
        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');
        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'export')) return array('status' => 'No permission');
        Yii::app()->loadHelper('admin/exportresults');
        if (!tableExists('{{survey_' . $iSurveyID . '}}')) return array('status' => 'No Data, survey table does not exist.');
        if(!($maxId = SurveyDynamic::model($iSurveyID)->getMaxId())) return array('status' => 'No Data, could not get max id.');
        if(!empty($sLanguageCode) && !in_array($sLanguageCode,Survey::model()->findByPk($iSurveyID)->getAllLanguages()) ) return array('status' => 'Language code not found for this survey.');

        if (empty($sLanguageCode)) $sLanguageCode=getBaseLanguageFromSurveyID($iSurveyID);
        if (is_null($aFields)) $aFields=array_keys(createFieldMap($iSurveyID,'full',true,false,$sLanguageCode));
        if($sDocumentType=='xls'){
            // Cut down to the first 255 fields
            $aFields=array_slice($aFields,0,255);
        }
        $oFormattingOptions=new FormattingOptions();

        if($iFromResponseID !=null)
            $oFormattingOptions->responseMinRecord=$iFromResponseID;
        else
            $oFormattingOptions->responseMinRecord=1;

        if($iToResponseID !=null)
            $oFormattingOptions->responseMaxRecord=$iToResponseID;
        else
            $oFormattingOptions->responseMaxRecord = $maxId;

        $oFormattingOptions->selectedColumns=$aFields;
        $oFormattingOptions->responseCompletionState=$sCompletionStatus;
        $oFormattingOptions->headingFormat=$sHeadingType;
        $oFormattingOptions->answerFormat=$sResponseType;
        $oFormattingOptions->output='file';

        $oExport=new ExportSurveyResultsService();
        $sTempFile=$oExport->exportSurvey($iSurveyID,$sLanguageCode, $sDocumentType,$oFormattingOptions, '');
        return new BigFile($sTempFile, true, 'base64');
    }

    /**
    * RPC Routine to export token response in a survey.
    * Returns the requested file as base64 encoded string
    *
    * @access public
    * @param string $sSessionKey Auth credentials
    * @param int $iSurveyID Id of the Survey
    * @param string $sDocumentType pdf,csv,xls,doc,json
    * @param string $sToken The token for which responses needed
    * @param string $sLanguageCode The language to be used
    * @param string $sCompletionStatus Optional 'complete','incomplete' or 'all' - defaults to 'all'
    * @param string $sHeadingType 'code','full' or 'abbreviated' Optional defaults to 'code'
    * @param string $sResponseType 'short' or 'long' Optional defaults to 'short'
    * @param array $aFields Optional Selected fields
    * @return array|string On success: Requested file as base 64-encoded string. On failure array with error information
    *
    */
    public function export_responses_by_token($sSessionKey, $iSurveyID, $sDocumentType, $sToken, $sLanguageCode=null, $sCompletionStatus='all', $sHeadingType='code', $sResponseType='short', $aFields=null)
    {
        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');
        Yii::app()->loadHelper('admin/exportresults');
        if (!tableExists('{{survey_' . $iSurveyID . '}}')) return array('status' => 'No Data, survey table does not exist.');
        if(!($maxId = SurveyDynamic::model($iSurveyID)->getMaxId())) return array('status' => 'No Data, could not get max id.');
        if(!empty($sLanguageCode) && !in_array($sLanguageCode,Survey::model()->findByPk($iSurveyID)->getAllLanguages()) ) return array('status' => 'Language code not found for this survey.');

        if (!SurveyDynamic::model($iSurveyID)->findByAttributes(array('token' => $sToken))) return array('status' => 'No Response found for Token');
        if (!Permission::model()->hasSurveyPermission($iSurveyID, 'responses', 'export')) return array('status' => 'No permission');
        if (empty($sLanguageCode)) $sLanguageCode=getBaseLanguageFromSurveyID($iSurveyID);
        if (is_null($aFields)) $aFields=array_keys(createFieldMap($iSurveyID,'full',true,false,$sLanguageCode));
        if($sDocumentType=='xls'){
            // Cut down to the first 255 fields
            $aFields=array_slice($aFields,0,255);
        }
        $oFormattingOptions=new FormattingOptions();
        $oFormattingOptions->responseMinRecord=1;
        $oFormattingOptions->responseMaxRecord = $maxId;

        $oFormattingOptions->selectedColumns=$aFields;
        $oFormattingOptions->responseCompletionState=$sCompletionStatus;
        $oFormattingOptions->headingFormat=$sHeadingType;
        $oFormattingOptions->answerFormat=$sResponseType;
        $oFormattingOptions->output='file';

        $oExport=new ExportSurveyResultsService();

        $sTableName = Yii::app()->db->tablePrefix.'survey_'.$iSurveyID;

        $sTempFile=$oExport->exportSurvey($iSurveyID,$sLanguageCode, $sDocumentType,$oFormattingOptions, "$sTableName.token='$sToken'");
        return new BigFile($sTempFile, true, 'base64');

    }



    /**
    * Tries to login with username and password
    *
    * @access protected
    * @param string $sUsername The username
    * @param mixed $sPassword The Password
    * @return bool
    */
    protected function _doLogin($sUsername, $sPassword)
    {
        $identity = new UserIdentity(sanitize_user($sUsername), $sPassword);

        if (!$identity->authenticate())
        {
            return false;
        }
        else
            return true;
    }

    /**
    * Fills the session with necessary user info on the fly
    *
    * @access protected
    * @param string $username The username
    * @return bool
    */
    protected function _jumpStartSession($username)
    {
        $aUserData = User::model()->findByAttributes(array('users_name' => $username))->attributes;

        $session = array(
            'loginID' => intval($aUserData['uid']),
            'user' => $aUserData['users_name'],
            'full_name' => $aUserData['full_name'],
            'htmleditormode' => $aUserData['htmleditormode'],
            'templateeditormode' => $aUserData['templateeditormode'],
            'questionselectormode' => $aUserData['questionselectormode'],
            'dateformat' => $aUserData['dateformat'],
            'adminlang' => 'en'
        );
        foreach ($session as $k => $v)
            Yii::app()->session[$k] = $v;
        Yii::app()->user->setId($aUserData['uid']);

        $this->controller->_GetSessionUserRights($aUserData['uid']);
        return true;
    }

    /**
    * This function checks if the XML-RPC session key is valid. If yes returns true, otherwise false and sends an error message with error code 1
    *
    * @access protected
    * @param string $sSessionKey Auth credentials
    * @return bool
    */
    protected function _checkSessionKey($sSessionKey)
    {
        $criteria = new CDbCriteria;
        $criteria->condition = 'expire < ' . time();
        Session::model()->deleteAll($criteria);
        $oResult = Session::model()->findByPk($sSessionKey);

        if (is_null($oResult))
            return false;
        else
        {
            $this->_jumpStartSession($oResult->data);
            return true;
        }
    }


    /**
     * This function import a participant to the LimeSurvey cpd. It stores attributes as well, if they are registered before within ui
     *
     * Call the function with $response = $myJSONRPCClient->cpd_importParticipants( $sessionKey, $aParticipants);
     *
     * @param int $sSessionKey
     * @param array $aParticipants
     * [[0] => ["email"=>"dummy-02222@limesurvey.com","firstname"=>"max","lastname"=>"mustermann"]]
     * @return array with status
     */
    public function cpd_importParticipants($sSessionKey, $aParticipants)
    {

        if (!$this->_checkSessionKey($sSessionKey)) return array('status' => 'Invalid session key');

        $aResponse = array();
        $aAttributeData = array();
        $aAttributes = array();
        $aDefaultFields = array('participant_id', 'firstname', 'lastname', 'email', 'language', 'blacklisted');
        $bIsValidEmail = true;
        $bDoImport = true;
        $sMandatory = 0;
        $sAttribCount = 0;
        $aResponse = array();
        $aResponse['ImportCount'] = 0;

        // get all attributes for mapping
        $oFindCriteria = new CDbCriteria();
        $oFindCriteria->offset = -1;
        $oFindCriteria->limit = -1;
        $aAttributeRecords = ParticipantAttributeName::model()->with('participant_attribute_names_lang')->findAll($oFindCriteria);

        foreach ($aParticipants as $sKey => $aParticipantData) {

            $aData = array(
                'firstname' => $aParticipantData['firstname'],
                'lastname' => $aParticipantData['lastname'],
                'email' => $aParticipantData['email'],
                'owner_uid' => Yii::app()->session['loginID'], // ToDo is this working?
            );

            //Check for duplicate participants
            $arRecordExists = Participant::model()->exists(
                'firstname = :firstname AND lastname = :lastname AND email = :email AND owner_uid = :owner_uid',
                array(
                    ':firstname' => $aData['firstname'],
                    ':lastname' => $aData['lastname'],
                    ':email' => $aData['email'],
                    ':owner_uid' => $aData['owner_uid'],
                ));

            // check if email is valid
            $this->_checkEmailFormat($aData['email']);

            if ($bIsValidEmail == true) {

                //First, process the known fields
                if (!isset($aData['participant_id']) || $aData['participant_id'] == "")
                {
                  //  $arParticipantModel = new Participant();
                    $aData['participant_id'] = Participant::gen_uuid();
                }
                if (isset($aData['emailstatus']) && trim($aData['emailstatus'] == ''))
                {
                    unset($aData['emailstatus']);
                }
                if (!isset($aData['language']) || $aData['language'] == "")
                {
                    $aData['language'] = "en";
                }
                if (!isset($aData['blacklisted']) || $aData['blacklisted'] == "")
                {
                    $aData['blacklisted'] = "N";
                }
                $aData['owner_uid'] = Yii::app()->session['loginID'];
                if (isset($aData['validfrom']) && trim($aData['validfrom'] == ''))
                {
                    unset($aData['validfrom']);
                }
                if (isset($aData['validuntil']) && trim($aData['validuntil'] == ''))
                {
                    unset($aData['validuntil']);
                }

                if (!empty($aData['email']))
                {
                    //The mandatory fields of email, firstname and lastname
                    $sMandatory++;
                    $bDoImport = false;
                }

                // Write to database if record not exists
                if (empty($arRecordExists))
                {
                    // save participant to database
                    Participant::model()->insertParticipantCSV($aData);

                    // Prepare atrribute values to store in db . Iterate through our values
                    foreach ($aParticipantData as $sLabel => $sAttributeValue) {
                        // skip default fields
                        if (!in_array($sLabel, $aDefaultFields)) {
                            foreach ($aAttributeRecords as $sKey => $arValue) {
                                $aAttributes = $arValue->getAttributes();
                                if ($aAttributes['defaultname'] == $sLabel)
                                {
                                    $aAttributeData['participant_id'] = $aData['participant_id'];
                                    $aAttributeData['attribute_id'] = $aAttributes['attribute_id'];
                                    $aAttributeData['value'] = $sAttributeValue;
                                    $sAttribCount++;
                                    // save attributes values for participant
                                    ParticipantAttributeName::model()->saveParticipantAttributeValue($aAttributeData);
                                }
                            }
                        }
                    }
                    $aResponse['ImportCount']++;
                }
            }
        }
        return $aResponse;
    }

    /**
     * This function checks the email, if it's in a valid format
     * @param $sEmail
     * @return bool
     */
    protected function _checkEmailFormat($sEmail)
    {
        if ($sEmail != '')
        {
            $aEmailAddresses = explode(';', $sEmail);
            // Ignore additional email addresses
            $sEmailaddress = $aEmailAddresses[0];
            if (!validateEmailAddress($sEmailaddress))
            {
                return false;
            }
            return true;
        }
        return false;
    }
    
    /**
     * RPC routine to create a new user
     * @param string $sSessionKey
     * @param array $aAttributes
     * @return array Array with status, uid, password
     */
    public function add_user($sSessionKey, $aAttributes, $sendMail = true, $sendPassword = true)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if( !Permission::model()->hasGlobalPermission('users','create') ) {
            return array('status' => 'No permission');
        }

        // Validate UserName
        $new_user = flattenText($aAttributes['username'],false,true,'UTF-8',true);
        if (empty($new_user)) {
            return array('status' => 'Error: A username was not supplied or the username is invalid.');
        }
        if (User::model()->find("users_name=:users_name",array(':users_name'=>$new_user))) {
            return array('status' => 'Error: The username already exists.');
        }

        /**
         * Create User
         * Plugin Event
         * https://github.com/LimeSurvey/LimeSurvey/blob/83eb591e7956bdc4d6d947be4543688296af0b95/application/core/plugins/Authdb/Authdb.php
         */

        // Set post manullay
        $_POST['user_type'] = 'DB';
        $_POST['new_user'] = $new_user;
        $_POST['new_email'] = $aAttributes['email'];
        $_POST['new_full_name'] = $aAttributes['full_name'];

        // Call Event to create the User
        $event = new PluginEvent('createNewUser');
        $event->set('errorCode', AuthPluginBase::ERROR_NOT_ADDED);
        $event->set('errorMessageTitle', "Failed to add user");
        $event->set('errorMessageBody', "Plugin is not active");
        App()->getPluginManager()->dispatchEvent($event);

        if ($event->get('errorCode') != AuthPluginBase::ERROR_NONE)
        {
            return array('status' => "Error: " . $event->get('errorMessageTitle'), 'message' => $event->get('errorMessageBody') );
        }

        // Get values back
        $iNewUID = $event->get('newUserID');
        $new_pass = $event->get('newPassword');
        $new_email = $event->get('newEmail');
        $new_full_name = $event->get('newFullName');

        // add default template to template rights for user
        Permission::model()->insertSomeRecords(array('uid' => $iNewUID, 'permission' => Yii::app()->getConfig("defaulttemplate"), 'entity'=>'template', 'read_p' => 1, 'entity_id'=>0));

        /**
         * Send Mail
         */
        $statusMail = 'unsent';
        if ($sendMail)
        {
            $body = sprintf(gT("Hello %s,"), $new_full_name) . "<br /><br />\n";
            $body .= sprintf(gT("This is an automated email to notify that a user has been created for you on the site '%s'."), Yii::app()->getConfig("sitename")) . "<br /><br />\n";
            $body .= gT("You can use now the following credentials to log into the site:") . "<br />\n";
            $body .= gT("Username") . ": " . htmlspecialchars($new_user) . "<br />\n";
            // authent is not delegated to web server or LDAP server
            if (Yii::app()->getConfig("auth_webserver") === false  && Permission::model()->hasGlobalPermission('auth_db','read',$iNewUID)) {
                // send password (if authorized by config)
                if ($sendPassword || Yii::app()->getConfig("display_user_password_in_email") === true) {
                    $body .= gT("Password") . ": " . $new_pass . "<br />\n";
                }
                else
                {
                    $body .= gT("Password") . ": " . gT("Please contact your LimeSurvey administrator for your password.") . "<br />\n";
                }
            }

            $adminUrl = Yii::app()->createAbsoluteUrl("/admin");
            $body .= "<a href='" . $adminUrl . "'>" . gT("Click here to log in.") . "</a><br /><br />\n";
            $body .= sprintf(gT('If you have any questions regarding this mail please do not hesitate to contact the site administrator at %s. Thank you!'), Yii::app()->getConfig("siteadminemail")) . "<br />\n";

            $subject = sprintf(gT("User registration at '%s'", "unescaped"), Yii::app()->getConfig("sitename"));
            $to = $new_user . " <$new_email>";
            $from = Yii::app()->getConfig("siteadminname") . " <" . Yii::app()->getConfig("siteadminemail") . ">";
            $extra = '';
            $classMsg = '';
            if (SendEmailMessage($body, $subject, $to, $from, Yii::app()->getConfig("sitename"), true, Yii::app()->getConfig("siteadminbounce"))) {
                $statusMail = 'success';
            }
            else
            {
                $statusMail = 'failed';
            }
        }

        return array('status' => 'Success', 'uid' => $iNewUID, 'password' => $new_pass, 'statusMail' => $statusMail);
    }

    /**
     * RPC routine to delete a user
     * @param  string  $sSessionKey
     * @param  int  $uid
     * @param  bool $transfer_to_admin
     * @param  int $uid_transfer_surveys_to
     * @return array
     */
    public function delete_user($sSessionKey, $uid, $transfer_to_admin = TRUE , $uid_transfer_surveys_to = 0)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if (!Permission::model()->hasGlobalPermission('superadmin','read') && !Permission::model()->hasGlobalPermission('users','delete')) {
            return array('status' => 'No permission');
        }

        /**
         * Process user to delete
         */

        // Get super admin
        $oInitialAdmin = User::model()->findByAttributes(array('parent_id' => 0));

        // Initilize user
        $postuserid = sanitize_int($uid);

        // Validate user
        if (!$postuserid)
        {
            return array('status' => 'Error: User was not supplied.');
        }

        // CAN'T DELETE ORIGINAL SUPERADMIN (with findByAttributes : found the first user without parent)
        if ($oInitialAdmin && $oInitialAdmin->uid == $postuserid) // it's the original superadmin !!!
        {
            return array('status' => 'Error: Initial Superadmin cannot be deleted!');
        }

        // Check logged in user owns the user to delete
        $sresultcount = 0; // 1 if I am parent of $postuserid
        if (!Permission::model()->hasGlobalPermission('superadmin','read'))
        {
            $sresult = User::model()->findAllByAttributes(array('parent_id' => $postuserid, 'parent_id' => Yii::app()->session['loginID']));
            $sresultcount = count($sresult);
        }

        if (!(Permission::model()->hasGlobalPermission('superadmin','read') || $sresultcount > 0 || $postuserid == Yii::app()->session['loginID']))
        {
            return array('status' => 'Error: You do not have sufficient rights.');
        }

        /**
         * Review surveys owned and who to transfer to
         */

        $transfer_surveys_to = $uid_transfer_surveys_to;

        // If no user set to transfer, try to guess
        if (empty($transfer_surveys_to))
        {
            // If only 2 users, transfer to the other
            $ownerUser = User::model()->findAll();
            if (count($ownerUser) == 2) {
                foreach ($ownerUser as &$user)
                {
                    if ($postuserid != $user['uid'])
                        $transfer_surveys_to = $user['uid'];
                }
            }
        }

        // If no user set to transfer and transfer to admin, set to transfer to admin
        if (empty($transfer_surveys_to) && !empty($transfer_to_admin))
        {
            $transfer_surveys_to = $oInitialAdmin->uid;
        }

        // Get owned surveys
        $ownerUser = Survey::model()->findAllByAttributes(array('owner_id' => $postuserid));

        // If no user set to transfer and it owns surveys, error
        if (empty($transfer_surveys_to) && count($ownerUser) > 0)
        {
            return array('status' => 'Error: No user was set to whom transfer the surveys.');
        }

        // Delete user and transfer surveys
        return $this->deleteFinalUser($postuserid, $ownerUser, $transfer_surveys_to);

        // If got to here, error.
        return array('status' => 'Error: Unexpected End');
    }

    /**
     * Aux function for delete user RPC routine
     * @param  [type] $postuserid          [description]
     * @param  [type] $result              [description]
     * @param  [type] $transfer_surveys_to [description]
     * @return [type]                      [description]
     */
    private function deleteFinalUser($postuserid, $result, $transfer_surveys_to)
    {
        // Never delete initial admin (with findByAttributes : found the first user without parent)
        $oInitialAdmin = User::model()->findByAttributes(array('parent_id' => 0));
        if ($oInitialAdmin && $oInitialAdmin->uid == $postuserid) // it's the original superadmin !!!
        {
            return array('status' => 'Error: Initial Superadmin cannot be deleted!');
        }

        // Transfer surveys
        if ($transfer_surveys_to > 0) {
            $iSurveysTransferred = Survey::model()->updateAll(array('owner_id' => $transfer_surveys_to), 'owner_id='.$postuserid);
        }

        // Find user
        $sresult = User::model()->findByAttributes(array('uid' => $postuserid));
        $fields = $sresult;

        // Reassign child users to theire grandpa (the parent of the user to be deleted)
        if (isset($fields['parent_id'])) {
            $uresult = User::model()->updateAll(array('parent_id' => $fields['parent_id']), 'parent_id='.$postuserid);
        }

        //DELETE USER FROM TABLE
        $dresult = User::model()->deleteUser($postuserid);

        // Delete user rights
        $dresult = Permission::model()->deleteAllByAttributes(array('uid' => $postuserid));

        // User deleted himself?
        if ($postuserid == Yii::app()->session['loginID'])
        {
            session_destroy();    // user deleted himself
        }

        return array('status' => 'Success');
    }

    /**
     * RPC routine to edit a  user
     * @param  string $sSessionKey
     * @param  int $uid
     * @param  array $aAttributes: email, full_name, password
     * @return array
     */
    public function edit_user($sSessionKey, $uid, $aAttributes)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if( !Permission::model()->hasGlobalPermission('users','update') ) {
            return array('status' => 'No permission');
        }

        // Initialize data
        $postuserid = sanitize_int($uid);
        $email = empty($aAttributes['email']) ? NULL : flattenText($aAttributes['email'],false,true,'UTF-8',true) ;
        $full_name = empty($aAttributes['full_name']) ? NULL : flattenText($aAttributes['full_name'],false,true,'UTF-8',true) ;
        $sPassword = empty($aAttributes['password']) ? NULL : $aAttributes['password'] ;

        // Get user
        $sresult = User::model()->findAllByAttributes(array('uid' => $postuserid, 'parent_id' => Yii::app()->session['loginID']));
        $sresultcount = count($sresult);

        // Check rights
        if (! ((Permission::model()->hasGlobalPermission('superadmin','read') || $postuserid == Yii::app()->session['loginID'] ||
        ($sresultcount > 0 && Permission::model()->hasGlobalPermission('users','update'))) && !(Yii::app()->getConfig("demoMode") == true && $postuserid == 1)
        ) )
        {
            return array('status' => 'Error: You do not have sufficient rights');
        }

        // Process Password
        if ($sPassword == null) $sPassword = '';

        // Validate email
        if (!empty($email) && !validateEmailAddress($email)) {
            return array('status' => 'Error: Email address is not valid');
        }

        /**
         * Update User
         */

        $oRecord = User::model()->findByPk($postuserid);
        if (!empty($email)) $oRecord->email= $this->escape($email);
        if (!empty($full_name)) $oRecord->full_name= $this->escape($full_name);
        if (!empty($sPassword))
        {
            $oRecord->password= hash('sha256', $sPassword);
        }

        $uresult = $oRecord->save();    // store result of save in uresult
        if (!$uresult)
        {
            return array('status' => 'Error: Update failed');
        }

        return array('status' => 'Success');
    }

    /**
     * RPC routine to set user permissions
     * @param string $sSessionKey
     * @param int $uid
     * @param array $aNewPermissions
     *
     *  Example:
     * {"method":"set_user_permissions","params":{"sSessionKey":"xxx", "uid":"2", "aNewPermissions":{"perm_surveys_export":false}},"id":1}
     */
    public function set_user_permissions($sSessionKey, $uid, $aNewPermissions=array())
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if( !Permission::model()->hasGlobalPermission('users','create') ) {
            return array('status' => 'No permission');
        }

        // Initialize data
        $iUserID= sanitize_int($uid);

        /**
         * Validate user
         */

        // A user may not modify his own permissions
        if (Yii::app()->session['loginID']==$iUserID) {
            return array('status' => 'Error: You are not allowed to edit your own user permissions.');
        }

        // Can not update initial superadmin permissions (with findByAttributes : found the first user without parent)
        $oInitialAdmin = User::model()->findByAttributes(array('parent_id' => 0));
        if ($oInitialAdmin && $oInitialAdmin->uid == $iUserID) // it's the original superadmin !!!
        {
            return array('status' => 'Error: Initial Superadmin permissions cannot be updated.');
        }

        /**
         * Compose permissions
         */
        if ($sEntityName == 'global')
        {
            $aBaseUserPermissions = Permission::model()->getGlobalBasePermissions();
        }
        else
        {
            $aBaseUserPermissions = Permission::model()->getSurveyBasePermissions();
        }

        $aPermissions=array();
        foreach ($aBaseUserPermissions as $sPermissionKey=>$aCRUDPermissions)
        {
            foreach ($aCRUDPermissions as $sCRUDKey=>$CRUDValue)
            {
                if (!in_array($sCRUDKey,array('create','read','update','delete','import','export'))) continue;

                if ($CRUDValue)
                {
                    if(!empty($aNewPermissions["perm_{$sPermissionKey}_{$sCRUDKey}"])){
                        $aPermissions[$sPermissionKey][$sCRUDKey]=1;
                    }
                    else
                    {
                        $aPermissions[$sPermissionKey][$sCRUDKey]=0;
                    }
                }
            }
        }

        /**
         * Update permissions
         */
        if (!(Permission::model()->setPermissions($iUserID, 0, 'global', $aPermissions)))
        {
            return array('status' => 'Error: Operation failed.');
        }

        return array('status' => 'Success');
    }

    /**
     * RPC routine to list user permissions
     * @param  string $sSessionKey
     * @param  int $uid
     * @return array
     */
    public function list_user_permissions($sSessionKey, $uid)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Initialize data
        $iUserID= sanitize_int($uid);

        /**
         * Get user
         */

        if ($iUserID) {//Never update 1st admin
            if(Permission::model()->hasGlobalPermission('superadmin','read'))
                $oUser = User::model()->findByAttributes(array('uid' => $iUserID));
            else
                $oUser = User::model()->findByAttributes(array('uid' => $iUserID, 'parent_id' => Yii::app()->session['loginID']));
        }

        /**
         * Check Permissions
         */
        if (!($oUser && (
                Permission::model()->hasGlobalPermission('superadmin','read')
                || Permission::model()->hasGlobalPermission('users','update')
                &&  Yii::app()->session['loginID'] != $iUserID
                )
            ))
        {
            return array('status' => 'Error: You do not have sufficient rights');
        }

        /**
         * Get base permissions
         */

        $aBasePermissions=Permission::model()->getGlobalBasePermissions();
        if (!Permission::model()->hasGlobalPermission('superadmin','read', $iUserID)) // if not superadmin filter the available permissions as no admin may give more permissions than he owns
        {
            $aFilteredPermissions=array();
            foreach  ($aBasePermissions as $PermissionName=>$aPermission)
            {
                foreach ($aPermission as $sPermissionKey=>&$sPermissionValue)
                {
                    if ($sPermissionKey!='title' && $sPermissionKey!='description' && $sPermissionKey!='img' && !Permission::model()->hasGlobalPermission($PermissionName, $sPermissionKey, $iUserID)) $sPermissionValue=false;
                }
                $aFilteredPermissions[$PermissionName]=$aPermission;
            }
            $aBasePermissions=$aFilteredPermissions;
        }

        // Only the original superadmin (UID 1) may create new superadmins
        if (Yii::app()->session['loginID']!=1)
        {
            unset($aBasePermissions['superadmin']);
        }

        return $aBasePermissions;
    }

    private function escape($str)
    {
        if (is_string($str)) {
            $str = $this->escape_str($str);
        }
        elseif (is_bool($str))
        {
            $str = ($str === true) ? 1 : 0;
        }
        elseif (is_null($str))
        {
            $str = 'NULL';
        }

        return $str;
    }

    private function escape_str($str, $like = FALSE)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val)
            {
                $str[$key] = $this->escape_str($val, $like);
            }

            return $str;
        }

        // Escape single quotes
        $str = str_replace("'", "''", $this->remove_invisible_characters($str));

        return $str;
    }

    private function remove_invisible_characters($str, $url_encoded = TRUE)
    {
        $non_displayables = array();

        // every control character except newline (dec 10)
        // carriage return (dec 13), and horizontal tab (dec 09)

        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/'; // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127

        do
        {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }    
    
    /**
     * RPC routine to list the available user groups
     * @param string $sSessionKey
     * @param int $owner_id Optional Filter by Owner
     * @param int $uid Optional Filter by User in the Group
     * @return array
     */
    function list_usergroups($sSessionKey, $owner_id = FALSE, $uid = FALSE)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if( !Permission::model()->hasGlobalPermission('usergroups','read') ) {
            return array('status' => 'No permission');
        }

        // Initialize Data
        $owner_id = sanitize_int($owner_id);
        $uid = sanitize_int($uid);


        /**
         * Compose the SQL statement
         */

        $sQuery = "SELECT distinct a.ugid, a.name, a.description, a.owner_id FROM {{user_groups}} AS a LEFT JOIN {{user_in_groups}} AS b ON a.ugid = b.ugid WHERE 1=1 ";

        // Filter by own ones
        if (!Permission::model()->hasGlobalPermission('superadmin','read'))
        {
            $sQuery .=" AND (
                b.uid = ".Yii::app()->session['loginID'] . "
                    OR
                a.owner_id = " . Yii::app()->session['loginID'] . "
            )";
        }

        // Filter by Owner
        if (!empty($owner_id))
        {
            $sQuery .=" AND a.owner_id = " . $owner_id;
        }

        // Filter by User in the group
        if (!empty($uid))
        {
            $sQuery .=" AND b.uid = " . $uid;
        }

        $sQuery .=  " ORDER BY name";

        /**
         * Execute the query and get the data
         */

        $sresult = Yii::app()->db->createCommand($sQuery)->query(); //Checked
        if (!$sresult) {
            return array('status' => 'Error: Operation failed');
        }

        foreach ($sresult->readAll() as $row)
        {
            $groupnames[] = $row;
        }

        return $groupnames;
    }

    /**
     * RPC routine to get the details of a user group
     * @param string $sSessionKey
     * @param int $ugid
     * @return array
     */
    public function get_usergroup_properties($sSessionKey, $ugid)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if( !Permission::model()->hasGlobalPermission('usergroups','read') ) {
            return array('status' => 'No permission');
        }

        // Initialize Data
        $ugid = sanitize_int($ugid);
        $owner_id = Yii::app()->session['loginID'];

        if (empty($ugid) || !($ugid > -1)) {
            return array('status' => 'Error: No group selected.');
        }

        /**
         * Get the details
         */
        $aData['ugid'] = $ugid;

        $result = UserGroup::model()->requestViewGroup($ugid, Yii::app()->session["loginID"]);
        if (!$result) {
            return array('status' => 'Error: Group not found.');
        }

        $crow = $result[0];
        $aData["name"] = $crow['name'];
        $aData["owner_id"] = $crow['owner_id'];
        if (!empty($crow['description']))
            $aData["description"] = $crow['description'];
        else
            $aData["description"] = "";

        /**
         * Get the users
         */
        //$this->user_in_groups_model = new User_in_groups;
        $eguquery = "SELECT * FROM {{user_in_groups}} AS a INNER JOIN {{users}} AS b ON a.uid = b.uid WHERE ugid = " . $ugid . " ORDER BY b.users_name";
        $eguresult = dbExecuteAssoc($eguquery);
        $aUserInGroupsResult = $eguresult->readAll();

        $row = 0;
        $userloop = array();
        foreach ($aUserInGroupsResult as $egurow)
        {
            $userloop[$row]["userid"] = $egurow['uid'];
            $userloop[$row]["username"] = $egurow['users_name'];
            $userloop[$row]["email"] = $egurow['email'];

            $row++;
        }
        $aData["users"] = $userloop;

        /**
         * Return
         */
        return ($aData);
    }

    /**
     * RPC routine to create a user group
     * @param string $sSessionKey
     * @param array $aAttributes
     */
    public function add_usergroup($sSessionKey, $aAttributes)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if (!Permission::model()->hasGlobalPermission('usergroups','create')) {
            return array('status' => 'No permission');
        }

        // Initialize Data
        $owner_id = Yii::app()->session['loginID'];
        $db_group_name = flattenText($aAttributes['name'],false,true,'UTF-8',true);
        $db_group_description = $aAttributes['description'];

        if (empty($db_group_name)) {
            return array('status' => 'Error: No group name given.');
        }

        if (strlen($db_group_name) > 21) {
            return array('status' => 'Error: Group name length more than 20 characters');
        }

        if (UserGroup::model()->find("name=:groupName", array(':groupName'=>$db_group_name))) {
            return array('status' => 'Error: Group already exists');
        }


        // Operation
        $ugid = UserGroup::model()->addGroup($db_group_name, $db_group_description);
        if ($ugid <= 0)
        {
            return array('status' => 'Error: Operation failed');
        }

        return (int)$ugid;
    }


    /**
     * RPC routine to edit a user group
     * @param  string $sSessionKey
     * @param  int $ugid
     * @param  array $aAttributes
     * @return array|string|int
     */
    public function edit_usergroup($sSessionKey, $ugid, $aAttributes)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if (!Permission::model()->hasGlobalPermission('usergroups','update')) {
            return array('status' => 'No permission');
        }

        // Initialize Data
        $ugid = sanitize_int($ugid);
        $owner_id = Yii::app()->session['loginID'];

        if (empty($ugid) || !($ugid > -1)) {
            return array('status' => 'Error: No group selected.');
        }

        $db_group_name = flattenText($aAttributes['name'],false,true,'UTF-8',true);
        $db_group_description = $aAttributes['description'];

        if (empty($db_group_name)) {
            return array('status' => 'Error: No group name given.');
        }

        if (strlen($db_group_name) > 21) {
            return array('status' => 'Error: Group name length more than 20 characters');
        }

        // Find User Group
        $result = UserGroup::model()->requestEditGroup($ugid, $owner_id);
        if (!($result->count() > 0))
        {
            return array('status' => 'Error: User Group not found.');
        }

        // Operation
        $result = UserGroup::model()->updateGroup($db_group_name, $db_group_description, $ugid);
        if (!$result)
        {
            return array('status' => 'Error: Operation failed');
        }

        return array('status' => 'Success');
    }

    /**
     * RPC routine to delete a user group
     * @param  string $sSessionKey
     * @param  int $ugid
     * @return array|string|int
     */
    public function delete_usergroup($sSessionKey, $ugid)
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permission
        if (!Permission::model()->hasGlobalPermission('usergroups','delete')) {
            return array('status' => 'No permission');
        }

        // Initialize Data
        $ugid = sanitize_int($ugid);
        $owner_id = Yii::app()->session['loginID'];

        if (empty($ugid) || !($ugid > -1)) {
            return array('status' => 'Error: No group selected.');
        }

        // Find User Group
        $result = UserGroup::model()->requestEditGroup($ugid, $owner_id);
        if (!($result->count() > 0))
        {
            return array('status' => 'Error: User Group not found.');
        }

        // Operation
        $result = UserGroup::model()->deleteGroup($ugid, $owner_id);
        if (!$result)
        {
            return array('status' => 'Error: Operation failed');
        }

        return array('status' => 'Success');
    }

    /**
     * RPC routine to manage a user group: Add / remove users
     * @param  string $sSessionKey
     * @param  int $ugid
     * @param  int $uid
     * @param  string $action
     * @return array|string|int
     */
    public function manage_usergroup($sSessionKey, $ugid, $uid, $action = 'add')
    {
        // Check Session
        if (!$this->_checkSessionKey($sSessionKey))
        {
            return array('status' => 'Invalid Session Key');
        }

        // Check Permissions
        if (!Permission::model()->hasGlobalPermission('usergroups','read') || !in_array($action, array('add', 'remove')))
        {
            return array('status' => 'No permission');
        }

        // Initialize Data
        $ugid = sanitize_int($ugid);
        $owner_id = Yii::app()->session['loginID'];
        $uid = (int) sanitize_int($uid);

        // User Group Validation
        if (empty($ugid) || !($ugid > -1)) {
            return array('status' => 'Error: No group selected.');
        }

        $group = UserGroup::model()->requestEditGroup($ugid, Yii::app()->session['loginID']);
        if (empty($group))
        {
            return array('status' => 'Error: No group found.');
        }

        // User validation
        if (empty($uid) || !($uid > 0)) {
            return array('status' => 'Error: No user selected.');
        }

        if (! ($uid > 0 && User::model()->findByPk($uid) ) )
        {
            return array('status' => 'Error: No user found.');
        }

        // Validate we are not operating on the owner.
        if ($group->owner_id == $uid)
        {
            return array('status' => 'Error: You can not add or remove the group owner from the group.');
        }

        // Find UserGroup-user relation
        $user_in_group = UserInGroup::model()->findByPk(array('ugid' => $ugid, 'uid' => $uid));

        // Operate
        switch ($action)
        {
        case 'add' :
            if (empty($user_in_group) && UserInGroup::model()->insertRecords(array('ugid' => $ugid, 'uid' => $uid)))
            {
                return array('status' => 'Success');
            }
            else
            {
                return array('status' => 'Error: User already exists in the group.');
            }
            break;

        case 'remove' :
            if (!empty($user_in_group) && UserInGroup::model()->deleteByPk(array('ugid' => $ugid, 'uid' => $uid)))
            {
                return array('status' => 'Success');
            }
            else
            {
                return array('status' => 'User does not exist in the group.');
            }
            break;
        }

        return array('status' => 'Error: Unexpeced End.');
    }
    
}
