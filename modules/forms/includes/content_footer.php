<script type="module">
  /* JS via esm.sh (resolve dependências automaticamente) */
  import { Form }       from 'https://esm.sh/@bpmn-io/form-js-viewer@1.17.0';
  import { FormEditor } from 'https://esm.sh/@bpmn-io/form-js-editor@1.17.0';

  const $ = (s, c=document)=>c.querySelector(s);

  // ===== Schema base =====
  const DEFAULT_SCHEMA = {
    schemaVersion: 1,
    type: "default",
    components: [
      { type: "textfield", key: "nome",   label: "Nome",  validate: { required: true } },
      { type: "textfield", key: "email",  label: "E-mail", properties: { inputType: "email", placeholder: "voce@exemplo.com" }, validate: { required: true } },
      { type: "select",    key: "plano",  label: "Plano",  values: [
        { label: "Básico", value: "basic" },
        { label: "Pro",    value: "pro" },
        { label: "Enterprise", value: "enterprise" }
      ]},
      { type: "checkbox",  key: "aceite", label: "Aceito os termos", validate: { required: true } }
    ],
    data: {}
  };

  let currentSchema = structuredClone(DEFAULT_SCHEMA);
  let currentData   = {};
  let currentMode   = 'visual';

  const editor = new FormEditor({ container: $('#editorHost') });
  const viewer = new Form({ container: $('#preview') });

  async function getSchemaFromEditor(){ const { schema } = await editor.save(); return schema; }

  async function renderPreview(schema, data){
    const title = (document.getElementById('formTitle').value || 'Formulário');

    await viewer.importSchema(schema);

    if (typeof viewer.importData === 'function') {
      await viewer.importData(data || {});
    } else if (typeof viewer.setData === 'function') {
      viewer.setData(data || {});
    }

    const host = document.getElementById('preview');
    let t = host.querySelector('.mozart-title');
    if (!t){ t = document.createElement('div'); t.className='mozart-title'; t.style.cssText='font-weight:600;margin:0 0 8px 0'; host.prepend(t); }
    t.textContent = title;
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }
  const saveAs = (blob, filename) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = filename; document.body.appendChild(a); a.click(); setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 700); };

  function safeCopy(text) {
    return navigator.clipboard?.writeText(text).catch(() => {
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.left = '-9999px';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } finally { ta.remove(); }
    });
  }

  // ===== Boot =====
  try {
    await editor.importSchema(currentSchema);
    await viewer.importSchema(currentSchema);

    if (typeof viewer.importData === 'function') {
      await viewer.importData(currentData);
    } else if (typeof viewer.setData === 'function') {
      viewer.setData(currentData);
    }

    document.getElementById('fatalEditorErr').style.display = 'none';
  } catch (e) {
    console.error('[form-js] erro:', e);
    alert('Falha ao carregar form-js (rede/CDN). Veja o console (F12).');
    document.getElementById('fatalEditorErr').style.display = 'flex';
  }

  // Preview submit
  viewer.on('submit', (ev) => {
    currentData = ev.data || {};
    alert('Dados enviados (preview):\n\n' + JSON.stringify(currentData, null, 2));
  });

  // ===== Alterna modos =====
  document.getElementById('btnVisual').onclick = async ()=>{
    if (currentMode==='visual') return;
    try {
      currentSchema = JSON.parse($('#schemaArea').value || '{}');
      await editor.importSchema(currentSchema);
      await renderPreview(currentSchema, currentData);
      $('#editorHost').style.display = '';
      $('#codeHost').style.display   = 'none';
      currentMode = 'visual'; $('#modeLabel').textContent = 'Modo: Visual';
    } catch { alert('JSON inválido.'); }
  };

  document.getElementById('btnCode').onclick = async ()=>{
    currentSchema = await getSchemaFromEditor();
    document.getElementById('schemaArea').value = JSON.stringify(currentSchema, null, 2);
    $('#editorHost').style.display = 'none';
    $('#codeHost').style.display   = '';
    currentMode = 'code'; $('#modeLabel').textContent = 'Modo: Código (JSON)';
  };

  // ===== Toolbar =====
  document.getElementById('btnNew').onclick = async ()=>{
    currentSchema = structuredClone(DEFAULT_SCHEMA);
    currentData = {};
    if (currentMode==='visual') await editor.importSchema(currentSchema);
    else document.getElementById('schemaArea').value = JSON.stringify(currentSchema, null, 2);
    await renderPreview(currentSchema, currentData);
  };

  document.getElementById('btnOpen').onclick = ()=> document.getElementById('fileOpen').click();

  document.getElementById('fileOpen').addEventListener('change', async (ev)=>{
    const f = ev.target.files?.[0]; if (!f) return;
    try{
      const js = JSON.parse(await f.text());
      currentSchema = js;
      if (currentMode==='visual') await editor.importSchema(currentSchema);
      else document.getElementById('schemaArea').value = JSON.stringify(currentSchema, null, 2);
      await renderPreview(currentSchema, currentData);
    } catch { alert('Arquivo JSON inválido.'); }
    ev.target.value = '';
  });

  document.getElementById('btnSaveJSON').onclick = async ()=>{
    if (currentMode==='visual') currentSchema = await getSchemaFromEditor();
    else { try { currentSchema = JSON.parse(document.getElementById('schemaArea').value||'{}'); } catch { return alert('JSON inválido.'); } }
    const name = (currentSchema.title || 'form') + '.form.json';
    saveAs(new Blob([JSON.stringify(currentSchema, null, 2)], { type:'application/json' }), name);
  };

  document.getElementById('btnRender').onclick = async ()=>{
    if (currentMode==='visual') currentSchema = await getSchemaFromEditor();
    else { try { currentSchema = JSON.parse(document.getElementById('schemaArea').value||'{}'); } catch { return alert('JSON inválido.'); } }
    await renderPreview(currentSchema, currentData);
  };

  document.getElementById('btnCopyJSON').onclick = async ()=>{
    const txt = currentMode==='visual'
      ? JSON.stringify(await getSchemaFromEditor(), null, 2)
      : document.getElementById('schemaArea').value;
    await safeCopy(txt);
  };

  document.getElementById('btnCopyHTML').onclick = async ()=>{
    const schema = currentMode==='visual'
      ? await getSchemaFromEditor()
      : JSON.parse(document.getElementById('schemaArea').value||'{}');

    const cssBase = './vendor/form-js@1.17.0/dist/assets'; // relativo à página
    const title = escapeHtml(document.getElementById('formTitle').value || 'Formulário');

    const html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${title}</title>
<link rel="stylesheet" href="${cssBase}/form-js.css"></head>
<body style="margin:16px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial">
<h1 style="font-size:18px;margin:0 0 12px 0">${title}</h1>
<div id="preview"></div>
<script type="module">
  import { Form } from 'https://esm.sh/@bpmn-io/form-js-viewer@1.17.0';
  const schema = ${JSON.stringify(schema, null, 2)};
  const viewer = new Form({ container: document.getElementById('preview') });
  await viewer.importSchema(schema);
  if (typeof viewer.importData==='function') await viewer.importData({});
  else if (typeof viewer.setData==='function') viewer.setData({});
<\/script></body></html>`;

    await safeCopy(html);
  };

  document.getElementById('btnSaveHTML').onclick = async ()=>{
    const schema = currentMode==='visual'
      ? await getSchemaFromEditor()
      : JSON.parse(document.getElementById('schemaArea').value||'{}');

    const cssBase = './vendor/form-js@1.17.0/dist/assets';
    const title = escapeHtml(document.getElementById('formTitle').value || 'Formulário');

    const html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${title}</title>
<link rel="stylesheet" href="${cssBase}/form-js.css"></head>
<body style="margin:16px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial">
<h1 style="font-size:18px;margin:0 0 12px 0">${title}</h1>
<div id="preview"></div>
<script type="module">
  import { Form } from 'https://esm.sh/@bpmn-io/form-js-viewer@1.17.0';
  const schema = ${JSON.stringify(schema, null, 2)};
  const viewer = new Form({ container: document.getElementById('preview') });
  await viewer.importSchema(schema);
  if (typeof viewer.importData==='function') await viewer.importData({});
  else if (typeof viewer.setData==='function') viewer.setData({});
<\/script></body></html>`;

    const name = (schema.title || 'form') + '.html';
    saveAs(new Blob([html], { type:'text/html' }), name);
  };

  document.getElementById('btnClearData').onclick = async ()=>{
    currentData = {};
    if (typeof viewer.importData==='function') await viewer.importData({});
    else if (typeof viewer.setData==='function') viewer.setData({});
  };
</script>