<?php
/**
 * @var \modX $modx
 * @var object $validator
 * @var string $key
 * @var mixed $value
 * @var string $param
 * @var array $scriptProperties
 */

$param = $param ?: 'email';
$prefixes = [
    'username' => 'modUser.',
    'email' => 'Profile.',
    'phone' => 'Profile.',
    'mobilephone' => 'Profile.',
];
$keyName = $prefixes[$param] . $param;
$q = $modx->newQuery(\MODX\Revolution\modUser::class);
$q->leftJoin(\MODX\Revolution\modUserProfile::class, 'Profile');
$q->select($modx->getSelectColumns(\MODX\Revolution\modUser::class, 'modUser', '', ['id']));
$q->where([$keyName => $value]);
$userId = [];
if ($q->prepare() && $q->stmt->execute()) {
    $userId = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
}

if (empty($userId)) {
    $msg = $scriptProperties[$key . '.vTextUserNotExists'] ?? 'Пользователь с такими данными не найден.';
    $validator->addError($key, $msg);
}
return true;
