<?php
// includes/hd_rbac.php
// Funções utilitárias de RBAC (roles/permissões)

if (!function_exists('hd_rbac_user_perms')) {
  function hd_rbac_user_perms(mysqli $conn, int $user_id): array {
    $perms = [];
    $sql = "SELECT p.code
              FROM hd_user_role ur
              JOIN hd_role_perm rp ON rp.role_id = ur.role_id
              JOIN hd_permission p ON p.id = rp.perm_id
             WHERE ur.user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        $perms[$row['code']] = true;
      }
      $stmt->close();
    }
    return $perms;
  }
}

if (!function_exists('hd_can')) {
  function hd_can(mysqli $conn, int $user_id, string $perm_code): bool {
    static $cache = [];
    $key = $user_id.':'.$perm_code;
    if (isset($cache[$key])) return $cache[$key];

    $sql = "SELECT 1
              FROM hd_user_role ur
              JOIN hd_role_perm rp ON rp.role_id = ur.role_id
              JOIN hd_permission p ON p.id = rp.perm_id
             WHERE ur.user_id = ? AND p.code = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('is', $user_id, $perm_code);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    $cache[$key] = $ok;
    return $ok;
  }
}

if (!function_exists('hd_require')) {
  function hd_require(mysqli $conn, int $user_id, string $perm_code) {
    if (!hd_can($conn, $user_id, $perm_code)) {
      http_response_code(403);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['success'=>false,'error'=>'forbidden','message'=>"Permissão negada: $perm_code"]);
      exit;
    }
  }
}
