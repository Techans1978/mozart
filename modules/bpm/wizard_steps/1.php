<?php /* Categoria */ ?>
<div class="card"><h2>1) Informacoes</h2>
<form method="post" action="/modules/bpm/wizard_steps/save.php?step=1">
  <div class="row">
    <div class="col-12 col-md-12">
      <label>Nome do processo</label>
      <input name="nome"
             value="<?php echo htmlspecialchars($state['nome'] ?? ''); ?>"
             required>
    </div>
    <div class="col-12 col-md-12">
      <label>Código</label>
      <input name="codigo"
             value="<?php echo htmlspecialchars($state['codigo'] ?? ''); ?>">
    </div>
  </div>

  <div class="row">
    <div class="col-12 col-md-12">
      <label>Categoria</label>
      <select name="categoria_id" required>
        <option value="">Selecione...</option>
        <?php
        $selectedCatId = isset($state['categoria_id']) ? (int)$state['categoria_id'] : 0;
        foreach ($categorias as $cat):
            $id   = (int)$cat['id'];
            $nome = $cat['nome'];
            $sel  = $id === $selectedCatId ? 'selected' : '';
        ?>
          <option value="<?php echo $id; ?>" <?php echo $sel; ?>>
            <?php echo htmlspecialchars($nome); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <button class="btn primary">Salvar e continuar</button>
</form>
</div>
