<?php /* Desenho — usa o designer existente */ ?>
<div class="card">
  <h2>3) Desenho (usando o seu bpm_designer.php)</h2>

  <div class="nav" style="margin-bottom:8px">
    <a class="btn"
       href="/modules/bpm/bpm_designer.php?from=wizard&return=/modules/bpm/wizard_bpm.php%3Fstep=4"
       target="_blank">Abrir Designer em nova aba</a>
  </div>

  <div style="height:560px; border:1px solid #1f2745; border-radius:12px; overflow:hidden; background:#0c1226">
    <iframe src="/modules/bpm/designer-wizard.php?from=wizard"
            title="BPM Designer"
            style="width:100%; height:100%; border:0;"></iframe>
  </div>

  <p class="small">
    O Wizard não injeta bpmn-js; toda edição ocorre no seu Designer.
    Depois de salvar/publicar, volte para os próximos passos.
  </p>
</div>
