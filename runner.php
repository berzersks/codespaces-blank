<?php

use plugins\router\Start\console;
use plugins\router\Start\cache;
use plugins\terminal;


include 'plugins/autoloader.php';
if (empty($argv[1]) && empty($argv[2]) && empty($argv[3])) {
    $cache = cache::global()['interface'];
    $script = sprintf("php relay.php %s %s %s",
        $cache['server']['localPortListener'],
        $cache['server']['remoteAddress'],
        $cache['server']['autoGenerateSslCertificate'] ? 'auto-secure' : 'no-auto-secure'
    );
} else $script = sprintf("php relay.php %s %s %s", $argv[1], $argv[2], $argv[3]);


$p = readline("Deseja iniciar o servidor? (s/n): ");
for ($i = 0; $i < 9999999; $i++) {
    echo shell_exec('clear');
    Co\run(fn() => terminal::asyncShell($script, (new console())));
    sleep(1);
}


