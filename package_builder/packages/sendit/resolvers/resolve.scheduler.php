<?php
/**
 * SendIt Scheduler integration resolver.
 *
 * On install/upgrade: creates an sFileTask for periodic session cleanup (if Scheduler is installed).
 * On uninstall: removes the task and its pending runs.
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

$schedulerModelPath = $modx->getOption('core_path') . 'components/scheduler/model/';
$schedulerClassFile = $schedulerModelPath . 'scheduler/scheduler.class.php';

if (!file_exists($schedulerClassFile)) {
    $modx->log(\modX::LOG_LEVEL_INFO, '[SendIt] Scheduler not found, skipping task registration.');
    return true;
}

require_once $schedulerClassFile;
$modx->loadClass('sTask', $schedulerModelPath . 'scheduler/');
$modx->loadClass('sTaskRun', $schedulerModelPath . 'scheduler/');
$modx->addPackage('scheduler', $schedulerModelPath);

try {
    $modx->getCount('sTask');
} catch (\Exception $e) {
    $modx->log(\modX::LOG_LEVEL_INFO, '[SendIt] Scheduler tables not found, skipping task registration.');
    return true;
}

$taskNamespace = 'sendit';
$taskReference = 'clear_sessions';

if ($action === xPDOTransport::ACTION_INSTALL || $action === xPDOTransport::ACTION_UPGRADE) {
    $task = $modx->getObject('sTask', [
        'namespace' => $taskNamespace,
        'reference' => $taskReference,
    ]);

    if (!$task) {
        $task = $modx->newObject('sFileTask');
    }

    $task->fromArray([
        'class_key'   => 'sFileTask',
        'namespace'   => $taskNamespace,
        'reference'   => $taskReference,
        'description' => 'Clear expired SendIt sessions and uploaded files.',
        'content'     => 'cron/clear_sessions.php',
        'recurring'   => true,
        'interval'    => '+1 hour',
    ]);

    if (!$task->save()) {
        $modx->log(\modX::LOG_LEVEL_ERROR, '[SendIt] Failed to save Scheduler task.');
        return true;
    }

    $modx->log(\modX::LOG_LEVEL_INFO, '[SendIt] Scheduler task saved (id=' . $task->get('id') . ').');

    $pendingRuns = $modx->getCount('sTaskRun', [
        'task'   => $task->get('id'),
        'status' => 0,
    ]);

    if ($pendingRuns === 0) {
        $run = $task->schedule('+1 minute');
        if ($run) {
            $modx->log(\modX::LOG_LEVEL_INFO, '[SendIt] First task run scheduled.');
        }
    }
}

if ($action === xPDOTransport::ACTION_UNINSTALL) {
    $task = $modx->getObject('sTask', [
        'namespace' => $taskNamespace,
        'reference' => $taskReference,
    ]);

    if ($task) {
        $runs = $modx->getIterator('sTaskRun', [
            'task'   => $task->get('id'),
            'status' => 0,
        ]);

        foreach ($runs as $run) {
            $run->remove();
        }

        if ($task->remove()) {
            $modx->log(\modX::LOG_LEVEL_INFO, '[SendIt] Scheduler task removed.');
        }
    }
}

return true;
