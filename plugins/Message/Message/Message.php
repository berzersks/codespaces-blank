<?php

namespace plugins\router;

use Swoole\Coroutine\Http\Client;
use Swoole\WebSocket\Server as WebSocketServer;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
class Message
{
    private static $connections = [];

    public static function onMessage(WebSocketServer $server, Frame $frame): void
    {
        $fd = $frame->fd;
        $data = $frame->data;

        if (isset(self::$connections[$fd])) {
            $client = self::$connections[$fd];

            go(function () use ($client, $data) {
                $client->push($data);
            });
        } else {
            var_dump("Conexão não encontrada para fd: $fd");
        }
    }

    public static function onOpen(WebSocketServer $server, Request $request): void
    {
        $link = $request->server['request_uri'] . (!empty($request->server['query_string']) ? '?' . $request->server['query_string'] : '');
        $host = $request->header['host'];
        $serverEndPoints = $GLOBALS['interface']['server']['endPoints'];
        if (array_key_exists($host, $serverEndPoints)) {
            $link = $serverEndPoints[$host] . $link;
        }
        $fd = $request->fd;
        $parse = parse_url($link);
        $parse['port'] = $parse['port'] ?? ($parse['scheme'] == 'https' ? 443 : 80);
        $client = new Client($parse['host'], $parse['port'], $parse['scheme'] == 'https');
        $link = $request->server['request_uri'] . (!empty($request->server['query_string']) ? '?' . $request->server['query_string'] : '');
        go(function () use ($server, $client, $link, $fd) {
            $client->upgrade($link);
            self::$connections[$fd] = $client;
            while (true) {
                $frame = $client->recv();
                if ($frame === false) {
                    unset(self::$connections[$fd]);
                    break;
                }
                $server->push($fd, $frame->data);
            }

            $client->close();
            echo "Conexão remota fechada para fd: $fd\n";
        });
    }

    public static function onClose(WebSocketServer $server, int $fd): void
    {
        if (isset(self::$connections[$fd])) {
            self::$connections[$fd]->close();
            unset(self::$connections[$fd]);
        }
        echo "Conexão WebSocket fechada: {$fd}\n";
    }
}