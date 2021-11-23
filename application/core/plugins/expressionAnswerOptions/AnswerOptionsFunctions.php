<?php
/**
 * This file is part of ExpressionAnswerOptions plugin
 * @version 0.2.0
 */

namespace expressionAnswerOptions;

use LimeExpressionManager;
use Question;
use Answer;

class AnswerOptionsFunctions
{
    /**
     * Return the answer text related to a question
     * @param integer|string $qid : id or code of question, get parent question if needed 
     * @param string $code : code of the answer text to return
     * @return null|string
     */
    public static function getAnswerOptionText($qidortitle, $code, $scale = 0)
    {
        $surveyId = LimeExpressionManager::getLEMsurveyId();
        $oQuestion = null;
        if (is_int($qidortitle) || ctype_digit($qidortitle)) { // self.qid is not an int …
            $oQuestion = Question::model()->find("qid = :qid and sid = :sid", array(":qid"=>$qidortitle, ":sid" => $surveyId));
            if ($oQuestion && $oQuestion->parent_qid) {
                $oQuestion = Question::model()->find("qid = :qid and sid = :sid", array(":qid"=>$oQuestion->parent_qid, ":sid" => $surveyId));
            }
        }
        
        if(empty($oQuestion)) {
            $oQuestion = Question::model()->find("title = :title and sid = :sid", array(":title"=>$qidortitle, ":sid" => $surveyId));
        }
        if(empty($oQuestion)) {
            if (Permission::model()->hasSurveyPermission($surveyId, 'surveycontent')) { // update ???
                return sprintf(gT("Invalid question code or id “%s”"), CHtml::encode($qidortitle));
            }
            return null;
        }
        /* Don't check the question type : we know it's a question (not a subquestion) */
        $language = LimeExpressionManager::getEMlanguage(); // Or by App()->getLanguage(), em for expression file view ?
        $answer = Answer::model()->getAnswerFromCode($oQuestion->qid, $code, $language, $scale);
        if (is_null($answer) && Permission::model()->hasSurveyPermission($surveyId, 'surveycontent')) {
            return sprintf(gT("Invalid answer option code “%s”"), CHtml::encode($code));
        }
        return $answer;
    }
}
