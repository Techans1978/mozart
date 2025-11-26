<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . 'system/includes/autenticacao.php';
require_once ROOT_PATH . 'system/config/connect.php';

proteger_pagina();

if (empty($_SESSION['user_id'])) {
    die('Erro: Usuário não autenticado.');
}
$user_id = (int)$_SESSION['user_id'];

// Helpers
function so_digitos(string $s): string { return preg_replace('/\D+/', '', $s); }
function fail_stmt(mysqli $conn, $stmt, string $msgPrefix) {
    if (!$stmt) { die($msgPrefix . $conn->error); }
    return $stmt;
}

$erros = [];
$sucesso = null;

// Carrega dados atuais
$stmt = $conn->prepare("SELECT id, username, nome_completo, cpf, cargo, email, telefone, nivel_acesso FROM usuarios WHERE id = ? LIMIT 1");
fail_stmt($conn, $stmt, "Erro ao preparar SELECT: ");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$usuario = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$usuario) { die("Erro: Usuário não encontrado."); }

// Processa POST (atualização do próprio perfil)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebe
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $cpfMask       = trim($_POST['cpf'] ?? '');
    $cargo         = trim($_POST['cargo'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $telMask       = trim($_POST['telefone'] ?? '');

    // Normaliza
    $cpf      = so_digitos($cpfMask);
    $telefone = so_digitos($telMask);

    // Valida
    if ($nome_completo === '') $erros[] = 'Nome completo é obrigatório.';
    if (!preg_match('/^\d{11}$|^\d{14}$/', $cpf)) $erros[] = 'Documento inválido. Informe CPF (11) ou CNPJ (14) dígitos.';
    if ($cargo === '') $erros[] = 'Cargo é obrigatório.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';

    // Unicidade: cpf e email não podem existir em outro usuário
    if (!$erros) {
        $s = $conn->prepare("SELECT id FROM usuarios WHERE cpf = ? AND id <> ? LIMIT 1");
        fail_stmt($conn, $s, "Erro ao preparar verificação CPF: ");
        $s->bind_param('si', $cpf, $user_id);
        $s->execute(); $r = $s->get_result(); $dup = $r && $r->fetch_assoc(); $s->close();
        if ($dup) $erros[] = 'Já existe um usuário com esse CPF/CNPJ.';

        if (!$erros) {
            $s = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1");
            fail_stmt($conn, $s, "Erro ao preparar verificação e-mail: ");
            $s->bind_param('si', $email, $user_id);
            $s->execute(); $r = $s->get_result(); $dup = $r && $r->fetch_assoc(); $s->close();
            if ($dup) $erros[] = 'Já existe um usuário com esse e-mail.';
        }
    }

    // Atualiza
    if (!$erros) {
        $u = $conn->prepare("
            UPDATE usuarios
               SET nome_completo = ?, cpf = ?, cargo = ?, email = ?, telefone = ?
             WHERE id = ?
             LIMIT 1
        ");
        fail_stmt($conn, $u, "Erro ao preparar UPDATE: ");
        $u->bind_param('sssssi', $nome_completo, $cpf, $cargo, $email, $telefone, $user_id);

        if ($u->execute()) {
            $sucesso = 'Perfil atualizado com sucesso!';
            // Recarrega dados para refletir alterações no form
            $usuario['nome_completo'] = $nome_completo;
            $usuario['cpf']           = $cpf;
            $usuario['cargo']         = $cargo;
            $usuario['email']         = $email;
            $usuario['telefone']      = $telefone;
        } else {
            $erros[] = 'Erro ao atualizar perfil: ' . $u->error;
        }
        $u->close();
    }
}

include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>

<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <?php if (!empty($erros)): ?>
      <div class="alert alert-danger">
        <strong>Erros:</strong>
        <ul style="margin:0;padding-left:18px">
          <?php foreach ($erros as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php elseif ($sucesso): ?>
      <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <div class="row">
      <div class="col-lg-12">
        <h2>Meu Perfil</h2>
        <form method="post" onsubmit="return confirmarAlteracao();">

          <!-- Username (somente leitura) -->
          <div class="form-group">
            <label for="username">Username:</label>
            <div class="form-group input-group">
              <span class="input-group-addon">@</span>
              <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($usuario['username']) ?>" readonly>
            </div>
          </div>

          <div class="form-group">
            <label for="nome_completo">Nome Completo:</label>
            <input class="form-control" type="text" id="nome_completo" name="nome_completo"
                   value="<?= htmlspecialchars($usuario['nome_completo']) ?>" required>
          </div>

          <div class="form-group">
            <label for="cpf">CPF/CNPJ:</label>
            <input class="form-control" type="text" id="cpf" name="cpf"
                   value="<?= htmlspecialchars($usuario['cpf']) ?>" inputmode="numeric"
                   placeholder="000.000.000-00 ou 00.000.000/0000-00" required>
          </div>

          <div class="form-group">
            <label for="cargo">Cargo:</label>
            <input class="form-control" type="text" id="cargo" name="cargo"
                   value="<?= htmlspecialchars($usuario['cargo']) ?>" required>
          </div>

          <div class="form-group">
            <label for="email">E-mail:</label>
            <input class="form-control" type="text" id="email" name="email"
                   value="<?= htmlspecialchars($usuario['email']) ?>" required>
          </div>

          <div class="form-group">
            <label for="telefone">Telefone:</label>
            <input class="form-control" type="text" id="telefone" name="telefone"
                   value="<?= htmlspecialchars($usuario['telefone']) ?>" placeholder="Telefone ou Celular" required>
            <p class="help-block">Somente números.</p>
          </div>

          <button type="submit" class="btn btn-primary">Atualizar Perfil</button>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
// Confirmação no submit
function confirmarAlteracao() {
  return confirm("Deseja realmente alterar seu perfil?");
}

// Máscara CPF/CNPJ
document.getElementById('cpf').addEventListener('input', function () {
  let v = this.value.replace(/\D/g, '');
  if (v.length <= 11) {
    v = v.slice(0, 11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  } else {
    v = v.slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
  }
  this.value = v;
});

// Máscara telefone
document.getElementById('telefone').addEventListener('input', function () {
  let v = this.value.replace(/\D/g, '').slice(0, 11);
  if (v.length <= 10) {
    v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
  } else {
    v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
  }
  this.value = v;
});

// E-mail: remove espaços e vírgulas
document.getElementById('email').addEventListener('input', function () {
  this.value = this.value.replace(/\s/g, '').replace(/[,;]/g, '');
});
</script>

<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
<?php
$conn->close();
?>
