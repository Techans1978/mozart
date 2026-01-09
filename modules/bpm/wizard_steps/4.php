<?php /* Step 4: Salvar rascunho */ ?>
<div class="card">
  <h2>4) Salvar rascunho</h2>

  <?php if (!empty($state['id'])): ?>
    <div class="alert alert-info">
      Processo em edicao: <strong>#<?php echo (int)$state['id']; ?></strong>
      | Status atual: <strong><?php echo htmlspecialchars($state['status'] ?? 'draft'); ?></strong>
    </div>
  <?php endif; ?>

  <div class="alert alert-warning">
    Neste passo voce salva o rascunho no banco e mantem o status como <strong>draft</strong>.
  </div>

  <form method="post" action="/modules/bpm/wizard_steps/save.php?step=4">
    <button class="btn primary">Salvar rascunho</button>
  </form>

  <div class="small text-muted" style="margin-top:10px;">
    Dica: o desenho (BPMN) deve estar salvo no designer antes de salvar o rascunho.
  </div>
</div>
