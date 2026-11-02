<?php

// Юнит-проверка SendIt\Session\TokenTtl без MODX: modX подменён заглушкой с getOption.
class modX
{
    private $options;
    public function __construct(array $options) { $this->options = $options; }
    public function getOption($key, $options = null, $default = null) { return $this->options[$key] ?? $default; }
}

require_once __DIR__ . '/../core/components/sendit/src/Session/TokenTtl.php';

use SendIt\Session\TokenTtl;

function check(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$modx = new modX(['si_token_ttl' => 900, 'si_token_grace' => 60]);
$now = time();

$t = TokenTtl::issue($modx, []);
check(strlen($t['sitoken']) === 32 && $t['sitoken_prev'] === '', 'Пустая сессия получает новый токен.');
check(TokenTtl::isValid($modx, $t, $t['sitoken']), 'Свежий токен принимается.');
check(TokenTtl::issue($modx, $t)['sitoken'] === $t['sitoken'], 'Токен моложе TTL переиспользуется.');

$old = ['sitoken' => 'aaa', 'sitoken_at' => $now - 901];
check(!TokenTtl::isValid($modx, $old, 'aaa'), 'Токен старше TTL отклоняется.');
$n = TokenTtl::issue($modx, $old);
check($n['sitoken'] !== 'aaa' && $n['sitoken_prev'] === 'aaa' && $n['sitoken_prev_till'] === $n['sitoken_at'] + 60, 'Просроченный заменяется, прежний запоминается на GRACE.');
check(TokenTtl::isValid($modx, $n, 'aaa'), 'Предыдущий принимается в пределах GRACE.');
check(TokenTtl::isValid($modx, $n, $n['sitoken']), 'Новый принимается.');
check(!TokenTtl::isValid($modx, array_merge($n, ['sitoken_prev_till' => $now - 1]), 'aaa'), 'Предыдущий отклоняется после GRACE.');
check(!TokenTtl::isValid($modx, $n, 'deadbeef'), 'Мусорный токен отклоняется.');
check(!TokenTtl::isValid($modx, $n, ''), 'Пустой токен отклоняется.');
check(!TokenTtl::isValid($modx, ['sitoken_prev' => '', 'sitoken_prev_till' => $now + 60], ''), 'Пустой prev не совпадает с пустым токеном.');

$short = new modX(['si_token_ttl' => 60, 'si_token_grace' => 0]);
$mid = ['sitoken' => 'bbb', 'sitoken_at' => $now - 61];
check(!TokenTtl::isValid($short, $mid, 'bbb'), 'TTL берётся из настройки si_token_ttl.');
check(TokenTtl::isValid($modx, $mid, 'bbb'), 'С дефолтным TTL тот же токен действителен.');
check(TokenTtl::issue($short, $mid)['sitoken_prev_till'] - $now >= 60, 'GRACE <= 0 заменяется дефолтом 60.');
check(!TokenTtl::isValid(new modX(['si_token_ttl' => 0]), ['sitoken' => 'c', 'sitoken_at' => $now - 901], 'c'), 'TTL <= 0 заменяется дефолтом 900.');

echo "tokenttl: ok\n";
