<?php
/**
 * @var \modX $modx
 * @var object $validator
 * @var string $key
 * @var mixed $value
 * @var string $param
 * @var array $scriptProperties
 */

$param = explode('|', $param);
$msg = $validator->formit->config[$key . '.vTextRequiredIf'] ?? 'Это поле обязательно для заполнения';

if ($_POST[$param[0]] == $param[1] && !$value) {
    $validator->addError($key, $msg);
}
return true;