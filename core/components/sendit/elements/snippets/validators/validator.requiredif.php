<?php
/**
 * @var \modX $modx
 * @var object $validator
 * @var string $key
 * @var mixed $value
 * @var string $param
 * @var array $scriptProperties
 */

$parts = explode('|', $param);
$msg = $validator->formit->config[$key . '.vTextRequiredIf'] ?? 'Это поле обязательно для заполнения';

if (($_POST[$parts[0]] ?? null) == ($parts[1] ?? null) && !$value) {
    $validator->addError($key, $msg);
}
return true;