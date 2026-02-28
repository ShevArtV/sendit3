<?php
/**
 * @var \modX $modx
 * @var object $validator
 * @var string $key
 * @var mixed $value
 * @var string $param
 * @var array $scriptProperties
 */

if ($value) {
    $min_length = $param ?: $modx->getOption('password_min_length', null, 8);
    if (strlen($value) < $min_length) {
        $validator->addError($key, $scriptProperties[$key . '.vTextCheckPassLength'] ?? '');
    }
}
return true;