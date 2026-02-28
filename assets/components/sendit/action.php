<?php
/**
 * @var \modX $modx
 */

use SendIt\SendIt;
use SendIt\Session\SessionManager;

define('MODX_API_MODE', true);
require_once dirname(__FILE__, 4) . '/index.php';
require_once MODX_CORE_PATH . 'components/sendit/vendor/autoload.php';

$modx->setLogLevel(modX::LOG_LEVEL_ERROR);

$headers = array_change_key_case(getallheaders());
$token = $headers['x-sitoken'] ?? '';

$preset = $headers['x-sipreset'] ?? '';
$formName = $headers['x-siform'] ?? '';
$action = $headers['x-siaction'] ?? '';

$sendit = new SendIt($modx, (string)$preset, (string)$formName);

$sessionManager = new SessionManager($modx);
$session = $sessionManager->get();

if (!isset($session['sitoken']) || !$token || $token !== $session['sitoken']) {
    die(json_encode($sendit->error('si_msg_token_err')));
}

$res = [];

switch ($action) {
    case 'validate_files':
        $filesData = isset($_POST['filesData']) ? json_decode($_POST['filesData'], true) : [];
        $fileList = !empty($_POST['fileList']) ? explode(',', $_POST['fileList']) : [];
        $res = $sendit->validateFiles($filesData, count($fileList));
        break;

    case 'uploadChunk':
        $content = file_get_contents('php://input');
        $res = $sendit->uploadChunk($content, $headers);
        break;

    case 'send':
        $res = $sendit->process();
        break;

    case 'removeFile':
        $path = MODX_BASE_PATH . ($_POST['path'] ?? '');
        $nomsg = (bool)($_POST['nomsg'] ?? false);
        $res = $sendit->removeFile($path, $nomsg);
        break;
}

if (is_array($res)) {
    $res = json_encode($res);
} else {
    $res = json_encode(['result' => $res]);
}
die($res);
