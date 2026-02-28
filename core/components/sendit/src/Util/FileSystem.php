<?php
/**
 * Утилита для работы с файловой системой.
 * Рекурсивное удаление директорий с проверкой allowDirs.
 */

namespace SendIt\Util;

class FileSystem
{
    /**
     * @param string $dir
     * @param \modX $modx
     * @return void
     */
    public static function removeDir(string $dir, \modX $modx): void
    {
        $allowDirs = $modx->getOption('si_allow_dirs', '', 'uploaded_files');
        $allowDirs = explode(',', $allowDirs);
        $dirParts = explode('/', $dir);

        if (empty(array_intersect($allowDirs, $dirParts))) {
            return;
        }

        if (!str_ends_with($dir, '/')) {
            $dir .= '/';
        }

        if (!is_dir($dir)) {
            return;
        }

        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }

            $path = $dir . $object;
            if (is_dir($path) && !is_link($path)) {
                self::removeDir($path, $modx);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }

        if (file_exists($dir) && is_dir($dir)) {
            rmdir($dir);
        }
    }
}
