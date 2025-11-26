<?php /* Formulários */ ?>
<div class="card"><h2>5) Formulários</h2>
<p>Associe Form-JS às user tasks. Para protótipo, detectamos tasks pelo XML salvo no estado do Wizard (quando habilitarmos a leitura do publicado).</p>
<form method="post" action="/modules/bpm/wizard_steps/save.php?step=5">
<?php
  $tasks=[]; if(!empty($state['bpmn_xml'])){
    if (preg_match_all('/<bpmn:userTask[^>]*id="([^"]+)"[^>]*name="([^"]*)"/', $state['bpmn_xml'], $m)){
      for($i=0;$i<count($m[1]);$i++){ $tasks[]=['id'=>$m[1][$i],'name'=>$m[2][$i]]; }
    }
  }
  if(!$tasks){ $tasks=[['id'=>'UserTask_1','name'=>'Tarefa do Usuário A']]; }
?>
<table class="table"><tr><th>Tarefa</th><th>Form vinculado (key)</th></tr>
<?php foreach($tasks as $t): $key=$state['forms'][$t['id']]??''; ?>
<tr><td><?php echo htmlspecialchars($t['name']?:$t['id']); ?></td>
<td><input name="forms[<?php echo $t['id']; ?>]" value="<?php echo htmlspecialchars($key); ?>" placeholder="ex: form.solicitacao"></td></tr>
<?php endforeach; ?>
</table>
<button class="btn primary">Salvar</button>
</form></div>
