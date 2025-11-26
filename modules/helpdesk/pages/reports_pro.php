<?php
// public/modules/helpdesk/pages/reports_pro.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
include_once ROOT_PATH . '/system/includes/head.php';
require_once ROOT_PATH.'/system/includes/head_hd.php';

proteger_pagina();

$dbc = $conn ?? (isset($mysqli) ? $mysqli : null);
if(!$dbc instanceof mysqli){ die('Sem conexão MySQLi ($conn)'); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Carrega empresas para o filtro (ajuste o nome da tabela se necessário)
$empresas=[]; if($res=$dbc->query("SELECT id,nome FROM empresa ORDER BY nome")){ while($r=$res->fetch_assoc()) $empresas[]=$r; }
?>
<style>
.reports-shell{padding:10px;}
.filters{display:grid; grid-template-columns: repeat(6,minmax(140px,1fr)); gap:10px; align-items:end; margin-bottom:12px;}
.filters .wide{grid-column: span 2;}
.card{background:#fff;border:1px solid #e6e8eb;border-radius:12px;padding:14px; box-shadow:0 1px 3px rgba(0,0,0,.05);}
.grid{display:grid; grid-template-columns: repeat(4,1fr); gap:12px;}
.grid a.card{display:block; text-decoration:none; color:#111;}
.grid h4{margin:0 0 6px 0; font-size:16px;}
.small{font-size:12px; color:#666;}
table { width:100%; border-collapse: collapse; } th, td { padding: 8px; border-bottom: 1px solid #eee; } th { text-align:left; }
#result pre{white-space:pre-wrap; word-break:break-word;}
.tags{display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;}
.tag{font-size:11px; background:#f2f4f7; border:1px solid #e6e8eb; padding:2px 6px; border-radius:999px;}
.btn{display:inline-flex; align-items:center; gap:6px; padding:8px 12px; background:#111827; color:#fff; border:none; border-radius:8px; cursor:pointer;}
.btn:disabled{opacity:.6; cursor:not-allowed;}
</style>

<?php
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<!-- layout -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
	  <!-- Content -->

<div class="container-fluid reports-shell">
  <h2 style="margin:4px 0 12px 0;">Relatórios Pro & Agendamentos</h2>

  <form id="filters" class="filters card" onsubmit="return false;">
    <div><label>De</label><input type="date" name="date_from" class="form-control" value="<?=h(date('Y-m-d', strtotime('-30 days')))?>"></div>
    <div><label>Até</label><input type="date" name="date_to" class="form-control" value="<?=h(date('Y-m-d'))?>"></div>
    <div class="wide">
      <label>Empresas</label>
      <select name="empresa_ids[]" class="form-control" multiple size="3">
        <?php foreach($empresas as $e): ?><option value="<?=$e['id']?>"><?=h($e['nome'])?></option><?php endforeach; ?>
      </select>
      <small class="small">CTRL/Cmd para múltiplas</small>
    </div>
    <div>
      <label>Status</label>
      <select name="status" class="form-control">
        <option value="">Todos</option>
        <option value="aberto">Aberto</option>
        <option value="pendente">Pendente</option>
        <option value="em_andamento">Em andamento</option>
        <option value="resolvido">Resolvido</option>
        <option value="fechado">Fechado</option>
      </select>
    </div>
    <div class="wide">
      <label>Busca (protocolo/assunto/corpo)</label>
      <input type="text" name="q" class="form-control" placeholder="apenas na Busca Inteligente">
    </div>
    <div><button id="btn-run" class="btn" onclick="runSelected()">Executar</button></div>
  </form>

  <div class="grid">
    <?php $items=[
      ['minidash-comparar','Mini-Dash Comparar','Comparativo por empresa: totais, abertos, fechados, SLA'],
      ['busca-inteligente','Busca Inteligente','Pesquisa por protocolo/assunto/corpo'],
      ['listagem-usuarios','Listagem de Usuários','Top solicitantes e métricas'],
      ['metricas-por-categoria','Métricas por Categoria','Totais, fechados e TTR médio (h)'],
      ['listagem-sla','Listagem SLA','Status de SLA por ticket'],
      ['listagem-reaberturas','Listagem Reaberturas','Tickets com reaberturas'],
      ['listagem-backlog','Listagem Backlog','Tickets não fechados no período'],
      ['listagem-sem-atualizacao','Sem Atualização','Tickets parados há N dias'],
      ['listagem-demanda','Demanda (Série)','Aberturas por dia'],
      ['heatmap-aberturas','Heatmap Aberturas','Distribuição por dia/hora'],
      ['relatorio-por-origem','Por Origem','Portal, e-mail, telefone, integração...'],
      ['metricas-por-loja','Métricas por Loja','Totais/fechados/atraso por loja'],
    ]; foreach($items as $it): ?>
      <a class="card" href="#" onclick="loadReport('<?=$it[0]?>');return false;">
        <h4><?=$it[1]?></h4><div class="small"><?=$it[2]?></div>
        <div class="tags"><span class="tag">JSON</span><span class="tag">Export</span><span class="tag">API</span></div>
      </a>
    <?php endforeach; ?>
  </div>

  <div id="result" class="card" style="margin-top:12px;">
    <div class="small">Resultado</div>
    <div id="render"></div>
  </div>

  <div class="card" style="margin-top:12px;">
    <h4 style="margin:0 0 6px 0;">Agendar por e-mail</h4>
    <div class="small">Cole a URL do endpoint para enviar um snapshot agora.</div>
    <form id="emailForm" onsubmit="return false;" style="display:grid; grid-template-columns: 1fr 220px 120px; gap:8px; margin-top:8px;">
      <input type="url" class="form-control" name="url" placeholder="/public/api/hd/reports/minidash-comparar.php?date_from=...&date_to=...">
      <input type="email" class="form-control" name="to" placeholder="destinatario@exemplo.com.br">
      <button class="btn" onclick="sendEmail()">Enviar</button>
    </form>
    <div id="emailStatus" class="small" style="margin-top:8px;"></div>
  </div>
</div>


    <!-- End content -->
      </div>
    </div>
  </div>
</div>
<!-- layout -->

<script>
let currentReport=null;

function formToParams(form){
  const fd=new FormData(form); const arr=[];
  for(const [k,v] of fd.entries()){
    if(k.endsWith('[]')){ const kk=k.slice(0,-2); (fd.getAll(k)||[]).forEach(x=>arr.push([kk,x])); }
    else { if(v!=='') arr.push([k,v]); }
  }
  return new URLSearchParams(arr);
}

function loadReport(name){
  currentReport=name;
  const params=formToParams(document.getElementById('filters'));
  const url=`/public/api/hd/reports/${name.replaceAll('_','-')}.php?`+params.toString();
  fetch(url,{credentials:'same-origin'})
    .then(r=>r.json())
    .then(j=>renderJSON(j,url))
    .catch(e=>{ document.getElementById('render').innerHTML='<pre>'+e+'</pre>'; });
}

function renderJSON(json,url){
  let html=''; html+=`<div class="small" style="margin-bottom:6px;">Fonte: <code>${url}</code></div>`;
  html+=`<pre>${JSON.stringify(json,null,2)}</pre>`;
  document.getElementById('render').innerHTML=html;
}

function runSelected(){ if(!currentReport){ alert('Clique em um relatório.'); return; } loadReport(currentReport); }

function sendEmail(){
  const f=document.getElementById('emailForm'); const url=f.url.value.trim(); const to=f.to.value.trim();
  if(!url||!to){ alert('Informe URL e e-mail.'); return; }
  const call=`/public/api/hd/cron/report_email.php?to=${encodeURIComponent(to)}&url=${encodeURIComponent(url)}`;
  document.getElementById('emailStatus').textContent='Enviando...';
  fetch(call,{credentials:'same-origin'}).then(r=>r.json()).then(j=>{
    document.getElementById('emailStatus').textContent=j.ok?'Enviado.':'Falhou: '+(j.error||'erro');
  }).catch(e=> document.getElementById('emailStatus').textContent='Erro: '+e);
}
</script>

<?php require_once ROOT_PATH . '/system/includes/footer_hd.php'; ?>
