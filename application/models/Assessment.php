<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/*
   * LimeSurvey
   * Copyright (C) 2013 The LimeSurvey Project Team / Carsten Schmitz
   * All rights reserved.
   * License: GNU/GPL License v2 or later, see LICENSE.php
   * LimeSurvey is free software. This version may have been modified pursuant
   * to the GNU General Public License, and as distributed it includes or
   * is derivative of works licensed under the GNU General Public License or
   * other free or open source software licenses.
   * See COPYRIGHT.php for copyright notices and details.
   *
     *	Files Purpose: lots of common functions
*/

/**
 * Class Assessment
 *
 * @property integer $id Primary key
 * @property integer $sid Survey id
 * @property integer $gid Group id
 * @property string $scope
 * @property string $name
 * @property string $minimum
 * @property string $maximum
 * @property string $message
 * @property string $language
 */
class Assessment extends LSActiveRecord
{
    /**
     * @inheritdoc
     * @return Assessment
     */
    public static function model($class = __CLASS__)
    {
        /** @var self $model */
        $model = parent::model($class);
        return $model;
    }

    /** @inheritdoc */
    public function rules()
    {
        return array(
            array('name,message', 'LSYii_Validators'),
        );
    }

    /** @inheritdoc */
    public function tableName()
    {
        return '{{assessments}}';
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return array('id', 'language');
    }

    /**
     * @param array $data
     * @return Assessment
     */
    public static function insertRecords($data)
    {
        $assessment = new self;

        foreach ($data as $k => $v) {
                    $assessment->$k = $v;
        }
        $assessment->save();

        return $assessment;
    }

    /**
     * @param integer $id
     * @param integer $iSurveyID
     * @param string $language
     * @param array $data
     */
    public static function updateAssessment($id, $iSurveyID, $language, array $data)
    {
        $assessment = self::model()->findByAttributes(array('id' => $id, 'sid'=> $iSurveyID, 'language' => $language));
        if (!is_null($assessment)) {
            foreach ($data as $k => $v) {
                            $assessment->$k = $v;
            }
            $assessment->save();
        }
    }
}
