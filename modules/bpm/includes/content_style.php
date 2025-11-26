    <style>
    
    /* bpm designer */

    :root {
      --toolbar-h: 56px;
      --sidebar-w: 360px;
      --gap: 10px;
    }
    * { box-sizing: border-box; }
    body { margin:0; font: 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; color:#111; background:#f6f7f9; }
    .shell { display:flex; flex-direction:column; height:100vh; }
    .toolbar {
      height: var(--toolbar-h);
      display:flex; gap:8px; align-items:center; padding:8px 12px;
      background:#fff; border-bottom:1px solid #e5e7eb; position:sticky; top:0; z-index:5;
    }
    .toolbar h2 { font-size:16px; margin:0 12px 0 0; font-weight:600; color:#111827; }
    .toolbar .spacer { flex:1; }
    .btn {
      border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px; cursor:pointer;
      transition: .15s; font-weight:600;
    }
    .btn:hover { background:#f3f4f6; }
    .btn.primary { background:#111827; color:#fff; border-color:#111827; }
    .btn.primary:hover { background:#0b1220; }
    input[type="file"] { display:none; }

    .work {
      display:flex; gap: var(--gap);
      padding: var(--gap);
      height: calc(100vh - var(--toolbar-h));
    }
    #canvas { flex:1; height:100%; background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
    #properties {
      width: var(--sidebar-w); height:100%;
      background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:auto;
    }

    .panel {
      background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin: var(--gap) 0;
    }
    .row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    label { font-size:12px; color:#6b7280; }
    select, input[type="text"] {
      border:1px solid #d1d5db; border-radius:8px; padding:6px 8px; min-width: 160px; background:#fff;
    }

    .notice { font-size:12px; color:#6b7280; }
    .kbd { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:6px; padding:0 6px; }

    /* CSS do camunda-bpmn-js (CDN) cai aqui; se CDN falhar, o JS injeta fallback local abaixo */

    </style>