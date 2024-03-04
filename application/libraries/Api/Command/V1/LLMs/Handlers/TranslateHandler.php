<?php

namespace LimeSurvey\Libraries\Api\Command\V1\LLMs\Handlers;

class TranslateHandler implements CommandHandlerInterface
{
    public function canHandle(Command $command)
    {
        $cmd = strtolower($command->getOperation());
        if (str_contains($cmd, 'translate')) {
            return true;
        }
        return false;
    }

    public function execute(Command $command, AIClientInterface $client)
    {
        $op = $command->getOperation();
        $command->setOperation("{$op} in a plain text");
        return $client->generateContent();
    }

}
