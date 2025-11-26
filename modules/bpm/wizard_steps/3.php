<?php /* Acessos */ ?>
<div class="card"><h2>3) Acessos</h2>
<form method="post" action="/modules/bpm/wizard_steps/save.php?step=3">
  <div class="row">
    <div class="col"><label>Grupos</label><input name="grupos" value="<?php echo htmlspecialchars(implode(',', $state['acessos']['grupos']??[])); ?>"></div>
    <div class="col"><label>Papéis</label><input name="papeis" value="<?php echo htmlspecialchars(implode(',', $state['acessos']['papeis']??[])); ?>"></div>
    <div class="col"><label>Perfis</label><input name="perfis" value="<?php echo htmlspecialchars(implode(',', $state['acessos']['perfis']??[])); ?>"></div>
  </div>
  <button class="btn primary">Salvar</button>
</form></div>
