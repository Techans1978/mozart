<style>
  :root { --gap: 12px; --panel-w: 420px; }
  * { box-sizing: border-box; }
  body { margin:0; font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:#f6f7f9; color:#111; }
  .toolbar { position:sticky; top:0; z-index:5; display:flex; gap:8px; align-items:center; padding:10px 12px; background:#fff; border-bottom:1px solid #e5e7eb; }
  .toolbar .spacer{ flex:1; }
  .btn { border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px; font-weight:600; cursor:pointer; }
  .btn:hover { background:#f3f4f6; }
  .btn.primary { background:#111827; color:#fff; border-color:#111827; }
  .wrap { display:flex; gap:var(--gap); padding:var(--gap); height: calc(100vh - 58px); }
  .col { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; }
  #editorCol { flex:1; min-width:500px; position: relative; }
  #previewCol { flex:0 0 var(--panel-w); min-width:320px; }
  #editorHost, #codeHost { flex:1; min-height:520px; }
  #codeHost textarea { width:100%; height:100%; border:0; padding:12px; font-family: ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; outline:none; }
  #previewHead { padding:10px 12px; border-bottom:1px solid #e5e7eb; display:flex; gap:8px; align-items:center; }
  #previewBody { padding:12px; overflow:auto; }
  #preview { min-height:520px; border:1px solid #e5e7eb; border-radius:8px; padding:12px; }
  .mode { font-size:12px; color:#6b7280; margin-left:6px; }
  input[type="file"] { display:none; }
  .rowline { display:flex; gap:8px; align-items:center; }
  .rowline input[type="text"] { border:1px solid #d1d5db; border-radius:8px; padding:6px 8px; }
  .fatal { position:absolute; inset:0; display:none; align-items:center; justify-content:center; background:rgba(255,255,255,.94); color:#b91c1c; font-weight:700; padding:16px; text-align:center; border-top:1px solid #fecaca; }
</style>