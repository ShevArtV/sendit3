<?php
/**
 * Загрузка, наследование и мерж пресетов.
 * Замена лексиконов в параметрах.
 */

namespace SendIt\Config;

class PresetManager
{
    private \modX $modx;
    private string $pathToPresets;
    private string $presetKey;

    /**
     * @param \modX $modx
     * @param string $pathToPresets
     * @param string $presetKey
     */
    public function __construct(\modX $modx, string $pathToPresets, string $presetKey)
    {
        $this->modx = $modx;
        $this->pathToPresets = $pathToPresets;
        $this->presetKey = $presetKey;
    }

    /**
     * @return array
     */
    public function loadPresets(): array
    {
        $presets = [];
        if (!file_exists($this->pathToPresets)) {
            return $presets;
        }

        $files = scandir($this->pathToPresets);
        unset($files[0], $files[1]);

        foreach ($files as $file) {
            $path = $this->pathToPresets . '/' . $file;
            $presets[str_replace('.inc.php', '', $file)] = include($path);
        }

        return $presets;
    }

    /**
     * @param string $preset
     * @param array $extends
     * @param array $allPresets
     * @return array
     */
    public function resolveExtends(string $preset, array $extends, array $allPresets): array
    {
        $parts = explode('.', $preset);
        if (count($parts) < 2) {
            $parts[1] = $parts[0];
            $parts[0] = $this->presetKey;
        }

        $presetData = $allPresets[$parts[0]][$parts[1]] ?? null;
        if ($presetData && is_array($presetData)) {
            $extends = array_merge($extends, $presetData);
            if (!empty($presetData['extends'])) {
                $extends = $this->resolveExtends($presetData['extends'], $extends, $allPresets);
            }
        }

        return $extends;
    }

    /**
     * Мержит файловый пресет с сессионным и резолвит extends-цепочку.
     *
     * @param array $filePreset
     * @param array $sessionPreset
     * @param array $allPresets
     * @return array
     */
    public function resolvePreset(array $filePreset, array $sessionPreset, array $allPresets): array
    {
        $merged = array_merge($filePreset, $sessionPreset);
        if (!empty($merged['extends'])) {
            $extendsData = $this->resolveExtends($merged['extends'], [], $allPresets);
            $merged = array_merge($extendsData, $merged);
        }
        return $merged;
    }

    /**
     * @param array $config
     * @return array
     */
    public function buildParams(array $config): array
    {
        $adminID = $this->modx->getOption('si_default_admin', '', 1);
        $mgrEmail = '';
        $http_host = $this->modx->getOption('http_host', '', 'domain.com');
        $useSMTP = $this->modx->getOption('mail_use_smtp', '', false);
        $emailFrom = $useSMTP ? $this->modx->getOption('emailsender') : "noreply@{$http_host}";

        if ($profile = $this->modx->getObject(\MODX\Revolution\modUserProfile::class, ['internalKey' => $adminID])) {
            $mgrEmail = $profile->get('email');
        }

        $email = $this->modx->getOption('si_default_email') ?: $mgrEmail;
        $email = $email ?: $this->modx->getOption('ms2_email_manager');
        $emailTpl = $this->modx->getOption('si_default_emailtpl', '', 'siDefaultEmail');
        $hooks = $email ? 'FormItSaveForm,email' : 'FormItSaveForm';

        $default = [
            'successMessage' => $this->modx->lexicon('si_msg_success'),
            'hooks' => $hooks,
            'emailTpl' => $emailTpl,
            'emailTo' => $email,
            'emailFrom' => $emailFrom,
            'formName' => $this->modx->lexicon('si_default_formname'),
            'emailSubject' => $this->modx->lexicon('si_default_subject', [
                'host' => $this->modx->getOption('http_host'),
            ]),
        ];

        $preset = $config['preset'] ?? [];
        $extendsPreset = $config['extendsPreset'] ?? [];
        $pluginParams = $config['pluginParams'] ?? [];
        $formName = $config['formName'] ?? '';

        $params = array_merge($extendsPreset, $preset, $pluginParams);
        if (!isset($params['snippet']) || $params['snippet'] === 'FormIt') {
            $params = array_merge($default, $params);
        }

        $params['sendGoal'] = $params['sendGoal'] ?? $this->modx->getOption('si_send_goal', '', false);
        $params['counterId'] = $params['counterId'] ?? $this->modx->getOption('si_counter_id', '', '');
        $params['formName'] = !empty($params['formName'])
            && $params['formName'] !== $this->modx->lexicon('si_default_formname')
            ? $params['formName']
            : $formName;

        return $params;
    }

    /**
     * @param string $formName
     * @param string $presetName
     * @param object $sendIt
     * @return void
     */
    public function fireFormParamsEvent(string $formName, string $presetName, object $sendIt): void
    {
        $this->modx->invokeEvent('OnGetFormParams', [
            'formName' => $formName,
            'presetName' => $presetName,
            'SendIt' => $sendIt,
        ]);
    }

    /**
     * @param array $params
     * @return array
     */
    public function replaceLexicons(array $params): array
    {
        if (empty($params['useLexicons'])) {
            return $params;
        }

        $lexicons = explode(',', $params['useLexicons']);
        foreach ($lexicons as $lexicon) {
            $this->modx->lexicon->load($lexicon);
        }

        foreach ($params as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_contains($key, 'Message')
                || str_contains($key, 'message')
                || str_contains($key, 'vText')
            ) {
                $params[$key] = $this->modx->lexicon($value);
            }

            if ($key === 'fieldNames') {
                $values = explode(',', $value);
                $replaced = [];
                foreach ($values as $item) {
                    $fieldNames = explode('==', $item);
                    $fieldNames[0] = $this->modx->lexicon($fieldNames[0]);
                    $fieldNames[1] = $this->modx->lexicon($fieldNames[1]);
                    $replaced[] = implode('==', $fieldNames);
                }
                $params[$key] = implode(',', $replaced);
            }
        }

        return $params;
    }
}
