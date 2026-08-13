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
    private FileName $fileName;
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
        $this->fileName = new FileName($modx);
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
        $uploaddir = PathGuard::sessionDirectory($config['uploaddir'], $session);
        $basePath = $config['basePath'];

        $this->modx->invokeEvent('OnBeforeFileValidate', [
            'formName' => $config['formName'] ?? '',
            'presetName' => $config['presetName'] ?? '',
            'SendIt' => $config['sendIt'] ?? null,
            'filesData' => $filesData,
            'totalCount' => $totalCount,
        ]);

        $totalCount = $this->modx->event->returnedValues['totalCount'] ?? $totalCount;

        if ($uploaddir === '') {
            return $this->response->error('si_msg_file_remove_session_err', [], ['filename' => '']);
        }

        $allowExt = !empty($params['allowExt'])
            ? array_map(static fn ($ext) => strtolower(trim($ext)), explode(',', $params['allowExt']))
            : [];
        $maxSize = !empty($params['maxSize']) ? (float)$params['maxSize'] * 1024 * 1024 : 1024 * 1024;
        $maxCount = !empty($params['maxCount']) ? (int)$params['maxCount'] : 1;

        $status = 'success';
        $data = [
            'fileNames' => [],
            'errors' => [],
            'names' => [],
            'portion' => !empty($params['portion']) ? $params['portion'] : 0.1,
            'threadsQuantity' => !empty($params['threadsQuantity']) ? $params['threadsQuantity'] : 1,
        ];
        $validated = [];

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
            $safeName = $this->fileName->sanitize(
                (string)$filename,
                $uploaddir,
                (string)($config['formName'] ?? ''),
                (string)($config['presetName'] ?? ''),
                $config['sendIt']
            );
            $target = $safeName !== '' ? PathGuard::resolve($uploaddir, $safeName) : '';
            if ($safeName === '' || $target === '') {
                $data['errors'][$filename] = $this->modx->lexicon('si_msg_file_name_err', ['filename' => $filename]);
                $data['fileNames'][] = $filename;
                $status = 'error';
                continue;
            }
            $data['names'][$filename] = $safeName;
            [$nameWithoutExt, $fileExt] = FileName::split($safeName);
            $dir = PathGuard::resolve($uploaddir, $nameWithoutExt . '/');

            if (is_file($target)) {
                $data['loaded'][$filename] = $baseUploadUrl . $session['session_id'] . '/' . $safeName;
            }

            $uploadedSize = $session['uploadedSize'][$safeName] ?? 0;
            if ($dir !== '' && is_dir($dir) && $uploadedSize) {
                $percent = $this->getPercent($uploadedSize, $filesize);
                if ($percent < 100 && $percent > 0) {
                    $chunks = scandir($dir);
                    unset($chunks[0], $chunks[1]);
                    $msg = $this->getLoadingMsg($percent, $uploadedSize, $filesize, $safeName, $params);
                    $data['start'][$safeName] = [
                        'percent' => "{$percent}%",
                        'bytes' => $uploadedSize,
                        'chunks' => implode(',', $chunks),
                        'msg' => $msg,
                    ];
                }
            }

            $isValid = true;
            if ($maxSize <= $filesize) {
                $data['errors'][$filename] = ($data['errors'][$filename] ?? '') . $this->modx->lexicon('si_msg_file_size_err');
                $data['fileNames'][] = $filename;
                $status = 'error';
                $isValid = false;
            }

            if (!in_array(strtolower($fileExt), $allowExt, true)) {
                $data['errors'][$filename] = ($data['errors'][$filename] ?? '') . $this->modx->lexicon('si_msg_file_extention_err');
                $data['fileNames'][] = $filename;
                $status = 'error';
                $isValid = false;
            }

            if ($isValid) {
                $validated[$safeName] = [
                    'client' => (string)$filename,
                    'size' => (int)$filesize,
                    'ext' => strtolower($fileExt),
                    'preset' => (string)($config['presetName'] ?? ''),
                    'portion' => (float)$data['portion'],
                    'maxSize' => (int)$maxSize,
                ];
            }
        }

        if ($validated) {
            $uploadFiles = array_merge($session['uploadFiles'] ?? [], $validated);
            $this->sessionManager->set(['uploadFiles' => $uploadFiles]);
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
        $session = $config['session'];
        $uploaddir = PathGuard::sessionDirectory($config['uploaddir'], $session);
        $basePath = $config['basePath'];
        $requestedName = (string)($headers['x-content-name'] ?? '');
        $displayName = basename(str_replace('\\', '/', $requestedName));
        [$safeName, $file] = $this->findValidatedFile($requestedName, $session);
        if (!$file || (string)($file['preset'] ?? '') !== (string)($config['presetName'] ?? '')) {
            return $this->response->error('si_msg_file_not_validated_err', [], ['filename' => $displayName]);
        }

        $chunkId = $headers['x-chunk-id'] ?? null;
        $totalLength = $headers['x-total-length'] ?? null;
        if (!is_numeric($chunkId) || (int)$chunkId < 0 || !is_numeric($totalLength) || (int)$totalLength <= 0) {
            return $this->response->error('si_msg_file_upload_params_err', [], ['filename' => $displayName]);
        }
        $chunkId = (int)$chunkId;
        $totalLength = (int)$totalLength;
        $portion = max(1, (int)round((float)($file['portion'] ?? 0.1) * 1024 * 1024));
        $expectedSize = (int)($file['size'] ?? 0);
        $maxSize = (int)($file['maxSize'] ?? 0);
        $expectedChunks = (int)ceil($expectedSize / $portion);
        if ($totalLength !== $expectedSize || ($maxSize > 0 && $totalLength >= $maxSize)
            || strlen($content) > $portion || $chunkId >= $expectedChunks) {
            return $this->response->error('si_msg_file_upload_params_err', [], ['filename' => $displayName]);
        }

        [, $extension] = FileName::split($safeName);
        if (strtolower($extension) !== (string)($file['ext'] ?? '')) {
            return $this->response->error('si_msg_file_not_validated_err', [], ['filename' => $displayName]);
        }

        $filename = $uploaddir !== '' ? PathGuard::resolve($uploaddir, $safeName) : '';
        if ($filename === '') {
            return $this->response->error('si_msg_file_name_err', [], ['filename' => $displayName]);
        }

        if (!is_dir($uploaddir)) {
            mkdir($uploaddir, 0777, true);
        }

        $baseUploadUrl = str_replace($basePath, '', $config['uploaddir']);
        $relativePath = $baseUploadUrl . $session['session_id'] . '/' . $safeName;

        if (is_file($filename) && filesize($filename) === $expectedSize) {
            return $this->response->success($this->modx->lexicon('si_msg_loading', [
                'filename' => $displayName,
                'percent' => 100,
            ]), [
                'path' => $relativePath,
                'percent' => '100%',
                'filename' => $safeName,
                'savedName' => $safeName,
                'chunkId' => $chunkId,
            ]);
        }

        [$nameWithoutExt, $fileExt] = FileName::split($safeName);
        $dir = PathGuard::resolve($uploaddir, $nameWithoutExt . '/');
        if ($dir === '') {
            return $this->response->error('si_msg_file_name_err', [], ['filename' => $displayName]);
        }
        $dir .= '/';
        $chunkName = $fileExt !== '' ? $chunkId . '.' . $fileExt : (string)$chunkId;
        $chunkPath = $dir . $chunkName;

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($chunkPath) || filesize($chunkPath) < strlen($content)) {
            file_put_contents($chunkPath, $content);
        }

        $uploadedSize = 0;
        foreach (scandir($dir) ?: [] as $chunk) {
            if ($chunk !== '.' && $chunk !== '..' && is_file($dir . $chunk)) {
                $uploadedSize += (int)filesize($dir . $chunk);
            }
        }
        $this->sessionManager->set(['uploadedSize' => array_merge(
            $session['uploadedSize'] ?? [],
            [$safeName => min($uploadedSize, $totalLength)]
        )]);

        $percent = $this->getPercent(min($uploadedSize, $totalLength), $totalLength);
        $msg = $this->getLoadingMsg($percent, min($uploadedSize, $totalLength), $totalLength, $displayName, $config['params']);

        if ($uploadedSize < $totalLength) {
            return $this->response->success($msg, [
                'percent' => "{$percent}%",
                'bytes' => $uploadedSize,
                'filename' => $safeName,
                'savedName' => $safeName,
                'chunkId' => $chunkId,
            ]);
        }

        $this->assembleFile($filename, $dir, $fileExt);
        if (!is_file($filename) || filesize($filename) !== $expectedSize) {
            @unlink($filename);
            return $this->response->error('si_msg_file_upload_params_err', [], ['filename' => $displayName]);
        }

        FileSystem::removeDir($dir, $this->modx);

        return $this->response->success($msg, [
            'path' => $relativePath,
            'percent' => "{$percent}%",
            'filename' => $safeName,
            'savedName' => $safeName,
            'chunkId' => $chunkId,
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

        $filename = basename(str_replace('\\', '/', $path));
        $uploaddir = PathGuard::sessionDirectory($config['uploaddir'], $session);
        if ($uploaddir === '' && !$forceRemove) {
            return $this->response->error('si_msg_file_remove_session_err', [], ['filename' => $filename]);
        }

        if ($forceRemove) {
            $target = $path;
            $chunkDir = dirname($path);
            $responseName = $filename;
        } else {
            $relative = PathGuard::relativeFromRequest($path, $uploaddir, $basePath);
            $target = $relative !== '' ? PathGuard::resolve($uploaddir, $relative) : '';
            if ($target === '') {
                return $this->response->error('si_msg_file_remove_session_err', [], ['filename' => $filename]);
            }
            [$nameWithoutExt] = FileName::split($relative);
            $chunkDir = $nameWithoutExt !== '' ? PathGuard::resolve($uploaddir, $nameWithoutExt . '/') : '';
            $responseName = $relative;
        }

        $this->modx->invokeEvent('OnBeforeFileRemove', [
            'path' => $target,
            'SendIt' => $config['sendIt'] ?? null,
        ]);

        $uploadedSize = $session['uploadedSize'] ?? [];
        unset($uploadedSize[$filename]);
        $uploadFiles = $session['uploadFiles'] ?? [];
        [$safeName] = $this->findValidatedFile($relative ?? $filename, $session);
        if ($safeName !== '') {
            unset($uploadFiles[$safeName], $uploadedSize[$safeName]);
        }
        $this->sessionManager->set(['uploadedSize' => $uploadedSize, 'uploadFiles' => $uploadFiles]);

        if (is_file($target)) {
            unlink($target);
        } elseif ($chunkDir !== '' && is_dir($chunkDir)) {
            FileSystem::removeDir($chunkDir, $this->modx);
        }

        $msg = $nomsg ? '' : 'si_msg_file_remove_success';

        return $this->response->success($msg, [
            'filename' => $responseName,
            'path' => str_replace($basePath, '', $path),
            'nomsg' => $nomsg,
        ]);
    }

    /** @return array{0:string,1:array} */
    private function findValidatedFile(string $name, array $session): array
    {
        if ($name === '') {
            return ['', []];
        }
        $files = $session['uploadFiles'] ?? [];
        if (isset($files[$name]) && is_array($files[$name])) {
            return [$name, $files[$name]];
        }
        foreach ($files as $safeName => $file) {
            if (is_array($file) && (string)($file['client'] ?? '') === $name) {
                return [(string)$safeName, $file];
            }
        }

        return ['', []];
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
        while (file_exists($dir . ($fileExt !== '' ? $i . '.' . $fileExt : (string)$i))) {
            $name = $dir . ($fileExt !== '' ? $i . '.' . $fileExt : (string)$i);
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
