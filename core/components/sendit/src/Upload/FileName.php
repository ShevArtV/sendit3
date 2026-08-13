<?php

namespace SendIt\Upload;

/**
 * File-name policy: package default -> project event -> mandatory hardening.
 */
final class FileName
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'pht', 'phtm', 'phtml', 'phar',
        'shtml', 'shtm', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'msi',
        'jsp', 'jspx', 'asp', 'aspx', 'asa', 'asax', 'cer', 'htaccess', 'htpasswd', 'user', 'ini', 'conf',
    ];

    private const NAME_LIMIT = 100;
    private const PATH_LIMIT = 255;

    private \modX $modx;
    private bool $inEvent = false;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    public function sanitize(
        string $name,
        string $directory,
        string $formName,
        string $presetName,
        object $sendIt
    ): string {
        $sanitized = self::harden(self::defaultName($name));
        if ($this->inEvent) {
            return $sanitized;
        }

        $this->inEvent = true;
        try {
            [, $extension] = self::split($sanitized);
            if (is_object($this->modx->event)) {
                unset($this->modx->event->returnedValues['name']);
            }
            $this->modx->invokeEvent('siOnSanitizeFileName', [
                'name' => $name,
                'sanitized' => $sanitized,
                'extension' => $extension,
                'directory' => $directory,
                'context' => 'upload',
                'formName' => $formName,
                'presetName' => $presetName,
                'SendIt' => $sendIt,
            ]);
            $custom = $this->modx->event->returnedValues['name'] ?? '';
            unset($this->modx->event->returnedValues['name']);
        } finally {
            $this->inEvent = false;
        }

        return is_string($custom) && trim($custom) !== ''
            ? self::harden($custom)
            : $sanitized;
    }

    public static function defaultName(string $name): string
    {
        [$base, $extension] = self::split(basename(str_replace('\\', '/', $name)));
        $base = self::transliterate($base);
        $extension = self::normalizeExtension($extension);

        return $extension !== '' ? $base . '.' . $extension : $base;
    }

    public static function harden(string $name): string
    {
        $name = str_replace(["\0", '\\'], ['', '/'], $name);
        $segments = explode('/', $name);
        $filePart = (string)array_pop($segments);
        $directories = [];

        foreach ($segments as $segment) {
            $segment = self::transliterate($segment);
            if ($segment !== '') {
                $directories[] = mb_substr($segment, 0, self::NAME_LIMIT, 'UTF-8');
            }
        }

        [$base, $extension] = self::split($filePart);
        $base = self::transliterate($base);
        $extension = self::normalizeExtension($extension);
        if ($extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            $base = self::transliterate($base . '-' . $extension);
            $extension = '';
        }
        if ($base === '') {
            return '';
        }

        $base = mb_substr($base, 0, self::NAME_LIMIT, 'UTF-8');
        $filePart = $extension !== '' ? $base . '.' . $extension : $base;
        $result = implode('/', array_merge($directories, [$filePart]));

        return mb_strlen($result, 'UTF-8') <= self::PATH_LIMIT ? $result : '';
    }

    /** @return array{0:string,1:string} */
    public static function split(string $filename): array
    {
        $position = strrpos($filename, '.');
        return $position === false
            ? [$filename, '']
            : [substr($filename, 0, $position), substr($filename, $position + 1)];
    }

    private static function normalizeExtension(string $extension): string
    {
        return strtolower((string)preg_replace('/[^0-9a-zA-Z]/', '', $extension));
    }

    private static function transliterate(string $value): string
    {
        $converter = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $value = mb_strtolower($value, 'UTF-8');
        $result = '';
        for ($i = 0, $length = mb_strlen($value, 'UTF-8'); $i < $length; $i++) {
            $char = mb_substr($value, $i, 1, 'UTF-8');
            $result .= $converter[$char] ?? $char;
        }

        return trim((string)preg_replace('/[^0-9a-z]+/', '-', $result), '-');
    }
}
