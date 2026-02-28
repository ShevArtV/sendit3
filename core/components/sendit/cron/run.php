<?php
/**
 * @var \modX $modx
 */

use SendIt\Session\SessionManager;

define('MODX_API_MODE', true);
require_once dirname(__FILE__, 5) . '/index.php';
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

$modx->setLogLevel(\modX::LOG_LEVEL_ERROR);

$sessionManager = new SessionManager($modx);
$sessionManager->clear();
