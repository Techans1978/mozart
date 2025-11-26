<?php /* Teste com Pessoa */ ?>
<div class="card"><h2>7) Teste com Pessoa</h2>
<form method="post" action="/modules/bpm/wizard_steps/save.php?step=7">
  <label>Feedback</label>
  <textarea name="feedback"><?php echo htmlspecialchars($state['teste_pessoa']['feedback']??''); ?></textarea>
  <button class="btn">Salvar feedback</button>
</form></div>
