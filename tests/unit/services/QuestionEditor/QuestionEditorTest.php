<?php

namespace ls\tests\unit\services\QuestionEditor;

use ls\tests\TestBaseClass;

use Mockery;
use Permission;
use LimeSurvey\Models\Services\Exception\{
    PermissionDeniedException
};

/**
 * @group services
 */
class QuestionEditorTest extends TestBaseClass
{
    public function testThrowsExceptionPermissionDenied()
    {
        $this->expectException(
            PermissionDeniedException::class
        );

        $modelPermission = Mockery::mock(Permission::class)
            ->makePartial();
        $modelPermission->shouldReceive('hasSurveyPermission')
            ->andReturn(false);

        $mockSet = (new QuestionEditorMockSetFactory)->make();
        $mockSet->modelPermission = $modelPermission;

        $questionEditor = (new QuestionEditorFactory)->make($mockSet);

        $questionEditor->save([]);
    }
}
