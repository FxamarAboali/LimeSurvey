<?php

namespace ls\tests\unit\jsonpatch;

use ls\tests\TestBaseClass;

use LimeSurvey\JsonPatch\JsonPatchException;
use LimeSurvey\JsonPatch\Patcher;
use LimeSurvey\JsonPatch\OpType\OpTypeAdd;
use LimeSurvey\JsonPatch\Pattern\PatternRaw;
use LimeSurvey\JsonPatch\OpHandler\OpHandlerInterface;

/**
 * @testdox Patcher
 */
class PatcherTest extends TestBaseClass
{
    /**
     * @testdox applyPatch() should execute matched operation
     */
    public function testApplyPatchExecuteMatchedOperation()
    {
        $patch = [
            [
                'op' => 'add',
                'path' => '/hello',
                'value' => 'World'
            ]
        ];

        $opHandler = \Mockery::mock(
            OpHandlerInterface::class
        );
        $opHandler->shouldReceive('getOpType')
            ->andReturn(new OpTypeAdd);
        $opHandler->shouldReceive('getPattern')
            ->andReturn(new PatternRaw($patch[0]['path']));
        $opHandler->shouldReceive('getGroupByParams');
        $opHandler->shouldReceive('getValueKeyParam');
        $opHandler->shouldReceive('applyOperation')
            ->withArgs([[], $patch[0]['value']]);

        $patcher = new Patcher();
        $patcher->addOpHandler($opHandler);
        $operationsApplied = $patcher->applyPatch($patch);

        $this->assertEquals(1, $operationsApplied);
    }

    /**
     * @testdox applyPatch() passes global params to op handlers
     */
    public function testApplyPatchPassesGlobalParamToOpHandlers()
    {
        $patch = [
            [
                'op' => 'add',
                'path' => '/hello',
                'value' => 'World'
            ]
        ];

        $opHandler = \Mockery::mock(
            OpHandlerInterface::class
        );
        $opHandler->shouldReceive('getOpType')
            ->andReturn(new OpTypeAdd);
        $opHandler->shouldReceive('getPattern')
            ->andReturn(new PatternRaw($patch[0]['path']));
        $opHandler->shouldReceive('getGroupByParams');
        $opHandler->shouldReceive('getValueKeyParam');
        $opHandler->shouldReceive('applyOperation')
            ->withArgs([['myGlobalValue' => 123], $patch[0]['value']]);

        $patcher = new Patcher();
        $patcher->setParams(['myGlobalValue' => 123]);
        $patcher->addOpHandler($opHandler);
        $operationsApplied = $patcher->applyPatch($patch);

        $this->assertEquals(1, $operationsApplied);
    }

    /**
     * @testdox applyPatch() throws JsonPatchException on missing op handler
     */
    public function testApplyPatchThrowsJsonPatchExceptionOnMissingOpHandler()
    {
        $this->expectException(JsonPatchException::class);

        $patch = [
            [
                'op' => 'add',
                'path' => '/hello',
                'value' => 'World'
            ]
        ];

        $patcher = new Patcher();
        $operationsApplied = $patcher->applyPatch($patch);

        $this->assertEquals(0, $operationsApplied);
    }

    /**
     * @testdox applyPatch() throws JsonPatchException on missing op
     */
    public function testApplyPatchThrowsJsonPatchExceptionOnMissingOp()
    {
        $this->expectException(JsonPatchException::class);

        $patch = [
            [
                // Op not set
                'path' => '/hello',
                'value' => 'World'
            ]
        ];

        $patcher = new Patcher();
        $patcher->applyPatch($patch);
    }
}
