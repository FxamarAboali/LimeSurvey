<?php

namespace LimeSurvey\Api\Command\V1\SurveyPatch;

use LimeSurvey\Libraries\Api\Command\V1\SurveyPatch\OpHandlerAnswerDelete;
use LimeSurvey\ObjectPatch\OpHandler\OpHandlerActiveRecordUpdate;
use LimeSurvey\ObjectPatch\Patcher;
use Answer;
use QuestionAttribute;
use LimeSurvey\Api\Command\V1\Transformer\Input\{
    TransformerInputAnswer,
    TransformerInputQuestionAttribute
};
use DI\FactoryInterface;
use Psr\Container\ContainerInterface;

class PatcherSurvey extends Patcher
{
    /**
     * Constructor
     *
     * @param FactoryInterface $diFactory
     * @param ContainerInterface $diContainer
     */
    public function __construct(FactoryInterface $diFactory, ContainerInterface $diContainer)
    {
        $this->addOpHandler($diContainer->get(
            OpHandlerSurveyUpdate::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerLanguageSettingsUpdate::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerQuestionGroup::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerQuestionGroupL10n::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerQuestionDelete::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerQuestionCreate::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerQuestionUpdate::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerQuestionL10nUpdate::class
        ));
        $this->addOpHandlerQuestionAttribute($diFactory, $diContainer);
        $this->addOpHandlerQuestionAnswer($diFactory, $diContainer);
        $this->addOpHandler($diContainer->get(
            OpHandlerQuestionGroupReorder::class
        ));
        $this->addOpHandler($diContainer->get(
            OpHandlerAnswerDelete::class
        ));
    }

    private function addOpHandlerQuestionAttribute(FactoryInterface $diFactory, ContainerInterface $diContainer): void
    {
        $this->addOpHandler($diFactory->make(
            OpHandlerActiveRecordUpdate::class,
            [
                'entity' => 'questionAttribute',
                'model' => QuestionAttribute::model(),
                'transformer' => $diContainer->get(
                    TransformerInputQuestionAttribute::class
                )
            ]
        ));
    }

    private function addOpHandlerQuestionAnswer(FactoryInterface $diFactory, ContainerInterface $diContainer): void
    {
        $this->addOpHandler($diFactory->make(
            OpHandlerActiveRecordUpdate::class,
            [
                'entity' => 'questionAnswer',
                'model' => Answer::model(),
                'transformer' => $diContainer->get(
                    TransformerInputAnswer::class
                )
            ]
        ));
    }
}
