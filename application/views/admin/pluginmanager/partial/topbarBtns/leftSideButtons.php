<?php

/** @var bool $showUpload */
/** @var string $scanFilesUrl */

if ($showUpload) {
    $this->widget(
        'ext.ButtonWidget.ButtonWidget',
        [
            'name' => 'plugin-install-button',
            'id' => 'plugin-install-button',
            'text' => gT('Upload & install'),
            'icon' => 'icon-import',
            'htmlOptions' => [
                'class' => 'btn btn-outline-secondary',
                'data-bs-toggle' => "modal",
                'data-bs-target' => '#installPluginZipModal',
                'title' => gT('Install plugin by ZIP archive')
            ],
        ]
    );
}

if ($scanFilesUrl !== null && count($scanFilesUrl) > 0) {
    $this->widget(
        'ext.ButtonWidget.ButtonWidget',
        [
            'name' => 'plugin-scanfiles-button',
            'id' => 'plugin-scanfiles-button',
            'text' => gT('Scan files'),
            'icon' => 'fa fa-search',
            'link' => $scanFilesUrl,
            'htmlOptions' => [
                'class' => 'btn btn-outline-secondary',
                'data-bs-toggle' => 'tooltip',
                'title' => gT('Scan files for available plugins')
            ],
        ]
    );
}
