<?php
/**
 * @var \modX $modx
 * @var array $scriptProperties
 */

use SendIt\Session\SessionManager;

$corePath = $modx->getOption('core_path', null, MODX_CORE_PATH);
require_once $corePath . 'components/sendit/vendor/autoload.php';

$parser = $modx->services->has('pdoTools') ? $modx->services->get('pdoTools') : $modx;
$tpl = $scriptProperties['tpl'] ?? $scriptProperties['form'] ?? '';
$presetName = $scriptProperties['presetName'] ?? '';
$content = $parser->parseChunk($tpl, $scriptProperties);

$sessionManager = new SessionManager($modx);
$session = $sessionManager->get();
$session['presets'][$presetName] = $scriptProperties;
$sessionManager->set($session);

return $content;
