<?php
require_once __DIR__ . '/../_partials/helpers.php';
require_once __DIR__ . '/ConnectorRunner.php';
require_once __DIR__ . '/Secrets.php';
require_once __DIR__ . '/JsonPath.php';
require_once __DIR__ . '/FtpHelper.php';
require_once __DIR__ . '/FluigClient.php';
require_once __DIR__ . '/EmailHelper.php';
class MozartFlowRunner {
  public function __construct(private mysqli $db) {}
  private function log($a,$b,$c,$d,$e=null){}
  private function getVersion($id){return ['spec_json'=>'{"nodes":[{"id":"start","type":"start"},{"id":"end","type":"end"}],"edges":[{"source":"start","target":"end"}]}'];}
  private function createRun($a,$b,$c,$d){return 1;}
  private function finishRun($a,$b,$c,$d){}
  public function run(int $flowVersionId, array $varsIn, string $env='dev'): array { return ['status'=>'ok','data'=>[]]; }
  private function execNode(int $runId, array $node, array $ctx): array {
    // RUNIF / DELAY
    $conf = $node['config'] ?? [];
    if (isset($conf['runIf']) && is_array($conf['runIf'])) {
      $srcVar = $conf['runIf']['sourceVar'] ?? 'last';
      $src = $ctx['vars'][$srcVar] ?? null;
      $jp = $conf['runIf']['jsonpath'] ?? null;
      $val = $jp ? JsonPath::get($src, $jp) : null;
      $equals = $conf['runIf']['equals'] ?? null; $exists = $conf['runIf']['exists'] ?? null;
      $allow = true;
      if ($equals !== null) $allow = ($val === $equals);
      if ($exists !== null) { $allow = $exists ? ($val !== null) : ($val === null); }
      if (!$allow) { $this->log($runId, $node['id'],'info','runIf skipped'); return ['status'=>'ok','ctx'=>$ctx]; }
    }
    if (isset($conf['delayMs'])) { usleep(((int)$conf['delayMs'])*1000); }
 return ['status'=>'ok','ctx'=>$ctx]; }
  private function nodeScript(int $runId, array $node, array $ctx): array { $conf=$node['config']??[]; $code=$conf['code']??'return $vars;'; $vars=$ctx['vars']??[]; try{$fn=function($vars) use ($code){ return eval($code); }; $res=$fn($vars); $ctx['vars'][$conf['outputVar']??'script']=$res; return ['status'=>'ok','ctx'=>$ctx]; }catch(\Throwable $e){ return ['status':'error','error'=>$e->getMessage(),'ctx'=>$ctx]; }}
  private function nodeEmail(int $runId, array $node, array $ctx): array { $conf=$node['config']??[]; $to=$conf['to']??($ctx['vars'][$conf['toVar']??'to']??''); $subject=$conf['subject']??''; $body=$conf['body']??''; $from=$conf['from']??null; $res=EmailHelper::send((string)$to,(string)$subject,(string)$body,$from,[]); if(!($res['ok']??false)) return ['status'=>'error','error'=>'email error','ctx'=>$ctx]; $ctx['vars'][$conf['outputVar']??'email']=['sent'=>True]; return ['status':'ok','ctx'=>$ctx]; }
  private function nodeCode(int $runId, array $node, array $ctx): array { $conf=$node['config']??[]; $lang=strtolower($conf['lang']??'php'); if($lang==='php'){ $node2=$node; $node2['config']['outputVar']=$conf['outputVar']??'code'; $node2['config']['code']=$conf['code']??'return $vars;'; return $this->nodeScript($runId,$node2,$ctx);} $ctx['vars'][$conf['outputVar']??'code']=['warning'=>'lang not implemented']; return ['status':'ok','ctx'=>$ctx]; }
}
