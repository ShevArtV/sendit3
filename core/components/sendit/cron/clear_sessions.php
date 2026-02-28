<?php
/**
 * Scheduler task: clear expired SendIt sessions.
 * Executed by sFileTask — MODX is already available in scope.
 *
 * @var \modX $modx
 * @var sFileTask $task
 * @var array $scriptProperties
 */

use SendIt\Session\SessionManager;

$corePath = $modx->getOption('core_path', null, MODX_CORE_PATH);
require_once $corePath . 'components/sendit/vendor/autoload.php';

$sessionManager = new SessionManager($modx);
$sessionManager->clear();

return true;
