<?php
// Mostrar erros (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
// Autenticação e conexão
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // expõe $conn (mysqli)

// ---------- Helpers ----------
function so_digitos(string $s): string { return preg_replace('/\D+/', '', $s); }
function fail_stmt(mysqli $conn, $stmt, string $msgPrefix) {
  if (!$stmt) { die($msgPrefix . $conn->error); }
  return $stmt;
}
function gerarSenha($tamanho = 10) {
  return substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789@#\$!"), 0, $tamanho);
}
/** Valida se a lista contém pai e filho ao mesmo tempo (closure table) */
function validaHierarquiaSelecionada(mysqli $conn, array $ids, string $tabelaPaths): ?string {
  $ids = array_values(array_unique(array_map('intval', $ids)));
  if (count($ids) <= 1) return null;

  // Só valida se a tabela de paths existir
  $tbl = $conn->real_escape_string($tabelaPaths);
  $existe = $conn->query("SHOW TABLES LIKE '{$tbl}'");
  if (!$existe || $existe->num_rows === 0) return null;

  $in = implode(',', $ids);
  $sql = "SELECT 1 FROM {$tabelaPaths}
          WHERE depth >= 1
            AND ancestor_id IN ($in)
            AND descendant_id IN ($in)
          LIMIT 1";
  $res = $conn->query($sql);
  if ($res && $res->num_rows > 0) {
    return "Não é permitido selecionar simultaneamente um item e seu pai/filho.";
  }
  return null;
}

$sugestao_senha = gerarSenha();
$erros = [];
$sucesso = null;



// -------------------- POST (salvar) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Campos conforme sua tabela `usuarios`
  $username      = preg_replace('/\s+/u', '', trim($_POST['username']       ?? ''));
  $nome_completo = trim($_POST['nome_completo']  ?? '');
  $cpfMask       = trim($_POST['cpf']            ?? '');
  $cargo         = trim($_POST['cargo']          ?? '');
  $email         = trim($_POST['email']          ?? '');
  $telefoneMask  = trim($_POST['telefone']       ?? '');
  $nivel_acesso  = trim($_POST['nivel_acesso']   ?? 'usuario');
  $ativo         = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1; // 1/0
  $senha         = $_POST['senha']               ?? '';
  $confirmar     = $_POST['confirmarSenha']      ?? '';

  $grupos_ids = array_map('intval', $_POST['grupos_ids'] ?? []);
  $perfis_ids = array_map('intval', $_POST['perfis_ids'] ?? []);

  // Normalizações
  $cpf      = so_digitos($cpfMask);
  $telefone = so_digitos($telefoneMask);

  // Validações
  if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
    $erros[] = 'Username inválido. Use 3–30 caracteres: letras, números, ponto, traço ou sublinhado.';
  }
  if ($nome_completo === '') $erros[] = 'Nome completo é obrigatório.';
  if ($cpf === '' || !preg_match('/^\d{11}$|^\d{14}$/', $cpf)) $erros[] = 'Documento inválido. Informe um CPF (11) ou CNPJ (14) dígitos.';
  if ($cargo === '') $erros[] = 'Cargo é obrigatório.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
  if ($senha === '' || $confirmar === '' || $senha !== $confirmar) $erros[] = 'Senha e confirmação precisam coincidir.';
  if (!(strlen($senha) >= 8 && preg_match('/[A-Z]/', $senha) && preg_match('/[\d\W_]/', $senha))) {
    $erros[] = 'Senha muito fraca. Use 8+ caracteres, com letra maiúscula e número/símbolo.';
  }

  // Checagens de unicidade
  if (!$erros) {
    // username
    $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE username = ? LIMIT 1"), "Erro ao preparar (username): ");
    $stmt->bind_param('s', $username);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $erros[] = 'Já existe um usuário com esse username.'; }
    $stmt->close();
  }
  if (!$erros) {
    // cpf
    $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE cpf = ? LIMIT 1"), "Erro ao preparar (cpf): ");
    $stmt->bind_param('s', $cpf);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $erros[] = 'Já existe um usuário com esse CPF/CNPJ.'; }
    $stmt->close();
  }
  if (!$erros) {
    // email
    $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE email = ? LIMIT 1"), "Erro ao preparar (email): ");
    $stmt->bind_param('s', $email);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $erros[] = 'Já existe um usuário com esse e-mail.'; }
    $stmt->close();
  }

  // Validação hierarquia dos selects
  if (!$erros) {
    if ($msg = validaHierarquiaSelecionada($conn, $grupos_ids, "grupos_paths")) $erros[] = $msg;
    if ($msg = validaHierarquiaSelecionada($conn, $perfis_ids, "perfis_paths")) $erros[] = $msg;
  }

  // Insert
  if (!$erros) {
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = fail_stmt(
      $conn,
      $conn->prepare("
        INSERT INTO usuarios
          (username, nome_completo, cpf, cargo, email, senha, telefone, nivel_acesso, ativo, data_cadastro, tentativas_login, bloqueado)
        VALUES
          (?,        ?,            ?,   ?,     ?,     ?,     ?,        ?,            ?,     NOW(),        0,               0)
      "),
      "Erro ao preparar INSERT: "
    );
    $stmt->bind_param(
      'ssssssssi',
      $username,
      $nome_completo,
      $cpf,
      $cargo,
      $email,
      $senha_hash,
      $telefone,
      $nivel_acesso,
      $ativo
    );

    if ($stmt->execute()) {
      $userId = $stmt->insert_id;
      $stmt->close();

      // Vincular grupos (is_primary=0 por padrão aqui; telas dedicadas tratam flag)
      if (!empty($grupos_ids)) {
        $insG = $conn->prepare("INSERT INTO usuarios_grupos (usuario_id, grupo_id, is_primary) VALUES (?, ?, 0)");
        foreach ($grupos_ids as $gid) {
          $gid = (int)$gid;
          if ($gid > 0) { $insG->bind_param('ii', $userId, $gid); $insG->execute(); }
        }
        $insG->close();
      }

      // Vincular perfis
      if (!empty($perfis_ids)) {
        $insP = $conn->prepare("INSERT INTO usuarios_perfis (usuario_id, perfil_id, is_primary) VALUES (?, ?, 0)");
        foreach ($perfis_ids as $pid) {
          $pid = (int)$pid;
          if ($pid > 0) { $insP->bind_param('ii', $userId, $pid); $insP->execute(); }
        }
        $insP->close();
      }

      $sucesso = 'Usuário cadastrado com sucesso!';
      // Limpa POST e seleção de múltiplos para a tela ficar zerada
      $_POST = [];
      $sel_grupos = [];
      $sel_perfis = [];
    } else {
      $erros[] = 'Erro ao inserir: ' . $stmt->error;
      $stmt->close();
    }
  }
}

// Cabeçalhos e navbar
include_once ROOT_PATH . '/system/includes/head.php';
include_once ROOT_PATH . '/system/includes/navbar.php';
?>
<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
        <h2>Cadastrar Usuário - <?= APP_NAME ?></h2>

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

        <form method="post" onsubmit="return validarSenha();">
          <!-- Username -->
          <div class="form-group">
            <label for="username">Username:</label>
            <div class="form-group input-group">
              <span class="input-group-addon">@</span>
              <input type="text" class="form-control" id="username" name="username"
                     placeholder="Não são aceitos espaços e acentos"
                     value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="nome_completo">Nome Completo:</label>
            <input class="form-control" type="text" id="nome_completo" name="nome_completo"
                   value="<?= htmlspecialchars($_POST['nome_completo'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="cpf">CPF/CNPJ:</label>
            <input class="form-control" inputmode="numeric"
                   placeholder="000.000.000-00 ou 00.000.000/0000-00"
                   type="text" id="cpf" name="cpf"
                   value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="cargo">Cargo:</label>
            <input class="form-control" type="text" id="cargo" name="cargo"
                   value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="email">E-mail:</label>
            <input class="form-control" type="text" id="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="telefone">Telefone:</label>
            <input class="form-control" type="text" id="telefone" name="telefone"
                   placeholder="Telefone ou Celular"
                   value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" required>
            <p class="help-block">Somente números.</p>
          </div>

          <div class="form-group">
            <label for="nivel_acesso">Nível de Acesso:</label>
            <select id="nivel_acesso" name="nivel_acesso" class="form-control">
              <option value="bigboss" <?= (($_POST['nivel_acesso'] ?? '')==='bigboss')?'selected':'' ?>>Big Boss (Super Admin)</option>
              <option value="admin"   <?= (($_POST['nivel_acesso'] ?? '')==='admin')?'selected':'' ?>>Administrador</option>
              <option value="gerente" <?= (($_POST['nivel_acesso'] ?? '')==='gerente')?'selected':'' ?>>Gerente</option>
              <option value="usuario" <?= (($_POST['nivel_acesso'] ?? 'usuario')==='usuario')?'selected':'' ?>>Usuário</option>
            </select>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Grupos (múltiplos)</label>
                <select class="form-control" name="grupos_ids[]" multiple size="8">
                  <?php foreach($grupos_opts as $g):
                    $sel = in_array((int)$g['id'], $sel_grupos) ? 'selected' : ''; ?>
                    <option value="<?= (int)$g['id'] ?>" <?= $sel ?>><?= htmlspecialchars($g['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="help-block">Segure Ctrl/Cmd para múltiplos. Regra: não selecionar pai e filho ao mesmo tempo.</p>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label>Perfis (múltiplos)</label>
                <select class="form-control" name="perfis_ids[]" multiple size="8">
                  <?php foreach($perfis_opts as $p):
                    $sel = in_array((int)$p['id'], $sel_perfis) ? 'selected' : ''; ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $sel ?>><?= htmlspecialchars($p['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="help-block">Regra: não selecionar pai e filho ao mesmo tempo.</p>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="ativo">Ativo:</label>
            <select id="ativo" name="ativo" class="form-control">
              <option value="1" <?= (($_POST['ativo'] ?? '1')==='1')?'selected':'' ?>>Sim</option>
              <option value="0" <?= (($_POST['ativo'] ?? '')==='0')?'selected':'' ?>>Não</option>
            </select>
          </div>

          <div class="form-group">
            <label for="senha">Senha:</label>
            <div class="input-group">
              <input class="form-control" type="password" id="senha" name="senha" oninput="avaliarSenha()" required>
              <span class="input-group-addon" style="cursor:pointer;" onclick="toggleSenha()">👁️</span>
            </div>
          </div>

          <div class="form-group">
            <label for="confirmarSenha">Confirmar Senha:</label>
            <div class="input-group">
              <input class="form-control" type="password" id="confirmarSenha" name="confirmarSenha" required>
              <span class="input-group-addon" style="cursor:pointer;" onclick="toggleConfSenha()">👁️</span>
            </div>
          </div>

          <div class="form-group">
            <div class="barra"><div id="forcaBarra" class="forca" style="width:0%; background-color:red; height:8px;"></div></div>
            <p class="help-block"><div id="porcentagem">Força da senha: 0%</div></p>
            <p class="help-block">Sua senha sugerida é:
              <code id="senhaSugerida"><?= $sugestao_senha ?></code>
              <button class="btn btn-default" type="button" onclick="usarSenha()">Usar senha sugerida</button>
            </p>
          </div>

          <button type="submit" class="btn btn-primary">Registrar Usuário</button>
          <button type="reset" class="btn btn-default">Limpar Formulário</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Variável global usada em validarSenha()
let forcaAtual = 0;

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

function usarSenha() {
  const senha = document.getElementById("senhaSugerida").textContent;
  document.getElementById("senha").value = senha;
  document.getElementById("confirmarSenha").value = senha;
  avaliarSenha();
}

function avaliarSenha() {
  const senha = document.getElementById("senha").value;
  const barra = document.getElementById("forcaBarra");
  const texto = document.getElementById("porcentagem");

  let forca = 0;
  if (senha.length >= 8)  forca += 25;
  if (/[A-Z]/.test(senha)) forca += 20;
  if (/[0-9]/.test(senha)) forca += 20;
  if (/[\W_]/.test(senha)) forca += 20;
  if (senha.length >= 12) forca += 15;

  forca = Math.min(forca, 100);
  forcaAtual = forca;

  barra.style.width = forca + "%";
  texto.textContent = "Força da senha: " + forca + "%";

  if (forca < 40)      barra.style.backgroundColor = "red";
  else if (forca < 70) barra.style.backgroundColor = "orange";
  else if (forca < 90) barra.style.backgroundColor = "gold";
  else                 barra.style.backgroundColor = "green";
}

function validarSenha() {
  if (forcaAtual < 60) {
    alert("❌ A senha é muito fraca. Use letras maiúsculas, números e símbolos para fortalecer.");
    return false;
  }
  return true;
}
function toggleSenha() {
  const input = document.getElementById("senha");
  input.type = input.type === "password" ? "text" : "password";
}
function toggleConfSenha() {
  const input = document.getElementById("confirmarSenha");
  input.type = input.type === "password" ? "text" : "password";
}
</script>

<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>
