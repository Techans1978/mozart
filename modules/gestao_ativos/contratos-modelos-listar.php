<?php
// public/modules/gestao_ativos/contratos-modelos-listar.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start(); proteger_pagina();
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

function migrate(mysqli $db){
  $db->query("CREATE TABLE IF NOT EXISTS moz_contrato_template (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    conteudo LONGTEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // seed modelo "cessao-equipamento" se não existir
  $slug='cessao-equipamento';
  $ck=$db->prepare("SELECT id FROM moz_contrato_template WHERE slug=?");
  $ck->bind_param('s',$slug); $ck->execute(); $ck->bind_result($ex); $ck->fetch(); $ck->close();
  if(!$ex){
    $nome='Termo de Cessão de Uso de Equipamento (Comodato)';
    $conteudo = <<<TXT
TERMO DE CESSÃO DE USO DE EQUIPAMENTO EM COMODATO

CEDENTE: {empresa}, com sede em {endereco_empresa}, inscrita no CNPJ sob o n.º {cnpj_empresa}, representada neste ato por {representante_legal} ({cargo_representante}), doravante denominada CEDENTE.
CESSIONÁRIO: {nome_colaborador}, {nacionalidade_colaborador}, {estado_civil_colaborador}, {profissao_colaborador}, RG/CPF {doc_colaborador}, residente em {endereco_colaborador}, doravante denominado CESSIONÁRIO.

CLÁUSULA PRIMEIRA – DO OBJETO
1.1. Cessão, em comodato gratuito, do(s) equipamento(s) descrito(s): {ativo_categoria} {ativo_modelo} (TAG {ativo_tag}, Série {ativo_serie}), pertencente(s) à CEDENTE, para uso no desempenho das funções do CESSIONÁRIO.

CLÁUSULA SEGUNDA – DA VIGÊNCIA
2.1. Vigência: {vigencia_prazo} a contar de {vigencia_inicio}, com término em {vigencia_fim}, podendo ser prorrogada por escrito.

CLÁUSULA TERCEIRA – OBRIGAÇÕES DO CESSIONÁRIO
3.1. Utilizar o(s) equipamento(s) somente para fins profissionais; zelar pela guarda e conservação; não ceder a terceiros; responder por danos decorrentes de mau uso.

CLÁUSULA QUARTA – DA DEVOLUÇÃO
4.1. Ao término da vigência, por solicitação da CEDENTE ou rescisão contratual, devolver o(s) equipamento(s) nas mesmas condições, ressalvada depreciação natural.

CLÁUSULA QUINTA – DA RESCISÃO
5.1. O presente Termo poderá ser rescindido pela CEDENTE a qualquer tempo, a seu exclusivo critério.

CLÁUSULA SEXTA – DO FORO
6.1. Fica eleito o foro da Comarca de {cidade}/{estado}.

E, por estarem de acordo, firmam o presente Termo em 2 (duas) vias de igual teor.
{cidade}, {data_extenso}.

CEDENTE: {empresa}
CESSIONÁRIO: {nome_colaborador}
TXT;
    $st=$db->prepare("INSERT INTO moz_contrato_template (nome,slug,conteudo,ativo) VALUES (?,?,?,1)");
    $st->bind_param('sss',$nome,$slug,$conteudo); $st->execute(); $st->close();
  }
}
migrate($dbc);

// ações simples
if(isset($_GET['toggle']) && ($id=(int)$_GET['toggle'])){
  $dbc->query("UPDATE moz_contrato_template SET ativo=1-ativo WHERE id=".$id);
  header('Location: contratos-modelos-listar.php'); exit;
}
if(isset($_GET['del']) && ($id=(int)$_GET['del'])){
  $dbc->query("DELETE FROM moz_contrato_template WHERE id=".$id);
  header('Location: contratos-modelos-listar.php'); exit;
}

$busca = trim($_GET['q'] ?? '');
$where = $busca ? "WHERE nome LIKE '%".$dbc->real_escape_string($busca)."%'" : '';
$res = $dbc->query("SELECT * FROM moz_contrato_template $where ORDER BY created_at DESC");
$rows=[]; if($res) while($r=$res->fetch_assoc()) $rows[]=$r;

include_once ROOT_PATH.'system/includes/head.php';
include_once ROOT_PATH.'system/includes/navbar.php';
?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header">Modelos de contrato</h1></div></div>

  <div class="card">
    <form class="row" style="gap:8px" method="get">
      <input class="form-control" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="buscar por nome">
      <a class="btn btn-success" href="contrato-editar.php">+ Novo modelo</a>
      <button class="btn btn-default">Buscar</button>
    </form>
  </div>

  <?php if(!$rows): ?>
    <div class="alert alert-info">Nenhum modelo.</div>
  <?php else: ?>
    <div class="panel panel-default">
      <div class="panel-heading">Modelos</div>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>ID</th><th>Nome</th><th>Slug</th><th>Ativo</th><th>Criado</th><th>Ações</th></tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= htmlspecialchars($r['nome']) ?></td>
              <td><code><?= htmlspecialchars($r['slug']) ?></code></td>
              <td><?= $r['ativo']?'Sim':'Não' ?></td>
              <td><?= htmlspecialchars($r['created_at']) ?></td>
              <td>
                <a class="btn btn-xs btn-primary" href="contrato-editar.php?id=<?= (int)$r['id'] ?>">Editar</a>
                <a class="btn btn-xs btn-warning" href="?toggle=<?= (int)$r['id'] ?>">Ativar/Inativar</a>
                <a class="btn btn-xs btn-danger" onclick="return confirm('Excluir modelo?')" href="?del=<?= (int)$r['id'] ?>">Excluir</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div></div>
<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH.'system/includes/footer.php'; ?>
