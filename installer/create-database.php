<?php
function handleError($currentTable, $oError, &$oCurrentTransaction)
{
    if ($oError->getCode() == '42S01') {
        $oCurrentTransaction->rollback();
        return;
    }
    /* disabled so installer can skip showing errors if tables already exist
    $oCurrentTransaction->rollback();
    throw new CHttpException(500, "Table: {$currentTable}  \n".$oError->getMessage());
    */
}

/**
 * @param CDbConnection $oDB
 * @return bool
 * @throws CHttpException
 */
function createDatabase($oDB)
{
    /**
    * Populate the database for a limesurvey installation
    * Rules:
    * - Use the provided addColumn, alterColumn, dropPrimaryKey etc. functions where applicable - they ensure cross-DB compatibility
    * - Never use foreign keys
    * - Do not use fancy database field types (like mediumtext, timestamp, etc) - only use the ones provided by Yii
    * - If you want to use database functions make sure they exist on all three supported database types
    * - Always prefix key/index names by using curly brackets {{ }}*
    */

    // Get current database version:
    $version = require(\Yii::app()->getBasePath() . '/config/version.php');
    $databaseCurrentVersion = $version['dbversionnumber'];

    Yii::app()->loadHelper('database');
    Yii::app()->loadHelper('update.updatedb');
    $options = 'ROW_FORMAT=DYNAMIC';

    $oTransaction = $oDB->beginTransaction();
    try {
        //answers table
        $oDB->createCommand()->createTable('{{answers}}', array(
            'aid' =>  "pk",
            'qid' => 'integer NOT NULL',
            'code' => 'string(5) NOT NULL',
            'sortorder' => 'integer NOT NULL',
            'assessment_value' => 'integer NOT NULL DEFAULT 0',
            'scale_id' => 'integer NOT NULL DEFAULT 0',
        ),$options);

        $oDB->createCommand()->createIndex('{{answers_idx}}', '{{answers}}', ['qid', 'code', 'scale_id'], true);
        $oDB->createCommand()->createIndex('{{answers_idx2}}', '{{answers}}', 'sortorder', false);

        $oDB->createCommand()->createTable('{{answer_l10ns}}', array(
            'id' =>  "pk",
            'aid' =>  "integer NOT NULL",
            'answer' =>  "text NOT NULL",
            'language' =>  "string(20) NOT NULL"
        ), $options);
        $oDB->createCommand()->createIndex('{{answer_l10ns_idx}}', '{{answer_l10ns}}', ['aid', 'language'], true);

        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('answers', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // assessements
        $oDB->createCommand()->createTable('{{assessments}}', array(
            'id' =>         'autoincrement',
            'sid' =>        'integer NOT NULL DEFAULT 0',
            'scope' =>      'string(5) NOT NULL'	,
            'gid' =>        'integer NOT NULL DEFAULT 0',
            'name' =>       'text NOT NULL',
            'minimum' =>    'string(50) NOT NULL',
            'maximum' =>    'string(50) NOT NULL',
            'message' =>    'text NOT NULL',
            'language' =>   "string(20) NOT NULL DEFAULT 'en'",
            'composite_pk' => array('id', 'language')
        ), $options);

        $oDB->createCommand()->createIndex('{{assessments_idx2}}', '{{assessments}}', 'sid', false);
        $oDB->createCommand()->createIndex('{{assessments_idx3}}', '{{assessments}}', 'gid', false);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('assessments', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // boxes
        $oDB->createCommand()->createTable('{{boxes}}', array(
            'id' => "pk",
            'position' => "integer NULL ",
            'url' => "text NOT NULL ",
            'title' => "text NOT NULL ",
            'ico' => "string(255) NULL ",
            'desc' => "text NOT NULL ",
            'page' => "text NOT NULL ",
            'usergroup' => "integer NOT NULL "
        ), $options);
        
        foreach ($boxesData=LsDefaultDataSets::getBoxesData() as $box) {
            $oDB->createCommand()->insert("{{boxes}}", $box);
        }
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('boxes', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // conditions
        $oDB->createCommand()->createTable('{{conditions}}', array(
            'cid' => 'pk',
            'qid' => "integer NOT NULL default '0'",
            'cqid' => "integer NOT NULL default '0'",
            'cfieldname' => "string(50) NOT NULL default ''",
            'method' => "string(5) NOT NULL default ''",
            'value' => "string(255) NOT NULL default ''",
            'scenario' => "integer NOT NULL default 1"
        ), $options);
        $oDB->createCommand()->createIndex('{{conditions_idx}}', '{{conditions}}', 'qid', false);
        $oDB->createCommand()->createIndex('{{conditions_idx3}}', '{{conditions}}', 'cqid', false);

        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('conditions', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // defaultvalues
        $oDB->createCommand()->createTable('{{defaultvalues}}', array(
            'dvid' =>  "pk",
            'qid' =>  "integer NOT NULL default '0'",
            'scale_id' =>  "integer NOT NULL default '0'",
            'sqid' =>  "integer NOT NULL default '0'",
            'specialtype' =>  "string(20) NOT NULL default ''",
        ), $options);
        $oDB->createCommand()->createIndex('{{idx1_defaultvalue}}', '{{defaultvalues}}', ['qid', 'scale_id', 'sqid', 'specialtype'], false);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('defaultvalues', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // defaultvalue_l10ns
        $oDB->createCommand()->createTable('{{defaultvalue_l10ns}}', array(
            'id' =>  "pk",
            'dvid' =>  "integer NOT NULL default '0'",
            'language' =>  "string(20) NOT NULL",
            'defaultvalue' =>  "text",
        ));
        $oDB->createCommand()->createIndex('{{idx1_defaultvalue_ls}}', '{{defaultvalue_l10ns}}', ['dvid', 'language'], false);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('defaultvalue_l10ns', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // expression_errors
        $oDB->createCommand()->createTable('{{expression_errors}}', array(
            'id' =>  "pk",
            'errortime' =>  "string(50) NULL",
            'sid' =>  "integer NULL",
            'gid' =>  "integer NULL",
            'qid' =>  "integer NULL",
            'gseq' =>  "integer NULL",
            'qseq' =>  "integer NULL",
            'type' =>  "string(50)",
            'eqn' =>  "text",
            'prettyprint' =>  "text",
        ), $options);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('expression_errors', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // failed_login_attempts
        $oDB->createCommand()->createTable('{{failed_login_attempts}}', array(
            'id' =>  "pk",
            'ip' =>  "string(40) NOT NULL",
            'last_attempt' =>  "string(20) NOT NULL",
            'number_attempts' =>  "integer NOT NULL",
        ), $options);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('failed_login_attempts', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        $oDB->createCommand()->createTable('{{groups}}', array(
            'gid' =>  "pk",
            'sid' =>  "integer NOT NULL default '0'",
            'group_order' =>  "integer NOT NULL default '0'",
            'randomization_group' =>  "string(20) NOT NULL default ''",
            'grelevance' =>  "text NULL"
        ), $options);
        $oDB->createCommand()->createIndex('{{idx1_groups}}', '{{groups}}', 'sid', false);
        
 
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('groups', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        $oDB->createCommand()->createTable('{{group_l10ns}}', array(
            'id' =>  "pk",
            'gid' =>  "integer NOT NULL",
            'group_name' =>  "text NOT NULL",
            'description' =>  "text",
            'language' =>  "string(20) NOT NULL"
        ), $options);
        $oDB->createCommand()->createIndex('{{idx1_group_ls}}', '{{group_l10ns}}', ['gid', 'language'], true);

        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('group_l10ns', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // labels
        $oDB->createCommand()->createTable('{{labels}}', array(
            'id' =>  "pk",
            'lid' =>  "integer NOT NULL DEFAULT 0",
            'code' =>  "string(5) NOT NULL default ''",
            'sortorder' =>  "integer NOT NULL",
            'assessment_value' =>  "integer NOT NULL default '0'",
        ), $options);
        $oDB->createCommand()->createIndex('{{idx1_labels}}', '{{labels}}', 'code', false);
        $oDB->createCommand()->createIndex('{{idx2_labels}}', '{{labels}}', 'sortorder', false);
        $oDB->createCommand()->createIndex('{{idx4_labels}}', '{{labels}}', ['lid','sortorder'], false);

        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('labels', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // label_l10ns
        $oDB->createCommand()->createTable('{{label_l10ns}}', array(
            'id' =>  "pk",
            'label_id' =>  "integer NOT NULL",
            'title' =>  "text",
            'language' =>  "string(20) NOT NULL DEFAULT 'en'"
        ), $options);

        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('label_l10ns', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // labelsets
        $oDB->createCommand()->createTable('{{labelsets}}', array(
            'lid' => 'pk',
            'label_name' =>  "string(100) NOT NULL DEFAULT ''",
            'languages' =>  "string(255) NOT NULL",
        ), $options);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('labelsets', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // notifications
        $oDB->createCommand()->createTable('{{notifications}}', array(
            'id' =>  "pk",
            'entity' =>  "string(15) NOT NULL ",
            'entity_id' =>  "integer NOT NULL",
            'title' =>  "string(255) NOT NULL",
            'message' =>  "TEXT NOT NULL",
            'status' =>  "string(15) NOT NULL DEFAULT 'new' ",
            'importance' =>  "integer NOT NULL DEFAULT 1",
            'display_class' =>  "string(31) DEFAULT 'default' ",
            'hash' =>  "string(64)",
            'created' =>  "datetime",
            'first_read' =>  "datetime",
        ), $options);

        $oDB->createCommand()->createIndex('{{notifications_pk}}', '{{notifications}}', ['entity', 'entity_id', 'status'], false);
        $oDB->createCommand()->createIndex('{{idx1_notifications}}', '{{notifications}}', 'hash', false);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('notifications', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //  participants
        $oDB->createCommand()->createTable('{{participants}}', array(
            'participant_id' =>  "string(50) NOT NULL",
            'firstname' =>  "text NULL",
            'lastname' =>  "text NULL",
            'email' =>  "text",
            'language' =>  "string(40) NULL",
            'blacklisted' =>  "string(1) NOT NULL",
            'owner_uid' =>  "integer NOT NULL",
            'created_by' =>  "integer NOT NULL",
            'created' =>  "datetime",
            'modified' =>  "datetime",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{participant_pk}}', '{{participants}}', 'participant_id', false);
        $oDB->createCommand()->createIndex('{{idx3_participants}}', '{{participants}}', 'language', false);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('participants', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // participant_attribute
        $oDB->createCommand()->createTable('{{participant_attribute}}', array(
            'participant_id' =>  "string(50) NOT NULL",
            'attribute_id' =>  "integer NOT NULL",
            'value' =>  "text NOT NULL",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{participant_attribute_pk}}', '{{participant_attribute}}', ['participant_id', 'attribute_id']);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('participant_attribute', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // participant_attribute_names_lang
        $oDB->createCommand()->createTable('{{participant_attribute_names_lang}}', array(
            'attribute_id' =>  "integer NOT NULL",
            'attribute_name' =>  "string(255) NOT NULL",
            'lang' =>  "string(20) NOT NULL",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{participant_attribute_names_lang_pk}}', '{{participant_attribute_names_lang}}', ['attribute_id', 'lang']);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('participant_attribute_names_lang', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {

        // participant_attribute_names
        $oDB->createCommand()->createTable('{{participant_attribute_names}}', array(
            'attribute_id' =>  "autoincrement",
            'attribute_type' =>  "string(4) NOT NULL",
            'defaultname' =>  "string(255) NOT NULL",
            'visible' =>  "string(5) NOT NULL",
            'encrypted' =>  "string(5) NOT NULL",
            'core_attribute' =>  "string(5) NOT NULL",
            'composite_pk' => array('attribute_id', 'attribute_type')
        ), $options);

        $oDB->createCommand()->createIndex('{{idx_participant_attribute_names}}', '{{participant_attribute_names}}', ['attribute_id', 'attribute_type']);
        $aCoreAttributes = array('firstname', 'lastname', 'email');

        // load sodium library
        $sodium = Yii::app()->sodium;
        // check if sodium library exists
        if ($sodium->bLibraryExists === true) {
            $sEncrypted = 'Y';
        } else {
            $sEncrypted = 'N';
        }

        foreach ($aCoreAttributes as $attribute) {
            $oDB->createCommand()->insert('{{participant_attribute_names}}', array(
                'attribute_type'    => 'TB',
                'defaultname'       => $attribute,
                'visible'           => 'TRUE',
                'encrypted'         => $sEncrypted,
                'core_attribute'    => 'Y'
            ));
        }


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('participant_attribute_names', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //participant_attribute_values
        $oDB->createCommand()->createTable('{{participant_attribute_values}}', array(
            'value_id' => "pk",
            'attribute_id' => "integer NOT NULL",
            'value' => "text NOT NULL",
        ), $options);



        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('participant_attribute_values', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //participant_shares
        $oDB->createCommand()->createTable('{{participant_shares}}', array(
            'participant_id' =>  "string(50) NOT NULL",
            'share_uid' =>  "integer NOT NULL",
            'date_added' =>  "datetime NOT NULL",
            'can_edit' =>  "string(5) NOT NULL",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{participant_shares_pk}}', '{{participant_shares}}', ['participant_id', 'share_uid'], false);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('participant_shares', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // permissions
        $oDB->createCommand()->createTable('{{permissions}}', array(
            'id' =>  "pk",
            'entity' =>  "string(50) NOT NULL",
            'entity_id' =>  "integer NOT NULL",
            'uid' =>  "integer NOT NULL",
            'permission' =>  "string(100) NOT NULL",
            'create_p' =>  "integer NOT NULL default 0",
            'read_p' =>  "integer NOT NULL default 0",
            'update_p' =>  "integer NOT NULL default 0",
            'delete_p' =>  "integer NOT NULL default 0",
            'import_p' =>  "integer NOT NULL default 0",
            'export_p' =>  "integer NOT NULL default 0",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_permissions}}', '{{permissions}}', ['entity_id', 'entity', 'permission', 'uid'], true);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('permissions', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // plugins
        $oDB->createCommand()->createTable('{{plugins}}', array(
            'id' =>  "pk",
            'name' =>  "string(50) NOT NULL",
            'plugin_type' =>  "string(6) default 'user'",
            'active' =>  "int NOT NULL default 0",
            'priority' =>  "int NOT NULL default 0",
            'version' =>  "string(32) NULL",
            'load_error' => 'int default 0',
            'load_error_message' => 'text'
        ), $options);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('plugins', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // plugin_settings
        $oDB->createCommand()->createTable('{{plugin_settings}}', array(
            'id' => "pk",
            'plugin_id' => "integer NOT NULL",
            'model' => "string(50) NULL",
            'model_id' => "integer NULL",
            'key' => "string(50) NOT NULL",
            'value' => "text NULL",
        ), $options);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('plugin_settings', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // questions
        $oDB->createCommand()->createTable('{{questions}}', array(
            'qid' =>  "pk",
            'parent_qid' =>  "integer NOT NULL default '0'",
            'sid' =>  "integer NOT NULL default '0'",
            'gid' =>  "integer NOT NULL default '0'",
            'type' =>  "string(30) NOT NULL default 'T'",
            'title' =>  "string(20) NOT NULL default ''",
            'preg' =>  "text",
            'other' =>  "string(1) NOT NULL default 'N'",
            'mandatory' =>  "string(1) NULL",
            'encrypted' =>  "string(1) NULL default 'N'",
            'question_order' =>  "integer NOT NULL",
            'scale_id' =>  "integer NOT NULL default '0'",
            'same_default' =>  "integer NOT NULL default '0'",
            'relevance' =>  "text",
            'modulename' =>  "string(255) NULL"
        ), $options);
        $oDB->createCommand()->createIndex('{{idx1_questions}}', '{{questions}}', 'sid', false);
        $oDB->createCommand()->createIndex('{{idx2_questions}}', '{{questions}}', 'gid', false);
        $oDB->createCommand()->createIndex('{{idx3_questions}}', '{{questions}}', 'type', false);
        $oDB->createCommand()->createIndex('{{idx4_questions}}', '{{questions}}', 'title', false);
        $oDB->createCommand()->createIndex('{{idx5_questions}}', '{{questions}}', 'parent_qid', false);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('questions', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // question language settings
        $oDB->createCommand()->createTable('{{question_l10ns}}', array(
            'id' =>  "pk",
            'qid' =>  "integer NOT NULL",
            'question' =>  "text NOT NULL",
            'help' =>  "TEXT DEFAULT NULL",
            'script' => " TEXT DEFAULT NULL",
            'language' =>  "string(20) NOT NULL"
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_question_ls}}', '{{question_l10ns}}', ['qid', 'language'], true);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('question_l10ns', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // question_attributes
        $oDB->createCommand()->createTable('{{question_attributes}}', array(
            'qaid' => "pk",
            'qid' => "integer NOT NULL default '0'",
            'attribute' => "string(50) NULL",
            'value' => "text NULL",
            'language' => "string(20) NULL",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_question_attributes}}', '{{question_attributes}}', 'qid', false);
        $oDB->createCommand()->createIndex('{{idx2_question_attributes}}', '{{question_attributes}}', 'attribute', false);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('question_attributes', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // quota
        $oDB->createCommand()->createTable('{{quota}}', array(
            'id' => "pk",
            'sid' => "integer NULL",
            'name' => "string(255) NULL",
            'qlimit' => "integer NULL",
            'action' => "integer NULL",
            'active' => "integer NOT NULL default '1'",
            'autoload_url' => "integer NOT NULL default '0'",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_quota}}', '{{quota}}', 'sid', false);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('quota', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //quota_languagesettings
        $oDB->createCommand()->createTable('{{quota_languagesettings}}', array(
            'quotals_id' => "pk",
            'quotals_quota_id' => "integer NOT NULL default '0'",
            'quotals_language' => "string(45) NOT NULL default 'en'",
            'quotals_name' => "string(255) NULL",
            'quotals_message' => "text NOT NULL",
            'quotals_url' => "string(255)",
            'quotals_urldescrip' => "string(255)",
        ), $options);


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('quota_languagesettings', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // quota_members
        $oDB->createCommand()->createTable('{{quota_members}}', array(
            'id' => "pk",
            'sid' => "integer NULL",
            'qid' => "integer NULL",
            'quota_id' => "integer NULL",
            'code' => "string(11) NULL",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_quota_members}}', '{{quota_members}}', ['sid', 'qid', 'quota_id', 'code'], true);



        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('quota_members', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // saved_control
        $oDB->createCommand()->createTable('{{saved_control}}', array(
            'scid' => "pk",
            'sid' => "integer NOT NULL default '0'",
            'srid' => "integer NOT NULL default '0'",
            'identifier' => "text NOT NULL",
            'access_code' => "text NOT NULL",
            'email' => "string(192)",
            'ip' => "text NOT NULL",
            'saved_thisstep' => "text NOT NULL",
            'status' => "string(1) NOT NULL default ''",
            'saved_date' => "datetime NOT NULL",
            'refurl' => "text",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_saved_control}}', '{{saved_control}}', 'sid');
        $oDB->createCommand()->createIndex('{{idx2_saved_control}}', '{{saved_control}}', 'srid');


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('saved_control', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // sessions

        $oDB->createCommand()->createTable('{{sessions}}', array(
            'id' => "string(32) NOT NULL",
            'expire' => "integer NULL",
            'data' => "longbinary",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{sessions_pk}}', '{{sessions}}', 'id');

        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('sessions_pk', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // settings_global

        $oDB->createCommand()->createTable('{{settings_global}}', array(
            'stg_name' =>  "string(50) NOT NULL default ''",
            'stg_value' =>  "text NOT NULL",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{settings_global_pk}}', '{{settings_global}}', 'stg_name');


        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('settings_global', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //settings_user

        $oDB->createCommand()->createTable('{{settings_user}}', array(
            'id' => "pk",
            'uid' => "integer NOT NULL",
            'entity' => "string(15) NULL",
            'entity_id' => "string(31) NULL",
            'stg_name' => "string(63) NOT NULL",
            'stg_value' => "TEXT NULL",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_settings_user}}', '{{settings_user}}', 'uid', false);
        $oDB->createCommand()->createIndex('{{idx2_settings_user}}', '{{settings_user}}', 'entity', false);
        $oDB->createCommand()->createIndex('{{idx3_settings_user}}', '{{settings_user}}', 'entity_id', false);
        $oDB->createCommand()->createIndex('{{idx4_settings_user}}', '{{settings_user}}', 'stg_name', false);



        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('settings_user', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {

        //Surveymenu

        $oDB->createCommand()->createTable('{{surveymenu}}', array(
            'id' => "pk",
            'parent_id' => "integer NULL",
            'survey_id' => "integer NULL",
            'user_id' => "integer NULL",
            'name' => "string(128)",
            'ordering' => "integer NULL DEFAULT '0'",
            'level' => "integer NULL DEFAULT '0'",
            'title' => "string(168)  NOT NULL DEFAULT ''",
            'position' => "string(192)  NOT NULL DEFAULT 'side'",
            'description' => "text ",
            'showincollapse' => 'integer DEFAULT 0',
            'active' => "integer NOT NULL DEFAULT '0'",
            'changed_at' => "datetime",
            'changed_by' => "integer NOT NULL DEFAULT '0'",
            'created_at' => "datetime",
            'created_by' => "integer NOT NULL DEFAULT '0'",
        ), $options);

        $oDB->createCommand()->createIndex('{{surveymenu_name}}', '{{surveymenu}}', 'name', true);
        $oDB->createCommand()->createIndex('{{idx2_surveymenu}}', '{{surveymenu}}', 'title', false);

        $surveyMenuRowData = LsDefaultDataSets::getSurveyMenuData();
        foreach ($surveyMenuRowData as $surveyMenuRow) {
            if (in_array($oDB->getDriverName(), array('mssql', 'sqlsrv', 'dblib'))) {
                unset($surveyMenuRow['id']);
            }
            $oDB->createCommand()->insert("{{surveymenu}}", $surveyMenuRow);
        }
        
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('surveymenu', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // Surveymenu entries

        $oDB->createCommand()->createTable('{{surveymenu_entries}}', array(
            'id' =>  "pk",
            'menu_id' =>  "integer NULL",
            'user_id' =>  "integer NULL",
            'ordering' =>  "integer DEFAULT '0'",
            'name' =>  "string(168)  DEFAULT ''",
            'title' =>  "string(168)  NOT NULL DEFAULT ''",
            'menu_title' =>  "string(168)  NOT NULL DEFAULT ''",
            'menu_description' =>  "text ",
            'menu_icon' =>  "string(192)  NOT NULL DEFAULT ''",
            'menu_icon_type' =>  "string(192)  NOT NULL DEFAULT ''",
            'menu_class' =>  "string(192)  NOT NULL DEFAULT ''",
            'menu_link' =>  "string(192)  NOT NULL DEFAULT ''",
            'action' =>  "string(192)  NOT NULL DEFAULT ''",
            'template' =>  "string(192)  NOT NULL DEFAULT ''",
            'partial' =>  "string(192)  NOT NULL DEFAULT ''",
            'classes' =>  "string(192)  NOT NULL DEFAULT ''",
            'permission' =>  "string(192)  NOT NULL DEFAULT ''",
            'permission_grade' =>  "string(192)  NULL",
            'data' =>  "text ",
            'getdatamethod' =>  "string(192)  NOT NULL DEFAULT ''",
            'language' =>  "string(32)  NOT NULL DEFAULT 'en-GB'",
            'showincollapse' => 'integer DEFAULT 0',
            'active' =>  "integer NOT NULL DEFAULT '0'",
            'changed_at' =>  "datetime NULL",
            'changed_by' =>  "integer NOT NULL DEFAULT '0'",
            'created_at' =>  "datetime NULL",
            'created_by' =>  "integer NOT NULL DEFAULT '0'",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_surveymenu_entries}}', '{{surveymenu_entries}}', 'menu_id', false);
        $oDB->createCommand()->createIndex('{{idx5_surveymenu_entries}}', '{{surveymenu_entries}}', 'menu_title', false);
        $oDB->createCommand()->createIndex('{{surveymenu_entries_name}}', '{{surveymenu_entries}}', 'name', true);
        
        foreach ($surveyMenuEntryRowData=LsDefaultDataSets::getSurveyMenuEntryData() as $surveyMenuEntryRow) {
            if (in_array($oDB->getDriverName(), array('mssql', 'sqlsrv', 'dblib'))) {
                unset($surveyMenuEntryRow['id']);
            }
            $oDB->createCommand()->insert("{{surveymenu_entries}}", $surveyMenuEntryRow);
        }
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('surveymenu_entries', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // surveys
        $oDB->createCommand()->createTable('{{surveys}}', array(
            'sid' => "integer NOT NULL",
            'owner_id' => "integer NOT NULL",
            'gsid' => "integer default '1'",
            'admin' => "string(50) NULL",
            'active' => "string(1) NOT NULL default 'N'",
            'expires' => "datetime NULL",
            'startdate' => "datetime NULL",
            'adminemail' => "string(254) NULL",
            'anonymized' => "string(1) NOT NULL default 'N'",
            'faxto' => "string(20) NULL",
            'format' => "string(1) NULL",
            'savetimings' => "string(1) NOT NULL default 'N'",
            'template' => "string(100) default 'default'",
            'language' => "string(50) NULL",
            'additional_languages' => "string(255) NULL",
            'datestamp' => "string(1) NOT NULL default 'N'",
            'usecookie' => "string(1) NOT NULL default 'N'",
            'allowregister' => "string(1) NOT NULL default 'N'",
            'allowsave' => "string(1) NOT NULL default 'Y'",
            'autonumber_start' => "integer NOT NULL default '0'",
            'autoredirect' => "string(1) NOT NULL default 'N'",
            'allowprev' => "string(1) NOT NULL default 'N'",
            'printanswers' => "string(1) NOT NULL default 'N'",
            'ipaddr' => "string(1) NOT NULL default 'N'",
            'refurl' => "string(1) NOT NULL default 'N'",
            'datecreated' => "datetime",
            'showsurveypolicynotice' => 'int DEFAULT 0',
            'publicstatistics' => "string(1) NOT NULL default 'N'",
            'publicgraphs' => "string(1) NOT NULL default 'N'",
            'listpublic' => "string(1) NOT NULL default 'N'",
            'htmlemail' => "string(1) NOT NULL default 'N'",
            'sendconfirmation' => "string(1) NOT NULL default 'Y'",
            'tokenanswerspersistence' => "string(1) NOT NULL default 'N'",
            'assessments' => "string(1) NOT NULL default 'N'",
            'usecaptcha' => "string(1) NOT NULL default 'N'",
            'usetokens' => "string(1) NOT NULL default 'N'",
            'bounce_email' => "string(254) NULL",
            'attributedescriptions' => "text",
            'emailresponseto' => "text NULL",
            'emailnotificationto' => "text NULL",
            'tokenlength' => "integer NOT NULL default '15'",
            'showxquestions' => "string(1) default 'Y'",
            'showgroupinfo' => "string(1) default 'B'",
            'shownoanswer' => "string(1) default 'Y'",
            'showqnumcode' => "string(1) default 'X'",
            'bouncetime' => "integer",
            'bounceprocessing' => "string(1) default 'N'",
            'bounceaccounttype' => "string(4)",
            'bounceaccounthost' => "string(200)",
            'bounceaccountpass' => "string(100)",
            'bounceaccountencryption' => "string(3)",
            'bounceaccountuser' => "string(200)",
            'showwelcome' => "string(1) default 'Y'",
            'showprogress' => "string(1) default 'Y'",
            'questionindex' => "integer default '0' NOT NULL",
            'navigationdelay' => "integer NOT NULL default '0'",
            'nokeyboard' => "string(1) default 'N'",
            'alloweditaftercompletion' => "string(1) default 'N'",
            'googleanalyticsstyle' => "string(1) NULL",
            'googleanalyticsapikey' => "string(25) NULL",
            'tokenencryptionoptions' => "text NULL",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{surveys_pk}}', '{{surveys}}', 'sid');

        $oDB->createCommand()->createIndex('{{idx1_surveys}}', '{{surveys}}', 'owner_id', false);
        $oDB->createCommand()->createIndex('{{idx2_surveys}}', '{{surveys}}', 'gsid', false);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('surveys', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {

        // surveys_groups
        $oDB->createCommand()->createTable('{{surveys_groups}}', array(
            'gsid' => "pk",
            'name' => "string(45) NOT NULL",
            'title' => "string(100) NULL",
            'template' => "string(128) DEFAULT 'default'",
            'description' => "text ",
            'sortorder' => "integer NOT NULL",
            'owner_id' => "integer NULL",
            'parent_id' => "integer NULL",
            'created' => "datetime NULL",
            'modified' => "datetime NULL",
            'created_by' => "integer NOT NULL"
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_surveys_groups}}', '{{surveys_groups}}', 'name', false);
        $oDB->createCommand()->createIndex('{{idx2_surveys_groups}}', '{{surveys_groups}}', 'title', false);

        foreach ($surveyGroupData=LsDefaultDataSets::getSurveygroupData() as $surveyGroup) {
            $oDB->createCommand()->insert("{{surveys_groups}}", $surveyGroup);
        }

        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('surveys_groups', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // surveys_groupsettings
        $oDB->createCommand()->createTable('{{surveys_groupsettings}}', array(
            'gsid' => "integer NOT NULL",
            'owner_id' => "integer NULL DEFAULT NULL",
            'admin' => "string(50) NULL DEFAULT NULL",
            'adminemail' => "string(254) NULL DEFAULT NULL",
            'anonymized' => "string(1) NOT NULL DEFAULT 'N'",
            'format' => "string(1) NULL DEFAULT NULL",
            'savetimings' => "string(1) NOT NULL DEFAULT 'N'",
            'template' => "string(100) NULL DEFAULT 'default'",
            'datestamp' => "string(1) NOT NULL DEFAULT 'N'",
            'usecookie' => "string(1) NOT NULL DEFAULT 'N'",
            'allowregister' => "string(1) NOT NULL DEFAULT 'N'",
            'allowsave' => "string(1) NOT NULL DEFAULT 'Y'",
            'autonumber_start' => "integer NULL DEFAULT '0'",
            'autoredirect' => "string(1) NOT NULL DEFAULT 'N'",
            'allowprev' => "string(1) NOT NULL DEFAULT 'N'",
            'printanswers' => "string(1) NOT NULL DEFAULT 'N'",
            'ipaddr' => "string(1) NOT NULL DEFAULT 'N'",
            'refurl' => "string(1) NOT NULL DEFAULT 'N'",
            'showsurveypolicynotice' => "integer NULL DEFAULT '0'",
            'publicstatistics' => "string(1) NOT NULL DEFAULT 'N'",
            'publicgraphs' => "string(1) NOT NULL DEFAULT 'N'",
            'listpublic' => "string(1) NOT NULL DEFAULT 'N'",
            'htmlemail' => "string(1) NOT NULL DEFAULT 'N'",
            'sendconfirmation' => "string(1) NOT NULL DEFAULT 'Y'",
            'tokenanswerspersistence' => "string(1) NOT NULL DEFAULT 'N'",
            'assessments' => "string(1) NOT NULL DEFAULT 'N'",
            'usecaptcha' => "string(1) NOT NULL DEFAULT 'N'",
            'bounce_email' => "string(254) NULL DEFAULT NULL",
            'attributedescriptions' => "text NULL",
            'emailresponseto' => "text NULL",
            'emailnotificationto' => "text NULL",
            'tokenlength' => "integer NULL DEFAULT '15'",
            'showxquestions' => "string(1) NULL DEFAULT 'Y'",
            'showgroupinfo' => "string(1) NULL DEFAULT 'B'",
            'shownoanswer' => "string(1) NULL DEFAULT 'Y'",
            'showqnumcode' => "string(1) NULL DEFAULT 'X'",
            'showwelcome' => "string(1) NULL DEFAULT 'Y'",
            'showprogress' => "string(1) NULL DEFAULT 'Y'",
            'questionindex' => "integer NULL DEFAULT '0'",
            'navigationdelay' => "integer NULL DEFAULT '0'",
            'nokeyboard' => "string(1) NULL DEFAULT 'N'",
            'alloweditaftercompletion' => "string(1) NULL DEFAULT 'N'"
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{surveys_groupsettings_pk}}', '{{surveys_groupsettings}}', ['gsid']);

        // insert settings for global level
        $attributes1 = array(
            'gsid' => '0',
            'owner_id' => '1',
            'admin' => 'Administrator',
            'adminemail' => 'your-email@example.net',
            'anonymized' => 'N',
            'format' => 'G',
            'savetimings' => 'N',
            'template' => 'fruity',
            'datestamp' => 'N',
            'usecookie' => 'N',
            'allowregister' => 'N',
            'allowsave' => 'Y',
            'autonumber_start' => '0',
            'autoredirect' => 'N',
            'allowprev' => 'N',
            'printanswers' => 'N',
            'ipaddr' => 'N',
            'refurl' => 'N',
            'showsurveypolicynotice' => '0',
            'publicstatistics' => 'N',
            'publicgraphs' => 'N',
            'listpublic' => 'N',
            'htmlemail' => 'N',
            'sendconfirmation' => 'Y',
            'tokenanswerspersistence' => 'N',
            'assessments' => 'N',
            'usecaptcha' => 'N',
            'tokenlength' => '15',
            'showxquestions' => 'Y',
            'showgroupinfo' => 'B',
            'shownoanswer' => 'Y',
            'showqnumcode' => 'X',
            'showwelcome' => 'Y',
            'showprogress' => 'Y',
            'questionindex' => '0',
            'navigationdelay' => '0',
            'nokeyboard' => 'N',
            'alloweditaftercompletion' => 'N'
        );
        $oDB->createCommand()->insert("{{surveys_groupsettings}}", $attributes1);

        // insert settings for default survey group
        $attributes2 =  array(
                "gsid" => 1,
                "owner_id" => -1,
                "admin" => "inherit",
                "adminemail" => "inherit",
                "anonymized" => "I",
                "format" => "I",
                "savetimings" => "I",
                "template" => "inherit",
                "datestamp" => "I",
                "usecookie" => "I",
                "allowregister" => "I",
                "allowsave" => "I",
                "autonumber_start" => 0,
                "autoredirect" => "I",
                "allowprev" => "I",
                "printanswers" => "I",
                "ipaddr" => "I",
                "refurl" => "I",
                "showsurveypolicynotice" => 0,
                "publicstatistics" => "I",
                "publicgraphs" => "I",
                "listpublic" => "I",
                "htmlemail" => "I",
                "sendconfirmation" => "I",
                "tokenanswerspersistence" => "I",
                "assessments" => "I",
                "usecaptcha" => "E",
                "bounce_email" => "inherit",
                "attributedescriptions" => null,
                "emailresponseto" => "inherit",
                "emailnotificationto" => "inherit",
                "tokenlength" => -1,
                "showxquestions" => "I",
                "showgroupinfo" => "I",
                "shownoanswer" => "I",
                "showqnumcode" => "I",
                "showwelcome" => "I",
                "showprogress" => "I",
                "questionindex" => -1,
                "navigationdelay" => -1,
                "nokeyboard" => "I",
                "alloweditaftercompletion" => "I",
        );
        $oDB->createCommand()->insert("{{surveys_groupsettings}}", $attributes2);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('surveys_groupsettings', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {

        // surveys_languagesettings
        $oDB->createCommand()->createTable('{{surveys_languagesettings}}', array(
            'surveyls_survey_id' => "integer NOT NULL",
            'surveyls_language' => "string(45) NOT NULL DEFAULT 'en'",
            'surveyls_title' => "string(200) NOT NULL",
            'surveyls_description' => "TEXT NULL",
            'surveyls_welcometext' => "TEXT NULL",
            'surveyls_endtext' => "TEXT NULL",
            'surveyls_policy_notice' => "TEXT NULL",
            'surveyls_policy_error' => "TEXT NULL",
            'surveyls_policy_notice_label' => 'string(192) NULL',
            'surveyls_url' => "TEXT NULL",
            'surveyls_urldescription' => "string(255) NULL",
            'surveyls_email_invite_subj' => "string(255) NULL",
            'surveyls_email_invite' => "TEXT NULL",
            'surveyls_email_remind_subj' => "string(255) NULL",
            'surveyls_email_remind' => "TEXT NULL",
            'surveyls_email_register_subj' => "string(255) NULL",
            'surveyls_email_register' => "TEXT NULL",
            'surveyls_email_confirm_subj' => "string(255) NULL",
            'surveyls_email_confirm' => "TEXT NULL",
            'surveyls_dateformat' => "integer NOT NULL DEFAULT 1",
            'surveyls_attributecaptions' => "TEXT NULL",
            'email_admin_notification_subj' => "string(255) NULL",
            'email_admin_notification' => "TEXT NULL",
            'email_admin_responses_subj' => "string(255) NULL",
            'email_admin_responses' => "TEXT NULL",
            'surveyls_numberformat' => "INT NOT NULL DEFAULT 0",
            'attachments' => "text NULL",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{surveys_languagesettings_pk}}', '{{surveys_languagesettings}}', ['surveyls_survey_id', 'surveyls_language']);

        $oDB->createCommand()->createIndex('{{idx1_surveys_languagesettings}}', '{{surveys_languagesettings}}', 'surveyls_title', false);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('surveys_languagesettings', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // survey_links
        $oDB->createCommand()->createTable('{{survey_links}}', array(
            'participant_id' => "string(50) NOT NULL",
            'token_id' => "integer NOT NULL",
            'survey_id' => "integer NOT NULL",
            'date_created' => "datetime",
            'date_invited' => "datetime",
            'date_completed' => "datetime",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{survey_links_pk}}', '{{survey_links}}', ['participant_id','token_id','survey_id']);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('survey_links', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // survey_url_parameters
        $oDB->createCommand()->createTable('{{survey_url_parameters}}', array(
            'id' => "pk",
            'sid' => "integer NOT NULL",
            'parameter' => "string(50) NOT NULL",
            'targetqid' => "integer NULL",
            'targetsqid' => "integer NULL",
        ), $options);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('survey_url_parameters', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {

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
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_templates}}', '{{templates}}', 'name', false);
        $oDB->createCommand()->createIndex('{{idx2_templates}}', '{{templates}}', 'title', false);
        $oDB->createCommand()->createIndex('{{idx3_templates}}', '{{templates}}', 'owner_id', false);
        $oDB->createCommand()->createIndex('{{idx4_templates}}', '{{templates}}', 'extends', false);

        // NOTE: PLEASE DON'T USE ARRAY COMBINE !!! HARD TO READ AND MODIFY !!!!
        $headerArray = ['name','folder','title','creation_date','author','author_email','author_url','copyright','license','version','api_version','view_folder','files_folder',
        'description','last_update','owner_id','extends'];

        foreach ($templateData=LsDefaultDataSets::getTemplatesData() as $template) {
            $oDB->createCommand()->insert("{{templates}}", $template);
        }
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('templates', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
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
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_template_configuration}}', '{{template_configuration}}', 'template_name', false);
        $oDB->createCommand()->createIndex('{{idx2_template_configuration}}', '{{template_configuration}}', 'sid', false);
        $oDB->createCommand()->createIndex('{{idx3_template_configuration}}', '{{template_configuration}}', 'gsid', false);
        $oDB->createCommand()->createIndex('{{idx4_template_configuration}}', '{{template_configuration}}', 'uid', false);

        foreach ($templateConfigurationData=LsDefaultDataSets::getTemplateConfigurationData() as $templateConfiguration) {
            $oDB->createCommand()->insert("{{template_configuration}}", $templateConfiguration);
        }
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('template_configuration', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //tutorials
        $oDB->createCommand()->createTable(
            '{{tutorials}}',
            [
                'tid' =>  'pk',
                'name' =>  'string(128)',
                'title' =>  'string(192)',
                'icon' =>  'string(64)',
                'description' =>  'text',
                'active' =>  'int DEFAULT 0',
                'settings' => 'text',
                'permission' =>  'string(128) NOT NULL',
                'permission_grade' =>  'string(128) NOT NULL'
            ], $options
        );
        $oDB->createCommand()->createIndex('{{idx1_tutorials}}', '{{tutorials}}', 'name', true);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('tutorials', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //tutorial user mapping
        $oDB->createCommand()->createTable('{{map_tutorial_users}}', array(
            'tid' => 'int NOT NULL',
            'uid' => 'int NOT NULL',
            'taken' => 'int DEFAULT 1',
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{map_tutorial_users_pk}}', '{{map_tutorial_users}}', ['uid','tid']);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('map_tutorial_users', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //tutorial entry groups
        $oDB->createCommand()->createTable('{{tutorial_entry_relation}}', array(
            'teid' => 'int NOT NULL',
            'tid' => 'int NOT NULL',
            'uid' => 'int NULL',
            'sid' => 'int NULL',
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{tutorial_entry_relation_pk}}', '{{tutorial_entry_relation}}', ['teid','tid']);
        $oDB->createCommand()->createIndex('{{idx1_tutorial_entry_relation}}', '{{tutorial_entry_relation}}', 'uid', false);
        $oDB->createCommand()->createIndex('{{idx2_tutorial_entry_relation}}', '{{tutorial_entry_relation}}', 'sid', false);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('tutorial_entry_relation', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //tutorial entries
        $oDB->createCommand()->createTable(
            '{{tutorial_entries}}',
            [
                'teid' =>  'pk',
                'ordering' =>  'int',
                'title' =>  'text',
                'content' =>  'text',
                'settings' => 'text'
            ], $options
        );
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('tutorial_entries', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //user_in_groups
        $oDB->createCommand()->createTable('{{user_in_groups}}', array(
            'ugid' => "integer NOT NULL",
            'uid' => "integer NOT NULL",
        ), $options);

        $oDB->createCommand()->addPrimaryKey('{{user_in_groups_pk}}', '{{user_in_groups}}', ['ugid','uid']);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('user_in_groups', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // users
        $oDB->createCommand()->createTable('{{users}}', array(
            'uid' => "pk",
            'users_name' => "string(64) NOT NULL default ''",
            'password' => "text NOT NULL",
            'full_name' => "string(50) NOT NULL",
            'parent_id' => "integer NOT NULL",
            'lang' => "string(20)",
            'email' => "string(192)",
            'htmleditormode' => "string(7) default 'default'",
            'templateeditormode' => "string(7) NOT NULL default 'default'",
            'questionselectormode' => "string(7) NOT NULL default 'default'",
            'one_time_pw' => "text",
            'dateformat' => "integer NOT NULL DEFAULT 1",
            'created' => "datetime",
            'modified' => "datetime",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_users}}', '{{users}}', 'users_name', true);
        $oDB->createCommand()->createIndex('{{idx2_users}}', '{{users}}', 'email', false);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('users', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        //user_groups
        $oDB->createCommand()->createTable('{{user_groups}}', array(
            'ugid' => "pk",
            'name' => "string(20) NOT NULL",
            'description' => "TEXT NOT NULL",
            'owner_id' => "integer NOT NULL",
        ), $options);

        $oDB->createCommand()->createIndex('{{idx1_user_groups}}', '{{user_groups}}', 'name', true);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('user_groups', $e, $oTransaction);
    }

    $oTransaction = $oDB->beginTransaction();
    try {
        // asset version
        $oDB->createCommand()->createTable('{{asset_version}}', array(
            'id' => 'pk',
            'path' => 'text NOT NULL',
            'version' => 'integer NOT NULL',
        ), $options);

        // Install default plugins.
        foreach (LsDefaultDataSets::getDefaultPluginsData() as $plugin) {
            unset($plugin['id']);
            $oDB->createCommand()->insert("{{plugins}}", $plugin);
        }

        // Set database version
        $oDB->createCommand()->insert("{{settings_global}}", ['stg_name'=> 'DBVersion' , 'stg_value' => $databaseCurrentVersion]);
        $oTransaction->commit();
    } catch (Exception $e) {
        handleError('asset_version', $e, $oTransaction);
    }
}
