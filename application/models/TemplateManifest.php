<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2015 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

/**
 * Template Configuration Model
 *
 * This model retrieves all the data of template configuration from the configuration file
 *
 * @package       LimeSurvey
 * @subpackage    Backend
 */
class TemplateManifest extends TemplateConfiguration
{
    /** @var string $sTemplateName The template name */
    public $sTemplateName='';

    /** @var string $sPackageName Name of the asset package of this template*/
    public $sPackageName;

    /** @var  string $path Path of this template */
    public $path;

    /** @var string[] $sTemplateurl Url to reach the framework */
    public $sTemplateurl;

    /** @var  string $viewPath Path of the views files (twig template) */
    public $viewPath;

    /** @var  string $sFilesDirectory name of the file directory */
    public $sFilesDirectory;

    /** @var  string $filesPath Path of the tmeplate's files */
    public $filesPath;

    /** @var string[] $cssFramework What framework css is used */
    public $cssFramework;

    /** @var boolean $isStandard Is this template a core one? */
    public $isStandard;

    /** @var SimpleXMLElement $config Will contain the config.xml */
    public $config;

    /** @var TemplateConfiguration $oMotherTemplate The template name */
    public $oMotherTemplate;

    public $templateEditor;

    /** @var SimpleXMLElement $oOptions The template options */
    public $oOptions;

    /** @var string $iSurveyId The current Survey Id. It can be void. It's use only to retreive the current template of a given survey */
    private $iSurveyId='';

    /** @var string $hasConfigFile Does it has a config.xml file? */
    private $hasConfigFile='';//

    /** @var stdClass[] $packages Array of package dependencies defined in config.xml*/
    private $packages;

    /** @var string[] $depends List of all dependencies (could be more that just the config.xml packages) */
    private $depends = array();

    /** @var string $xmlFile What xml config file does it use? (config/minimal) */
    private $xmlFile;

    /**  @var integer $apiVersion: Version of the LS API when created. Must be private : disallow update */
    private $apiVersion;


    /**
     * Constructs a template configuration object
     * If any problem (like template doesn't exist), it will load the default template configuration
     *
     * @param  string $sTemplateName the name of the template to load. The string comes from the template selector in survey settings
     * @param  string $iSurveyId the id of the survey. If
     * @return $this
     */
    public function setTemplateConfiguration($sTemplateName='', $iSurveyId='')
    {
        $this->setTemplateName($sTemplateName, $iSurveyId);                     // Check and set template name
        $this->setIsStandard();                                                 // Check if  it is a CORE template
        $this->setPath();                                                       // Check and set path
        $this->readManifest();                                                  // Check and read the manifest to set local params
        $this->setMotherTemplates();                                            // Recursive mother templates configuration
        $this->setThisTemplate();                                               // Set the main config values of this template
        $this->createTemplatePackage($this);                                    // Create an asset package ready to be loaded
        return $this;
    }

    /**
     * Update the configuration file "last update" node.
     * For now, it is called only from template editor
     */
    public function actualizeLastUpdate()
    {
        libxml_disable_entity_loader(false);
        $config = simplexml_load_file(realpath ($this->xmlFile));
        $config->metadatas->last_update = date("Y-m-d H:i:s");
        $config->asXML( realpath ($this->xmlFile) );                // Belt
        touch ( $this->path );                                      // & Suspenders ;-)
        libxml_disable_entity_loader(true);
    }


    /**
     * get the template API version
     * @return integer
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
    * Returns the complete URL path to a given template name
    *
    * @param string $sTemplateName
    * @return string template url
    */
    public function getTemplateURL()
    {
        if(!isset($this->sTemplateurl)){
            $this->sTemplateurl = Template::getTemplateURL($this->sTemplateName);
        }
        return $this->sTemplateurl;
    }

    /**
     * Used from the template editor.
     * It returns an array of editable files by screen for a given file type
     *
     * @param   string  $sType      the type of files (view/css/js)
     * @param   string  $sScreen    the screen you want to retreive the files from. If null: all screens
     * @return  array   array       ( [screen name] => array([files]) )
     */
    public function getValidScreenFiles($sType = "view", $sScreen=null)
    {
        $aScreenFiles = array();

        $filesFromXML = (is_null($sScreen)) ? (array) $this->templateEditor->screens->xpath('//file') : $this->templateEditor->screens->xpath('//'.$sScreen.'/file');

        foreach( $filesFromXML as $file){

            if ( $file->attributes()->type == $sType ){
                $aScreenFiles[] = (string) $file;
            }
        }

        $aScreenFiles = array_unique($aScreenFiles);
        return $aScreenFiles;
    }

    /**
     * Returns the layout file name for a given screen
     *
     * @param   string  $sScreen    the screen you want to retreive the files from. If null: all screens
     * @return  string  the file name
     */
    public function getLayoutForScreen($sScreen)
    {
        $filesFromXML = $this->templateEditor->screens->xpath('//'.$sScreen.'/file');

        foreach( $filesFromXML as $file){

            if ( $file->attributes()->role == "layout" ){
                return (string) $file;
            }
        }

        return false;
    }

    /**
     * Retreives the absolute path for a file to edit (current template, mother template, etc)
     * Also perform few checks (permission to edit? etc)
     *
     * @param string $sfile relative path to the file to edit
     */
    public function getFilePathForEdition($sFile, $aAllowedFiles=null)
    {

        // Check if the file is allowed for edition ($aAllowedFiles is produced via getValidScreenFiles() )
        if (is_array($aAllowedFiles)){
            if (!in_array($sFile, $aAllowedFiles)){
                return false;
            }
        }

        return $this->getFilePath($sFile, $this);
    }

    /**
    * Copy a file from mother template to local directory and edit manifest if needed
    *
    * @param string $sTemplateName
    * @return string template url
    */
    public function extendsFile($sFile)
    {

        if( !file_exists($this->path.'/'.$sFile) && !file_exists($this->viewPath.$sFile) ){

            // Copy file from mother template to local directory
            $sRfilePath = $this->getFilePath($sFile, $this);
            $sLfilePath = (pathinfo($sFile, PATHINFO_EXTENSION) == 'twig')?$this->viewPath.$sFile:$this->path.'/'.$sFile;
            copy ( $sRfilePath,  $sLfilePath );

            // If it's a css or js file from config... must update DB and XML too....
            $sExt = pathinfo($sLfilePath, PATHINFO_EXTENSION);
            if ($sExt == "css" || $sExt == "js"){

                // Check if that CSS/JS file is in DB/XML
                $aFiles = $this->getFilesForPackages($sExt, $this);
                $sFile  = str_replace('./', '', $sFile);

                // The CSS/JS file is a configuration one....
                if(in_array($sFile, $aFiles)){

                    // First we get the XML file
                    libxml_disable_entity_loader(false);
                    $oNewManifest = new DOMDocument();
                    $oNewManifest->load($this->path."/config.xml");

                    $oConfig   = $oNewManifest->getElementsByTagName('config')->item(0);
                    $oFiles    = $oNewManifest->getElementsByTagName('files')->item(0);
                    $oOptions  = $oNewManifest->getElementsByTagName('options')->item(0);

                    if (is_null($oFiles)){
                        $oFiles    = $oNewManifest->createElement('files');
                    }

                    $oAssetType = $oFiles->getElementsByTagName($sExt)->item(0);
                    if (is_null($oAssetType)){
                        $oAssetType   = $oNewManifest->createElement($sExt);
                        $oFiles->appendChild($oAssetType);
                    }

                    // <filename replace="css/template.css">css/template.css</filename>
                    $oNewManifest->createElement('filename');

                    //$oConfig->appendChild($oNvFilesNode);
                    $oAssetElem       = $oNewManifest->createElement('filename', $sFile);
                    $replaceAttribute = $oNewManifest->createAttribute('replace');
                    $replaceAttribute->value = $sFile;
                    $oAssetElem->appendChild($replaceAttribute);
                    $oAssetType->appendChild($oAssetElem);
                    $oConfig->insertBefore($oFiles,$oOptions);
                    $oNewManifest->save($this->path."/config.xml");
                    libxml_disable_entity_loader(true);
                }
            }
        }

        return $this->getFilePath($sFile, $this);
    }

    /**
    * Get the files (css or js) defined in the manifest of a template and its mother templates
    *
    * @param  string $type       css|js
    * @param string $oRTemplate template from which the recurrence should start
    * @return array
    */
    public function getFilesForPackages($type, $oRTemplate)
    {
        $aFiles = array();
        while(is_a($oRTemplate, 'TemplateManifest')){
            $aTFiles = isset($oRTemplate->config->files->$type->filename)?(array) $oRTemplate->config->files->$type->filename:array();
            $aFiles  = array_merge($aTFiles, $aFiles);
            $oRTemplate = $oRTemplate->oMotherTemplate;
        }
        return $aFiles;
    }


    /**
    * Get the template for a given file. It checks if a file exist in the current template or in one of its mother templates
    *
    * @param  string $sFile      the  file to look for (must contain relative path, unless it's a view file)
    * @param string $oRTemplate template from which the recurrence should start
    * @return TemplateManifest
    */
    public function getTemplateForFile($sFile, $oRTemplate)
    {
        while (!file_exists($oRTemplate->path.'/'.$sFile) && !file_exists($oRTemplate->viewPath.$sFile)){
            $oMotherTemplate = $oRTemplate->oMotherTemplate;
            if(!($oMotherTemplate instanceof TemplateConfiguration)){
                throw new Exception("no template found for  $sFile!");
                break;
            }
            $oRTemplate = $oMotherTemplate;
        }

        return $oRTemplate;
    }

    /**
     * Get the list of all the files for a template and its mother templates
     * @return array
     */
    public function getOtherFiles()
    {
        $otherfiles = array();

        if (!empty($this->oMotherTemplate)){
            $otherfiles = $this->oMotherTemplate->getOtherFiles();
        }

        if ( file_exists($this->filesPath) && $handle = opendir($this->filesPath)){

            while (false !== ($file = readdir($handle))){
                if (!array_search($file, array("DUMMYENTRY", ".", "..", "preview.png"))) {
                    if (!is_dir($this->viewPath . DIRECTORY_SEPARATOR . $file)) {
                        $otherfiles[] = $this->sFilesDirectory . DIRECTORY_SEPARATOR . $file;
                    }
                }
            }

            closedir($handle);
        }
        return $otherfiles;
    }

    /**
     * Update the config file of a given template so that it extends another one
     *
     * It will:
     * 1. Delete files and engine nodes
     * 2. Update the name of the template
     * 3. Change the creation/modification date to the current date
     * 4. Change the autor name to the current logged in user
     * 5. Change the author email to the admin email
     *
     * Used in template editor
     * Both templates and configuration files must exist before using this function
     *
     * It's used when extending a template from template editor
     * @param   string  $sToExtends     the name of the template to extend
     * @param   string  $sNewName       the name of the new template
     */
    static public function extendsConfig($sToExtends, $sNewName)
    {
        $sConfigPath = Yii::app()->getConfig('usertemplaterootdir') . "/" . $sNewName;

        // First we get the XML file
        libxml_disable_entity_loader(false);
        $oNewManifest = new DOMDocument();
        $oNewManifest->load($sConfigPath."/config.xml");
        $oConfig            = $oNewManifest->getElementsByTagName('config')->item(0);

        // Then we delete the nodes that should be inherit
        $aNodesToDelete     = array();
        $aNodesToDelete[]   = $oConfig->getElementsByTagName('files')->item(0);
        $aNodesToDelete[]   = $oConfig->getElementsByTagName('engine')->item(0);

        foreach($aNodesToDelete as $node){
            $oConfig->removeChild($node);
        }

        // We replace the name by the new name
        $oMetadatas     = $oConfig->getElementsByTagName('metadatas')->item(0);

        $oOldNameNode   = $oMetadatas->getElementsByTagName('name')->item(0);
        $oNvNameNode    = $oNewManifest->createElement('name', $sNewName);
        $oMetadatas->replaceChild($oNvNameNode, $oOldNameNode);

        // We change the date
        $today          = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig("timeadjust"));
        $oOldDateNode   = $oMetadatas->getElementsByTagName('creationDate')->item(0);
        $oNvDateNode    = $oNewManifest->createElement('creationDate', $today);
        $oMetadatas->replaceChild($oNvDateNode, $oOldDateNode);

        $oOldUpdateNode = $oMetadatas->getElementsByTagName('last_update')->item(0);
        $oNvDateNode    = $oNewManifest->createElement('last_update', $today);
        $oMetadatas->replaceChild($oNvDateNode, $oOldUpdateNode);

        // We change the author name
        $oOldAuthorNode   = $oMetadatas->getElementsByTagName('author')->item(0);
        $oNvAuthorNode    = $oNewManifest->createElement('author', Yii::app()->user->name);
        $oMetadatas->replaceChild($oNvAuthorNode, $oOldAuthorNode);

        // We change the author email
        $oOldMailNode   = $oMetadatas->getElementsByTagName('authorEmail')->item(0);
        $oNvMailNode    = $oNewManifest->createElement('authorEmail', htmlspecialchars(getGlobalSetting('siteadminemail')));
        $oMetadatas->replaceChild($oNvMailNode, $oOldMailNode);

        // TODO: provide more datas in the post variable such as description, url, copyright, etc

        // We add the extend parameter
        $oExtendsNode    = $oNewManifest->createElement('extends', $sToExtends);

        // We test if mother template already extends another template
        if(!empty($oMetadatas->getElementsByTagName('extends')->item(0))){
            $oMetadatas->replaceChild($oExtendsNode, $oMetadatas->getElementsByTagName('extends')->item(0));
        }else{
            $oMetadatas->appendChild($oExtendsNode);
        }

        $oNewManifest->save($sConfigPath."/config.xml");

        libxml_disable_entity_loader(true);
    }


    /**
     * Create a package for the asset manager.
     * The asset manager will push to tmp/assets/xyxyxy/ the whole template directory (with css, js, files, etc.)
     * And it will publish the CSS and the JS defined in config.xml. So CSS can use relative path for pictures.
     * The publication of the package itself is in LSETwigViewRenderer::renderTemplateFromString()
     *
     * @param $oTemplate TemplateManifest
     */
    private function createTemplatePackage($oTemplate)
    {
        // Each template in the inheritance tree needs a specific alias
        $sPathName  = 'survey.template-'.$oTemplate->sTemplateName.'.path';
        $sViewName  = 'survey.template-'.$oTemplate->sTemplateName.'.viewpath';

        Yii::setPathOfAlias($sPathName, $oTemplate->path);
        Yii::setPathOfAlias($sViewName, $oTemplate->viewPath);

        $aCssFiles = $aJsFiles = array();

        // First we add the framework replacement (bootstrap.css must be loaded before template.css)
        $aCssFiles = $this->getFrameworkAssetsToReplace('css');
        $aJsFiles  = $this->getFrameworkAssetsToReplace('js');

        // Then we add the template config files
        $aTCssFiles   = isset($oTemplate->config->files->css->filename)?(array) $oTemplate->config->files->css->filename:array();        // The CSS files of this template
        $aTJsFiles    = isset($oTemplate->config->files->js->filename)? (array) $oTemplate->config->files->js->filename:array();         // The JS files of this template

        $aCssFiles    = array_merge($aCssFiles, $aTCssFiles);
        $aTJsFiles    = array_merge($aCssFiles, $aTJsFiles);

        $dir         = getLanguageRTL(App()->language) ? 'rtl' : 'ltr';

        // Remove/Replace mother template files
        $aCssFiles = $this->changeMotherConfiguration('css', $aCssFiles);
        $aJsFiles  = $this->changeMotherConfiguration('js',  $aJsFiles);

        // Then we add the direction files if they exist
        if (isset($oTemplate->config->files->$dir)) {
            $aCssFilesDir = isset($oTemplate->config->files->$dir->css->filename) ? (array) $oTemplate->config->files->$dir->css->filename : array();
            $aJsFilesDir  = isset($oTemplate->config->files->$dir->js->filename)  ? (array) $oTemplate->config->files->$dir->js->filename : array();
            $aCssFiles    = array_merge($aCssFiles,$aCssFilesDir);
            $aJsFiles     = array_merge($aJsFiles,$aJsFilesDir);
        }

        if (Yii::app()->getConfig('debug') == 0) {
            Yii::app()->clientScript->registerScriptFile( Yii::app()->getConfig("generalscripts"). 'deactivatedebug.js', CClientScript::POS_END);
        }

        $this->sPackageName = 'survey-template-'.$this->sTemplateName;
        $sTemplateurl       = $oTemplate->getTemplateURL();

        // The package "survey-template-{sTemplateName}" will be available from anywhere in the app now.
        // To publish it : Yii::app()->clientScript->registerPackage( 'survey-template-{sTemplateName}' );
        // Depending on settings, it will create the asset directory, and publish the css and js files
        Yii::app()->clientScript->addPackage( $this->sPackageName, array(
            'devBaseUrl'  => $sTemplateurl,                                     // Used when asset manager is off
            'basePath'    => $sPathName,                                        // Used when asset manager is on
            'css'         => $aCssFiles,
            'js'          => $aJsFiles,
            'depends'     => $oTemplate->depends,
        ) );
    }

    /**
     * Change the mother template configuration depending on template settings
     * @param $sType     string   the type of settings to change (css or js)
     * @param $aSettings array    array of local setting
     * @return array
     */
    private function changeMotherConfiguration( $sType, $aSettings )
    {
        foreach( $aSettings as $key => $aSetting){
            if (!empty($aSetting['replace']) || !empty($aSetting['remove'])){
                Yii::app()->clientScript->removeFileFromPackage($this->oMotherTemplate->sPackageName, $sType, $aSetting['replace'] );
                unset($aSettings[$key]);
            }
        }

        return $aSettings;
    }

    /**
     * Read the config.xml file of the template and push its contents to $this->config
     */
    private function readManifest()
    {
        $this->xmlFile         = $this->path.DIRECTORY_SEPARATOR.'config.xml';
        $bOldEntityLoaderState = libxml_disable_entity_loader(true);            // @see: http://phpsecurity.readthedocs.io/en/latest/Injection-Attacks.html#xml-external-entity-injection
        $sXMLConfigFile        = file_get_contents( realpath ($this->xmlFile)); // @see: Now that entity loader is disabled, we can't use simplexml_load_file; so we must read the file with file_get_contents and convert it as a string
        $this->config          = simplexml_load_string($sXMLConfigFile);        // Using PHP >= 5.4 then no need to decode encode + need attributes : then other function if needed :https://secure.php.net/manual/en/book.simplexml.php#108688 for example

        libxml_disable_entity_loader($bOldEntityLoaderState);                   // Put back entity loader to its original state, to avoid contagion to other applications on the server
    }

    /**
     * Configure the mother template (and its mother templates)
     * This is an object recursive call to TemplateManifest::setTemplateConfiguration()
     */
    private function setMotherTemplates()
    {
        if (isset($this->config->metadatas->extends)){
            $sMotherTemplateName   = (string) $this->config->metadatas->extends;
            $this->oMotherTemplate = new TemplateManifest;
            $this->oMotherTemplate->setTemplateConfiguration($sMotherTemplateName); // Object Recursion
        }
    }

    /**
     * Set the path of the current template
     * It checks if it's a core or a user template, if it exists, and if it has a config file
     */
    private function setPath()
    {
        // If the template is standard, its root is based on standardtemplaterootdir, else, it is a user template, its root is based on usertemplaterootdir
        $this->path = ($this->isStandard)?Yii::app()->getConfig("standardtemplaterootdir").DIRECTORY_SEPARATOR.$this->sTemplateName:Yii::app()->getConfig("usertemplaterootdir").DIRECTORY_SEPARATOR.$this->sTemplateName;

        // If the template directory doesn't exist, we just set Default as the template to use
        // TODO: create a method "setToDefault"
        if (!is_dir($this->path)) {
            $this->sTemplateName = 'default';
            $this->isStandard    = true;
            $this->path = Yii::app()->getConfig("standardtemplaterootdir").DIRECTORY_SEPARATOR.$this->sTemplateName;
            if(!$this->iSurveyId){
                setGlobalSetting('defaulttemplate', 'default');
            }
        }

        // If the template doesn't have a config file (maybe it has been deleted, or whatever),
        // then, we load the default template
        $this->hasConfigFile = (string) is_file($this->path.DIRECTORY_SEPARATOR.'config.xml');
        if (!$this->hasConfigFile) {
            $this->path = Yii::app()->getConfig("standardtemplaterootdir").DIRECTORY_SEPARATOR.$this->sTemplateName;

        }
    }

    /**
     * Set the template name.
     * If no templateName provided, then a survey id should be given (it will then load the template related to the survey)
     *
     * @var     $sTemplateName  string the name of the template
     * @var     $iSurveyId      int    the id of the survey
      */
    private function setTemplateName($sTemplateName='', $iSurveyId='')
    {
        // If it is called from the template editor, a template name will be provided.
        // If it is called for survey taking, a survey id will be provided
        if ($sTemplateName == '' && $iSurveyId == '') {
            /* Some controller didn't test completely survey id (PrintAnswersController for example), then set to default here */
            $sTemplateName = Template::templateNameFilter(Yii::app()->getConfig('defaulttemplate','default'));
        }

        $this->sTemplateName = $sTemplateName;
        $this->iSurveyId     = (int) $iSurveyId;

        if ($sTemplateName == '') {
            $oSurvey       = Survey::model()->findByPk($iSurveyId);

            if($oSurvey) {
                $this->sTemplateName = $oSurvey->template;
            } else {
                $this->sTemplateName = Template::templateNameFilter(App()->getConfig('defaulttemplate','default'));
            }
        }
    }

    /**
     * Set the default configuration values for the template, and use the motherTemplate value if needed
     */
    private function setThisTemplate()
    {
        // Mandtory setting in config XML (can be not set in inheritance tree, but must be set in mother template (void value is still a setting))
        $this->apiVersion               = (isset($this->config->metadatas->apiVersion))            ? $this->config->metadatas->apiVersion                                                       : $this->oMotherTemplate->apiVersion;
        $this->viewPath                 = (!empty($this->config->xpath("//viewdirectory")))   ? $this->path.DIRECTORY_SEPARATOR.$this->config->engine->viewdirectory.DIRECTORY_SEPARATOR    : $this->path.DIRECTORY_SEPARATOR.$this->oMotherTemplate->config->engine->viewdirectory.DIRECTORY_SEPARATOR;
        $this->filesPath                = (!empty($this->config->xpath("//filesdirectory")))  ? $this->path.DIRECTORY_SEPARATOR.$this->config->engine->filesdirectory.DIRECTORY_SEPARATOR   :  $this->path.DIRECTORY_SEPARATOR.$this->oMotherTemplate->config->engine->filesdirectory.DIRECTORY_SEPARATOR;
        $this->sFilesDirectory          = (!empty($this->config->xpath("//filesdirectory")))  ? $this->config->engine->filesdirectory   :  $this->oMotherTemplate->sFilesDirectory;
        $this->templateEditor           = (!empty($this->config->xpath("//template_editor"))) ? $this->config->engine->template_editor : $this->oMotherTemplate->templateEditor;

        // Options are optional
        if (!empty($this->config->xpath("//options"))){
            $this->oOptions = $this->config->xpath("//options");
        }elseif(!empty($this->oMotherTemplate->oOptions)){
            $this->oOptions = $this->oMotherTemplate->oOptions;
        }else{
            $this->oOptions = "";
        }

        // Not mandatory (use package dependances)
        $this->cssFramework             = (!empty($this->config->xpath("//cssframework")))    ? $this->config->engine->cssframework                                                                                  : '';
        $this->packages                 = (!empty($this->config->xpath("//packages")))        ? $this->config->engine->packages                                                                                      : array();

        // Add depend package according to packages
        $this->depends                  = array_merge($this->depends, $this->getDependsPackages($this));
        //var_dump($this->depends); die();
    }


    /**
     * @return bool
     */
    private function setIsStandard()
    {
        $this->isStandard = Template::isStandardTemplate($this->sTemplateName);
    }


    /**
     * Get the depends package
     * @uses self::@package
     * @return string[]
     */
    private function getDependsPackages($oTemplate)
    {
        $dir = (getLanguageRTL(App()->getLanguage()))?'rtl':'ltr';

        /* Core package */
        $packages[] = 'limesurvey-public';
        $packages[] = 'template-core';
        $packages[] = ( $dir == "ltr")? 'template-core-ltr' : 'template-core-rtl'; // Awesome Bootstrap Checkboxes

        /* bootstrap */
        if(!empty($this->cssFramework)){

            // Basic bootstrap package
            if((string)$this->cssFramework->name == "bootstrap"){
                $packages[] = 'bootstrap';
            }

            // Rtl version of bootstrap
            if ($dir == "rtl"){
                $packages[] = 'bootstrap-rtl';
            }

            // Remove unwanted bootstrap stuff
            foreach( $this->getFrameworkAssetsToReplace('css', true) as $toReplace){
                Yii::app()->clientScript->removeFileFromPackage('bootstrap', 'css', $toReplace );
            }

            foreach( $this->getFrameworkAssetsToReplace('js', true) as $toReplace){
                Yii::app()->clientScript->removeFileFromPackage('bootstrap', 'js', $toReplace );
            }
        }

        /* Moter Template */
        if (isset($this->config->metadatas->extends)){
            $sMotherTemplateName = (string) $this->config->metadatas->extends;
            $packages[]          = 'survey-template-'.$sMotherTemplateName;
        }

        return $packages;
    }

    /**
     * Get the list of file replacement from Engine Framework
     * @param string  $sType            css|js the type of file
     * @param boolean $bInlcudeRemove   also get the files to remove
     * @return array
     */
    private function getFrameworkAssetsToReplace( $sType, $bInlcudeRemove = false)
    {
        $aAssetsToRemove = array();
        if (!empty($this->cssFramework->$sType)){
            $aAssetsToRemove = array_merge( (array) $this->cssFramework->$sType->attributes()->replace );
            if($bInlcudeRemove){
                $aAssetsToRemove = array_merge($aAssetsToRemove, (array) $this->cssFramework->$sType->attributes()->remove );
            }
        }
        return $aAssetsToRemove;
    }

    /**
     * Get the file path for a given template.
     * It will check if css/js (relative to path), or view (view path)
     * It will search for current template and mother templates
     *
     * @param   string  $sFile          relative path to the file
     * @param   string  $oTemplate      the template where to look for (and its mother templates)
     */
    private function getFilePath($sFile, $oTemplate)
    {
        // Remove relative path
        $sFile = trim($sFile, '.');
        $sFile = trim($sFile, '/');

        // Retreive the correct template for this file (can be a mother template)
        $oTemplate = $this->getTemplateForFile($sFile, $oTemplate);

        if($oTemplate instanceof TemplateConfiguration){
            if(file_exists($oTemplate->path.'/'.$sFile)){
                return $oTemplate->path.'/'.$sFile;
            }elseif(file_exists($oTemplate->viewPath.$sFile)){
                return $oTemplate->viewPath.$sFile;
            }
        }
        return false;
    }

}
