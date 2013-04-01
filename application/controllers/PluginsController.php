<?php
    /**
     * @property PluginSettingsHelper $PluginSettings
     */
    class PluginsController extends LSYii_Controller 
    {
        public $layout = 'main';
        /**
         * Stored dynamic properties set and unset via __get and __set.
         * @var array of mixed.
         */
        protected $properties = array();

        public function accessRules()
        {
            $rules = array(
                array('allow', 'roles' => array('administrator')),
                array('deny')
            );


            // Note the order; rules are numerically indexed and we want to
            // parents rules to be executed only if ours dont apply.
            return array_merge($rules, parent::accessRules());
        }
        public function actionIndex()
        {
            // Scan the plugins folder.
            $discoveredPlugins = App()->getPluginManager()->scanPlugins();
            
            $installedPlugins = Plugin::model()->findAll();
            
            $installedNames = array_map(function ($installedPlugin) { return $installedPlugin->name; }, $installedPlugins);
            
            // Install newly discovered plugins.
            foreach ($discoveredPlugins as $discoveredPlugin)
            {
                if (!in_array($discoveredPlugin['name'], $installedNames))
                {
                    $plugin = new Plugin();
                    $plugin->name = $discoveredPlugin['name'];
                    $plugin->active = 0;
                    $plugin->save();
                }
            }
            
            $plugins = Plugin::model()->findAll();
            $data = array();
            foreach ($plugins as $plugin)
            {
                $data[] = array(
                    'id' => $plugin->id,
                    'name' => $plugin->name,
                    'description' => $discoveredPlugins[$plugin->name]['description'],
                    'active' => $plugin->active,
                    'new' => !in_array($plugin->name, $installedNames)
                );
            }
            echo $this->render('/plugins/index', compact('data'));
        }
        
         public function actionActivate($id)
        {
            $plugin = Plugin::model()->findByPk($id);
            if (!is_null($plugin)) {
                $status = $plugin->active;
                if ($status == 1) {
                    $result = App()->getPluginManager()->dispatchEvent(new PluginEvent('beforeDeactivate', $this), $plugin->name);
                    if ($result->get('success', true)) {
                        $status = 0;
                    } else {
                        $message = $result->get('message', gT('Failed to deactivate the plugin.'));
                        App()->user->setFlash('pluginActivation', $message);
                        $this->redirect(array('plugins/'));
                    }

                } else {
                    // Load the plugin:
                    App()->getPluginManager()->loadPlugin($plugin->name, $id);
                    $result = App()->getPluginManager()->dispatchEvent(new PluginEvent('beforeActivate', $this), $plugin->name);
                    if ($result->get('success', true)) {
                        $status = 1;
                    } else {
                        $message = $result->get('message', gT('Failed to activate the plugin.'));
                        App()->user->setFlash('pluginActivation', $message);
                        $this->redirect(array('plugins/'));
                    }
                }
                $plugin->active = $status;
                $plugin->save();
            }
            $this->redirect(array('plugins/'));
        }

         public function actionConfigure($id)
         {
             $plugin = Plugin::model()->findByPk($id)->attributes;
             $pluginObject = App()->getPluginManager()->loadPlugin($plugin['name'], $plugin['id']);
             
             if ($plugin === null)
             {
                 /**
                  * @todo Add flash message "Plugin not found".
                  */
                 $this->redirect(array('plugins/'));
             }
             // If post handle data.
             if (App()->request->isPostRequest)
             {
                 if (!is_null(App()->request->getPost('ok'))) {
                    $settings =  $pluginObject->getPluginSettings(false);
                    $save = array();
                    foreach ($settings as $name => $setting)
                    {
                        $save[$name] = App()->request->getPost($name, null);

                    }
                    $pluginObject->saveSettings($save);
                    Yii::app()->user->setFlash('pluginmanager', 'Settings saved');   
                    
                 } else {
                    // Ok buttons was not pressed, assume cancel
                 }
                 $this->forward('plugins/index', true);
             }                
             
             $settings =  $pluginObject->getPluginSettings();
             if (empty($settings)) {
                 // And show a message
                 Yii::app()->user->setFlash('pluginmanager', 'This plugin has no settings');
                 $this->forward('plugins/index', true);
             }
             $this->render('/plugins/configure', compact('settings', 'plugin'));
             
         }
         
         public function filters()
         {
             $filters = array(
                 'accessControl'
             );
             return array_merge(parent::filters(), $filters);
         }
         
         public function __get($property)
         {
             return  $this->properties[$property];
         }
         
         public function __set($property, $value)
         {
             $this->properties[$property] = $value;
         }
         
          
    }
?>
