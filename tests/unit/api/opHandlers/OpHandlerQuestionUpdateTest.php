<?php

namespace ls\tests\unit\api\opHandlers;

use LimeSurvey\Api\Command\V1\SurveyPatch\OpHandlerQuestionGroupReorder;
use LimeSurvey\Api\Command\V1\SurveyPatch\OpHandlerQuestionUpdate;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestion;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestionGroup;
use LimeSurvey\Api\Command\V1\Transformer\Input\TransformerInputQuestionGroupL10ns;
use LimeSurvey\Models\Services\QuestionAggregateService;
use LimeSurvey\ObjectPatch\ObjectPatchException;
use LimeSurvey\ObjectPatch\Op\OpInterface;
use LimeSurvey\ObjectPatch\Op\OpStandard;
use ls\tests\TestBaseClass;
use LimeSurvey\ObjectPatch\OpHandler\OpHandlerException;
use ls\tests\unit\services\QuestionGroup\QuestionGroupMockSetFactory;

/**
 * @testdox OpHandlerQuestionUpdate
 */
class OpHandlerQuestionUpdateTest extends TestBaseClass
{
    protected OpInterface $op;

    /**
     * @testdox throws exception when no valid values are provided
     */
    public function testOpQuestionCreateThrowsNoValuesException()
    {
        $this->expectException(
            OpHandlerException::class
        );
        $this->initializePatcher(
            $this->getWrongPropsArray()
        );
        $opHandler = $this->getOpHandler();
        $opHandler->getPreparedData($this->op);
    }

    /**
     * @testdox getPreparedData() is expected to return a certain data structure
     */
    public function testOpQuestionUpdateDataStructure()
    {
        $this->initializePatcher(
            $this->getCorrectPropsArray()
        );
        $opHandler = $this->getOpHandler();
        $preparedData = $opHandler->getPreparedData($this->op);
        $this->assertArrayHasKey('question', $preparedData);
        $this->assertArrayHasKey('qid', $preparedData['question']);
        $this->assertEquals(77, $preparedData['question']['qid']);
    }

    /**
     * @testdox can handle a question update
     */
    public function testOpQuestionCreateCanHandle()
    {
        $this->initializePatcher(
            $this->getCorrectPropsArray()
        );

        $opHandler = $this->getOpHandler();
        self::assertTrue($opHandler->canHandle($this->op));
    }

    /**
     * @testdox cannot handle a question create
     */
    public function testOpQuestionCreateCanNotHandle()
    {
        $this->initializePatcher(
            $this->getCorrectPropsArray(),
            'create'
        );

        $opHandler = $this->getOpHandler();
        self::assertFalse($opHandler->canHandle($this->op));
    }

    /**
     * @param array $props
     * @param string $type
     * @return void
     * @throws ObjectPatchException
     */
    private function initializePatcher(array $props, string $type = 'update')
    {
        $this->op = OpStandard::factory(
            'question',
            $type,
            "77",
            [
                $props
            ],
            [
                'id' => 666
            ]
        );
    }

    /**
     * @return array
     */
    private function getCorrectPropsArray()
    {
        return [
            'title' => 'test title',
            'mandatory' => true,
        ];
    }

    /**
     * @return array
     */
    private function getWrongPropsArray()
    {
        return [
            'xxx' => 'test title',
            'yyy' => true,
        ];
    }

    /**
     * @return OpHandlerQuestionUpdate
     */
    private function getOpHandler()
    {
        $mockQuestionAggregateService = \Mockery::mock(
            QuestionAggregateService::class
        )->makePartial();
        return new OpHandlerQuestionUpdate(
            $mockQuestionAggregateService,
            new TransformerInputQuestion()
        );
    }
}
