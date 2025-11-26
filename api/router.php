<?php
// public/modules/helpdesk/api/router.php
declare(strict_types=1);
ini_set('display_errors', '1'); error_reporting(E_ALL);

require_once dirname(__DIR__, 3).'/config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';

require_once __DIR__.'/Utils.php';
require_once __DIR__.'/TicketController.php';

use HD\Api\Utils;
use HD\Api\TicketController;

// ===== CORS básico (ajuste se expor externamente)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ===== Checa sessão Mozart (ajuste conforme seu SSO)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('usuario_logado') || !usuario_logado()) {
  Utils::json(['error'=>'unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = Utils::pathAfter('/modules/helpdesk/api');

// Roteamento simples
try {
  $ctl = new TicketController($conn ?? $mysqli ?? null);

  // POST /api/hd/tickets  (criar)
  if ($method==='POST' && preg_match('#^/hd/tickets/?$#', $path)) {
    $data = Utils::jsonBody();
    Utils::json($ctl->createTicket($data));
  }

  // GET /api/hd/tickets/mine
  if ($method==='GET' && preg_match('#^/hd/tickets/mine/?$#', $path)) {
    $q = [
      'page' => (int)($_GET['page'] ?? 1),
      'per'  => (int)($_GET['per'] ?? 20),
      'status'=> trim($_GET['status'] ?? ''),
      'search'=> trim($_GET['search'] ?? ''),
    ];
    Utils::json($ctl->listMyTickets($q));
  }

  // GET /api/hd/tickets/:id
  if ($method==='GET' && preg_match('#^/hd/tickets/(\d+)/?$#', $path, $m)) {
    Utils::json($ctl->getTicket((int)$m[1]));
  }

  // POST /api/hd/tickets/:id/mensagens
  if ($method==='POST' && preg_match('#^/hd/tickets/(\d+)/mensagens/?$#', $path, $m)) {
    Utils::json($ctl->addMessage((int)$m[1], $_POST, $_FILES));
  }

  // (Opcional) POST /api/hd/tickets/:id/avaliacao
  if ($method==='POST' && preg_match('#^/hd/tickets/(\d+)/avaliacao/?$#', $path, $m)) {
    $data = Utils::jsonBody();
    Utils::json($ctl->rateTicket((int)$m[1], $data));
  }

  // Não encontrado
  Utils::json(['error'=>'not_found', 'path'=>$path], 404);

} catch (Throwable $e) {
  Utils::json(['error'=>'exception','message'=>$e->getMessage()], 500);
}
