<?php
/**
 * Creates/updates the si_sessions table from the xPDO schema.
 *
 * @var xPDO\Transport\xPDOTransport $transport
 * @var array $options
 */

use xPDO\Transport\xPDOTransport;

if (!$transport->xpdo) {
    return true;
}

/** @var \modX $modx */
$modx = $transport->xpdo;
$action = $options[xPDOTransport::PACKAGE_ACTION] ?? '';

if ($action === xPDOTransport::ACTION_UNINSTALL) {
    return true;
}

$corePath = $modx->getOption('core_path') . 'components/sendit/';
$modelPath = $corePath . 'model/';

$modx->addPackage('sendit', $modelPath);

$manager = $modx->getManager();

$manager->createObjectContainer('siSession');

$modx->log(\modX::LOG_LEVEL_INFO, '[SendIt] Table si_sessions checked/created.');

return true;
