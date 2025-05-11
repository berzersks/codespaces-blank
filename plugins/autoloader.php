<?php


declare(strict_types=1);

namespace Co;

use DirectoryIterator;
use Swoole\Coroutine;

$interface = json_decode(file_get_contents(__DIR__ . '/configInterface.json'), true);
$paths = $interface['autoload'];
$allowObservable = $interface['reloadCaseFileModify'];
$nameFiles = [];
$cachePages = [];
foreach ($paths as $path) {
    $directory = new DirectoryIterator(__DIR__ . "/{$path}");
    foreach ($directory as $fileInfo) {
        $nameFile = $fileInfo->getFilename();
        if (strlen($nameFile) > 2) {
            $nameFiles[] = (strlen($path) > 1) ? __DIR__ . '/' . $path . "/" . $nameFile : $nameFile;
        }
    }
}
foreach ($nameFiles as $key => $file) require $file;