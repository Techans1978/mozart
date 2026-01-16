<?php
// system/lib/auditoria.php
// Requer: $conn (mysqli) já disponível no escopo

function auditoria_log(mysqli $conn, array $e): void {
  // Campos aceitos
  $action    = trim((string)($e['action'] ?? ''));
  if ($action === '') return;

  $module    = $e['module']    ?? null;
  $entity    = $e['entity']    ?? null;
  $entity_id = isset($e['entity_id']) ? (string)$e['entity_id'] : null;
  $details   = $e['details']   ?? null;
  $severity  = $e['severity']  ?? 'info';

  // User (tenta pegar da sessão)
  $user_id   = $e['user_id']   ?? ($_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null);
  $user_name = $e['user_name'] ?? ($_SESSION['user_name'] ?? $_SESSION['usuario'] ?? $_SESSION['login'] ?? null);

  // IP/UA
  $ip        = $e['ip']        ?? ($_SERVER['REMOTE_ADDR'] ?? null);
  $ua        = $e['user_agent']?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

  // Details: se array/obj, vira JSON
  if (is_array($details) || is_object($details)) {
    $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } else {
    $details = ($details !== null) ? (string)$details : null;
  }

  $stmt = $conn->prepare("
    INSERT INTO system_auditoria
      (user_id, user_name, action, module, entity, entity_id, ip, user_agent, details, severity)
    VALUES
      (?,?,?,?,?,?,?,?,?,?)
  ");
  if (!$stmt) return;

  $stmt->bind_param(
    "isssssssss",
    $user_id,
    $user_name,
    $action,
    $module,
    $entity,
    $entity_id,
    $ip,
    $ua,
    $details,
    $severity
  );
  @$stmt->execute();
  $stmt->close();
}
