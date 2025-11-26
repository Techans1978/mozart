<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$db = $conn ?? $mysqli ?? null; if(!$db){ die('Sem conexão.'); }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flow = ['id'=>0,'nome'=>'Novo Fluxo','categoria'=>'','descr'=>''];
$lastVersion = null;
if ($id>0){
  $s = $db->prepare("SELECT * FROM moz_flow WHERE id=?");
  $s->bind_param('i',$id); $s->execute();
  $flow = $s->get_result()->fetch_assoc() ?: $flow; $s->close();
  $s = $db->prepare("SELECT * FROM moz_flow_version WHERE flow_id=? ORDER BY created_at DESC LIMIT 1");
  $s->bind_param('i',$id); $s->execute(); $res=$s->get_result();
  $lastVersion = $res->fetch_assoc(); $s->close();
}
$spec_json = $lastVersion ? $lastVersion['spec_json'] : '{"nodes":[{"id":"start","type":"start","position":{"x":60,"y":120},"config":{}},{"id":"end","type":"end","position":{"x":520,"y":120},"config":{}}],"edges":[{"id":"e1","source":"start","target":"end"}]}';
?>
<?php include_once ROOT_PATH . '/system/includes/head.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jointjs/3.7.6/joint.css" />
<style>.canvas{height:70vh;border:1px solid #e1e5ea;border-radius:10px;background:#fafbfc}.palette .btn{text-align:left}.inspector textarea{font-family:ui-monospace, SFMono-Regular, Menlo, monospace}.joint-paper{background-image:linear-gradient(0deg, rgba(0,0,0,.035) 1px, transparent 1px),linear-gradient(90deg, rgba(0,0,0,.035) 1px, transparent 1px);background-size:16px 16px}</style>
<?php include_once ROOT_PATH . '/system/includes/navbar.php'; ?>
<div class="container-fluid mt-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Designer (JointJS) — <?=htmlspecialchars($flow['nome'])?></h4>
    <div class="d-flex gap-2"><button id="btn-run" class="btn btn-primary btn-sm">Executar</button><button id="btn-save" class="btn btn-outline-primary btn-sm">Salvar</button><button id="btn-export" class="btn btn-outline-secondary btn-sm">Exportar</button><label class="btn btn-outline-secondary btn-sm mb-0">Importar <input type="file" id="imp-file" accept="application/json" hidden></label><a href="flows-listar.php" class="btn btn-light btn-sm">Voltar</a></div>
  </div>
  <form id="form-flow" class="row g-3 mb-2" method="post" action="api/flows_save.php">
    <input type="hidden" name="id" value="<?=$flow['id']?>">
    <div class="col-12 col-lg-3"><label class="form-label">Nome</label><input class="form-control" name="nome" value="<?=htmlspecialchars($flow['nome'])?>" required></div>
    <div class="col-6 col-lg-2"><label class="form-label">Categoria</label><input class="form-control" name="categoria" value="<?=htmlspecialchars($flow['categoria'])?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label">Versão</label><input class="form-control" id="flow-version" name="version" value="<?= $lastVersion ? htmlspecialchars($lastVersion['version']) : '1.0.0' ?>"></div>
    <div class="col-12 col-lg-5"><label class="form-label">Descrição</label><input class="form-control" name="descr" value="<?=htmlspecialchars($flow['descr'])?>"></div>
    <input type="hidden" id="flow-spec" name="spec_json" value='<?=$spec_json?>'>
  </form>
  <div class="row">
    <div class="col-12 col-lg-2"><div class="card palette"><div class="card-body small"><h6 class="mb-2">Blocos</h6><div class="d-grid gap-2">
      <button class="btn btn-sm btn-outline-secondary" data-node="start">Start</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="http">HTTP</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="mapper">Mapper</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="switch">Switch</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="ftp">FTP/SFTP</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="email">Email</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="fluig">Fluig</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="script">Script</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="code">Code</button>
      <button class="btn btn-sm btn-outline-secondary" data-node="end">End</button>
    </div></div></div></div>
    <div class="col-12 col-lg-7"><div id="paper" class="canvas"></div></div>
    <div class="col-12 col-lg-3"><div class="card inspector"><div class="card-body small"><h6 class="mb-2">Propriedades</h6><div id="inspector-panel" class="text-muted">Selecione um bloco…</div><hr/><label class="form-label">Vars In (JSON)</label><textarea class="form-control form-control-sm" id="vars-in" rows="6">{}</textarea><div id="run-result" class="mt-2 small"></div></div></div></div>
  </div>
</div>
<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.21/lodash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/backbone.js/1.4.1/backbone-min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jointjs/3.7.6/joint.min.js"></script>
<script>
(function(){
  const graph = new joint.dia.Graph();
  const paper = new joint.dia.Paper({ el: document.getElementById('paper'), model: graph, width: '100%', height: '100%', gridSize: 16, drawGrid: true, defaultLink: function(){ return new joint.shapes.standard.Link({ attrs:{ line:{ strokeWidth:1.5 } } }); } });
  const Node = joint.shapes.standard.Rectangle.define('Mozart.Node', { size:{ width:160, height:56 }, attrs:{ body:{ rx:10, ry:10, strokeWidth:1, stroke:'#C7D2FE', fill:'#EEF2FF' }, label:{ fontSize:12, fontWeight:'600', fill:'#111827', text:'Node' }, sublabel:{ fontSize:10, fill:'#374151' } }, ports:{ groups:{ in:{ position:{ name:'left' }, attrs:{ circle:{ r:4, magnet:'passive', fill:'#A5B4FC' } } }, out:{ position:{ name:'right' }, attrs:{ circle:{ r:4, magnet:true, fill:'#60A5FA' } } } } } }, { markup:[{ tagName:'rect', selector:'body' },{ tagName:'text', selector:'label', attributes:{ 'ref-x':.5,'ref-y':.35,'y-alignment':'middle','x-alignment':'middle' } },{ tagName:'text', selector:'sublabel', attributes:{ 'ref-x':.5,'ref-y':.7,'y-alignment':'middle','x-alignment':'middle' } }] });
  function makeNode(type, x=100, y=100){
    const n = new Node(); n.position(x,y); n.attr('label/text', type.toUpperCase()); if(type==='start') n.attr({body:{fill:'#E0F2FE',stroke:'#7DD3FC'}}); if(type==='end') n.attr({body:{fill:'#DCFCE7',stroke:'#86EFAC'}});
    n.addPort({ group:'in', id:'in' }); n.addPort({ group:'out', id:'out' }); n.set('mozart', { type, config:{} }); n.resize(170,56); return n;
  }
  document.querySelectorAll('[data-node]').forEach(btn=>{ btn.addEventListener('click', ()=>{ const t=btn.getAttribute('data-node'); graph.addCell(makeNode(t, 120+Math.random()*200, 80+Math.random()*160)); }); });

  // Load
  try{
    const spec = JSON.parse(document.getElementById('flow-spec').value || '{}');
    const mapId = {};
    (spec.nodes||[]).forEach((n,i)=>{ const node=makeNode(n.type||'custom', n.position?.x||100, n.position?.y||100); node.set('mozart',{type:n.type, config:n.config||{}}); node.attr('sublabel/text', n.config?.outputVar?('→ '+n.config.outputVar):''); graph.addCell(node); mapId[n.id]=node.id; });
    (spec.edges||[]).forEach((e,i)=>{ const link=new joint.shapes.standard.Link(); link.source({id:mapId[e.source]}); link.target({id:mapId[e.target]}); if(e.label) link.appendLabel({attrs:{text:{text:e.label}}}); graph.addCell(link); });
  }catch(e){}

  const $ins = document.getElementById('inspector-panel');
  function input(k,l,v,ph=''){ return `<label class="form-label mt-2">${l}</label><input data-k="${k}" class="form-control form-control-sm" value="${_.escape(v??'')}" placeholder="${ph}">`; }
  function area(k,l,v){ return `<label class="form-label mt-2">${l}</label><textarea data-k="${k}" class="form-control form-control-sm" rows="4">${_.escape(typeof v==='string'?v:JSON.stringify(v,null,2)||'')}</textarea>`; }
  function renderInspector(cell){ if(!cell||!cell.get('mozart')){ $ins.innerHTML='Selecione um bloco…'; return; } const mz=cell.get('mozart'); const cfg=mz.config||{}; const type=mz.type;
    let html=`<div><span class="badge bg-secondary">${type}</span> <small class="text-muted">(${cell.id})</small></div>`;
    if(type==='http'){ html+=input('connectorVersionId','Connector Version ID',cfg.connectorVersionId||'','12'); html+=area('pathVars','Path Vars (JSON)',cfg.pathVars||{}); html+=input('bodyVar','Body Var',cfg.bodyVar||'','payload'); html+=input('outputVar','Output Var',cfg.outputVar||'resp','resp'); }
    else if(type==='mapper'){ html+=input('sourceVar','Source Var',cfg.sourceVar||'resp'); html+=input('targetVar','Target Var',cfg.targetVar||'mapped'); html+=area('mapping','Mapping (JSONPath map)',cfg.mapping||{"id":"$.id"}); }
    else if(type==='switch'){ html+=input('sourceVar','Source Var',cfg.sourceVar||'resp'); html+=area('branches','Branches JSON',cfg.branches||[{"label":"ok","jsonpath":"$.status","equals":200},{"label":"_","exists":null}]); }
    else if(type==='ftp'){ html+=input('op','Operação',cfg.op||'list','list|get|put|move'); html+=input('proto','Protocolo',cfg.proto||'ftp','ftp|sftp'); html+=input('host','Host',cfg.host||''); html+=input('port','Porta',cfg.port||''); html+=input('user','Usuário',cfg.user||''); html+=input('pass','Senha',cfg.pass||''); html+=input('remote_path','Remote Path',cfg.remote_path||''); html+=input('remote_pathVar','Remote Path (Var)',cfg.remote_pathVar||''); html+=input('fromVar','From (Var)',cfg.fromVar||''); html+=input('toVar','To (Var)',cfg.toVar||''); html+=input('contentVar','Content Var (put)',cfg.contentVar||''); html+=input('outputVar','Output Var',cfg.outputVar||'ftp'); }
    else if(type==='email'){ html+=input('to','Para',cfg.to||''); html+=input('subject','Assunto',cfg.subject||''); html+=area('body','Corpo',cfg.body||'Olá!'); html+=input('from','De',cfg.from||''); html+=area('headers','Headers (JSON)',cfg.headers||{}); html+=input('outputVar','Output Var',cfg.outputVar||'email'); }
    else if(type==='fluig'){ html+=input('baseUrl','Base URL',cfg.baseUrl||''); html+=input('tokenKey','Token Key',cfg.tokenKey||'FLUIG_TOKEN'); html+=input('operation','Operação',cfg.operation||'abrir','abrir|acompanhar|fechar'); html+=input('caseVar','Case Var',cfg.caseVar||'caseId'); html+=input('payloadVar','Payload Var',cfg.payloadVar||'payload'); html+=input('outputVar','Output Var',cfg.outputVar||'fluig'); }
    else if(type==='script'||type==='code'){ html+=input('lang','Linguagem',cfg.lang||(type==='script'?'php':'php')); html+=area('code','Código',cfg.code||'return $vars;'); html+=input('outputVar','Output Var',cfg.outputVar||(type==='script'?'script':'code')); }
    html+='<hr/><div class="text-muted">Opções</div>'+area('runIf','runIf (JSON)',cfg.runIf||{})+input('delayMs','Delay (ms)',cfg.delayMs||'')+area('policy','Policy (JSON)',cfg.policy||{"retry":{"attempts":1,"backoff_ms":300}});
    $ins.innerHTML=html;
    $ins.querySelectorAll('[data-k]').forEach(el=>{ el.addEventListener('input', ()=>{ const k=el.getAttribute('data-k'); let val=el.value; if(['pathVars','headers','mapping','branches','runIf','policy','body'].includes(k)){ try{ val=JSON.parse(el.value);}catch(e){} } const m=cell.get('mozart'); m.config=Object.assign({},m.config,{[k]:val}); cell.set('mozart',m); cell.attr('sublabel/text', m.config?.outputVar?('→ '+m.config.outputVar):''); }); });
  }
  paper.on('element:pointerclick', v=>renderInspector(v.model)); paper.on('blank:pointerclick', ()=>renderInspector(null));
  paper.on('link:pointerdblclick', function(linkView){ const lbl=prompt('Label da conexão (opcional):', linkView.model.labels()?.[0]?.attrs?.text?.text||''); if(lbl!==null){ linkView.model.removeLabels(); if(lbl) linkView.model.appendLabel({attrs:{text:{text:lbl}}}); } });

  function exportSpec(){ const cells=graph.getCells(); const nodes=cells.filter(c=>c.isElement()).map((el,i)=>{ const m=el.get('mozart')||{type:'custom',config:{}}; return { id:'n'+i, type:m.type||'custom', position:el.position(), config:m.config||{} }; }); const map=new Map(); cells.filter(c=>c.isElement()).forEach((el,i)=>map.set(el.id,'n'+i)); const edges=cells.filter(c=>c.isLink()).map((lnk,i)=>({ id:'e'+i, source:map.get(lnk.get('source').id), target:map.get(lnk.get('target').id), label:(lnk.labels()?.[0]?.attrs?.text?.text)||null })); return JSON.stringify({nodes,edges}); }
  window.__mozart_export_spec=exportSpec;

  document.getElementById('btn-export').addEventListener('click', function(){ const blob=new Blob([exportSpec()],{type:'application/json'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='mozart_flow.json'; a.click(); });
  document.getElementById('imp-file').addEventListener('change', function(e){ const f=e.target.files[0]; if(!f) return; const rd=new FileReader(); rd.onload=()=>{ document.getElementById('flow-spec').value=rd.result; alert('Spec carregada no formulário. Clique em Salvar.'); }; rd.readAsText(f); });
  document.getElementById('btn-save').addEventListener('click', function(){ document.getElementById('flow-spec').value=exportSpec(); document.getElementById('form-flow').submit(); });
  document.getElementById('btn-run').addEventListener('click', async function(){ const spec=exportSpec(); const vars=document.getElementById('vars-in').value||'{}'; const form=new FormData(); form.append('spec_json',spec); form.append('vars_json',vars); form.append('env','dev'); const res=await fetch('api/flow_run.php',{method:'POST',body:form}); const out=await res.json(); document.getElementById('run-result').innerHTML='<pre class="bg-light p-2 border rounded">'+JSON.stringify(out,null,2)+'</pre>'; });
})();</script>
