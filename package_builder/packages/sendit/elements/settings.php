<?php

return [
    'si_storage_time' => [
        'value' => '86400',
        'xtype' => 'textfield',
        'area' => 'session',
    ],
    'si_use_custom_session_id' => [
        'value' => '',
        'xtype' => 'combo-boolean',
        'area' => 'session',
    ],
    'si_js_config_path' => [
        'value' => './sendit.inc.js',
        'xtype' => 'textfield',
        'area' => 'frontend',
    ],
    'si_frontend_js' => [
        'value' => '[[+assetsUrl]]components/sendit/js/web/index.js',
        'xtype' => 'textfield',
        'area' => 'frontend',
    ],
    'si_frontend_css' => [
        'value' => '[[+assetsUrl]]components/sendit/css/web/index.css',
        'xtype' => 'textfield',
        'area' => 'frontend',
    ],
    'si_precision' => [
        'value' => '2',
        'xtype' => 'textfield',
        'area' => 'uploads',
    ],
    'si_unset_params' => [
        'value' => 'emailTo,extends',
        'xtype' => 'textfield',
        'area' => 'general',
    ],
    'si_uploaddir' => [
        'value' => '[[+assetsUrl]]components/sendit/uploaded_files/',
        'xtype' => 'textfield',
        'area' => 'uploads',
    ],
    'si_path_to_presets' => [
        'value' => 'components/sendit/presets/sendit.inc.php',
        'xtype' => 'textfield',
        'area' => 'general',
    ],
    'si_allow_dirs' => [
        'value' => 'uploaded_files',
        'xtype' => 'textfield',
        'area' => 'uploads',
    ],
    'si_default_admin' => [
        'value' => '1',
        'xtype' => 'textfield',
        'area' => 'email',
    ],
    'si_default_email' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'email',
    ],
    'si_default_emailtpl' => [
        'value' => 'siDefaultEmail',
        'xtype' => 'textfield',
        'area' => 'email',
    ],
    'si_send_goal' => [
        'value' => '',
        'xtype' => 'combo-boolean',
        'area' => 'analytics',
    ],
    'si_counter_id' => [
        'value' => '',
        'xtype' => 'textfield',
        'area' => 'analytics',
    ],
    'si_pause_between_sending' => [
        'value' => '30',
        'xtype' => 'textfield',
        'area' => 'security',
    ],
    'si_max_sending_per_session' => [
        'value' => '2',
        'xtype' => 'textfield',
        'area' => 'security',
    ],
];
