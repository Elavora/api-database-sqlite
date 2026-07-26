<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];

foreach (['src', 'tests'] as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$invalidFiles = [];

foreach ($files as $file) {
    $output = [];
    $status = 0;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    exec($command, $output, $status);

    if ($status !== 0) {
        $invalidFiles[] = $file;
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
    }
}

if ($invalidFiles !== []) {
    fwrite(
        STDERR,
        sprintf("Lint falhou em %d arquivo(s).%s", count($invalidFiles), PHP_EOL)
    );
    exit(1);
}

fwrite(STDOUT, sprintf("Lint concluido: %d arquivo(s).%s", count($files), PHP_EOL));
