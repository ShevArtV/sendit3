<?php
/**
 * @var \modX $modx
 * @var object $hook
 * @var array $scriptProperties
 */

use SendIt\Auth\Identification;

$corePath = $modx->getOption('core_path', null, MODX_CORE_PATH);
require_once $corePath . 'components/sendit/vendor/autoload.php';

if (isset($scriptProperties['method'])) {
    $method = $scriptProperties['method'];
    $identification = new Identification($modx, $hook, $scriptProperties);
    return $identification->$method();
}
return true;
