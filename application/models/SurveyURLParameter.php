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
 * Class SurveyURLParameter
 *
 * @property integer $id
 * @property integer $sid Survey ID
 * @property string $parameter
 * @property integer $targetqid
 * @property integer $targetsqid
 */
class SurveyURLParameter extends LSActiveRecord
{
    /**
     * @inheritdoc
     * @return SurveyURLParameter
     */
    public static function model($className = __CLASS__)
    {
        /** @var self $model */
        $model = parent::model($className);
        return $model;
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return 'id';
    }

    /** @inheritdoc */
    public function tableName()
    {
        return '{{survey_url_parameters}}';
    }
    /** @inheritdoc */
    public function relations()
    {
        return array(
            'survey' => array(self::BELONGS_TO, 'Survey', 'sid', 'together' => true),
            'question' => array(self::BELONGS_TO, 'Question', array('targetqid' => 'qid')),
            'subquestion' => array(self::BELONGS_TO, 'Question', array('targetsqid' => 'qid'))
        );
    }
    /**
     * @param integer $iSurveyID
     * @return mixed
     */
    public function getParametersForSurvey($iSurveyID)
    {
        return Yii::app()->db->createCommand("select '' as act, up.*,q.title, sq.title as sqtitle from {{survey_url_parameters}} up
            left join {{questions}} q on q.qid=up.targetqid
            left join {{questions}} sq on q.qid=up.targetsqid
            where up.sid=:surveyid")
            ->bindParam(":surveyid", $iSurveyID, PDO::PARAM_INT)
            ->query();
    }

    /**
     * @param array $aConditions
     * @return mixed
     */
    public function deleteRecords($aConditions)
    {
        foreach ($aConditions as $sFieldname => $sFieldvalue) {
            Yii::app()->db->createCommand()->where($sFieldname, $sFieldvalue);
        }
        return Yii::app()->db->delete('survey_url_parameters'); // Deletes from token
    }

    /**
     * @param array $aData
     * @return mixed
     */
    public function insertRecord($aData)
    {
        return Yii::app()->db->createCommand()->insert('{{survey_url_parameters}}', $aData);
    }

    /**
     * @return CActiveDataProvider
     */
    public function search()
    {
        $pageSize = Yii::app()->user->getState('pageSize', Yii::app()->params['defaultPageSize']);

        $survey = Survey::model()->findByPk($this->sid);
        $language = $survey->language;

        $criteria = new CDbCriteria();
        $criteria->with = ['question', 'question.questionl10ns', 'subquestion', 'subquestion.questionl10ns' => ['alias' => 'subquestionl10ns']];
        $criteria->together = true;
        $criteria->condition = 't.sid=:surveyid AND (questionl10ns.language=:language OR questionl10ns.language IS NULL) AND (subquestionl10ns.language=:language OR subquestionl10ns.language IS NULL)';
        $criteria->params = (array(':surveyid' => $this->sid, ':language' => $language));
        //$criteria->compare('group_name', $this->group_name, true);

        /*$surveyId = $this->sid;
        $sBaseLanguage = Survey::model()->findByPk($surveyId)->language;
        $aSurveyParameters = SurveyURLParameter::model()->findAll('sid=:sid', [':sid' => $surveyId]);
        
        $rows = [];
        foreach ($aSurveyParameters as $oSurveyParameter) {
            $row = $oSurveyParameter;//->attributes;
            $rows[] = $row;
        }*/

        $sort = new CSort();
        $sort->defaultOrder = array('parameter' => false, 'target_question' => false);
        $sort->attributes = array(
            'parameter' => array(
                'asc' => 'parameter',
                'desc' => 'parameter desc',
            ),
            'target_question' => array(
                'asc' => 'question.title, questionl10ns.question, subquestionl10ns.question',
                'desc' => 'subquestionl10ns.question desc, questionl10ns.question desc, question.title desc',
            ),
        );

        /*$dataProvider = new CArrayDataProvider($rows, array(
            'pagination' => array(
                'pageSize' => $pageSize,
                'pageVar' => 'page'
            ),
        ));*/

        $dataProvider = new CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,

            'sort' => $sort,

            'pagination' => array(
                'pageSize' => $pageSize,
            ),
        ));

        return $dataProvider;
    }

    /**
     * @return string
     */
    public function getButtons()
    {
        $buttons = "<div class='icon-btn-row'>";

        // Edit
        $buttons .= '<button class="btn btn-sm btn-default surveysettings_edit_intparameter" data-id="' . $this->id . '" data-sid="' . $this->sid . '" data-qid="' . $this->targetqid . '" data-sqid="' . $this->targetsqid . '"><i class="fa fa-pencil"></i></button>';

        // Delete
        $buttons .= '<button class="btn btn-sm btn-default surveysettings_delete_intparameter" data-id="' . $this->id . '" data-sid="' . $this->sid . '" data-qid="' . $this->targetqid . '" data-sqid="' . $this->targetsqid . '"><i class="fa fa-trash text-danger"></i></button>';

        $buttons .= "</div>";
        return $buttons;
    }

    public function getQuestionTitle()
    {
        $baseLanguage = Survey::model()->findByPk($this->sid)->language;
        $title = '';
        if ($this->targetqid != '') {
            $title = $this->question->title . ": " . ellipsize(flattenText($this->question->questionl10ns[$baseLanguage]->question, false, true), 43, .70);
        }
        if ($this->targetsqid != '') {
            $title .= (' - ' . ellipsize(flattenText($this->subquestion->questionl10ns[$baseLanguage]->question, false, true), 30, .75));
        }
        return $title;
    }
}
