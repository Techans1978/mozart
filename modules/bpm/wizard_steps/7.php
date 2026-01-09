<?php /* Step 7: Concluir */ ?>
<div class="card">
  <h2>7) Concluir</h2>

  <div class="alert alert-success">
    Wizard finalizado. A partir daqui voce ja pode ir para o Designer e/ou para o Runtime.
  </div>

  <div class="row">
    <div class="col-md-6">
      <h3>Atalhos</h3>
      <ul>
        <li><a href="<?php echo BASE_URL; ?>/modules/bpm/bpm_designer.php">Ir para o Designer</a></li>
        <li><a href="<?php echo BASE_URL; ?>/modules/bpm/processos-listar.php">Ver processos</a></li>
        <li><a href="<?php echo BASE_URL; ?>/modules/bpm/runtime.php">Ir para runtime</a></li>
      </ul>
    </div>

    <div class="col-md-6">
      <h3>Limpar estado do Wizard</h3>
      <form method="post" action="/modules/bpm/wizard_steps/save.php?step=7" onsubmit="return confirm('Limpar estado do wizard?');">
        <button class="btn btn-default">Limpar wizard</button>
      </form>
    </div>
  </div>

</div>
