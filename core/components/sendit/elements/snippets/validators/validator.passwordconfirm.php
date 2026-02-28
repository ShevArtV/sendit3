<?php
/**
 * @var \modX $modx
 * @var object $validator
 * @var string $key
 * @var mixed $value
 * @var string $param
 * @var array $scriptProperties
 */

$msg = $validator->formit->config[$key . '.vTextPasswordConfirm'] ?? 'Пароли не совпадают.';
if ($_POST[$param] && $_POST[$param] !== $value) {
    $validator->addError($key, $msg);
}
return true;
