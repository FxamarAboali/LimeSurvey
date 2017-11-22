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

/* Rules:
- Never use models in the upgrade process - never ever!
- Use the provided addColumn, alterColumn, dropPrimaryKey etc. functions where applicable - they ensure cross-DB compatibility
- Never use foreign keys
- Do not use fancy database field types (like mediumtext, timestamp, etc) - only use the ones provided by Yii
- If you want to use database functions make sure they exist on all three supported database types
- Always prefix key names by using curly brackets {{ }}

*/

/**
* @param integer $iOldDBVersion The previous database version
* @param boolean $bSilent Run update silently with no output - this checks if the update can be run silently at all. If not it will not run any updates at all.
*/
function db_upgrade_all($iOldDBVersion, $bSilent = false) {
    /**
     * If you add a new database version add any critical database version numbers to this array. See link
     * @link https://manual.limesurvey.org/Database_versioning for explanations
     * @var array $aCriticalDBVersions An array of cricital database version.
     */
    $aCriticalDBVersions = array(310);
    $aAllUpdates         = range($iOldDBVersion + 1, Yii::app()->getConfig('dbversionnumber'));

    // If trying to update silenty check if it is really possible
    if ($bSilent && (count(array_intersect($aCriticalDBVersions, $aAllUpdates)) > 0)) {
        return false;
    }
    // If DBVersion is older than 184 don't allow database update
    If ($iOldDBVersion < 184) {
        return false;
    }

    /// This function does anything necessary to upgrade
    /// older versions to match current functionality
    global $modifyoutput;

    Yii::app()->loadHelper('database');
    $sUserTemplateRootDir       = Yii::app()->getConfig('userthemerootdir');
    $sStandardTemplateRootDir   = Yii::app()->getConfig('standardthemerootdir');
    $oDB                        = Yii::app()->getDb();
    $oDB->schemaCachingDuration = 0; // Deactivate schema caching
    Yii::app()->setConfig('Updating', true);

    try {
        // LS 2.5 table start at 250
        if ($iOldDBVersion < 250) {
            $oTransaction = $oDB->beginTransaction();
            createBoxes250();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>250), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 251) {
            $oTransaction = $oDB->beginTransaction();
            upgradeBoxesTable251();

            // Update DBVersion
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>251), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 252) {
            $oTransaction = $oDB->beginTransaction();
            Yii::app()->db->createCommand()->addColumn('{{questions}}', 'modulename', 'string');
            // Update DBVersion
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>252), "stg_name='DBVersion'");
            $oTransaction->commit();
        }
        if ($iOldDBVersion < 253) {
            $oTransaction = $oDB->beginTransaction();
            upgradeSurveyTables253();

            // Update DBVersion
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>253), "stg_name='DBVersion'");
            $oTransaction->commit();
        }
        if ($iOldDBVersion < 254) {
            $oTransaction = $oDB->beginTransaction();
            upgradeSurveyTables254();
            // Update DBVersion
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>254), "stg_name='DBVersion'");
            $oTransaction->commit();
        }
        if ($iOldDBVersion < 255) {
            $oTransaction = $oDB->beginTransaction();
            upgradeSurveyTables255();
            // Update DBVersion
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>255), "stg_name='DBVersion'");
            $oTransaction->commit();
        }
        if ($iOldDBVersion < 256) {
            $oTransaction = $oDB->beginTransaction();
            upgradeTokenTables256();
            alterColumn('{{participants}}', 'email', "text", false);
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>256), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 257) {
            $oTransaction = $oDB->beginTransaction();
            switch (Yii::app()->db->driverName) {
                case 'pgsql':
                    $sSubstringCommand = 'substr';
                    break;
                default:
                    $sSubstringCommand = 'substring';
            }
            $oDB->createCommand("UPDATE {{templates}} set folder={$sSubstringCommand}(folder,1,50)")->execute();
            dropPrimaryKey('templates');
            alterColumn('{{templates}}', 'folder', "string(50)", false);
            addPrimaryKey('templates', 'folder');
            dropPrimaryKey('participant_attribute_names_lang');
            alterColumn('{{participant_attribute_names_lang}}', 'lang', "string(20)", false);
            addPrimaryKey('participant_attribute_names_lang', array('attribute_id', 'lang'));
            //Fixes the collation for the complete DB, tables and columns
            if (Yii::app()->db->driverName == 'mysql')
            {
                fixMySQLCollations('utf8mb4', 'utf8mb4_unicode_ci');
                // Also apply again fixes from DBVersion 181 again for case sensitive token fields
                upgradeSurveyTables181('utf8mb4_bin');
                upgradeTokenTables181('utf8mb4_bin');
            }
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>257), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Remove adminimageurl from global settings
         */
        if ($iOldDBVersion < 258) {
            $oTransaction = $oDB->beginTransaction();
            Yii::app()->getDb()->createCommand(
                "DELETE FROM {{settings_global}} WHERE stg_name='adminimageurl'"
            )->execute();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>258), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

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
                'title' => 'string not null', // varchar(255) in postgres
                'message' => 'text not null',
                'status' => "string(15) not null default 'new' ",
                'importance' => 'integer default 1',
                'display_class' => "string(31) default 'default'",
                'created' => 'datetime not null',
                'first_read' => 'datetime null'
            ));
            $oDB->createCommand()->createIndex('{{notif_index}}', '{{notifications}}', 'entity, entity_id, status', false);
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>259), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 260) {
            $oTransaction = $oDB->beginTransaction();
            alterColumn('{{participant_attribute_names}}', 'defaultname', "string(255)", false);
            alterColumn('{{participant_attribute_names_lang}}', 'attribute_name', "string(255)", false);
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>260), "stg_name='DBVersion'");
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
            $oDB->createCommand()->createIndex('{{notif_hash_index}}', '{{notifications}}', 'hash', false);
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>261), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 262) {
            $oTransaction = $oDB->beginTransaction();
            alterColumn('{{settings_global}}', 'stg_value', "text", false);
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>262), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 263) {
            $oTransaction = $oDB->beginTransaction();
            // Dummy version update for hash column in installation SQL.
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>263), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Add seed column in all active survey tables
         * Might take time to execute
         * @since 2016-09-01
         */
        if ($iOldDBVersion < 290) {
            $oTransaction = $oDB->beginTransaction();
            $aTables = dbGetTablesLike("survey\_%");
            $oSchema = Yii::app()->db->schema;
            foreach ($aTables as $sTableName) {
                $oTableSchema = $oSchema->getTable($sTableName);
                // Only update the table if it really is a survey response table - there are other tables that start the same
                if (!in_array('lastpage', $oTableSchema->columnNames)) {
                    continue;
                }
                // If survey has active table, create seed column
                Yii::app()->db->createCommand()->addColumn($sTableName, 'seed', 'string(31)');

                // RAND is RANDOM in Postgres
                switch (Yii::app()->db->driverName)
                {
                    case 'pgsql':
                        Yii::app()->db->createCommand("UPDATE {$sTableName} SET seed = ROUND(RANDOM() * 10000000)")->execute();
                        break;
                    default:
                        Yii::app()->db->createCommand("UPDATE {$sTableName} SET seed = ROUND(RAND() * 10000000, 0)")->execute();
                        break;
                }
            }
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>290), "stg_name='DBVersion'");
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

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>291), "stg_name='DBVersion'");
            $oTransaction->commit();
        }


        /**
         * Survey menue table
         * @since 2017-07-03
         */
        if ($iOldDBVersion < 293) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>293), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Survey menue table update
         * @since 2017-07-03
         */
        if ($iOldDBVersion < 294) {
            $oTransaction = $oDB->beginTransaction();


            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>294), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Survey menue table update
         * @since 2017-07-12
         */
        if ($iOldDBVersion < 296) {
            $oTransaction = $oDB->beginTransaction();


            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>296), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /**
         * Template tables
         * @since 2017-07-12
         */
        if ($iOldDBVersion < 298) {
            $oTransaction = $oDB->beginTransaction();
            upgradeTemplateTables298($oDB);
            $oTransaction->commit();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>298), "stg_name='DBVersion'");
        }

        /**
         * Template tables
         * @since 2017-07-12
         */
        if ($iOldDBVersion < 304) {
            $oTransaction = $oDB->beginTransaction();
            upgradeTemplateTables304($oDB);
            $oTransaction->commit();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>304), "stg_name='DBVersion'");
        }

        /**
         * Update to sidemenu rendering
         */
        if ($iOldDBVersion < 305) {
            $oTransaction = $oDB->beginTransaction();
            $oTransaction->commit();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>305), "stg_name='DBVersion'");
        }

        /**
         * Template tables
         * @since 2017-07-12
         */
        if ($iOldDBVersion < 306) {
            $oTransaction = $oDB->beginTransaction();
            createSurveyGroupTables306($oDB);
            $oTransaction->commit();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>306), "stg_name='DBVersion'");
        }

        /**
         * User settings table
         * @since 2016-08-29
         */
        if ($iOldDBVersion < 307) {
            $oTransaction = $oDB->beginTransaction();
            if (tableExists('{settings_user}')) {
                $oDB->createCommand()->dropTable('{{settings_user}}');
            }
            $oDB->createCommand()->createTable('{{settings_user}}', array(
                'id' => 'pk',
                'uid' => 'integer NOT NULL',
                'entity' => 'string(15)',
                'entity_id' => 'string(31)',
                'stg_name' => 'string(63) not null',
                'stg_value' => 'text',

            ));
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>307), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /*
        * Change dbfieldnames to be more functional
        */
        if ($iOldDBVersion < 308) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>308), "stg_name='DBVersion'");
            $oTransaction->commit();
        }
        /*
        * Add survey template editing to menu
        */
        if ($iOldDBVersion < 309) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>309), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /*
        * Reset all surveymenu tables, because there were too many errors
        */
        if ($iOldDBVersion < 310) {
            $oTransaction = $oDB->beginTransaction();
            reCreateSurveyMenuTable310($oDB);

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>310), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /*
        * Add template settings to survey groups
        */
        if ($iOldDBVersion < 311) {
            $oTransaction = $oDB->beginTransaction();
            addColumn('{{surveys_groups}}', 'template', "string(128) DEFAULT 'default'");
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>311), "stg_name='DBVersion'");
            $oTransaction->commit();
        }


        /*
        * Add ltr/rtl capability to template configuration
        */
        if ($iOldDBVersion < 312) {
            $oTransaction = $oDB->beginTransaction();
            // Already added in beta 2 but with wrong type
            try { setTransactionBookmark(); $oDB->createCommand()->dropColumn('{{template_configuration}}', 'packages_ltr'); } catch (Exception $e) { rollBackToTransactionBookmark(); }
            try { setTransactionBookmark(); $oDB->createCommand()->dropColumn('{{template_configuration}}', 'packages_rtl'); } catch (Exception $e) { rollBackToTransactionBookmark(); }

            addColumn('{{template_configuration}}', 'packages_ltr', "text");
            addColumn('{{template_configuration}}', 'packages_rtl', "text");
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>312), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /*
        * Add ltr/rtl capability to template configuration
        */
        if ($iOldDBVersion < 313) {
            $oTransaction = $oDB->beginTransaction();

            addColumn('{{surveymenu_entries}}', 'active', "boolean DEFAULT '0'");
            addColumn('{{surveymenu}}', 'active', "boolean DEFAULT '0'");
            $oDB->createCommand()->update('{{surveymenu_entries}}', array('active'=>1));
            $oDB->createCommand()->update('{{surveymenu}}', array('active'=>1));

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>313), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        /*
        * Add ltr/rtl capability to template configuration
        */
        if ($iOldDBVersion < 314) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->update('{{surveymenu_entries}}',
                array('name'=>'resources', 'title'=>'Add/Edit resources to the survey', 'menu_title'=>'Resources', 'menu_description'=>'Add/Edit resources to the survey'),
                'id=15'
            );

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>314), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 315) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->update('{{template_configuration}}',
                array('packages_to_load'=>'["pjax"]'),
                "templates_name='default' OR templates_name='material'"
            );

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>315), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 316) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->renameColumn('{{template_configuration}}', 'templates_name', 'template_name');

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>316), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        //Transition of the password field to a TEXT type

        if ($iOldDBVersion < 317) {
            $oTransaction = $oDB->beginTransaction();

            transferPasswordFieldToText($oDB);

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>317), "stg_name='DBVersion'");
            $oTransaction->commit();
        }



        //Rename order to sortorder

        if ($iOldDBVersion < 318) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>318), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        //force panelintegration to a full reload

        if ($iOldDBVersion < 319) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->update('{{surveymenu_entries}}', array('data'=>'{"render": {"link": { "pjaxed": false}}}'), "name='panelintegration'");

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>319), "stg_name='DBVersion'");

            $table = Yii::app()->db->schema->getTable('{{surveys_groups}}');
            if (isset($table->columns['order'])) {
                $oDB->createCommand()->renameColumn('{{surveys_groups}}', 'order', 'sortorder');
            }

            $table = Yii::app()->db->schema->getTable('{{templates}}');
            if (isset($table->columns['extends_template_name'])) {
                $oDB->createCommand()->renameColumn('{{templates}}', 'extends_template_name', 'extends');
            }

            $oTransaction->commit();
        }

        if ($iOldDBVersion < 320) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->update('{{surveymenu_entries}}', array('action'=>'updatesurveylocalesettings_generalsettings'), "name='generalsettings'");
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>320), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 321) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->update(
                '{{surveymenu_entries}}',
                array('data' => '{"render": {"isActive": true, "link": {"data": {"surveyid": ["survey", "sid"]}}}}'),
                "name = 'statistics' OR name = 'responses'"
            );
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>321), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 322) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->createTable(
                '{{tutorials}}', [
                    'tid' =>  'pk',
                    'name' =>  'string(128)',
                    'description' =>  'text',
                    'active' =>  'int DEFAULT 0',
                    'settings' => 'text',
                    'permission' =>  'string(128) NOT NULL',
                    'permission_grade' =>  'string(128) NOT NULL'
                ]
            );
            $oDB->createCommand()->createTable(
                '{{tutorial_entries}}', [
                    'teid' =>  'pk',
                    'tid' =>  'int NOT NULL',
                    'title' =>  'text',
                    'content' =>  'text',
                    'settings' => 'text'
                ]
            );
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>322), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 323) {
            $oTransaction = $oDB->beginTransaction();
            dropPrimaryKey('labels', 'lid');
            $oDB->createCommand()->addColumn('{{labels}}', 'id', 'pk');
            $oDB->createCommand()->createIndex('{{idx4_labels}}', '{{labels}}', ['lid', 'sortorder', 'language'], false);
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>323), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 324) {
            $oTransaction = $oDB->beginTransaction();

            $oDB->createCommand()->insert('{{surveymenu_entries}}',
            array(
                'menu_id' => 1,
                'ordering' => 16,
                'name' => 'plugins',
                'title' => 'Plugin settings',
                'menu_title' => 'Plugins',
                'menu_description' => 'Edit plugin settings',
                'menu_icon' => 'plug',
                'menu_icon_type' => 'fontawesome',
                'action' => 'updatesurveylocalesettings',
                'template' => 'editLocalSettings_main_view',
                'partial' => '/admin/survey/subview/accordion/_plugin_panel',
                'permission' => 'surveysettings',
                'permission_grade' => 'read',
                'data' => '',
                'getdatamethod' => '_pluginTabSurvey',
                'changed_at' => date('Y-m-d H:i:s'),
                'changed_by' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 1,
                'active' => 0
            ));
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>324), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 325) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->dropTable('{{templates}}');
            $oDB->createCommand()->dropTable('{{template_configuration}}');

            // templates
            $oDB->createCommand()->createTable('{{templates}}', array(
                'id' =>  "pk",
                'name' =>  "string(150) NOT NULL",
                'folder' =>  "string(45) NULL",
                'title' =>  "string(100) NOT NULL",
                'creation_date' =>  "datetime NULL",
                'author' =>  "string(150) NULL",
                'author_email' =>  "string(255) NULL",
                'author_url' =>  "string(255) NULL",
                'copyright' =>  "text ",
                'license' =>  "text ",
                'version' =>  "string(45) NULL",
                'api_version' =>  "string(45) NOT NULL",
                'view_folder' =>  "string(45) NOT NULL",
                'files_folder' =>  "string(45) NOT NULL",
                'description' =>  "text ",
                'last_update' =>  "datetime NULL",
                'owner_id' =>  "integer NULL",
                'extends' =>  "string(150)  NULL",
            ));

            $oDB->createCommand()->createIndex('{{idx1_templates}}', '{{templates}}', 'name', false);
            $oDB->createCommand()->createIndex('{{idx2_templates}}', '{{templates}}', 'title', false);
            $oDB->createCommand()->createIndex('{{idx3_templates}}', '{{templates}}', 'owner_id', false);
            $oDB->createCommand()->createIndex('{{idx4_templates}}', '{{templates}}', 'extends', false);

            $headerArray = ['name', 'folder', 'title', 'creation_date', 'author', 'author_email', 'author_url', 'copyright', 'license', 'version', 'api_version', 'view_folder', 'files_folder', 'description', 'last_update', 'owner_id', 'extends'];
            $oDB->createCommand()->insert("{{templates}}", array_combine($headerArray, ['default', 'default', 'Advanced Template', date('Y-m-d H:i:s'), 'Louis Gac', 'louis.gac@limesurvey.org', 'https://www.limesurvey.org/', 'Copyright (C) 2007-2017 The LimeSurvey Project Team\\r\\nAll rights reserved.', 'License: GNU/GPL License v2 or later, see LICENSE.php\\r\\n\\r\\nLimeSurvey is free software. This version may have been modified pursuant to the GNU General Public License, and as distributed it includes or is derivative of works licensed under the GNU General Public License or other free or open source software licenses. See COPYRIGHT.php for copyright notices and details.', '1.0', '3.0', 'views', 'files', "<strong>LimeSurvey Advanced Template</strong><br>A template with custom options to show what it's possible to do with the new engines. Each template provider will be able to offer its own option page (loaded from template)", NULL, 1, '']));

            $oDB->createCommand()->insert("{{templates}}", array_combine($headerArray, ['material', 'material', 'Material Template', date('Y-m-d H:i:s'), 'Louis Gac', 'louis.gac@limesurvey.org', 'https://www.limesurvey.org/', 'Copyright (C) 2007-2017 The LimeSurvey Project Team\\r\\nAll rights reserved.', 'License: GNU/GPL License v2 or later, see LICENSE.php\\r\\n\\r\\nLimeSurvey is free software. This version may have been modified pursuant to the GNU General Public License, and as distributed it includes or is derivative of works licensed under the GNU General Public License or other free or open source software licenses. See COPYRIGHT.php for copyright notices and details.', '1.0', '3.0', 'views', 'files', '<strong>LimeSurvey Advanced Template</strong><br> A template extending default, to show the inheritance concept. Notice the options, differents from Default.<br><small>uses FezVrasta\'s Material design theme for Bootstrap 3</small>', NULL, 1, 'default']));

            $oDB->createCommand()->insert("{{templates}}", array_combine($headerArray, ['monochrome', 'monochrome', 'Monochrome Templates', date('Y-m-d H:i:s'), 'Louis Gac', 'louis.gac@limesurvey.org', 'https://www.limesurvey.org/', 'Copyright (C) 2007-2017 The LimeSurvey Project Team\\r\\nAll rights reserved.', 'License: GNU/GPL License v2 or later, see LICENSE.php\\r\\n\\r\\nLimeSurvey is free software. This version may have been modified pursuant to the GNU General Public License, and as distributed it includes or is derivative of works licensed under the GNU General Public License or other free or open source software licenses. See COPYRIGHT.php for copyright notices and details.', '1.0', '3.0', 'views', 'files', '<strong>LimeSurvey Monochrome Templates</strong><br>A template with monochrome colors for easy customization.', NULL, 1, '']));


            // template_configuration
            $oDB->createCommand()->createTable('{{template_configuration}}', array(
                'id' => "pk",
                'template_name' => "string(150)  NOT NULL",
                'sid' => "integer NULL",
                'gsid' => "integer NULL",
                'uid' => "integer NULL",
                'files_css' => "text",
                'files_js' => "text",
                'files_print_css' => "text",
                'options' => "text ",
                'cssframework_name' => "string(45) NULL",
                'cssframework_css' => "text",
                'cssframework_js' => "text",
                'packages_to_load' => "text",
                'packages_ltr' => "text",
                'packages_rtl' => "text",
            ));

            $oDB->createCommand()->createIndex('{{idx1_template_configuration}}', '{{template_configuration}}', 'template_name', false);
            $oDB->createCommand()->createIndex('{{idx2_template_configuration}}', '{{template_configuration}}', 'sid', false);
            $oDB->createCommand()->createIndex('{{idx3_template_configuration}}', '{{template_configuration}}', 'gsid', false);
            $oDB->createCommand()->createIndex('{{idx4_template_configuration}}', '{{template_configuration}}', 'uid', false);

            $headerArray = ['template_name', 'sid', 'gsid', 'uid', 'files_css', 'files_js', 'files_print_css', 'options', 'cssframework_name', 'cssframework_css', 'cssframework_js', 'packages_to_load', 'packages_ltr', 'packages_rtl'];
            $oDB->createCommand()->insert("{{template_configuration}}", array_combine($headerArray, ['default', NULL, NULL, NULL, '{"add": ["css/animate.css","css/template.css"]}', '{"add": ["scripts/template.js", "scripts/ajaxify.js"]}', '{"add":"css/print_template.css"}', '{"ajaxmode":"on","brandlogo":"on", "brandlogofile": "./files/logo.png", "boxcontainer":"on", "backgroundimage":"off","animatebody":"off","bodyanimation":"fadeInRight","animatequestion":"off","questionanimation":"flipInX","animatealert":"off","alertanimation":"shake"}', 'bootstrap', '{"replace": [["css/bootstrap.css","css/flatly.css"]]}', '', '["pjax"]', '', '']));

            $oDB->createCommand()->insert("{{template_configuration}}", array_combine($headerArray, ['material', NULL, NULL, NULL, '{"add": ["css/bootstrap-material-design.css", "css/ripples.min.css", "css/template.css"]}', '{"add": ["scripts/template.js", "scripts/material.js", "scripts/ripples.min.js", "scripts/ajaxify.js"]}', '{"add":"css/print_template.css"}', '{"ajaxmode":"on","brandlogo":"on", "brandlogofile": "./files/logo.png", "animatebody":"off","bodyanimation":"fadeInRight","animatequestion":"off","questionanimation":"flipInX","animatealert":"off","alertanimation":"shake"}', 'bootstrap', '{"replace": [["css/bootstrap.css","css/bootstrap.css"]]}', '', '["pjax"]', '', '']));

            $oDB->createCommand()->insert("{{template_configuration}}", array_combine($headerArray, ['monochrome', NULL, NULL, NULL, '{"add":["css/animate.css","css/ajaxify.css","css/sea_green.css", "css/template.css"]}', '{"add":["scripts/template.js","scripts/ajaxify.js"]}', '{"add":"css/print_template.css"}', '{"ajaxmode":"on","brandlogo":"on","brandlogofile":".\/files\/logo.png","boxcontainer":"on","backgroundimage":"off","animatebody":"off","bodyanimation":"fadeInRight","animatequestion":"off","questionanimation":"flipInX","animatealert":"off","alertanimation":"shake"}', 'bootstrap', '{}', '', '["pjax"]', '', '']));

            $oDB->createCommand()->update('{{surveymenu_entries}}', array('data'=>'{"render": {"link": { "data": {"surveyid": ["survey","sid"], "gsid":["survey","gsid"]}}}}'), "name='template_options'");

            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>325), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 326) {
            $oTransaction = $oDB->beginTransaction();
            $oDB->createCommand()->alterColumn('{{surveys}}', 'datecreated', 'datetime');
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>326), "stg_name='DBVersion'");
            $oTransaction->commit();
        }

        if ($iOldDBVersion < 327) {
            $oTransaction = $oDB->beginTransaction();
            upgrade327($oDB);
            $oDB->createCommand()->update('{{settings_global}}', array('stg_value'=>327), "stg_name='DBVersion'");
            $oTransaction->commit();
        }
    }
    catch (Exception $e)
    {
        Yii::app()->setConfig('Updating', false);
        $oTransaction->rollback();
        // Activate schema caching
        $oDB->schemaCachingDuration = 3600;
        // Load all tables of the application in the schema
        $oDB->schema->getTables();
        // clear the cache of all loaded tables
        $oDB->schema->refresh();
        //echo '<br /><br />'.gT('An non-recoverable error happened during the update. Error details:')."<p>".htmlspecialchars($e->getMessage()).'</p><br />';
        Yii::app()->user->setFlash('error', gT('An non-recoverable error happened during the update. Error details:')."<p>".htmlspecialchars($e->getMessage()).'</p><br />');
        return false;
    }

    // Activate schema cache first - otherwise it won't be refreshed!
    $oDB->schemaCachingDuration = 3600;
    // Load all tables of the application in the schema
    $oDB->schema->getTables();
    // clear the cache of all loaded tables
    $oDB->schema->refresh();
    $oDB->active = false;
    $oDB->active = true;

    // Force User model to refresh meta data (for updates from very old versions)
    User::model()->refreshMetaData();
    Survey::model()->refreshMetaData();
    Notification::model()->refreshMetaData();

    // Inform  superadmin about update
    $superadmins = User::model()->getSuperAdmins();
    $currentDbVersion = $oDB->createCommand()->select('stg_value')->from('{{settings_global}}')->where("stg_name=:stg_name", array('stg_name'=>'DBVersion'))->queryRow();

    Notification::broadcast(array(
        'title' => gT('Database update'),
        'message' => sprintf(gT('The database has been updated from version %s to version %s.'), $iOldDBVersion, $currentDbVersion['stg_value'])
        ), $superadmins);

    fixLanguageConsistencyAllSurveys();

    Yii::app()->setConfig('Updating', false);
    return true;
}



/**
* @param $oDB
* @return void
*/
function upgrade327($oDB)
{
    // Update the box value so it uses to the the themeoptions controler
    $oDB->createCommand()->update('{{boxes}}', array(
        'position' =>  '6',
        'url'      =>  'admin/themeoptions',
        'title'    =>  'Themes',
        'ico'      =>  'templates',
        'desc'     =>  'Edit LimeSurvey Themes',
        'page'     =>  'welcome',
        'usergroup' => '-2',
    ), "url='admin/templateoptions'");


    // Update the survey menu so it uses the themeoptions controller
    $oDB->createCommand()->update('{{surveymenu_entries}}', array(
        'menu_id'          => 1,
        'user_id'          => NULL,
        'ordering'         => 4,
        'name'             => 'theme_options',
        'title'            => 'Theme options',
        'menu_title'       => 'Theme options',
        'menu_description' => 'Edit theme options for this survey',
        'menu_icon'        =>  'paint-brush',
        'menu_icon_type'   =>  'fontawesome',
        'menu_class'       =>  '',
        'menu_link'        => 'admin/themeoptions/sa/updatesurvey',
        'action'           =>  '',
        'partial'          => '',
        'classes'          =>  '',
        'permission'       =>  'templates', // TODO: change permission from template to theme
        'permission_grade' =>  'read',
        'data'             =>  '{"render": {"link": { "data": {"surveyid": ["survey","sid"], "gsid":["survey","gsid"]}}}}',
        'getdatamethod'    =>  '',
        'language'         =>  'en-GB',
        'active'           =>  1,
        'changed_at'       =>  date('Y-m-d H:i:s'),
        'changed_by'       =>  0,
        'created_at'       =>  date('Y-m-d H:i:s'),
        'created_by'       =>  0
    ), "name='template_options'");

}

function transferPasswordFieldToText($oDB) {
    switch ($oDB->getDriverName()) {
        case 'mysql':
        case 'mysqli':
            $oDB->createCommand()->alterColumn('{{users}}', 'password', 'TEXT NOT NULL');
            break;
        case 'pgsql':

            $userPasswords = $oDB->createCommand()->select(['uid', "encode(password::bytea, 'escape') as password"])->from('{{users}}')->queryAll();

            $oDB->createCommand()->renameColumn('{{users}}', 'password', 'password_blob');
            $oDB->createCommand()->addColumn('{{users}}', 'password', "TEXT NOT NULL DEFAULT 'nopw'");

            foreach ($userPasswords as $userArray) {
                $oDB->createCommand()->update('{{users}}', ['password' => $userArray['password']], 'uid=:uid', [':uid'=> $userArray['uid']]);
            }

            $oDB->createCommand()->dropColumn('{{users}}', 'password_blob');
            break;
        case 'sqlsrv':
        case 'dblib':
        case 'mssql':
        default:
            break;
    }
}

function createSurveyMenuTable293($oDB) {
    // Drop the old survey rights table.
    if (tableExists('{surveymenu_entries}')) {
        $oDB->createCommand()->dropTable('{{surveymenu_entries}}');
    }

    if (tableExists('{surveymenu}')) {
        $oDB->createCommand()->dropTable('{{surveymenu}}');
    }


    $oDB->createCommand()->createTable('{{surveymenu}}', array(
        "id" => "pk",
        "parent_id" => "int DEFAULT NULL",
        "survey_id" => "int DEFAULT NULL",
        "order" => "int DEFAULT '0'",
        "level" => "int DEFAULT '0'",
        "title" => "character varying(255)  NOT NULL DEFAULT ''",
        "description" => "text ",
        "changed_at" => "datetime NULL",
        "changed_by" => "int NOT NULL DEFAULT '0'",
        "created_at" => "datetime DEFAULT NULL",
        "created_by" => "int NOT NULL DEFAULT '0'",

    ));

    $oDB->createCommand()->insert(
        '{{surveymenu}}',
        array(
            'parent_id' => NULL,
            'survey_id' => NULL,
            'order' => 0,
            'level' => 0,
            'title' => 'Survey menu',
            'description' => 'Main survey menu',
            'changed_at' => date('Y-m-d H:i:s'),
            'changed_by' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 0
        )
    );

    $oDB->createCommand()->createTable('{{surveymenu_entries}}', array(
        "id" => "pk",
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
        "changed_at" => "datetime DEFAULT NULL",
        "changed_by" => "int NOT NULL DEFAULT '0'",
        "created_at" => "datetime DEFAULT NULL",
        "created_by" => "int NOT NULL DEFAULT '0'",
        "FOREIGN KEY (menu_id) REFERENCES  {{surveymenu}} (id) ON DELETE CASCADE"
    ));

    $colsToAdd = array("menu_id", "order", "name", "title", "menu_title", "menu_description", "menu_icon", "menu_icon_type", "menu_class", "menu_link", "action", "template", "partial", "classes", "permission", "permission_grade", "data", "getdatamethod", "language", "changed_at", "changed_by", "created_at", "created_by");
    $rowsToAdd = array(
        array(1, 1, 'overview', 'Survey overview', 'Overview', 'Open general survey overview and quick action', 'list', 'fontawesome', '', 'admin/survey/sa/view', '', '', '', '', '', '', NULL, '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 2, 'generalsettings', 'Edit survey general settings', 'General settings', 'Open general survey settings', 'gears', 'fontawesome', '', '', 'updatesurveylocalesettings_generalsettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_generaloptions_panel', '', 'surveysettings', 'read', NULL, '_generalTabEditSurvey', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 3, 'surveytexts', 'Edit survey text elements', 'Survey texts', 'Edit survey text elements', 'file-text-o', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/tab_edit_view', '', 'surveylocale', 'read', NULL, '_getTextEditData', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 4, 'presentation', 'Presentation &amp; navigation settings', 'Presentation', 'Edit presentation and navigation settings', 'eye-slash', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_presentation_panel', '', 'surveylocale', 'read', NULL, '_tabPresentationNavigation', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 5, 'publication', 'Publication and access control settings', 'Publication &amp; access', 'Edit settings for publicationa and access control', 'key', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_publication_panel', '', 'surveylocale', 'read', NULL, '_tabPublicationAccess', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 6, 'surveypermissions', 'Edit surveypermissions', 'Survey permissions', 'Edit permissions for this survey', 'lock', 'fontawesome', '', 'admin/surveypermission/sa/view/', '', '', '', '', 'surveysecurity', 'read', NULL, '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 7, 'tokens', 'Token handling', 'Participant tokens', 'Define how tokens should be treated or generated', 'users', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_tokens_panel', '', 'surveylocale', 'read', NULL, '_tabTokens', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 8, 'quotas', 'Edit quotas', 'Survey quotas', 'Edit quotas for this survey.', 'tasks', 'fontawesome', '', 'admin/quotas/sa/index/', '', '', '', '', 'quotas', 'read', NULL, '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 9, 'assessments', 'Edit assessments', 'Assessments', 'Edit and look at the assessements for this survey.', 'comment-o', 'fontawesome', '', 'admin/assessments/sa/index/', '', '', '', '', 'assessments', 'read', NULL, '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 10, 'notification', 'Notification and data management settings', 'Data management', 'Edit settings for notification and data management', 'feed', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_notification_panel', '', 'surveylocale', 'read', NULL, '_tabNotificationDataManagement', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 11, 'emailtemplates', 'Email templates', 'Email templates', 'Edit the templates for invitation, reminder and registration emails', 'envelope-square', 'fontawesome', '', 'admin/emailtemplates/sa/index/', '', '', '', '', 'assessments', 'read', NULL, '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 12, 'panelintegration', 'Edit survey panel integration', 'Panel integration', 'Define panel integrations for your survey', 'link', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_integration_panel', '', 'surveylocale', 'read', NULL, '_tabPanelIntegration', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, 13, 'ressources', 'Add/Edit ressources to the survey', 'Ressources', 'Add/Edit ressources to the survey', 'file', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_resources_panel', '', 'surveylocale', 'read', NULL, '_tabResourceManagement', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0)
    );
    foreach ($rowsToAdd as $row) {
        $oDB->createCommand()->insert('{{surveymenu_entries}}', array_combine($colsToAdd, $row));
    }
}

/**
* @param CDbConnection $oDB
* @return void
*/
function reCreateSurveyMenuTable310(CDbConnection $oDB)
{
    // NB: Need to refresh here, since surveymenu table is
    // created in earlier version in same script.
    $oDB->schema->getTables();
    $oDB->schema->refresh();

    // Drop the old surveymenu_entries table.
    if (tableExists('{surveymenu_entries}')) {
        $oDB->createCommand()->dropTable('{{surveymenu_entries}}');
    }

    // Drop the old surveymenu table.
    if (tableExists('{surveymenu}')) {
        $oDB->createCommand()->dropTable('{{surveymenu}}');
    }

    $oDB->createCommand()->createTable('{{surveymenu}}', array(
        "id" =>  "pk",
        "parent_id" =>  "integer DEFAULT NULL",
        "survey_id" =>  "integer DEFAULT NULL",
        "user_id" =>  "integer DEFAULT NULL",
        "ordering" =>  "integer DEFAULT '0'",
        "level" =>  "integer DEFAULT '0'",
        "title" =>  "string(192)  NOT NULL DEFAULT ''",
        "position" =>  "string(192)  NOT NULL DEFAULT 'side'",
        "description" =>  "text ",
        "changed_at" =>  "datetime NULL",
        "changed_by" =>  "integer NOT NULL DEFAULT '0'",
        "created_at" =>  "datetime DEFAULT NULL",
        "created_by" =>  "integer NOT NULL DEFAULT '0'",
    ));
    $oDB->createCommand()->createIndex('{{idx_ordering}}', '{{surveymenu}}', 'ordering');
    $oDB->createCommand()->createIndex('{{idx_title}}', '{{surveymenu}}', 'title');

    $oDB->createCommand()->insert(
        '{{surveymenu}}',
        array(
            "parent_id" =>NULL,
            "survey_id" =>NULL,
            "user_id" =>NULL,
            "ordering" =>1,
            "level" =>0,
            "title" =>'Survey Menu',
            "position" =>'side',
            "description" =>'Main survey menu',
            "changed_at" => date('Y-m-d H:i:s'),
            "changed_by" =>0,
            "created_at" =>date('Y-m-d H:i:s'),
            "created_by" =>  0
    ));
    $oDB->createCommand()->insert(
        '{{surveymenu}}',
        array(
            "parent_id" =>NULL,
            "survey_id" =>NULL,
            "user_id" =>NULL,
            "ordering" =>1,
            "level" =>0,
            "title" =>'Quick menu',
            "position" =>'collapsed',
            "description" =>'Quick menu',
            "changed_at" => date('Y-m-d H:i:s'),
            "changed_by" =>0,
            "created_at" =>date('Y-m-d H:i:s'),
            "created_by" =>  0
    ));


    $oDB->createCommand()->createTable('{{surveymenu_entries}}', array(
        "id" => "pk",
        "menu_id" => "integer DEFAULT NULL",
        "user_id" => "integer DEFAULT NULL",
        "ordering" => "integer DEFAULT '0'",
        "name" => "string(192)  NOT NULL DEFAULT ''",
        "title" => "string(168)  NOT NULL DEFAULT ''",
        "menu_title" => "string(168)  NOT NULL DEFAULT ''",
        "menu_description" => "text ",
        "menu_icon" => "string(192)  NOT NULL DEFAULT ''",
        "menu_icon_type" => "string(192)  NOT NULL DEFAULT ''",
        "menu_class" => "string(192)  NOT NULL DEFAULT ''",
        "menu_link" => "string(192)  NOT NULL DEFAULT ''",
        "action" => "string(192)  NOT NULL DEFAULT ''",
        "template" => "string(192)  NOT NULL DEFAULT ''",
        "partial" => "string(192)  NOT NULL DEFAULT ''",
        "classes" => "string(192)  NOT NULL DEFAULT ''",
        "permission" => "string(192)  NOT NULL DEFAULT ''",
        "permission_grade" => "string(192)  DEFAULT NULL",
        "data" => "text ",
        "getdatamethod" => "string(192)  NOT NULL DEFAULT ''",
        "language" => "string(32)  NOT NULL DEFAULT 'en-GB'",
        "changed_at" => "datetime NULL",
        "changed_by" => "integer NOT NULL DEFAULT '0'",
        "created_at" => "datetime DEFAULT NULL",
        "created_by" => "integer NOT NULL DEFAULT '0'"
    ));
    $oDB->createCommand()->createIndex('{{idx_menu_id}}', '{{surveymenu_entries}}', 'menu_id');
    $oDB->createCommand()->createIndex('{{idx_menu_title}}', '{{surveymenu_entries}}', 'menu_title');

    $colsToAdd = array("menu_id", "user_id", "ordering", "name", "title", "menu_title", "menu_description", "menu_icon", "menu_icon_type", "menu_class", "menu_link", "action", "template", "partial", "classes", "permission", "permission_grade", "data", "getdatamethod", "language", "changed_at", "changed_by", "created_at", "created_by");
    $rowsToAdd = array(
        array(1, NULL, 1, 'overview', 'Survey overview', 'Overview', 'Open general survey overview and quick action', 'list', 'fontawesome', '', 'admin/survey/sa/view', '', '', '', '', '', '', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 2, 'generalsettings', 'Edit survey general settings', 'General settings', 'Open general survey settings', 'gears', 'fontawesome', '', '', 'updatesurveylocalesettings_generalsettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_generaloptions_panel', '', 'surveysettings', 'read', NULL, '_generalTabEditSurvey', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 3, 'surveytexts', 'Edit survey text elements', 'Survey texts', 'Edit survey text elements', 'file-text-o', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/tab_edit_view', '', 'surveylocale', 'read', NULL, '_getTextEditData', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 4, 'template_options', 'Template options', 'Template options', 'Edit Template options for this survey', 'paint-brush', 'fontawesome', '', 'admin/templateoptions/sa/updatesurvey', '', '', '', '', 'templates', 'read', '{"render": {"link": { "pjaxed": true, "data": {"surveyid": ["survey","sid"], "gsid":["survey","gsid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 5, 'participants', 'Survey participants', 'Survey participants', 'Go to survey participant and token settings', 'user', 'fontawesome', '', 'admin/tokens/sa/index/', '', '', '', '', 'surveysettings', 'update', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 6, 'presentation', 'Presentation &amp; navigation settings', 'Presentation', 'Edit presentation and navigation settings', 'eye-slash', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_presentation_panel', '', 'surveylocale', 'read', NULL, '_tabPresentationNavigation', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 7, 'publication', 'Publication and access control settings', 'Publication &amp; access', 'Edit settings for publicationa and access control', 'key', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_publication_panel', '', 'surveylocale', 'read', NULL, '_tabPublicationAccess', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 8, 'surveypermissions', 'Edit surveypermissions', 'Survey permissions', 'Edit permissions for this survey', 'lock', 'fontawesome', '', 'admin/surveypermission/sa/view/', '', '', '', '', 'surveysecurity', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 9, 'tokens', 'Token handling', 'Participant tokens', 'Define how tokens should be treated or generated', 'users', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_tokens_panel', '', 'surveylocale', 'read', NULL, '_tabTokens', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 10, 'quotas', 'Edit quotas', 'Survey quotas', 'Edit quotas for this survey.', 'tasks', 'fontawesome', '', 'admin/quotas/sa/index/', '', '', '', '', 'quotas', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 11, 'assessments', 'Edit assessments', 'Assessments', 'Edit and look at the assessements for this survey.', 'comment-o', 'fontawesome', '', 'admin/assessments/sa/index/', '', '', '', '', 'assessments', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 12, 'notification', 'Notification and data management settings', 'Data management', 'Edit settings for notification and data management', 'feed', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_notification_panel', '', 'surveylocale', 'read', NULL, '_tabNotificationDataManagement', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 13, 'emailtemplates', 'Email templates', 'Email templates', 'Edit the templates for invitation, reminder and registration emails', 'envelope-square', 'fontawesome', '', 'admin/emailtemplates/sa/index/', '', '', '', '', 'assessments', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 14, 'panelintegration', 'Edit survey panel integration', 'Panel integration', 'Define panel integrations for your survey', 'link', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_integration_panel', '', 'surveylocale', 'read', NULL, '_tabPanelIntegration', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(1, NULL, 15, 'ressources', 'Add/Edit ressources to the survey', 'Ressources', 'Add/Edit ressources to the survey', 'file', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_resources_panel', '', 'surveylocale', 'read', NULL, '_tabResourceManagement', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 1, 'activateSurvey', 'Activate survey', 'Activate survey', 'Activate survey', 'play', 'fontawesome', '', 'admin/survey/sa/activate', '', '', '', '', 'surveyactivation', 'update', '{"render": {"isActive": false, "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 2, 'deactivateSurvey', 'Stop this survey', 'Stop this survey', 'Stop this survey', 'stop', 'fontawesome', '', 'admin/survey/sa/deactivate', '', '', '', '', 'surveyactivation', 'update', '{"render": {"isActive": true, "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 3, 'testSurvey', 'Go to survey', 'Go to survey', 'Go to survey', 'cog', 'fontawesome', '', 'survey/index/', '', '', '', '', '', '', '{"render": {"link": {"external": true, "data": {"sid": ["survey","sid"], "newtest": "Y", "lang": ["survey","language"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 4, 'listQuestions', 'List questions', 'List questions', 'List questions', 'list', 'fontawesome', '', 'admin/survey/sa/listquestions', '', '', '', '', 'surveycontent', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 5, 'listQuestionGroups', 'List question groups', 'List question groups', 'List question groups', 'th-list', 'fontawesome', '', 'admin/survey/sa/listquestiongroups', '', '', '', '', 'surveycontent', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 6, 'generalsettings', 'Edit survey general settings', 'General settings', 'Open general survey settings', 'gears', 'fontawesome', '', '', 'updatesurveylocalesettings_generalsettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_generaloptions_panel', '', 'surveysettings', 'read', NULL, '_generalTabEditSurvey', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 7, 'surveypermissions', 'Edit surveypermissions', 'Survey permissions', 'Edit permissions for this survey', 'lock', 'fontawesome', '', 'admin/surveypermission/sa/view/', '', '', '', '', 'surveysecurity', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 8, 'quotas', 'Edit quotas', 'Survey quotas', 'Edit quotas for this survey.', 'tasks', 'fontawesome', '', 'admin/quotas/sa/index/', '', '', '', '', 'quotas', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 9, 'assessments', 'Edit assessments', 'Assessments', 'Edit and look at the assessements for this survey.', 'comment-o', 'fontawesome', '', 'admin/assessments/sa/index/', '', '', '', '', 'assessments', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 10, 'emailtemplates', 'Email templates', 'Email templates', 'Edit the templates for invitation, reminder and registration emails', 'envelope-square', 'fontawesome', '', 'admin/emailtemplates/sa/index/', '', '', '', '', 'surveylocale', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 11, 'surveyLogicFile', 'Survey logic file', 'Survey logic file', 'Survey logic file', 'sitemap', 'fontawesome', '', 'admin/expressions/sa/survey_logic_file/', '', '', '', '', 'surveycontent', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 12, 'tokens', 'Token handling', 'Participant tokens', 'Define how tokens should be treated or generated', 'user', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_tokens_panel', '', 'surveylocale', 'read', '{"render": { "link": {"data": {"surveyid": ["survey","sid"]}}}}', '_tabTokens', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 13, 'cpdb', 'Central participant database', 'Central participant database', 'Central participant database', 'users', 'fontawesome', '', 'admin/participants/sa/displayParticipants', '', '', '', '', 'tokens', 'read', '{"render": {"link": {}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 14, 'responses', 'Responses', 'Responses', 'Responses', 'icon-browse', 'iconclass', '', 'admin/responses/sa/browse/', '', '', '', '', 'responses', 'read', '{"render": {"isActive": true}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 15, 'statistics', 'Statistics', 'Statistics', 'Statistics', 'bar-chart', 'fontawesome', '', 'admin/statistics/sa/index/', '', '', '', '', 'statistics', 'read', '{"render": {"isActive": true}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0),
        array(2, NULL, 16, 'reorder', 'Reorder questions/question groups', 'Reorder questions/question groups', 'Reorder questions/question groups', 'icon-organize', 'iconclass', '', 'admin/survey/sa/organize/', '', '', '', '', 'surveycontent', 'update', '{"render": {"isActive": false, "link": {"data": {"surveyid": ["survey","sid"]}}}}', '', 'en-GB', date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0)
    );
    foreach ($rowsToAdd as $row) {
        $oDB->createCommand()->insert('{{surveymenu_entries}}', array_combine($colsToAdd, $row));
    }
}
/**
* @param $oDB
* @return void
*/
function createSurveyGroupTables306($oDB)
{
    // Drop the old survey groups table.
    if (tableExists('{surveys_groups}')) {
        $oDB->createCommand()->dropTable('{{surveys_groups}}');
    }


    // Create templates table
    $oDB->createCommand()->createTable('{{surveys_groups}}', array(
        'gsid'        => 'pk',
        'name'        => 'string(45) NOT NULL',
        'title'       => 'string(100) DEFAULT NULL',
        'description' => 'text DEFAULT NULL',
        'sortorder'   => 'integer NOT NULL',
        'owner_uid'   => 'integer DEFAULT NULL',
        'parent_id'   => 'integer DEFAULT NULL',
        'created'     => 'datetime',
        'modified'    => 'datetime',
        'created_by'  => 'integer NOT NULL'
    ));

    // Add default template
    $date = date("Y-m-d H:i:s");
    $oDB->createCommand()->insert('{{surveys_groups}}', array(
        'name'        => 'default',
        'title'       => 'Default Survey Group',
        'description' => 'LimeSurvey core default survey group',
        'sortorder'   => '0',
        'owner_uid'   => '1',
        'created'     => $date,
        'modified'    => $date,
        'created_by'  => '1'
    ));

    $oDB->createCommand()->addColumn('{{surveys}}', 'gsid', "integer DEFAULT 1");


}



/**
* @param $oDB
* @return void
*/
function upgradeTemplateTables304($oDB)
{
    // Drop the old survey rights table.
    if (tableExists('{{templates}}')) {
        $oDB->createCommand()->dropTable('{{templates}}');
    }

    if (tableExists('{{template_configuration}}')) {
        $oDB->createCommand()->dropTable('{{template_configuration}}');
    }

    // Create templates table
    $oDB->createCommand()->createTable('{{templates}}', array(
        'name'                   => 'string(150) NOT NULL',
        'folder'                 => 'string(45) DEFAULT NULL',
        'title'                  => 'string(100) NOT NULL',
        'creation_date'          => 'datetime',
        'author'                 => 'string(150) DEFAULT NULL',
        'author_email'           => 'string DEFAULT NULL',
        'author_url'             => 'string DEFAULT NULL',
        'copyright'              => 'TEXT',
        'license'                => 'TEXT',
        'version'                => 'string(45) DEFAULT NULL',
        'api_version'            => 'string(45) NOT NULL',
        'view_folder'            => 'string(45) NOT NULL',
        'files_folder'           => 'string(45) NOT NULL',
        'description'            => 'TEXT',
        'last_update'            => 'datetime DEFAULT NULL',
        'owner_id'               => 'integer DEFAULT NULL',
        'extends_template_name' => 'string(150) DEFAULT NULL',
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
        'description'            => "<strong>LimeSurvey Advanced Template</strong><br>A template with custom options to show what it's possible to do with the new engines. Each template provider will be able to offer its own option page (loaded from template)",
        'owner_id'               => '1',
        'extends_template_name' => '',
    ));

    // Add minimal template
    $oDB->createCommand()->insert('{{templates}}', array(
        'name'                   => 'minimal',
        'folder'                 => 'minimal',
        'title'                  => 'Minimal Template',
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
        'description'            => '<strong>LimeSurvey Minimal Template</strong><br>A clean and simple base that can be used by developers to create their own solution.',
        'owner_id'               => '1',
        'extends_template_name' => '',
    ));



    // Add material template
    $oDB->createCommand()->insert('{{templates}}', array(
        'name'                   => 'material',
        'folder'                 => 'material',
        'title'                  => 'Material Template',
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
        'description'            => "<strong>LimeSurvey Advanced Template</strong><br> A template extending default, to show the inheritance concept. Notice the options, differents from Default.<br><small>uses FezVrasta's Material design theme for Bootstrap 3</small>",
        'owner_id'               => '1',
        'extends_template_name' => 'default',
    ));


    // Add template configuration table
    $oDB->createCommand()->createTable('{{template_configuration}}', array(
        'id'                => 'pk',
        'templates_name'    => 'string(150) NOT NULL',
        'sid'               => 'integer DEFAULT NULL',
        'gsid'              => 'integer DEFAULT NULL',
        'uid'               => 'integer DEFAULT NULL',
        'files_css'         => 'TEXT',
        'files_js'          => 'TEXT',
        'files_print_css'   => 'TEXT',
        'options'           => 'TEXT',
        'cssframework_name' => 'string(45) DEFAULT NULL',
        'cssframework_css'  => 'TEXT',
        'cssframework_js'   => 'TEXT',
        'packages_to_load'  => 'TEXT',
    ));

    // Add global configuration for Advanced Template
    $oDB->createCommand()->insert('{{template_configuration}}', array(
        'templates_name'    => 'default',
        'files_css'         => '{"add": ["css/template.css", "css/animate.css"]}',
        'files_js'          => '{"add": ["scripts/template.js"]}',
        'files_print_css'   => '{"add":"css/print_template.css"}',
        'options'           => '{"ajaxmode":"on","brandlogo":"on", "brandlogofile":"./files/logo.png", "boxcontainer":"on", "backgroundimage":"off","animatebody":"off","bodyanimation":"fadeInRight","animatequestion":"off","questionanimation":"flipInX","animatealert":"off","alertanimation":"shake"}',
        'cssframework_name' => 'bootstrap',
        'cssframework_css'  => '{"replace": [["css/bootstrap.css","css/flatly.css"]]}',
        'cssframework_js'   => '',
        'packages_to_load'  => '["pjax"]',
    ));


    // Add global configuration for Minimal Template
    $oDB->createCommand()->insert('{{template_configuration}}', array(
        'templates_name'    => 'minimal',
        'files_css'         => '{"add": ["css/template.css"]}',
        'files_js'          => '{"add": ["scripts/template.js"]}',
        'files_print_css'   => '{"add":"css/print_template.css"}',
        'options'           => '{}',
        'cssframework_name' => 'bootstrap',
        'cssframework_css'  => '{}',
        'cssframework_js'   => '',
        'packages_to_load'  => '["pjax"]',
    ));

    // Add global configuration for Material Template
    $oDB->createCommand()->insert('{{template_configuration}}', array(
        'templates_name'    => 'material',
        'files_css'         => '{"add": ["css/template.css", "css/bootstrap-material-design.css", "css/ripples.min.css"]}',
        'files_js'          => '{"add": ["scripts/template.js", "scripts/material.js", "scripts/ripples.min.js"]}',
        'files_print_css'   => '{"add":"css/print_template.css"}',
        'options'           => '{"ajaxmode":"on","brandlogo":"on", "brandlogofile":"./files/logo.png", "animatebody":"off","bodyanimation":"fadeInRight","animatequestion":"off","questionanimation":"flipInX","animatealert":"off","alertanimation":"shake"}',
        'cssframework_name' => 'bootstrap',
        'cssframework_css'  => '{"replace": [["css/bootstrap.css","css/bootstrap.css"]]}',
        'cssframework_js'   => '',
        'packages_to_load'  => '["pjax"]',
    ));

}


/**
* @param $oDB
* @return void
*/
function upgradeTemplateTables298($oDB)
{
    // Add global configuration for Advanced Template
    $oDB->createCommand()->update('{{boxes}}', array(
        'url'=>'admin/templateoptions',
        'title'=>'Templates',
        'desc'=>'View templates list',
        ), "id=6");
}

function upgradeTokenTables256()
{
    $aTableNames = dbGetTablesLike("tokens%");
    $oDB = Yii::app()->getDb();
    foreach ($aTableNames as $sTableName)
    {
        try { setTransactionBookmark(); $oDB->createCommand()->dropIndex("idx_lime_{$sTableName}_efl", $sTableName); } catch (Exception $e) { rollBackToTransactionBookmark(); }
        alterColumn($sTableName, 'email', "text");
        alterColumn($sTableName, 'firstname', "string(150)");
        alterColumn($sTableName, 'lastname', "string(150)");
    }
}


function upgradeSurveyTables255()
{
    // We delete all the old boxes, and reinsert new ones
    Yii::app()->getDb()->createCommand(
        "DELETE FROM {{boxes}}"
    )->execute();

    // Then we recreate them
    $oDB = Yii::app()->db;
    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '1',
        'url'      => 'admin/survey/sa/newsurvey',
        'title'    => 'Create survey',
        'ico'      => 'add',
        'desc'     => 'Create a new survey',
        'page'     => 'welcome',
        'usergroup' => '-2',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '2',
        'url'      =>  'admin/survey/sa/listsurveys',
        'title'    =>  'List surveys',
        'ico'      =>  'list',
        'desc'     =>  'List available surveys',
        'page'     =>  'welcome',
        'usergroup' => '-1',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '3',
        'url'      =>  'admin/globalsettings',
        'title'    =>  'Global settings',
        'ico'      =>  'global',
        'desc'     =>  'Edit global settings',
        'page'     =>  'welcome',
        'usergroup' => '-2',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '4',
        'url'      =>  'admin/update',
        'title'    =>  'ComfortUpdate',
        'ico'      =>  'shield',
        'desc'     =>  'Stay safe and up to date',
        'page'     =>  'welcome',
        'usergroup' => '-2',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '5',
        'url'      =>  'admin/labels/sa/view',
        'title'    =>  'Label sets',
        'ico'      =>  'labels',
        'desc'     =>  'Edit label sets',
        'page'     =>  'welcome',
        'usergroup' => '-2',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '6',
        'url'      =>  'admin/themes/sa/view',
        'title'    =>  'Template editor',
        'ico'      =>  'templates',
        'desc'     =>  'Edit LimeSurvey templates',
        'page'     =>  'welcome',
        'usergroup' => '-2',
    ));

}

function upgradeSurveyTables254()
{
    Yii::app()->db->createCommand()->dropColumn('{{boxes}}', 'img');
    Yii::app()->db->createCommand()->addColumn('{{boxes}}', 'usergroup', 'integer');
}

function upgradeSurveyTables253()
{
    $oSchema = Yii::app()->db->schema;
    $aTables = dbGetTablesLike("survey\_%");
    foreach ($aTables as $sTable)
    {
        $oTableSchema = $oSchema->getTable($sTable);
        if (in_array('refurl', $oTableSchema->columnNames))
        {
            alterColumn($sTable, 'refurl', "text");
        }
        if (in_array('ipaddr', $oTableSchema->columnNames))
        {
            alterColumn($sTable, 'ipaddr', "text");
        }
    }
}


function upgradeBoxesTable251()
{
    Yii::app()->db->createCommand()->addColumn('{{boxes}}', 'ico', 'string');
    Yii::app()->db->createCommand()->update('{{boxes}}', array('ico'=>'add',
        'title'=>'Create survey')
        ,"id=1");
    Yii::app()->db->createCommand()->update('{{boxes}}', array('ico'=>'list')
        ,"id=2");
    Yii::app()->db->createCommand()->update('{{boxes}}', array('ico'=>'settings')
        ,"id=3");
    Yii::app()->db->createCommand()->update('{{boxes}}', array('ico'=>'shield')
        ,"id=4");
    Yii::app()->db->createCommand()->update('{{boxes}}', array('ico'=>'label')
        ,"id=5");
    Yii::app()->db->createCommand()->update('{{boxes}}', array('ico'=>'templates')
        ,"id=6");
}

/**
* Create boxes table
*/
function createBoxes250()
{
    $oDB = Yii::app()->db;
    $oDB->createCommand()->createTable('{{boxes}}', array(
        'id' => 'pk',
        'position' => 'integer',
        'url' => 'text',
        'title' => 'text',
        'img' => 'text',
        'desc' => 'text',
        'page'=>'text',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '1',
        'url'      => 'admin/survey/sa/newsurvey',
        'title'    => 'Create survey',
        'img'      => 'add.png',
        'desc'     => 'Create a new survey',
        'page'     => 'welcome',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '2',
        'url'      =>  'admin/survey/sa/listsurveys',
        'title'    =>  'List surveys',
        'img'      =>  'surveylist.png',
        'desc'     =>  'List available surveys',
        'page'     =>  'welcome',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '3',
        'url'      =>  'admin/globalsettings',
        'title'    =>  'Global settings',
        'img'      =>  'global.png',
        'desc'     =>  'Edit global settings',
        'page'     =>  'welcome',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '4',
        'url'      =>  'admin/update',
        'title'    =>  'ComfortUpdate',
        'img'      =>  'shield&#45;update.png',
        'desc'     =>  'Stay safe and up to date',
        'page'     =>  'welcome',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '5',
        'url'      =>  'admin/labels/sa/view',
        'title'    =>  'Label sets',
        'img'      =>  'labels.png',
        'desc'     =>  'Edit label sets',
        'page'     =>  'welcome',
    ));

    $oDB->createCommand()->insert('{{boxes}}', array(
        'position' =>  '6',
        'url'      =>  'admin/themes/sa/view',
        'title'    =>  'Template editor',
        'img'      =>  'templates.png',
        'desc'     =>  'Edit LimeSurvey templates',
        'page'     =>  'welcome',
    ));
}


function fixLanguageConsistencyAllSurveys()
{
    $surveyidquery = "SELECT sid,additional_languages FROM ".dbQuoteID('{{surveys}}');
    $surveyidresult = Yii::app()->db->createCommand($surveyidquery)->queryAll();
    foreach ($surveyidresult as $sv)
    {
        fixLanguageConsistency($sv['sid'], $sv['additional_languages']);
    }
}


/**
* @param string $sMySQLCollation
*/
function upgradeSurveyTables181($sMySQLCollation)
{
    $oDB = Yii::app()->db;
    $oSchema = Yii::app()->db->schema;
    if (Yii::app()->db->driverName != 'pgsql')
    {
        $aTables = dbGetTablesLike("survey\_%");
        foreach ($aTables as $sTableName)
        {
            $oTableSchema = $oSchema->getTable($sTableName);
            if (!in_array('token', $oTableSchema->columnNames)) continue; // No token field in this table
            switch (Yii::app()->db->driverName) {
                case 'sqlsrv':
                case 'dblib':
                case 'mssql': dropSecondaryKeyMSSQL('token', $sTableName);
                    alterColumn($sTableName, 'token', "string(35) COLLATE SQL_Latin1_General_CP1_CS_AS");
                    $oDB->createCommand()->createIndex("{{idx_{$sTableName}_".rand(1, 40000).'}}', $sTableName, 'token');
                    break;
                case 'mysql':
                case 'mysqli':
                    alterColumn($sTableName, 'token', "string(35) COLLATE '{$sMySQLCollation}'");
                    break;
                default: die('Unknown database driver');
            }
        }

    }
}

/**
* @param string $sMySQLCollation
*/
function upgradeTokenTables181($sMySQLCollation)
{
    $oDB = Yii::app()->db;
    if (Yii::app()->db->driverName != 'pgsql')
    {
        $aTables = dbGetTablesLike("tokens%");
        if (!empty($aTables))
        {
            foreach ($aTables as $sTableName)
            {
                switch (Yii::app()->db->driverName) {
                    case 'sqlsrv':
                    case 'dblib':
                    case 'mssql': dropSecondaryKeyMSSQL('token', $sTableName);
                        alterColumn($sTableName, 'token', "string(35) COLLATE SQL_Latin1_General_CP1_CS_AS");
                        $oDB->createCommand()->createIndex("{{idx_{$sTableName}_".rand(1, 50000).'}}', $sTableName, 'token');
                        break;
                    case 'mysql':
                    case 'mysqli':
                        alterColumn($sTableName, 'token', "string(35) COLLATE '{$sMySQLCollation}'");
                        break;
                    default: die('Unknown database driver');
                }
            }
        }
    }
}

/**
* @param string $sFieldType
* @param string $sColumn
*/
function alterColumn($sTable, $sColumn, $sFieldType, $bAllowNull = true, $sDefault = 'NULL')
{
    $oDB = Yii::app()->db;
    switch (Yii::app()->db->driverName) {
        case 'mysql':
        case 'mysqli':
            $sType = $sFieldType;
            if ($bAllowNull !== true)
            {
                $sType .= ' NOT NULL';
            }
            if ($sDefault != 'NULL')
            {
                $sType .= " DEFAULT '{$sDefault}'";
            }
            $oDB->createCommand()->alterColumn($sTable, $sColumn, $sType);
            break;
        case 'dblib':
        case 'sqlsrv':
        case 'mssql':
            dropDefaultValueMSSQL($sColumn, $sTable);
            $sType = $sFieldType;
            if ($bAllowNull != true && $sDefault != 'NULL')
            {
                $oDB->createCommand("UPDATE {$sTable} SET [{$sColumn}]='{$sDefault}' where [{$sColumn}] is NULL;")->execute();
            }
            if ($bAllowNull != true)
            {
                $sType .= ' NOT NULL';
            }
            else
            {
                $sType .= ' NULL';
            }
            $oDB->createCommand()->alterColumn($sTable, $sColumn, $sType);
            if ($sDefault != 'NULL')
            {
                $oDB->createCommand("ALTER TABLE {$sTable} ADD default '{$sDefault}' FOR [{$sColumn}];")->execute();
            }
            break;
        case 'pgsql':
            $sType = $sFieldType;
            $oDB->createCommand()->alterColumn($sTable, $sColumn, $sType);
            try { $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} DROP DEFAULT")->execute(); } catch (Exception $e) {};
            try { $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} DROP NOT NULL")->execute(); } catch (Exception $e) {};

            if ($bAllowNull != true)
            {
                $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} SET NOT NULL")->execute();
            }
            if ($sDefault != 'NULL')
            {
                $oDB->createCommand("ALTER TABLE {$sTable} ALTER COLUMN {$sColumn} SET DEFAULT '{$sDefault}'")->execute();
            }
            $oDB->createCommand()->alterColumn($sTable, $sColumn, $sType);
            break;
        default: die('Unknown database type');
    }
}

/**
* @param string $sType
*/
function addColumn($sTableName, $sColumn, $sType) {
    Yii::app()->db->createCommand()->addColumn($sTableName, $sColumn, $sType);
}

/**
* Set a transaction bookmark - this is critical for Postgres because a transaction in Postgres cannot be continued unless you roll back to the transaction bookmark first
*
* @param mixed $sBookmark  Name of the bookmark
*/
function setTransactionBookmark($sBookmark = 'limesurvey') {
    if (Yii::app()->db->driverName == 'pgsql') {
        Yii::app()->db->createCommand("SAVEPOINT {$sBookmark};")->execute();
    }
}

/**
* Roll back to a transaction bookmark
*
* @param mixed $sBookmark   Name of the bookmark
*/
function rollBackToTransactionBookmark($sBookmark = 'limesurvey')
{
    if (Yii::app()->db->driverName == 'pgsql') {
        Yii::app()->db->createCommand("ROLLBACK TO SAVEPOINT {$sBookmark};")->execute();
    }
}

/**
* Drop a default value in MSSQL
*
* @param string $fieldname
* @param mixed $tablename
*/
function dropDefaultValueMSSQL($fieldname, $tablename)
{
    // find out the name of the default constraint
    // Did I already mention that this is the most suckiest thing I have ever seen in MSSQL database?
    $dfquery = "SELECT c_obj.name AS constraint_name
    FROM sys.sysobjects AS c_obj INNER JOIN
    sys.sysobjects AS t_obj ON c_obj.parent_obj = t_obj.id INNER JOIN
    sys.sysconstraints AS con ON c_obj.id = con.constid INNER JOIN
    sys.syscolumns AS col ON t_obj.id = col.id AND con.colid = col.colid
    WHERE (c_obj.xtype = 'D') AND (col.name = '$fieldname') AND (t_obj.name='{$tablename}')";
    $defaultname = Yii::app()->getDb()->createCommand($dfquery)->queryRow();
    if ($defaultname != false) {
        Yii::app()->db->createCommand("ALTER TABLE {$tablename} DROP CONSTRAINT {$defaultname['constraint_name']}")->execute();
    }
}

/**
* This function drops a unique Key of an MSSQL database field by using the field name and the table name
*
* @param string $sFieldName
* @param string $sTableName
*/
function dropUniqueKeyMSSQL($sFieldName, $sTableName)
{
    $sQuery = "select TC.Constraint_Name, CC.Column_Name from information_schema.table_constraints TC
    inner join information_schema.constraint_column_usage CC on TC.Constraint_Name = CC.Constraint_Name
    where TC.constraint_type = 'Unique' and Column_name='{$sFieldName}' and TC.TABLE_NAME='{$sTableName}'";
    $aUniqueKeyName = Yii::app()->getDb()->createCommand($sQuery)->queryRow();
    if ($aUniqueKeyName != false) {
        Yii::app()->getDb()->createCommand("ALTER TABLE {$sTableName} DROP CONSTRAINT {$aUniqueKeyName['Constraint_Name']}")->execute();
    }
}

/**
* This function drops a secondary key of an MSSQL database field by using the field name and the table name
*
* @param string $sFieldName
* @param mixed $sTableName
*/
function dropSecondaryKeyMSSQL($sFieldName, $sTableName)
{
    $oDB = Yii::app()->getDb();
    $sQuery = "select
    i.name as IndexName
    from sys.indexes i
    join sys.objects o on i.object_id = o.object_id
    join sys.index_columns ic on ic.object_id = i.object_id
    and ic.index_id = i.index_id
    join sys.columns co on co.object_id = i.object_id
    and co.column_id = ic.column_id
    where i.[type] = 2
    and i.is_unique = 0
    and i.is_primary_key = 0
    and o.[type] = 'U'
    and ic.is_included_column = 0
    and o.name='{$sTableName}' and co.name='{$sFieldName}'";
    $aKeyName = Yii::app()->getDb()->createCommand($sQuery)->queryScalar();
    if ($aKeyName != false)
    {
        try { $oDB->createCommand()->dropIndex($aKeyName, $sTableName); } catch (Exception $e) { }
    }
}

/**
* Drops the primary key of a table
*
* @param string $sTablename
* @param string $oldPrimaryKeyColumn
*/
function dropPrimaryKey($sTablename, $oldPrimaryKeyColumn = null)
{
    switch (Yii::app()->db->driverName) {
        case 'mysql':
        if ($oldPrimaryKeyColumn !== null) {
            $sQuery = "ALTER TABLE {{".$sTablename."}} MODIFY {$oldPrimaryKeyColumn} INT NOT NULL";
            Yii::app()->db->createCommand($sQuery)->execute();
        }
            $sQuery = "ALTER TABLE {{".$sTablename."}} DROP PRIMARY KEY";
            Yii::app()->db->createCommand($sQuery)->execute();
            break;
        case 'pgsql':
        case 'sqlsrv':
        case 'dblib':
        case 'mssql':
            $pkquery = "SELECT CONSTRAINT_NAME "
            ."FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS "
            ."WHERE (TABLE_NAME = '{{{$sTablename}}}') AND (CONSTRAINT_TYPE = 'PRIMARY KEY')";

            $primarykey = Yii::app()->db->createCommand($pkquery)->queryRow(false);
            if ($primarykey !== false)
            {
                $sQuery = "ALTER TABLE {{".$sTablename."}} DROP CONSTRAINT ".$primarykey[0];
                Yii::app()->db->createCommand($sQuery)->execute();
            }
            break;
        default: die('Unknown database type');
    }

}

/**
* @param string $sTablename
*/
function addPrimaryKey($sTablename, $aColumns)
{
    return Yii::app()->db->createCommand()->addPrimaryKey('PK_'.$sTablename.'_'.randomChars(12, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'), '{{'.$sTablename.'}}', $aColumns);
}

/**
* Modifies a primary key in one command  - this is only tested on MySQL
*
* @param string $sTablename The table name
* @param string[] $aColumns Column names to be in the new key
*/
function modifyPrimaryKey($sTablename, $aColumns)
{
    Yii::app()->db->createCommand("ALTER TABLE {{".$sTablename."}} DROP PRIMARY KEY, ADD PRIMARY KEY (".implode(',', $aColumns).")")->execute();
}



/**
* @param string $sEncoding
* @param string $sCollation
*/
function fixMySQLCollations($sEncoding, $sCollation)
{
    $surveyidresult = dbGetTablesLike("%");
    foreach ($surveyidresult as $sTableName) {
        try {
            Yii::app()->getDb()->createCommand("ALTER TABLE {$sTableName} CONVERT TO CHARACTER SET {$sEncoding} COLLATE {$sCollation};")->execute();
        } catch (Exception $e) {
            // There are some big survey response tables that cannot be converted because the new charset probably uses
            // more bytes per character than the old one - we just leave them as they are for now.
        };
    }
    $sDatabaseName = getDBConnectionStringProperty('dbname');
    Yii::app()->getDb()->createCommand("ALTER DATABASE `$sDatabaseName` DEFAULT CHARACTER SET {$sEncoding} COLLATE {$sCollation};");
}
