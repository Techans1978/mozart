<?php
// public/modules/orquestrador_api/includes/FluigClient.php
require_once __DIR__ . '/Secrets.php';

class FluigClient {
  public function __construct(private mysqli $db, private string $baseUrl, private string $tokenSecretKeyName='FLUIG_TOKEN', private string $env='dev'){}

  private function token(): ?string {
    $cofre = new SecretsCofre($this->db);
    return $cofre->get($this->env, 'global', null, $this->tokenSecretKeyName);
  }

  private function req(string $method, string $path, $payload=null): array {
    $url = rtrim($this->baseUrl,'/').'/'.ltrim($path,'/');
    $ch = curl_init();
    $hdrs = ['Content-Type: application/json'];
    $tok = $this->token(); if ($tok) $hdrs[] = 'Authorization: Bearer '.$tok;
    curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>$hdrs]);
    if ($payload!==null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload)?$payload:json_encode($payload, JSON_UNESCAPED_UNICODE));
    $resp = curl_exec($ch);
    if ($resp===false){ $err=curl_error($ch); curl_close($ch); return ['ok'=>false,'error'=>$err]; }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $ok = $status>=200 && $status<400;
    $json = json_decode($resp,true);
    return ['ok'=>$ok,'status'=>$status,'data'=>$json ?? $resp];
  }

  public function abrir(array $data): array {
    // Ajuste o path conforme seu Fluig (exemplo)
    return $this->req('POST', '/api/public/2.0/workflows/start', $data);
  }
  public function acompanhar(int $caseId): array {
    return $this->req('GET', '/api/public/2.0/workflows/'+$caseId, null);
  }
  public function fechar(int $caseId, array $data=[]): array {
    return $this->req('POST', '/api/public/2.0/workflows/finish', ['caseId'=>$caseId] + $data);
  }
}
