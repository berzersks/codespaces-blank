<?php

namespace plugins\router;

use plugins\router\Extension\plugins;

class pages
{

    public static function isRoute(\Swoole\Http\Request $request): bool
    {
        $endpoint = substr($request->server['request_uri'], 1);
        if (in_array($endpoint, plugins::listPages())) return true;
        $baseDir = plugins::baseDir();
        if (is_file($baseDir.'/pages/'.$endpoint)) return true;
        return false;
    }

    public static function dispatchRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        $endpoint = substr($request->server['request_uri'], 1);
        $array = explode('.', $endpoint);
        $split = end($array);
        if ($split == $endpoint) $endpoint .= '.html';

        $page = file_get_contents(plugins::baseDir().'/pages/'.$endpoint);
        $response->header('Content-Type', mime_content_type(plugins::baseDir().'/pages/'.$endpoint));
        return $response->end($page);
    }
}