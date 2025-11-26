<?php
require_once __DIR__.'/_lib/bpm_store.php';
header('Content-Type: application/json; charset=utf-8');
$store = new BpmStore();
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? ($payload['action'] ?? '');
try {
  switch($action){
    case 'toggle':
      $id=$payload['id']??null; $p=$store->getProcess($id); if(!$p) throw new Exception('Processo não encontrado');
      $p['active']=!($p['active']??false); $store->saveProcess($p); echo json_encode(['ok'=>true,'active'=>$p['active']]); break;
    case 'delete':
      $id=$payload['id']??null; $store->deleteProcess($id); echo json_encode(['ok'=>true]); break;
    case 'save_wizard':
      $_SESSION['bpm_wizard']=$payload['data']??[]; echo json_encode(['ok'=>true]); break;
    default: echo json_encode(['ok'=>false,'error'=>'Ação não reconhecida']);
  }
} catch (Throwable $e){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }