<?php

namespace ls\tests\unit\services\SurveyUpdater\GeneralSettings;

use LimeSurvey\Models\Services\SurveyUpdater\GeneralSettings;
/**
 * General Settings Mock Factory
 *
 * Reusable initialisation of mock dependencies for use in GeneralSettings tests.
 */
class GeneralSettingsFactory
{
    /**
     * @param ?GeneralSettingsMockSet $init
     */
    public function make(GeneralSettingsMockSet $mockSet = null): GeneralSettings
    {
        $mockSet = (new GeneralSettingsMockSetFactory())->make($mockSet);

        return new GeneralSettings(
            $mockSet->modelPermission,
            $mockSet->modelSurvey,
            $mockSet->yiiApp,
            $mockSet->session,
            $mockSet->pluginManager,
            $mockSet->languageConsistency
        );
    }
}
