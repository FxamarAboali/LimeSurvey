<?php

require_once(APPPATH.'/third_party/phpmailer/load_phpmailer.php');

/**
 * WIP
 * A SubClass of phpMailer adapted for LimeSurvey
 */
class LimeMailer extends \PHPMailer\PHPMailer\PHPMailer
{
    /**
     * Singleton
     * @var LimeMailer
     */
    private static $instance = null;

    /**
     * Reset part
     */
    /* No reset */
    CONST ResetNone = 0;
    /* Basic reset */
    CONST ResetBase = 1;
    /* Complete reset : all except survey part , remind : you always can get a new one */
    CONST ResetComplete = 2;

    /* Current survey id */
    public $surveyId;
    /* Current language */
    public $mailLanguage;
    /* Current email use html */
    public $html = true;
    /* email must be sent */
    private $sent = false;

    /* Current token object */
    public $oToken;

    /* @var string[] Array for barebone url and url */
    public $aUrlsPlaceholders = [];

    /*  @var string[] Array of replacements */
    public $aReplacements = [];

    /**
     * @var string Current email type, used for updating email raw subject and body
     * for survey (token) : invite, remind, confirm, register …
     * for survey (admin) :
     * other : newuser, passwordreminder … 
     **/
    public $emailType = 'unknow';

    /**
     * @var boolean replace token attributes (FIRSTNAME etc …) and replace to TOKEN:XXX by XXXX
     */
    public $replaceTokenAttributes = false;

    /**
     * @var array Current attachements (as string or array)
     * @see parent::addAttachment
     **/
    public $aAttachements = array();

    /**
     * The Raw Subject of the message. before any update
     * @var string
     */
    public $rawSubject = '';

    /**
     * The Rw Body of the message, before any update
     * @var string
     */
    public $rawBody = '';

    /**
     * Charset of Body and Subject
     */
    public $BodySubjectCharset = 'utf-8';

    /* var string */
    private $eventName = 'beforeEmail';

    /* @var string event message */
    private $eventMessage = null;

    /* @var string[] */
    public $debug = array();

    /**
     * @inheritdoc
     * Set default to idna (unsure is needed : need an idna email to check since seems PHPMailer do the job here ?)
     * @var string|callable
     */
    public static $validator = 'php-idna';

    /**
     * WIP Set all needed fixed in params
     */
    public function __construct()
    {
        /* Launch parent without Exceptions */
        parent::__construct(false);
        /* Global configuration for ALL email of this LimeSurvey instance */
        $emailmethod = Yii::app()->getConfig('emailmethod');
        $emailsmtphost = Yii::app()->getConfig("emailsmtphost");
        $emailsmtpuser = Yii::app()->getConfig("emailsmtpuser");
        $emailsmtppassword = Yii::app()->getConfig("emailsmtppassword");
        $emailsmtpdebug = Yii::app()->getConfig("emailsmtpdebug");
        $emailsmtpssl = Yii::app()->getConfig("emailsmtpssl");
        $defaultlang = Yii::app()->getConfig("defaultlang");
        $emailcharset = Yii::app()->getConfig("emailcharset");

        /* Set language for errors */
        if (!$this->SetLanguage(Yii::app()->getConfig("defaultlang"),APPPATH.'/third_party/phpmailer/language/')) {
            $this->SetLanguage('en',APPPATH.'/third_party/phpmailer/language/');
        }

        $this->mailLanguage = Yii::app()->getLanguage();

        $this->SMTPDebug = Yii::app()->getConfig("emailsmtpdebug");
        $this->Debugoutput = function($str, $level) {
            $this->addDebug($str);
        };

        if (Yii::app()->getConfig('demoMode')) {
            return;
        }

        $this->CharSet = Yii::app()->getConfig("emailcharset");

        /* Don't check tls by default : allow own sign certificate */
        $this->SMTPAutoTLS = false;

        switch ($emailmethod) {
            case "qmail":
                $this->IsQmail();
                break;
            case "smtp":
                $this->IsSMTP();
                if ($emailsmtpdebug > 0) {
                    $this->SMTPDebug = $emailsmtpdebug;
                }
                if (strpos($emailsmtphost, ':') > 0) {
                    $this->Host = substr($emailsmtphost, 0, strpos($emailsmtphost, ':'));
                    $this->Port = (int) substr($emailsmtphost, strpos($emailsmtphost, ':') + 1);
                } else {
                    $this->Host = $emailsmtphost;
                }
                if ($emailsmtpssl === 1) {
                    $this->SMTPSecure = "ssl";
                } elseif(!empty($emailsmtpssl)) {
                    $this->SMTPSecure = $emailsmtpssl;
                }
                $this->Username = $emailsmtpuser;
                $this->Password = $emailsmtppassword;
                if (trim($emailsmtpuser) != "") {
                    $this->SMTPAuth = true;
                }
                break;
            case "sendmail":
                $this->IsSendmail();
                break;
            default:
                $this->IsMail();
        }

        /* set default return path */
        if(!empty(Yii::app()->getConfig('siteadminbounce'))) {
            $this->Sender = Yii::app()->getConfig('siteadminbounce');
        }
        $this->addCustomHeader("X-Surveymailer",Yii::app()->getConfig("sitename")." Emailer (LimeSurvey.org)");
    }

    /**
     * needed by Yii::app() ?
     */
    public function init()
    {

    }
    /**
     * To get a singleton : some part are not needed to do X times
     * @param integer $reset partially $this
     * return self
     */
    public static function getInstance($reset=self::ResetBase)
    {
        Yii::log("Call instance", 'info', 'application.Mailer.LimeMailer.getInstance');
        if (empty(self::$instance)) {
            Yii::log("New mailer instance", 'info', 'application.Mailer.LimeMailer.getInstance');
            self::$instance = new self;
            /* no need to reset if new */
            return self::$instance;
        }
        Yii::log("Existing mailer instance", 'info', 'application.Mailer.LimeMailer.getInstance');
        /* Some part must be always resetted */
        self::$instance->debug = [];
        if($reset) {
            self::$instance->clearAddresses(); // Unset only $this->to recepient
            self::$instance->clearAttachments(); // Unset attachments (maybe only under condition ?)
            self::$instance->oToken = null;
            if($reset > 1) {
                self::$instance->AltBody = "";
                self::$instance->Body = "";
                self::$instance->Subject = "";
                /* Clear extra to */
                self::$instance->clearAllRecipients(); /* clearAddresses + clearCCs + clearBCCs */
                self::$instance->clearCustomHeaders();
                if(self::$instance->surveyId) {
                    /* Reset cleaned part for this survey (no from or sender resetted) */
                    self::$instance->setSurvey(self::$instance->surveyId);
                }
            }
        }
        return self::$instance;
    }

    /**
     * Set email for this survey
     * If surveyId are not updated : no reset of from or sender
     * @param integer $surveyId
     * @return void
     */
    public function setSurvey($surveyId)
    {
        $this->addCustomHeader("X-surveyid",$surveyId);
        $this->eventName = "beforeSurveyEmail";
        $oSurvey = Survey::model()->findByPk($surveyId);
        $this->isHtml($oSurvey->getIsHtmlEmail());
        if(!in_array($this->mailLanguage,$oSurvey->getAllLanguages())) {
            $this->mailLanguage = $oSurvey->language;
        }
        if($this->surveyId == $surveyId) {
            // Other part not needed (to confirm)
            return;
        }
        $this->surveyId = $surveyId;
        if(!empty($oSurvey->oOptions->adminemail) && self::validateAddress($oSurvey->oOptions->adminemail)) {
            $this->setFrom($oSurvey->oOptions->adminemail,$oSurvey->oOptions->admin);
        }
        if(!empty($oSurvey->oOptions->bounce_email) && self::validateAddress($oSurvey->oOptions->bounce_email)) {
            // Check what for N : did we leave default or not (if it's set and valid ?)
            $this->Sender = $oSurvey->oOptions->bounce_email;
        }
    }

    /**
     * Add url place holder
     * @param string|string[] $aUrlsPlaceholders an array of url placeholder to set automatically
     * @return void
     */
    public function addUrlsPlaceholders($aUrlsPlaceholders)
    {
        if(is_string($aUrlsPlaceholders)){
            $aUrlsPlaceholders = [$aUrlsPlaceholders];
        }
        $this->aUrlsPlaceholders = array_merge($this->aUrlsPlaceholders,$aUrlsPlaceholders);
    }

    /**
     * Set email for this survey
     * @param string $token
     * @return void
     * @throw CException
     */
    public function setToken($token)
    {
        if(empty($this->surveyId)) {
            throw new \CException("Survey must be set before set token");
        }
        /* Did need to check all here ? */
        $oToken =  \Token::model($this->surveyId)->findByToken($token);
        if(empty($oToken)) {
            throw new \CException("Invalid token");
        }
        $this->oToken = $oToken;
        $this->mailLanguage = Survey::model()->findByPk($this->surveyId)->language;
        if(in_array($oToken->language,Survey::model()->findByPk($this->surveyId)->getAllLanguages())) {
            $this->mailLanguage = $oToken->language;
        }
        $this->eventName = 'beforeTokenEmail';
        $aEmailaddresses = preg_split("/(,|;)/", $this->oToken->email);
        foreach ($aEmailaddresses as $sEmailaddress) {
            $this->addAddress($sEmailaddress,$oToken->firstname." ".$oToken->lastname);
        }
        $this->addCustomHeader("X-tokenid",$oToken->token);
    }

    /**
     * set the rawSubject and rawBody according to type
     * @param string|null $emailType : set the rawSubject and rawBody at same time
     * @param string|null $language forced language
     */
    public function setTypeWithRaw($emailType, $language=null)
    {
        $this->emailType = $emailType;
        if(empty($language) and !empty($this->oToken)) {
            $language = $this->oToken->language;
        }
        if(empty($language)) {
            $language = App()->language;
        }
        $this->mailLanguage = $language;
        if(empty($this->surveyId)) {
            return;
        }
        if(!in_array($language,Survey::model()->findByPk($this->surveyId)->getAllLanguages())) {
            $this->mailLanguage = Survey::model()->findByPk($this->surveyId)->language;
        }
        $this->mailLanguage = $language;
        if(!in_array($emailType,['invite','remind','register','confirm','admin_notification','admin_responses'])) {
            return;
        }
        $oSurveyLanguageSetting = SurveyLanguageSetting::model()->findByPk(array('surveyls_survey_id'=>$this->surveyId, 'surveyls_language'=>$this->mailLanguage));
        $attributeSubject = "email_{$emailType}_subj";
        $attributeBody = "email_{$emailType}";
        $this->rawSubject = $oSurveyLanguageSetting->{$attributeSubject};
        $this->rawBody = $oSurveyLanguageSetting->{$attributeBody};
    }
    /**
     * @inheritdoc
     * Fix first parameters if he had email + name ( Name <email> format)
      */
    public function setFrom($from,$fromname = "",$auto = true)
    {
        $fromemail = $from;
        if (strpos($from, '<')) {
            $fromemail = substr($from, strpos($from, '<') + 1, strpos($from, '>') - 1 - strpos($from, '<'));
            if(empty($fromname)) {
                $fromname = trim(substr($from, 0, strpos($from, '<') - 1));
            }
        }
        parent::setFrom($fromemail, $fromname, $auto);
    }

    /**
     * @inheritdoc
     * Fix first parameters if he had email + name ( Name <email> format)
     */
    public function addAddress($addressTo, $name = '')
    {
        $address = $addressTo;
        if (strpos($address, '<')) {
            $address = substr($addressTo, strpos($addressTo, '<') + 1, strpos($addressTo, '>') - 1 - strpos($addressTo, '<'));
            if(empty($name)) {
                $name = trim(substr($addressTo, 0, strpos($addressTo, '<') - 1));
            }
        }
        return parent::addAddress($address, $name);
    }

    /**
     * Get from
     * @return string from (email + name)
     */
    public function getFrom()
    {
        if(empty($this->FromName)) {
            return $this->From;
        }
        return $this->FromName." <".$this->From.">";
    }

    /**
     * Add a debug line (with a new line like SMTP echo)
     * @param string
     * @param integer
     * @return void
     */
    public function addDebug($str, $level = 0) {
        $this->debug[] = rtrim($str)."\n";
    }

    /**
     * Hate to use global var
     * maybe add format : raw (array of errors), html : clean html etc …
     * @param string $format (currently only html or null (return array))
     * @return null|string|array
     */
    public function getDebug($format='')
    {
        if(empty($this->debug)) {
            return null;
        }
        switch ($format) {
            case 'html':
                $debug = array_map('CHtml::encode',$this->debug);
                return CHtml::tag("pre",array('class'=>'maildebug'),implode("",$debug));
                break;
            default:
                return $this->debug;
        }
    }

    /**
     * Hate to use global var
     * maybe add format : raw (array of errors), html : clean html etc …
     */
    public function getError()
    {
        return $this->ErrorInfo;
    }

    /**
     * Launch the needed event : beforeTokenEmail, beforeSurveyEmail, beforeEmail
     * and update this according to action
     * return boolean|null : if it's not null : stop sending, boolean are the result of sended.
     */
    private function manageEvent($eventParams=array())
    {
        switch($this->emailType) {
            case 'invite':
                $model = 'invitation';
                break;
            case 'remind':
                $model = 'reminder';
                break;
            default:
                $model = $this->emailType;
        }
        $eventBaseParams = array(
            'survey'=>$this->surveyId,
            'type'=>$this->emailType,
            'model'=>$model,
            'to'=>$this->to, // To review for multiple tokens
            'subject'=>$this->Subject,
            'body'=>$this->Body,
            'from'=>$this->getFrom(),
            'bounce'=>$this->Sender,
        );
        if(!empty($this->oToken)) {
            $eventBaseParams['token'] = $this->oToken->getAttributes();
        }
        $eventParams = array_merge($eventBaseParams,$eventParams);
        $event = new PluginEvent($this->eventName);
        /**
         * plugin can get this mailer with $oEvent->get('mailer')
         * This allow udpate of anythings : $this->getEvent()->get('mailer')->addCC or $this->getEvent()->get('mailer')->addCustomHeader etc …
         **/
        $event->set('mailer',$this); //  no need to add other event param
        /* Previous plugin compatibility … */
        foreach($eventParams as $param=>$value) {
            $event->set($param, $value);
        }
        /* A plugin can update any part : here true, but i really think it's best if it false */
        /* Maybe part by part ? $event->get('updated') as arry : update only what is updated */
        $event->set('updateDisable',array());
        App()->getPluginManager()->dispatchEvent($event);
        /* Manage what can be updated */
        $updateDisable = $event->get('updateDisable');
        if(empty($updateDisable['subject'])) {
            $this->Subject = $event->get('subject');
        }
        if(empty($updateDisable['body'])) {
            $this->Body = $event->get('body');
        }
        if(empty($updateDisable['from'])) {
            $this->setFrom($event->get('from'));
        }
        if(empty($updateDisable['to'])) {
            /* Warning : pre 4 version send array of string, here we send array of array (email+name) */
            /* I think it's better BUT it broke plugin API for email : need a compatible API ? */
            /* But then with a new settings for «plugin have updated the to event param ? */
            $this->to = $event->get('to');
        }
        if(empty($updateDisable['bounce'])) {
            $this->Sender = $event->get('bounce');
        }
        $this->eventMessage = $event->get('message');
        if($event->get('send', true) == false) {
            $this->ErrorInfo = $event->get('error');
            return $event->get('error') == null;
        }
    }

    public function getEventMessage()
    {
        return $this->eventMessage;
    }

    public function sendMessage()
    {
        if (Yii::app()->getConfig('demoMode')) {
            $this->setError(gT('Email was not sent because demo-mode is activated.'));
            return false;
        }
        if(!empty($this->rawSubject)) {
            $this->Subject = $this->doReplacements($this->rawSubject);
        }
        if(!empty($this->rawBody)) {
            $this->Body = $this->doReplacements($this->rawBody);
        }
        if($this->CharSet != $this->BodySubjectCharset) {
            /* Must test this … */
            $this->Subject = mb_convert_encoding($this->Subject,$this->CharSet,$this->BodySubjectCharset);
            $this->Body = mb_convert_encoding($this->Body,$this->CharSet,$this->BodySubjectCharset);
        }
        $this->setCoreAttachements();
        /* All core done, next are done for all survey */
        $eventResult = $this->manageEvent();
        if(!is_null($eventResult)) {
            return $eventResult;
        }
        /* Fix body according to HTML on/off */
        if($this->ContentType == 'text/html') {
            if (strpos($this->Body, "<html>") === false) {
                $this->Body = "<html>".$this->Body."</html>";
            }
            $this->msgHTML($this->Body, App()->getConfig("publicdir")); // This allow embedded image if we remove the servername from image
            if(empty($this->AltBody)) {
                $html = new \Html2Text\Html2Text($body);
                $this->AltBody = $this->getText();
            }
        }
        return $this->Send();
    }

    /**
     * Surely need to extend parent
     */
    public function Send()
    {
        if (Yii::app()->getConfig('demoMode')) {
            $this->setError(gT('Email was not sent because demo-mode is activated.'));
            return false;
        }
        return parent::Send();
    }

    /**
     * Get the replacements for token.
     * @return string[]
     */
    public function getTokenReplacements() {
        $aTokenReplacements = array();
        if(empty($this->oToken)) { // Did need to check if sent to token ?
            return $aTokenReplacements;
        }
        $language = Yii::app()->getLanguage();
        if(!in_array($language,Survey::model()->findByPk($this->surveyId)->getAllLanguages())) {
            $language = Survey::model()->findByPk($this->surveyId)->language;
        }
        $token = $this->oToken->token;
        if(!empty($this->oToken->language)) {
            $language = trim($this->oToken->language);
        }
        LimeExpressionManager::singleton()->loadTokenInformation($this->surveyId, $this->oToken->token);
        if($this->replaceTokenAttributes) {
            foreach ($this->oToken->attributes as $attribute => $value) {
                $aTokenReplacements[strtoupper($attribute)] = $value;
            }
        }
        /* Did we need to check if each url are in $this->aUrlsPlaceholders ? */
        $aTokenReplacements["OPTOUTURL"] = App()->getController()
            ->createAbsoluteUrl("/optout/tokens", array("surveyid"=>$this->surveyId, "token"=>$token,"langcode"=>$language));
        $aTokenReplacements["OPTINURL"] = App()->getController()
            ->createAbsoluteUrl("/optin/tokens", array("surveyid"=>$this->surveyId, "token"=>$token,"langcode"=>$language));
        $aTokenReplacements["SURVEYURL"] = App()->getController()
            ->createAbsoluteUrl("/survey/index", array("sid"=>$this->surveyId, "token"=>$token,"lang"=>$language));
        return $aTokenReplacements;
    }

    /**
     * Do the replacements : if current replacement jey is set and LimeSurvey core have it too : it reset to the needed one.
     * @param string $string wher need to replace
     * @return string
     */
    public function doReplacements($string)
    {
        $aReplacements = array();
        if($this->surveyId) {
            $aReplacements["SID"] = $this->surveyId;
            $oSurvey = Survey::model()->findByPk($this->surveyId);
            $aReplacements["EXPIRY"] = $oSurvey->expires;
            $aReplacements["ADMINNAME"] = $oSurvey->oOptions->admin;
            $aReplacements["ADMINEMAIL"] = $oSurvey->oOptions->adminemail;
            if(!in_array($this->mailLanguage,$oSurvey->getAllLanguages())) {
                $this->mailLanguage = $oSurvey->language;
            }
            /* Get it separatly since (not Survey::model()->with('languagesetting')) since need to be sure to get current language ? */
            $oSurveyLanguageSettings = SurveyLanguageSetting::model()->findByPk(array('surveyls_survey_id'=>$this->surveyId, 'surveyls_language'=>$this->mailLanguage));
            $aReplacements["SURVEYNAME"] = $oSurveyLanguageSettings->surveyls_title;
            $aReplacements["SURVEYDESCRIPTION"] = $oSurveyLanguageSettings->surveyls_description;
        }
        $aTokenReplacements = $this->getTokenReplacements();
        if($this->replaceTokenAttributes && !empty($aTokenReplacements)) {
            $string = preg_replace("/{TOKEN:([A-Z0-9_]+)}/", "{"."$1"."}", $string);
        }
        $aReplacements = array_merge($aReplacements,$aTokenReplacements);
        /* Fix Url replacements */
        foreach ($this->aUrlsPlaceholders as $urlPlaceholder) {
            if(!empty($aReplacements["{$urlPlaceholder}URL"])) {
                $url = $aReplacements["{$urlPlaceholder}URL"];
                $string = str_replace("@@{$urlPlaceholder}URL@@", $url, $string);
                $aReplacements["{$urlPlaceholder}URL"] = Chtml::link($url,$url);
            }
        }
        $aReplacements = array_merge($this->aReplacements,$aReplacements);
        return LimeExpressionManager::ProcessString($string, null, $aReplacements, 3, 1, false, false, true);
    }

    /**
     * Set the attachments according to current survey,language and emailtype
     * @ return void
     */
    public function setCoreAttachements()
    {
        if(empty($this->surveyId)) {
            return;
        }
        switch ($this->emailType) {
            case 'invite':
                $attachementType = 'invitation';
                break;
            case 'remind':
                $attachementType = 'reminder';
                break;
            case 'register':
                $attachementType = 'registration';
                break;
            default:
                $attachementType = $this->emailType;
        }
        if(!in_array($attachementType,['invitation','reminder','registration','admin_notification','admin_detailed_notification'])) {
            return;
        }
        $oSurveyLanguageSetting = SurveyLanguageSetting::model()->findByPk(array('surveyls_survey_id'=>$this->surveyId, 'surveyls_language'=>$this->mailLanguage));
        if(!empty($oSurveyLanguageSetting->attachments) ) {
            $aAttachments = unserialize($oSurveyLanguageSetting->attachments);
            if(!empty($aAttachments[$this->emailType])) {
                if($this->oToken) {
                    LimeExpressionManager::singleton()->loadTokenInformation($this->surveyId, $this->oToken->token);
                }
                foreach ($aAttachments[$sTemplate] as $aAttachment) {
                    if (LimeExpressionManager::singleton()->ProcessRelevance($aAttachment['relevance'])) {
                        $this->addAttachment($aAttachment['url']);
                    }
                }
            }
        }
        
    }

    /**
     * @inheritdoc
     * Adding php with idna support
     */
    public static function validateAddress($address, $patternselect = null)
    {
        if (null === $patternselect) {
            $patternselect = static::$validator;
        }
        if($patternselect != 'idna') {
            return parent::validateAddress($address, $patternselect);
        }
        require_once(APPPATH.'third_party/idna-convert/idna_convert.class.php');
        $oIdnConverter = new idna_convert();
        $sEmailAddress = $oIdnConverter->encode($sEmailAddress);
        $bResult = filter_var($sEmailAddress, FILTER_VALIDATE_EMAIL);
        if ($bResult !== false) {
            return true;
        }
        return false;
    }

    /**
    * Validate an list of email addresses - either as array or as semicolon-limited text
    * @return string List with valid email addresses - invalid email addresses are filtered - false if none of the email addresses are valid
    * @param string $aEmailAddressList  Email address to check
    * @param string|callable $patternselect Which pattern to use (default to static::$validator)
    * @returns array
    */
    public static function validateAddresses($aEmailAddressList, $patternselect = null)
    {
        $aOutList = [];
        if (!is_array($aEmailAddressList)) {
            $aEmailAddressList = explode(';', $aEmailAddressList);
        }

        foreach ($aEmailAddressList as $sEmailAddress) {
            $sEmailAddress = trim($sEmailAddress);
            if (self::validateAddress($sEmailAddress,$patternselect)) {
                $aOutList[] = $sEmailAddress;
            }
        }
        return $aOutList;
    }
}
