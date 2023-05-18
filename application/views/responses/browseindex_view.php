<?php

/**
 * Response Summary view
 * @var $this AdminController
 * @var $num_completed_answers string
 * @var $num_total_answers string
 * @var $tokeninfo mixed
 * @var $with_token CDbTableSchema|null
 */

// DO NOT REMOVE This is for automated testing to validate we see that page
echo viewHelper::getViewTestTag('surveyResponsesIndex');

?>
<div class='side-body survey-response-page <?php echo getSideBodyClass(true); ?>'>
    <h1> <?= gT("Survey responses") ?> </h1>
    <div class="mt-4">
        <?php echo $this->renderPartial(
            'partial/responseSummary',
            [
                'num_completed_answers' => $num_completed_answers,
                'num_total_answers' => $num_total_answers,
                'num_total_answers' => $num_total_answers,
            ],
            true
        );
        ?>
    </div>

    <h2 class="summary-title mt-4"><?php eT("Response summary"); ?></h2>

    <ul class="nav nav-tabs mt-2 " role="tablist">
        <li class="nav-item"><a class="nav-link active" href="#allResponses" aria-controls="allResponses" role="tab" data-bs-toggle="tab"><?php eT('All Responses'); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="#notSubmittedResponses" aria-controls="notSubmittedResponses" role="tab" data-bs-toggle="tab"><?php eT('Saved but not submitted responses'); ?></a></li>
    </ul>
    <div class="tab-content p-4 h-100">
        <div id="allResponses" class="tab-pane show fade active row">
            <?php echo $this->renderPartial(
                'partial/responseListTable',
                [
                    'surveyid' => $surveyid,
                    'dateformatdetails' => $dateformatdetails,
                    'model' => $model,
                    'bHaveToken' => $bHaveToken,
                    'language' => $language,
                    'pageSize' => $pageSize,
                    'fieldmap' => $fieldmap,
                    'filteredColumns' => $filteredColumns,
                ],
                true
            );
            ?>
        </div>
        <div id="notSubmittedResponses" class="tab-pane row">
            <?php echo $this->renderPartial(
                'partial/notSubmittedResponseListTable',
                [
                    'model' => $savedModel,
                ],
                true
            );
            ?>
        </div>
    </div>

</div>