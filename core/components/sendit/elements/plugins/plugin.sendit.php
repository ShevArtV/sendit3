<?php
/**
 * @var \modX $modx
 */

use SendIt\SendIt;
use SendIt\Session\SessionManager;

$corePath = $modx->getOption('core_path', null, MODX_CORE_PATH);
require_once $corePath . 'components/sendit/vendor/autoload.php';

$sessionManager = new SessionManager($modx);

switch ($modx->event->name) {
    case 'OnHandleRequest':
        if (!($_COOKIE['siSession'] ?? '')) {
            $_COOKIE['siSession'] = $sessionManager->generateId();
            $expires = time() + $modx->getOption('si_storage_time', '', 86400);
            setcookie('siSession', $_COOKIE['siSession'], $expires, '/', '', false, true);
        }
        break;
    case 'OnMODXInit':
        if (!$modx->services->has('scheduler')) {
            $sessionManager->clear();
        }
        break;
    case 'OnManagerPageInit':
    case 'OnWebPageInit':
        $alias = !empty($_REQUEST['q']) ? explode('.', basename($_REQUEST['q'])) : [];
        if (isset($alias[1]) && $alias[1] !== 'html') {
            return;
        }

        $modx->lexicon->load('sendit:default');
        $jsConfigPath = $modx->getOption('si_js_config_path', '', './sendit.inc.js');
        $cookies = !empty($_COOKIE['SendIt']) ? json_decode($_COOKIE['SendIt'], true) : [];

        $data = [
            'simsgantispam' => $modx->lexicon('si_msg_trusted_err'),
            'sitoken' => md5($_SERVER['REMOTE_ADDR'] . time()),
            'sitrusted' => '0',
            'sijsconfigpath' => $jsConfigPath,
        ];
        $sessionManager->set([
            'sitoken' => $data['sitoken'],
            'sendingLimits' => [],
        ]);

        $data = array_merge($cookies, $data);
        setcookie('SendIt', json_encode($data), 0, '/');

        break;
    case 'OnLoadWebDocument':
        $SendIt = new SendIt($modx);
        $SendIt->loadCssJs();
        break;
}
