<?php
/**
 * @var \modX $modx
 * @var array $scriptProperties
 */

use SendIt\Auth\CodeGenerator;
use SendIt\Auth\Identification;

$lu = $_GET['lu'] ?? false;
if ($lu) {
    $corePath = $modx->getOption('core_path', null, MODX_CORE_PATH);
    require_once $corePath . 'components/sendit/vendor/autoload.php';

    $toPls = $scriptProperties['toPls'] ?? false;
    $userdata = Identification::activateUser(CodeGenerator::base64urlDecode($lu), $modx, $toPls);

    if (!empty($userdata['extended']['autologin'])) {
        if (Identification::loginWithoutPass($userdata['username'], $modx, $userdata['extended']['autologin'])
            && $userdata['extended']['autologin']['afterLoginRedirectId']
        ) {
            $url = $userdata['extended']['autologin']['afterLoginRedirectId'];
            if ((int)$url > 0) {
                $url = $modx->makeUrl($userdata['extended']['autologin']['afterLoginRedirectId']);
            }
            if ($url) {
                $modx->sendRedirect($url);
            }
        } else {
            return $userdata;
        }
    }
}
return false;
