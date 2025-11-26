<?php
// modules/helpdesk/pages/admin/criar_script.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/head_hd.php';
proteger_pagina();

$dbc = $conn ?? $mysqli ?? null;
if(!$dbc || !($dbc instanceof mysqli)) die('Sem conexão MySQLi.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ok = $err = null;

// Handle SAVE
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao']) && $_POST['acao']==='salvar'){
  $titulo     = trim($_POST['titulo'] ?? '');
  $descricao  = trim($_POST['descricao'] ?? '');
  $categoria  = trim($_POST['categoria'] ?? '');
  $status     = in_array(($_POST['status'] ?? 'rascunho'), ['rascunho','publicado','arquivado']) ? $_POST['status'] : 'rascunho';
  $xml        = $_POST['xml'] ?? '';
  $user_id    = $_SESSION['usuario_id'] ?? null;

  if($titulo==='' || $xml===''){
    $err = 'Título e XML são obrigatórios.';
  } else {
    $stmt = $dbc->prepare("INSERT INTO hd_scripts (titulo, descricao, categoria, xml, status, criado_por) VALUES (?,?,?,?,?,?)");
    if(!$stmt){ $err='Erro prepare: '.$dbc->error; }
    else{
      $stmt->bind_param('sssssi', $titulo, $descricao, $categoria, $xml, $status, $user_id);
      if($stmt->execute()){
        $ok = 'Script salvo com sucesso (#'.$stmt->insert_id.').';
      } else {
        $err = 'Erro ao salvar: '.$stmt->error;
      }
      $stmt->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Criar Script (Q&A -> XML) — Help Desk</title>
  <style>
    .shell{display:grid;grid-template-columns:1fr 420px;gap:18px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .card h3{margin:0;padding:14px 16px;border-bottom:1px solid #eee}
    .card .body{padding:14px 16px}
    .row{display:flex;gap:10px;align-items:center;margin-bottom:8px}
    .row input[type=text], .row select, textarea, input[type=text].full{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px}
    .q-list{max-height:56vh;overflow:auto;padding-right:6px}
    .pill{padding:2px 8px;border:1px solid #ddd;border-radius:999px;font-size:12px;background:#fafafa}
    .btn{padding:8px 12px;border:1px solid #ccc;border-radius:10px;background:#f7f7f7;cursor:pointer}
    .btn.primary{background:#2563eb;border-color:#1e40af;color:#fff}
    .btn.ghost{background:#fff}
    .btn.danger{background:#ef4444;border-color:#b91c1c;color:#fff}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .toolbar{display:flex;gap:8px;flex-wrap:wrap}
    code.k{background:#f3f4f6;border:1px solid #e5e7eb;padding:2px 6px;border-radius:6px}
    .mini{font-size:12px;color:#6b7280}
  </style>
</head>
<body>
<?php include ROOT_PATH.'/system/includes/navbar.php'; ?>
<div class="container" style="padding:18px 20px;">
  <h2 style="margin:0 0 14px;">Help Desk — Criador de Scripts (Perguntas → XML)</h2>

  <?php if($ok): ?><div class="alert alert-success"><?=h($ok)?></div><?php endif; ?>
  <?php if($err): ?><div class="alert alert-danger"><?=h($err)?></div><?php endif; ?>

  <form method="post" id="form-salvar">
    <input type="hidden" name="acao" value="salvar">
    <div class="shell">
      <div class="card">
        <h3>Editor de Perguntas</h3>
        <div class="body">
          <div class="row">
            <input type="text" name="titulo" placeholder="Título do script (ex.: Triagem de Incidentes - Loja)" required>
          </div>
          <div class="row">
            <input type="text" name="categoria" placeholder="Categoria (ex.: suporte/loja)">
            <select name="status" style="max-width:220px">
              <option value="rascunho">Rascunho</option>
              <option value="publicado">Publicado</option>
              <option value="arquivado">Arquivado</option>
            </select>
          </div>
          <div class="row">
            <input type="text" class="full" name="descricao" placeholder="Descrição (opcional)">
          </div>

          <div class="toolbar" style="margin:10px 0 6px;">
            <button type="button" class="btn" onclick="addPergunta()">+ Adicionar pergunta</button>
            <button type="button" class="btn" onclick="addPergunta('Qual o seu problema?','texto',1)">Pergunta padrão</button>
            <span class="pill">Tipos: texto, numero, data, select, checkbox, email, phone, textarea</span>
          </div>

          <div id="lista" class="q-list"></div>

          <div class="toolbar" style="margin-top:12px;">
            <button type="button" class="btn" onclick="gerarXML()">Gerar XML</button>
            <button type="button" class="btn" onclick="montarIA()">Montar por IA (heurística)</button>
            <button type="button" class="btn ghost" onclick="baixarXML()">Baixar XML</button>
            <button type="button" class="btn danger" onclick="limpar()">Limpar</button>
          </div>
        </div>
      </div>

      <div class="card">
        <h3>XML Gerado</h3>
        <div class="body">
          <textarea name="xml" id="xml" rows="20" placeholder="XML aparecerá aqui"></textarea>
          <div class="mini" style="margin-top:6px;">
            Exemplo de nó: <code class="k">&lt;quest ordem="1" tipo="texto" obrigatoria="1"&gt;Qual o seu problema?&lt;/quest&gt;</code>
          </div>
          <div class="toolbar" style="margin-top:10px;">
            <button class="btn primary" type="submit">Salvar</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
let ordem = 0;

function tplItem(q='', tipo='texto', obrig=1, opcoes=''){
  ordem++;
  return `
    <div class="card" data-item style="margin:8px 0;">
      <div class="body">
        <div class="row">
          <span class="pill">#${ordem}</span>
          <input type="text" placeholder="Pergunta" value="${q.replaceAll('"','&quot;')}" data-q>
        </div>
        <div class="row">
          <select data-tipo>
            ${['texto','numero','data','select','checkbox','email','phone','textarea'].map(t => 
              `<option value="${t}" ${t===tipo?'selected':''}>${t}</option>`).join('')}
          </select>
          <label style="display:flex;align-items:center;gap:6px">
            <input type="checkbox" ${obrig? 'checked':''} data-obrig> Obrigatória
          </label>
          <input type="text" placeholder="Opções (para select: valor|rótulo;...)" value="${opcoes||''}" data-opcoes style="flex:1">
          <button type="button" class="btn" onclick="this.closest('[data-item]').remove()">Remover</button>
        </div>
      </div>
    </div>
  `;
}

function addPergunta(q='',tipo='texto',obrig=1){
  document.querySelector('#lista').insertAdjacentHTML('beforeend', tplItem(q,tipo,obrig,''));
}

function coletar(){
  const items = Array.from(document.querySelectorAll('[data-item]'));
  return items.map((el,i) => {
    return {
      ordem: i+1,
      q: el.querySelector('[data-q]').value.trim(),
      tipo: el.querySelector('[data-tipo]').value,
      obrig: el.querySelector('[data-obrig]').checked ? 1 : 0,
      opcoes: el.querySelector('[data-opcoes]').value.trim()
    };
  }).filter(x => x.q !== '');
}

function gerarXML(){
  const qs = coletar();
  const meta = `  <meta versao="1" origem="Mozart" tipo="helpdesk"/>\n`;
  const body = qs.map(x=>{
    let extras = '';
    if(x.tipo==='select' && x.opcoes){
      // opções como <opt value="a">A</opt>
      const opts = x.opcoes.split(';').map(p=>{
        const [v,r] = p.split('|');
        return `      <opt value="${(v||'').trim()}">${(r||v||'').trim()}</opt>`;
      }).join('\n');
      extras = `\n    <options>\n${opts}\n    </options>`;
    }
    return `    <quest ordem="${x.ordem}" tipo="${x.tipo}" obrigatoria="${x.obrig}">${x.q}</quest>\n    <answer/>${extras}`;
  }).join('\n');

  const xml = `<script>\n${meta}  <flow>\n${body}\n  </flow>\n</script>`;
  document.querySelector('#xml').value = xml;
}

function montarIA(){
  // Heurística local: se tiver "problema", sugere coletar ambiente, impacto, anexo, prioridade etc.
  const base = [
    ['Qual o seu problema?','textarea',1],
    ['Quando começou a ocorrer?','data',0],
    ['Impacto (na operação)','select',1,'baixo|Baixo;médio|Médio;alto|Alto;crítico|Crítico'],
    ['Qual sistema/equipamento?','texto',1],
    ['Já tentou alguma solução? Qual?','textarea',0],
    ['Anexar evidência (link/ID)','texto',0],
    ['Prioridade sugerida','select',0,'baixa|Baixa;normal|Normal;alta|Alta'],
    ['Contato para retorno (telefone ou ramal)','texto',1]
  ];
  base.forEach(([q,t,o,ops])=> document.querySelector('#lista').insertAdjacentHTML('beforeend', tplItem(q,t,o,ops||'')));
}

function baixarXML(){
  gerarXML();
  const blob = new Blob([document.querySelector('#xml').value], {type:'application/xml'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'script.xml';
  a.click();
}

function limpar(){
  document.querySelector('#lista').innerHTML = '';
  document.querySelector('#xml').value = '';
  ordem = 0;
}
</script>
</body>
</html>
