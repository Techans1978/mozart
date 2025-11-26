<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../_lib/bpm_store.php';
$store = new BpmStore();
$state = $_SESSION['bpm_wizard'] ?? [];
$step = intval($_GET['step'] ?? 1);

switch ($step){
  case 1:
    $state['nome']=$_POST['nome']??''; $state['codigo']=$_POST['codigo']??''; $state['categoria']=$_POST['categoria']??''; break;
  case 2:
    $state['origem']=$_POST['origem']??'novo'; $state['ia_prompt']=$_POST['ia_prompt']??null;
    if (!empty($_FILES['upload']['tmp_name'])){
      $tmp = file_get_contents($_FILES['upload']['tmp_name']);
      $state['upload_tmp'] = substr($tmp,0,200000);
      if (stripos($_FILES['upload']['name'], '.bpmn') !== false){ $state['bpmn_xml'] = $tmp; }
    }
    if ($state['origem']==='ia' && !empty($state['ia_prompt'])){
      $state['bpmn_xml'] = "<bpmn:definitions><!-- placeholder IA --></bpmn:definitions>";
    }
    break;
  case 3:
    $state['acessos']['grupos']=array_filter(array_map('trim', explode(',', $_POST['grupos']??'')));
    $state['acessos']['papeis']=array_filter(array_map('trim', explode(',', $_POST['papeis']??'')));
    $state['acessos']['perfis']=array_filter(array_map('trim', explode(',', $_POST['perfis']??'')));
    break;
  case 5:
    $state['forms']=$_POST['forms']??[]; break;
  case 6:
    $issues=[]; $xml=$state['bpmn_xml']??'';
    if(!$xml) $issues[]='Nenhum diagrama carregado';
    if (strpos($xml, '<bpmn:endEvent')===false) $issues[]='Nenhum evento de fim encontrado';
    $state['teste_ia']['issues']=$issues?:['Nenhum problema crítico encontrado']; break;
  case 7:
    $state['teste_pessoa']['feedback']=$_POST['feedback']??''; break;
  case 8:
    $state['status']=$_POST['status']??'draft';
    $proc=[ 'id'=>$state['id']??null, 'name'=>$state['nome']??'Sem nome', 'code'=>$state['codigo']??null, 'category'=>$state['categoria']??null,
            'status'=>$state['status']??'draft', 'version'=>1, 'active'=>($state['status']??'draft')==='published',
            'bpmn_xml'=>$state['bpmn_xml']??'', 'forms'=>$state['forms']??[] ];
    $saved=$store->saveProcess($proc); $state['id']=$saved['id']; break;
}
$_SESSION['bpm_wizard'] = $state;
$next = min(8, $step+1); header('Location: /modules/bpm/wizard_bpm.php?step='.$next);
