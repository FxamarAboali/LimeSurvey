<?php
namespace ls\models;
use Yii;
/**
 * @property Question[] $questions
 * @property boolean $bool_usetokens
 * @property-read boolean $isExpired
 * @property SurveyLanguageSetting[] $languagesettings
 * @property QuestionGroup[] $groups
 * @property string $admin
 * @property string $adminEmail
 * @property int $questionCount
 * @property int $groupCount
 */
class Survey extends ActiveRecord
{
    const QNUM_SHOW_NEITHER = 'X';
    const QNUM_SHOW_CODE = 'C';
    const QNUM_SHOW_NUM = 'N';
    const QNUM_SHOW_BOTH = 'B';

    const STATUS_INACTIVE = 'inactive';
    const STATUS_EXPIRED = 'expired';
    const STATUS_ACTIVE = 'active';

    const FORMAT_GROUP = 'G';
    const FORMAT_ALL_IN_ONE = 'A';
    const FORMAT_QUESTION = 'S';

    const INDEX_NONE = 0;
    const INDEX_INCREMENTAL = 1;
    const INDEX_FULL = 2;

    const GINF_SHOW_NEITHER = 'X';
    const GINF_SHOW_NAME = 'N';
    const GINF_SHOW_DESC = 'D';
    const GINF_SHOW_BOTH = 'B';

    private $_fieldMap;


    public function attributeLabels()
    {
        return [

            'localizedTitle' => gT('Title'),
            'bool_usecookie' => gT('Set cookie to prevent repeated participation?'),
            'bool_listpublic' => gT('List survey publicly:'),
            'bool_alloweditaftercompletion' => gT("Allow responses to be edited after completion"),
            'bool_usetokens' => gT('Use tokens'),
            'bool_showwelcome' => gT("Show welcome screen"),
            'startdate' => gT("Start date/time:"),
            'expires' => gT("Expiry date/time:"),
            'usecaptcha' => gT("Use CAPTCHA for"),
            'completedResponseCount' => gT("Completed"),
            'partialResponseCount' => gT("Partial"),
            'responseCount' => gT("Total"),
            'responseRate' => gT('Rate'),
            'sid' => gT('Survey ID')


        ];
    }

    /**
     * Returns the title of the survey. Uses the current language and
     * falls back to the surveys' default language if the current language is not available.
     */
    public function getLocalizedTitle()
    {
        return $this->localizedProperty('title');
    }

    public function getLocalizedDescription()
    {
        return $this->localizedProperty('description');
    }

    public function getLocalizedDateFormat()
    {
        return $this->localizedProperty('dateformat');
    }

    public function getLocalizedNumberFormat()
    {
        return $this->localizedProperty('numberformat');
    }

    public function getLocalizedWelcomeText()
    {
        return $this->localizedProperty('welcometext');
    }

    public function getLocalizedEndText()
    {
        return $this->localizedProperty('endtext');
    }

    public function getLocalizedConfirmationEmail()
    {
        return $this->localizedProperty('email_confirm');
    }

    public function getLocalizedConfirmationEmailSubject()
    {
        return $this->localizedProperty('email_confirm_subj');
    }

    public function getLocalizedAttachments()
    {
        return $this->localizedProperty('attachments', '');
    }

    public function getEmailFormat()
    {
        return $this->bool_htmlemail ? 'html' : 'text';
    }

    /**
     * @return string
     */
    public function getLocalizedEndUrl()
    {
        return $this->localizedProperty('url');
    }

    /**
     * @return string
     */
    public function getLocalizedEndUrlDescription()
    {
        return $this->localizedProperty('urldescription');
    }

    /**
     * Getter to support proper casing of the property:
     * $this->adminEmail instead of $this->adminemail
     * @return string
     */
    public function getAdminEmail()
    {
        return $this->attributes['adminemail'];
    }

    protected function localizedProperty($name, $prefix = 'surveyls_')
    {
        $property = $prefix . $name;
        if (isset($this->languagesettings[App()->language])) {
            return $this->languagesettings[App()->language]->$property;
        } elseif (isset($this->languagesettings[$this->language])) {
            return $this->languagesettings[$this->language]->$property;
        } else {
            return null;
        }
    }

    /**
     * Returns the table's name
     *
     * @access public
     * @return string
     */
    public function tableName()
    {
        return '{{surveys}}';
    }

    /**
     * Returns this model's relations
     *
     * @access public
     * @return array
     */
    public function relations()
    {
        $alias = $this->getTableAlias();

        return [
            'languagesettings' => array(
                self::HAS_MANY,
                SurveyLanguageSetting::class,
                'surveyls_survey_id',
                'index' => 'surveyls_language'
            ),
            'defaultlanguage' => array(
                self::BELONGS_TO,
                SurveyLanguageSetting::class,
                array('language' => 'surveyls_language', 'sid' => 'surveyls_survey_id'),
                'together' => true
            ),
            'owner' => array(self::BELONGS_TO, User::class, '', 'on' => "$alias.owner_id = owner.uid"),
            'groups' => [self::HAS_MANY, QuestionGroup::class, 'sid', 'order' => 'group_order ASC', 'index' => 'id'],
            // @todo Disable this since we should only iterate over questions via groups.
            'questions' => [
                self::HAS_MANY,
                Question::class,
                'sid',
                'on' => "questions.parent_qid = 0",
                'order' => 'question_order ASC'
            ],
            'questionCount' => [self::STAT, Question::class, 'sid', 'condition' => "parent_qid = 0"],
            'groupCount' => [self::STAT, QuestionGroup::class, 'sid'],
            'savedControls' => [self::HAS_MANY, SavedControl::class, 'sid'],
            'surveyLinks' => [self::HAS_MANY, SurveyLink::class, 'survey_id'],
            'quota' => [self::HAS_MANY, Quota::class, 'sid']
        ];
    }

    /**
     * Returns this model's scopes
     *
     * @access public
     * @return array
     */
    public function scopes()
    {
        return array(
            'active' => array('condition' => "active = 'Y'"),
            'open' => array(
                'condition' => '(startdate <= :now1 OR startdate IS NULL) AND (expires >= :now2 OR expires IS NULL)',
                'params' => array(
                    ':now1' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust")),
                    ':now2' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"))
                )
            ),
            'public' => array('condition' => "listpublic = 'Y'"),
            'registration' => array(
                'condition' => "allowregister = 'Y' AND startdate > :now3 AND (expires < :now4 OR expires IS NULL)",
                'params' => array(
                    ':now3' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust")),
                    ':now4' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"))
                )
            )
        );
    }

    /**
     * Returns this model's validation rules
     *
     */
    public function rules()
    {
        return [
            // Defaults
            ['format', \CDefaultValueValidator::class, 'value' => self::FORMAT_ALL_IN_ONE],
            ['admin', \CDefaultValueValidator::class, 'value' => App()->user->getName()],
            ['template', \CDefaultValueValidator::class, 'value' => 'default'],
            ['datecreated', 'default', 'value' => date("Y-m-d")],
            ['startdate', 'default', 'value' => null],
            ['expires', 'default', 'value' => null],


            ['admin', 'required'],
            ['adminemail', 'filter', 'filter' => 'trim'],
            ['bounce_email', 'email', 'allowEmpty' => true],
            ['adminemail', 'filter', 'filter' => 'trim'],
            ['bounce_email', 'email', 'allowEmpty' => true],
            ['active', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['anonymized', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['savetimings', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['datestamp', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['usecookie', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['allowregister', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['allowsave', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['autoredirect', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['allowprev', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['printanswers', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['ipaddr', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['refurl', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['publicstatistics', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['publicgraphs', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['htmlemail', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['sendconfirmation', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['tokenanswerspersistence', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['assessments', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['showxquestions', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['shownoanswer', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['showprogress', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['questionindex', 'in', 'range' => array_keys($this->indexOptions), 'allowEmpty' => false],
            ['nokeyboard', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['alloweditaftercompletion', 'in', 'range' => ['Y', 'N'], 'allowEmpty' => true],
            ['bounceprocessing', 'in', 'range' => ['L', 'N', 'G'], 'allowEmpty' => true],
            ['usecaptcha', 'in', 'range' => ['A', 'B', 'C', 'D', 'X', 'R', 'S', 'N'], 'allowEmpty' => true],
            ['showgroupinfo', 'in', 'range' => ['B', 'N', 'D', 'X'], 'allowEmpty' => true],
            ['showqnumcode', 'in', 'range' => ['B', 'N', 'C', 'X'], 'allowEmpty' => true],
            ['format', 'in', 'range' => array_keys($this->formatOptions), 'allowEmpty' => false],
            [
                'googleanalyticsstyle',
                'numerical',
                'integerOnly' => true,
                'min' => '0',
                'max' => '2',
                'allowEmpty' => true
            ],
            ['autonumber_start', 'numerical', 'integerOnly' => true, 'allowEmpty' => true],
            ['tokenlength', 'numerical', 'integerOnly' => true, 'allowEmpty' => true, 'min' => '5', 'max' => '36'],
            ['bouncetime', 'numerical', 'integerOnly' => true, 'allowEmpty' => true],
            ['navigationdelay', 'numerical', 'integerOnly' => true, 'allowEmpty' => true],
            ['template', \CRangeValidator::class, 'range' => array_keys(Template::getOptions())],
            ['language', 'required', 'on' => 'insert'],
            ['additionalLanguages', 'safe'],
            ['translatedFields', 'safe'],
            ['use_series', 'boolean'],
            ['features', 'safe'],
            ['sid', 'default', 'value' => randomChars(6, '123456789')],
            ['bool_listpublic', 'boolean'],
            ['bool_showwelcome', 'boolean'],



        ];
    }


    /**
     * permission scope for this model
     * Actually only test if user have minimal access to survey (read)
     * @access public
     * @param int $loginID
     * @return CActiveRecord
     */
    public function permission($loginID)
    {
        $loginID = (int)$loginID;
        if (Permission::model()->hasGlobalPermission('surveys', 'read'))// Test global before adding criteria
        {
            return $this;
        }
        $criteria = $this->getDBCriteria();
        $criteria->mergeWith(array(
            'condition' => 'sid IN (SELECT entity_id FROM {{permissions}} WHERE entity = :entity AND  uid = :uid AND permission = :permission AND read_p = 1)
                            OR owner_id = :owner_id',
        ));
        $criteria->params[':uid'] = $loginID;
        $criteria->params[':permission'] = 'survey';
        $criteria->params[':owner_id'] = $loginID;
        $criteria->params[':entity'] = 'survey';

        return $this;
    }

    /**
     * Returns additional languages formatted into a string
     *
     * @access public
     * @return array
     */
    public function getAdditionalLanguages()
    {
        $sLanguages = trim($this->additional_languages);
        if ($sLanguages != '') {
            return explode(' ', $sLanguages);
        } else {
            return array();
        }
    }


    public function setAdditionalLanguages($value)
    {
        if (is_array($value)) {
            $this->additional_languages = implode(' ', $value);
        } else {
            $this->additional_languages = $value;
        }
    }

    /**
     * Returns all languages array
     *
     * @access public
     * @return array
     */
    public function getAllLanguages()
    {
        $sLanguages = self::getAdditionalLanguages();
        $baselang = $this->language;
        array_unshift($sLanguages, $baselang);

        return $sLanguages;
    }

    /**
     * Returns the status for this survey.
     * Possible values are:
     * - inactive
     * - active
     * - expired
     */
    public function getStatus()
    {

        if (!$this->isActive) {
            $result = self::STATUS_INACTIVE;
        } elseif ($this->isExpired) {
            $result = self::STATUS_EXPIRED;
        } else {
            $result = self::STATUS_ACTIVE;
        }

        return $result;
    }

    public function getIsActive()
    {
        return $this->bool_active;
    }

    /**
     * @return array
     */
    public function getHints()
    {
        $result = [];
        if (!$this->isActive && $this->questionCount == 0) {
            $result[] = gT("Survey cannot be activated yet.");
            if ($this->groupCount == 0 && App()->user->checkAccess('surveycontent',
                    ['crud' => 'create', 'entity' => 'survey', 'entity_id' => $this->sid])
            ) {
                $result[] = gT("You need to add question groups");
            }
            if ($this->questionCount == 0 && App()->user->checkAccess('surveycontent',
                    ['crud' => 'create', 'entity' => 'survey', 'entity_id' => $this->sid])
            ) {
                $result[] = gT("You need to add questions");
            }
        }

        if ($this->anonymized != "N") {
            $result[] = gT("Responses to this survey are anonymized.");
        } else {
            $result[] = gT("Responses to this survey are NOT anonymized.");
        }

        if ($this->format == "S") {
            $result[] = gT("It is presented question by question.");
        } elseif ($this->format == "G") {
            $result[] = gT("It is presented group by group.");
        } else {
            $result[] = gT("It is presented on one single page.");
        }

        if ($this->questionindex != self::INDEX_NONE) {
            if ($this->format == self::FORMAT_ALL_IN_ONE) {
                $result[] = gT("No question index will be shown with this format.");
            } elseif ($this->questionindex == self::INDEX_INCREMENTAL) {
                $result[] = gT("A question index will be shown; participants will be able to jump between viewed questions.");
            } elseif ($this->questionindex == self::INDEX_FULL) {
                $result[] = gT("A full question index will be shown; participants will be able to jump between relevant questions.");
            }
        }
        if ($this->bool_datestamp) {
            $result[] = gT("Responses will be date stamped.");
        }
        if ($this->bool_ipaddr) {
            $result[] = gT("IP Addresses will be logged");
        }
        if ($this->bool_refurl) {
            $result[] = gT("Referrer URL will be saved.");
        }
        if ($this->bool_usecookie) {
            $result[] = gT("It uses cookies for access control.");
        }
        if ($this->bool_allowregister) {
            $result[] = gT("If tokens are used, the public may register for this survey");
        }
        if ($this->bool_allowsave && !$this->bool_tokenanswerspersistence) {
            $result[] = gT("Participants can save partially finished surveys") . "<br />\n";
        }
        if ($this->emailnotificationto != '') {
            $result[] = gT("Basic email notification is sent to:") . ' ' . htmlspecialchars($this->emailnotificationto) . "<br />\n";
        }
        if ($this->emailresponseto != '') {
            $result[] = gT("Detailed email notification with response data is sent to:") . ' ' . htmlspecialchars($this->emailresponseto) . "<br />\n";
        }

        return $result;
    }

    /**
     * Returns the additional token attributes
     *
     * @access public
     * @return array
     */
    public function getTokenAttributes()
    {

        $attdescriptiondata = json_decode($this->attributedescriptions, true);
        // checked for invalid data
        if ($attdescriptiondata == null) {
            return array();
        }
        // Catches malformed data
        if ($attdescriptiondata && strpos(key(reset($attdescriptiondata)), 'attribute_') === false) {
            // don't know why yet but this breaks normal tokenAttributes functionning
        } elseif (is_null($attdescriptiondata)) {
            $attdescriptiondata = array();
        }
        // Legacy records support
        if ($attdescriptiondata === false) {
            $attdescriptiondata = explode("\n", $this->attributedescriptions);
            $fields = array();
            $languagesettings = array();
            foreach ($attdescriptiondata as $attdescription) {
                if (trim($attdescription) != '') {
                    $fieldname = substr($attdescription, 0, strpos($attdescription, '='));
                    $desc = substr($attdescription, strpos($attdescription, '=') + 1);
                    $fields[$fieldname] = array(
                        'description' => $desc,
                        'mandatory' => 'N',
                        'show_register' => 'N',
                        'cpdbmap' => ''
                    );
                    $languagesettings[$fieldname] = $desc;
                }
            }
            $ls = SurveyLanguageSetting::model()->findByAttributes(array(
                'surveyls_survey_id' => $this->sid,
                'surveyls_language' => $this->language
            ));
            self::model()->updateByPk($this->sid, array('attributedescriptions' => json_encode($fields)));
            $ls->surveyls_attributecaptions = json_encode($languagesettings);
            $ls->save();
            $attdescriptiondata = $fields;
        }
        $aCompleteData = array();
        foreach ($attdescriptiondata as $sKey => $aValues) {
            if (preg_match("/^attribute_[0-9]$/", $sKey)) {
                if (!is_array($aValues)) {
                    $aValues = array();
                }
                $aCompleteData[$sKey] = array_merge(array(
                    'description' => '',
                    'mandatory' => 'N',
                    'show_register' => 'N',
                    'cpdbmap' => ''
                ), $aValues);
            }
        }

        return $aCompleteData;
    }

    /**
     * Returns true in a token table exists for the given $surveyId
     *
     * @staticvar array $tokens
     * @param int $iSurveyID
     * @return boolean
     */
    public function hasTokens($iSurveyID)
    {
        static $tokens = array();
        $iSurveyID = (int)$iSurveyID;

        if (!isset($tokens[$iSurveyID])) {
            // Make sure common_helper is loaded
            Yii::import('application.helpers.common_helper', true);

            $tokens_table = "{{tokens_{$iSurveyID}}}";
            if (tableExists($tokens_table)) {
                $tokens[$iSurveyID] = true;
            } else {
                $tokens[$iSurveyID] = false;
            }
        }

        return $tokens[$iSurveyID];
    }

    public function getIsExpired()
    {
        return !empty($this->expires)
        && (new DateTime($this->expires)) < new DateTime()
        && (new DateTime($this->startdate)) > new DateTime();
    }

    /**
     * Creates a new survey - does some basic checks of the suppplied data
     *
     * @param array $aData Array with fieldname=>fieldcontents data
     * @return integer The new survey id
     */
    public function insertNewSurvey($aData)
    {
        do {
            if (isset($aData['wishSID'])) // if wishSID is set check if it is not taken already
            {
                $aData['sid'] = $aData['wishSID'];
                unset($aData['wishSID']);
            } else {
                $aData['sid'] = randomChars(6, '123456789');
            }

            $isresult = self::model()->findByPk($aData['sid']);
        } while (!is_null($isresult));

        $survey = new self;
        foreach ($aData as $k => $v) {
            $survey->$k = $v;
        }
        $sResult = $survey->save();
        if (!$sResult) {
            return false;
        } else {
            return $aData['sid'];
        }
    }

    public function getFieldMap($style = 'short')
    {
        if (!isset($this->_fieldMap[$style])) {
            $this->_fieldMap[$style] = createFieldMap($this->sid, $style);
        }

        return $this->_fieldMap[$style];


    }

    public function getFormatOptions()
    {
        return [
            self::FORMAT_QUESTION => gT("Question by Question"),
            self::FORMAT_GROUP => gT("Group by Group"),
            self::FORMAT_ALL_IN_ONE => gT("All in one"),
        ];
    }

    public function getQnumOptions()
    {
        return [
            self::QNUM_SHOW_NEITHER => gT('Hide both'),
            self::QNUM_SHOW_CODE => gT('Show question code only'),
            self::QNUM_SHOW_NUM => gT('Show question number only'),
            self::QNUM_SHOW_BOTH => gT('Show both')
        ];
    }


    public function getGroupOptions()
    {
        return [
            self::GINF_SHOW_NEITHER => gT('Hide both'),
            self::GINF_SHOW_NAME => gT('Show group name only'),
            self::GINF_SHOW_DESC => gT('Show group description only'),
            self::GINF_SHOW_BOTH => gT('Show both')
        ];
    }

    public function getIndexOptions()
    {
        return [
            self::INDEX_NONE => gT('Disabled'),
            self::INDEX_INCREMENTAL => gT('Incremental'),
            self::INDEX_FULL => gT('Full')
        ];
    }

    public function getInfo($language = null)
    {
        $language = !isset($language) ? $this->language : $language;
        $result = $this->attributes;
        if (null !== $localization = SurveyLanguageSetting::model()->findByPk([
                'surveyls_survey_id' => $this->primaryKey,
                'surveyls_language' => $language
            ])
        ) {
            $result = array_merge($result, $localization->attributes);
            $result['name'] = $result['surveyls_title'];
            $result['description'] = $result['surveyls_description'];
            $result['welcome'] = $result['surveyls_welcometext'];
            $result['adminname'] = $result['admin'];
            $result['tablename'] = '{{survey_' . $result['sid'] . '}}';
            $result['urldescrip'] = $result['surveyls_urldescription'];
            $result['url'] = $result['surveyls_url'];
            $result['expiry'] = $result['expires'];
            $result['email_invite_subj'] = $result['surveyls_email_invite_subj'];
            $result['email_invite'] = $result['surveyls_email_invite'];
            $result['email_remind_subj'] = $result['surveyls_email_remind_subj'];
            $result['email_remind'] = $result['surveyls_email_remind'];
            $result['email_confirm_subj'] = $result['surveyls_email_confirm_subj'];
            $result['email_confirm'] = $result['surveyls_email_confirm'];
            $result['email_register_subj'] = $result['surveyls_email_register_subj'];
            $result['email_register'] = $result['surveyls_email_register'];
            $result['attributedescriptions'] = $this->tokenAttributes;
            $result['attributecaptions'] = $localization->attributeCaptions;
            if (!isset($result['adminname'])) {
                $result['adminname'] = Yii::app()->getConfig('siteadminemail');
            }
            if (!isset($result['adminemail'])) {
                $result['adminemail'] = Yii::app()->getConfig('siteadminname');
            }
            if (!isset($result['urldescrip']) || $result['urldescrip'] == '') {
                $result['urldescrip'] = $result['surveyls_url'];
            }

        }

        return $result;
    }

    /**
     * Scope to remove surveys for which the current user doesn't have access.
     */
    public function accessible()
    {
        if (!App()->user->checkAccess('superadmin')) {
            $this->permission(Yii::app()->user->id);
        }

        return $this;
    }

    public function getCompletedResponseCount()
    {
        return $this->isNewRecord || !Response::valid($this->sid) ? 0 : Response::model($this->sid)->complete()->count();
    }

    public function getPartialResponseCount()
    {
        return $this->isNewRecord || !Response::valid($this->sid) ? 0 : Response::model($this->sid)->incomplete()->count();
    }

    /**
     * @return int
     */
    public function getResponseCount()
    {
        return $this->isNewRecord || !Response::valid($this->sid, true) ? 0 : Response::model($this->sid)->count();
    }

    /**
     * Returns the response rate of the survey as a float.
     * @todo We should decide how to define this, a good metric would be sent completed / invitation count
     * @return float
     */
    public function getResponseRate()
    {
        return 0;
    }

    /**
     * Returns the generic survey response columns and the question specific columns.
     * @return string[] Array containing field names and types.
     */
    public function getColumns()
    {
        $result = [
            'id' => 'string(36) NOT NULL',
            'startlanguage' => 'string(20) NOT NULL',
            'submitdate' => 'datetime',
            'lastpage' => 'int',
        ];
        if ($this->bool_datestamp) {
            $result['datestamp'] = 'datetime NOT NULL';
            $result['startdate'] = 'datetime NOT NULL';
        }
        if ($this->bool_ipaddr) {
            $result['ipaddress'] = 'string(15)';
        }
        if ($this->bool_usetokens) {

            $result['token'] = "string({$this->tokenlength})";
        }
        if ($this->bool_refurl) {
            $result['url'] = "string";
        }

        if ($this->use_series) {
            $result['series_id'] = 'string(36) NOT NULL';
        }

        /** @var Question $question */
        foreach ($this->questions as $question) {
            $result += $question->columns;
        }

        return $result;
    }

    /**
     * Attempts to activate the survey.
     */
    public function activate()
    {
        $result = false;
        // Precheck.

        if (true) {

            // Create tables.
            $messages = [];
            if (Response::createTable($this, $messages)) {

            }
            if ($this->bool_usetokens && !Token::valid($this->sid)) {
                Token::createTable($this->sid);
            }
            if (Timing::createTable($this, $messages)) {

            }

            // Set active to true.
            $this->active = 'Y';
            $result = $this->save();
        }

        return $result;
    }

    /**
     * Attempts to deactivate the survey.
     */
    public function deactivate()
    {
        $result = false;
        // Precheck.
        if (true) {
            // We set active to false first; this ensures no new users entering the survey.
            $this->bool_active = false;
            $this->save();

            if (Response::valid($this->sid)) {
                $responseTable = Response::model($this->sid);
                // We drop the response table if it is empty.
                if ($responseTable->count() == 0) {
                    $this->dbConnection->createCommand()->dropTable($responseTable->tableName());
                } else {
                    $name = strtr($responseTable->tableName(),
                            ['survey_' => 'survey_old_']) . '_' . date('Y-m-d_H-i-s');
                    $this->dbConnection->createCommand()->renameTable($responseTable->tableName(), $name);
                }
            }

            if (Token::valid($this->sid, true)) {
                $tokenTable = Token::model($this->sid);
                // We drop the token table if it is empty.
                if ($tokenTable->count() == 0) {
                    $this->dbConnection->createCommand()->dropTable($tokenTable->tableName());
                } else {
                    $name = strtr($tokenTable->tableName(), ['token_' => 'token_old_']) . '_' . date('Y-m-d_H-i-s');
                    $this->dbConnection->createCommand()->renameTable($tokenTable->tableName(), $name);
                }
            }


            // Remove entries in ls\models\SavedControl
            /**
             * @todo
             *
             */

            // Remove / rename timings table.
            /**
             * @todo
             */

            return true;
        }

        return $result;
    }

    /**
     * Attempts to expire the survey.
     */
    public function expire()
    {
        $this->expires = '0000-00-00 00:00:00';

        return $this->save();
    }

    public function unexpire()
    {
        $this->expires = null;

        return $this->save();
    }

    public function getFeatures()
    {
        $result = [];
        foreach ($this->getFeatureOptions() as $key => $value) {
            if ($this->$key) {
                $result[] = $key;
            }
        }

        return $result;
    }

    public function setFeatures($value)
    {
        $value = is_array($value) ? $value : [];
        foreach ($this->getFeatureOptions() as $key => $title) {
            /**
             * @todo Could be optimized for less array searching.
             */
            $this->$key = in_array($key, $value);
        }
    }

    public function getFeatureOptions()
    {
        return [
            'use_series' => gT("Response series"),
            'bool_usetokens' => gT("Token support"),
            'bool_anonymized' => gT("Anonymized responses"),
            'bool_datestamp' => gT("Date stamps"),
            'bool_ipaddr' => gT("Log IP address"),
            'bool_refurl' => gT("Log referrer URL"),
            'bool_savetimings' => gT("Save timing information")
        ];
    }

    public function getCaptchaOptions()
    {
        $a = gT("Survey Access");
        $an = str_pad('', strlen($a), '-');
        $r = gT("Registration");
        $rn = str_pad('', strlen($r), '-');
        $s = gT("Save & Load");
        $sn = str_pad('', strlen($s), '-');

        return [
            'A' => implode(' / ', [$a, $r, $s]),
            'B' => implode(' / ', [$a, $r, $sn]),
            'C' => implode(' / ', [$a, $rn, $s]),
            'D' => implode(' / ', [$an, $r, $s]),
            'X' => implode(' / ', [$a, $rn, $sn]),
            'R' => implode(' / ', [$an, $r, $sn]),
            'S' => implode(' / ', [$an, $rn, $s]),
            'N' => implode(' / ', [$an, $rn, $sn])
        ];
    }

    public function __get($name)
    {
        if (substr($name, 0, 5) == 'bool_') {
            $result = parent::__get(substr($name, 5)) === 'Y';
        } else {
            $result = parent::__get($name);
        }

        return $result;
    }

    public function __set($name, $value)
    {
        if (substr($name, 0, 5) == 'bool_') {
            parent::__set(substr($name, 5), $value ? 'Y' : 'N');
        } else {
            parent::__set($name, $value);
        }
    }

    public function __isset($name)
    {
        if (substr($name, 0, 5) == 'bool_') {
            $result = parent::__isset(substr($name, 5));
        } else {
            $result = parent::__isset($name);
        }

        return $result;
    }

    public function getTotalSteps()
    {
        switch ($this->format) {
            case "A":
                $result = 1;
                break;
            case "G":
                $result = $this->groupCount;
                break;
            case "S":
                $result = $this->questionCount;
                break;
            default:
                throw new \Exception("Unknown survey display format ({$this->format})");

        }

        return $result;

    }

    public function getLanguages()
    {

        $result = $this->getAdditionalLanguages();
        array_unshift($result, $this->language);

        return $result;
    }


    public function getTranslatedFields()
    {
        /** @var SurveyLanguageSetting $languageSetting */
        $result = [];
        foreach ($this->languagesettings as $languageSetting) {
            $result[$languageSetting->surveyls_language] = $languageSetting->attributes;
        }

        return $result;
    }

    /**
     * We save this immediately if / when we move to TranslatableBehavior, saving will happen automatically when
     * saving the main record.
     * @param array $value
     */
    public function setTranslatedFields($value)
    {
        foreach ($value as $language => $fields) {
            if (!isset($this->languagesettings[$language])) { // && in_array($language,$this->getAllLanguages()) ?
                $this->languagesettings[$language] = $languageSetting = new SurveyLanguageSetting();
                $languageSetting->surveyls_survey_id = $this->primaryKey;
                $languageSetting->surveyls_language = $language;
            } else {
                $languageSetting = $this->languagesettings[$language];
            }
            $languageSetting->attributes = $fields;
            $languageSetting->save();
        }
    }

    /**
     * @return Response[]
     */
    public function getResponses()
    {
        $result = Response::model($this->sid)->findAll();
        // Forward load the survey object.
        foreach ($result as $response) {
            $response->survey = $this;
        }

        return $result;
    }

    /**
     * Returns the relations that map to dependent records.
     * Dependent records should be deleted when this object gets deleted.
     * @return string[]
     */
    public function dependentRelations()
    {
        return [
            'languagesettings',
            'groups',
            'savedControls',
            'surveyLinks',
            'quota'
        ];
    }

    /**
     * Deletes this record and all dependent records.
     * @throws CDbException
     */
    public function deleteDependent()
    {
        if (App()->db->getCurrentTransaction() == null) {
            $transaction = App()->db->beginTransaction();
        }
        foreach ($this->dependentRelations() as $relation) {
            /** @var CActiveRecord $record */

            $config = $this->relations()[$relation];
            if (method_exists($config[1], 'deleteDependent')) {
                foreach ($this->$relation as $record) {
                    $record->deleteDependent();
                }
            } else {
                // Delete all records in the relation.
                if ($config[0] == \CHasManyRelation::class && !isset($config['on']) && is_string($config[2])) {
                    $class = $config[1];
                    $class::model()->deleteAllByAttributes([
                        $config[2] => $this->primaryKey
                    ]);
                } else {
                    throw new \Exceptiion("dont know what to do!");
                }
            }
        }
        $this->delete();

        if (isset($transaction)) {
            $transaction->commit();
        }
    }


}
