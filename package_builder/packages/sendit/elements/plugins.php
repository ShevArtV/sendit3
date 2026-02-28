<?php

return [
    'SendIt' => [
        'description' => 'SendIt plugin - session, cookie, JS/CSS loading',
        'content' => 'file:elements/plugins/plugin.sendit.php',
        'events' => [
            'OnHandleRequest',
            'OnMODXInit',
            'OnManagerPageInit',
            'OnWebPageInit',
            'OnLoadWebDocument',
        ],
    ],
];
