<?php

declare(strict_types=1);

namespace Core;

final class Autoloader
{
    /** @param array<string, string> $prefixes */
    public static function register(array $prefixes): void
    {
        spl_autoload_register(static function (string $class) use ($prefixes): void {
            foreach ($prefixes as $prefix => $baseDir) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = substr($class, strlen($prefix));
                $file = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

                if (is_file($file)) {
                    require $file;
                }
            }
        });
    }
}
