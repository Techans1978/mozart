<?php
/* ===========================
 *  WPPConnect — teste_wpp.php
 *  Gera token, inicia sessão, exibe QR, teste de envio/leitura e envio de imagem
 * =========================== */

/** CONFIGURE AQUI (ou via ENV) **/
const WPP_HOST   = '127.0.0.1';        // IP ou domínio do servidor onde está o container
const WPP_PORT   = 21465;              // porta do WPPConnect
const SESSION    = 'superabc';         // nome da sessão
const TIMEOUT_S  = 20;                 // timeout das chamadas

$SECRET_FROM_ENV = getenv('WPP_SECRET');
const SECRET_DEFAULT = 'FSPSOLGRDOSOBWGC';
$SECRET_KEY = $SECRET_FROM_ENV !== false ? $SECRET_FROM_ENV : SECRET_DEFAULT;

/* ===== utils ===== */
function parse_headers_raw(string $raw): array {
  $lines = preg_split("/\r\n|\n|\r/", trim($raw));
  $out = ['_status' => $lines[0] ?? ''];
  foreach ($lines as $i => $ln) {
    if ($i===0 || $ln==='') continue;
    $p = strpos($ln, ':');
    if ($p!==false) {
      $k = strtolower(trim(substr($ln,0,$p)));
      $v = trim(substr($ln,$p+1));
      // múltiplos headers com mesmo nome
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
function looks_like_base64(string $s): bool {
  // ignora quebras de linha
  $s2 = preg_replace('/\s+/', '', $s);
  if ($s2 === '') return false;
  if (strlen($s2) % 4 !== 0) return false;
  // só charset válido e '=' de padding
  return (bool)preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $s2);
}
function http_request(string $method, string $url, array $headers = [], $body = null): array {
  $ch = curl_init($url);
  $h = [];
  foreach ($headers as $k => $v) $h[] = is_int($k) ? $v : ($k . ': ' . $v);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST   => strtoupper($method),
    CURLOPT_HTTPHEADER      => $h,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_MAXREDIRS       => 3,
    CURLOPT_CONNECTTIMEOUT  => TIMEOUT_S,
    CURLOPT_TIMEOUT         => TIMEOUT_S,
    CURLOPT_HEADER          => true,
    CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,
  ]);
  if ($body !== null) {
    if (is_array($body)) $body = json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  }
  $raw = curl_exec($ch);
  if ($raw === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok'=>false, 'code'=>0, 'headers'=>[], 'headers_raw'=>'', 'body_raw'=>null, 'json'=>null, 'error'=>"cURL: $err"];
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
    'ok'       => ($statusCode>=200 && $statusCode<300),
    'code'     => $statusCode,
    'headers'  => $headersParsed,
    'headers_raw' => $rawHeaders,
    'body_raw' => $respBody,
    'json'     => $json,
  ];
}
function normalize_phone_br($p){ return preg_replace('/\D+/', '', $p ?? ''); }

/* ==== endpoints base ==== */
$BASE = 'http://' . WPP_HOST . ':' . WPP_PORT . '/api';

/* 0) health */
$health = http_request('GET', "http://" . WPP_HOST . ":" . WPP_PORT . "/health");

/* 1) token */
$genTokenUrl = "{$BASE}/" . rawurlencode(SESSION) . '/' . rawurlencode($SECRET_KEY) . '/generate-token';
$tok = http_request('POST', $genTokenUrl, ['Content-Type'=>'application/json'], '{}');
$token = $tok['json']['token'] ?? null;

/* 2) start */
$start = null;
if ($token) {
  $startUrl = "{$BASE}/" . rawurlencode(SESSION) . '/start-session';
  $start = http_request('POST', $startUrl, [
    'Authorization'=>"Bearer {$token}",
    'Content-Type'=>'application/json'
  ], '{}');
}

/* 3) status + QR */
$status = null; $qr = null; $qrDataUrl = null; $qr_note = '';
if ($token) {
  $statusUrl = "{$BASE}/" . rawurlencode(SESSION) . '/check-connection-session';
  $status = http_request('GET', $statusUrl, ['Authorization'=>"Bearer {$token}"]);

  if (!($status['json']['connected'] ?? false)) {
    $qrUrl = "{$BASE}/" . rawurlencode(SESSION) . '/qrcode-session';
    $qr = http_request('GET', $qrUrl, ['Authorization'=>"Bearer {$token}"]);

    // fontes possíveis:
    // 1) JSON: { qrcode: "data:image/png;base64,..." }  ou { base64: "iVBOR..." }
    // 2) Body PNG binário com Content-Type: image/png
    // 3) Body com base64 puro (texto)
    $qrcodeRaw = $qr['json']['qrcode'] ?? $qr['json']['base64'] ?? null;

    if ($qrcodeRaw) {
      if (strpos($qrcodeRaw, 'data:image') === 0) {
        $qrDataUrl = $qrcodeRaw;
      } elseif (looks_like_base64($qrcodeRaw)) {
        $qrDataUrl = 'data:image/png;base64,' . $qrcodeRaw;
      } else {
        // raro, mas se vier texto não-base64
        $qrDataUrl = 'data:image/png;base64,' . base64_encode($qrcodeRaw);
        $qr_note = 'QR veio em JSON mas não reconhecido como base64; foi convertido.';
      }
    } else {
      // sem campo JSON: olha Content-Type
      $ct = strtolower($qr['headers']['content-type'] ?? '');
      if (strpos($ct, 'image/png') !== false) {
        $qrDataUrl = 'data:image/png;base64,' . base64_encode($qr['body_raw'] ?? '');
        $qr_note = 'QR retornado como PNG binário; convertido para base64.';
      } else {
        // pode ser base64 puro no body
        $body = trim($qr['body_raw'] ?? '');
        if ($body !== '') {
          if (looks_like_base64($body)) {
            $qrDataUrl = 'data:image/png;base64,' . $body;
            $qr_note = 'QR retornado como base64 puro no corpo.';
          } else {
            $qrDataUrl = null; // não dá pra montar
          }
        }
      }
    }
  }
}

/* 4) ações POST (envio e leitura) */
$sendTextResp = null;
$sendFileResp = null;
$listChatsResp = null;
$getMsgsResp   = null;
$last_error_note = '';

if ($token && ($_SERVER['REQUEST_METHOD']==='POST')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'send_text') {
    $phone = normalize_phone_br($_POST['phone'] ?? '');
    $msg   = (string)($_POST['message'] ?? '');
    if ($phone && $msg!=='') {
      // endpoint típico
      $url = "{$BASE}/" . rawurlencode(SESSION) . '/send-message';
      $sendTextResp = http_request('POST', $url, [
        'Authorization'=>"Bearer {$token}",
        'Content-Type'=>'application/json'
      ], [
        'phone'   => $phone,
        'message' => $msg
      ]);
    }
  }
  if ($action === 'send_file') {
    $phone = normalize_phone_br($_POST['phone_file'] ?? '');
    $caption = (string)($_POST['caption'] ?? '');
    if ($phone && !empty($_FILES['file']['tmp_name'])) {
      $bin = file_get_contents($_FILES['file']['tmp_name']);
      $b64 = base64_encode($bin);
      // endpoint: send-file-base64
      $url = "{$BASE}/" . rawurlencode(SESSION) . '/send-file-base64';
      $filename = basename($_FILES['file']['name']);
      $sendFileResp = http_request('POST', $url, [
        'Authorization'=>"Bearer {$token}",
        'Content-Type'=>'application/json'
      ], [
        'phone'    => $phone,
        'filename' => $filename,
        'base64'   => $b64,
        'caption'  => $caption
      ]);
    }
  }
  if ($action === 'list_chats') {
    // tenta endpoint A
    $urlA = "{$BASE}/" . rawurlencode(SESSION) . '/list-chats';
    $listChatsResp = http_request('GET', $urlA, ['Authorization'=>"Bearer {$token}"]);
    if (!$listChatsResp['ok']) {
      // fallback endpoint B
      $urlB = "{$BASE}/" . rawurlencode(SESSION) . '/chats';
      $alt = http_request('GET', $urlB, ['Authorization'=>"Bearer {$token}"]);
      if ($alt['ok']) $listChatsResp = $alt;
      else $last_error_note = 'Nenhum endpoint de listagem de chats respondeu OK (/list-chats ou /chats).';
    }
  }
  if ($action === 'get_messages') {
    $phone = normalize_phone_br($_POST['phone_search'] ?? '');
    $limit = (int)($_POST['limit'] ?? 10);
    if ($limit<=0 || $limit>200) $limit = 10;

    // tenta endpoint A (muitos builds usam "get-messages")
    $urlA = "{$BASE}/" . rawurlencode(SESSION) . '/get-messages?phone=' . rawurlencode($phone) . '&limit=' . $limit;
    $getMsgsResp = http_request('GET', $urlA, ['Authorization'=>"Bearer {$token}"]);

    if (!$getMsgsResp['ok']) {
      // fallback endpoint B: /messages
      $urlB = "{$BASE}/" . rawurlencode(SESSION) . '/messages?phone=' . rawurlencode($phone) . '&limit=' . $limit;
      $alt = http_request('GET', $urlB, ['Authorization'=>"Bearer {$token}"]);
      if ($alt['ok']) $getMsgsResp = $alt;
      else $last_error_note = 'Nenhum endpoint de mensagens respondeu OK (/get-messages ou /messages).';
    }
  }
}

/* ==== HTML ==== */
function badge($ok) { return $ok ? '<span class="ok">OK</span>' : '<span class="err">ERRO</span>'; }
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Teste WPPConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f7f9;margin:0;padding:24px;color:#222}
    .card{background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.08);padding:20px;max-width:1100px;margin:0 auto 18px}
    h1{margin:0 0 8px;font-size:22px}
    code,pre{background:#f0f3f6;border-radius:6px;padding:10px;display:block;overflow:auto;white-space:pre-wrap}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .qr{display:flex;align-items:center;justify-content:center;background:#111;border-radius:12px;padding:16px;min-height:280px}
    .qr img{max-width:100%;height:auto}
    .muted{color:#666}
    .ok{color:#0a0;font-weight:700}
    .err{color:#c00;font-weight:700}
    .kv{display:grid;grid-template-columns:160px 1fr;gap:6px 12px;margin:10px 0}
    .kv div:first-child{color:#666}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#111;color:#fff;text-decoration:none}
    .small{font-size:12px;color:#888}
    form .row{display:grid;grid-template-columns:220px 1fr;gap:10px;align-items:center;margin:8px 0}
    input[type=text],input[type=number]{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
    input[type=file]{border:1px dashed #aaa;border-radius:8px;padding:8px;background:#fafafa}
    textarea{width:100%;min-height:80px;padding:10px;border:1px solid #ccc;border-radius:8px}
    .actions{margin-top:8px}
    .actions button{padding:10px 14px;border:0;border-radius:8px;background:#0a66c2;color:#fff;cursor:pointer}
    .flex{display:flex;gap:14px;flex-wrap:wrap}
  </style>
</head>
<body>
  <div class="card">
    <h1>WPPConnect — Teste rápido</h1>
    <div class="kv">
      <div>Host:</div><div><?=htmlspecialchars(WPP_HOST)?>:<?=WPP_PORT?></div>
      <div>Sessão:</div><div><?=htmlspecialchars(SESSION)?></div>
      <div>Auth:</div><div>Bearer (token gerado via secret)</div>
      <div>Health:</div><div><?= $health['code'] ? $health['code'].' '.($health['ok']?'OK':'') : '—' ?></div>
      <div>Secret (fonte):</div><div class="small"><?= $SECRET_FROM_ENV!==false ? 'ENV WPP_SECRET' : 'const SECRET_DEFAULT' ?></div>
    </div>
    <p class="muted">Este script gera o token, inicia a sessão, verifica o status, exibe o QR quando necessário e permite testar envio/leitura.</p>
  </div>

  <div class="card">
    <h2>1) Gerar token</h2>
    <p>Status: <?= badge($tok['ok'] ?? false) ?> (HTTP <?=$tok['code'] ?? 0;?>)</p>
<?php if(!empty($tok['json'])): ?>
    <pre><?=htmlspecialchars(json_encode($tok['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
    <pre><?=htmlspecialchars($tok['body_raw'] ?? $tok['error'] ?? 'Sem corpo de resposta')?></pre>
    <pre><b>Headers:</b>
<?=htmlspecialchars($tok['headers_raw'] ?? '')?></pre>
<?php endif; ?>
  </div>

  <div class="card">
    <h2>2) Iniciar sessão</h2>
    <p>Status: <?= badge($start['ok'] ?? false) ?> (HTTP <?=$start['code'] ?? 0;?>)</p>
<?php if(!empty($start['json'])): ?>
    <pre><?=htmlspecialchars(json_encode($start['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
    <pre><?=htmlspecialchars($start['body_raw'] ?? $start['error'] ?? 'Sem corpo de resposta')?></pre>
    <pre><b>Headers:</b>
<?=htmlspecialchars($start['headers_raw'] ?? '')?></pre>
<?php endif; ?>
  </div>

  <div class="card">
    <h2>3) Status da sessão</h2>
    <p>Status: <?= badge($status['ok'] ?? false) ?> (HTTP <?=$status['code'] ?? 0;?>)</p>
<?php if(!empty($status['json'])): ?>
    <pre><?=htmlspecialchars(json_encode($status['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
    <pre><?=htmlspecialchars($status['body_raw'] ?? $status['error'] ?? 'Sem corpo de resposta')?></pre>
    <pre><b>Headers:</b>
<?=htmlspecialchars($status['headers_raw'] ?? '')?></pre>
<?php endif; ?>
  </div>

  <div class="card">
    <h2>4) QR Code</h2>
<?php if ($qrDataUrl): ?>
    <div class="grid">
      <div class="qr"><img src="<?=$qrDataUrl?>" alt="QR Code WhatsApp"></div>
      <div>
        <p>Abra o WhatsApp → <b>Conectar um aparelho</b> → aponte a câmera para este QR.</p>
        <?php if ($qr_note): ?><p class="muted">Nota: <?=$qr_note?></p><?php endif; ?>
        <p class="muted">O QR costuma expirar rápido; clique abaixo para regenerar.</p>
        <p><a class="btn" href="?r=<?=time()?>">Recarregar</a></p>
<?php if(!empty($qr['json'])): ?>
        <h3>Resposta do /qrcode-session</h3>
        <pre><?=htmlspecialchars(json_encode($qr['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php else: ?>
        <pre class="muted">Sem JSON (provavelmente veio PNG binário ou base64 puro).</pre>
<?php endif; ?>
      </div>
    </div>
<?php else: ?>
    <p class="muted">Nenhum QR retornado agora. Se o status acima mostrar “connected:true”, já está logado ✅</p>
<?php endif; ?>
  </div>

  <div class="card">
    <h2>5) Testes rápidos</h2>
    <div class="flex">
      <form method="post">
        <input type="hidden" name="action" value="send_text">
        <h3>Enviar mensagem de texto</h3>
        <div class="row"><label>Telefone (somente números, ex.: 5537988887777)</label><input type="text" name="phone" required></div>
        <div class="row"><label>Mensagem</label><textarea name="message" required>Teste via WPPConnect ✅</textarea></div>
        <div class="actions"><button type="submit">Enviar texto</button></div>
      </form>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="send_file">
        <h3>Enviar imagem/arquivo</h3>
        <div class="row"><label>Telefone (somente números)</label><input type="text" name="phone_file" required></div>
        <div class="row"><label>Arquivo</label><input type="file" name="file" accept="image/*,application/pdf,application/zip" required></div>
        <div class="row"><label>Legenda (opcional)</label><input type="text" name="caption" placeholder="Legenda/caption"></div>
        <div class="actions"><button type="submit">Enviar arquivo</button></div>
      </form>
    </div>

<?php if ($sendTextResp): ?>
    <h4>Resposta envio de texto</h4>
    <pre><?=htmlspecialchars(json_encode($sendTextResp['json'] ?? ['http'=>$sendTextResp['code'], 'body'=>$sendTextResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>

<?php if ($sendFileResp): ?>
    <h4>Resposta envio de arquivo</h4>
    <pre><?=htmlspecialchars(json_encode($sendFileResp['json'] ?? ['http'=>$sendFileResp['code'], 'body'=>$sendFileResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>
  </div>

  <div class="card">
    <h2>6) Leitura</h2>
    <div class="flex">
      <form method="post">
        <input type="hidden" name="action" value="list_chats">
        <h3>Listar conversas</h3>
        <div class="actions"><button type="submit">Listar</button></div>
      </form>

      <form method="post">
        <input type="hidden" name="action" value="get_messages">
        <h3>Buscar mensagens por número</h3>
        <div class="row"><label>Telefone (somente números)</label><input type="text" name="phone_search" required></div>
        <div class="row"><label>Limite</label><input type="number" name="limit" value="10" min="1" max="200"></div>
        <div class="actions"><button type="submit">Buscar</button></div>
      </form>
    </div>

<?php if ($listChatsResp): ?>
    <h4>Conversas</h4>
    <pre><?=htmlspecialchars(json_encode($listChatsResp['json'] ?? ['http'=>$listChatsResp['code'], 'body'=>$listChatsResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>

<?php if ($getMsgsResp): ?>
    <h4>Mensagens</h4>
    <pre><?=htmlspecialchars(json_encode($getMsgsResp['json'] ?? ['http'=>$getMsgsResp['code'], 'body'=>$getMsgsResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>

<?php if ($last_error_note): ?>
    <p class="muted">Obs.: <?=$last_error_note?></p>
<?php endif; ?>
  </div>

  <div class="card">
    <p class="muted">Dica: se sua instalação usar endpoints ligeiramente diferentes, você pode ajustar a rota em <code>$BASE</code> e os caminhos de <code>/send-message</code>, <code>/send-file-base64</code>, <code>/list-chats</code>, <code>/get-messages</code>/<code>/messages</code>.</p>
  </div>

  <?php
/* ===== Ações administrativas extras: status detalhado / logout / close / delete ===== */
$adminResp = null;
if ($token && ($_SERVER['REQUEST_METHOD']==='POST') && isset($_POST['admin'])) {
  $act = $_POST['admin'];
  $s = rawurlencode(SESSION);

  if ($act === 'status_session') {
    $adminResp = http_request('GET', "{$BASE}/{$s}/status-session", ['Authorization'=>"Bearer {$token}"]);
  } elseif ($act === 'logout') {
    $adminResp = http_request('POST', "{$BASE}/{$s}/logout-session", ['Authorization'=>"Bearer {$token}", 'Content-Type'=>'application/json'], '{}');
  } elseif ($act === 'close') {
    $adminResp = http_request('POST', "{$BASE}/{$s}/close-session", ['Authorization'=>"Bearer {$token}", 'Content-Type'=>'application/json'], '{}');
  } elseif ($act === 'delete') {
    // CUIDADO: apaga a pasta de sessão na instância WPPConnect
    $adminResp = http_request('DELETE', "{$BASE}/{$s}/session", ['Authorization'=>"Bearer {$token}"]);
  }
}
?>
<div class="card">
  <h2>Admin — Sessão</h2>
  <div class="flex">
    <form method="post"><input type="hidden" name="admin" value="status_session"><button type="submit">Status detalhado</button></form>
    <form method="post"><input type="hidden" name="admin" value="logout"><button type="submit">Logout (WhatsApp)</button></form>
    <form method="post"><input type="hidden" name="admin" value="close"><button type="submit">Close session (API)</button></form>
    <form method="post" onsubmit="return confirm('Apagar a sessão? Isto força um novo QR.');">
      <input type="hidden" name="admin" value="delete"><button type="submit" style="background:#c00">Apagar sessão</button>
    </form>
  </div>
<?php if(isset($adminResp)): ?>
  <h4>Resposta</h4>
  <pre><?=htmlspecialchars(json_encode($adminResp['json'] ?? ['http'=>$adminResp['code'],'body'=>$adminResp['body_raw']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre>
<?php endif; ?>
  <p class="muted">Fluxo recomendado quando der erro após ler o QR: <b>Status detalhado</b> → se travado, <b>Logout</b> → <b>Close</b> → (se persistir) <b>Apagar sessão</b> e tentar novo QR.</p>
</div>

</body>
</html>
