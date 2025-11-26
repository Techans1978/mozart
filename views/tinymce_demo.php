<?php
// views/tinymce_demo.php — Página de exemplo do editor
// Acesse no navegador: /views/tinymce_demo.php
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Demo TinyMCE — Mozart</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Helvetica,Arial,sans-serif; padding:24px; background:#f7f7f9;}
    .card{max-width:980px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
    .card header{padding:16px 20px;border-bottom:1px solid #f0f0f2;font-weight:600;}
    .card .body{padding:20px;}
    .hint{color:#6b7280;font-size:14px;margin-top:8px;}
    .row{display:flex;gap:12px;align-items:center;justify-content:space-between;}
    .row .actions{display:flex;gap:8px;}
    button{padding:10px 14px;border-radius:10px;border:1px solid #d1d5db;background:#fff;cursor:pointer}
    button.primary{background:#111827;color:#fff;border-color:#111827}
    textarea{width:100%}
  </style>
</head>
<body>
  <div class="card">
    <header class="row">
      <div>Demo TinyMCE</div>
      <div class="actions">
        <button onclick="document.querySelector('form').reset()">Limpar</button>
        <button class="primary" onclick="alert(tinyMCE.activeEditor.getContent());">Mostrar HTML</button>
      </div>
    </header>
    <div class="body">
      <form>
        <textarea class="tinymce" name="conteudo">
          <h2>Olá, Mozart 👋</h2>
          <p>Este é um editor TinyMCE self-hosted. Faça upload de <em>imagens</em> pelo botão da barra.</p>
          <p><strong>Dica:</strong> o arquivo de idiomas <code>pt_BR.js</code> é opcional.</p>
        </textarea>
      </form>
      <div class="hint">
        • Os arquivos do TinyMCE devem estar em <code>/system/includes/assets/tinymce</code>.<br>
        • O upload envia para <code>/api/upload-imagem.php</code> e salva em <code>/public/uploads/AAAA/MM/</code>.
      </div>
    </div>
  </div>

  <!-- TinyMCE core (self-hosted) -->
  <script src="/system/includes/assets/tinymce/tinymce.min.js"></script>
  <!-- Init -->
  <script src="/system/includes/assets/js/tinymce-init.js"></script>
</body>
</html>
