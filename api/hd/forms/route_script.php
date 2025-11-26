<?php
// public/api/hd/forms/route_script.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
header('Content-Type: application/json; charset=utf-8');
$dbc = $conn ?? (isset($mysqli) ? $mysqli : null);
if(!$dbc instanceof mysqli){ echo json_encode(['ok'=>false,'error'=>'Sem conexão']); exit; }

$session = $_GET['session'] ?? '';
$answer  = trim($_GET['answer'] ?? '');
$step    = $_GET['step'] ?? null;
$script_id = $_GET['script_id'] ?? null;

// Carrega o script default (o primeiro ativo) ou o especificado
function load_script($dbc, $script_id){
  if($script_id){
    $stmt = $dbc->prepare("SELECT id,nome,script_json FROM hd_form_script WHERE id=? AND ativo=1");
    $stmt->bind_param('i', $script_id);
  } else {
    $stmt = $dbc->prepare("SELECT id,nome,script_json FROM hd_form_script WHERE ativo=1 ORDER BY id ASC LIMIT 1");
  }
  $stmt->execute(); $res=$stmt->get_result(); $row=$res?$res->fetch_assoc():null; $stmt->close(); return $row;
}

$script = load_script($dbc, $script_id);
if(!$script){ echo json_encode(['ok'=>true,'messages'=>['Olá! Nenhum script ativo encontrado. Envie sua demanda:'],'next_step'=>null]); exit; }

$S = json_decode($script['script_json'], true);
if(!$S){ echo json_encode(['ok'=>false,'error'=>'Script JSON inválido']); exit; }

// Estrutura esperada do script (MVP):
// {
//   "first": "root",
//   "steps": {
//     "root": {"q":"O que você precisa?","var":"intent","branch":[
//         {"match":"rede|internet","goto":"rede"},
//         {"match":"impressora|print","goto":"print"}
//     ], "else":"fallback"},
//     "fallback": {"action":"link","url":"/public/modules/helpdesk/pages/formulario.php?id=1"},
//     "rede": [
//       {"q":"Quando o problema iniciou?","var":"quando"},
//       {"q":"Descreva como aconteceu?","var":"desc"},
//       {"action":"form","form_id":5}
//     ],
//     "print": [ ... ]
//   }
// }

$first = $S['first'] ?? 'root';
$steps = $S['steps'] ?? [];

if(!$step){ // primeira chamada
  $node = $steps[$first] ?? null;
  if(!$node){ echo json_encode(['ok'=>false,'error'=>'Script sem passo inicial']); exit; }
  if(isset($node['q'])){
    echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>[$node['q']],'next_step'=>$first]); exit;
  } else {
    echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>['Como posso ajudar?'],'next_step'=>$first]); exit;
  }
}

// Continuação: processa a resposta do usuário e navega
$node = $steps[$step] ?? null;
if(!$node){ echo json_encode(['ok'=>false,'error'=>'Passo desconhecido']); exit; }

// passo de branching
if(isset($node['q']) && isset($node['branch'])){
  $ans = mb_strtolower($answer, 'UTF-8');
  foreach($node['branch'] as $b){
    $pattern = '/(' . $b['match'] . ')/i';
    if(preg_match($pattern, $ans)){ // vai para o nó indicado
      $goto = $b['goto'];
      $n2 = $steps[$goto] ?? null;
      if(!$n2){ echo json_encode(['ok'=>false,'error'=>'Destino inválido']); exit; }
      if(is_array($n2) && isset($n2[0])){
        // é uma sequência de perguntas
        $q = $n2[0]['q'] ?? 'Detalhe, por favor:';
        echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>[$q],'next_step'=>$goto.':0']); exit;
      } else if(isset($n2['q'])){
        echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>[$n2['q']],'next_step'=>$goto]); exit;
      } else if(isset($n2['action'])){
        // ação direta
        if($n2['action']==='form'){
          $form_id = (int)($n2['form_id'] ?? 0);
          $form = null;
          if($form_id){
            $rs=$dbc->query("SELECT schema_json FROM hd_formulario WHERE id=".$form_id);
            if($rs && ($r=$rs->fetch_assoc())) $form = json_decode($r['schema_json'], true);
          }
          echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>['Abrindo formulário...'],'action'=>'render_form','form'=>$form,'next_step'=>null]); exit;
        } else if($n2['action']==='link'){
          echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>['Abrindo formulário...'],'action'=>'open_link','url'=>($n2['url']??'/'),'next_step'=>null]); exit;
        }
      }
    }
  }
  // nenhum branch bateu → else
  if(isset($node['else'])){
    $goto = $node['else'];
    $n2 = $steps[$goto] ?? null;
    if(!$n2){ echo json_encode(['ok'=>false,'error'=>'Destino else inválido']); exit; }
    if(isset($n2['action']) && $n2['action']==='link'){
      echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>['Acesse o formulário para prosseguir.'],'action'=>'open_link','url'=>($n2['url']??'/'),'next_step'=>null]); exit;
    }
    if(is_array($n2) && isset($n2[0])){
      $q = $n2[0]['q'] ?? 'Detalhe, por favor:';
      echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>[$q],'next_step'=>$goto.':0']); exit;
    }
    if(isset($n2['q'])){
      echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>[$n2['q']],'next_step'=>$goto]); exit;
    }
  }
  echo json_encode(['ok'=>true,'messages'=>['Não entendi. Pode reformular?'],'next_step'=>$step]); exit;
}

// sequência linear "nó:índice"
if(strpos($step, ':')!==false){
  list($nodeKey, $idxStr) = explode(':', $step, 2);
  $idx = (int)$idxStr;
  $seq = $steps[$nodeKey] ?? null;
  if(!is_array($seq)){ echo json_encode(['ok'=>false,'error'=>'Sequência inválida']); exit; }
  $nextIdx = $idx + 1;
  // se existir próxima pergunta
  if(isset($seq[$nextIdx]) && isset($seq[$nextIdx]['q'])){
    echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>[$seq[$nextIdx]['q']],'next_step'=>$nodeKey.':'.$nextIdx]); exit;
  }
  // fim da sequência → ação
  $last = end($seq);
  if(isset($last['action'])){
    if($last['action']==='form'){
      $form_id = (int)($last['form_id'] ?? 0);
      $form = null;
      if($form_id){
        $rs=$dbc->query("SELECT schema_json FROM hd_formulario WHERE id=".$form_id);
        if($rs && ($r=$rs->fetch_assoc())) $form = json_decode($r['schema_json'], true);
      }
      echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>['Beleza. Agora preencha o formulário.'],'action'=>'render_form','form'=>$form,'next_step'=>null]); exit;
    } else if($last['action']==='link'){
      echo json_encode(['ok'=>true,'script_id'=>$script['id'],'messages'=>['Abrindo formulário...'],'action'=>'open_link','url'=>($last['url']??'/'),'next_step'=>null]); exit;
    }
  }
  echo json_encode(['ok'=>true,'messages'=>['Certo! Obrigado.'],'next_step'=>null]); exit;
}

echo json_encode(['ok'=>true,'messages'=>['Certo, prossiga...'],'next_step'=>$step]);
