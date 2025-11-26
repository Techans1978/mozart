<?php
// includes/hd_audit.php
// Auditoria campo-a-campo: use audit_log_changes($conn, 'tabela', $id, $old, $new, $user_id)

if (!function_exists('audit_log')) {
  function audit_log(mysqli $conn, string $table, int $record_id, string $field, $old, $new, int $user_id, string $note = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $sql = "INSERT INTO hd_audit_log (table_name, record_id, field_name, old_value, new_value, changed_by, ip_address, user_agent, note)
            VALUES (?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $old_s = is_scalar($old) || is_null($old) ? (string)$old : json_encode($old, JSON_UNESCAPED_UNICODE);
    $new_s = is_scalar($new) || is_null($new) ? (string)$new : json_encode($new, JSON_UNESCAPED_UNICODE);
    $stmt->bind_param('sisssisss', $table, $record_id, $field, $old_s, $new_s, $user_id, $ip, $ua, $note);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

if (!function_exists('audit_log_changes')) {
  /**
   * $old e $new são arrays associativos (campo => valor)
   */
  function audit_log_changes(mysqli $conn, string $table, int $record_id, array $old, array $new, int $user_id, string $note = null) {
    $all_keys = array_unique(array_merge(array_keys($old), array_keys($new)));
    foreach ($all_keys as $k) {
      $ov = $old[$k] ?? null;
      $nv = $new[$k] ?? null;
      if ($ov !== $nv) {
        audit_log($conn, $table, $record_id, $k, $ov, $nv, $user_id, $note);
      }
    }
  }
}
