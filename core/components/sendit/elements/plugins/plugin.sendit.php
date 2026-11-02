<?php
/**
 * @var \modX $modx
 */

use SendIt\SendIt;
use SendIt\Session\SessionManager;
use SendIt\Session\TokenTtl;

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
        $jsConfigPath = $modx->getOption('si_js_config_path', '', '../configs/modules.inc.js');
        $cookies = !empty($_COOKIE['SendIt']) ? json_decode($_COOKIE['SendIt'], true) : [];

        $powChallenge = bin2hex(random_bytes(16));
        $behaviorKey = bin2hex(random_bytes(16));
        // Токен живёт si_token_ttl, а не одну страницу: новый на каждой странице ломал фоновые
        // запросы, отправленные одновременно с другой загрузкой страницы той же сессии.
        $token = TokenTtl::issue($modx, $sessionManager->get());

        $data = [
            'simsgantispam' => $modx->lexicon('si_msg_trusted_err'),
            'sitoken' => $token['sitoken'],
            'sitrusted' => '0',
            'sijsconfigpath' => $jsConfigPath,
            'sipowchallenge' => $powChallenge,
            'sibehaviorkey' => $behaviorKey,
        ];
        $sessionManager->set(array_merge($token, [
            'sendingLimits' => [],
            'powChallenge' => $powChallenge,
            'powTimestamp' => time(),
            'behaviorKey' => $behaviorKey,
        ]));

        $data = array_merge($cookies, $data);
        setcookie('SendIt', json_encode($data), 0, '/');

        break;
    case 'OnLoadWebDocument':
        $SendIt = new SendIt($modx);
        $SendIt->loadCssJs();
        break;
}
