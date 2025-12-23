<?php
// modules/dmn/dmn_versions.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$decisionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($decisionId <= 0) {
  die("Informe ?id=DECISION_ID");
}
?>
<?php include_once ROOT_PATH.'system/includes/head.php'; ?>

<style>
  :root{ --bg:#f6f7f9; --card:#fff; --bd:#e5e7eb; --txt:#111; }
  *{ box-sizing:border-box; }
  body{ margin:0; font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--bg); color:var(--txt); }
  .top{ padding:12px; border-bottom:1px solid var(--bd); background:#fff; display:flex; gap:10px; align-items:center; flex-wrap:wrap; position:sticky; top:0; z-index:10; }
  .brand{ font-weight:900; }
  .spacer{ flex:1; }
  .btn{ border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
  .btn:hover{ background:#f3f4f6; }
  .btn.primary{ background:#111827; border-color:#111827; color:#fff; }
  .wrap{ padding:12px; }
  .card{ background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:12px; }
  table{ width:100%; border-collapse:separate; border-spacing:0; margin-top:12px; }
  th, td{ text-align:left; padding:10px; border-bottom:1px solid var(--bd); vertical-align:top; }
  th{ font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
  .muted{ color:#6b7280; font-size:12px; }
  .pill{ display:inline-flex; padding:3px 10px; border-radius:999px; font-weight:900; font-size:12px; border:1px solid var(--bd); background:#fff; }
  .pill.draft{ border-color:#fde68a; }
  .pill.published{ border-color:#86efac; }
  pre{ background:#0b1020; color:#e5e7eb; padding:12px; border-radius:12px; overflow:auto; max-height:260px; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; }
</style>

<?php include_once ROOT_PATH.'system/includes/navbar.php'; ?>

<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
<!-- Page Content -->

<div class="top">
  <div class="brand">Mozart — DMN Versões</div>
  <div class="muted">Decision #<?php echo (int)$decisionId; ?></div>
  <div class="spacer"></div>
  <a class="btn" href="dmn_list.php">Voltar</a>
  <a class="btn primary" href="dmn_editor.php?id=<?php echo (int)$decisionId; ?>">Abrir Editor</a>
</div>

<div class="wrap">
  <div class="card">
    <div style="font-weight:900">Histórico</div>
    <div class="muted">Draft = rascunho atual. Published = versões publicadas (v1, v2, v3…).</div>

    <table>
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Versão</th>
          <th>Checksum</th>
          <th>Notas</th>
          <th>Criado</th>
          <th>Publicado</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="7" class="muted">Carregando…</td></tr>
      </tbody>
    </table>

    <div style="margin-top:12px; font-weight:900">Preview XML</div>
    <pre id="xmlPreview">&lt;xml&gt;…&lt;/xml&gt;</pre>
  </div>
</div>

<!-- Page Content -->
      </div>
    </div>
  </div>
</div>
<!-- Page Content -->
<?php
// carrega seus scripts globais + Camunda JS (inserido no code_footer.php)
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>

<script>
const decisionId = <?php echo (int)$decisionId; ?>;
const API = {
  versions: 'api/version_list.php?decision_id=' + decisionId,
  versionXml: 'api/version_get_xml.php?id=' // opcional (recomendado)
};

const $ = (s)=>document.querySelector(s);

async function apiGet(url){
  const r = await fetch(url, { credentials:'same-origin' });
  const j = await r.json().catch(()=>null);
  if (!r.ok || !j || j.ok === false) throw new Error(j?.error || ('HTTP '+r.status));
  return j;
}

function esc(s){
  return (s??'').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");
}

function downloadText(text, filename){
  const blob = new Blob([text], {type:'application/xml'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 700);
}

async function loadXml(versionId, filename){
  try{
    const data = await apiGet(API.versionXml + versionId);
    const xml = data.item?.xml || '';
    $('#xmlPreview').textContent = xml || '(vazio)';
    if (filename) downloadText(xml, filename);
  }catch(e){
    alert('Erro ao carregar XML da versão. Se você não criou api/version_get_xml.php, crie-o (eu mandei no pacote).');
  }
}

async function load(){
  try{
    const data = await apiGet(API.versions);
    const items = data.items || [];

    if (!items.length) {
      $('#tbody').innerHTML = `<tr><td colspan="7" class="muted">Sem versões.</td></tr>`;
      $('#xmlPreview').textContent = '(sem xml)';
      return;
    }

    $('#tbody').innerHTML = items.map(v => {
      const t = v.type;
      const ver = v.version_num ?? '';
      const pill = `<span class="pill ${t}">${t}</span>`;
      const fname = `decision_${decisionId}_${t}${t==='published' ? ('_v'+ver) : ''}.dmn`;

      return `
        <tr>
          <td>${pill}</td>
          <td>${esc(ver)}</td>
          <td class="muted">${esc(v.checksum || '')}</td>
          <td>${esc(v.notes || '')}</td>
          <td class="muted">${esc(v.created_at || '')}</td>
          <td class="muted">${esc(v.published_at || '')}</td>
          <td>
            <div class="actions">
              <button class="btn" onclick="preview(${v.id})">Preview</button>
              <button class="btn" onclick="download(${v.id}, '${fname.replaceAll("'", "")}')">Baixar XML</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    // preview do primeiro item
    await loadXml(items[0].id);

  }catch(e){
    $('#tbody').innerHTML = `<tr><td colspan="7" class="muted">Erro: ${esc(e.message)}</td></tr>`;
  }
}

window.preview = async (id)=>{ await loadXml(id); };
window.download = async (id, filename)=>{ await loadXml(id, filename); };

load();
</script>





<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>
