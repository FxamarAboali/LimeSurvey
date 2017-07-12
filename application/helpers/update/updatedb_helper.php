<?PHP
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

// There will be a file for each database (accordingly named to the dbADO scheme)
// where based on the current database version the database is upgraded
// For this there will be a settings table which holds the last time the database was upgraded

/**
 * @param integer $iOldDBVersion The previous database version
 * @param boolean $bSilent Run update silently with no output - this checks if the update can be run silently at all. If not it will not run any updates at all.
 */
function db_upgrade_all($iOldDBVersion, $bSilent=false) {
    /**
    * If you add a new database version add any critical database version numbers to this array. See link
    * @link https://manual.limesurvey.org/Database_versioning for explanations
    * @var array $aCriticalDBVersions An array of cricital database version.
    */
    $aCriticalDBVersions = array();
    $aAllUpdates         = range($iOldDBVersion+1,Yii::app()->getConfig('dbversionnumber'));

    // If trying to update silenty check if it is really possible
    if ($bSilent && (count(array_intersect($aCriticalDBVersions,$aAllUpdates))>0)){
        return false;
    }

    /// This function does anything necessary to upgrade
    /// older versions to match current functionality
    global $modifyoutput;

    Yii::app()->loadHelper('database');
    $sUserTemplateRootDir       = Yii::app()->getConfig('usertemplaterootdir');
    $sStandardTemplateRootDir   = Yii::app()->getConfig('standardtemplaterootdir');
    $oDB                        = Yii::app()->getDb();
    $oDB->schemaCachingDuration = 0; // Deactivate schema caching
    Yii::app()->setConfig('Updating',true);

    try{
        /**
         * Add table for notifications
         * @since 2016-08-04
         * @author Olle Haerstedt
         */
        if ($iOldDBVersion < 259) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->createTable('{{notifications}}', array(
                'id' => 'pk',
                'entity' => 'string(15) not null',
                'entity_id' => 'integer not null',
                'title' => 'string not null',  // varchar(255) in postgres
                'message' => 'text not null',
                'status' => "string(15) not null default 'new' ",
                'importance' => 'integer default 1',
                'display_class' => "string(31) default 'default'",
                'created' => 'datetime not null',
                'first_read' => 'datetime null'
            ));
            $oDB->createCommand()->createIndex('notif_index', '{{notifications}}', 'entity, entity_id, status', false);
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>259),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 260) {
            $oTransaction = $oDB->beginTransaction();
            alterColumn('{{participant_attribute_names}}','defaultname',"string(255)",false);
            alterColumn('{{participant_attribute_names_lang}}','attribute_name',"string(255)",false);
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>260),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 261) {
            $oTransaction = $oDB->beginTransaction();
            /*
             * The hash value of a notification is used to calculate uniqueness.
             * @since 2016-08-10
             * @author Olle Haerstedt
             */
            addColumn('{{notifications}}', 'hash', 'string(64)');
            $oDB->createCommand()->createIndex('notif_hash_index', '{{notifications}}', 'hash', false);
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>261),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 262) {
            $oTransaction = $oDB->beginTransaction();
            alterColumn('{{settings_global}}','stg_value',"text",false);
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>262),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 263) {
            $oTransaction = $oDB->beginTransaction();
            // Dummy version update for hash column in installation SQL.
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>263),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Add seed column in all active survey tables
         * Might take time to execute
         * @since 2016-09-01
         */
        if ($iOldDBVersion < 290) {

            $oTransaction = $oDB->beginTransaction();

            // Loop all surveys
            $surveys = Survey::model()->findAll();
            foreach ($surveys as $survey)
            {
                $prefix = Yii::app()->getDb()->tablePrefix;
                $tableName = $prefix . 'survey_' . $survey->sid;

                // If survey has active table, create seed column
                $table = Yii::app()->db->schema->getTable($tableName);
                if ($table)
                {
                    if (!isset($table->columns['seed']))
                    {
                        Yii::app()->db->createCommand()->addColumn($tableName, 'seed', 'string(31)');
                    }
                    else
                    {
                        continue;
                    }

                    // RAND is RANDOM in Postgres
                    switch (Yii::app()->db->driverName)
                    {
                        case 'pgsql':
                            Yii::app()->db->createCommand("UPDATE $tableName SET seed = ROUND(RANDOM() * 10000000)")->execute();
                            break;
                        default:
                            Yii::app()->db->createCommand("UPDATE $tableName SET seed = ROUND(RAND() * 10000000, 0)")->execute();
                            break;
                    }
                }
            }
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>290),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Plugin JSON config file
         * @since 2016-08-22
         */
        if ($iOldDBVersion < 291)
        {
            $oTransaction = $oDB->beginTransaction();

            addColumn('{{plugins}}', 'version', 'string(32)');

            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>291),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * User settings table
         * @since 2016-08-29
         */
        if ($iOldDBVersion < 292) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->createTable('{{settings_user}}', array(
                'uid' => 'integer NOT NULL',
                'entity' => 'string(15)',
                'entity_id' => 'string(31)',
                'stg_name' => 'string(63) not null',
                'stg_value' => 'text',
                'PRIMARY KEY (uid, entity, entity_id, stg_name)'
            ));
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>292),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Survey menue table
         * @since 2017-07-03
         */
        if ($iOldDBVersion < 293) {
            $oTransaction = $oDB->beginTransaction();
            createSurveyMenuTable293($oDB);
            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>293),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Survey menue table update
         * @since 2017-07-03
         */
        if ($iOldDBVersion < 294) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->addColumn('{{surveymenu}}', 'position', "string(255) DEFAULT 'side'");

            $oDB->createCommand()->truncateTable('{{surveymenu_entries}}');
            $colsToAdd = array("id","menu_id","order","name","title","menu_title","menu_description","menu_icon","menu_icon_type","menu_class","menu_link","action","template","partial","classes","permission","permission_grade","data","getdatamethod","language","changed_at","changed_by","created_at","created_by");
            $rowsToAdd = array(
                array(1,1,1,'overview','Survey overview','Overview','Open general survey overview and quick action','list','fontawesome','','admin/survey/sa/view','','','','','','',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(2,1,2,'generalsettings','Edit survey general settings','General settings','Open general survey settings','gears','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_generaloptions_panel','','surveysettings','read',NULL,'_generalTabEditSurvey','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(3,1,3,'surveytexts','Edit survey text elements','Survey texts','Edit survey text elements','file-text-o','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/tab_edit_view','','surveylocale','read',NULL,'_getTextEditData','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(4,1,4,'participants','Survey participants','Survey participants','Go to survey participant and token settings','user','fontawesome','','admin/tokens/sa/index/','','','','','surveysettings','update',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(5,1,4,'presentation','Presentation &amp; navigation settings','Presentation','Edit presentation and navigation settings','eye-slash','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_presentation_panel','','surveylocale','read',NULL,'_tabPresentationNavigation','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(6,1,5,'publication','Publication and access control settings','Publication &amp; access','Edit settings for publicationa and access control','key','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_publication_panel','','surveylocale','read',NULL,'_tabPublicationAccess','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(7,1,6,'surveypermissions','Edit surveypermissions','Survey permissions','Edit permissions for this survey','lock','fontawesome','','admin/surveypermission/sa/view/','','','','','surveysecurity','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(8,1,7,'tokens','Token handling','Participant tokens','Define how tokens should be treated or generated','users','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_tokens_panel','','surveylocale','read',NULL,'_tabTokens','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(9,1,8,'quotas','Edit quotas','Survey quotas','Edit quotas for this survey.','tasks','fontawesome','','admin/quotas/sa/index/','','','','','quotas','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(10,1,9,'assessments','Edit assessments','Assessments','Edit and look at the asessements for this survey.','comment-o','fontawesome','','admin/assessments/sa/index/','','','','','assessments','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(11,1,10,'notification','Notification and data management settings','Data management','Edit settings for notification and data management','feed','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_notification_panel','','surveylocale','read',NULL,'_tabNotificationDataManagement','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(12,1,11,'emailtemplates','Email templates','Email templates','Edit the templates for invitation, reminder and registration emails','envelope-square','fontawesome','','admin/emailtemplates/sa/index/','','','','','assessments','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(13,1,12,'panelintegration','Edit survey panel integration','Panel integration','Define panel integrations for your survey','link','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_integration_panel','','surveylocale','read',NULL,'_tabPanelIntegration','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
                array(14,1,13,'ressources','Add/Edit ressources to the survey','Ressources','Add/Edit ressources to the survey','file','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_resources_panel','','surveylocale','read',NULL,'_tabResourceManagement','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0)
            );
            foreach($rowsToAdd as $row){
                $oDB->createCommand()->insert('{{surveymenu_entries}}', array_combine($colsToAdd,$row));
            }

            $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>294),"stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Template tables
         * @since 2017-07-12
         */
        if ($iOldDBVersion < 295) {
            $oTransaction = $oDB->beginTransaction();
            upgradeTemplateTables295($oDB);
        }

    }
    catch(Exception $e)
    {
        Yii::app()->setConfig('Updating',false);
        $oTransaction->rollback();
        // Activate schema caching
        $oDB->schemaCachingDuration=3600;
        // Load all tables of the application in the schema
        $oDB->schema->getTables();
        // clear the cache of all loaded tables
        $oDB->schema->refresh();
        //echo '<br /><br />'.gT('An non-recoverable error happened during the update. Error details:')."<p>".htmlspecialchars($e->getMessage()).'</p><br />';
        Yii::app()->user->setFlash('error', gT('An non-recoverable error happened during the update. Error details:')."<p>".htmlspecialchars($e->getMessage()).'</p><br />');
        return false;
    }

    // Load all tables of the application in the schema
    $oDB->schema->getTables();

    // clear the cache of all loaded tables
    $oDB->schema->refresh();
    $oDB->active = false;
    $oDB->active = true;

    // Force User model to refresh meta data (for updates from very old versions)
    User::model()->refreshMetaData();

    // Inform  superadmin about update
    $superadmins = User::model()->getSuperAdmins();

    Notification::broadcast(array(
        'title' => gT('Database update'),
        'message' => sprintf(gT('The database has been updated from version %s to version %s.'), $iOldDBVersion, '295')
    ), $superadmins);

    fixLanguageConsistencyAllSurveys();

    Yii::app()->setConfig('Updating',false);
    return true;
}

function createSurveyMenuTable293($oDB) {
    $oDB->createCommand()->createTable('{{surveymenu}}', array(
        "id" => "int NOT NULL ",
        "parent_id" => "int DEFAULT NULL",
        "survey_id" => "int DEFAULT NULL",
        "order" => "int DEFAULT '0'",
        "level" => "int DEFAULT '0'",
        "title" => "character varying(255)  NOT NULL DEFAULT ''",
        "description" => "text ",
        "changed_at" => "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
        "changed_by" => "int NOT NULL DEFAULT '0'",
        "created_at" => "datetime DEFAULT NULL",
        "created_by" => "int NOT NULL DEFAULT '0'",
        'PRIMARY KEY (id)'
    ));

    $oDB->createCommand()->insert(
        '{{surveymenu}}',
        array(
            'id' => 1,
            'parent_id' => NULL,
            'survey_id' => NULL,
            'order' => 0,
            'level' => 0,
            'title' => 'surveymenu',
            'description' => 'Main survey menu',
            'changed_at' => date('Y-m-d H:i:s'),
            'changed_by' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 0
        )
    );

    $oDB->createCommand()->createTable('{{surveymenu_entries}}', array(
        "id" => "int NOT NULL ",
        "menu_id" => "int DEFAULT NULL",
        "order" => "int DEFAULT '0'",
        "name" => "character varying(255)  NOT NULL DEFAULT ''",
        "title" => "character varying(255)  NOT NULL DEFAULT ''",
        "menu_title" => "character varying(255)  NOT NULL DEFAULT ''",
        "menu_description" => "text ",
        "menu_icon" => "character varying(255)  NOT NULL DEFAULT ''",
        "menu_icon_type" => "character varying(255)  NOT NULL DEFAULT ''",
        "menu_class" => "character varying(255)  NOT NULL DEFAULT ''",
        "menu_link" => "character varying(255)  NOT NULL DEFAULT ''",
        "action" => "character varying(255)  NOT NULL DEFAULT ''",
        "template" => "character varying(255)  NOT NULL DEFAULT ''",
        "partial" => "character varying(255)  NOT NULL DEFAULT ''",
        "classes" => "character varying(255)  NOT NULL DEFAULT ''",
        "permission" => "character varying(255)  NOT NULL DEFAULT ''",
        "permission_grade" => "character varying(255)  DEFAULT NULL",
        "data" => "text ",
        "getdatamethod" => "character varying(255)  NOT NULL DEFAULT ''",
        "language" => "character varying(255)  NOT NULL DEFAULT 'en-GB'",
        "changed_at" => "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
        "changed_by" => "int NOT NULL DEFAULT '0'",
        "created_at" => "datetime DEFAULT NULL",
        "created_by" => "int NOT NULL DEFAULT '0'",
        "PRIMARY KEY (id)",
        "FOREIGN KEY (menu_id) REFERENCES  {{surveymenu}} (id) ON DELETE CASCADE"
    ));

    $colsToAdd = array("id","menu_id","order","name","title","menu_title","menu_description","menu_icon","menu_icon_type","menu_class","menu_link","action","template","partial","classes","permission","permission_grade","data","getdatamethod","language","changed_at","changed_by","created_at","created_by");
    $rowsToAdd = array(
        array(1,1,1,'overview','Survey overview','Overview','Open general survey overview and quick action','list','fontawesome','','admin/survey/sa/view','','','','','','',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(2,1,2,'generalsettings','Edit survey general settings','General settings','Open general survey settings','gears','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_generaloptions_panel','','surveysettings','read',NULL,'_generalTabEditSurvey','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(3,1,3,'surveytexts','Edit survey text elements','Survey texts','Edit survey text elements','file-text-o','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/tab_edit_view','','surveylocale','read',NULL,'_getTextEditData','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(4,1,4,'presentation','Presentation &amp; navigation settings','Presentation','Edit presentation and navigation settings','eye-slash','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_presentation_panel','','surveylocale','read',NULL,'_tabPresentationNavigation','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(5,1,5,'publication','Publication and access control settings','Publication &amp; access','Edit settings for publicationa and access control','key','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_publication_panel','','surveylocale','read',NULL,'_tabPublicationAccess','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(6,1,6,'surveypermissions','Edit surveypermissions','Survey permissions','Edit permissions for this survey','lock','fontawesome','','admin/surveypermission/sa/view/','','','','','surveysecurity','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(7,1,7,'tokens','Token handling','Participant tokens','Define how tokens should be treated or generated','users','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_tokens_panel','','surveylocale','read',NULL,'_tabTokens','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(8,1,8,'quotas','Edit quotas','Survey quotas','Edit quotas for this survey.','tasks','fontawesome','','admin/quotas/sa/index/','','','','','quotas','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(9,1,9,'assessments','Edit assessments','Assessments','Edit and look at the asessements for this survey.','comment-o','fontawesome','','admin/assessments/sa/index/','','','','','assessments','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(10,1,10,'notification','Notification and data management settings','Data management','Edit settings for notification and data management','feed','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_notification_panel','','surveylocale','read',NULL,'_tabNotificationDataManagement','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(11,1,11,'emailtemplates','Email templates','Email templates','Edit the templates for invitation, reminder and registration emails','envelope-square','fontawesome','','admin/emailtemplates/sa/index/','','','','','assessments','read',NULL,'','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(12,1,12,'panelintegration','Edit survey panel integration','Panel integration','Define panel integrations for your survey','link','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_integration_panel','','surveylocale','read',NULL,'_tabPanelIntegration','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0),
        array(13,1,13,'ressources','Add/Edit ressources to the survey','Ressources','Add/Edit ressources to the survey','file','fontawesome','','','updatesurveylocalesettings','editLocalSettings_main_view','/admin/survey/subview/accordion/_resources_panel','','surveylocale','read',NULL,'_tabResourceManagement','en-GB',date('Y-m-d H:i:s'),0,date('Y-m-d H:i:s'),0)
    );
    foreach($rowsToAdd as $row){
        $oDB->createCommand()->insert('{{surveymenu_entries}}', array_combine($colsToAdd,$row));
    }
}

/**
 * @param $oDB
 * @return void
 */
function upgradeTemplateTables295($oDB)
{
    $oTransaction = $oDB->beginTransaction();

    // Drop the old survey rights table.
    if (tableExists('{templates}')) {
        $oDB->createCommand()->dropTable('{{templates}}');
    }

    // Create templates table
    $oDB->createCommand()->createTable('{{templates}}', array(
        'name'                   => 'string(150) NOT NULL',
        'folder'                 => 'string(45) DEFAULT NULL',
        'title'                  => 'string(100) NOT NULL',
        'creation_date'          => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'author'                 => 'string(150) DEFAULT NULL',
        'author_email'           => 'string DEFAULT NULL',
        'author_url'             => 'string DEFAULT NULL',
        'copyright'              => 'MEDIUMTEXT',
        'license'                => 'MEDIUMTEXT',
        'version'                => 'string(45) DEFAULT NULL',
        'api_version'            => 'string(45) DEFAULT NULL',
        'view_folder'            => 'string(45) DEFAULT NULL',
        'files_folder'           => 'string(45) DEFAULT NULL',
        'description'            => 'TEXT',
        'last_update'            => 'DATETIME DEFAULT NULL',
        'owner_id'               => 'INT(11) DEFAULT NULL',
        'extends_templates_name' => 'string(150) DEFAULT NULL',
        'PRIMARY KEY (name)'
    ));

    // Add default template
    $oDB->createCommand()->insert('{{templates}}', array(
        'name'                   => 'default',
        'folder'                 => 'default',
        'title'                  => 'Advanced Template',
        'creation_date'          => '2017-07-12 12:00:00',
        'author'                 => 'Louis Gac',
        'author_email'           => 'louis.gac@limesurvey.org',
        'author_url'             => 'https://www.limesurvey.org/',
        'copyright'              => 'Copyright (C) 2007-2017 The LimeSurvey Project Team\r\nAll rights reserved.',
        'license'                => 'License: GNU/GPL License v2 or later, see LICENSE.php\r\n\r\nLimeSurvey is free software. This version may have been modified pursuant to the GNU General Public License, and as distributed it includes or is derivative of works licensed under the GNU General Public License or other free or open source software licenses. See COPYRIGHT.php for copyright notices and details.',
        'version'                => '1.0',
        'api_version'            => '3.0',
        'view_folder'            => 'views',
        'files_folder'           => 'files',
        'description'            => 'LimeSurvey Advanced Template:\r\nMany options for user customizations. \r\n',
//        'last_update'            => '',
        'owner_id'               => '1',
        'extends_templates_name' => '',
    ));

    // Add template configuration table
    $oDB->createCommand()->createTable('{{template_configuration}}', array(
        'id'                => 'int(11) NOT NULL',
        'templates_name'    => 'string(150) NOT NULL',
        'sid'               => 'int(11) DEFAULT NULL',
        'gsid'              => 'int(11) DEFAULT NULL',
        'files_css'         => 'MEDIUMTEXT',
        'files_js'          => 'MEDIUMTEXT',
        'files_print_css'   => 'MEDIUMTEXT',
        'options'           => 'MEDIUMTEXT',
        'cssframework_name' => 'string(45) DEFAULT NULL',
        'cssframework_css'  => 'MEDIUMTEXT',
        'cssframework_js'   => 'MEDIUMTEXT',
        'packages_to_load'  => 'MEDIUMTEXT',
        'packages_ltr'      => 'MEDIUMTEXT',
        'packages_rtl'      => 'MEDIUMTEXT',
        'PRIMARY KEY (id)'
    ));

    // Add global configuration for Advanced Template
    $oDB->createCommand()->insert('{{template_configuration}}', array(
        'id'                => '1',
        'templates_name'    => 'default',
        'files_css'         => '{"add": ["css/template.css", "css/animate.css"]}',
        'files_js'          => '{"add": ["scripts/template.js"]}',
        'files_print_css'   => '{"add":"css/print_template.css",}',
        'options'           => '{"ajaxmode":"on","brandlogo":"on","backgroundimage":"on","animatebody":"on","bodyanimation":"lightSpeedIn","animatequestion":"on","questionanimation":"flipInX","animatealert":"on","alertanimation":"shake"}',
        'cssframework_name' => 'bootstrap',
        'cssframework_css'  => '{"replace": ["css/bootstrap.css", "css/yiistrap.css"]}',
        'cssframework_js'   => '',
        'packages_to_load'  => 'template-default,',
        'packages_ltr'      => 'template-default-ltr,',
        'packages_rtl'      => 'template-default-rtl,',
    ));

    $oDB->createCommand()->update('{{settings_global}}',array('stg_value'=>295),"stg_name='DBVersion'");
    $oTransaction->commit();
}




function fixLanguageConsistencyAllSurveys()
{
    $surveyidquery = "SELECT sid,additional_languages FROM ".dbQuoteID('{{surveys}}');
    $surveyidresult = Yii::app()->db->createCommand($surveyidquery)->queryAll();
    foreach ( $surveyidresult as $sv )
    {
        fixLanguageConsistency($sv['sid'],$sv['additional_languages']);
    }
}

/**
 * @param string $sFieldType
 */
function alterColumn($sTable, $sColumn, $sFieldType, $bAllowNull=true, $sDefault='NULL')
{
    $oDB = Yii::app()->db;
    switch (Yii::app()->db->driverName){
        case 'mysql':
        case 'mysqli':
            $sType=$sFieldType;
            if ($bAllowNull!==true)
            {
                $sType.=' NOT NULL';
            }
            if ($sDefault!='NULL')
            {
                $sType.=" DEFAULT '{$sDefault}'";
            }
            $oDB->createCommand()->alterColumn($sTable,$sColumn,$sType);
            break;
        case 'dblib':
        case 'sqlsrv':
        case 'mssql':
            dropDefaultValueMSSQL($sColumn,$sTable);
            $sType=$sFieldType;
            if ($bAllowNull!=true && $sDefault!='NULL')
            {
                $oDB->createCommand("UPDATE {$sTable} SET [{$sColumn}]='{$sDefault}' where [{$sColumn}] is NULL;")->execute();
            }
            if ($bAllowNull!=true)
            {
                $sType.=' NOT NULL';
            }
            else
            {
                $sType.=' NULL';
            }
            $oDB->createCommand()->alterColumn($sTable,$sColumn,$sType);
            if ($sDefault!='NULL')
            {
                $oDB->createCommand("ALTER TABLE {$sTable} ADD default '{$sDefault}' FOR [{$sColumn}];")->execute();
            }
            break;
        case 'pgsql':
            $sType=$sFieldType;
            $oDB->createCommand()->alterColumn($sTable,$sColumn,$sType);
            try{ $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} DROP DEFAULT")->execute();} catch(Exception $e) {};
            try{ $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} DROP NOT NULL")->execute();} catch(Exception $e) {};

            if ($bAllowNull!=true)
            {
                $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} SET NOT NULL")->execute();
            }
            if ($sDefault!='NULL')
            {
                $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} SET DEFAULT '{$sDefault}'")->execute();
            }
            $oDB->createCommand()->alterColumn($sTable,$sColumn,$sType);
            break;
        default: die('Unknown database type');
    }
}

/**
 * @param string $sType
 */
function addColumn($sTableName, $sColumn, $sType)
{
    Yii::app()->db->createCommand()->addColumn($sTableName,$sColumn,$sType);
}
