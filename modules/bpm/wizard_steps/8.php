<?php /* Salvar/Concluir */ ?>
<div class="card"><h2>8) Salvar/Concluir</h2>
<form method="post" action="/modules/bpm/wizard_steps/save.php?step=8">
  <label>Status</label>
  <select name="status">
    <option value="draft" <?php echo ($state['status']==='draft'?'selected':''); ?>>Rascunho</option>
    <option value="published" <?php echo ($state['status']==='published'?'selected':''); ?>>Publicado</option>
  </select>
  <button class="btn primary">Salvar/Concluir</button>
</form>
<div class="nav" style="margin-top:8px">
  <a class="btn" href="<?php echo step_link(4); ?>">Abrir no Designer</a>
  <a class="btn" href="<?php echo step_link(6); ?>">Rodar Teste IA</a>
  <a class="btn" href="<?php echo step_link(7); ?>">Rodar Teste Pessoa</a>
</div>
</div>
