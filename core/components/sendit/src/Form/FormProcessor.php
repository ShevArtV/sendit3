<?php
/**
 * Обработка формы, интеграция с FormIt.
 * Rate-limiting (пауза между отправками, лимит на сессию).
 */

namespace SendIt\Form;

use SendIt\Http\Response;
use SendIt\Session\SessionManager;

class FormProcessor
{
    private \modX $modx;
    private object $parser;
    private Response $response;
    private SessionManager $sessionManager;

    public function __construct(\modX $modx, object $parser, Response $response)
    {
        $this->modx = $modx;
        $this->parser = $parser;
        $this->response = $response;
    }

    /**
     * @param SessionManager $sessionManager
     * @return void
     */
    public function setSessionManager(SessionManager $sessionManager): void
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * @param array $config
     * @return array|string
     */
    public function process(array $config)
    {
        $params = $config['params'];
        $hooks = $config['hooks'];
        $session = $config['session'];
        $formName = $config['formName'];
        $sendIt = $config['sendIt'] ?? null;

        $result = $this->checkPossibilityWork($params, $hooks, $session, $formName);

        $this->modx->invokeEvent('OnCheckPossibilityWork', [
            'formName' => $formName,
            'result' => $result,
        ]);

        $eventResponse = isset($this->modx->event->returnedValues)
            && !empty($this->modx->event->returnedValues['result'])
            ? $this->modx->event->returnedValues['result']
            : '';

        if (!empty($eventResponse)) {
            $result = $eventResponse;
        }

        if (!$result['success']) {
            return $result;
        }

        if (in_array('FormItAutoResponder', $hooks) || !empty($params['antispam'])) {
            $countSending = 0;
            if (isset($session['sendingLimits'][$formName]['countSending'])) {
                $countSending = (int)$session['sendingLimits'][$formName]['countSending'];
            }
            $session['sendingLimits'][$formName]['countSending'] = ++$countSending;
            $session['sendingLimits'][$formName]['lastSendingTime'] = time();
            $this->sessionManager->set(['sendingLimits' => $session['sendingLimits']]);
        }

        $snippet = !empty($params['snippet']) ? $params['snippet'] : 'FormIt';

        if ($snippet !== 'FormIt') {
            if (!empty($params['validate'])) {
                $this->runSnippet('FormIt', $params);
                $result = $this->handleFormIt($params);
                if (!$result['success']) {
                    return $this->response->error($result['message'], $result['data']);
                }
            }
            $params['SendIt'] = $sendIt;
            return $this->runSnippet($snippet, $params);
        }

        $this->runSnippet('FormIt', $params);
        $result = $this->handleFormIt($params);
        $status = $result['success'] ? 'success' : 'error';

        return $this->response->$status($result['message'], $result['data']);
    }

    /**
     * @param string $snippet
     * @param array $params
     * @return mixed
     */
    private function runSnippet(string $snippet, array $params): mixed
    {
        return $this->parser->runSnippet($snippet, $params);
    }

    /**
     * @param array $params
     * @return array
     */
    private function handleFormIt(array $params): array
    {
        $plPrefix = ($params['placeholderPrefix'] ?? 'fi.') . 'error.';
        $data = [];

        foreach ($this->modx->placeholders as $pls => $v) {
            if (!str_contains($pls, $plPrefix)) {
                continue;
            }
            $v = strip_tags(trim($v));
            preg_match('/[^\s]/', $v, $matches);
            if (empty($matches)) {
                continue;
            }
            if ($k = str_replace($plPrefix, '', $pls)) {
                $data['errors'][$k] = $v;
            }
        }

        $params['aliases'] = [];
        if (!empty($params['fieldNames'])) {
            $fields = explode(',', $params['fieldNames']);
            foreach ($fields as $field) {
                $items = explode('==', $field);
                $params['aliases'][$items[0]] = $items[1];
            }
        }

        if (!empty($data['errors'])) {
            return [
                'success' => false,
                'message' => $params['validationErrorMessage'] ?? '',
                'data' => $data,
            ];
        }

        $data['redirectTimeout'] = $params['redirectTimeout'] ?? 2000;
        $data['redirectUrl'] = $params['redirectTo'] ?? '';
        if (!empty($params['redirectTo']) && (int)$params['redirectTo']) {
            $data['redirectUrl'] = $this->modx->makeUrl($params['redirectTo'], '', '', 'full');
        }

        return [
            'success' => true,
            'message' => $params['successMessage'] ?? '',
            'data' => $data,
        ];
    }

    /**
     * @param array $params
     * @param array $hooks
     * @param array $session
     * @param string $formName
     * @return array
     */
    private function checkPossibilityWork(array $params, array $hooks, array $session, string $formName): array
    {
        if (!in_array('email', $hooks)
            && !in_array('FormItAutoResponder', $hooks)
            && empty($params['pauseBetweenSending'])
            && empty($params['sendingPerSession'])
        ) {
            return $this->response->success();
        }

        $pause = $params['pauseBetweenSending']
            ?? $this->modx->getOption('si_pause_between_sending', '', 30);
        $maxSendingCount = $params['sendingPerSession']
            ?? $this->modx->getOption('si_max_sending_per_session', '', 2);

        $now = time();
        $countSending = 0;
        $lastSendingTime = $now - $pause;

        if (isset($session['sendingLimits'][$formName])) {
            $countSending = (int)($session['sendingLimits'][$formName]['countSending'] ?? 0);
            $lastSendingTime = (int)($session['sendingLimits'][$formName]['lastSendingTime'] ?? $now - $pause);
        }

        $timePassed = $now - $lastSendingTime;

        if ($timePassed >= $pause && $countSending < $maxSendingCount) {
            return $this->response->success();
        }

        if ($countSending >= $maxSendingCount) {
            return $this->response->error('si_msg_count_sending_err', [], ['count' => $maxSendingCount]);
        }

        if ($timePassed < $pause) {
            return $this->response->error('si_msg_pause_err', [], ['left' => $pause - $timePassed]);
        }

        return $this->response->success();
    }
}
