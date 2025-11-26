<?php
class EmailHelper {
  public static function send(string $to, string $subject, string $body, ?string $from=null, array $headers=[]): array {
    $hdrs = '';
    if ($from) $headers['From'] = $from;
    foreach ($headers as $k=>$v) $hdrs .= $k.': '.$v."\r\n";
    $ok = @mail($to, $subject, $body, $hdrs);
    return $ok ? ['ok'=>true] : ['ok'=>false,'error'=>'mail() failed'];
  }
}
