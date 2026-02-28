<?php
/**
 * Обработка пагинации: рендер страниц, хеширование параметров, список страниц.
 */

namespace SendIt\Pagination;

use SendIt\Http\Response;
use SendIt\Session\SessionManager;

class PaginationHandler
{
    private \modX $modx;
    private object $parser;
    private SessionManager $sessionManager;
    private Response $response;

    public function __construct(\modX $modx, object $parser, SessionManager $sessionManager, Response $response)
    {
        $this->modx = $modx;
        $this->parser = $parser;
        $this->sessionManager = $sessionManager;
        $this->response = $response;
    }

    /**
     * @param array $params
     * @param array $session
     * @param string $formName
     * @param string $presetName
     * @param object $sendIt
     * @return array
     */
    public function handle(array &$params, array $session, string $formName, string $presetName, object $sendIt): array
    {
        $snippetName = $params['render'] ?? '!pdoResources';
        $pageKey = ($params['pagination'] ?? '') . 'page';

        unset($params['SendIt']);
        $params['limit'] = (int)($_REQUEST['limit'] ?? $params['limit'] ?? 10);
        $currentPage = !empty($_REQUEST[$pageKey]) ? (int)$_REQUEST[$pageKey] : 1;

        $hashParams = [];
        $this->modx->invokeEvent('OnBeforePageRender', [
            'formName' => $formName,
            'presetName' => $presetName,
            'SendIt' => $sendIt,
        ]);

        $params['hashParams'] = !empty($params['hashParams'])
            ? explode(',', $params['hashParams'])
            : [];
        $params['hashParams'] = array_unique(
            array_merge(['pagination', 'limit', 'presetName'], $params['hashParams'])
        );

        foreach ($params as $key => $value) {
            if (in_array($key, $params['hashParams'])) {
                $hashParams[$key] = $value;
            }
        }

        $resultShowMethod = $_REQUEST['resultShowMethod'] ?? $params['resultShowMethod'] ?? 'insert';
        $oldHash = $session['hash'][$presetName] ?? '';
        $newHash = md5(json_encode($hashParams));

        if ($oldHash !== $newHash) {
            $session['hash'][$presetName] = $newHash;
            $this->sessionManager->set(['hash' => $session['hash']]);
            $currentPage = !$oldHash ? $currentPage : 1;
            $resultShowMethod = 'insert';
        }

        $params['offset'] = $params['offset'] ?? $params['limit'] * ($currentPage - 1);
        $html = $this->parser->runSnippet($snippetName, $params);
        $total = $this->modx->getPlaceholder($params['totalVar'] ?? 'total');
        $totalPages = (int)ceil($total / $params['limit']);

        if ($totalPages && $currentPage > $totalPages) {
            $params['offset'] = ($totalPages - 1) * $params['limit'];
            $currentPage = $totalPages;
            $html = $this->parser->runSnippet($snippetName, $params);
        }

        if (!$html && ($params['tplEmpty'] ?? '')) {
            $html = $this->parser->getChunk($params['tplEmpty'], $params);
        }

        if ($totalPages === 1) {
            $resultShowMethod = 'insert';
        }

        return $this->response->success('', [
            'html' => $html,
            'totalPages' => $totalPages ?: 1,
            'total' => $total ?: 0,
            'limit' => $params['limit'],
            'pagination' => $params['pagination'] ?? '',
            'currentPage' => $currentPage,
            'resultShowMethod' => $resultShowMethod,
            'pageList' => $this->getPageList($currentPage, $totalPages, $params),
        ]);
    }

    /**
     * @param int $currentPage
     * @param int $totalPages
     * @param array $params
     * @return string
     */
    private function getPageList(int $currentPage, int $totalPages, array $params): string
    {
        $maxPageListItems = $params['maxPageListItems'] ?? 0;
        if (!$maxPageListItems) {
            return '';
        }

        if ($maxPageListItems > $totalPages) {
            $maxPageListItems = $totalPages;
        }

        $firstValue = $currentPage > 1 ? $currentPage - 1 : $currentPage;
        $lastValue = $firstValue + ($maxPageListItems - 1);
        if ($lastValue > $totalPages) {
            $firstValue -= $lastValue - $totalPages;
        }

        $pageKey = ($params['pagination'] ?? '') . 'page';
        $tplPageListItem = $params['tplPageListItem'] ?? 'siPageListItem';
        $tplPageListWrapper = $params['tplPageListWrapper'] ?? 'siPageListWrapper';

        $items = '';
        for ($i = 0; $i < $maxPageListItems; $i++) {
            $items .= $this->parser->getChunk($tplPageListItem, [
                'page' => $firstValue++,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'pageKey' => $pageKey,
            ]);
        }

        if ($items) {
            return $this->parser->getChunk($tplPageListWrapper, [
                'items' => $items,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'pageKey' => $pageKey,
            ]);
        }

        return '';
    }
}
