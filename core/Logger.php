<?php

declare(strict_types=1);

namespace Core;

use Throwable;

final readonly class Logger
{
    public function __construct(private string $file)
    {
        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
    }

    public function error(Throwable $e): void
    {
        $line = sprintf("[%s] %s in %s:%d\n%s\n", date(DATE_ATOM), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
