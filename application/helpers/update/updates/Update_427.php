
            // Menu Link needs to be updated, cause we will revert the filemanager and enable the older one.
            $oDB->createCommand()->update(
                '{{surveymenu_entries}}',
                array(
                    'menu_link' => '',
                    'action' => 'updatesurveylocalsettings',
                    'template' => 'editLocalSettings_main_view',
                    'partial' => '/admin/survey/subview/accordion/_resources_panel'
                ),
                "name='resources'"
            );
