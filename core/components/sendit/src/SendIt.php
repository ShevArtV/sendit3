<?php
/**
 * Главный сервис-фасад SendIt.
 * Инициализирует зависимости, делегирует вызовы специализированным классам.
 * Загружает CSS/JS на фронтенд.
 */

namespace SendIt;

use SendIt\Config\PresetManager;
use SendIt\Form\FormProcessor;
use SendIt\Form\ValidationManager;
use SendIt\Http\Response;
use SendIt\Session\SessionManager;
use SendIt\Pagination\PaginationHandler;
use SendIt\Upload\FileUploader;

class SendIt
{
    public \modX $modx;
    public string $formName;
    public string $presetName;
    public string $basePath;
    public string $assetsPath;
    public string $jsConfigPath;
    public string $uploaddir;
    public string $corePath;
    public array $params = [];
    public array $validates = [];
    public array $session = [];
    public int $roundPrecision;
    public object $parser;
    public array $response = [];
    public bool $forceRemove = false;
    public array $pluginParams = [];
    public array $webConfig = [];
    /** @var mixed Значение после санитизации (доступно для senditOnSetValue) */
    public mixed $newValue = null;

    private SessionManager $sessionManager;
    private Response $responseHelper;
    private PresetManager $presetManager;
    private ValidationManager $validationManager;
    private FileUploader $fileUploader;
    private FormProcessor $formProcessor;
    private PaginationHandler $paginationHandler;

    public array $hooks = [];
    public array $presets = [];
    public array $preset = [];
    public array $extendsPreset = [];
    public string $pathToPresets;
    public string $presetKey;
    private array $unsetParamsList;

    public function __construct(\modX $modx, string $presetName = '', string $formName = '')
    {
        $this->modx = $modx;
        $this->formName = $formName ?: $presetName;
        $this->presetName = $presetName ?: '';
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->sessionManager = new SessionManager($this->modx);
        $this->session = $this->sessionManager->get() ?: [];

        $this->basePath = $this->modx->getOption('base_path');
        $this->corePath = $this->modx->getOption('core_path');
        $this->assetsPath = $this->modx->getOption('assets_path');
        $this->jsConfigPath = $this->modx->getOption('si_js_config_path', '', '../configs/modules.inc.js');
        $this->roundPrecision = (int)$this->modx->getOption('si_precision', '', 2);

        $unsetParamsList = $this->modx->getOption('si_unset_params', '', 'emailTo,extends');
        $this->unsetParamsList = explode(',', $unsetParamsList);

        $uploaddir = $this->modx->getOption('si_uploaddir', '', '[[+asseetsUrl]]components/sendit/uploaded_files/');
        $this->uploaddir = str_replace('[[+asseetsUrl]]', $this->assetsPath, $uploaddir);

        $pathToPresets = $this->modx->getOption('si_path_to_presets', '', 'components/sendit/presets/sendit.inc.php');
        $this->presetKey = str_replace('.inc.php', '', basename($pathToPresets));
        $this->pathToPresets = dirname($this->corePath . $pathToPresets);

        $this->initParser();

        // Preset loading
        $this->presetManager = new PresetManager($this->modx, $this->pathToPresets, $this->presetKey);
        $this->presets = $this->presetManager->loadPresets();

        if (empty($this->presets)) {
            $this->modx->log(\modX::LOG_LEVEL_ERROR, 'Путь к пресетам не задан или задан не корректно!');
        }

        $sessionPreset = $this->session['presets'][$this->presetName] ?? [];
        $this->preset = array_merge($this->presets[$this->presetKey][$this->presetName] ?? [], $sessionPreset);

        $this->pluginParams = [];
        $this->presetManager->fireFormParamsEvent($this->formName, $this->presetName, $this);

        $this->modx->lexicon->load('sendit:default');

        $this->extendsPreset = !empty($this->preset['extends'])
            ? $this->presetManager->resolveExtends($this->preset['extends'], [], $this->presets)
            : [];

        $this->params = $this->presetManager->buildParams([
            'preset' => $this->preset,
            'extendsPreset' => $this->extendsPreset,
            'pluginParams' => $this->pluginParams,
            'formName' => $this->formName,
        ]);

        $this->hooks = !empty($this->params['hooks']) ? explode(',', $this->params['hooks']) : [];

        $this->validationManager = new ValidationManager($this->modx);

        if (!empty($this->params['validate'])) {
            $this->validates = $this->validationManager->parseValidation($this->params['validate']);
        }

        $this->params = $this->presetManager->replaceLexicons($this->params);

        $this->validationManager->sanitizePost($this->validates, $this);
        $this->validationManager->prepareValidation($this->validates, $this->params);

        if (!empty($this->params['fieldNames'])) {
            $this->validationManager->setFieldsAliases($this->params);
        }

        if (!empty($this->params['attachFilesToEmail']) && !empty($this->params['allowFiles'])) {
            $this->validationManager->attachFiles($this->params, $this->session, $this->uploaddir);
        }

        $this->params['validate'] = $this->validationManager->serializeValidation($this->validates);

        // Response helper
        $this->responseHelper = new Response($this->modx, $this->unsetParamsList);
        $this->responseHelper->setParams($this->params);
        $this->responseHelper->setEventContext($this->formName, $this->presetName, $this);

        // File uploader
        $this->fileUploader = new FileUploader($this->modx, $this->responseHelper, $this->sessionManager);

        // Form processor
        $this->formProcessor = new FormProcessor($this->modx, $this->parser, $this->responseHelper);
        $this->formProcessor->setSessionManager($this->sessionManager);

        // Pagination handler
        $this->paginationHandler = new PaginationHandler($this->modx, $this->parser, $this->sessionManager, $this->responseHelper);
    }

    private function initParser(): void
    {
        $this->parser = $this->modx->services->has('pdoTools')
            ? $this->modx->services->get('pdoTools')
            : $this->modx;
    }

    public function loadCssJs(): void
    {
        $frontend_js = $this->modx->getOption('si_frontend_js', '', '[[+assetsUrl]]components/sendit/js/web/index.js');
        $frontend_css = $this->modx->getOption('si_frontend_css', '', '[[+assetsUrl]]components/sendit/css/web/index.css');
        $basePath = $this->modx->getOption('base_path');
        $assetsUrl = str_replace($basePath, '', $this->modx->getOption('assets_path'));

        $this->getWebConfig();

        if (!empty($this->webConfig)) {
            $webConfig = json_encode($this->webConfig, JSON_UNESCAPED_UNICODE);
            $this->modx->regClientScript(
                "<script> window.siConfig = $webConfig; </script>",
                1
            );
        }

        if ($frontend_js) {
            $scriptPath = str_replace('[[+assetsUrl]]', $assetsUrl, $frontend_js);
            $this->modx->regClientScript(
                '<script type="module" src="' . $scriptPath . $this->webConfig['version'] . '"></script>',
                true
            );
        }

        if ($frontend_css) {
            $stylePath = str_replace('[[+assetsUrl]]', $assetsUrl, $frontend_css);
            $this->modx->regClientCSS($stylePath . $this->webConfig['version']);
        }
    }

    public function getWebConfig(): void
    {
        $scriptsVersion = '?v=' . $this->getVersion($this->basePath . 'assets/components/sendit/js/web/');

        $this->webConfig = [
            'version' => $scriptsVersion,
            'actionUrl' => '/assets/components/sendit/action.php',
            'modulesConfigPath' => $this->jsConfigPath,
            'cookieName' => 'SendIt',
        ];

        $this->modx->invokeEvent('senditOnGetWebConfig', [
            'webConfig' => $this->webConfig,
            'object' => $this,
        ]);
    }

    /**
     * @param string $directory
     * @return int
     */
    public function getVersion(string $directory): int
    {
        if (!is_dir($directory)) {
            return 1;
        }

        $latestTime = 1;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $fileTime = $file->getMTime();
                if ($fileTime > $latestTime) {
                    $latestTime = $fileTime;
                }
            }
        }

        return $latestTime;
    }

    /**
     * @return array|string
     */
    public function process()
    {
        return $this->formProcessor->process([
            'params' => $this->params,
            'hooks' => $this->hooks,
            'session' => $this->session,
            'formName' => $this->formName,
            'sendIt' => $this,
        ]);
    }

    public function paginationHandler(): array
    {
        return $this->paginationHandler->handle(
            $this->params,
            $this->session,
            $this->formName,
            $this->presetName,
            $this,
        );
    }

    /**
     * @param array $filesData
     * @param int $totalCount
     * @return array
     */
    public function validateFiles(array $filesData, int $totalCount = 0): array
    {
        return $this->fileUploader->validateFiles([
            'filesData' => $filesData,
            'totalCount' => $totalCount,
            'params' => $this->params,
            'session' => $this->session,
            'uploaddir' => $this->uploaddir,
            'basePath' => $this->basePath,
            'formName' => $this->formName,
            'presetName' => $this->presetName,
            'sendIt' => $this,
        ]);
    }

    /**
     * @param string $content
     * @param array $headers
     * @return array
     */
    public function uploadChunk(string $content, array $headers): array
    {
        return $this->fileUploader->uploadChunk([
            'content' => $content,
            'headers' => $headers,
            'params' => $this->params,
            'session' => $this->session,
            'uploaddir' => $this->uploaddir,
            'basePath' => $this->basePath,
        ]);
    }

    /**
     * @param string $path
     * @param bool $nomsg
     * @return array
     */
    public function removeFile(string $path, bool $nomsg = false): array
    {
        return $this->fileUploader->removeFile([
            'path' => $path,
            'nomsg' => $nomsg,
            'session' => $this->session,
            'basePath' => $this->basePath,
            'forceRemove' => $this->forceRemove,
            'sendIt' => $this,
        ]);
    }

    /**
     * @param string $message
     * @param array $data
     * @param array $placeholders
     * @return array
     */
    public function error(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->responseHelper->error($message, $data, $placeholders);
    }

    /**
     * @param string $message
     * @param array $data
     * @param array $placeholders
     * @return array
     */
    public function success(string $message = '', array $data = [], array $placeholders = []): array
    {
        return $this->responseHelper->success($message, $data, $placeholders);
    }

    /**
     * @return SessionManager
     */
    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    // ========== Deprecated static proxies ==========

    /**
     * @deprecated Используйте (new SessionManager($modx))->set($values)
     */
    public static function setSession(\modX $modx, array $values = [], string $sessionId = '', string $className = 'SendIt'): void
    {
        (new SessionManager($modx))->set($values, $sessionId, $className);
    }

    /**
     * @deprecated Используйте (new SessionManager($modx))->get()
     */
    public static function getSession(\modX $modx, string $sessionId = '', string $className = 'SendIt'): array
    {
        return (new SessionManager($modx))->get($sessionId, $className);
    }

    /**
     * @deprecated Используйте (new SessionManager($modx))->clear()
     */
    public static function clearSession(\modX $modx, string $className = 'SendIt'): void
    {
        (new SessionManager($modx))->clear($className);
    }

    /**
     * @deprecated Используйте (new SessionManager($modx))->generateId()
     */
    public static function getSessionId(\modX $modx): string
    {
        return (new SessionManager($modx))->generateId();
    }

    /**
     * @deprecated Используйте FileSystem::removeDir($dir, $modx)
     */
    public static function removeDir(string $dir, \modX $modx): void
    {
        \SendIt\Util\FileSystem::removeDir($dir, $modx);
    }
}
