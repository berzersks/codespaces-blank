<?php
declare(strict_types=1);

namespace plugins;

use Swoole\Coroutine;
use Swoole\Timer;

class terminal
{
    public static function pKill(mixed $pid, mixed $sig_num = 9): bool
    {
        $idProcess = (int)$pid + 1;
        if (function_exists("posix_kill")) return posix_kill($idProcess, $sig_num);
        exec("/usr/bin/kill -s $sig_num $idProcess 2>&1", $junk, $return_code);
        return !$return_code;
    }

    public static function asyncShell($command, $cli): void
    {
        $descriptorSpec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        $process = proc_open($command, $descriptorSpec, $pipes);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        if (is_resource($process)) {
            print $cli->color("Processo iniciado com sucesso\n", 'green');
            Timer::tick(50, function ($timerId) use (&$pipes, &$process, &$command, $cli) {
                $outputPipes = [$pipes[1], $pipes[2]];
                $readyPipes = $outputPipes;
                $null = null;


                if (!is_resource($pipes[1]) || !is_resource($pipes[2])) {
                    if (is_resource($pipes[1])) fclose($pipes[1]);
                    if (is_resource($pipes[2])) fclose($pipes[2]);
                    if (is_resource($pipes[0])) fclose($pipes[0]);
                    if (is_resource($process)) proc_close($process);
                    if (is_resource($process)) proc_terminate($process, 15);
                    self::pKill(proc_get_status($process)['pid'], 9);
                    return Timer::clear($timerId);
                }
                if (is_resource($process) && proc_get_status($process)['running'] === false) {
                    if (is_resource($pipes[0])) fclose($pipes[0]);
                    self::pKill(proc_get_status($process)['pid'], 9);
                    return Timer::clear($timerId);
                }
                stream_select($readyPipes, $null, $null, 0);
                foreach ($readyPipes as $pipe) {
                    $data = fgets($pipe);
                    if ($data === false) {
                        $outputPipes = array_diff($outputPipes, [$pipe]);
                    } elseif (strlen($data) > 1) {
                        print $cli->color($data, 'yellow');
                    }
                }
            });
        }
    }
}



