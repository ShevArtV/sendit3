<?php

define('MODX_CORE_PATH', __DIR__ . '/../core/');

final class FakeSession
{
    private array $values = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function fromArray(array $values): void
    {
        $this->values = array_merge($this->values, $values);
    }

    public function save(): bool
    {
        return true;
    }
}

class modX
{
    public const LOG_LEVEL_ERROR = 3;

    public array $packages = [];
    public object $event;
    public ?FakeSession $session = null;
    public $sanitizeCallback = null;

    public function __construct()
    {
        $this->event = (object)['returnedValues' => []];
    }

    public function getOption(string $key, mixed $options = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function addPackage(string $name, string $path): bool
    {
        $this->packages[$name] = $path;
        return true;
    }

    public function getObject(string $class, array $criteria): ?FakeSession
    {
        return $class === 'siSession' ? $this->session : null;
    }

    public function newObject(string $class): ?FakeSession
    {
        return $class === 'siSession' ? ($this->session = new FakeSession()) : null;
    }

    public function invokeEvent(string $name, array $params = []): void
    {
        $this->event->returnedValues = [];
        if ($name === 'siOnSanitizeFileName' && is_callable($this->sanitizeCallback)) {
            $this->event->returnedValues['name'] = ($this->sanitizeCallback)($params);
        }
    }

    public function lexicon(string $key, array $placeholders = []): string
    {
        return $key;
    }

    public function log(int $level, string $message): void
    {
    }
}

require_once __DIR__ . '/../core/components/sendit/src/Upload/FileName.php';
require_once __DIR__ . '/../core/components/sendit/src/Upload/PathGuard.php';
require_once __DIR__ . '/../core/components/sendit/src/Util/FileSystem.php';
require_once __DIR__ . '/../core/components/sendit/src/Http/Response.php';
require_once __DIR__ . '/../core/components/sendit/src/Session/SessionManager.php';
require_once __DIR__ . '/../core/components/sendit/src/Upload/FileUploader.php';
require_once __DIR__ . '/../core/components/sendit/src/Form/ValidationManager.php';

use SendIt\Form\ValidationManager;
use SendIt\Http\Response;
use SendIt\Session\SessionManager;
use SendIt\Upload\FileName;
use SendIt\Upload\FileUploader;
use SendIt\Upload\PathGuard;

function assertSameUpload(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertUpload(bool $actual, string $message): void
{
    assertSameUpload(true, $actual, $message);
}

function removeTestDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = $path . '/' . $item;
        is_dir($target) && !is_link($target) ? removeTestDirectory($target) : unlink($target);
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/sendit3-upload-hardening-' . bin2hex(random_bytes(6));
$uploadRoot = $root . '/uploaded_files/';
$outside = $root . '/outside.txt';
mkdir($uploadRoot, 0777, true);
file_put_contents($outside, 'outside');

try {
    assertSameUpload('foto-dogovora-2.png', FileName::defaultName('Фото Договора №2.PNG'), 'Имя должно транслитерироваться.');
    assertSameUpload('shell-php', FileName::harden('../../shell.php'), 'Исполняемое расширение должно обезвреживаться.');
    assertSameUpload('shell-php.jpg', FileName::harden('shell.php.jpg'), 'Двойное расширение должно стать безопасным.');

    $modx = new modX();
    $modx->sanitizeCallback = static fn (array $params): string => '../Custom Folder/' . $params['sanitized'];
    $fileName = new FileName($modx);
    assertSameUpload(
        'custom-folder/foto.png',
        $fileName->sanitize('Foto.PNG', $uploadRoot, 'form', 'preset', new stdClass()),
        'Результат плагина должен повторно пройти security-фильтр.'
    );
    $modx->sanitizeCallback = null;

    assertSameUpload('', PathGuard::sessionDirectory($uploadRoot, []), 'Пустая сессия должна блокироваться.');
    $sessionDirectory = PathGuard::sessionDirectory($uploadRoot, ['session_id' => 'sid-1']);
    assertUpload(str_ends_with($sessionDirectory, '/sid-1/'), 'Каталог сессии должен строиться внутри upload root.');
    assertSameUpload('', PathGuard::resolve($sessionDirectory, '../../../outside.txt'), 'Traversal должен блокироваться.');
    assertSameUpload('', PathGuard::resolve($sessionDirectory, '/etc/passwd'), 'Абсолютный путь должен блокироваться.');
    mkdir($sessionDirectory, 0777, true);
    symlink(dirname($outside), $sessionDirectory . 'escape');
    assertSameUpload('', PathGuard::resolve($sessionDirectory, 'escape/new.txt'), 'Symlink наружу каталога сессии должен блокироваться.');
    symlink(dirname($uploadRoot), $uploadRoot . 'sid-2');
    assertSameUpload('', PathGuard::sessionDirectory($uploadRoot, ['session_id' => 'sid-2']), 'Symlink вместо каталога сессии должен блокироваться.');

    $_COOKIE['siSession'] = 'sid-1';
    $response = new Response($modx);
    $sessionManager = new SessionManager($modx);
    $uploader = new FileUploader($modx, $response, $sessionManager);
    $sendIt = new stdClass();
    $baseConfig = [
        'uploaddir' => $uploadRoot,
        'basePath' => $root . '/',
        'formName' => 'form',
        'presetName' => 'upload',
        'params' => [
            'allowExt' => 'jpg,png',
            'maxSize' => 1,
            'maxCount' => 3,
            'portion' => 0.1,
        ],
        'sendIt' => $sendIt,
    ];
    $session = ['session_id' => 'sid-1'];
    $validation = $uploader->validateFiles($baseConfig + [
        'filesData' => ['Фото.PNG' => 3],
        'totalCount' => 0,
        'session' => $session,
    ]);
    assertSameUpload(true, $validation['success'], 'Валидный файл должен пройти validate_files.');
    assertSameUpload('foto.png', $validation['data']['names']['Фото.PNG'], 'Клиент должен получить карту имён.');

    $session = $sessionManager->get();
    assertUpload(isset($session['uploadFiles']['foto.png']), 'Валидация должна сохранить allowlist в сессию.');

    $uploadConfig = $baseConfig + ['session' => $session];
    $unvalidated = $uploader->uploadChunk($uploadConfig + [
        'content' => '<?php',
        'headers' => ['x-content-name' => 'shell.php', 'x-chunk-id' => 0, 'x-total-length' => 5],
    ]);
    assertSameUpload(false, $unvalidated['success'], 'Файл вне allowlist должен отклоняться.');
    assertUpload(!file_exists($sessionDirectory . 'shell.php'), 'Отклонённый файл не должен создаваться.');

    $wrongPreset = $uploader->uploadChunk(array_merge($uploadConfig, [
        'presetName' => 'other',
        'content' => 'abc',
        'headers' => ['x-content-name' => 'Фото.PNG', 'x-chunk-id' => 0, 'x-total-length' => 3],
    ]));
    assertSameUpload(false, $wrongPreset['success'], 'Пресет должен совпадать с validate_files.');

    $wrongLength = $uploader->uploadChunk($uploadConfig + [
        'content' => 'abcd',
        'headers' => ['x-content-name' => 'Фото.PNG', 'x-chunk-id' => 0, 'x-total-length' => 4],
    ]);
    assertSameUpload(false, $wrongLength['success'], 'Размер должен совпадать с validate_files.');

    $uploaded = $uploader->uploadChunk($uploadConfig + [
        'content' => 'abc',
        'headers' => ['x-content-name' => 'Фото.PNG', 'x-chunk-id' => 0, 'x-total-length' => 3],
    ]);
    assertSameUpload(true, $uploaded['success'], 'Валидный чанк должен загрузиться.');
    assertSameUpload('abc', file_get_contents($sessionDirectory . 'foto.png'), 'Файл должен собраться внутри каталога сессии.');

    $blockedRemove = $uploader->removeFile($baseConfig + [
        'path' => 'uploaded_files/sid-1/../../../outside.txt',
        'session' => $sessionManager->get(),
    ]);
    assertSameUpload(false, $blockedRemove['success'], 'removeFile должен блокировать traversal.');
    assertSameUpload('outside', file_get_contents($outside), 'Внешний файл не должен изменяться.');

    $manager = new ValidationManager($modx);
    file_put_contents($sessionDirectory . 'attachment.txt', 'safe');
    $_POST = ['file-list' => 'attachment.txt,../../../outside.txt'];
    $_FILES = [];
    $manager->attachFiles(
        ['attachFilesToEmail' => 'attachments', 'allowFiles' => 'file-list'],
        ['session_id' => 'sid-1'],
        $uploadRoot
    );
    assertSameUpload(['attachment.txt'], $_FILES['attachments']['name'], 'attachFiles должен добавить только файл из каталога сессии.');

    $removed = $uploader->removeFile($baseConfig + [
        'path' => 'uploaded_files/sid-1/foto.png',
        'session' => $sessionManager->get(),
    ]);
    assertSameUpload(true, $removed['success'], 'Файл из своей сессии должен удаляться.');
    assertUpload(!file_exists($sessionDirectory . 'foto.png'), 'Удалённый файл не должен остаться на диске.');
} finally {
    unset($_COOKIE['siSession']);
    removeTestDirectory($root);
}

echo "Upload hardening tests passed\n";
