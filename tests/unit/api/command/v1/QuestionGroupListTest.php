<?php

namespace ls\tests\unit\api\command\v1;

use Eloquent\Phony\Phpunit\Phony;
use Survey;
use QuestionGroup;
use Permission;
use QuestionGroupL10n;
use ls\tests\TestBaseClass;
use ls\tests\unit\api\command\mixin\AssertResponse;
use LimeSurvey\Api\Command\V1\QuestionGroupList;
use LimeSurvey\Api\Command\Request\Request;
use LimeSurvey\Api\Command\Response\Status\StatusErrorNotFound;
use LimeSurvey\Api\Command\Response\Status\StatusSuccess;
use LimeSurvey\Api\Command\Response\Status\StatusErrorUnauthorised;
use LimeSurvey\Api\ApiSession;
use Mockery;

/**
 * @testdox API command v1 QuestionGroupList
 */
class QuestionGroupListTest extends TestBaseClass
{
    use AssertResponse;

    /**
     * @testdox Returns invalid session response (error unauthorised) if session key is not valid.
     */
    public function testQuestionGroupListInvalidSession()
    {
        $request = new Request(array(
            'sessionKey' => 'not-a-valid-session-id',
            'surveyID' => 'surveyID',
            'language' => 'language'
        ));
        $command = new QuestionGroupList();
        $response = $command->run($request);

        $this->assertResponseInvalidSession($response);
    }

    /**
     * @testdox Returns error not-found if survey id is not valid.
     */
    public function testQuestionGroupListAddInvalidSurveyId()
    {
        $request = new Request(array(
            'sessionKey' => 'not-a-valid-session-id',
            'surveyID' => 'surveyID',
            'language' => 'language'
        ));

        $mockApiSessionHandle = Phony::mock(ApiSession::class);
        $mockApiSessionHandle
            ->checkKey
            ->returns(true);
        $mockApiSession = $mockApiSessionHandle->get();

        $command = new QuestionGroupList();
        $command->setApiSession($mockApiSession);

        $response = $command->run($request);

        $this->assertResponseStatus(
            $response,
            new StatusErrorNotFound()
        );

        $this->assertResponseDataStatus(
            $response,
            'Error: Invalid survey ID'
        );
    }

    /**
     * @testdox Returns invalid session response (error unauthorised) users does not have permission.
     */
    public function testQuestionGroupListAddNoPermission()
    {
        $request = new Request(array(
            'sessionKey' => 'not-a-valid-session-id',
            'surveyID' => 'surveyID',
            'language' => 'language'
        ));

        $mockApiSession = $this->createStub(ApiSession::class);
        $mockApiSession
            ->method('checkKey')
            ->willReturn(true);

        $mockSurveyModel = Mockery::mock(Survey::class);
        $mockSurveyModel
            ->allows()
            ->hasSurveyPermission()
            ->andReturns(true);

        $mockModelPermission = Mockery::mock(Permission::class);
        $mockModelPermission
            ->allows()
            ->hasSurveyPermission(0, 'survey', 'read', null)
            ->andReturns(false);

        $command = new QuestionGroupList();
        $command->setApiSession($mockApiSession);
        $command->setSurveyModel($mockSurveyModel);
        $command->setPermissionModel($mockModelPermission);

        $response = $command->run($request);

        $this->assertResponseStatus(
            $response,
            new StatusErrorUnauthorised()
        );
    }

    /**
     * @testdox Returns success with status if there are no groups for survey id.
     */
    public function testQuestionGroupListNoGroupsForSurveyId()
    {
        $request = new Request(array(
            'sessionKey' => 'invalid-id',
            'surveyID' => 'surveyID',
            'language' => 'language'
        ));

        $mockApiSession = Mockery::mock(ApiSession::class);
        $mockApiSession
            ->allows()
            ->checkKey('invalid-id')
            ->andReturns(true);

        $mockSurveyModel = Mockery::mock(Survey::class);
        $mockSurveyModel
            ->allows()
            ->hasSurveyPermission()
            ->andReturns(true);

        $mockModelPermission = Mockery::mock(Permission::class);
        $mockModelPermission
            ->allows()
            ->hasSurveyPermission(0, 'survey', 'read', null)
            ->andReturns(true);

        $command = new QuestionGroupList();
        $command->setApiSession($mockApiSession);
        $command->setSurveyModel($mockSurveyModel);
        $command->setPermissionModel($mockModelPermission);
        $command->setQuestionGroupModelCollectionWithL10nsBySid([]);

        $response = $command->run($request);

        $this->assertResponseStatus(
            $response,
            new StatusSuccess()
        );
    }

    /**
     * @testdox Returns success with data.
     */
    public function testQuestionGroupListSuccessWithData()
    {
        $request = new Request(array(
            'sessionKey' => 'not-a-valid-session-id',
            'surveyID' => 'surveyID',
            'language' => 'en'
        ));

        $mockApiSessionHandle = Phony::mock(ApiSession::class);
        $mockApiSessionHandle
            ->checkKey
            ->returns(true);
        $mockApiSession = $mockApiSessionHandle->get();

        $mockSurveyModelHandle = Phony::mock(Survey::class);
        $mockSurveyModelHandle->hasSurveyPermission
            ->returns(true);
        $mockSurveyModel = $mockSurveyModelHandle->get();

        $mockModelPermissionHandle = Phony::mock(Permission::class);
        $mockModelPermissionHandle->hasSurveyPermission
            ->returns(true);
        $mockModelPermission = $mockModelPermissionHandle->get();

        $questionGroup = new QuestionGroup();
        $questionGroup->setAttributes(array(
            'id' => '1',
            'gid' => '1',
            'sid' => '712896',
            'group_order' => '1',
            'randomization_group' => '',
            'grelevance' => '1',
            'language' => 'en'
        ), false);

        $questionGroupL10n = new QuestionGroupL10n();
        $questionGroupL10n->setAttributes(array(
            'group_name' => 'Question group 1',
            'description' => ''
        ), false);

        $questionGroup->questiongroupl10ns = array('en' => $questionGroupL10n);

        $command = new QuestionGroupList();
        $command->setApiSession($mockApiSession);
        $command->setSurveyModel($mockSurveyModel);
        $command->setPermissionModel($mockModelPermission);
        $command->setQuestionGroupModelCollectionWithL10nsBySid([
            $questionGroup
        ]);

        $response = $command->run($request);

        $this->assertResponseStatus(
            $response,
            new StatusSuccess()
        );

        $responseData = $response->getData();

        $this->assertEquals(array(
            'id' => '1',
            'gid' => '1',
            'sid' => '712896',
            'group_order' => '1',
            'randomization_group' => '',
            'grelevance' => '1',
            'group_name' => 'Question group 1',
            'description' => '',
            'language' => 'en'
        ), $responseData[0]);
    }
}
