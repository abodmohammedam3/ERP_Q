<?php

namespace App\Services\Backup;

/**
 * كشف mysqldump.exe / mysql.exe تلقائياً
 * -----------------------------------------------------
 * يدعم: XAMPP, Laragon, WAMP, MySQL Standalone
 * مع Cache لمدة ساعة.
 *
 * الترتيب:
 *   1) config (من .env)
 *   2) Cache
 *   3) بحث تلقائي
 */
class BackupBinaryDetector
{
    private const CACHE_KEY_MYSQLDUMP = 'backup.mysqldump_path';
    private const CACHE_KEY_MYSQL     = 'backup.mysql_path';
    private const CACHE_TTL           = 3600;

    public function detectMysqldump(): ?string
    {
        return $this->detect('mysqldump', self::CACHE_KEY_MYSQLDUMP);
    }

    public function detectMysql(): ?string
    {
        return $this->detect('mysql', self::CACHE_KEY_MYSQL);
    }

    private function detect(string $binary, string $cacheKey): ?string
    {
        $envPath = config("backup.{$binary}_path");

        if ($this->isValidBinary($envPath)) {
            return $envPath;
        }

        $cached = cache()->get($cacheKey);

        if ($cached && $this->isValidBinary($cached)) {
            return $cached;
        }

        $found = $this->findInKnownLocations($binary);

        if ($found) {
            cache()->put($cacheKey, $found, self::CACHE_TTL);
        }

        return $found;
    }

    private function findInKnownLocations(string $binary): ?string
    {
        $paths = $this->getSearchPaths();

        foreach ($paths as $dir) {

            if ($dir === '' || !is_dir($dir)) {
                continue;
            }

            $names = PHP_OS_FAMILY === 'Windows'
                ? ["{$binary}.exe", $binary]
                : [$binary];

            foreach ($names as $name) {

                $full = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;

                if ($this->isValidBinary($full)) {
                    return $full;
                }
            }
        }

        return null;
    }

    private function getSearchPaths(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {

            $paths = [
                'C:\\xampp\\mysql\\bin',
                'D:\\xampp\\mysql\\bin',
                'C:\\laragon\\bin\\mysql',
                'C:\\wamp64\\bin\\mysql',
                'C:\\wamp\\bin\\mysql',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin',
                'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin',
            ];

            foreach (['C:\\laragon\\bin\\mysql'] as $base) {

                if (!is_dir($base)) {
                    continue;
                }

                $subDirs = glob($base . DIRECTORY_SEPARATOR . 'mysql-*', GLOB_ONLYDIR) ?: [];

                foreach ($subDirs as $sub) {
                    $paths[] = $sub . DIRECTORY_SEPARATOR . 'bin';
                }
            }

            return $paths;
        }

        return [
            '/usr/bin',
            '/usr/local/bin',
            '/usr/local/mysql/bin',
            '/opt/homebrew/bin',
        ];
    }

    private function isValidBinary(?string $path): bool
    {
        if (!$path || !is_file($path)) {
            return false;
        }

        if (PHP_OS_FAMILY !== 'Windows' && !is_executable($path)) {
            return false;
        }

        return true;
    }

    public function clearCache(): void
    {
        cache()->forget(self::CACHE_KEY_MYSQLDUMP);
        cache()->forget(self::CACHE_KEY_MYSQL);
    }
}
