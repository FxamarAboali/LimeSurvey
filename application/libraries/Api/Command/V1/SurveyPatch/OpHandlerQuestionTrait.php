<?php

namespace LimeSurvey\Api\Command\V1\SurveyPatch;

use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputAnswer;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputAnswerL10ns;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestionAttribute;
use LimeSurvey\ObjectPatch\Op\OpInterface;
use LimeSurvey\ObjectPatch\OpHandler\OpHandlerException;

trait OpHandlerQuestionTrait
{
    /**
     * Converts the answers from the raw data to the expected format.
     * @param OpInterface $op
     * @param array|null $data
     * @param TransformerInputAnswer $transformerAnswer
     * @param TransformerInputAnswerL10ns $transformerAnswerL10n
     * @param array|null $additionalRequiredEntities
     * @return array
     * @throws OpHandlerException
     */
    public function prepareAnswers(
        OpInterface $op,
        ?array $data,
        TransformerInputAnswer $transformerAnswer,
        TransformerInputAnswerL10ns $transformerAnswerL10n,
        ?array $additionalRequiredEntities = null
    ): array {
        $preparedAnswers = [];
        if (is_array($data)) {
            foreach ($data as $index => $answer) {
                $transformedAnswer = $transformerAnswer->transform(
                    $answer
                );
                $this->checkRequiredData(
                    $op,
                    $transformedAnswer,
                    'answers',
                    $additionalRequiredEntities
                );
                if (
                    is_array($answer) && array_key_exists(
                        'l10ns',
                        $answer
                    ) && is_array($answer['l10ns'])
                ) {
                    $transformedAnswer['answeroptionl10n'] = $this->prepareAnswerL10n(
                        $op,
                        $answer['l10ns'],
                        $transformerAnswerL10n,
                        $additionalRequiredEntities
                    );
                }
                /**
                 * second array index needs to be the scaleId
                 */
                $scaleId = array_key_exists(
                    'scale_id',
                    $transformedAnswer
                ) ? $transformedAnswer['scale_id'] : 0;
                $preparedAnswers[$index][$scaleId] = $transformedAnswer;
            }
        }
        return $preparedAnswers;
    }

    /**
     * @param OpInterface $op
     * @param array $AnswerL10nArray
     * @param TransformerInputAnswerL10ns $transformerAnswerL10n
     * @param array|null $additionalRequiredEntities
     * @return array
     * @throws OpHandlerException
     */
    private function prepareAnswerL10n(
        OpInterface $op,
        array $AnswerL10nArray,
        TransformerInputAnswerL10ns $transformerAnswerL10n,
        ?array $additionalRequiredEntities
    ): array {
        $prepared = [];
        foreach ($AnswerL10nArray as $lang => $answerL10n) {
            $tfAnswerL10n = $transformerAnswerL10n->transform(
                $answerL10n
            );
            $this->checkRequiredData(
                $op,
                $tfAnswerL10n,
                'answerL10n',
                $additionalRequiredEntities
            );
            $prepared[$lang] =
                (
                    is_array($tfAnswerL10n)
                    && isset($tfAnswerL10n['answer'])
                ) ?
                    $tfAnswerL10n['answer'] : null;
        }
        return $prepared;
    }

    /**
     * Checks required entities' data to be not empty.
     * @param OpInterface $op
     * @param array|null $data
     * @param string $name
     * @param array|null $additionalEntities
     * @return void
     * @throws OpHandlerException
     */
    private function checkRequiredData(
        OpInterface $op,
        ?array $data,
        string $name,
        ?array $additionalEntities = null
    ): void {
        if (
            in_array(
                $name,
                $this->getRequiredEntitiesArray($additionalEntities)
            )
            && empty($data)
        ) {
            throw new OpHandlerException(
                sprintf(
                    'No values to update for %s in entity %s',
                    $name,
                    $op->getEntityType()
                )
            );
        }
    }

    /**
     * For creating a question without breaking the app, we need at least
     * "question"", "questionL10n" entities.
     * For a more basic use, it is possible to add other required entities
     * via additionalEntities.
     * @param array|null $additionalEntities
     * @return array
     */
    private function getRequiredEntitiesArray(
        ?array $additionalEntities = null
    ): array {
        if (!is_array($additionalEntities)) {
            $additionalEntities = [];
        }
        return array_merge($additionalEntities, [
            'question',
            'questionL10n'
        ]);
    }

    /**
     * Converts the advanced settings from the raw data to the expected format.
     * @param OpInterface $op
     * @param TransformerInputQuestionAttribute $transformerAttribute
     * @param array|null $data
     * @param array|null $additionalRequiredEntities
     * @return array
     * @throws OpHandlerException
     */
    public function prepareAdvancedSettings(
        OpInterface $op,
        TransformerInputQuestionAttribute $transformerAttribute,
        ?array $data,
        ?array $additionalRequiredEntities = null
    ): array {
        $preparedSettings = [];
        if (is_array($data)) {
            foreach ($data as $attrName => $languages) {
                foreach ($languages as $lang => $advancedSetting) {
                    $transformedSetting = $transformerAttribute->transform(
                        $advancedSetting
                    );
                    $this->checkRequiredData(
                        $op,
                        $transformedSetting,
                        'attributes',
                        $additionalRequiredEntities
                    );
                    if (
                        is_array($transformedSetting) && array_key_exists(
                            'value',
                            $transformedSetting
                        )
                    ) {
                        $value = $transformedSetting['value'];
                        if ($lang !== '') {
                            $preparedSettings[0][$attrName][$lang] = $value;
                        } else {
                            $preparedSettings[0][$attrName] = $value;
                        }
                    }
                }
            }
        }
        return $preparedSettings;
    }
}
