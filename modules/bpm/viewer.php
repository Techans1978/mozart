<?php
// modules/bpm/viewer.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once __DIR__.'/../../config.php';
require_once ROOT_PATH.'/system/config/autenticacao.php';
require_once ROOT_PATH.'/system/config/connect.php';
if (session_status()===PHP_SESSION_NONE) session_start();
proteger_pagina();

$instance_id = (int)($_GET['instance_id'] ?? 0);
$version_id  = (int)($_GET['version_id'] ?? 0); // alternativa: abrir por versão

include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<style>
  #page-wrapper{ background:#f6f7f9; }
  .shell{max-width:1180px;margin:10px auto;padding:0 10px;}
  #canvas{height:70vh;background:#fff;border:1px solid #e5e7eb;border-radius:8px}
</style>

<div class="shell">
  <h2>Diagrama BPMN</h2>
  <div id="canvas"></div>
</div>

<?php include __DIR__ . '/includes/bpmn_viewer_loader.php'; ?>
<script>
(async function(){
  const instanceId = <?= (int)$instance_id ?>;
  const versionId  = <?= (int)$version_id ?>;

  // decide como carregar: se veio instanceId, preferir XML da versão ligada à instância
  const xmlUrl  = instanceId
      ? '<?= BASE_URL ?>/modules/bpm/api/process_diagram_xml.php?instance_id='+instanceId
      : '<?= BASE_URL ?>/modules/bpm/api/process_diagram_xml.php?version_id='+versionId;

  const pathUrl = instanceId
      ? '<?= BASE_URL ?>/modules/bpm/api/instance_path.php?id='+instanceId
      : '';

  const viewer = new BpmnJS({ container: '#canvas' });

  async function get(url){ const r=await fetch(url,{credentials:'same-origin'}); return await r.text(); }
  async function getJSON(url){ const r=await fetch(url,{credentials:'same-origin'}); return await r.json(); }

  const xml = await get(xmlUrl);
  await viewer.importXML(xml);
  const canvas = viewer.get('canvas');

  // Fit diagram
  canvas.zoom('fit-viewport', 'auto');

  // highlight path if instance
  if (pathUrl){
    const p = await getJSON(pathUrl);
    const done = new Set(p.doneIds||[]);
    done.forEach(id=>{
      try { canvas.addMarker(id, 'highlight-done'); } catch(e){}
    });
  }

  // simple styles (done path)
  const style = document.createElement('style');
  style.textContent = `
    .djs-element.highlight-done .djs-visual > :nth-child(1) {
      stroke: #10b981 !important; stroke-width: 3px !important;
    }
  `;
  document.head.appendChild(style);
})();
</script>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
