<?php
// public/modules/orquestrador_api/includes/FtpHelper.php
class FtpHelper {
  public static function list(array $cfg): array {
    $proto = $cfg['proto'] ?? 'ftp';
    if ($proto === 'sftp') return self::sftpList($cfg);
    return self::ftpList($cfg);
  }
  public static function get(array $cfg): array {
    $proto = $cfg['proto'] ?? 'ftp';
    if ($proto === 'sftp') return self::sftpGet($cfg);
    return self::ftpGet($cfg);
  }
  public static function put(array $cfg, string $contents): array {
    $proto = $cfg['proto'] ?? 'ftp';
    if ($proto === 'sftp') return self::sftpPut($cfg, $contents);
    return self::ftpPut($cfg, $contents);
  }
  public static function move(array $cfg): array {
    $proto = $cfg['proto'] ?? 'ftp';
    if ($proto === 'sftp') return self::sftpMove($cfg);
    return self::ftpMove($cfg);
  }

  private static function ftpConn($cfg){
    $conn = ftp_connect($cfg['host'], (int)($cfg['port'] ?? 21), 15);
    if(!$conn) return [null,'connect failed'];
    if (!ftp_login($conn, $cfg['user'], $cfg['pass'])) return [null,'auth failed'];
    ftp_pasv($conn, (bool)($cfg['passive'] ?? true));
    return [$conn,null];
  }

  private static function ftpList($cfg){
    [$c,$err] = self::ftpConn($cfg); if(!$c) return ['ok'=>false,'error'=>$err];
    $path = $cfg['remote_path'] ?? '.';
    $files = ftp_nlist($c, $path);
    ftp_close($c);
    return ['ok'=>true,'files'=>$files?:[]];
  }
  private static function ftpGet($cfg){
    [$c,$err] = self::ftpConn($cfg); if(!$c) return ['ok'=>false,'error'=>$err];
    $remote = $cfg['remote_path'] ?? '';
    $tmp = tmpfile(); $meta = stream_get_meta_data($tmp); $tmpPath = $meta['uri'];
    $ok = ftp_get($c, $tmpPath, $remote, FTP_BINARY);
    $data = $ok ? file_get_contents($tmpPath) : null;
    fclose($tmp); ftp_close($c);
    return $ok ? ['ok'=>true,'data'=>$data] : ['ok'=>false,'error'=>'download failed'];
    }
  private static function ftpPut($cfg, $contents){
    [$c,$err] = self::ftpConn($cfg); if(!$c) return ['ok'=>false,'error'=>$err];
    $remote = $cfg['remote_path'] ?? '';
    $tmp = tmpfile(); fwrite($tmp, $contents); $meta = stream_get_meta_data($tmp); $tmpPath = $meta['uri'];
    $ok = ftp_put($c, $remote, $tmpPath, FTP_BINARY);
    fclose($tmp); ftp_close($c);
    return $ok ? ['ok'=>true] : ['ok'=>false,'error'=>'upload failed'];
  }
  private static function ftpMove($cfg){
    [$c,$err] = self::ftpConn($cfg); if(!$c) return ['ok'=>false,'error'=>$err];
    $from = $cfg['from'] ?? ''; $to = $cfg['to'] ?? '';
    $ok = ftp_rename($c, $from, $to);
    ftp_close($c);
    return $ok ? ['ok'=>true] : ['ok'=>false,'error'=>'move failed'];
  }

  // SFTP via phpseclib (se disponível)
  private static function sftpConn($cfg){
    if (!class_exists('\\phpseclib3\\Net\\SFTP')) return [null,'phpseclib3 not installed'];
    $sftp = new \phpseclib3\Net\SFTP($cfg['host'], (int)($cfg['port'] ?? 22), 15);
    $ok = $sftp->login($cfg['user'], $cfg['pass']);
    return $ok ? [$sftp, null] : [null, 'auth failed'];
  }
  private static function sftpList($cfg){
    [$sftp,$err] = self::sftpConn($cfg); if(!$sftp) return ['ok'=>false,'error'=>$err];
    $path = $cfg['remote_path'] ?? '.';
    $files = $sftp->nlist($path);
    return ['ok'=>true,'files'=>$files?:[]];
  }
  private static function sftpGet($cfg){
    [$sftp,$err] = self::sftpConn($cfg); if(!$sftp) return ['ok'=>false,'error'=>$err];
    $remote = $cfg['remote_path'] ?? '';
    $data = $sftp->get($remote);
    return $data !== false ? ['ok'=>true,'data'=>$data] : ['ok'=>false,'error'=>'download failed'];
  }
  private static function sftpPut($cfg, $contents){
    [$sftp,$err] = self::sftpConn($cfg); if(!$sftp) return ['ok'=>false,'error'=>$err];
    $remote = $cfg['remote_path'] ?? '';
    $ok = $sftp->put($remote, $contents);
    return $ok ? ['ok'=>true] : ['ok'=>false,'error'=>'upload failed'];
  }
  private static function sftpMove($cfg){
    [$sftp,$err] = self::sftpConn($cfg); if(!$sftp) return ['ok'=>false,'error'=>$err];
    $from = $cfg['from'] ?? ''; $to = $cfg['to'] ?? '';
    $ok = $sftp->rename($from, $to);
    return $ok ? ['ok'=>true] : ['ok'=>false,'error'=>'move failed'];
  }
}
