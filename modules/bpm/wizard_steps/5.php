<?php /* Step 5: Publicar */ ?>
<div class="card">
  <h2>5) Publicar</h2>

  <div class="alert alert-danger">
    Publicar congela a versao e torna o processo disponivel para runtime. Nesta fase, o wizard apenas marca o processo como <strong>published</strong>.
  </div>

  <form method="post" action="/modules/bpm/wizard_steps/save.php?step=5" onsubmit="return confirm('Confirmar publicacao?');">
    <button class="btn primary">Publicar agora</button>
  </form>

  <div class="small text-muted" style="margin-top:10px;">
    Depois vamos plugar o publish completo: gerar bpm_process_version_element, validar formas e assignments, e criar assets/conectores oficiais.
  </div>
</div>
