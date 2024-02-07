<?php

namespace LimeSurvey\Api\Command\V1\SurveyPatch;

use DI\DependencyException;
use DI\NotFoundException;
use LimeSurvey\Models\Services\Exception\PermissionDeniedException;
use LimeSurvey\Api\Command\V1\SurveyPatch\Traits\{OpHandlerExceptionTrait,
    OpHandlerSurveyTrait,
    OpHandlerValidationTrait};
use QuestionGroupL10n;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestionGroupL10ns;
use LimeSurvey\Models\Services\QuestionGroupService;
use LimeSurvey\ObjectPatch\{
    Op\OpInterface,
    OpType\OpTypeUpdate,
    OpHandler\OpHandlerException,
    OpHandler\OpHandlerInterface
};

class OpHandlerQuestionGroupL10n implements OpHandlerInterface
{
    use OpHandlerSurveyTrait;
    use OpHandlerValidationTrait;
    use OpHandlerExceptionTrait;

    protected string $entity;
    protected QuestionGroupL10n $model;
    protected TransformerInputQuestionGroupL10ns $transformer;

    public function __construct(
        QuestionGroupL10n $model,
        TransformerInputQuestionGroupL10ns $transformer
    ) {
        $this->entity = 'questionGroupL10n';
        $this->model = $model;
        $this->transformer = $transformer;
    }

    /**
     * Checks if the operation is applicable for the given entity.
     *
     * @param OpInterface $op
     * @return bool
     */
    public function canHandle(OpInterface $op): bool
    {
        return $op->getType()->getId() === OpTypeUpdate::ID
            && $op->getEntityType() === $this->entity;
    }

    /**
     * Saves the changes to the database.
     * Expects a patch structure like this:
     * {
     *      "entity": "questionGroupL10n",
     *      "op": "update",
     *      "id": 1,
     *      "props": {
     *          "en": {
     *              "groupName": "Name of group",
     *              "description": "English description"
     *          },
     *          "de": {
     *              "groupName": "Gruppenname",
     *              "description": "Deutsche Beschreibung"
     *          }
     *      }
     * }
     *
     * @param OpInterface $op
     * @throws OpHandlerException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws \LimeSurvey\Models\Services\Exception\NotFoundException
     * @throws PermissionDeniedException
     */
    public function handle(OpInterface $op): void
    {
        $diContainer = \LimeSurvey\DI::getContainer();
        $questionGroupService = $diContainer->get(
            QuestionGroupService::class
        );
        $questionGroup = $questionGroupService->getQuestionGroupForUpdate(
            $this->getSurveyIdFromContext($op),
            $op->getEntityId()
        );
        $transformedProps = $this->transformer->transformAll(
            $op->getProps(),
            ['operation' => $op->getType()->getId()]
        );
        if (empty($transformedProps)) {
            $this->throwNoValuesException($op);
        }
        $questionGroupService->updateQuestionGroupLanguages(
            $questionGroup,
            $transformedProps
        );
    }

    /**
     * Checks if patch is valid for this operation.
     * @param OpInterface $op
     * @return array
     */
    public function validateOperation(OpInterface $op): array
    {
        $validationData = $this->validateCollectionIndex($op, []);
        $validationData = $this->validateEntityId($op, $validationData);
        if (empty($validationData)) {
            $validationData = $this->transformer->validateAll(
                $op->getProps(),
                ['operation' => $op->getType()->getId()]
            );
        }

        return $this->getValidationReturn(
            !is_array($validationData) ? [] : $validationData,
            $op
        );
    }
}
