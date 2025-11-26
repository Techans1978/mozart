<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$dbc = $conn ?? null; if(!$dbc) die('Sem conexão.');

if (empty($_SESSION['csrf_dep'])) $_SESSION['csrf_dep']=bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_dep'];

$id = (int)($_GET['id'] ?? 0);
$err = ''; $ok='';

/* Carrega empresas (Entidade) p/ o select */
$empresas=[];
$re=$dbc->query("SELECT id, COALESCE(NULLIF(nome_fantasia,''), nome_empresarial) AS nome
                 FROM empresas WHERE ativo=1 ORDER BY nome");
if($re) while($r=$re->fetch_assoc()) $empresas[]=$r;

/* Defaults */
$dep = [
  'empresa_id'=>'', 'nome'=>'', 'status'=>1, 'codigo'=>'', 'tipo'=>'Geral', 'responsavel'=>'',
  'cep'=>'','logradouro'=>'','numero'=>'','complemento'=>'','bairro'=>'','municipio'=>'','uf'=>'',
  'telefone'=>'','email'=>'',
  'horario'=>'','politica'=>'FIFO','capacidade_txt'=>'','categorias_permitidas'=>'',
  'estoque_min'=>'','estoque_max'=>'','observacoes'=>'',
  'zonas'=>[]
];

/* POST = salvar */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) die('CSRF inválido.');

  $id           = (int)($_POST['id'] ?? 0);
  $empresa_id   = (int)($_POST['empresa_id'] ?? 0);
  $nome         = trim($_POST['nome'] ?? '');
  $status       = (int)($_POST['status'] ?? 1);
  $codigo       = trim($_POST['codigo'] ?? '');
  $tipo         = in_array($_POST['tipo'] ?? 'Geral',['Geral','Peças','Consumíveis','Ativos de TI']) ? $_POST['tipo'] : 'Geral';
  $responsavel  = trim($_POST['responsavel'] ?? '');

  $cep=trim($_POST['cep'] ?? ''); $logradouro=trim($_POST['logradouro'] ?? ''); $numero=trim($_POST['numero'] ?? '');
  $complemento=trim($_POST['complemento'] ?? ''); $bairro=trim($_POST['bairro'] ?? ''); $municipio=trim($_POST['municipio'] ?? '');
  $uf=trim($_POST['uf'] ?? ''); $telefone=trim($_POST['telefone'] ?? ''); $email=trim($_POST['email'] ?? '');

  $horario=trim($_POST['horario'] ?? ''); $politica=in_array($_POST['politica'] ?? 'FIFO',['FIFO','FEFO','LIFO'])?$_POST['politica']:'FIFO';
  $capacidade_txt=trim($_POST['capacidade_txt'] ?? ''); $categorias_permitidas=trim($_POST['categorias_permitidas'] ?? '');
  $estoque_min=($_POST['estoque_min']!=='')?(int)$_POST['estoque_min']:null;
  $estoque_max=($_POST['estoque_max']!=='')?(int)$_POST['estoque_max']:null;

  $observacoes = trim($_POST['observacoes'] ?? '');

  try {
    if ($empresa_id<=0) throw new Exception('Selecione a Empresa/Entidade.');
    if ($nome==='')      throw new Exception('Informe o nome do depósito.');

    $dbc->begin_transaction();

    if ($id>0){
      $sql="UPDATE moz_deposito SET empresa_id=?, nome=?, status=?, codigo=?, tipo=?, responsavel=?,
            cep=?, logradouro=?, numero=?, complemento=?, bairro=?, municipio=?, uf=?, telefone=?, email=?,
            horario=?, politica=?, capacidade_txt=?, categorias_permitidas=?, estoque_min=?, estoque_max=?, observacoes=?
            WHERE id=?";
      $st=$dbc->prepare($sql);
      $st->bind_param('isisssssssssssssssssissi',
        $empresa_id,$nome,$status,$codigo,$tipo,$responsavel,
        $cep,$logradouro,$numero,$complemento,$bairro,$municipio,$uf,$telefone,$email,
        $horario,$politica,$capacidade_txt,$categorias_permitidas,$estoque_min,$estoque_max,$observacoes,$id
      );
      $st->execute(); $st->close();

      // Zonas (remove e reinsere)
      $dbc->query("DELETE FROM moz_deposito_zona WHERE deposito_id=".$id);
      if (!empty($_POST['zona']) && is_array($_POST['zona'])){
        $ins=$dbc->prepare("INSERT INTO moz_deposito_zona (deposito_id,zona,rua,colunas_niveis) VALUES (?,?,?,?)");
        $Z=$_POST['zona']; $R=$_POST['rua']; $C=$_POST['colunas_niveis'];
        for($i=0;$i<count($Z);$i++){
          $z=trim((string)$Z[$i]); $r=trim((string)($R[$i]??'')); $c=trim((string)($C[$i]??''));
          if($z==='' && $r==='' && $c==='') continue;
          $ins->bind_param('isss',$id,$z,$r,$c); $ins->execute();
        }
        $ins->close();
      }
    } else {
      $sql="INSERT INTO moz_deposito (empresa_id,nome,status,codigo,tipo,responsavel,
            cep,logradouro,numero,complemento,bairro,municipio,uf,telefone,email,
            horario,politica,capacidade_txt,categorias_permitidas,estoque_min,estoque_max,observacoes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
      $st=$dbc->prepare($sql);
      $st->bind_param('isisssssssssssssssssis',
        $empresa_id,$nome,$status,$codigo,$tipo,$responsavel,
        $cep,$logradouro,$numero,$complemento,$bairro,$municipio,$uf,$telefone,$email,
        $horario,$politica,$capacidade_txt,$categorias_permitidas,$estoque_min,$estoque_max,$observacoes
      );
      $st->execute(); $id=$st->insert_id; $st->close();

      // Zonas
      if (!empty($_POST['zona']) && is_array($_POST['zona'])){
        $ins=$dbc->prepare("INSERT INTO moz_deposito_zona (deposito_id,zona,rua,colunas_niveis) VALUES (?,?,?,?)");
        $Z=$_POST['zona']; $R=$_POST['rua']; $C=$_POST['colunas_niveis'];
        for($i=0;$i<count($Z);$i++){
          $z=trim((string)$Z[$i]); $r=trim((string)($R[$i]??'')); $c=trim((string)($C[$i]??''));
          if($z==='' && $r==='' && $c==='') continue;
          $ins->bind_param('isss',$id,$z,$r,$c); $ins->execute();
        }
        $ins->close();
      }
    }

    $dbc->commit();
    $_SESSION['flash_ok']='Depósito salvo.';
    header('Location: '.BASE_URL.'/modules/gestao_ativos/depositos-listar.php'); exit;

  } catch (Exception $e){
    $dbc->rollback();
    $err = $e->getMessage();
    // devolve campos ao form
    foreach($dep as $k=>$_) if(isset($_POST[$k])) $dep[$k]=$_POST[$k];
  }
}

/* GET: carregar para edição */
if ($id>0 && $err===''){
  $st=$dbc->prepare("SELECT * FROM moz_deposito WHERE id=?");
  $st->bind_param('i',$id); $st->execute();
  $res=$st->get_result()->fetch_assoc(); $st->close();
  if($res) foreach($dep as $k=>$_){ if(array_key_exists($k,$res)) $dep[$k]=$res[$k]; }

  $dep['zonas']=[];
  $rz=$dbc->query("SELECT zona,rua,colunas_niveis FROM moz_deposito_zona WHERE deposito_id=".(int)$id." ORDER BY id");
  if($rz) while($z=$rz->fetch_assoc()) $dep['zonas'][]=$z;
}
?>
<?php include_once ROOT_PATH.'system/includes/head.php'; ?>
<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">
<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<div id="page-wrapper"><div class="container-fluid">
  <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

  <div class="row"><div class="col-lg-12">
<session class="bpm"><div class="container">

  <header class="toolbar">
    <h1>Depósitos — Cadastro</h1>
    <div class="actions">
      <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/depositos-listar.php">Listar depósitos</a>
    </div>
  </header>

  <?php if($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
  <?php if(!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>

  <form class="card" method="post" autocomplete="off" novalidate>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <p class="subtitle">Identificação</p>
    <div class="grid cols-3">
      <div>
        <label>Empresa/Entidade *</label>
        <select name="empresa_id" required>
          <option value="">— selecione —</option>
          <?php foreach($empresas as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= (int)$dep['empresa_id']===(int)$e['id']?'selected':'' ?>>
              <?= h($e['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Nome do depósito *</label><input type="text" name="nome" required placeholder="Depósito Central" value="<?= h($dep['nome']) ?>"/></div>
      <div><label>Status *</label>
        <select name="status"><option value="1" <?= $dep['status']? 'selected':'' ?>>Ativo</option><option value="0" <?= !$dep['status']? 'selected':'' ?>>Inativo</option></select>
      </div>
    </div>

    <div class="grid cols-3">
      <div><label>Código interno</label><input type="text" name="codigo" placeholder="SIGLA/ID" value="<?= h($dep['codigo']) ?>"/></div>
      <div><label>Tipo</label>
        <select name="tipo">
          <?php foreach(['Geral','Peças','Consumíveis','Ativos de TI'] as $t): ?>
            <option <?= $dep['tipo']===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Responsável</label><input type="text" name="responsavel" placeholder="Nome/usuário" value="<?= h($dep['responsavel']) ?>"/></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Endereço & contato</p>
    <div class="grid cols-4">
      <div><label>CEP</label><input type="text" name="cep" placeholder="00000-000" value="<?= h($dep['cep']) ?>"/></div>
      <div class="cols-span-3"><label>Logradouro</label><input type="text" name="logradouro" placeholder="Rua/Avenida" value="<?= h($dep['logradouro']) ?>"/></div>
    </div>
    <div class="grid cols-4">
      <div><label>Número</label><input type="text" name="numero" placeholder="S/N" value="<?= h($dep['numero']) ?>"/></div>
      <div><label>Complemento</label><input type="text" name="complemento" placeholder="Galpão, doca..." value="<?= h($dep['complemento']) ?>"/></div>
      <div><label>Bairro</label><input type="text" name="bairro" value="<?= h($dep['bairro']) ?>"/></div>
      <div><label>Município</label><input type="text" name="municipio" value="<?= h($dep['municipio']) ?>"/></div>
    </div>
    <div class="grid cols-3">
      <div><label>UF</label><input type="text" name="uf" placeholder="SP, RJ..." value="<?= h($dep['uf']) ?>"/></div>
      <div><label>Telefone</label><input type="tel" name="telefone" placeholder="(11) 4000-0000" value="<?= h($dep['telefone']) ?>"/></div>
      <div><label>E-mail</label><input type="email" name="email" placeholder="estoque@empresa.com.br" value="<?= h($dep['email']) ?>"/></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Operação</p>
    <div class="grid cols-3">
      <div><label>Horário de funcionamento</label><input type="text" name="horario" placeholder="Seg–Sex 08:00–17:00" value="<?= h($dep['horario']) ?>"/></div>
      <div><label>Política de picking</label>
        <select name="politica"><?php foreach(['FIFO','FEFO','LIFO'] as $p): ?><option <?= $dep['politica']===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select>
      </div>
      <div><label>Capacidade (m³) / posições</label><input type="text" name="capacidade_txt" placeholder="Ex.: 120 / 300 posições" value="<?= h($dep['capacidade_txt']) ?>"/></div>
    </div>
    <div class="grid cols-3">
      <div><label>Categorias permitidas</label><input type="text" name="categorias_permitidas" placeholder="Ex.: Consumíveis; Peças" value="<?= h($dep['categorias_permitidas']) ?>"/></div>
      <div><label>Estoque mínimo (alerta)</label><input type="number" name="estoque_min" min="0" value="<?= h($dep['estoque_min']) ?>"/></div>
      <div><label>Estoque máximo</label><input type="number" name="estoque_max" min="0" value="<?= h($dep['estoque_max']) ?>"/></div>
    </div>

    <div class="divider"></div>
    <p class="subtitle">Zonas e endereçamento (opcional)</p>
    <div id="zonas" class="stack">
      <?php if(!$dep['zonas']) $dep['zonas']=[['zona'=>'','rua'=>'','colunas_niveis'=>'']]; ?>
      <?php foreach($dep['zonas'] as $z): ?>
        <div class="grid cols-4" style="align-items:end">
          <div><label>Zona/Área</label><input type="text" name="zona[]" placeholder="Ex.: Zona A" value="<?= h($z['zona']) ?>"/></div>
          <div><label>Rua/Corredor</label><input type="text" name="rua[]" placeholder="Ex.: Rua 01" value="<?= h($z['rua']) ?>"/></div>
          <div><label>Colunas x Níveis</label><input type="text" name="colunas_niveis[]" placeholder="Ex.: 10 x 4" value="<?= h($z['colunas_niveis']) ?>"/></div>
          <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn small" id="add-zona">+ Adicionar zona/rua</button>

    <div class="divider"></div>
    <p class="subtitle">Observações</p>
    <textarea name="observacoes" placeholder="Regras de segurança, EPIs, docas, acesso etc."><?= h($dep['observacoes']) ?></textarea>

    <div class="divider"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a class="btn" href="<?= BASE_URL ?>/modules/gestao_ativos/depositos-listar.php">Cancelar</a>
      <button class="btn primary">Salvar</button>
    </div>
  </form>

</div></session>
  </div></div>
</div></div>

<?php include_once ROOT_PATH.'system/includes/code_footer.php'; ?>
<script>
document.getElementById('add-zona').addEventListener('click',()=>{
  const w=document.getElementById('zonas');
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`<div><label>Zona/Área</label><input type="text" name="zona[]" placeholder="Ex.: Zona A"/></div>
                <div><label>Rua/Corredor</label><input type="text" name="rua[]" placeholder="Ex.: Rua 01"/></div>
                <div><label>Colunas x Níveis</label><input type="text" name="colunas_niveis[]" placeholder="Ex.: 10 x 4"/></div>
                <div class="row"><button type="button" class="btn small danger" onclick="this.closest('.grid').remove()">Remover</button></div>`;
  w.appendChild(el);
});
</script>
<?php include_once ROOT_PaTH.'system/includes/footer.php'; /* cuidado com o case */ ?>
