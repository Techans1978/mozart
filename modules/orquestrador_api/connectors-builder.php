<?php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();
$db = $conn ?? $mysqli ?? null; if(!$db){ die('Sem conexão.'); }
$connector_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cv_id = isset($_GET['cv']) ? (int)$_GET['cv'] : 0;
$connector = ['id'=>0,'nome'=>'','display_name'=>'','image_url'=>'','categoria'=>'','descr'=>''];
$cv = ['id'=>0,'connector_id'=>0,'version'=>'1.0.0','method'=>'GET','base_url'=>'','path_tpl'=>'','headers_json'=>'{}','policy_json'=>'{}','mapping_out_json'=>'{}','status'=>'draft'];
if ($connector_id>0){ $s=$db->prepare("SELECT * FROM api_connector WHERE id=?"); $s->bind_param('i',$connector_id); $s->execute(); $r=$s->get_result()->fetch_assoc(); if($r) $connector=$r; $s->close(); }
if ($cv_id>0){ $s=$db->prepare("SELECT * FROM api_connector_version WHERE id=?"); $s->bind_param('i',$cv_id); $s->execute(); $r=$s->get_result()->fetch_assoc(); if($r) $cv=$r; $s->close(); }
?>
<?php include_once ROOT_PATH . '/system/includes/head.php'; ?>
<style>.pm-input{border-radius:8px}.pm-tab{font-weight:600}.pm-params td,.pm-headers td{vertical-align:middle}.sticky-actions{position:sticky;top:64px;z-index:10;background:#fff;padding:8px 0}</style>
<?php include_once ROOT_PATH . '/system/includes/navbar.php'; ?>
<div class="container-fluid mt-3">
  <div class="sticky-actions d-flex align-items-center gap-2">
    <select id="method" class="form-select form-select-sm" style="flex:0 0 110px;"><?php foreach(['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS'] as $m): ?><option <?= $cv['method']===$m?'selected':''?>><?=$m?></option><?php endforeach; ?></select>
    <input id="url" class="form-control form-control-sm pm-input" placeholder="Enter URL or paste text" value="<?=htmlspecialchars(($cv['base_url']? rtrim($cv['base_url'],'/'): '').'/'.ltrim($cv['path_tpl'],'/'), ENT_QUOTES)?>">
    <button id="btn-send" class="btn btn-primary btn-sm">Send</button>
    <div class="ms-auto"></div>
    <button id="btn-ai-test" class="btn btn-outline-secondary btn-sm">Testar com IA</button>
    <button id="btn-ai-build" class="btn btn-outline-secondary btn-sm">Construir com IA</button>
    <button id="btn-save-conn" class="btn btn-success btn-sm">Salvar / Criar Conector</button>
  </div>
  <div class="row mt-2">
    <div class="col-12 col-lg-9">
      <ul class="nav nav-tabs pm-tab" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-params">Params</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-auth">Authorization</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-headers">Headers</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-body">Body</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-scripts">Scripts</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-tests">Tests</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-settings">Settings</a></li>
      </ul>
      <div class="tab-content border border-top-0 rounded-bottom p-3">
        <div class="tab-pane fade show active" id="tab-params">
          <table class="table table-sm table-hover pm-params"><thead><tr><th style="width:30%">Key</th><th style="width:35%">Value</th><th>Description</th><th style="width:40px"></th></tr></thead><tbody id="params-tbody"></tbody></table>
          <button class="btn btn-link btn-sm" id="params-add">+ Add param</button>
        </div>
        <div class="tab-pane fade" id="tab-auth">
          <div class="row g-3"><div class="col-12 col-md-4"><label class="form-label">Auth Type</label>
            <select id="auth-type" class="form-select form-select-sm">
              <option>No Auth</option><option>Basic Auth</option><option>Bearer Token</option><option>JWT Bearer</option><option>Digest Auth</option><option>OAuth 1.0</option><option>OAuth 2.0</option><option>Hawk Authentication</option><option>AWS Signature</option><option>NTLM Authentication</option><option>API Key</option><option>Akamai EdgeGrid</option><option>ASAP (Atlassian)</option>
            </select></div>
            <div class="col-12 col-md-8" id="auth-fields"><div class="text-muted">Select an auth type…</div></div>
          </div>
        </div>
        <div class="tab-pane fade" id="tab-headers">
          <table class="table table-sm table-hover pm-headers"><thead><tr><th style="width:30%">Key</th><th style="width:35%">Value</th><th>Description</th><th style="width:40px"></th></tr></thead><tbody id="headers-tbody"></tbody></table>
          <button class="btn btn-link btn-sm" id="headers-add">+ Add header</button>
        </div>
        <div class="tab-pane fade" id="tab-body">
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="bodyMode" value="none" checked><label class="form-check-label">none</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="bodyMode" value="form-data"><label class="form-check-label">form-data</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="bodyMode" value="x-www-form-urlencoded"><label class="form-check-label">x-www-form-urlencoded</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="bodyMode" value="raw"><label class="form-check-label">raw</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="bodyMode" value="binary"><label class="form-check-label">binary</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="bodyMode" value="graphql"><label class="form-check-label">GraphQL</label></div>
          <div id="body-area" class="mt-2"><textarea id="body-raw" class="form-control form-control-sm" rows="8" placeholder='{"name":"Mozart"}'></textarea></div>
        </div>
        <div class="tab-pane fade" id="tab-scripts">
          <div class="row g-3"><div class="col-12 col-lg-6"><label class="form-label">Pre-request</label><textarea id="script-pre" class="form-control form-control-sm" rows="8" placeholder="// JS pre-request"></textarea></div><div class="col-12 col-lg-6"><label class="form-label">Post-response</label><textarea id="script-post" class="form-control form-control-sm" rows="8" placeholder="// JS post-response"></textarea></div></div>
        </div>
        <div class="tab-pane fade" id="tab-tests"><label class="form-label">Tests (JS)</label><textarea id="tests" class="form-control form-control-sm" rows="8" placeholder="// pm.test('status', ()=>{ /* ... */ })"></textarea></div>
        <div class="tab-pane fade" id="tab-settings">
          <div class="row g-3"><div class="col-12 col-md-4"><label class="form-label">HTTP version</label><select id="http-version" class="form-select form-select-sm"><option>HTTP/1.x</option><option>HTTP/2</option></select></div><div class="col-12 col-md-8 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="ssl-verify"><label class="form-check-label" for="ssl-verify">Enable SSL certificate verification</label></div></div></div>
        </div>
      </div>
      <div class="mt-3"><h6 class="mb-2">Response</h6><pre id="response" class="bg-light p-3 border rounded" style="min-height:200px;max-height:420px;overflow:auto;">Enter the URL and click Send to get a response</pre></div>
    </div>
    <div class="col-12 col-lg-3"><div class="card"><div class="card-body"><h6>Conector</h6>
      <div class="mb-2"><label class="form-label">Nome</label><input id="conn-nome" class="form-control form-control-sm" value="<?=htmlspecialchars($connector['nome'], ENT_QUOTES)?>"></div>
      <div class="mb-2"><label class="form-label">Display name</label><input id="conn-display" class="form-control form-control-sm" value="<?=htmlspecialchars($connector['display_name']??'', ENT_QUOTES)?>"></div>
      <div class="mb-2"><label class="form-label">Imagem (URL)</label><input id="conn-image" class="form-control form-control-sm" value="<?=htmlspecialchars($connector['image_url']??'', ENT_QUOTES)?>"></div>
      <div class="mb-2"><label class="form-label">Categoria</label><input id="conn-cat" class="form-control form-control-sm" value="<?=htmlspecialchars($connector['categoria'], ENT_QUOTES)?>"></div>
      <div class="mb-2"><label class="form-label">Descrição</label><textarea id="conn-descr" class="form-control form-control-sm" rows="4"><?=htmlspecialchars($connector['descr'], ENT_QUOTES)?></textarea></div>
      <hr/>
      <div class="mb-2"><label class="form-label">Versão</label><input id="cv-version" class="form-control form-control-sm" value="<?=htmlspecialchars($cv['version'], ENT_QUOTES)?>"></div>
      <div class="mb-2"><label class="form-label">Path template</label><input id="cv-path" class="form-control form-control-sm" value="<?=htmlspecialchars($cv['path_tpl'], ENT_QUOTES)?>" placeholder="/v1/items/:id"></div>
      <div class="mb-2"><label class="form-label">Status</label><select id="cv-status" class="form-select form-select-sm"><?php foreach(['draft','active','deprecated'] as $st){ echo '<option'.($cv['status']===$st?' selected':'').'>'.$st.'</option>'; } ?></select></div>
    </div></div></div>
  </div>
</div>
<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
<script>
function rowTpl(key='', value='', desc=''){ return `<tr><td><input class="form-control form-control-sm k" placeholder="Key" value="\${key}"></td><td><input class="form-control form-control-sm v" placeholder="Value" value="\${value}"></td><td><input class="form-control form-control-sm d" placeholder="Description" value="\${desc}"></td><td><button class="btn btn-light btn-sm text-danger rm">&times;</button></td></tr>`;}
const tbodyParams=document.getElementById('params-tbody'); const tbodyHeaders=document.getElementById('headers-tbody'); tbodyParams.insertAdjacentHTML('beforeend',rowTpl()); tbodyHeaders.insertAdjacentHTML('beforeend',rowTpl());
document.getElementById('params-add').onclick=()=>tbodyParams.insertAdjacentHTML('beforeend',rowTpl()); document.getElementById('headers-add').onclick=()=>tbodyHeaders.insertAdjacentHTML('beforeend',rowTpl());
document.addEventListener('click',(e)=>{ if(e.target.classList.contains('rm')) e.target.closest('tr').remove(); });
const authFields=document.getElementById('auth-fields'); document.getElementById('auth-type').addEventListener('change',function(){ const t=this.value; function input(id,ph=''){return `<input id="\${id}" class="form-control form-control-sm mb-2" placeholder="\${ph}">`;} function select(id,opts){return `<select id="\${id}" class="form-select form-select-sm mb-2">\${opts.map(o=>`<option>\${o}</option>`).join('')}</select>`;} let html=''; if(t==='No Auth') html='<div class="text-muted">No authentication.</div>'; if(t==='Basic Auth') html=input('auth-basic-user','username')+input('auth-basic-pass','password'); if(t==='Bearer Token') html=input('auth-bearer','token ({{from_vault}})'); if(t==='JWT Bearer') html=input('auth-jwt','jwt token'); if(t==='Digest Auth') html=input('auth-digest-user','username')+input('auth-digest-pass','password'); if(t==='OAuth 1.0') html=input('auth-oauth1-key','consumer key')+input('auth-oauth1-secret','consumer secret')+input('auth-oauth1-token','token')+input('auth-oauth1-token-secret','token secret'); if(t==='OAuth 2.0') html=select('auth-oauth2-grant',['client_credentials','authorization_code','password'])+input('auth-oauth2-token-url','token url')+input('auth-oauth2-client-id','client id')+input('auth-oauth2-client-secret','client secret')+input('auth-oauth2-scope','scope'); if(t==='Hawk Authentication') html=input('auth-hawk-id','id')+input('auth-hawk-key','key'); if(t==='AWS Signature') html=input('auth-aws-key','access key')+input('auth-aws-secret','secret')+input('auth-aws-region','region')+input('auth-aws-service','service'); if(t==='NTLM Authentication') html=input('auth-ntlm-user','username')+input('auth-ntlm-pass','password')+input('auth-ntlm-domain','domain'); if(t==='API Key') html=input('auth-apikey-key','key name (e.g. X-API-Key)')+input('auth-apikey-value','key value'); if(t==='Akamai EdgeGrid') html=input('auth-edge-client-token','client token')+input('auth-edge-client-secret','client secret')+input('auth-edge-access-token','access token'); if(t==='ASAP (Atlassian)') html=input('auth-asap-issuer','issuer')+input('auth-asap-keyid','key id')+input('auth-asap-private','private key'); authFields.innerHTML=html||'<div class="text-muted">Select an auth type…</div>'; });
document.getElementById('btn-send').addEventListener('click', async function(){ const method=document.getElementById('method').value; const url=document.getElementById('url').value; const headers=[...document.querySelectorAll('#headers-tbody tr')].reduce((acc,tr)=>{ const k=tr.querySelector('.k').value.trim(); if(!k) return acc; acc[k]=tr.querySelector('.v').value; return acc; },{}); const params=[...document.querySelectorAll('#params-tbody tr')].reduce((acc,tr)=>{ const k=tr.querySelector('.k').value.trim(); if(!k) return acc; acc[k]=tr.querySelector('.v').value; return acc; },{}); const mode=(document.querySelector('input[name="bodyMode"]:checked')||{}).value||'none'; const bodyRaw=document.getElementById('body-raw').value; const payload=new FormData(); payload.append('method',method); payload.append('url',url); payload.append('headers_json',JSON.stringify(headers)); payload.append('params_json',JSON.stringify(params)); payload.append('body_mode',mode); payload.append('body_raw',bodyRaw); const res=await fetch('api/connector_send_preview.php',{method:'POST',body:payload}); const out=await res.json(); document.getElementById('response').textContent=JSON.stringify(out,null,2); });
document.getElementById('btn-save-conn').addEventListener('click', async function(){ const payload=new FormData(); payload.append('connector_id','<?= $connector_id ?>'); payload.append('cv_id','<?= $cv_id ?>'); payload.append('nome',document.getElementById('conn-nome').value); payload.append('display_name',document.getElementById('conn-display').value); payload.append('image_url',document.getElementById('conn-image').value); payload.append('categoria',document.getElementById('conn-cat').value); payload.append('descr',document.getElementById('conn-descr').value); payload.append('version',document.getElementById('cv-version').value); payload.append('status',document.getElementById('cv-status').value); const method=document.getElementById('method').value; const url=document.getElementById('url').value; const path=(document.getElementById('cv-path').value||''); const headers=[...document.querySelectorAll('#headers-tbody tr')].reduce((acc,tr)=>{ const k=tr.querySelector('.k').value.trim(); if(!k) return acc; acc[k]=tr.querySelector('.v').value; return acc; },{}); try{ const u=new URL(url); payload.append('base_url',u.origin); if(!path) payload.append('path_tpl', u.pathname+(u.search||'')); else payload.append('path_tpl',path); }catch(e){ payload.append('base_url','<?= htmlspecialchars($cv['base_url'], ENT_QUOTES) ?>'); payload.append('path_tpl',path); } payload.append('method',method); payload.append('headers_json',JSON.stringify(headers)); payload.append('policy_json',JSON.stringify({timeout_ms:30000})); const res=await fetch('api/connector_save.php',{method:'POST',body:payload}); const out=await res.json(); alert(out.message||'Saved'); if(out.redirect) window.location=out.redirect; });
document.getElementById('btn-ai-test').addEventListener('click', function(){ alert('Stub IA: sugerir casos de teste e dados.'); }); document.getElementById('btn-ai-build').addEventListener('click', function(){ alert('Stub IA: construir conector a partir de URL/OAS.'); });
</script>
