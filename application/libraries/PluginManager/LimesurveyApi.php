<?php 
    /**
    * Class exposing a Limesurvey API to plugins.
    * This class is instantiated by the plugin manager,
    * plugins can obtain it by calling getAPI() on the plugin manager.
    */
    class LimesurveyApi
    {
        /**
         * Generates the real table name from plugin and tablename.
         * @param iPlugin $plugin
         * @param string $tableName
         */
        protected function getTableName(iPlugin $plugin, $tableName)
        {
            return App()->getDb()->tablePrefix . $plugin->getName() . "_$tableName";
        }
        /**
        * Sets a flash message to be shown to the user.
        * @param html $message
        */
        public function setFlash($message, $key ='api')
        {
            // @todo Remove direct session usage.
            Yii::app()->user->setFlash($key, $message);
        }

        /**
        * Builds and executes a SQL statement for creating a new DB table.
        * @param mixed $plugin The plugin object, id or name.
        * @param string $sTableName the name of the table to be created. The name will be properly quoted and prefixed by the method.
        * @param array $aColumns the columns (name=>definition) in the new table.
        * @param string $sOptions additional SQL fragment that will be appended to the generated SQL.
        * @return integer number of rows affected by the execution.
        */        
        public function createTable($plugin, $sTableName, $aColumns, $sOptions=null)
        {
            if (null !== $sTableName = $this->getTableName($plugin, $sTableName))
            {
                return App()->getDb()->createCommand()->createTable($sTableName,$aColumns,$sOptions);
            }
            return false;
        }

        /**
         * Gets an activerecord object associated to the table.
         * @param iPlugin $plugin
         * @param string $sTableName
         * @return PluginDynamic
         */
        public function getTable(iPlugin $plugin, $sTableName)
        {
            if (null !== $table = $this->getTableName($plugin, $sTableName))
            {
                return PluginDynamic::model($table);
            }
        }

        /**
         * Creates a new active record object instance.
         * @param iPlugin $plugin
         * @param string $sTableNamem
         * @param string $scenario
         * @return PluginDynamic
         */
        public function newModel(iPlugin $plugin, $sTableName, $scenario = 'insert')
        {
            if (null !== $table = $this->getTableName($plugin, $sTableName))
            {
                return new PluginDynamic($table, $scenario);
            }
        }

        /**
        * Check if a table does exist in the database
        * @param mixed $plugin
        * @param string $sTableName Table name to check for (without dbprefix!))
        * @return boolean True or false if table exists or not
        */
        public function tableExists(iPlugin $plugin, $sTableName)
        {
            $sTableName =  $this->getTableName($plugin, $sTableName);
            return isset($sTableName) && in_array($sTableName, App()->getDb()->getSchema()->getTableNames());
        }

        /**
        * Evaluates an expression via Expression Manager
        * Uses the current context.
        * @param string $expression
        * @return string
        */
        public function EMevaluateExpression($expression)
        {
            $result = LimeExpressionManager::ProcessString($expression);
            return $result;
        }
        
        /**
         * Get the current request object
         * 
         * @return LSHttpRequest
         */
        public function getRequest()
        {
            return App()->getRequest();
        }

        /**
        * Gets a survey response from the database.
        * 
        * @param int $surveyId
        * @param int $responseId
        */
        public function getResponse($surveyId, $responseId)
        {
            $response = Survey_dynamic::model($surveyId)->findByPk($responseId)->attributes;

            // Now map the response to the question codes if possible, duplicate question codes will result in the
            // old sidXgidXqid code for the second time the code is found
            $fieldmap = createFieldMap($surveyId, 'full',null, false, $response['startlanguage']);
            $output = array();
            foreach($response as $key => $value)
            {
                $newKey = $key;
                if (array_key_exists($key, $fieldmap)) {
                    if (array_key_exists('title', $fieldmap[$key]))
                    {
                        $code = $fieldmap[$key]['title'];
                        // Add subquestion code if needed
                        if (array_key_exists('aid', $fieldmap[$key]) && !empty($fieldmap[$key]['aid'])) {
                            $code .= '_' . $fieldmap[$key]['aid'];
                        }
                        // Only add if the code does not exist yet and is not empty
                        if (!empty($code) && !array_key_exists($code, $output)) {
                            $newKey = $code;
                        }
                    }
                }
                $output[$newKey] = $value;                    
            }

            // And return the mapped response, to further enhance we could add a method to the api that provides a 
            // simple sort of fieldmap that returns qcode index array with group, question, subquestion, 
            // possible answers, maybe even combined with relevance info so a plugin can handle display of the response
            return $output;
        }

        /**
        * Gets a key value list using the group name as value and the group id
        * as key.
        * @param type $surveyId
        * @return type
        */
        public function getGroupList($surveyId)
        {
            $result = Groups::model()->findListByAttributes(array('sid' => $surveyId), 'group_name');
            return $result;
        }
        
        /**
        * Retrieves user details for the currently logged in user
        * Returns false if the user is not logged and returns null if the user does not exist anymore for some reason (should not really happen)
        * @return User
        */
        public function getCurrentUser(){
            if (Yii::app()->session['loginID'])
            {
                return User::model()->findByPk(Yii::app()->session['loginID']);
            }
            return false;
        }
        /**
         * Gets an array of old response tables for a survey.
         * @param int $surveyId
         */
        public function getOldResponseTables($surveyId)
        {
            $tables = array();
            $base = App()->getDb()->tablePrefix . 'survey_' . $surveyId;
            foreach (App()->getDb()->getSchema()->getTableNames() as $table)
            {
                if (strpos($table, $base) === 0)
                $tables = $table;
            }
            return $tables;
        }
        /**
        * Retrieves user details for a user
        * Returns null if the user does not exist anymore for some reason (should not really happen)
        * @return User
        */
        public function getUser($iUserID){
            return User::model()->findByPk($iUserID);
        }

        
        /**
        * Retrieves user permission details for a user
        * @param $iUserID int The User ID
        * @param  $iSurveyID int The related survey IF for survey permissions - if 0 then global permissions will be retrieved
        * Returns null if the user does not exist anymore for some reason (should not really happen)
        * @return User
        */
        public function getUserPermissionSet($iUserID, $iSurveyID=0){
            return Permission::model()->getPermissions($iUserID,$iSurveyID);
        }        
        
        /**
        * Retrieves Participant data
        * @param $iParticipantID int The Participant ID
        * Returns null if the user does not exist anymore for some reason (should not really happen)
        * @return User
        */
        public function getParticipant($iParticipantID){
            return Participant::model()->findByPk($iParticipantID);
        }         
        
    }

?>