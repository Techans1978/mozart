<?php /* Teste por IA (mock) */ ?>
<div class="card"><h2>6) Teste por IA (simulado)</h2>
<p>Mock de relatório (vamos plugar seu analisador depois).</p>
<form method="post" action="/modules/bpm/wizard_steps/save.php?step=6"><button class="btn" name="run" value="1">Rodar análise</button></form>
<?php if (!empty($state['teste_ia']['issues'])): ?>
<div class="card"><ul><?php foreach($state['teste_ia']['issues'] as $i): ?><li><?php echo htmlspecialchars($i); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
