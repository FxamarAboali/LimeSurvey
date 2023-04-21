<?php

namespace LimeSurvey\Api\Rest\V1\SchemaFactory;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class SchemaFactorySurvey
{
    public function create(): Schema
    {
        $schemaSurveyLanguageSettings = (new SchemaFactorySurveyLanguageSettings)->create();
        $schemaSurveyDefaultLanguage = Schema::object('defaultlanguage')
            ->properties(...$schemaSurveyLanguageSettings->properties);

        return Schema::create()
            ->title('Survey')
            ->description('Survey')
            ->type(Schema::TYPE_OBJECT)
            ->properties(
                Schema::integer('sid')->default(null),
                Schema::integer('gsid')->default(null),
                Schema::boolean('active')->default(false),
                Schema::boolean('expires')->default(null),
                Schema::boolean('startdate')->default(null),
                Schema::boolean('anonymized')->default(null),
                Schema::boolean('savetimings')->default(null),
                Schema::boolean('datestamp')->default(null),
                Schema::boolean('usecookie')->default(null),
                Schema::boolean('allowregister')->default(null),
                Schema::boolean('allowsave')->default(null),
                Schema::integer('autonumber_start')->default(0),
                Schema::boolean('autoredirect')->default(null),
                Schema::boolean('allowprev')->default(null),
                Schema::boolean('printanswers')->default(null),
                Schema::boolean('ipaddr')->default(null),
                Schema::boolean('ipanonymize')->default(null),
                Schema::boolean('refurl')->default(null),
                Schema::string('datecreated')->default(null)->format(Schema::FORMAT_DATE_TIME),
                Schema::boolean('publicstatistics')->default(null),
                Schema::boolean('publicgraphs')->default(null),
                Schema::boolean('listpublic')->default(null),
                Schema::boolean('sendconfirmation')->default(null),
                Schema::boolean('tokenanswerspersistence')->default(null),
                Schema::boolean('assessments')->default(null),
                Schema::boolean('usecaptcha')->default(null),
                Schema::boolean('usetokens')->default(null),
                Schema::string('bounce_email')->default(null),
                Schema::string('attributedescriptions')->default(null),
                Schema::boolean('emailresponseto')->default(null),
                Schema::string('emailnotificationto')->default(null),
                Schema::integer('tokenlength')->default(null)->example(15),
                Schema::boolean('showxquestions')->default(null),
                Schema::boolean('showgroupinfo')->default(null),
                Schema::boolean('shownoanswer')->default(null),
                Schema::boolean('showqnumcode')->default(null),
                Schema::integer('bouncetime')->default(null),
                Schema::boolean('bounceprocessing')->default(null),
                Schema::string('bounceaccounttype')->default(null),
                Schema::string('bounceaccounthost')->default(null),
                Schema::string('bounceaccountpass')->default(null),
                Schema::string('bounceaccountencryption')->default(null),
                Schema::string('bounceaccountuser')->default(null),
                Schema::boolean('showwelcome')->default(null),
                Schema::boolean('showprogress')->default(null),
                Schema::integer('questionindex')->default(null),
                Schema::integer('navigationdelay')->default(null),
                Schema::boolean('nokeyboard')->default(null),
                Schema::boolean('alloweditaftercompletion')->default(null),
                Schema::integer('googleanalyticsstyle')->default(null),
                Schema::string('googleanalyticsapikey')->default(null),
                Schema::integer('showsurveypolicynotice')->default(null),
                $schemaSurveyDefaultLanguage,
                Schema::string('created_at')->format(Schema::FORMAT_DATE_TIME),
            );
    }
}
