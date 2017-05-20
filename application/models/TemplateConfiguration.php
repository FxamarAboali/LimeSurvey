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
class TemplateConfiguration extends CFormModel
{
    /** @var TemplateConfiguration $oMotherTemplate The template name */
    public $oMotherTemplate;

    /** @var string $sTemplateName The template name */
    public $sTemplateName='';
    /** @var string $iSurveyId The current Survey Id. It can be void. It's use only to retreive the current template of a given survey */
    public $iSurveyId='';
    /** @var SimpleXMLElement $config Will contain the config.xml*/
    public $config;
    /**
     * @var integer $apiVersion: Version of the LS API when created. Must be private : disallow update
     */
    private $apiVersion;

    /** @var  string $viewPath Path of the views files (php files to replace existing core views) */
    public $viewPath;
    /** @var  string $siteLogo Name of the logo file (like: logo.png) */
    public $siteLogo;
    /** @var  string $filesPath Path of the uploaded files */
    public $filesPath;
    /**
     * @var string[] $cssFramework What framework css is used
     * @see getFrameworkPackages()
     */
    public $cssFramework;
    /** @var stdClass[] $packages Array of package dependencies defined in config.xml*/
    public $packages;
    /**
     * @var string[] $depends List of all dependencies (could be more that just the config.xml packages)
     * @see getDependsPackages()
     */
    public $depends = array();
    /**
     * @var array $otherFiles Array of files in the file directory
     * @see setOtherFiles()
     */
    public $otherFiles;

    public $sTemplateurl;

    /** @var  Survey $oSurvey The survey object */
    public $oSurvey;
    /** @var boolean $isStandard Is this template a core one? */
    public $isStandard;
    /** @var  string $path Path of this template */
    public $path;
    /**
     * @var string $hasConfigFile Does it has a config.xml file?
     * //TODO why string not boolean ??
     */
    public $hasConfigFile='';//

    /** @var bool $overwrite_question_views Does it overwrites the question rendering from quanda.php? Must have a valid viewPath too. */
    public $overwrite_question_views=false;
    /** @var string $xmlFile What xml config file does it use? (config/minimal) */
    public $xmlFile;

    /**
     * This method constructs a template object, having all the needed configuration data.
     * It checks if the required template is a core one or a user one.
     * If it is a user template, it will check if it is an old 2.0x template to provide default configuration values corresponding to the old template system
     * If it is not an old template, it will check if it has a configuration file to load its datas.
     * If it is not the case (template probably doesn't exist), it will load the default template configuration
     * TODO : more tests should be done, with a call to private function _is_valid_template(), testing not only if it has a config.xml, but also if this file is correct, if it has the needed layout files, if the files refered in css exist, etc.
     *
     * @param string $sTemplateName the name of the template to load. The string comes from the template selector in survey settings
     * @param string $iSurveyId the id of the survey. If
     * @return $this
     */
    public function setTemplateConfiguration($sTemplateName='', $iSurveyId='')
    {
        // If it is called from the template editor, a template name will be provided.
        // If it is called for survey taking, a survey id will be provided
        if ($sTemplateName == '' && $iSurveyId == '') {
            /* Some controller didn't test completely survey id (PrintAnswersController for example), then set to default here */
            $sTemplateName=Template::templateNameFilter(Yii::app()->getConfig('defaulttemplate','default'));
            //throw new TemplateException("Template needs either template name or survey id");
        }
        $this->sTemplateName = $sTemplateName;
        $this->iSurveyId     = (int) $iSurveyId;

        if ($sTemplateName=='') {
            $this->oSurvey       = Survey::model()->findByPk($iSurveyId);
            if($this->oSurvey) {
                $this->sTemplateName = $this->oSurvey->template;
            } else {
                $this->sTemplateName = Template::templateNameFilter(App()->getConfig('defaulttemplate','default'));
            }
        }
        // We check if  it is a CORE template
        $this->isStandard = $this->setIsStandard();
        // If the template is standard, its root is based on standardtemplaterootdir, else, it is a user template, its root is based on usertemplaterootdir
        $this->path = ($this->isStandard)?Yii::app()->getConfig("standardtemplaterootdir").DIRECTORY_SEPARATOR.$this->sTemplateName:Yii::app()->getConfig("usertemplaterootdir").DIRECTORY_SEPARATOR.$this->sTemplateName;

        // If the template directory doesn't exist, it can be that:
        // - user deleted a custom theme
        // In any case, we just set Default as the template to use
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

        $this->xmlFile = $this->path.DIRECTORY_SEPARATOR.'config.xml';

        //////////////////////
        // Config file loading

        $bOldEntityLoaderState = libxml_disable_entity_loader(true);             // @see: http://phpsecurity.readthedocs.io/en/latest/Injection-Attacks.html#xml-external-entity-injection
        $sXMLConfigFile        = file_get_contents( realpath ($this->xmlFile));  // @see: Now that entity loader is disabled, we can't use simplexml_load_file; so we must read the file with file_get_contents and convert it as a string

        // Using PHP >= 5.4 then no need to decode encode + need attributes : then other function if needed :https://secure.php.net/manual/en/book.simplexml.php#108688 for example
        $this->config  = simplexml_load_string($sXMLConfigFile);

        // Recursive mother templates configuration
        if (isset($this->config->metadatas->extends)){
            $sMotherTemplateName = (string) $this->config->metadatas->extends;
            $this->oMotherTemplate = new TemplateConfiguration;

            // Object Recursion
            $this->oMotherTemplate->setTemplateConfiguration($sMotherTemplateName);

            $oTemplateToConfigure = $this->oMotherTemplate;
            $this->setThisTemplate($oTemplateToConfigure);

            $this->filesPath                = $this->oMotherTemplate->filesPath; // View override will be managed file by file from twig rendering...
            $this->viewPath                 = $this->oMotherTemplate->viewPath; // File override will be managed file by file from twig rendering...
            $this->depends                  = $this->getDependsPackages($this->oMotherTemplate);

        }else{
            $oTemplateToConfigure = $this;
            $this->setThisTemplate($oTemplateToConfigure);
        }

        $this->depends                  = array_merge($this->depends, $this->getDependsPackages($this));

        /* Add depend package according to packages */
        $this->otherFiles               = $this->setOtherFiles();

        libxml_disable_entity_loader($bOldEntityLoaderState);                   // Put back entity loader to its original state, to avoid contagion to other applications on the server
        return $this;
    }

    private function setThisTemplate($oTemplateToConfigure)
    {
        $this->apiVersion               = (isset($oTemplateToConfigure->config->metadatas->apiVersion))            ? $oTemplateToConfigure->config->metadatas->apiVersion:0;
        $this->viewPath                 = (isset($oTemplateToConfigure->config->engine->viewdirectory))            ? $oTemplateToConfigure->path.DIRECTORY_SEPARATOR.$oTemplateToConfigure->config->engine->viewdirectory.DIRECTORY_SEPARATOR                            : '';
        $this->siteLogo                 = (isset($oTemplateToConfigure->config->files->logo))                      ? $oTemplateToConfigure->config->files->logo->filename                                                                                 : '';
        $this->filesPath                = (isset($oTemplateToConfigure->config->engine->filesdirectory))           ? $oTemplateToConfigure->path.DIRECTORY_SEPARATOR.$oTemplateToConfigure->config->engine->filesdirectory.DIRECTORY_SEPARATOR                            : $oTemplateToConfigure->path . '/files/';
        $this->cssFramework             = (isset($oTemplateToConfigure->config->engine->cssframework))             ? $oTemplateToConfigure->config->engine->cssframework                                                                                  : '';
        $this->cssFramework->name       = (isset($oTemplateToConfigure->config->engine->cssframework->name))       ? $oTemplateToConfigure->config->engine->cssframework->name                                                                            : (string) $oTemplateToConfigure->config->engine->cssframework;
        $this->packages                 = (isset($oTemplateToConfigure->config->engine->packages))                 ? $oTemplateToConfigure->config->engine->packages                                                                                      : array();
        // Package creation
        $this->createTemplatePackage($oTemplateToConfigure);
    }

    /**
     * Update the configuration file "last update" node.
     * For now, it is called only from template editor
     */
    public function actualizeLastUpdate()
    {
        $date = date("Y-m-d H:i:s");
        $config = simplexml_load_file(realpath ($this->xmlFile));
        $config->metadatas->last_update = $date;
        $config->asXML( realpath ($this->xmlFile) );                // Belt
        touch ( $this->path );                                      // & Suspenders ;-)
    }

    public function registerAssets()
    {
        $sPackageName  = 'survey-template-'.$this->sTemplateName;
        if(!YII_DEBUG ||  Yii::app()->getConfig('use_asset_manager')){
            Yii::app()->clientScript->registerPackage( $sPackageName );
        }else{
            $aDepends = $this->getRecursiveDependencies($sPackageName);

            // CONVERT ALL PACKAGE IN $aDepend to BASE URL instead of PATH
            foreach($aDepends as $package){

                $aOldPackageDefinition = Yii::app()->clientScript->packages[$package];

                // This will overwrite the package definition using a base url instead of a base path
                if( array_key_exists('devBaseUrl', $aOldPackageDefinition ) ){
                    Yii::app()->clientScript->addPackage( $package, array(
                        'baseUrl'   => $aOldPackageDefinition['devBaseUrl'],                                 // Don't use asset manager
                        'css'       => array_key_exists('css', $aOldPackageDefinition)?$aOldPackageDefinition['css']:array(),
                        'js'        => array_key_exists('js', $aOldPackageDefinition)?$aOldPackageDefinition['js']:array(),
                        'depends'   => array_key_exists('depends', $aOldPackageDefinition)?$aOldPackageDefinition['depends']:array(),
                    ) );
                }
            }

            Yii::app()->clientScript->registerPackage( $sPackageName );
        }


    }

    /**
     * Return a list of all the recursive dependencies of a packages
     * eg: If a package A depends on B, and B depends on C, getRecursiveDependencies('A') will return {B,C}
     */
    public function getRecursiveDependencies($sPackageName)
    {
        $aPackages     = Yii::app()->clientScript->packages;
        if ( array_key_exists('depends', $aPackages[$sPackageName]) ){
            $aDependencies = $aPackages[$sPackageName]['depends'];

            foreach ($aDependencies as $sDpackageName){
                if($aPackages[$sPackageName]['depends']){
                    $aRDependencies = $this->getRecursiveDependencies($sDpackageName);                  // Recursive call
                    if (is_array($aRDependencies)){
                        $aDependencies = array_unique(array_merge($aDependencies, $aRDependencies));
                    }
                }
            }
            return $aDependencies;
        }
    }

    /**
     * Create a package for the asset manager.
     * The asset manager will push to tmp/assets/xyxyxy/ the whole template directory (with css, js, files, etc.)
     * And it will publish the CSS and the JS defined in config.xml. So CSS can use relative path for pictures.
     * The publication of the package itself is done for now in replacements_helper, to respect the old logic of {TEMPLATECSS} replacement keyword
     *
     * NOTE 1 : To refresh the assets, the base directory of the template must be updated.
     *
     * NOTE 2: By default, Asset Manager is off when debug mode is on.
     * Developers should then think about :
     * 1. refreshing their brower's cache (ctrl + F5) to see their changes
     * 2. update the config.xml last_update before pushing, to be sure that end users will have the new version
     *
     *
     * For more detail, see :
     *  http://www.yiiframework.com/doc/api/1.1/CClientScript#addPackage-detail
     *  http://www.yiiframework.com/doc/api/1.1/YiiBase#setPathOfAlias-detail
     *
     */
    public function createTemplatePackage($oTemplate)
    {
        Yii::setPathOfAlias('survey.template.path',     $oTemplate->path);                                   // The package creation/publication need an alias
        Yii::setPathOfAlias('survey.template.viewpath', $oTemplate->viewPath);

        $aCssFiles   = (array)$oTemplate->config->files->css->filename;                                 // The CSS files of this template
        $aJsFiles    = (array)$oTemplate->config->files->js->filename;                                  // The JS files of this template
        $dir         = getLanguageRTL(App()->language) ? 'rtl' : 'ltr';

        foreach($aCssFiles as $key => $cssFile){
            if (!empty($cssFile['replace'])){
                //unset($aCssFiles[$cssFile['replace']]);

                // TODO: Remove the file from mother package, and add it to current package

                unset($aCssFiles[$key]);


            }
        }

        if (isset($oTemplate->config->files->$dir)) {
            $aCssFilesDir = isset($oTemplate->config->files->$dir->css->filename) ? (array)$oTemplate->config->files->$dir->css->filename : array();
            $aJsFilesDir  = isset($oTemplate->config->files->$dir->js->filename) ?  (array)$oTemplate->config->files->$dir->js->filename : array();
            $aCssFiles    = array_merge($aCssFiles,$aCssFilesDir);
            $aJsFiles     = array_merge($aJsFiles,$aJsFilesDir);
        }

        if (Yii::app()->getConfig('debug') == 0) {
            Yii::app()->clientScript->registerScriptFile( Yii::app()->getConfig("generalscripts"). 'deactivatedebug.js', CClientScript::POS_END);
        }

        $sPackageName = 'survey-template-'.$this->sTemplateName;

        // The package "survey-template" will be available from anywhere in the app now.
        // To publish it : Yii::app()->clientScript->registerPackage( 'survey-template' );
        // It will create the asset directory, and publish the css and js files
        if(!YII_DEBUG ||  Yii::app()->getConfig('use_asset_manager')){
            Yii::app()->clientScript->addPackage( $sPackageName, array(
                'basePath'    => 'survey.template.path',                        // Use asset manager
                'css'         => $aCssFiles,
                'js'          => $aJsFiles,
                'depends'     => $oTemplate->depends,
            ) );
        }else{
            $sTemplateurl = $oTemplate->getTemplateURL();
            Yii::app()->clientScript->addPackage( $sPackageName, array(
                'baseUrl'    =>  $sTemplateurl,                                 // Don't use asset manager
                'css'         => $aCssFiles,
                'js'          => $aJsFiles,
                'depends'     => $oTemplate->depends,
            ) );
        }
    }


    /**
     * Return the list of ALL files present in the file directory
     * @return array
     */
    private function setOtherFiles()
    {
        $otherFiles = array();
        if ( file_exists($this->filesPath) && $handle = opendir($this->filesPath)) {
            while (false !== ($file = readdir($handle))) {
                if($file!='.' && $file!='..') {
                    if (!is_dir($file)) {
                        $otherFiles[] = array("name" => $file);
                    }
                }
            }
            closedir($handle);
        }
        return $otherFiles;
    }

    public function getName()
    {
        return $this->sTemplateName;
    }


    /**
     * @return bool
     */
    private function setIsStandard()
    {
        return Template::isStandardTemplate($this->sTemplateName);
    }

    /**
    * This function returns the complete URL path to a given template name
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
     * Get the depends package
     * @uses self::@package
     * @return string[]
     */
    private function getDependsPackages($oTemplate)
    {

        /* Start by adding cssFramework package */
        $packages=$this->getFrameworkPackages($oTemplate);

        if(!getLanguageRTL(App()->getLanguage())) {
            $packages=array_merge ($packages,$this->getFrameworkPackages($oTemplate, 'ltr'));
        } else {
            $packages=array_merge ($packages,$this->getFrameworkPackages($oTemplate, 'rtl'));
        }

        /* Core package */
        $packages[]='limesurvey-public';

        /* template packages */
        if(!empty($this->packages->package)) {
            $packages=array_merge ($packages,(array)$this->packages->package);
        }
        /* Adding rtl/tl specific package (see https://bugs.limesurvey.org/view.php?id=11970#c42317 ) */
        $dir=getLanguageRTL(App()->language) ? 'rtl' : 'ltr';
        if(!empty($this->packages->$dir->package)) {
            $packages=array_merge ($packages,(array)$this->packages->$dir->package);
        }

        if (isset($this->config->metadatas->extends)){
            $sMotherTemplateName = (string) $this->config->metadatas->extends;
            $packages[]          = 'survey-template-'.$sMotherTemplateName;
        }

        return $packages;
    }

    /**
     * Set the framework package
     * @param string $dir (rtl|ltr|)
     * @use self::@cssFramework
     * @return string[] depends for framework
     */
    private function getFrameworkPackages($oTemplate, $dir="")
    {
        $framework=isset($oTemplate->cssFramework->name)? (string)$oTemplate->cssFramework->name : (string)$oTemplate->cssFramework;
        $framework=$dir ? $framework."-".$dir : $framework;
        if(isset(Yii::app()->clientScript->packages[$framework])) {
            $frameworkPackages=array();
            /* Theming */
            if($dir) {
                $cssFrameworkCsss=isset($oTemplate->cssFramework->$dir->css) ? $oTemplate->cssFramework->$dir->css : array();
                $cssFrameworkJss=isset($oTemplate->cssFramework->$dir->js) ? $oTemplate->cssFramework->$dir->js : array();
            } else {
                $cssFrameworkCsss=isset($oTemplate->cssFramework->css) ? $oTemplate->cssFramework->css : array();
                $cssFrameworkJss=isset($oTemplate->cssFramework->js) ? $oTemplate->cssFramework->js : array();
            }
            if(empty($cssFrameworkCsss) && empty($cssFrameworkJss)) {
                $frameworkPackages[]=$framework;
            } else {
                /* Need to create an adapted core framework */
                $cssFrameworkPackage=Yii::app()->clientScript->packages[$framework];
                /* Need to create an adapted template/theme framework */
                $packageCss=array();
                $packageJs=array();
                /* css file to replace from default package */
                $cssDelete=array();
                foreach($cssFrameworkCsss as $cssFrameworkCss) {
                    if(isset($cssFrameworkCss['replace'])) {
                        $cssDelete[]=$cssFrameworkCss['replace'];
                    }
                    if((string)$cssFrameworkCss) {
                        $packageCss[]=(string)$cssFrameworkCss;
                    }
                }
                if(isset($cssFrameworkPackage['css'])) {
                    $cssFrameworkPackage['css']=array_diff($cssFrameworkPackage['css'],$cssDelete);
                }
                $jsDelete=array();
                foreach($cssFrameworkJss as $cssFrameworkJs) {
                    if(isset($cssFrameworkJs['replace'])) {
                        $jsDelete[]=$cssFrameworkJs['replace'];
                    }
                    if((string)$cssFrameworkJs) {
                        $packageJs[]=(string)$cssFrameworkJs;
                    }
                }
                if(isset($cssFrameworkPackage['js'])) {
                    $cssFrameworkPackage['js']=array_diff($cssFrameworkPackage['js'],$cssDelete);
                }
                /* And now : we add : core package fixed + template/theme package */
                Yii::app()->clientScript->packages[$framework]=$cssFrameworkPackage; /* @todo : test if empty css and js : just add depends if yes */
                $aDepends=array(
                    $framework,
                );

                if (!YII_DEBUG ||  Yii::app()->getConfig('use_asset_manager')){
                    Yii::app()->clientScript->addPackage(
                        $framework.'-template', array(
                            'basePath'    => 'survey.template.path',      // basePath: the asset manager will be used
                            'css'         => $packageCss,
                            'js'          => $packageJs,
                            'depends'     => $aDepends,
                        )
                    );
                }else{
                    $sTemplateurl = $oTemplate->getTemplateURL();
                    Yii::app()->clientScript->addPackage(
                        $framework.'-template', array(
                            'baseUrl'    =>  $sTemplateurl,            // Don't use asset manager
                            'css'         => $packageCss,
                            'js'          => $packageJs,
                            'depends'     => $aDepends,
                        )
                    );
                }
                $frameworkPackages[]=$framework.'-template';
            }
            return $frameworkPackages;
        }/*elseif($framework){
            throw error ? Only for admin template editor ? disable and reset to default ?
        }*/
        return array();
    }

    /**
     * get the template API version
     * @return integer
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }
}
