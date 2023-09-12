<?php

namespace LimeSurvey\Api\Command\V1\SurveyPatch;

use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputAnswer;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputAnswerL10ns;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestion;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestionAggregate;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestionAttribute;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestionL10ns;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputSubQuestion;
use LimeSurvey\Models\Services\QuestionAggregateService;
use LimeSurvey\ObjectPatch\Op\OpInterface;
use LimeSurvey\ObjectPatch\OpHandler\OpHandlerException;
use LimeSurvey\ObjectPatch\OpHandler\OpHandlerInterface;
use LimeSurvey\ObjectPatch\OpType\OpTypeCreate;
use Question;

class OpHandlerQuestionCreate implements OpHandlerInterface
{
    use OpHandlerSurveyTrait;

    protected string $entity;
    protected Question $model;
    protected TransformerInputQuestion $transformer;
    protected TransformerInputQuestionL10ns $transformerL10n;
    protected TransformerInputQuestionAttribute $transformerAttribute;
    protected TransformerInputAnswer $transformerAnswer;
    protected TransformerInputAnswerL10ns $transformerAnswerL10n;
    protected TransformerInputSubQuestion $transformerSubQuestion;
    protected TransformerInputQuestionAggregate $transformerInputQuestionAggregate;

    public function __construct(
        Question $model,
        TransformerInputQuestion $transformer,
        TransformerInputQuestionL10ns $transformerL10n,
        TransformerInputQuestionAttribute $transformerAttribute,
        TransformerInputAnswer $transformerAnswer,
        TransformerInputAnswerL10ns $transformerAnswerL10n,
        TransformerInputSubQuestion $transformerSubQuestion,
        TransformerInputQuestionAggregate $transformerInputQuestionAggregate
    ) {
        $this->entity = 'question';
        $this->model = $model;
        $this->transformer = $transformer;
        $this->transformerL10n = $transformerL10n;
        $this->transformerAttribute = $transformerAttribute;
        $this->transformerAnswer = $transformerAnswer;
        $this->transformerAnswerL10n = $transformerAnswerL10n;
        $this->transformerSubQuestion = $transformerSubQuestion;
        $this->transformerInputQuestionAggregate = $transformerInputQuestionAggregate;
    }

    public function canHandle(OpInterface $op): bool
    {
        $isCreateOperation = $op->getType()->getId() === OpTypeCreate::ID;
        $isQuestionEntity = $op->getEntityType() === 'question';

        return $isCreateOperation && $isQuestionEntity;
    }

    /**
     * For a valid creation of a question you need at least question and
     * questionL10n entities within the patch.
     * An example patch with all(!) possible entities:
     *
     * {
     *     "entity": "question",
     *     "op": "create",
     *     "id": 0,
     *     "props": {
     *         "question": {
     *             "qid": "0",
     *             "title": "G01Q06",
     *             "type": "1",
     *             "question_theme_name": "arrays\/dualscale",
     *             "gid": "50",
     *             "mandatory": false,
     *             "relevance": "1",
     *             "encrypted": false,
     *             "save_as_default": false
     *         },
     *         "questionL10n": {
     *             "en": {
     *                 "question": "Array Question",
     *                 "help": "Help text"
     *             },
     *             "de": {
     *                 "question": "Array ger",
     *                 "help": "help ger"
     *             }
     *         },
     *         "attributes": {
     *             "dualscale_headerA": {
     *                 "de": {
     *                     "value": "A ger"
     *                 },
     *                 "en": {
     *                     "value": "A"
     *                 }
     *             },
     *             "dualscale_headerB": {
     *                 "de": {
     *                     "value": "B ger"
     *                 },
     *                 "en": {
     *                     "value": "B"
     *                 }
     *             },
     *             "public_statistics": {
     *                 "": {
     *                     "value": "1"
     *                 }
     *             }
     *         },
     *         "answers": {
     *             "0": {
     *                 "code": "AO01",
     *                 "sortOrder": 0,
     *                 "assessmentValue": 0,
     *                 "scaleId": 0,
     *                 "l10ns": {
     *                     "de": {
     *                         "answer": "antwort1",
     *                         "language": "de"
     *                     },
     *                     "en": {
     *                         "answer": "answer1",
     *                         "language": "en"
     *                     }
     *                 }
     *             },
     *             "1": {
     *                 "code": "AO02",
     *                 "sortOrder": 1,
     *                 "assessmentValue": 0,
     *                 "scaleId": 0,
     *                 "l10ns": {
     *                     "de": {
     *                         "answer": "antwort1.2",
     *                         "language": "de"
     *                     },
     *                     "en": {
     *                         "answer": "answer1.2",
     *                         "language": "en"
     *                     }
     *                 }
     *             }
     *         },
     *         "subquestions": {
     *             "0": {
     *                 "title": "SQ001",
     *                 "sortOrder": 0,
     *                 "relevance": "1",
     *                 "l10ns": {
     *                     "de": {
     *                         "question": "subger1",
     *                         "language": "de"
     *                     },
     *                     "en": {
     *                         "question": "sub1",
     *                         "language": "en"
     *                     }
     *                 },
     *             },
     *             "1": {
     *                 "title": "SQ002",
     *                 "sortOrder": 1,
     *                 "relevance": "1",
     *                 "l10ns": {
     *                     "de": {
     *                         "question": "subger2",
     *                         "language": "de"
     *                     },
     *                     "en": {
     *                         "question": "sub2",
     *                         "language": "en"
     *                     }
     *                 },
     *             }
     *         }
     *     }
     * }
     *
     * @param OpInterface $op
     * @return void
     * @throws OpHandlerException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \LimeSurvey\Models\Services\Exception\NotFoundException
     * @throws \LimeSurvey\Models\Services\Exception\PermissionDeniedException
     * @throws \LimeSurvey\Models\Services\Exception\PersistErrorException
     */
    public function handle(OpInterface $op): void
    {
        $diContainer = \LimeSurvey\DI::getContainer();
        $questionService = $diContainer->get(
            QuestionAggregateService::class
        );

        $questionService->save(
            $this->getSurveyIdFromContext($op),
            $this->prepareData($op)
        );
    }

    /**
     * Aggregates the transformed data of all the different entities into
     * a single array as the service expects it.
     * @param OpInterface $op
     * @return ?mixed
     * @throws OpHandlerException
     */
    public function prepareData(OpInterface $op)
    {
        $allData = $op->getProps();
        $this->checkRawPropsForRequiredEntities($op, $allData);
        $preparedData = [];
        $entities = [
            'question', 'questionL10n', 'attributes', 'answers', 'subquestions'
        ];

        foreach ($entities as $name) {
            $entityData = [];
            if (array_key_exists($name, $allData)) {
                $entityData = $this->prepare($op, $name, $allData[$name]);
            }
            $preparedData[$name] = $entityData;
        }

        return $this->transformerInputQuestionAggregate->transform(
            $preparedData
        );
    }

    /**
     * Prepares the data structure for the different entities by calling
     * the different prepare functions.
     * @param OpInterface $op
     * @param string $name
     * @param array $data
     * @return array|mixed|null
     * @throws OpHandlerException
     */
    public function prepare(OpInterface $op, string $name, array $data)
    {
        switch ($name) {
            case 'question':
                $entityData = $this->transformer->transform($data);
                $this->checkRequiredData($op, $entityData, 'question');
                return $entityData;
            case 'questionL10n':
                return $this->prepareQuestionL10n($op, $data);
            case 'attributes':
                return $this->prepareAdvancedSettings($op, $data);
            case 'answers':
                return $this->prepareAnswers($op, $data);
            case 'subquestions':
                return $this->prepareSubQuestions($op, $data);
        }
        return $data;
    }

    /**
     * Checks required entities' data to be not empty.
     * @param OpInterface $op
     * @param array|null $data
     * @param string $name
     * @return void
     * @throws OpHandlerException
     */
    private function checkRequiredData(
        OpInterface $op,
        ?array $data,
        string $name
    ): void {
        if (
            in_array($name, $this->getRequiredEntitiesArray())
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
     * Checks the raw props for all required entities.
     * @param OpInterface $op
     * @param array $rawProps
     * @return void
     * @throws OpHandlerException
     */
    private function checkRawPropsForRequiredEntities(
        OpInterface $op,
        array $rawProps
    ): void {
        foreach ($this->getRequiredEntitiesArray() as $requiredEntity) {
            if (!array_key_exists($requiredEntity, $rawProps)) {
                throw new OpHandlerException(
                    sprintf(
                        'Missing entity %s in props of %s',
                        $requiredEntity,
                        $op->getEntityType()
                    )
                );
            }
        }
    }

    /**
     * For creating a question without breaking the app, we need at least
     * "question"", "questionL10n" entities.
     * @return array
     */
    private function getRequiredEntitiesArray(): array
    {
        return [
            'question',
            'questionL10n'
        ];
    }

    /**
     * @param OpInterface $op
     * @param array|null $data
     * @return array
     * @throws OpHandlerException
     */
    private function prepareQuestionL10n(OpInterface $op, ?array $data): array
    {
        $preparedL10n = [];
        if (is_array($data)) {
            foreach ($data as $index => $props) {
                $preparedL10n[$index] = $this->transformerL10n->transform(
                    $props
                );
                $this->checkRequiredData(
                    $op,
                    $preparedL10n[$index],
                    'questionL10n'
                );
            }
        }

        return $preparedL10n;
    }

    /**
     * Converts the advanced settings from the raw data to the expected format.
     * @param OpInterface $op
     * @param array|null $data
     * @return array
     * @throws OpHandlerException
     */
    private function prepareAdvancedSettings(
        OpInterface $op,
        ?array $data
    ): array {
        $preparedSettings = [];
        if (is_array($data)) {
            foreach ($data as $attrName => $languages) {
                foreach ($languages as $lang => $advancedSetting) {
                    $transformedSetting = $this->transformerAttribute->transform(
                        $advancedSetting
                    );
                    $this->checkRequiredData(
                        $op,
                        $transformedSetting,
                        'attributes'
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

    /**
     * Converts the answers from the raw data to the expected format.
     * @param OpInterface $op
     * @param array|null $data
     * @return array
     * @throws OpHandlerException
     */
    private function prepareAnswers(OpInterface $op, ?array $data): array
    {
        $preparedAnswers = [];
        if (is_array($data)) {
            foreach ($data as $index => $answer) {
                $transformedAnswer = $this->transformerAnswer->transform(
                    $answer
                );
                $this->checkRequiredData(
                    $op,
                    $transformedAnswer,
                    'answers'
                );
                if (
                    is_array($answer) && array_key_exists(
                        'l10ns',
                        $answer
                    ) && is_array($answer['l10ns'])
                ) {
                    foreach ($answer['l10ns'] as $lang => $answerL10n) {
                        $tfAnswerL10n = $this->transformerAnswerL10n->transform(
                            $answerL10n
                        );
                        $transformedAnswer['answeroptionl10n'][$lang] =
                            (
                                is_array($tfAnswerL10n)
                                && isset($tfAnswerL10n['answer'])
                            ) ?
                                $tfAnswerL10n['answer'] : null;
                    }
                }
                /**
                 * $index can sometimes determine where the answer is positioned
                 * (e.g.:array dualscale)
                 * index is used twice because of the structure the service
                 * expects the data to be in
                 */
                $preparedAnswers[$index][$index] = $transformedAnswer;
            }
        }
        return $preparedAnswers;
    }

    /**
     * Converts the subquestions from the raw data to the expected format.
     * @param OpInterface $op
     * @param array|null $data
     * @return array
     * @throws OpHandlerException
     */
    private function prepareSubQuestions(OpInterface $op, ?array $data): array
    {
        $preparedSubQuestions = [];
        if (is_array($data)) {
            foreach ($data as $index => $subQuestion) {
                $tfSubQuestion = $this->transformerSubQuestion->transform(
                    $subQuestion
                );
                $this->checkRequiredData(
                    $op,
                    $tfSubQuestion,
                    'subquestions'
                );
                if (
                    is_array($subQuestion) && array_key_exists(
                        'l10ns',
                        $subQuestion
                    ) && is_array($subQuestion['l10ns'])
                ) {
                    foreach ($subQuestion['l10ns'] as $lang => $subL10n) {
                        $tfSubL10n = $this->transformerL10n->transform(
                            $subL10n
                        );
                        $tfSubQuestion['subquestionl10n'][$lang] =
                            (
                                is_array($tfSubL10n)
                                && isset($tfSubL10n['question'])
                            ) ?
                                $tfSubL10n['question'] : null;
                    }
                }
                $preparedSubQuestions[$index][0] = $tfSubQuestion;
            }
        }
        return $preparedSubQuestions;
    }
}
