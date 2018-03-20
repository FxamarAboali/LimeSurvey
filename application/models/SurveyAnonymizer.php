<?php

/**
 * Class SurveyAnonymizer will overwrite the possibly personal data with random un-identifiable data
 */
class SurveyAnonymizer
{
    /** @var Survey */
    protected $survey;

    /** @var string */
    public $error;

    /** @var bool $includeOldTables whether also deleted and old table versions will be anonymized */
    public $includeOldTables = true;

    const ANONYMIZED_STRING = "anonymized";
    const ANONYMIZED_EMAIL = "anonymized@example.com";

    public function __construct($survey)
    {
        if (!($survey instanceof Survey)){
            throw new \Exception("Survey must be an instance of Survey");
        }

        $this->survey = $survey;
    }

    /**
     * @return bool
     */
    public function anonymize(){
        $this->anonymizeTokensTables();
        $this->anonymizeSurveyTables();
        return true;
    }

    private function anonymizeTokensTables() {
        if ($this->survey->hasTokensTable) {
            $this->anonymizeTokensTable($this->survey->tokensTableName);
        }
        if ($this->includeOldTables) {
            if (!empty($this->survey->oldTokensTableNames)) {
                foreach ($this->survey->oldTokensTableNames as $tableName) {
                    $this->anonymizeTokensTable($tableName);
                }
            }
        }
    }

    private function anonymizeSurveyTables() {
        if ($this->survey->hasResponsesTable) {
            $this->anonymizeResponsesTable($this->survey->responsesTableName);
        }

        if ($this->includeOldTables) {
            if (!empty($this->survey->oldResponsesTableNames)) {
                foreach ($this->survey->oldResponsesTableNames as $tableName) {
                    $this->anonymizeTokensTable($tableName);
                }
            }
        }
    }


    private function anonymizeResponsesTable($tableName){
        return $this->anonymizeDynamicTable(SurveyDynamic::class, $tableName);
   }


    private function anonymizeTokensTable($tableName){
        return $this->anonymizeDynamicTable(TokenDynamic::class, $tableName);
    }

    private function anonymizeDynamicTable($dynamicClass, $tableName){

        /** @var LSDynamicRecordInterface $dynamicModel */
        $dynamicModel = $dynamicClass::model($this->survey->primaryKey);
        $valueMap = [];
        foreach ($dynamicModel->personalFieldNames as $fieldName) {
            $valueMap[$fieldName] = self::ANONYMIZED_STRING;
            if ($fieldName == 'email') {
                $valueMap[$fieldName] = self::ANONYMIZED_EMAIL;
            }
        }
        return Yii::app()->db->createCommand()->update($tableName,$valueMap);

    }


}