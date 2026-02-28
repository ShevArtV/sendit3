<?php
/**
 * Парсинг и настройка правил валидации.
 * Санитизация POST-данных, управление кастомными валидаторами.
 * Подготовка файлов для email.
 */

namespace SendIt\Form;

use SendIt\Security\Sanitizer;

class ValidationManager
{
    private \modX $modx;

    /** @var string[] */
    private array $defaultValidators = [
        'blank', 'required', 'password_confirm', 'email',
        'minLength', 'maxLength', 'minValue', 'maxValue',
        'contains', 'strip', 'stripTags', 'allowTags',
        'isNumber', 'allowSpecialChars', 'isDate', 'regexp', 'checkbox',
    ];

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @param string $validate
     * @return array<string, array<int, string>>
     */
    public function parseValidation(string $validate = ''): array
    {
        $output = [];
        if (!$validate) {
            return $output;
        }

        $validate = str_replace(["\r", "\n", ', '], ['', '', ','], $validate);
        $validates = explode(',', $validate);

        foreach ($validates as $v) {
            $v = trim($v);
            $items = explode(':', $v);
            $key = $items[0];
            unset($items[0]);
            $output[$key] = $items;
        }

        return $output;
    }

    /**
     * @param array<string, array<int, string>> $validates
     * @return string
     */
    public function serializeValidation(array $validates): string
    {
        $output = [];
        foreach ($validates as $fieldName => $validators) {
            $output[] = $fieldName . ':' . implode(':', $validators);
        }

        return implode(',', $output);
    }

    /**
     * @param array $validates
     * @param array $params
     * @return void
     */
    public function prepareValidation(array &$validates, array &$params): void
    {
        $allValidators = [];

        foreach ($validates as $fieldName => &$validators) {
            $allValidators = array_merge($allValidators, $validators);

            if (!isset($_POST[$fieldName]) && !in_array('checkbox', $validators)) {
                unset($validates[$fieldName]);
            }

            $k = array_search('checkbox', $validators);
            if ($k !== false) {
                unset($validators[$k]);
                if (!isset($_POST[$fieldName])) {
                    $_POST[$fieldName] = '';
                }
            }
        }
        unset($validators);

        $custom = $this->detectCustomValidators(array_unique($allValidators));
        if ($custom) {
            $params['customValidators'] = $custom;
        }
    }

    /**
     * @param array $validates
     * @param object $sendIt
     * @return void
     */
    public function sanitizePost(array &$validates, object $sendIt): void
    {
        foreach ($_POST as $k => $v) {
            $this->processValue($v, $k, $validates, $sendIt);
            if (is_array($v)) {
                $_POST[$k] = json_encode($v);
            }
        }

        $_POST['fields'] = json_encode($_POST);
    }

    /**
     * @param array $params
     * @return void
     */
    public function setFieldsAliases(array $params): void
    {
        if (empty($params['fieldNames'])) {
            return;
        }

        $fields = explode(',', $params['fieldNames']);
        $result = [];
        foreach ($fields as $field) {
            $f = explode('==', trim($field));
            $result[$f[0]] = $f[1];
        }

        $_POST['fieldsAliases'] = json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array $params
     * @param array $session
     * @param string $uploaddir
     * @return void
     */
    public function attachFiles(array $params, array $session, string $uploaddir): void
    {
        if (empty($params['attachFilesToEmail']) || empty($params['allowFiles'])) {
            return;
        }

        $fileList = $_POST[$params['allowFiles']] ?? '';
        $fieldKey = $params['attachFilesToEmail'];

        $fileList = is_string($fileList) ? explode(',', $fileList) : (array)$fileList;
        if (empty($fileList)) {
            return;
        }
        $_FILES[$fieldKey] = [
            'name' => [],
            'type' => [],
            'tmp_name' => [],
            'error' => [],
            'size' => [],
        ];

        foreach ($fileList as $path) {
            $fullpath = $uploaddir . ($session['session_id'] ?? '') . '/' . $path;
            $_FILES[$fieldKey]['name'][] = basename($path);
            $_FILES[$fieldKey]['type'][] = filetype($fullpath);
            $_FILES[$fieldKey]['tmp_name'][] = $fullpath;
            $_FILES[$fieldKey]['error'][] = 0;
            $_FILES[$fieldKey]['size'][] = filesize($fullpath);
        }
    }

    /**
     * @param string[] $allValidators
     * @return string
     */
    private function detectCustomValidators(array $allValidators): string
    {
        $custom = [];
        foreach ($allValidators as $validator) {
            $items = explode('=', $validator);
            if (!in_array($items[0], $this->defaultValidators)) {
                $custom[] = $items[0];
            }
        }

        return implode(',', array_unique($custom));
    }

    /**
     * @param mixed $value
     * @param string $key
     * @param array $validates
     * @param object $sendIt
     * @return void
     */
    private function processValue(mixed $value, string $key, array &$validates, object $sendIt): void
    {
        if ($key === 'fields') {
            return;
        }

        if (!is_array($value)) {
            $sendIt->newValue = Sanitizer::process($value);

            $this->modx->invokeEvent('senditOnSetValue', [
                'key' => $key,
                'value' => $value,
                'SendIt' => $sendIt,
            ]);

            $_POST[$key] = $sendIt->newValue;
            $k = preg_replace('/\[\d*?\]/', '[*]', $key);
            if (!empty($validates[$k]) && !isset($validates[$key])) {
                $validates[$key] = $validates[$k];
            }
        } else {
            $_POST[$key . '[]'] = implode(', ', $value);
            foreach ($value as $k => $v) {
                $this->processValue($v, $key . '[' . $k . ']', $validates, $sendIt);
            }
        }
    }
}
