<?php
/**
 * @var \modX $modx
 * @var array $scriptProperties
 */

use SendIt\SendIt;
use SendIt\Session\SessionManager;

$corePath = $modx->getOption('core_path', null, MODX_CORE_PATH);
require_once $corePath . 'components/sendit/vendor/autoload.php';

$isAjax = false;
$SendIt = $scriptProperties['SendIt'] ?? false;

if ($SendIt instanceof SendIt) {
    $isAjax = true;
} else {
    $sessionManager = new SessionManager($modx);
    $session = $sessionManager->get();
    $session['presets'][$scriptProperties['presetName']] = $scriptProperties;
    $sessionManager->set([
        'presets' => $session['presets'],
    ]);
    $SendIt = new SendIt($modx, $scriptProperties['presetName'] ?? '', $scriptProperties['formName'] ?? '');
}

$response = $SendIt->paginationHandler();

if (!$isAjax) {
    $modx->setPlaceholder($response['data']['pagination'] . '.totalPages', $response['data']['totalPages']);
    $modx->setPlaceholder($response['data']['pagination'] . '.limit', $response['data']['limit']);
    $modx->setPlaceholder($response['data']['pagination'] . '.currentPage', $response['data']['currentPage']);
    $modx->setPlaceholder($response['data']['pagination'] . '.pageList', $response['data']['pageList']);
}
return $isAjax ? $response : $response['data']['html'];
