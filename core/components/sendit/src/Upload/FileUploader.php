<?php
/**
 * Валидация файлов, чанкованная загрузка с прогрессом, удаление файлов.
 */

namespace SendIt\Upload;

use SendIt\Http\Response;
use SendIt\Session\SessionManager;
use SendIt\Util\FileSystem;

class FileUploader
{
    private \modX $modx;
    private Response $response;
    private SessionManager $sessionManager;
    private int $roundPrecision;

    /**
     * @param \modX $modx
     * @param Response $response
     * @param SessionManager $sessionManager
     */
    public function __construct(\modX $modx, Response $response, SessionManager $sessionManager)
    {
        $this->modx = $modx;
        $this->response = $response;
        $this->sessionManager = $sessionManager;
        $this->roundPrecision = (int)$modx->getOption('si_precision', '', 2);
    }

    /**
     * @param array $config
     * @return array
     */
    public function validateFiles(array $config): array
    {
        $filesData = $config['filesData'];
        $totalCount = $config['totalCount'] ?? 0;
        $params = $config['params'];
        $session = $config['session'];
        $uploaddir = $config['uploaddir'] . ($session['session_id'] ?? '') . '/';
        $basePath = $config['basePath'];

        $this->modx->invokeEvent('OnBeforeFileValidate', [
            'formName' => $config['formName'] ?? '',
            'presetName' => $config['presetName'] ?? '',
            'SendIt' => $config['sendIt'] ?? null,
            'filesData' => $filesData,
            'totalCount' => $totalCount,
        ]);

        $totalCount = $this->modx->event->returnedValues['totalCount'] ?? $totalCount;

        $allowExt = !empty($params['allowExt']) ? explode(',', $params['allowExt']) : [];
        $maxSize = !empty($params['maxSize']) ? (float)$params['maxSize'] * 1024 * 1024 : 1024 * 1024;
        $maxCount = !empty($params['maxCount']) ? (int)$params['maxCount'] : 1;

        $status = 'success';
        $data = [
            'fileNames' => [],
            'errors' => [],
            'portion' => !empty($params['portion']) ? $params['portion'] : 0.1,
            'threadsQuantity' => !empty($params['threadsQuantity']) ? $params['threadsQuantity'] : 1,
        ];

        if ($maxCount < ($totalCount + count($filesData))) {
            $left = $maxCount - $totalCount;
            $declension = $this->getDeclension($left, 'файл', 'файла', 'файлов');
            if ($totalCount === 0) {
                $data['errors']['size'] = $this->modx->lexicon('si_msg_files_maxcount_err', [
                    'left' => $left, 'declension' => $declension,
                ]);
            } elseif ($left === 0) {
                $data['errors']['size'] = $this->modx->lexicon('si_msg_files_loaded_err');
            } else {
                $data['errors']['size'] = $this->modx->lexicon('si_msg_files_count_err', [
                    'left' => $left, 'declension' => $declension,
                ]);
            }
            return $this->response->error('', $data);
        }

        $baseUploadUrl = str_replace($basePath, '', $config['uploaddir']);

        foreach ($filesData as $filename => $filesize) {
            $data['aliases'][$filename] = $filename;
            [$nameWithoutExt, $fileExt] = $this->getFileParts($filename);
            $dir = $uploaddir . $nameWithoutExt . '/';

            if ($status === 'error') {
                $data['fileNames'][] = $filename;
            }

            if (file_exists($uploaddir . $filename)) {
                $data['loaded'][$filename] = $baseUploadUrl . ($session['session_id'] ?? '') . '/' . $filename;
                $status = 'success';
            }

            $uploadedSize = $session['uploadedSize'][$filename] ?? 0;
            if (file_exists($dir) && $uploadedSize) {
                $percent = $this->getPercent($uploadedSize, $filesize);
                if ($percent < 100 && $percent > 0) {
                    $chunks = scandir($dir);
                    unset($chunks[0], $chunks[1]);
                    $msg = $this->getLoadingMsg($percent, $uploadedSize, $filesize, $filename, $params);
                    $data['start'][$filename] = [
                        'percent' => "{$percent}%",
                        'bytes' => $uploadedSize,
                        'chunks' => implode(',', $chunks),
                        'msg' => $msg,
                    ];
                }
            }

            if ($maxSize <= $filesize) {
                $data['errors'][$filename] = ($data['errors'][$filename] ?? '') . $this->modx->lexicon('si_msg_file_size_err');
                $data['fileNames'][] = $filename;
                $status = 'error';
            }

            if (!in_array($fileExt, $allowExt)) {
                $data['errors'][$filename] = ($data['errors'][$filename] ?? '') . $this->modx->lexicon('si_msg_file_extention_err');
                $data['fileNames'][] = $filename;
                $status = 'error';
            }
        }

        $data['fileNames'] = array_unique($data['fileNames']);
        $data['queueMsg'] = $this->modx->lexicon('si_msg_queue');

        return $status === 'success'
            ? $this->response->success('', $data)
            : $this->response->error('', $data);
    }

    /**
     * @param array $config
     * @return array
     */
    public function uploadChunk(array $config): array
    {
        $content = $config['content'];
        $headers = $config['headers'];
        $params = $config['params'];
        $session = $config['session'];
        $uploaddir = $config['uploaddir'] . ($session['session_id'] ?? '') . '/';
        $basePath = $config['basePath'];

        $filename = $uploaddir . $headers['x-content-name'];

        if (!is_dir($uploaddir)) {
            mkdir($uploaddir, 0777, true);
        }

        $baseUploadUrl = str_replace($basePath, '', $config['uploaddir']);

        if (file_exists($filename)) {
            return $this->response->success($this->modx->lexicon('si_msg_loading', [
                'filename' => $headers['x-content-name'],
                'percent' => 100,
            ]), [
                'path' => $baseUploadUrl . ($session['session_id'] ?? '') . '/' . $headers['x-content-name'],
                'percent' => '100%',
                'filename' => $headers['x-content-name'],
                'chunkId' => $headers['x-chunk-id'],
            ]);
        }

        [$nameWithoutExt, $fileExt] = $this->getFileParts($headers['x-content-name']);

        $dir = $uploaddir . $nameWithoutExt . '/';
        $chunkName = $headers['x-chunk-id'] . '.' . $fileExt;
        $chunkPath = $dir . $chunkName;

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($chunkPath) || filesize($chunkPath) < $headers['content-length']) {
            file_put_contents($chunkPath, $content);
        }

        $portion = $params['portion'] * 1024 * 1024;
        $countChunks = count(scandir($dir)) - 2;
        $uploadedSize = $countChunks * $portion;

        if ($uploadedSize > $headers['x-total-length']) {
            $uploadedSize = $headers['x-total-length'];
        }

        $percent = $this->getPercent($uploadedSize, $headers['x-total-length']);
        $msg = $this->getLoadingMsg($percent, $uploadedSize, $headers['x-total-length'], $headers['x-content-name'], $params);

        if ($uploadedSize < $headers['x-total-length']) {
            return $this->response->success($msg, [
                'percent' => "{$percent}%",
                'bytes' => $session['uploadedSize'][$headers['x-content-name']] ?? 0,
                'filename' => $headers['x-content-name'],
                'chunkId' => $headers['x-chunk-id'],
            ]);
        }

        $this->assembleFile($filename, $dir, $fileExt);

        FileSystem::removeDir($dir, $this->modx);

        return $this->response->success($msg, [
            'path' => $baseUploadUrl . ($session['session_id'] ?? '') . '/' . $headers['x-content-name'],
            'percent' => "{$percent}%",
            'filename' => $headers['x-content-name'],
            'chunkId' => $headers['x-chunk-id'],
        ]);
    }

    /**
     * @param array $config
     * @return array
     */
    public function removeFile(array $config): array
    {
        $path = $config['path'];
        $nomsg = $config['nomsg'] ?? false;
        $session = $config['session'];
        $basePath = $config['basePath'];
        $forceRemove = $config['forceRemove'] ?? false;

        $filename = basename($path);
        $dir = str_replace($filename, '', $path);

        $this->modx->invokeEvent('OnBeforeFileRemove', [
            'path' => $path,
            'SendIt' => $config['sendIt'] ?? null,
        ]);

        if (!str_contains($path, $session['session_id'] ?? '') && !$forceRemove) {
            return $this->response->error('si_msg_file_remove_session_err', [], ['filename' => $filename]);
        }

        $uploadedSize = $session['uploadedSize'] ?? [];
        unset($uploadedSize[$filename]);
        $this->sessionManager->set(['uploadedSize' => $uploadedSize]);

        if (file_exists($path)) {
            unlink($path);
        } elseif (file_exists($dir)) {
            FileSystem::removeDir($dir, $this->modx);
        }

        $msg = $nomsg ? '' : 'si_msg_file_remove_success';

        return $this->response->success($msg, [
            'filename' => $filename,
            'path' => str_replace($basePath, '', $path),
            'nomsg' => $nomsg,
        ]);
    }

    /**
     * @param string $filename
     * @return array{0: string, 1: string}
     */
    private function getFileParts(string $filename): array
    {
        $nameParts = explode('.', $filename);
        $lastIndex = count($nameParts) - 1;
        $fileExt = $nameParts[$lastIndex];
        unset($nameParts[$lastIndex]);

        return [implode('.', $nameParts), $fileExt];
    }

    /**
     * @param int $percent
     * @param int $uploadedSize
     * @param int $totalSize
     * @param string $filename
     * @param array $params
     * @return string
     */
    private function getLoadingMsg(int $percent, int $uploadedSize, int $totalSize, string $filename, array $params): string
    {
        $unit = $params['loadedUnit'] ?? '%';
        $key = 'si_msg_loading_bytes';
        $data = ['filename' => $filename, 'unit' => $unit];

        switch (strtolower($unit)) {
            case 'b':
                $data['bytes'] = $uploadedSize;
                $data['total'] = $totalSize;
                break;
            case 'kb':
                $data['bytes'] = round($uploadedSize / 1024);
                $data['total'] = round($totalSize / 1024);
                break;
            case 'mb':
                $data['bytes'] = round($uploadedSize / (1024 * 1024), 1);
                $data['total'] = round($totalSize / (1024 * 1024), 1);
                break;
            case 'gb':
                $data['bytes'] = round($uploadedSize / (1024 * 1024 * 1024), 2);
                $data['total'] = round($totalSize / (1024 * 1024 * 1024), 2);
                break;
            default:
                $key = 'si_msg_loading';
                $data['percent'] = $percent;
                break;
        }

        return $this->modx->lexicon($key, $data);
    }

    /**
     * @param int $uploadedSize
     * @param int $totalSize
     * @return int
     */
    private function getPercent(int $uploadedSize, int $totalSize): int
    {
        $percent = (int)round($uploadedSize * 100 / $totalSize, $this->roundPrecision);
        if ($percent > 99) {
            $percent = 100;
        }

        return $percent;
    }

    /**
     * @param int $number
     * @param string $form1
     * @param string $form2
     * @param string $form3
     * @return string
     */
    private function getDeclension(int $number, string $form1, string $form2, string $form3): string
    {
        $number = abs($number) % 100;
        $mod = $number % 10;

        if ($number > 10 && $number < 20) {
            return $form3;
        } elseif ($mod > 1 && $mod < 5) {
            return $form2;
        } elseif ($mod === 1) {
            return $form1;
        }

        return $form3;
    }

    /**
     * @param string $filename
     * @param string $dir
     * @param string $fileExt
     * @return void
     */
    private function assembleFile(string $filename, string $dir, string $fileExt): void
    {
        $i = 0;
        while (file_exists($dir . $i . '.' . $fileExt)) {
            $name = $dir . $i . '.' . $fileExt;
            $mode = !file_exists($filename) ? 'wb' : 'ab';
            $fout = fopen($filename, $mode);
            $fin = fopen($name, 'rb');
            if ($fin) {
                while (!feof($fin)) {
                    $data = fread($fin, 1024 * 1024);
                    fwrite($fout, $data);
                }
                fclose($fin);
            }
            fclose($fout);
            unlink($name);
            $i++;
        }
    }
}
