<?php
/**
 * Генерация паролей, хешей, числовых кодов.
 * URL-safe Base64 кодирование/декодирование.
 */

namespace SendIt\Auth;

class CodeGenerator
{
    /**
     * @param \modX $modx
     * @param string $type
     * @param int $length
     * @return string
     */
    public static function generate(\modX $modx, string $type = 'pass', int $length = 0): string
    {
        if (!$length) {
            $length = (int)$modx->getOption('password_min_length');
        }

        $chars = match ($type) {
            'pass' => array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'), ['#', '!', '?']),
            'hash' => array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9')),
            'code' => range('0', '9'),
            default => range('a', 'z'),
        };

        $result = '';
        $max = count($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, $max)];
        }

        return $result;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function base64urlEncode(string $str): string
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    /**
     * @param string $str
     * @return string
     */
    public static function base64urlDecode(string $str): string
    {
        return base64_decode(str_pad(strtr($str, '-_', '+/'), strlen($str) % 4, '=', STR_PAD_RIGHT));
    }
}
