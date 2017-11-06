<?php
/**
 *  LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

namespace ls\tests\controllers;

use ls\tests\TestBaseClassView;

/**
 * Class AdminViewsTest
 * This test loops through all basic admin view pages and cheks if they open withour errors
 *
 * @package ls\tests
 * @group adminviews
 */
class AdminViewsTest extends TestBaseClassView
{

    public function addBaseViews()
    {
        return require __DIR__."/../data/views/adminBaseViews.php";
    }

    public function addSurveyViews()
    {
        return require __DIR__."/../data/views/adminSurveyViews.php";
    }

    public function addSettingsViews()
    {
        return require __DIR__."/../data/views/adminSettingsViews.php";
    }

    public function addUsersViews()
    {
        return require __DIR__."/../data/views/adminUsersViews.php";
    }

    /**
     * @param string $name
     * @param array$view
     * @dataProvider addBaseViews
     */
    public function testAdminViews($name, $view)
    {
        if ($name=='login') {
            // skip login
            $this->assertTrue(true);
            return;
        }
        $this->findViewTag($name, $view);
    }

    /**
     * @param string $name
     * @param array$view
     * @dataProvider addSurveyViews
     */
    public function testAdminSurveyViews($name, $view)
    {
        if (isset($view['import_id'])) {
            $surveyFile = __DIR__ . '/../data/surveys/limesurvey_survey_'.$view['import_id'].'.lss';
            self::importSurvey($surveyFile);
        }
        $view['route'] = ReplaceFields($view['route'], ['{SID}'=>self::$surveyId]);
        $this->findViewTag($name, $view);
    }

    /**
     * @param string $name
     * @param array$view
     * @dataProvider addSettingsViews
     */
    public function testSettingsViews($name, $view)
    {
        $this->findViewTag($name, $view);
    }

    /**
     * @param string $name
     * @param array$view
     * @dataProvider addUsersViews
     */
    public function testUsersViews($name,$view){
        // use Admin user
        $uid = 1;
        // non-adminuser for some views
        if(in_array($name,['setUserPermissions','setUserTemplates'])){
            $uid = 2;
        }
        $view['route'] = ReplaceFields($view['route'],['{UID}'=>$uid]);
        $this->findViewTag($name, $view);
    }
}
