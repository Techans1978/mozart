<?php /* Origem */ ?>
<div class="card"><h2>2) Origem do fluxo</h2>
<form method="post" enctype="multipart/form-data" action="/modules/bpm/wizard_steps/save.php?step=2">
  <label><input type="radio" name="origem" value="novo"   <?php echo ($state['origem']==='novo'?'checked':''); ?>> Criar novo</label><br>
  <label><input type="radio" name="origem" value="ia"     <?php echo ($state['origem']==='ia'?'checked':''); ?>> Criar completo por IA</label><br>
  <label><input type="radio" name="origem" value="fluig"  <?php echo ($state['origem']==='fluig'?'checked':''); ?>> Importar Fluig (.bpmn/.json/zip)</label><br>
  <label><input type="radio" name="origem" value="camunda"<?php echo ($state['origem']==='camunda'?'checked':''); ?>> Importar Camunda (.bpmn)</label>
  <hr>
  <div class="row">
    <div class="col"><label>Descrição (se IA)</label><textarea name="ia_prompt"><?php echo htmlspecialchars($state['ia_prompt']??''); ?></textarea></div>
    <div class="col"><label>Upload opcional</label><input type="file" name="upload"><div class="small">.bpmn, .json ou zip do Fluig</div></div>
  </div>
  <button class="btn primary">Processar</button>
</form></div>
