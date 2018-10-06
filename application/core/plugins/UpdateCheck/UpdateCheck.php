<?php

/**
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
 * Plugin to check for extension updates after a super admin logs in.
 * Uses the ExtensionInstaller library.
 *
 * @since 2018-10-04
 * @author Olle Haerstedt
 */
class UpdateCheck extends PluginBase
{

    /**
     * Where to save plugin settings etc.
     * @var string
     */
    protected $storage = 'DbStorage';

    /**
     * @return void
     */
    public function init()
    {
        $this->subscribe('afterSuccessfulLogin');
        $this->subscribe('beforeControllerAction');
    }

    /**
     * @return void
     */
    public function afterSuccessfulLogin()
    {
        if (Permission::model()->hasGlobalPermission('superadmin')) {
            // NB: $nextCheck will be set to "now" if next_extension_update_check is empty.
            // Hence it needs to be initialised *before* today.
            $nextCheck = new DateTime($this->get('next_extension_update_check'));
            $today = new DateTime("now");
            if ($nextCheck <= $today) {
                // Set flag.
                Yii::app()->session['do_extensions_update_check'] = true;
            }
        }
    }

    /**
     * @return void
     */
    public function beforeControllerAction()
    {
        $controller = $this->getEvent()->get('controller');
        $doUpdateCheckFlag = Yii::app()->session['do_extensions_update_check'];

        if ($controller == 'admin' && $doUpdateCheckFlag) {

            // Render some JavaScript that will Ajax call update check.
            $this->spitOutUrl();
            $this->registerScript();

            // Unset flag.
            Yii::app()->session['do_extensions_update_check'] = false;

            // Set date for next check.
            $today = new DateTime("now");
            $this->set('next_extension_update_check', $today->add(new DateInterval('P1D'))->format('Y-m-d H:i:s'));
        }
    }

    /**
     * Used to check for available updates for all plugins.
     * This method should be run at super admin login, max once every day.
     * Run by Ajax to avoid increased page load time.
     * @return void
     */
    public function checkAll()
    {
        $service = \Yii::app()->extensionUpdaterServiceLocator;

        // Get one updater class for each extension.
        list($updaters, $errors) = $service->getAllUpdaters();

        /** @var string[] */
        $messages = [];

        /** @var boolean */
        $foundSecurityVersion = false;

        foreach ($updaters as $updater) {
            try {
                $versions = $updater->fetchVersions();
                if ($updater->foundSecurityVersion($versions)) {
                    $foundSecurityVersion = true;
                }
                if ($versions) {
                    $messages[] = $updater->getVersionMessage($versions);
                }
            } catch (\Throwable $ex) {
                $errors[] = $updater->getExtensionName() . ': ' . $ex->getMessage();
            }
        }

        // Compose notification.
        if ($messages || $errors) {
            $superadmins = User::model()->getSuperAdmins();
            $title        = $foundSecurityVersion ? gT('Security updates available') : gT('Updates available');
            $displayClass = $foundSecurityVersion ? 'danger' : '';
            $importance   = $foundSecurityVersion ? Notification::HIGH_IMPORTANCE : Notification::NORMAL_IMPORTANCE;
            $message = implode($messages);
            if ($errors) {
                $message .= '<hr/><i class="fa fa-warning"></i>&nbsp;'
                    . gT('Errors happened during the update check. Please notify the extension authors for support.')
                    . '<ul>'
                    . '<li>' . implode('</li><li>', $errors) . '</li>';
            }
            UniqueNotification::broadcast(
                [
                    'title'         => $title,
                    'display_class' => $displayClass,
                    'message'       => $message,
                    'importance'    => $importance
                ],
                $superadmins
            );
        }
    }

    /**
     * @return void
     */
    protected function spitOutUrl()
    {
        $data = [
            'url' => Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa'     => 'ajax',
                    'plugin' => 'updateCheck',
                    'method' => 'checkAll'
                )
            ),
            'notificationUpdateUrl' => Notification::getUpdateUrl()
        ];
        echo $this->api->renderTwig(__DIR__ . '/views/index.twig', $data);
    }

    /**
     * @return void
     */
    protected function registerScript()
    {
        $assetsUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/js');
        Yii::app()->clientScript->registerScriptFile($assetsUrl . '/updateCheck.js');
    }
}
