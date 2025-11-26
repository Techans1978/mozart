<?php
// public/modules/wpp_chat/instancia-qr.php
// Tela rica de debug baseada em teste_wpp.php, usando config da moz_wpp_instance

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once __DIR__ . '/includes/helpers.php';
// require_once __DIR__ . '/includes/guard.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
proteger_pagina();

$dbc = $conn ?? null;
if (!$dbc instanceof mysqli) {
    die('Sem conexão com o banco.');
}

if (!function_exists('wpp_get_instance_config')) {
    die('helpers.php sem função wpp_get_instance_config.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Instância inválida.');
}

$instancia = wpp_get_instance_config($dbc, $id);
if (!$instancia) {
    die('Instância não encontrada ou inativa.');
}

/** CONFIG A PARTIR DA INSTÂNCIA **/
$WPP_HOST  = $instancia['host'] ?? '127.0.0.1';
$WPP_PORT  = (int)($instancia['port'] ?? 21465);
$SESSION   = $instancia['session_name'] ?? 'superabc';
$TIMEOUT_S = 20;

/**
 * SECRET:
 * - Tenta ler, nesta ordem:
 *   1) ENV SECRET_KEY
 *   2) ENV SECRETKEY
 *   3) ENV WPP_SECRET
 *   4) moz_wpp_instance.secret (banco) / default
 */
$SECRET_FROM_ENV = false;
$SECRET_SOURCE   = '';

$tmp = getenv('SECRET_KEY');
if ($tmp !== false && $tmp !== '') {
    $SECRET_FROM_ENV = $tmp;
    $SECRET_SOURCE   = 'ENV SECRET_KEY';
} else {
    $tmp = getenv('SECRETKEY');
    if ($tmp !== false && $tmp !== '') {
        $SECRET_FROM_ENV = $tmp;
        $SECRET_SOURCE   = 'ENV SECRETKEY';
    } else {
        $tmp = getenv('WPP_SECRET');
        if ($tmp !== false && $tmp !== '') {
            $SECRET_FROM_ENV = $tmp;
            $SECRET_SOURCE   = 'ENV WPP_SECRET';
        }
    }
}

$SECRET_DEFAULT = $instancia['secret'] ?: 'THISISMYSECURETOKEN';

if ($SECRET_FROM_ENV !== false && $SECRET_FROM_ENV !== '') {
    $SECRET_KEY = $SECRET_FROM_ENV;
} else {
    $SECRET_KEY  = $SECRET_DEFAULT;
    $SECRET_SOURCE = 'moz_wpp_instance.secret / default';
}

/* ===== utils ===== */
if (!function_exists('parse_headers_raw')) {
    function parse_headers_raw(string $raw): array {
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
if (!function_exists('looks_like_base64')) {
    function looks_like_base64(string $s): bool {
        $s2 = preg_replace('/\s+/', '', $s);
        if ($s2 === '') return false;
        if (strlen($s2) % 4 !== 0) return false;
        return (bool)preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $s2);
    }
}
if (!function_exists('http_request_wpp_local')) {
    function http_request_wpp_local(string $method, string $url, array $headers = [], $body = null, int $timeout = 20): array {
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
                'ok'          => false,
                'code'        => 0,
                'headers'     => [],
                'headers_raw' => '',
                'body_raw'    => null,
                'json'        => null,
                'error'       => "cURL: $err"
            ];
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $rawHeaders = substr($raw, 0, $headerSize);
        $respBody   = substr($raw, $headerSize);
        curl_close($ch);

        $headersParsed = parse_headers_raw($rawHeaders);

        $json = null;
        if (strlen($respBody)) {
            $tmp = json_decode($respBody, true);
            if (json_last_error() === JSON_ERROR_NONE) $json = $tmp;
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
if (!function_exists('normalize_phone_br')) {
    function normalize_phone_br($p) { return preg_replace('/\D+/', '', $p ?? ''); }
}

/* ==== endpoints base ==== */
$BASE   = 'http://' . $WPP_HOST . ':' . $WPP_PORT . '/api';
// healthz é o endpoint “oficial”; se quiser manter /health, volte aqui:
$HEALTH = 'http://' . $WPP_HOST . ':' . $WPP_PORT . '/healthz';

/* 0) health */
$health = http_request_wpp_local('GET', $HEALTH, [], null, $TIMEOUT_S);

/* 1) token */
// variáveis de debug da requisição do token
$genTokenMethod  = 'POST';
$genTokenUrl     = "{$BASE}/" . rawurlencode($SESSION) . '/' . rawurlencode($SECRET_KEY) . '/generate-token';
$genTokenHeaders = ['Content-Type' => 'application/json'];
$genTokenBody    = '{}';

$tok   = http_request_wpp_local($genTokenMethod, $genTokenUrl, $genTokenHeaders, $genTokenBody, $TIMEOUT_S);
$token = $tok['json']['token'] ?? null;

/* 2) start */
$start = null;
if ($token) {
    $startUrl = "{$BASE}/" . rawurlencode($SESSION) . '/start-session';
    $start = http_request_wpp_local('POST', $startUrl, [
        'Authorization' => "Bearer {$token}",
        'Content-Type'  => 'application/json'
    ], '{}', $TIMEOUT_S);
}

/* 3) status + QR */
$status = null; $qr = null; $qrDataUrl = null; $qr_note = '';
if ($token) {
    $statusUrl = "{$BASE}/" . rawurlencode($SESSION) . '/check-connection-session';
    $status = http_request_wpp_local('GET', $statusUrl, ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);

    if (!($status['json']['connected'] ?? false)) {
        $qrUrl = "{$BASE}/" . rawurlencode($SESSION) . '/qrcode-session';
        $qr = http_request_wpp_local('GET', $qrUrl, ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);

        $qrcodeRaw = $qr['json']['qrcode'] ?? $qr['json']['base64'] ?? null;

        if ($qrcodeRaw) {
            if (strpos($qrcodeRaw, 'data:image') === 0) {
                $qrDataUrl = $qrcodeRaw;
            } elseif (looks_like_base64($qrcodeRaw)) {
                $qrDataUrl = 'data:image/png;base64,' . $qrcodeRaw;
            } else {
                $qrDataUrl = 'data:image/png;base64,' . base64_encode($qrcodeRaw);
                $qr_note   = 'QR veio em JSON mas não reconhecido como base64; foi convertido.';
            }
        } else {
            $ct = strtolower($qr['headers']['content-type'] ?? '');
            if (strpos($ct, 'image/png') !== false) {
                $qrDataUrl = 'data:image/png;base64,' . base64_encode($qr['body_raw'] ?? '');
                $qr_note   = 'QR retornado como PNG binário; convertido para base64.';
            } else {
                $body = trim($qr['body_raw'] ?? '');
                if ($body !== '') {
                    if (looks_like_base64($body)) {
                        $qrDataUrl = 'data:image/png;base64,' . $body;
                        $qr_note   = 'QR retornado como base64 puro no corpo.';
                    } else {
                        $qrDataUrl = null;
                    }
                }
            }
        }
    }
}

/* 4) ações POST (envio e leitura) */
$sendTextResp    = null;
$sendFileResp    = null;
$listChatsResp   = null;
$getMsgsResp     = null;
$adminResp       = null;
$last_error_note = '';

if ($token && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'send_text') {
        $phone = normalize_phone_br($_POST['phone'] ?? '');
        $msg   = (string)($_POST['message'] ?? '');
        if ($phone && $msg !== '') {
            $url = "{$BASE}/" . rawurlencode($SESSION) . '/send-message';
            $sendTextResp = http_request_wpp_local('POST', $url, [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json'
            ], [
                'phone'   => $phone,
                'message' => $msg
            ], $TIMEOUT_S);
        }
    }
    if ($action === 'send_file') {
        $phone   = normalize_phone_br($_POST['phone_file'] ?? '');
        $caption = (string)($_POST['caption'] ?? '');
        if ($phone && !empty($_FILES['file']['tmp_name'])) {
            $bin = file_get_contents($_FILES['file']['tmp_name']);
            $b64 = base64_encode($bin);
            $url = "{$BASE}/" . rawurlencode($SESSION) . '/send-file-base64';
            $filename = basename($_FILES['file']['name']);
            $sendFileResp = http_request_wpp_local('POST', $url, [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json'
            ], [
                'phone'    => $phone,
                'filename' => $filename,
                'base64'   => $b64,
                'caption'  => $caption
            ], $TIMEOUT_S);
        }
    }
    if ($action === 'list_chats') {
        $urlA = "{$BASE}/" . rawurlencode($SESSION) . '/list-chats';
        $listChatsResp = http_request_wpp_local('GET', $urlA, ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);
        if (!$listChatsResp['ok']) {
            $urlB = "{$BASE}/" . rawurlencode($SESSION) . '/chats';
            $alt  = http_request_wpp_local('GET', $urlB, ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);
            if ($alt['ok']) $listChatsResp = $alt;
            else $last_error_note = 'Nenhum endpoint de listagem de chats respondeu OK (/list-chats ou /chats).';
        }
    }
    if ($action === 'get_messages') {
        $phone = normalize_phone_br($_POST['phone_search'] ?? '');
        $limit = (int)($_POST['limit'] ?? 10);
        if ($limit <= 0 || $limit > 200) $limit = 10;

        $urlA = "{$BASE}/" . rawurlencode($SESSION) . '/get-messages?phone=' . rawurlencode($phone) . '&limit=' . $limit;
        $getMsgsResp = http_request_wpp_local('GET', $urlA, ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);

        if (!$getMsgsResp['ok']) {
            $urlB = "{$BASE}/" . rawurlencode($SESSION) . '/messages?phone=' . rawurlencode($phone) . '&limit=' . $limit;
            $alt  = http_request_wpp_local('GET', $urlB, ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);
            if ($alt['ok']) $getMsgsResp = $alt;
            else $last_error_note = 'Nenhum endpoint de mensagens respondeu OK (/get-messages ou /messages).';
        }
    }

    // Ações admin (status / logout / close / delete)
    if (isset($_POST['admin'])) {
        $act = $_POST['admin'];
        $s   = rawurlencode($SESSION);

        if ($act === 'status_session') {
            $adminResp = http_request_wpp_local('GET', "{$BASE}/{$s}/status-session", ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);
        } elseif ($act === 'logout') {
            $adminResp = http_request_wpp_local('POST', "{$BASE}/{$s}/logout-session", [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json'
            ], '{}', $TIMEOUT_S);
        } elseif ($act === 'close') {
            $adminResp = http_request_wpp_local('POST', "{$BASE}/{$s}/close-session", [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json'
            ], '{}', $TIMEOUT_S);
        } elseif ($act === 'delete') {
            $adminResp = http_request_wpp_local('DELETE', "{$BASE}/{$s}/session", ['Authorization' => "Bearer {$token}"], null, $TIMEOUT_S);
        }
    }
}

function badge_wpp($ok) { return $ok ? '<span class="ok">OK</span>' : '<span class="err">ERRO</span>'; }

require_once ROOT_PATH . '/system/includes/head.php';
?>

<style>
  body{background:#f6f7f9;}
  .wpp-card{background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.08);padding:20px;max-width:1100px;margin:0 auto 18px}
  .wpp-main-title{margin:0 0 8px;font-size:22px}
  .wpp-code, .wpp-pre{background:#f0f3f6;border-radius:6px;padding:10px;display:block;overflow:auto;white-space:pre-wrap}
  .wpp-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .wpp-qr{display:flex;align-items:center;justify-content:center;background:#111;border-radius:12px;padding:16px;min-height:280px}
  .wpp-qr img{max-width:100%;height:auto}
  .wpp-muted{color:#666}
  .ok{color:#0a0;font-weight:700}
  .err{color:#c00;font-weight:700}
  .wpp-kv{display:grid;grid-template-columns:160px 1fr;gap:6px 12px;margin:10px 0}
  .wpp-kv div:first-child{color:#666}
  .wpp-btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#111;color:#fff;text-decoration:none}
  .wpp-small{font-size:12px;color:#888}
  form.wpp-form .row{display:grid;grid-template-columns:220px 1fr;gap:10px;align-items:center;margin:8px 0}
  form.wpp-form input[type=text],
  form.wpp-form input[type=number]{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
  form.wpp-form input[type=file]{border:1px dashed #aaa;border-radius:8px;padding:8px;background:#fafafa}
  form.wpp-form textarea{width:100%;min-height:80px;padding:10px;border:1px solid #ccc;border-radius:8px}
  .wpp-actions{margin-top:8px}
  .wpp-actions button{padding:10px 14px;border:0;border-radius:8px;background:#0a66c2;color:#fff;cursor:pointer}
  .wpp-flex{display:flex;gap:14px;flex-wrap:wrap}
</style>

<div class="container-fluid mt-3">
  <div class="wpp-card">
    <h1 class="wpp-main-title">WPPConnect — Instância: <?=wpp_h($instancia['nome'])?></h1>
    <div class="wpp-kv">
      <div>Host:</div><div><?=htmlspecialchars($WPP_HOST)?>:<?=$WPP_PORT?></div>
      <div>Sessão:</div><div><?=htmlspecialchars($SESSION)?></div>
      <div>Auth:</div><div>Bearer (token gerado via secret)</div>
      <div>Health:</div><div><?= $health['code'] ? $health['code'].' '.($health['ok']?'OK':'') : '—' ?></div>
      <div>Secret (fonte):</div>
      <div class="wpp-small">
        <?=htmlspecialchars($SECRET_SOURCE)?>
        <br><b>Secret em uso:</b> <?=htmlspecialchars($SECRET_KEY)?>
      </div>
    </div>
    <p class="wpp-muted">
      Esta tela gera o token, inicia a sessão, verifica o status, exibe o QR quando necessário
      e permite testar envio/leitura para esta instância.
    </p>
  </div>

  <div class="wpp-card">
    <h2>1) Gerar token</h2>
    <p>Status: <?= badge_wpp($tok['ok'] ?? false) ?> (HTTP <?=$tok['code'] ?? 0;?>)</p>
<?php if(!empty($tok['json'])): ?>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($tok['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
    <pre class="wpp-pre"><?=htmlspecialchars($tok['body_raw'] ?? $tok['error'] ?? 'Sem corpo de resposta')?></pre>
    <pre class="wpp-pre"><b>Headers (resposta):</b>
<?=htmlspecialchars($tok['headers_raw'] ?? '')?></pre>
<?php endif; ?>
  </div>

  <div class="wpp-card">
    <h2>Debug da requisição /generate-token</h2>
    <pre class="wpp-pre"><?=
      htmlspecialchars(
        $genTokenMethod . ' ' . $genTokenUrl . "\n\n" .
        "Headers (request):\n" . print_r($genTokenHeaders, true) . "\n" .
        "Body:\n" . $genTokenBody . "\n\n" .
        "Secret usado na URL: " . $SECRET_KEY . "\n",
        ENT_QUOTES,
        'UTF-8'
      )
    ?></pre>
    <p class="wpp-muted">
      Compare esse valor com o <code>SECRET_KEY</code> / <code>SECRETKEY</code> que você passou no
      <b>docker run</b> ou no <code>config.json</code> do WPPConnect. Se for diferente, o servidor
      devolve a mensagem “The SECRET_KEY is incorrect”.
    </p>
  </div>

  <div class="wpp-card">
    <h2>2) Iniciar sessão</h2>
    <p>Status: <?= badge_wpp($start['ok'] ?? false) ?> (HTTP <?=$start['code'] ?? 0;?>)</p>
<?php if(!empty($start['json'])): ?>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($start['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
    <pre class="wpp-pre"><?=htmlspecialchars($start['body_raw'] ?? $start['error'] ?? 'Sem corpo de resposta')?></pre>
    <pre class="wpp-pre"><b>Headers:</b>
<?=htmlspecialchars($start['headers_raw'] ?? '')?></pre>
<?php endif; ?>
  </div>

  <div class="wpp-card">
    <h2>3) Status da sessão</h2>
    <p>Status: <?= badge_wpp($status['ok'] ?? false) ?> (HTTP <?=$status['code'] ?? 0;?>)</p>
<?php if(!empty($status['json'])): ?>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($status['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
    <pre class="wpp-pre"><?=htmlspecialchars($status['body_raw'] ?? $status['error'] ?? 'Sem corpo de resposta')?></pre>
    <pre class="wpp-pre"><b>Headers:</b>
<?=htmlspecialchars($status['headers_raw'] ?? '')?></pre>
<?php endif; ?>
  </div>

  <div class="wpp-card">
    <h2>4) QR Code</h2>
<?php if ($qrDataUrl): ?>
    <div class="wpp-grid">
      <div class="wpp-qr"><img src="<?=$qrDataUrl?>" alt="QR Code WhatsApp"></div>
      <div>
        <p>Abra o WhatsApp → <b>Conectar um aparelho</b> → aponte a câmera para este QR.</p>
        <?php if ($qr_note): ?><p class="wpp-muted">Nota: <?=$qr_note?></p><?php endif; ?>
        <p class="wpp-muted">O QR costuma expirar rápido; clique abaixo para regenerar.</p>
        <p><a class="wpp-btn" href="?id=<?=$id?>&r=<?=time()?>">Recarregar</a></p>
<?php if(!empty($qr['json'])): ?>
        <h3>Resposta do /qrcode-session</h3>
        <pre class="wpp-pre"><?=htmlspecialchars(json_encode($qr['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
        <pre class="wpp-pre wpp-muted">Sem JSON (provavelmente veio PNG binário ou base64 puro).</pre>
<?php endif; ?>
      </div>
    </div>
<?php else: ?>
    <p class="wpp-muted">Nenhum QR retornado agora. Se o status acima mostrar “connected:true”, já está logado ✅</p>
<?php endif; ?>
  </div>

  <div class="wpp-card">
    <h2>5) Testes rápidos</h2>
    <div class="wpp-flex">
      <form method="post" class="wpp-form">
        <input type="hidden" name="action" value="send_text">
        <h3>Enviar mensagem de texto</h3>
        <div class="row"><label>Telefone (somente números, ex.: 5537988887777)</label><input type="text" name="phone" required></div>
        <div class="row"><label>Mensagem</label><textarea name="message" required>Teste via WPPConnect ✅</textarea></div>
        <div class="wpp-actions"><button type="submit">Enviar texto</button></div>
      </form>

      <form method="post" enctype="multipart/form-data" class="wpp-form">
        <input type="hidden" name="action" value="send_file">
        <h3>Enviar imagem/arquivo</h3>
        <div class="row"><label>Telefone (somente números)</label><input type="text" name="phone_file" required></div>
        <div class="row"><label>Arquivo</label><input type="file" name="file" accept="image/*,application/pdf,application/zip" required></div>
        <div class="row"><label>Legenda (opcional)</label><input type="text" name="caption" placeholder="Legenda/caption"></div>
        <div class="wpp-actions"><button type="submit">Enviar arquivo</button></div>
      </form>
    </div>

<?php if ($sendTextResp): ?>
    <h4>Resposta envio de texto</h4>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($sendTextResp['json'] ?? ['http'=>$sendTextResp['code'], 'body'=>$sendTextResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>

<?php if ($sendFileResp): ?>
    <h4>Resposta envio de arquivo</h4>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($sendFileResp['json'] ?? ['http'=>$sendFileResp['code'], 'body'=>$sendFileResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>
  </div>

  <div class="wpp-card">
    <h2>6) Leitura</h2>
    <div class="wpp-flex">
      <form method="post" class="wpp-form">
        <input type="hidden" name="action" value="list_chats">
        <h3>Listar conversas</h3>
        <div class="wpp-actions"><button type="submit">Listar</button></div>
      </form>

      <form method="post" class="wpp-form">
        <input type="hidden" name="action" value="get_messages">
        <h3>Buscar mensagens por número</h3>
        <div class="row"><label>Telefone (somente números)</label><input type="text" name="phone_search" required></div>
        <div class="row"><label>Limite</label><input type="number" name="limit" value="10" min="1" max="200"></div>
        <div class="wpp-actions"><button type="submit">Buscar</button></div>
      </form>
    </div>

<?php if ($listChatsResp): ?>
    <h4>Conversas</h4>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($listChatsResp['json'] ?? ['http'=>$listChatsResp['code'], 'body'=>$listChatsResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>

<?php if ($getMsgsResp): ?>
    <h4>Mensagens</h4>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($getMsgsResp['json'] ?? ['http'=>$getMsgsResp['code'], 'body'=>$getMsgsResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>

<?php if ($last_error_note): ?>
    <p class="wpp-muted">Obs.: <?=$last_error_note?></p>
<?php endif; ?>
  </div>

  <div class="wpp-card">
    <h2>Admin — Sessão</h2>
    <div class="wpp-flex">
      <form method="post"><input type="hidden" name="admin" value="status_session"><button type="submit" class="wpp-actions">Status detalhado</button></form>
      <form method="post"><input type="hidden" name="admin" value="logout"><button type="submit" class="wpp-actions">Logout (WhatsApp)</button></form>
      <form method="post"><input type="hidden" name="admin" value="close"><button type="submit" class="wpp-actions">Close session (API)</button></form>
      <form method="post" onsubmit="return confirm('Apagar a sessão? Isto força um novo QR.');">
        <input type="hidden" name="admin" value="delete"><button type="submit" class="wpp-actions" style="background:#c00;color:#fff">Apagar sessão</button>
      </form>
    </div>
<?php if(isset($adminResp)): ?>
    <h4>Resposta</h4>
    <pre class="wpp-pre"><?=htmlspecialchars(json_encode($adminResp['json'] ?? ['http'=>$adminResp['code'],'body'=>$adminResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>
    <p class="wpp-muted">Fluxo recomendado quando der erro após ler o QR: <b>Status detalhado</b> → se travado, <b>Logout</b> → <b>Close</b> → (se persistir) <b>Apagar sessão</b> e tentar novo QR.</p>
  </div>
</div>

<?php
require_once ROOT_PATH . '/system/includes/footer.php';
