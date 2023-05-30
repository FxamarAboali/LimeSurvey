<?php

namespace LimeSurvey\Models\Services;

use Survey,
QuestionGroup,
LimeExpressionManager,
EmCacheHelper;


/**
 * Expression Manager Service
 *
 */
class ExpressionManager
{
    private ?Survey $modelSurvey = null;
    private ?QuestionGroup $modelQuestionGroup = null;

    public function __construct(Survey $modelSurvey, QuestionGroup $modelQuestionGroup)
    {
        $this->modelSurvey = $modelSurvey;
        $this->modelQuestionGroup = $modelQuestionGroup;
    }

    /**
     * Reset Survey Expression Manager State
     *
     * This was originally located in the admin controller Database::resetEM().
     * The use of static methods make it impossible to inject LimeExpressionManager
     * as a dependency to enable testability. LimeExpressionManager needs to be
     * refactored to make it injectable as a dependency and to make its dependencies
     * injectable. This is a big task which I don't have time to tackle right now.
     * kfoster (2023-05-30)
     *
     * @param int $surveyId
     * @return void
     */
    public function reset($surveyId)
    {
        $oSurvey = $this->modelSurvey->findByPk($surveyId);
        // UpgradeConditionsToRelevance SetDirtyFlag too
        LimeExpressionManager::SetDirtyFlag();
        LimeExpressionManager::UpgradeConditionsToRelevance(
            $this->iSurveyID
        );
        // Deactivate _UpdateValuesInDatabase
        LimeExpressionManager::SetPreviewMode('database');
        LimeExpressionManager::StartSurvey(
            $oSurvey->sid, 'survey',
            $oSurvey->attributes,
            true
        );
        LimeExpressionManager::StartProcessingPage(
            true,
            true
        );
        $aGrouplist = $this->modelQuestionGroup
            ->findAllByAttributes(['sid' => $surveyId]);
        foreach ($aGrouplist as $aGroup) {
            LimeExpressionManager::StartProcessingGroup(
                $aGroup['gid'],
                $oSurvey->anonymized != 'Y',
                $surveyId
            );
            LimeExpressionManager::FinishProcessingGroup();
        }
        LimeExpressionManager::FinishProcessingPage();

        // Flush emcache when changes are made to the survey.
        EmCacheHelper::init(['sid' => $surveyId, 'active' => 'Y']);
        EmCacheHelper::flush();
    }
}
