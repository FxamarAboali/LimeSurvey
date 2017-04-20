<?php

/**
 * Dynamic response timing model.
 */
class Timing extends LSActiveRecord
{
    /** @var array $_models */
    private static $_models = array();

    /** @var CActiveRecordMetaData $_md meta data*/
    private $_md;

    /** @var int|null $surveyId */
    protected $surveyId;

    /**
     * @param string $scenario
     * @param int $iSurveyId
     */
    public function __construct($iSurveyId = null, $scenario = 'insert')
    {

        if (!isset($iSurveyId)) {
            // FIXME this method does not exist
            $iSurveyId = Response::getLastSurveyId();
        }

        $this->surveyId = $iSurveyId;
        parent::__construct($scenario);
    }

    /** @inheritdoc */
    protected function instantiate($attributes)
    {
        $class=get_class($this);
        $model=new $class($this->surveyId, null);
        return $model;
    }

    /**
     * We have a custom implementation here since the parents' implementation
     * does not create a new model for each table name.
     * @param int $iSurveyId
     * @return Response
     * @throws Exception
     */
    public static function model($iSurveyId = null)
    {
        if (is_numeric($iSurveyId)) {
            if (!isset(self::$_models[$iSurveyId])) {
                $model = self::$_models[$iSurveyId] = new self($iSurveyId, null);
                $model->_md = new CActiveRecordMetaData($model);
                $model->attachBehaviors($model->behaviors());
            }
            return self::$_models[$iSurveyId];
        }
        throw new Exception('iSurveyId missing in static call.');
    }


    /** @inheritdoc */
    public function relations() {
        return array(
            'response' => array(self::BELONGS_TO, 'Response', 'id')
        );
    }

    /** @inheritdoc */
    public function tableName() {
        return "{{survey_{$this->surveyId}_timings}}";
    }

    /**
     * Override
     * @inheritdoc
     */
    public function getMetaData()
    {
        if(isset($this->_md)){
            return $this->_md;
        } else{
            /** @var CActiveRecordMetaData $md */
            $md = self::model($this->surveyId)->_md;
            return $this->_md=$md;
        }
    }

}
