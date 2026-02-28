<?php
/**
 * CRUD операции с таблицей si_sessions.
 * Очистка просроченных сессий, генерация ID.
 */

namespace SendIt\Session;

use SendIt\Util\FileSystem;

class SessionManager
{
    private \modX $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
        $this->ensurePackage();
    }

    private function ensurePackage(): void
    {
        if (!isset($this->modx->packages['sendit'])) {
            $corePath = $this->modx->getOption('core_path', null, MODX_CORE_PATH);
            $this->modx->addPackage('sendit', $corePath . 'components/sendit/model/');
        }
    }

    /**
     * @param array $values
     * @param string $sessionId
     * @param string $className
     * @return void
     */
    public function set(array $values = [], string $sessionId = '', string $className = 'SendIt'): void
    {
        $sessionId = $sessionId ?: ($_COOKIE['siSession'] ?? '');
        if (!$sessionId) {
            return;
        }

        $session = $this->modx->getObject('siSession', [
            'session_id' => $sessionId,
            'class_name' => $className,
        ]);

        if (!$session) {
            $session = $this->modx->newObject('siSession');
        }

        if (!$session) {
            $this->modx->log(\modX::LOG_LEVEL_ERROR, 'Table si_sessions not found');
            return;
        }

        $item = $session->get('data') ? json_decode($session->get('data'), true) : [];
        $item = array_merge($item, $values);

        $session->fromArray([
            'session_id' => $sessionId,
            'data' => $item ? json_encode($item) : '',
            'class_name' => $className,
            'createdon' => time(),
        ]);
        $session->save();
    }

    /**
     * @param string $sessionId
     * @param string $className
     * @return array
     */
    public function get(string $sessionId = '', string $className = 'SendIt'): array
    {
        $sessionId = $sessionId ?: ($_COOKIE['siSession'] ?? '');
        if (!$sessionId) {
            return [];
        }

        $session = $this->modx->getObject('siSession', [
            'session_id' => $sessionId,
            'class_name' => $className,
        ]);

        if (!$session) {
            return [];
        }

        $sessionData = $session->get('data') ? json_decode($session->get('data'), true) : [];
        $sessionData['session_id'] = $sessionId;

        return $sessionData;
    }

    /**
     * @param string $className
     * @return void
     */
    public function clear(string $className = 'SendIt'): void
    {
        $assetsPath = $this->modx->getOption('assets_path', null, '');
        $uploaddir = $this->modx->getOption('si_uploaddir', '', '[[+asseetsUrl]]components/sendit/uploaded_files/');
        $uploaddir = str_replace('[[+asseetsUrl]]', $assetsPath, $uploaddir);
        $storageTime = $this->modx->getOption('si_storage_time', '', 86400);
        $max = date('Y-m-d H:i:s', time() - $storageTime);

        $sessions = $this->modx->getIterator('siSession', [
            'class_name' => $className,
            'createdon:<' => $max,
        ]);

        foreach ($sessions as $session) {
            if ($className === 'SendIt') {
                FileSystem::removeDir($uploaddir . $session->get('session_id'), $this->modx);
            }
            $session->remove();
        }
    }

    /**
     * @return string
     */
    public function generateId(): string
    {
        if ($this->modx->getOption('si_use_custom_session_id', '', false)) {
            return md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . time());
        }

        return session_id();
    }
}
