<?php

declare(strict_types=1);

/**
 * Проверка синтаксиса всех PHP-файлов пакета без внешних зависимостей.
 *
 * Запуск: composer lint   (или php tests/lint.php)
 */

$root = dirname(__DIR__);
$directories = [$root . '/src', $root . '/tests', $root . '/examples'];
$failed = 0;
$checked = 0;

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $output = [];
        $exitCode = 0;

        exec(
            escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1',
            $output,
            $exitCode
        );

        $checked++;

        if ($exitCode !== 0) {
            $failed++;
            fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        }
    }
}

if ($failed > 0) {
    fwrite(STDERR, sprintf('Синтаксические ошибки: %d из %d файлов.', $failed, $checked) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf('Синтаксис в порядке: %d файлов.', $checked) . PHP_EOL);
