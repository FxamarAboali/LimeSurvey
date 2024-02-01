<?php

namespace LimeSurvey\Api\Command\V1\SurveyPatch\Response;

use LimeSurvey\ObjectPatch\OpHandler\OpHandlerException;

/**
 * This class is responsible for preparing and
 * building the whole response for PatcherSurvey
 */
class SurveyResponse
{
    private int $operationsApplied = 0;
    private bool $isValidOperation = true;
    protected TempIdMapping $tempIdMapping;
    protected ValidationErrors $validationErrors;
    protected ErronousOperations $erronousOperations;

    /**
     * @param TempIdMapping $tempIdMapping
     * @param ValidationErrors $validationErrors
     * @param ErronousOperations $erronousOperations
     */
    public function __construct(
        TempIdMapping $tempIdMapping,
        ValidationErrors $validationErrors,
        ErronousOperations $erronousOperations
    ) {
        $this->tempIdMapping = $tempIdMapping;
        $this->validationErrors = $validationErrors;
        $this->erronousOperations = $erronousOperations;
    }

    /**
     * @param array $response
     * @return void
     */
    public function handleResponse(array $response): void
    {
        $this->extractTempIdMapping($response);
        $this->extractValidationErrors($response);
        if ($this->isValidOperation) {
            $this->incrementOperationsApplied();
        }
    }

    /**
     * @param \Exception $e
     * @param array $patchOpData
     * @return void
     */
    public function handleException(\Exception $e, array $patchOpData): void
    {
        // add error message and full operation info to ErrorItemList
        $erronousItem = new ErronousOperationItem(
            $e->getMessage() . "\n" . $e->getTraceAsString(),
            $patchOpData
        );
        $this->erronousOperations->addErronousOperationItem(
            $erronousItem
        );
    }

    public function incrementOperationsApplied(): void
    {
        $this->operationsApplied++;
    }

    /**
     * Extracts possible tempIdMapping from the $operationData array
     * @param array $handleResponse
     * @return void
     * @throws OpHandlerException
     */
    public function extractTempIdMapping(array $handleResponse)
    {
        if (array_key_exists('tempIdMapping', $handleResponse)) {
            foreach ($handleResponse['tempIdMapping'] as $groupName => $mappingItem) {
                $this->tempIdMapping->addTempIdMapItems($mappingItem, $groupName);
            }
        }
    }

    /**
     * Extracts possible validationErrors from the $operationData array
     * @param array $handleResponse
     * @return void
     * @throws OpHandlerException
     */
    public function extractValidationErrors(array $handleResponse)
    {
        $this->isValidOperation = true;
        if (array_key_exists('validationErrors', $handleResponse)) {
            foreach ($handleResponse['validationErrors'] as $validationErrorItem) {
                $this->validationErrors->addValidationErrorItem(
                    $validationErrorItem
                );
            }
            $this->isValidOperation = false;
        }
    }

    /**
     * @return array
     */
    public function buildResponseObject(): array
    {
        return array_merge(
            [
                'operationsApplied' => $this->operationsApplied,
            ],
            $this->tempIdMapping->getMappingResponseObject(),
            $this->validationErrors->getValidationErrorsObject(),
            $this->erronousOperations->getErronousOperationsObject()
        );
    }
}
