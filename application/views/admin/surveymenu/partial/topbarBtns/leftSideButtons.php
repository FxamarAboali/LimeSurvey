<?php

$this->widget(
    'ext.ButtonWidget.ButtonWidget',
    [
        'name' => 'createnewmenu',
        'id' => 'createnewmenu',
        'text' => gT('New menu'),
        'icon' => 'icon-add',
        'htmlOptions' => [
            'class' => 'btn btn-outline-secondary tab-dependent-button',
            'title' => gT('Add new menu'),
            'data-tab' => '#surveymenues'
        ],
    ]
);

$this->widget(
    'ext.ButtonWidget.ButtonWidget',
    [
        'name' => 'createnewmenuentry',
        'id' => 'createnewmenuentry',
        'text' => gT('New menu entry'),
        'icon' => 'icon-add',
        'htmlOptions' => [
            'class' => 'btn btn-outline-secondary tab-dependent-button',
            'title' => gT('Add new menu entry'),
            'data-tab' => '#surveymenuentries',
            'style' => 'dislpay:none;'
        ],
    ]
);
