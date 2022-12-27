<?php

namespace LimeSurvey\Api\Rest\V2\SchemaFactory;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class SchemaFactoryQuestionL10ns
{
    public function create(): Schema
    {
        return Schema::create('$language')->properties(
            Schema::integer('id')->default(null),
            Schema::integer('qid')->default(null),
            Schema::string('question')->default(null),
            Schema::string('script')->default(null),
            Schema::string('language')->default(null)
        );
    }
}
