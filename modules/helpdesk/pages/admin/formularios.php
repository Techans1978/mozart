<?php
// modules/helpdesk/pages/admin/formularios.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';
require_once ROOT_PATH . '/system/includes/head_hd.php';
proteger_pagina();

$dbc = $conn ?? $mysqli ?? null;
if(!$dbc || !($dbc instanceof mysqli)) die('Sem conexão MySQLi.');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify($t){
  $t = iconv('UTF-8','ASCII//TRANSLIT',$t);
  $t = preg_replace('~[^\\pL\\d]+~u','-',$t);
  $t = trim($t,'-');
  $t = strtolower($t);
  $t = preg_replace('~[^-a-z0-9]+~','',$t);
  return $t ?: ('form-'.date('YmdHis'));
}
function ensure_dir($path){
  if(!is_dir($path)) @mkdir($path,0775,true);
  return is_dir($path) && is_writable($path);
}

$ok = $err = null;

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao']) && $_POST['acao']==='salvar'){
  $nome      = trim($_POST['nome'] ?? '');
  $tipo      = in_array(($_POST['tipo'] ?? 'helpdesk'), ['helpdesk','bpm','outro']) ? $_POST['tipo'] : 'helpdesk';
  $categoria = trim($_POST['categoria'] ?? 'helpdesk/default');
  $json      = $_POST['json'] ?? '';
  $html      = $_POST['html'] ?? '';
  $ativo     = isset($_POST['ativo']) ? 1 : 1;
  $slug      = slugify($nome);
  $user_id   = $_SESSION['usuario_id'] ?? null;

  if($nome==='' || $json===''){
    $err = 'Nome e JSON do formulário são obrigatórios.';
  } else {
    $base = ROOT_PATH . '/public/modules/form/' . $categoria;
    if(!ensure_dir($base)){
      $err = 'Não foi possível criar/escrever a pasta: public/modules/form/'.h($categoria);
    } else {
      $pathJson = $base . '/' . $slug . '.json';
      $pathHtml = $base . '/' . $slug . '.html';

      // Grava os arquivos
      if(file_put_contents($pathJson, $json) === false){
        $err = 'Falha ao gravar o .json';
      } else {
        // Se não veio HTML, gera um wrapper básico que carrega o JSON (renderer)
        if($html===''){
          $html = "<!doctype html>\n<html lang=\"pt-br\"><head><meta charset=\"utf-8\"><title>".h($nome)."</title>\n".
                  "<script src=\"https://unpkg.com/@bpmn-io/form-js@1.10.0/dist/form-viewer.umd.js\"></script>\n".
                  "<link rel=\"stylesheet\" href=\"https://unpkg.com/@bpmn-io/form-js@1.10.0/dist/assets/form-js.css\"/>\n".
                  "<style>body{background:#f6f7f9;margin:0} .wrap{max-width:920px;margin:24px auto;background:#fff;border:1px solid #eee;border-radius:12px;padding:18px}</style>\n".
                  "</head><body><div class=\"wrap\"><div id=\"form\"></div></div>\n".
                  "<script>fetch('".basename($pathJson)."').then(r=>r.json()).then(schema=>{const v=new FormViewer({container:document.querySelector('#form')});v.importSchema(schema);});</script>\n".
                  "</body></html>";
        }
        if(file_put_contents($pathHtml, $html) === false){
          $err = 'Falha ao gravar o .html';
        } else {
          // Salva/atualiza DB
          $caminho_json = str_replace(ROOT_PATH, '', $pathJson);
          $caminho_html = str_replace(ROOT_PATH, '', $pathHtml);

          $stmt = $dbc->prepare("INSERT INTO moz_forms (nome, slug, tipo, categoria, caminho_json, caminho_html, json, html, ativo, criado_por)
                                 VALUES (?,?,?,?,?,?,?,?,?,?)
                                 ON DUPLICATE KEY UPDATE nome=VALUES(nome), tipo=VALUES(tipo), categoria=VALUES(categoria),
                                   caminho_json=VALUES(caminho_json), caminho_html=VALUES(caminho_html),
                                   json=VALUES(json), html=VALUES(html), ativo=VALUES(ativo), atualizado_em=CURRENT_TIMESTAMP, versao=versao+1");
          if(!$stmt){ $err = 'Erro prepare: '.$dbc->error; }
          else{
            $stmt->bind_param('ssssssssii', $nome, $slug, $tipo, $categoria, $caminho_json, $caminho_html, $json, $html, $ativo, $user_id);
            if($stmt->execute()){
              $ok = 'Formulário salvo! Caminhos: '.$caminho_json.' | '.$caminho_html;
            } else {
              $err = 'Erro ao salvar no banco: '.$stmt->error;
            }
            $stmt->close();
          }
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Formulários — Help Desk</title>

  <!-- form-js Editor + Viewer (CDN) -->
  <script src="https://unpkg.com/@bpmn-io/form-js@1.10.0/dist/form-editor.umd.js"></script>
  <script src="https://unpkg.com/@bpmn-io/form-js@1.10.0/dist/form-viewer.umd.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/@bpmn-io/form-js@1.10.0/dist/assets/form-js.css"/>
  <style>
    :root{--sidebar-w:360px}
    body{background:#f6f7f9}
    .topbar{display:flex;gap:8px;align-items:center;justify-content:flex-start;margin:12px 0}
    .btn{padding:8px 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;cursor:pointer}
    .btn.primary{background:#2563eb;border-color:#1e40af;color:#fff}
    .grid{display:grid;grid-template-columns: 1fr var(--sidebar-w); gap:14px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .card h3{margin:0;padding:12px 14px;border-bottom:1px solid #eee}
    .card .body{padding:12px 14px}
    #editor, #preview{height:62vh;border:1px dashed #e5e7eb;border-radius:10px;background:#fff}
    textarea.code{width:100%;min-height:240px}
    input[type=text], select{padding:8px 10px;border:1px solid #ddd;border-radius:8px}
    .row{display:flex;gap:10px;align-items:center;margin-bottom:8px}
  </style>
</head>
<body>
<?php include ROOT_PATH.'/system/includes/navbar.php'; ?>
<div class="container" style="padding:16px 18px;">
  <h2 style="margin:0 0 8px;">Formulários (form-js) — Help Desk / BPM / Outros</h2>
  <?php if($ok): ?><div class="alert alert-success"><?=h($ok)?></div><?php endif; ?>
  <?php if($err): ?><div class="alert alert-danger"><?=h($err)?></div><?php endif; ?>

  <form method="post" id="form-salvar">
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="json" id="json">
    <input type="hidden" name="html" id="html">

    <div class="card">
      <div class="body">
        <div class="row">
          <input type="text" name="nome" placeholder="Nome do formulário" required style="flex:1">
          <select name="tipo">
            <option value="helpdesk">Help Desk</option>
            <option value="bpm">BPM</option>
            <option value="outro">Outro</option>
          </select>
          <input type="text" name="categoria" placeholder="Categoria (ex.: helpdesk/abertura)" value="helpdesk/abertura" style="min-width:260px">
          <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="ativo" checked> Ativo</label>
        </div>

        <div class="topbar">
          <button type="button" class="btn" onclick="novo()">Novo</button>
          <label class="btn">
            Abrir JSON
            <input type="file" id="openJson" accept="application/json" style="display:none" onchange="abrirJSON(this)">
          </label>
          <button type="button" class="btn" onclick="baixarJSON()">Baixar JSON</button>
          <button type="button" class="btn" onclick="copiarHTML()">Copiar HTML</button>
          <button type="button" class="btn" onclick="baixarHTML()">Baixar HTML</button>
          <button type="button" class="btn" onclick="toPreview()">Preview</button>
          <button type="button" class="btn primary" onclick="salvar()">Salvar</button>
        </div>

        <div class="grid">
          <div class="card">
            <h3>Edição Visual</h3>
            <div class="body">
              <div id="editor"></div>
            </div>
          </div>

          <div class="card">
            <h3>Código (JSON) / Preview</h3>
            <div class="body">
              <textarea class="code" id="code" placeholder="Schema JSON aqui..."></textarea>
              <div id="preview" style="margin-top:10px;padding:10px;"></div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </form>
</div>

<script>
let editor, viewer;

function baseSchema(){
  return {
    "components": [
      { "key":"nome", "label":"Nome*", "type":"textfield", "validate":{ "required": true } },
      { "key":"email", "label":"E-mail*", "type":"email", "validate":{ "required": true } },
      { "key":"plano", "label":"Plano", "type":"select", "values":[{"value":"basic","label":"Basic"},{"value":"pro","label":"Pro"}] },
      { "key":"termos", "label":"Aceito os termos*", "type":"checkbox", "validate":{ "required": true } }
    ],
    "type": "default",
    "id": "Form_"+Math.random().toString(36).slice(2,8)
  };
}

function init(){
  editor = new FormEditor({
    container: document.querySelector('#editor'),
  });

  editor.importSchema(baseSchema());

  editor.on('changed', async ()=> {
    const schema = await editor.saveSchema();
    document.querySelector('#code').value = JSON.stringify(schema, null, 2);
  });

  viewer = new FormViewer({ container: document.querySelector('#preview')});
  document.querySelector('#code').value = JSON.stringify(baseSchema(), null, 2);
  toPreview();
}

async function toPreview(){
  try{
    const schema = JSON.parse(document.querySelector('#code').value || '{}');
    await viewer.importSchema(schema);
  }catch(e){ alert('JSON inválido: '+e.message); }
}

function novo(){
  editor.importSchema(baseSchema());
  document.querySelector('#code').value = JSON.stringify(baseSchema(), null, 2);
  toPreview();
}

function abrirJSON(input){
  const file = input.files[0];
  if(!file) return;
  const r = new FileReader();
  r.onload = ()=> {
    try{
      const schema = JSON.parse(r.result);
      editor.importSchema(schema);
      document.querySelector('#code').value = JSON.stringify(schema, null, 2);
      toPreview();
    }catch(e){ alert('JSON inválido.'); }
  };
  r.readAsText(file);
}

async function baixarJSON(){
  const schema = await editor.saveSchema();
  const blob = new Blob([JSON.stringify(schema,null,2)], {type:'application/json'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download='form.json'; a.click();
}

async function gerarHTMLStandalone(){
  const schema = await editor.saveSchema();
  const html = `<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Form</title>
<script src="https://unpkg.com/@bpmn-io/form-js@1.10.0/dist/form-viewer.umd.js"></script>
<link rel="stylesheet" href="https://unpkg.com/@bpmn-io/form-js@1.10.0/dist/assets/form-js.css"/>
<style>body{background:#f6f7f9;margin:0} .wrap{max-width:900px;margin:24px auto;background:#fff;border:1px solid #eee;border-radius:12px;padding:18px}</style>
</head>
<body>
  <div class="wrap"><div id="form"></div></div>
  <script>
    const schema = ${JSON.stringify(schema)};
    const viewer = new FormViewer({ container: document.querySelector('#form')});
    viewer.importSchema(schema);
  </script>
</body>
</html>`;
  return {schema, html};
}

async function copiarHTML(){
  const {html} = await gerarHTMLStandalone();
  await navigator.clipboard.writeText(html);
  alert('HTML copiado para a área de transferência.');
}

async function baixarHTML(){
  const {html} = await gerarHTMLStandalone();
  const blob = new Blob([html], {type:'text/html'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download='form.html'; a.click();
}

async function salvar(){
  const {schema, html} = await gerarHTMLStandalone();
  document.querySelector('#json').value = JSON.stringify(schema);
  document.querySelector('#html').value = html;
  document.querySelector('#form-salvar').submit();
}

document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
