<?php

namespace plugins\router\Start;
class cache
{
    public static function global(): ?array
    {
        return $GLOBALS;
    }

    public static function getEndPoints(): ?array
    {
        $ed = self::global()["interface"]["server"]["endPoints"];
        $new = [];
        foreach ($ed as $k => $v) {
            $host = $k;
            if (str_contains($host, '.')) $host = explode('.', $host)[0];
            $currentDomain = self::global()['interface']['server']['currentDomain'];
            $currentDomain = parse_url($currentDomain, PHP_URL_HOST);
            $host = $host.'.'.$currentDomain;
            $new[$host] = $v;
        }
        return $new;
    }

}