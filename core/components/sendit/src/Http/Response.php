<?php
/**
 * Формирование стандартного success/error ответа.
 * Вызов события OnBeforeReturnResponse.
 * Очистка чувствительных параметров из ответа.
 */

namespace SendIt\Http;

class Response
{
    private \modX $modx;
    private array $unsetParamsList;
    private array $params = [];
    private string $formName = '';
    private string $presetName = '';
    private ?object $sendIt = null;

    /**
     * @param \modX $modx
     * @param array $unsetParamsList
     */
    public function __construct(\modX $modx, array $unsetParamsList = [])
    {
        $this->modx = $modx;
        $this->unsetParamsList = $unsetParamsList;
    }

    /**
     * @param array $params
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * @param string $formName
     * @param string $presetName
     * @param object|null $sendIt
     * @return void
     */
    public function setEventContext(string $formName, string $presetName, ?object $sendIt = null): void
    {
        $this->formName = $formName;
        $this->presetName = $presetName;
        $this->sendIt = $sendIt;
    }

    /**
     * @param string $message
     * @param array $data
     * @param array $placeholders
     * @return array
     */
    public function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->build(false, $message, $data, $placeholders);
    }

    /**
     * @param string $message
     * @param array $data
     * @param array $placeholders
     * @return array
     */
    public function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->build(true, $message, $data, $placeholders);
    }

    /**
     * @param bool $status
     * @param string $message
     * @param array $data
     * @param array $placeholders
     * @return array
     */
    private function build(bool $status, string $message, array $data, array $placeholders): array
    {
        $response = array_merge($this->params, $data);

        if ($this->sendIt !== null) {
            $this->sendIt->response = $response;
        }

        $this->modx->invokeEvent('OnBeforeReturnResponse', [
            'formName' => $this->formName,
            'presetName' => $this->presetName,
            'SendIt' => $this->sendIt,
        ]);

        if ($this->sendIt !== null) {
            $response = $this->sendIt->response;
        }

        $response = $this->cleanParams($response);
        unset($response['SendIt']);

        return [
            'success' => $status,
            'message' => $this->modx->lexicon($message, $placeholders),
            'data' => $response,
        ];
    }

    /**
     * @param array $paramList
     * @return array
     */
    private function cleanParams(array $paramList): array
    {
        foreach ($this->unsetParamsList as $param) {
            unset($paramList[$param]);
        }

        return $paramList;
    }
}
