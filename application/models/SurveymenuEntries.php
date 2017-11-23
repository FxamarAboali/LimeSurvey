<?php

/**
 * This is the model class for table "{{surveymenu_entries}}".
 *
 * The followings are the available columns in table '{{surveymenu_entries}}':
 * @property integer $id
 * @property integer $menu_id
 * @property integer $user_id
 * @property integer $ordering
 * @property string $title
 * @property string $name
 * @property string $menu_title
 * @property string $menu_description
 * @property string $menu_icon
 * @property string $menu_class
 * @property string $menu_link
 * @property string $action
 * @property string $template
 * @property string $partial
 * @property string $language
 * @property string $permission
 * @property string $permission_grade
 * @property string $classes
 * @property string $data
 * @property string $getdatamethod
 * @property string $changed_at
 * @property integer $changed_by
 * @property string $created_at
 * @property integer $created_by
 * @property integer $active
 *
 * The followings are the available model relations:
 * @property Surveymenu $menu
 */
class SurveymenuEntries extends LSActiveRecord
{
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{surveymenu_entries}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('changed_at', 'required'),
            array('menu_id, user_id, ordering, changed_by, created_by', 'numerical', 'integerOnly'=>true),
            array('title, menu_title, menu_icon, menu_icon_type, menu_class, menu_link, action, template, partial, permission, permission_grade, classes, getdatamethod', 'length', 'max'=>255),
            array('name', 'unique'),
            array('name, menu_description, language, data, created_at', 'safe'),
            // The following rule is used by search().
            // @todo Please remove those attributes that should not be searched.
            array('id, menu_id, user_id, ordering, title, name, menu_title, menu_description, menu_icon, menu_icon_type, menu_class, menu_link, action, template, partial, language, permission, permission_grade, classes, data, getdatamethod, changed_at, changed_by, created_at, created_by', 'safe', 'on'=>'search'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
            'menu' => array(self::BELONGS_TO, 'Surveymenu', 'menu_id', 'together' => true),
            'user' => array(self::BELONGS_TO, 'Users', 'user_id'),
        );
    }

    public static function staticAddMenuEntry($menuId, $menuEntryArray)
    {
        $oSurveymenuEntries = new SurveymenuEntries();

        $oSurveymenuEntries->menu_id = $menuId;

        $oSurveymenuEntries->name = $menuEntryArray['name'];
        $oSurveymenuEntries->title = $menuEntryArray['title'];
        $oSurveymenuEntries->menu_title = $menuEntryArray['menu_title'];
        $oSurveymenuEntries->menu_description = $menuEntryArray['menu_description'];
        $oSurveymenuEntries->menu_icon = $menuEntryArray['menu_icon'];
        $oSurveymenuEntries->menu_icon_type = $menuEntryArray['menu_icon_type'];
        $oSurveymenuEntries->menu_link = $menuEntryArray['menu_link'];

        //permissions [optional]
        $oSurveymenuEntries->permission = isset($menuEntryArray['permission']) ? $menuEntryArray['permission'] : '';
        $oSurveymenuEntries->permission_grade = isset($menuEntryArray['permission_grade']) ? $menuEntryArray['permission_grade'] : '';



        //set data
        $oMenuEntryData = new SurveymenuEntryData();
        $oMenuEntryData->linkExternal = $menuEntryArray['linkExternal'];
        $oMenuEntryData->isActive = $menuEntryArray['hideOnSurveyState'] == 'active' ? true : ($menuEntryArray['hideOnSurveyState'] == 'inactive' ? false : null);


        if (is_array($menuEntryArray['manualParams'])) {
            $oMenuEntryData->linkData = $menuEntryArray['manualParams'];
        } else if ($menuEntryArray['manualParams'] != '') {
            $oMenuEntryData->linkData = json_decode($menuEntryArray['manualParams'], true);
        }

        //pjax optional
        $oMenuEntryData->pjaxed = isset($menuEntryArray['pjaxed']) ? $menuEntryArray['pjaxed'] : true;
        $oSurveymenuEntries->data = $oMenuEntryData->createOptionJson($menuEntryArray['addSurveyId'], $menuEntryArray['addQuestionGroupId'], $menuEntryArray['addQuestionId']);

        $oSurveymenuEntries->changed_at = date('Y-m-d H:i:s');
        $oSurveymenuEntries->changed_by = Yii::app()->user->getId();
        $oSurveymenuEntries->created_at = date('Y-m-d H:i:s');
        $oSurveymenuEntries->created_by = Yii::app()->user->getId();

        $oSurveymenuEntries->save();
        return $oSurveymenuEntries->getPrimaryKey();
    }

    public function reorder()
    {

        $menusWithEntries = SurveymenuEntries::model()->findAll(array(
            'select'=>'t.menu_id',
            'distinct'=>true,
        ));
        foreach ($menusWithEntries as $key=>$menuWithEntry) {
            self::reorderMenu($menuWithEntry->menu_id);
        }
    }

    public static function reorderMenu($menuId)
    {
        $criteriaItems = new CDbCriteria();
        $criteriaItems->compare('menu_id', (int) $menuId, false);
        $criteriaItems->order = 't.ordering ASC';
        $menuEntriesInMenu = SurveymenuEntries::model()->findAll($criteriaItems);

        $statistics =
        Yii::app()->db->createCommand()->select('MIN(ordering) as loworder, MAX(ordering) as highorder, COUNT(id) as count')
                ->from('{{surveymenu_entries}}')
                ->where(['menu_id = :menu_id'], ['menu_id' => (int) $menuId])
                ->queryRow();
        if (($statistics['loworder'] != 1) || ($statistics['highorder'] != $statistics['count'])) {
            $current = 1;
            foreach ($menuEntriesInMenu as $menuEntry) {
                $menuEntry->ordering = $current;
                $menuEntry->save();
                $current++;
            }
        }
    }

    public function onAfterSave($event)
    {
        $criteria = new CDbCriteria();

        $criteria->addCondition(['menu_id = :menu_id']);
        $criteria->addCondition(['ordering = :ordering']);
        $criteria->addCondition(['id!=:id']);
        $criteria->params = ['menu_id' => (int) $this->menu_id, 'ordering' => (int) $this->ordering, 'id'=>(int) $this->id];
        $criteria->limit = 1;

        $collidingMenuEntry = SurveymenuEntries::model()->find($criteria);
        if ($collidingMenuEntry != null) {
            $collidingMenuEntry->ordering = (((int) $collidingMenuEntry->ordering) + 1);
            $collidingMenuEntry->save();

        }
        return parent::onAfterSave($event);
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'menu_id' => 'Menu',
            'user_id' => 'User',
            'ordering' => 'ordering',
            'title' => 'Title',
            'name' => 'name',
            'menu_title' => 'Menu title',
            'menu_description' => 'Menu name',
            'menu_icon' => 'Menu icon',
            'menu_icon_type' => 'Menu icon type',
            'menu_class' => 'Menu class',
            'menu_link' => 'Menu link',
            'action' => 'Action',
            'template' => 'Template',
            'partial' => 'Partial',
            'language' => 'Language',
            'permission' => 'Permission',
            'permission_grade' => 'Permission grade',
            'classes' => 'Classes',
            'data' => 'Data',
            'getdatamethod' => 'Get data method',
            'changed_at' => 'Changed At',
            'changed_by' => 'Changed By',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
        );
    }

    public static function returnCombinedMenuLink($data)
    {
        if ($data->menu_link) {
            return $data->menu_link;
        } else {
            return gt('Action: ').$data->action.', <br/>'
            .gt('Template: ').$data->template.', <br/>'
            .gt('Partial: ').$data->partial;
        }
    }

    public static function returnMenuIcon($data)
    {
        if ($data->menu_icon_type == 'fontawesome') {
            return "<i class='fa fa-".$data->menu_icon."'></i>";
        } else if ($data->menu_icon_type == 'image') {
            return '<img width="60px" src="'.$data->menu_icon.'" />';
        } else {
            return $data->menu_icon_type.'|'.$data->menu_icon;
        }
    }

    public function getMenuIdOptions()
    {
        $criteria = new CDbCriteria;
        if (Yii::app()->getConfig('demoMode') || !Permission::model()->hasGlobalPermission('superadmin', 'read'))
        {
            $criteria->compare('id', '<> 0');
            $criteria->compare('id', '<> 1');
        }
        $oSurveymenus = Surveymenu::model()->findAll($criteria);
        $options = [];
        foreach ($oSurveymenus as $oSurveymenu) {
            $options["".$oSurveymenu->id] = $oSurveymenu->title;
        }
        return $options;
    }

    public function getUserIdOptions()
    {
        $oUsers = User::model()->findAll();
        $options = [
            NULL => gT('All users')
        ];
        foreach ($oUsers as $oUser) {
            //$options[] = "<option value='".$oSurveymenu->id."'>".$oSurveymenu->title."</option>";
            $options[$oUser->uid] = $oUser->full_name;
        }
        //return join('\n',$options);
        return $options;
    }

    public function getUserOptions()
    {

        $oUsers = User::model()->findAll();
        $options = [];
        foreach ($oUsers as $oUser) {
            $options[$oUser->uid] = $oUser->full_name;
        }
        return $options;
    }

    public function getMenuIconTypeOptions()
    {
        return [
            'fontawesome'	=> gT('Fontawesome icon'),
            'image'			=> gT('Image'),
        ];
        // return "<option value='fontawesome'>".gT("FontAwesome icon")."</option>"
        // 		."<option value='image'>".gT('Image')."</option>";
    }

    public function getButtons()
    {
        $buttons = "<div style='white-space: nowrap'>";
        $raw_button_template = ""
            . "<button class='btn btn-default btn-xs %s %s' role='button' data-toggle='tooltip' title='%s' onclick='return false;'>" //extra class //title
            . "<i class='fa fa-%s' ></i>" //icon class
            . "</button>";

        if (Permission::model()->hasGlobalPermission('settings', 'update')) {

            $deleteData = array(
                'action_surveymenuEntries_deleteModal',
                'text-danger',
                gT("Delete this survey menu entry"),
                'trash text-danger'
            );
            $editData = array(
                'action_surveymenuEntries_editModal',
                'text-danger',
                gT("Edit this survey menu entry"),
                'edit'
            );



            $buttons .= vsprintf($raw_button_template, $editData);
            $buttons .= vsprintf($raw_button_template, $deleteData);
        }

        $buttons .= '</div>';

        return $buttons;
    }
    /**
     * @return array
     */
    public function getColumns()
    {
        $cols = array(
            array(
            'name' => 'id',
            'value' => '\'<input type="checkbox" name="id[]" class="action_selectthisentry" value="\'.$data->id.\'" />\'',
            'type' => 'raw',
            'filter' => false
            ),
            array(
                "name" => 'buttons',
                "type" => 'raw',
                "header" => gT("Action"),
                "filter" => false
            ),
            array(
                'name' => 'title',
                'type' => 'raw'
            ),
            array(
                'name' => 'name',
            ),
            array(
                'name' => 'ordering',
            ),
            array(
                'name' => 'menu_title',
            ),
            array(
                'name' => 'menu_description',
            ),
            array(
                'name' => 'menu_icon',
                'value' => 'SurveymenuEntries::returnMenuIcon($data)',
                'type' => 'raw',
                'filter' => false,
            ),
            array(
                'name' => 'menu_class',
            ),
            array(
                'name' => 'menu_link',
                'value' => 'SurveymenuEntries::returnCombinedMenuLink($data)',
                'type' => 'raw'
            ),
            array(
                'name' => 'language',
            ),
            array(
                'name' => 'permission',
                'value' => '$data->permission ? $data->permission."<br/> => ". $data->permission_grade : ""',
                'type' => 'raw'
            ),
            array(
                'name' => 'classes',
                'htmlOptions'=>array('style'=>'white-space: prewrap;'),
                'headerHtmlOptions'=>array('style'=>'white-space: prewrap;'),
            ),
            array(
                'name' => 'data',
                'value' => '$data->data ? "<i class=\'fa fa-info-circle bigIcons\' title=\'".$data->data."\'></i>"
                : ( $data->getdatamethod ? gT("Get data method:")."<br/>".$data->getdatamethod : "")',
                'type' => 'raw',
                'filter' => false,
            ),
            array(
                'name' => 'menu_id',
                'value' => '$data->menu->title." (".$data->menu_id.")"',
                'filter' => $this->getMenuIdOptions()
            ),
            array(
                'name' => 'user_id',
                'value' => '$data->user_id ? $data->user->full_name : "<i class=\'fa fa-minus\'></i>"',
                'type' => 'raw',
                'filter' => $this->getUserOptions()
            )
        );

        return $cols;
    }

    /**
     * @return array
     */
    public function getShortListColumns()
    {
        $cols = array(
            array(
            'name' => 'id',
            ),
            array(
                'name' => 'title',
                'type' => 'raw'
            ),
            array(
                'name' => 'name',
            ),
            array(
                'name' => 'ordering',
            ),
            array(
                'header' => gT('Menu'),
                'value' => ''
                .'"<a class=\"".$data->menu_class."\" title=\"".$data->menu_description."\" data-toggle="tooltip" >'
                .'".SurveymenuEntries::returnMenuIcon($data)." ".$data->menu_title."</a>"',
                'type' => 'raw'
            ),
            array(
                'name' => 'menu_link',
                'value' => 'SurveymenuEntries::returnCombinedMenuLink($data)',
                'type' => 'raw'
            ),
            array(
                'name' => 'language',
            ),
            array(
                'name' => 'permission',
                'value' => '$data->permission ? $data->permission."<br/> => ". $data->permission_grade : ""',
                'type' => 'raw'
            ),
            array(
                'name' => 'menu_id',
                'value' => '$data->menu->title',
            )
        );

        return $cols;
    }
    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function search()
    {
        // @todo Please modify the following code to remove attributes that should not be searched.

        $criteria = new CDbCriteria;

        //Don't show main menu when not superadmin
        if (Yii::app()->getConfig('demoMode') || !Permission::model()->hasGlobalPermission('superadmin', 'read'))
        {
            $criteria->compare('menu_id', '<> 1');
            $criteria->compare('menu_id', '<> 2');
        }

        $criteria->compare('id', $this->id);
        $criteria->compare('menu_id', $this->menu_id);
        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('ordering', $this->ordering);
        $criteria->compare('title', $this->title, true);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('menu_title', $this->menu_title, true);
        $criteria->compare('menu_description', $this->menu_description, true);
        $criteria->compare('menu_icon', $this->menu_icon, true);
        $criteria->compare('menu_class', $this->menu_class, true);
        $criteria->compare('menu_link', $this->menu_link, true);
        $criteria->compare('action', $this->action, true);
        $criteria->compare('template', $this->template, true);
        $criteria->compare('partial', $this->partial, true);
        $criteria->compare('language', $this->language, true);
        $criteria->compare('permission', $this->permission, true);
        $criteria->compare('permission_grade', $this->permission_grade, true);
        $criteria->compare('classes', $this->classes, true);
        $criteria->compare('data', $this->data, true);
        $criteria->compare('getdatamethod', $this->getdatamethod, true);
        $criteria->compare('changed_at', $this->changed_at, true);
        $criteria->compare('changed_by', $this->changed_by);
        $criteria->compare('created_at', $this->created_at, true);
        $criteria->compare('created_by', $this->created_by);

        return new CActiveDataProvider($this, array(
            'criteria'=>$criteria,
            'sort'=>array(
                'defaultOrder'=>'t.menu_id ASC, t.ordering ASC',
            )
        ));
    }

    /**
     * Method to restore the default surveymenu entries
     * This method will fail if the surveymenus have been tempered, or wrongly set
     *
     * @return boolean
     */
    public function restoreDefaults()
    {
        $oDB = Yii::app()->db;
        $oTransaction = $oDB->beginTransaction();
        try {

            $oDB->createCommand()->truncateTable('{{surveymenu_entries}}');

            $headerArray = ['menu_id', 'user_id', 'ordering', 'name', 'title', 'menu_title', 'menu_description', 'menu_icon', 'menu_icon_type', 'menu_class', 'menu_link', 'action', 'template', 'partial', 'classes', 'permission', 'permission_grade', 'data', 'getdatamethod', 'language', 'active', 'changed_at', 'changed_by', 'created_at', 'created_by'];
            $basicMenues = [
            [1, NULL, 1, 'overview', 'Survey overview', 'Overview', 'Open general survey overview and quick action', 'list', 'fontawesome', '', 'admin/survey/sa/view', '', '', '', '', '', '', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 2, 'generalsettings', 'Edit survey general settings', 'General settings', 'Open general survey settings', 'gears', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_generaloptions_panel', '', 'surveysettings', 'read', NULL, '_generalTabEditSurvey', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 3, 'surveytexts', 'Edit survey text elements', 'Survey texts', 'Edit survey text elements', 'file-text-o', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/tab_edit_view', '', 'surveylocale', 'read', NULL, '_getTextEditData', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 4, 'theme_options', 'Theme options', 'Theme options', 'Edit theme options for this survey', 'paint-brush', 'fontawesome', '', 'admin/themeoptions/sa/updatesurvey', '', '', '', '', 'themes', 'read', '{"render": {"link": { "data": {"surveyid": ["survey","sid"], "gsid":["survey","gsid"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 5, 'participants', 'Survey participants', 'Survey participants', 'Go to survey participant and token settings', 'user', 'fontawesome', '', 'admin/tokens/sa/index/', '', '', '', '', 'surveysettings', 'update', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 6, 'presentation', 'Presentation &amp; navigation settings', 'Presentation', 'Edit presentation and navigation settings', 'eye-slash', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_presentation_panel', '', 'surveylocale', 'read', NULL, '_tabPresentationNavigation', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 7, 'publication', 'Publication and access control settings', 'Publication &amp; access', 'Edit settings for publicationa and access control', 'key', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_publication_panel', '', 'surveylocale', 'read', NULL, '_tabPublicationAccess', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 8, 'surveypermissions', 'Edit surveypermissions', 'Survey permissions', 'Edit permissions for this survey', 'lock', 'fontawesome', '', 'admin/surveypermission/sa/view/', '', '', '', '', 'surveysecurity', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 9, 'tokens', 'Token handling', 'Participant tokens', 'Define how tokens should be treated or generated', 'users', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_tokens_panel', '', 'surveylocale', 'read', NULL, '_tabTokens', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 10, 'quotas', 'Edit quotas', 'Survey quotas', 'Edit quotas for this survey.', 'tasks', 'fontawesome', '', 'admin/quotas/sa/index/', '', '', '', '', 'quotas', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 11, 'assessments', 'Edit assessments', 'Assessments', 'Edit and look at the assessements for this survey.', 'comment-o', 'fontawesome', '', 'admin/assessments/sa/index/', '', '', '', '', 'assessments', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 12, 'notification', 'Notification and data management settings', 'Data management', 'Edit settings for notification and data management', 'feed', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_notification_panel', '', 'surveylocale', 'read', NULL, '_tabNotificationDataManagement', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 13, 'emailtemplates', 'Email templates', 'Email templates', 'Edit the templates for invitation, reminder and registration emails', 'envelope-square', 'fontawesome', '', 'admin/emailtemplates/sa/index/', '', '', '', '', 'assessments', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 14, 'panelintegration', 'Edit survey panel integration', 'Panel integration', 'Define panel integrations for your survey', 'link', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_integration_panel', '', 'surveylocale', 'read', '{"render": {"link": { "pjaxed": false}}}', '_tabPanelIntegration', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [1, NULL, 15, 'resources', 'Add/Edit resources to the survey', 'Resources', 'Add/Edit resources to the survey', 'file', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_resources_panel', '', 'surveylocale', 'read', NULL, '_tabResourceManagement', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 1, 'activateSurvey', 'Activate survey', 'Activate survey', 'Activate survey', 'play', 'fontawesome', '', 'admin/survey/sa/activate', '', '', '', '', 'surveyactivation', 'update', '{\"render\": {\"isActive\": false, \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 2, 'deactivateSurvey', 'Stop this survey', 'Stop this survey', 'Stop this survey', 'stop', 'fontawesome', '', 'admin/survey/sa/deactivate', '', '', '', '', 'surveyactivation', 'update', '{\"render\": {\"isActive\": true, \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 3, 'testSurvey', 'Go to survey', 'Go to survey', 'Go to survey', 'cog', 'fontawesome', '', 'survey/index/', '', '', '', '', '', '', '{\"render\"\: {\"link\"\: {\"external\"\: true, \"data\"\: {\"sid\"\: [\"survey\",\"sid\"], \"newtest\"\: \"Y\", \"lang\"\: [\"survey\",\"language\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 4, 'listQuestions', 'List questions', 'List questions', 'List questions', 'list', 'fontawesome', '', 'admin/survey/sa/listquestions', '', '', '', '', 'surveycontent', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 5, 'listQuestionGroups', 'List question groups', 'List question groups', 'List question groups', 'th-list', 'fontawesome', '', 'admin/survey/sa/listquestiongroups', '', '', '', '', 'surveycontent', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 6, 'generalsettings', 'Edit survey general settings', 'General settings', 'Open general survey settings', 'gears', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_generaloptions_panel', '', 'surveysettings', 'read', NULL, '_generalTabEditSurvey', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 7, 'surveypermissions', 'Edit surveypermissions', 'Survey permissions', 'Edit permissions for this survey', 'lock', 'fontawesome', '', 'admin/surveypermission/sa/view/', '', '', '', '', 'surveysecurity', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 8, 'quotas', 'Edit quotas', 'Survey quotas', 'Edit quotas for this survey.', 'tasks', 'fontawesome', '', 'admin/quotas/sa/index/', '', '', '', '', 'quotas', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 9, 'assessments', 'Edit assessments', 'Assessments', 'Edit and look at the assessements for this survey.', 'comment-o', 'fontawesome', '', 'admin/assessments/sa/index/', '', '', '', '', 'assessments', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 10, 'emailtemplates', 'Email templates', 'Email templates', 'Edit the templates for invitation, reminder and registration emails', 'envelope-square', 'fontawesome', '', 'admin/emailtemplates/sa/index/', '', '', '', '', 'surveylocale', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 11, 'surveyLogicFile', 'Survey logic file', 'Survey logic file', 'Survey logic file', 'sitemap', 'fontawesome', '', 'admin/expressions/sa/survey_logic_file/', '', '', '', '', 'surveycontent', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 12, 'tokens', 'Token handling', 'Participant tokens', 'Define how tokens should be treated or generated', 'user', 'fontawesome', '', '', 'updatesurveylocalesettings', 'editLocalSettings_main_view', '/admin/survey/subview/accordion/_tokens_panel', '', 'surveylocale', 'read', '{\"render\": { \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '_tabTokens', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 13, 'cpdb', 'Central participant database', 'Central participant database', 'Central participant database', 'users', 'fontawesome', '', 'admin/participants/sa/displayParticipants', '', '', '', '', 'tokens', 'read', '{render\: {\"link\"\: {}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 14, 'responses', 'Responses', 'Responses', 'Responses', 'icon-browse', 'iconclass', '', 'admin/responses/sa/browse/', '', '', '', '', 'responses', 'read', '{\"render\"\: {\"isActive\"\: true}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 15, 'statistics', 'Statistics', 'Statistics', 'Statistics', 'bar-chart', 'fontawesome', '', 'admin/statistics/sa/index/', '', '', '', '', 'statistics', 'read', '{\"render\"\: {\"isActive\"\: true}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            [2, NULL, 16, 'reorder', 'Reorder questions/question groups', 'Reorder questions/question groups', 'Reorder questions/question groups', 'icon-organize', 'iconclass', '', 'admin/survey/sa/organize/', '', '', '', '', 'surveycontent', 'update', '{\"render\": {\"isActive\": false, \"link\": {\"data\": {\"surveyid\": [\"survey\",\"sid\"]}}}}', '', 'en-GB', 1, date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), 0],
            ];

            foreach ($basicMenues as $basicMenu) {
                $oDB->createCommand()->insert("{{surveymenu_entries}}", array_combine($headerArray, $basicMenu));
            }

            $oTransaction->commit();

        } catch (Exception $e) {
            throw $e;
            return false;
        }

        return true;
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return SurveymenuEntries the static model class
     */
    public static function model($className = __CLASS__)
    {
        /** @var self $model */
        $model = parent::model($className);
        return $model;
    }
}
