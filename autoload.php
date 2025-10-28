<?php

declare(strict_types=1);

/**
 * Простой автозагрузчик классов с поддержкой *.class.php
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    $length = strlen($prefix);
    if (strncmp($prefix, $class, $length) !== 0) {
        return;
    }

    $relativeClass = substr($class, $length);
    $path = str_replace('\\', '/', $relativeClass);

    $candidates = [
        $baseDir . $path . '.php',
        $baseDir . $path . '.class.php',
    ];

    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require $file;
            break;
        }
    }
});
