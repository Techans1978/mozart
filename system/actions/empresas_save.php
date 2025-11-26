<?php
// pages/empresas_save.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status()===PHP_SESSION_NONE) session_start();

function val($k){ return trim($_POST[$k] ?? ''); }
function toJSONList($json){
  $arr = json_decode($json, true);
  if (!is_array($arr)) $arr=[];
  // normaliza: apenas strings não vazias
  $arr = array_values(array_filter(array_map(function($v){ return trim((string)$v); }, $arr), function($v){ return $v!==''; }));
  return json_encode($arr, JSON_UNESCAPED_UNICODE);
}

$id = (int)($_POST['id'] ?? 0);

$nome_empresarial = val('nome_empresarial');
$nome_fantasia    = val('nome_fantasia');
$apelido          = val('apelido');
$nome_interno     = val('nome_interno');
$cnpj             = val('cnpj');

$cep              = val('cep');
$logradouro       = val('endereco_logradouro');
$numero           = val('endereco_numero');
$bairro           = val('endereco_bairro');
$cidade           = val('endereco_cidade');
$uf               = val('endereco_uf');
$telefone         = val('telefone');
$email            = val('email');

$ativo            = isset($_POST['ativo']) ? 1 : 0;
$codigos_json     = toJSONList($_POST['codigos_integracao_json'] ?? '[]');

if ($nome_empresarial===''){
  $_SESSION['flash']['erro'] = 'Informe a Razão Social.';
  header('Location: '.BASE_URL.'/pages/cadastrar_empresa.php'.($id?('?empresa_id='.$id):'')); exit;
}

if ($id>0){
  $sql = "UPDATE empresas SET
            nome_empresarial=?, nome_fantasia=?, apelido=?, nome_interno=?, cnpj=?,
            cep=?, endereco_logradouro=?, endereco_numero=?, endereco_bairro=?,
            endereco_cidade=?, endereco_uf=?, telefone=?, email=?, ativo=?,
            codigos_integracao=?, updated_at=NOW()
          WHERE id=?";
  $st = $conn->prepare($sql);
  if(!$st){ $_SESSION['flash']['erro']='Erro ao preparar UPDATE: '.$conn->error; header('Location: '.BASE_URL.'/pages/cadastrar_empresa.php?empresa_id='.$id); exit; }
  $st->bind_param(
    'ssssssssssssisssi',
    $nome_empresarial, $nome_fantasia, $apelido, $nome_interno, $cnpj,
    $cep, $logradouro, $numero, $bairro,
    $cidade, $uf, $telefone, $email, $ativo,
    $codigos_json, $id
  );
  $ok = $st->execute(); $st->close();
  $_SESSION['flash'][$ok?'ok':'erro'] = $ok ? 'Empresa atualizada.' : 'Falha ao atualizar.';
  header('Location: '.BASE_URL.'/pages/empresas_listar.php'); exit;

} else {
  $sql = "INSERT INTO empresas
          (nome_empresarial, nome_fantasia, apelido, nome_interno, cnpj,
           cep, endereco_logradouro, endereco_numero, endereco_bairro,
           endereco_cidade, endereco_uf, telefone, email, ativo,
           codigos_integracao, created_at, updated_at)
          VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?, NOW(), NOW())";
  $st = $conn->prepare($sql);
  if(!$st){ $_SESSION['flash']['erro']='Erro ao preparar INSERT: '.$conn->error; header('Location: '.BASE_URL.'/pages/cadastrar_empresa.php'); exit; }
  $st->bind_param(
    'sssssssssssssi s',
    $nome_empresarial, $nome_fantasia, $apelido, $nome_interno, $cnpj,
    $cep, $logradouro, $numero, $bairro,
    $cidade, $uf, $telefone, $email, $ativo,
    $codigos_json
  );
  // ATENÇÃO: alguns PHPs reclamam do espaço em 'si s'. Se der erro,
  // substitua por:
  // $st->bind_param('sssssssssssssis',
  //   ... mesmos parâmetros ...
  // );
  $ok = $st->execute(); $st->close();
  $_SESSION['flash'][$ok?'ok':'erro'] = $ok ? 'Empresa cadastrada.' : 'Falha ao cadastrar.';
  header('Location: '.BASE_URL.'/pages/empresas_listar.php'); exit;
}
