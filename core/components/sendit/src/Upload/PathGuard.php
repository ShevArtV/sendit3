<?php

namespace SendIt\Upload;

final class PathGuard
{
    public static function sessionDirectory(string $uploadDirectory, array $session): string
    {
        $sessionId = (string)($session['session_id'] ?? '');
        if ($sessionId === '' || $sessionId === '.' || $sessionId === '..'
            || $sessionId !== basename(str_replace('\\', '/', $sessionId))) {
            return '';
        }

        $root = rtrim($uploadDirectory, '/\\');
        $directory = $root . '/' . $sessionId;
        if (file_exists($directory) || is_link($directory)) {
            $realRoot = realpath($root);
            $realDirectory = realpath($directory);
            if ($realRoot === false || $realDirectory === false
                || !str_starts_with(rtrim($realDirectory, '/') . '/', rtrim($realRoot, '/') . '/')) {
                return '';
            }
        }

        return $directory . '/';
    }

    public static function resolve(string $base, string $relative): string
    {
        if ($relative === '' || strpos($relative, "\0") !== false) {
            return '';
        }
        $relative = str_replace('\\', '/', $relative);
        if (str_starts_with($relative, '/') || preg_match('/^[a-z]:/i', $relative)) {
            return '';
        }

        $baseCanonical = self::canonicalize($base);
        $target = self::canonicalize($baseCanonical . '/' . $relative);
        if ($baseCanonical === '' || $target === '' || !str_starts_with($target, $baseCanonical . '/')) {
            return '';
        }

        $realBase = realpath($baseCanonical);
        if ($realBase !== false) {
            $existing = $target;
            while (!file_exists($existing) && !is_link($existing)) {
                $parent = dirname($existing);
                if ($parent === $existing) {
                    return '';
                }
                $existing = $parent;
            }
            $realExisting = realpath($existing);
            if ($realExisting === false
                || !str_starts_with(rtrim($realExisting, '/') . '/', rtrim($realBase, '/') . '/')) {
                return '';
            }
        }

        return $target;
    }

    public static function relativeFromRequest(string $path, string $sessionDirectory, string $basePath): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || strpos($path, "\0") !== false) {
            return '';
        }

        $absolute = str_starts_with($path, $basePath)
            ? $path
            : rtrim($basePath, '/') . '/' . ltrim($path, '/');
        $absolute = self::canonicalize($absolute);
        $base = self::canonicalize($sessionDirectory);

        return $base !== '' && str_starts_with($absolute, $base . '/')
            ? substr($absolute, strlen($base) + 1)
            : '';
    }

    private static function canonicalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/');
        $result = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($result);
                continue;
            }
            $result[] = $part;
        }

        return ($absolute ? '/' : '') . implode('/', $result);
    }
}
