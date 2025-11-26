<?php
// public/modules/wpp_chat/includes/wpp-client.php
// Cliente WPPConnect baseado em teste_wpp.php

// Pode ajustar se quiser
if (!defined('WPP_DEFAULT_TIMEOUT_S')) {
    define('WPP_DEFAULT_TIMEOUT_S', 20);
}

// ========= FUNÇÕES BÁSICAS DE HTTP / HELPER ========= //

if (!function_exists('wpp_parse_headers_raw')) {
    function wpp_parse_headers_raw(string $raw): array {
        $lines = preg_split("/\r\n|\n|\r/", trim($raw));
        $out = ['_status' => $lines[0] ?? ''];
        foreach ($lines as $i => $ln) {
            if ($i === 0 || $ln === '') continue;
            $p = strpos($ln, ':');
            if ($p !== false) {
                $k = strtolower(trim(substr($ln, 0, $p)));
                $v = trim(substr($ln, $p + 1));
                if (isset($out[$k])) {
                    if (is_array($out[$k])) $out[$k][] = $v;
                    else $out[$k] = [$out[$k], $v];
                } else {
                    $out[$k] = $v;
                }
            }
        }
        return $out;
    }
}

if (!function_exists('wpp_looks_like_base64')) {
    function wpp_looks_like_base64(string $s): bool {
        $s2 = preg_replace('/\s+/', '', $s);
        if ($s2 === '') return false;
        if (strlen($s2) % 4 !== 0) return false;
        return (bool)preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $s2);
    }
}

if (!function_exists('wpp_http_request')) {
    function wpp_http_request(string $method, string $url, array $headers = [], $body = null, int $timeout = null): array {
        $timeout = $timeout ?? WPP_DEFAULT_TIMEOUT_S;

        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = is_int($k) ? $v : ($k . ': ' . $v);
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST   => strtoupper($method),
            CURLOPT_HTTPHEADER      => $h,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_CONNECTTIMEOUT  => $timeout,
            CURLOPT_TIMEOUT         => $timeout,
            CURLOPT_HEADER          => true,
            CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,
        ]);

        if ($body !== null) {
            if (is_array($body)) {
                $body = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return [
                'ok'         => false,
                'code'       => 0,
                'headers'    => [],
                'headers_raw'=> '',
                'body_raw'   => null,
                'json'       => null,
                'error'      => "cURL: $err"
            ];
        }

        $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $rawHeaders  = substr($raw, 0, $headerSize);
        $respBody    = substr($raw, $headerSize);
        curl_close($ch);

        $headersParsed = wpp_parse_headers_raw($rawHeaders);

        $json = null;
        if (strlen($respBody)) {
            $tmp = json_decode($respBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $tmp;
            }
        }

        return [
            'ok'          => ($statusCode >= 200 && $statusCode < 300),
            'code'        => $statusCode,
            'headers'     => $headersParsed,
            'headers_raw' => $rawHeaders,
            'body_raw'    => $respBody,
            'json'        => $json,
        ];
    }
}

// ========= CONFIG A PARTIR DA INSTÂNCIA ========= //

if (!function_exists('wpp_build_base_url')) {
    function wpp_build_base_url(array $cfg): string {
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (int)($cfg['port'] ?? 21465);
        return 'http://' . $host . ':' . $port . '/api';
    }
}

if (!function_exists('wpp_get_session_name')) {
    function wpp_get_session_name(array $cfg): string {
        return $cfg['session_name'] ?? 'superabc';
    }
}

if (!function_exists('wpp_get_secret_key')) {
    function wpp_get_secret_key(array $cfg): string {
        // prioridade: campo da tabela; depois ENV; depois default
        if (!empty($cfg['secret'])) {
            return (string)$cfg['secret'];
        }
        $env = getenv('WPP_SECRET');
        if ($env !== false && $env !== '') {
            return $env;
        }
        return 'THISISMYSECURETOKEN';
    }
}

// token em cache por processo
if (!function_exists('wpp_get_auth_token')) {
    function wpp_get_auth_token(array $cfg): ?string {
        static $cache = [];

        $session = wpp_get_session_name($cfg);
        $host    = $cfg['host'] ?? '127.0.0.1';
        $port    = (int)($cfg['port'] ?? 21465);
        $secret  = wpp_get_secret_key($cfg);

        $key = md5($host . ':' . $port . '/' . $session . '/' . $secret);

        // se quiser expirar, pode colocar timestamp aqui
        if (isset($cache[$key]) && !empty($cache[$key]['token'])) {
            return $cache[$key]['token'];
        }

        $BASE   = wpp_build_base_url($cfg);
        $genUrl = "{$BASE}/" . rawurlencode($session) . '/' . rawurlencode($secret) . '/generate-token';

        $tok = wpp_http_request('POST', $genUrl, ['Content-Type' => 'application/json'], '{}');

        $token = $tok['json']['token'] ?? null;
        if (!$token) {
            // sem token, não guarda cache
            return null;
        }

        $cache[$key] = [
            'token' => $token,
            'ts'    => time()
        ];
        return $token;
    }
}

// ========= FUNÇÕES PRINCIPAIS USADAS PELO MÓDULO ========= //

/**
 * Inicia a sessão WPPConnect (start-session)
 * Retorna o array de resposta completo (ok, code, json, etc.)
 */
if (!function_exists('wpp_session_start_call')) {
    function wpp_session_start_call(array $cfg): array {
        $BASE    = wpp_build_base_url($cfg);
        $session = wpp_get_session_name($cfg);
        $token   = wpp_get_auth_token($cfg);

        if (!$token) {
            return [
                'ok'    => false,
                'code'  => 0,
                'json'  => null,
                'error' => 'Token não obtido em generate-token'
            ];
        }

        $url = "{$BASE}/" . rawurlencode($session) . '/start-session';

        $res = wpp_http_request('POST', $url, [
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json'
        ], '{}');

        return $res;
    }
}

/**
 * Busca status da sessão (check-connection-session)
 */
if (!function_exists('wpp_get_status')) {
    function wpp_get_status(array $cfg): array {
        $BASE    = wpp_build_base_url($cfg);
        $session = wpp_get_session_name($cfg);
        $token   = wpp_get_auth_token($cfg);

        if (!$token) {
            return [
                'ok'    => false,
                'code'  => 0,
                'json'  => null,
                'error' => 'Token não obtido em generate-token'
            ];
        }

        $url = "{$BASE}/" . rawurlencode($session) . '/check-connection-session';

        $res = wpp_http_request('GET', $url, [
            'Authorization' => "Bearer {$token}"
        ]);

        return $res;
    }
}

/**
 * Busca QR Code da sessão (qrcode-session)
 */
if (!function_exists('wpp_get_qr')) {
    function wpp_get_qr(array $cfg): array {
        $BASE    = wpp_build_base_url($cfg);
        $session = wpp_get_session_name($cfg);
        $token   = wpp_get_auth_token($cfg);

        if (!$token) {
            return [
                'ok'    => false,
                'code'  => 0,
                'json'  => null,
                'error' => 'Token não obtido em generate-token'
            ];
        }

        $url = "{$BASE}/" . rawurlencode($session) . '/qrcode-session';

        $res = wpp_http_request('GET', $url, [
            'Authorization' => "Bearer {$token}"
        ]);

        // Aqui vamos tentar normalizar para sempre ter algum campo json['qrcode'] ou ['base64']
        // semelhante ao teste_wpp.php
        $json = $res['json'];

        // se não veio json mas o body é PNG ou base64, converte
        if (!$json) {
            $ct = strtolower($res['headers']['content-type'] ?? '');
            $body = $res['body_raw'] ?? '';

            if (strpos($ct, 'image/png') !== false && $body !== '') {
                $b64 = base64_encode($body);
                $json = ['base64' => $b64];
            } else {
                $bodyTrim = trim($body);
                if ($bodyTrim !== '' && wpp_looks_like_base64($bodyTrim)) {
                    $json = ['base64' => $bodyTrim];
                }
            }

            $res['json'] = $json;
        }

        return $res;
    }
}
