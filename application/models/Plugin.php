<?php

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

/**
 * This is the model class for table "{{plugins}}".
 *
 * @property integer $id
 * @property string $name
 * @property integer $active
 * @property string $version
 */
class Plugin extends LSActiveRecord
{
    /**
     * @inheritdoc
     * @return Plugin
     */
    public static function model($className = __CLASS__)
    {
        /** @var self $model */
        $model = parent::model($className);
        return $model;
    }

    /** @inheritdoc */
    public function tableName()
    {
        return '{{plugins}}';
    }

    /**
     * Set this plugin as load error in database, and saves the error message.
     * @param array $error Array with 'message' and 'file' keys (as get from error_get_last).
     * @return boolean Update result.
     */
    public function setLoadError(array $error)
    {
        $this->load_error = 1;
        $this->load_error_message = $error['message'] . ' ' . $error['file'];
        return $this->update();
    }

    /**
     * Returns true if this plugin is compatible with this version of LS.
     * @return boolean
     */
    public function isCompatible()
    {
        $config = $this->getConfig();
        $lsVersion = require \Yii::app()->getBasePath() . '/config/version.php';
        foreach ($config->compatibility->version as $pluginVersion) {
            // At least one $v in config.xml must be higher or equal to versionnumber.
            if (version_compare($lsVersion['versionnumber'], $pluginVersion) >= 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return xml
     * @throws Exception if file does not exist.
     */
    public function getConfig()
    {
        $file = $this->getDir() . DIRECTORY_SEPARATOR . 'config.xml';
        if (file_exists($file)) {
            libxml_disable_entity_loader(false);
            $config = simplexml_load_file(realpath($file));
            libxml_disable_entity_loader(true);
            return $config;
        } else {
            throw new \Exception('Missing configuration file for plugin ' . $this->name);
        }
    }

    /**
     * @param Plugin|null $plugin
     * @param string $pluginName
     * @param array $error Array with 'message' and 'file' keys (as get from error_get_last).
     * @return boolean
     */
    public static function setPluginLoadError($plugin, $pluginName, array $error)
    {
        if ($plugin) {
            $result = $plugin->setLoadError($error);
        } else {
            $plugin = new \Plugin();
            $plugin->name = $pluginName;
            $plugin->active = 0;
            $result1 = $plugin->save();
            $result2 = $plugin->setLoadError($error);
            $result = $result1 && $result2;
        }
        return $result;
    }

    /**
     * Get installation folder of this plugin.
     * Installation folder is different for core and
     * user plugins.
     * @return string
     * @throws Exception
     */
    protected function getDir()
    {
        $pluginManager = App()->getPluginManager();
        $alias = $pluginManager->pluginDirs[$this->plugin_type];

        if (empty($alias)) {
            throw new \Exception('Unknown plugin type: ' . json_encode($this->plugin_type));
        }

        $folder = Yii::getPathOfAlias($alias);

        if (empty($folder)) {
            throw new \Exception('Alias has no folder: ' . json_encode($alias));
        }

        // NB: Name is same as plugin folder and plugin main class.
        return $folder . DIRECTORY_SEPARATOR . $this->name;
    }
}
