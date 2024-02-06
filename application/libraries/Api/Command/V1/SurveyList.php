<?php

namespace LimeSurvey\Api\Command\V1;

use Survey;
use LimeSurvey\Api\Command\V1\Transformer\Output\TransformerOutputSurvey;
use LimeSurvey\Api\Command\{
    CommandInterface,
    Request\Request,
    Response\Response,
    Response\ResponseFactory
};
use LimeSurvey\Api\Auth\AuthSession;
use LimeSurvey\Api\Command\Mixin\Auth\AuthPermissionTrait;
use DI\FactoryInterface;

class SurveyList implements CommandInterface
{
    use AuthPermissionTrait;

    protected AuthSession $authSession;
    protected TransformerOutputSurvey $transformerOutputSurvey;
    protected ResponseFactory $responseFactory;

    /**
     * Constructor
     *
     * @param AuthSession $authSession
     * @param TransformerOutputSurvey $transformerOutputSurvey
     * @param FactoryInterface $diFactory
     * @param ResponseFactory $responseFactory
     */
    public function __construct(
        AuthSession $authSession,
        TransformerOutputSurvey $transformerOutputSurvey,
        FactoryInterface $diFactory,
        ResponseFactory $responseFactory
    ) {
        $this->survey = $diFactory->make(
            Survey::class,
            ['scenario' => 'search']
        );
        $this->authSession = $authSession;
        $this->transformerOutputSurvey = $transformerOutputSurvey;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Run survey list command
     *
     * @param Request $request
     * @return Response
     */
    public function run(Request $request)
    {
        $sessionKey = (string) $request->getData('sessionKey');

        if (
            !$this->authSession
                ->checkKey($sessionKey)
        ) {
            return $this->responseFactory
                ->makeErrorUnauthorised();
        }

        $this->survey->active = null;
        $dataProvider = $this->survey
            ->with('defaultlanguage')
            ->search([
                'pageSize' => $request->getData('pageSize'),
                // Yii pagination is zero based - so we must deduct 1
                'currentPage' => $request->getData('page') - 1,
            ]);

        $data = $this->transformerOutputSurvey
            ->transformAll($dataProvider->getData());

        return $this->responseFactory
            ->makeSuccess(['surveys' => $data]);
    }
}
