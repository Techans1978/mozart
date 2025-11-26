<?php /* Categoria */ ?>
<div class="card"><h2>1) Categoria</h2>
<form method="post" action="/modules/bpm/wizard_steps/save.php?step=1">
  <div class="row">
    <div class="col"><label>Nome do processo</label><input name="nome" value="<?php echo htmlspecialchars($state['nome']??''); ?>" required></div>
    <div class="col"><label>Código</label><input name="codigo" value="<?php echo htmlspecialchars($state['codigo']??''); ?>"></div>
  </div>
  <div class="row"><div class="col"><label>Categoria</label><input name="categoria" value="<?php echo htmlspecialchars($state['categoria']??''); ?>" required></div></div>
  <button class="btn primary">Salvar e continuar</button>
</form></div>
