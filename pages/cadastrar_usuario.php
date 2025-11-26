<?php
// pages/cadastrar_usuario.php (create + edit, one-page)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php'; // $conn (mysqli)

/* ===================== Helpers ===================== */
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
/** Carrega opções hierárquicas (usa 'nome' do schema e expõe como 'label') */
function carregarOpcoes(mysqli $conn, string $tabela): array {
  $t = $conn->real_escape_string($tabela);
  // usa 'nome' do seu schema e expõe como 'label'
  $sql = "SELECT id, nome AS label, parent_id FROM {$t} ORDER BY COALESCE(parent_id,0), nome";
  $res = $conn->query($sql);
  if (!$res) return [];

  $nodes = [];
  while ($row = $res->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['parent_id'] = isset($row['parent_id']) ? (int)$row['parent_id'] : null;
    $nodes[$row['id']] = $row;
  }

  // constrói lista com indentação leve a partir de parent_id
  $children = [];
  foreach ($nodes as $n) {
    $pid = $n['parent_id'] ?? 0;
    $children[$pid][] = $n['id'];
  }

  $out = [];
  $walk = function($pid, $prefix='') use (&$children, &$nodes, &$out, &$walk) {
    if (!isset($children[$pid])) return;
    foreach ($children[$pid] as $cid) {
      $n = $nodes[$cid];
      $out[] = ['id'=>$n['id'], 'label'=>$prefix.$n['label']];
      $walk($n['id'], $prefix.'— ');
    }
  };
  $walk(0, '');
  return $out ?: array_values(array_map(fn($n)=>['id'=>$n['id'],'label'=>$n['label']], $nodes));
}

/* ===================== Estado / Setup ===================== */
$sugestao_senha = gerarSenha();
$erros = [];
$sucesso = null;

if (session_status() === PHP_SESSION_NONE) session_start();
function csrf_token() { if (empty($_SESSION['csrf_user_form'])) $_SESSION['csrf_user_form']=bin2hex(random_bytes(16)); return $_SESSION['csrf_user_form']; }
function check_csrf($t){ return isset($_SESSION['csrf_user_form']) && hash_equals($_SESSION['csrf_user_form'], (string)$t); }

/* ======= Modo edição ======= */
$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$is_edit = $usuario_id > 0;

/* ======= Seleções atuais (mantém após erro) ======= */
$sel_grupos  = array_map('intval', $_POST['grupos_ids']  ?? []);
$sel_perfis  = array_map('intval', $_POST['perfis_ids']  ?? []);
$sel_papeis  = array_map('intval', $_POST['papeis_ids']  ?? []);

/* ======= Carrega opções dos selects ======= */
$grupos_opts = carregarOpcoes($conn, 'grupos');
$perfis_opts = carregarOpcoes($conn, 'perfis');
$papeis_opts = carregarOpcoes($conn, 'papeis'); // se não existir, ficará vazio e o select some da tela

/* ======= Pré-carregar dados no GET (edição) ======= */
if ($is_edit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  $st = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
  if ($st) {
    $st->bind_param('i', $usuario_id);
    $st->execute();
    $rs = $st->get_result();
    if ($u = $rs->fetch_assoc()) {
      // Preenche $_POST para reaproveitar o formulário sem mudar sua lógica
      $_POST['username']       = $u['username'];
      $_POST['nome_completo']  = $u['nome_completo'];
      $_POST['cpf']            = $u['cpf'];
      $_POST['cargo']          = $u['cargo'];
      $_POST['email']          = $u['email'];
      $_POST['telefone']       = $u['telefone'];
      $_POST['nivel_acesso']   = $u['nivel_acesso'];
      $_POST['ativo']          = (string)$u['ativo'];
      $_POST['observacao']     = $u['observacao'] ?? '';
      // senha/confirmar deixamos vazias (opcional editar)
      $_POST['senha'] = '';
      $_POST['confirmarSenha'] = '';

      // Carrega seleções vinculadas
      $sel_grupos = [];
      $q = $conn->prepare("SELECT grupo_id FROM usuarios_grupos WHERE usuario_id=?");
      if ($q){ $q->bind_param('i',$usuario_id); $q->execute(); $r=$q->get_result(); while($row=$r->fetch_assoc()) $sel_grupos[]=(int)$row['grupo_id']; $q->close(); }

      $sel_perfis = [];
      $q = $conn->prepare("SELECT perfil_id FROM usuarios_perfis WHERE usuario_id=?");
      if ($q){ $q->bind_param('i',$usuario_id); $q->execute(); $r=$q->get_result(); while($row=$r->fetch_assoc()) $sel_perfis[]=(int)$row['perfil_id']; $q->close(); }

      $sel_papeis = [];
      // Só carrega se a tabela existir
      $check = $conn->query("SHOW TABLES LIKE 'usuarios_papeis'");
      if ($check && $check->num_rows > 0) {
        $q = $conn->prepare("SELECT papel_id FROM usuarios_papeis WHERE usuario_id=?");
        if ($q){ $q->bind_param('i',$usuario_id); $q->execute(); $r=$q->get_result(); while($row=$r->fetch_assoc()) $sel_papeis[]=(int)$row['papel_id']; $q->close(); }
      }

      $_POST['grupos_ids'] = $sel_grupos;
      $_POST['perfis_ids'] = $sel_perfis;
      $_POST['papeis_ids'] = $sel_papeis;
    } else {
      $is_edit = false;
      $usuario_id = 0;
      $sucesso = null;
      $erros[] = 'Usuário não encontrado para edição.';
    }
    $st->close();
  }
}

/* ===================== POST (salvar) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? '')) {
    $erros[] = 'Token CSRF inválido. Recarregue a página.';
  }

  // Campos conforme sua tabela `usuarios`
  $usuario_id   = (int)($_POST['usuario_id'] ?? $usuario_id);
  $is_edit      = $usuario_id > 0;

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
  $observacao    = trim($_POST['observacao']     ?? '');

  $grupos_ids = array_map('intval', $_POST['grupos_ids'] ?? []);
  $perfis_ids = array_map('intval', $_POST['perfis_ids'] ?? []);
  $papeis_ids = array_map('intval', $_POST['papeis_ids'] ?? []);

  // Reflete nas seleções (para manter após erro)
  $sel_grupos = $grupos_ids;
  $sel_perfis = $perfis_ids;
  $sel_papeis = $papeis_ids;

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

  // Senha obrigatória apenas no CREATE; no EDIT só valida se foi preenchida
  $alterarSenha = false;
  if ($is_edit) {
    if ($senha !== '' || $confirmar !== '') {
      if ($senha !== $confirmar) $erros[] = 'Senha e confirmação precisam coincidir.';
      if (!(strlen($senha) >= 8 && preg_match('/[A-Z]/', $senha) && preg_match('/[\d\W_]/', $senha))) {
        $erros[] = 'Senha muito fraca. Use 8+ caracteres, com letra maiúscula e número/símbolo.';
      }
      $alterarSenha = empty($erros);
    }
  } else {
    if ($senha === '' || $confirmar === '' || $senha !== $confirmar) $erros[] = 'Senha e confirmação precisam coincidir.';
    if (!(strlen($senha) >= 8 && preg_match('/[A-Z]/', $senha) && preg_match('/[\d\W_]/', $senha))) {
      $erros[] = 'Senha muito fraca. Use 8+ caracteres, com letra maiúscula e número/símbolo.';
    }
    $alterarSenha = true;
  }

  // Checagens de unicidade (ignorando o próprio no EDIT)
  if (!$erros) {
    // username
    if ($is_edit) {
      $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE username = ? AND id <> ? LIMIT 1"), "Erro ao preparar (username): ");
      $stmt->bind_param('si', $username, $usuario_id);
    } else {
      $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE username = ? LIMIT 1"), "Erro ao preparar (username): ");
      $stmt->bind_param('s', $username);
    }
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $erros[] = 'Já existe um usuário com esse username.'; }
    $stmt->close();
  }
  if (!$erros) {
    // cpf
    if ($is_edit) {
      $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE cpf = ? AND id <> ? LIMIT 1"), "Erro ao preparar (cpf): ");
      $stmt->bind_param('si', $cpf, $usuario_id);
    } else {
      $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE cpf = ? LIMIT 1"), "Erro ao preparar (cpf): ");
      $stmt->bind_param('s', $cpf);
    }
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $erros[] = 'Já existe um usuário com esse CPF/CNPJ.'; }
    $stmt->close();
  }
  if (!$erros) {
    // email
    if ($is_edit) {
      $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE email = ? AND id <> ? LIMIT 1"), "Erro ao preparar (email): ");
      $stmt->bind_param('si', $email, $usuario_id);
    } else {
      $stmt = fail_stmt($conn, $conn->prepare("SELECT 1 FROM usuarios WHERE email = ? LIMIT 1"), "Erro ao preparar (email): ");
      $stmt->bind_param('s', $email);
    }
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $erros[] = 'Já existe um usuário com esse e-mail.'; }
    $stmt->close();
  }

  // Validação hierarquia dos selects
  if (!$erros) {
    if ($msg = validaHierarquiaSelecionada($conn, $grupos_ids, "grupos_paths")) $erros[] = $msg;
    if ($msg = validaHierarquiaSelecionada($conn, $perfis_ids, "perfis_paths")) $erros[] = $msg;
    if ($msg = validaHierarquiaSelecionada($conn, $papeis_ids, "papeis_paths")) $erros[] = $msg; // só valida se existir
  }

  // INSERT / UPDATE
  if (!$erros) {
    if ($is_edit) {
      // UPDATE
      $sql = "UPDATE usuarios SET
                username=?, nome_completo=?, cpf=?, cargo=?, email=?, telefone=?, nivel_acesso=?, observacao=?, ativo=?
              ".($alterarSenha ? ", senha=? " : "")."
              WHERE id=?";
      $stmt = fail_stmt($conn, $conn->prepare($sql), "Erro ao preparar UPDATE: ");

      if ($alterarSenha) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt->bind_param(
          'sssssssissi',
          $username,
          $nome_completo,
          $cpf,
          $cargo,
          $email,
          $telefone,
          $nivel_acesso,
          $observacao,
          $ativo,
          $senha_hash,
          $usuario_id
        );
      } else {
        $stmt->bind_param(
          'sssssssisi',
          $username,
          $nome_completo,
          $cpf,
          $cargo,
          $email,
          $telefone,
          $nivel_acesso,
          $observacao,
          $ativo,
          $usuario_id
        );
      }

      if ($stmt->execute()) {
        $stmt->close();

        // Atualiza vínculos: zera e insere de novo (simples e consistente)
        // Grupos
        $conn->query("DELETE FROM usuarios_grupos WHERE usuario_id=".$usuario_id);
        if (!empty($grupos_ids)) {
          $insG = $conn->prepare("INSERT INTO usuarios_grupos (usuario_id, grupo_id, is_primary) VALUES (?, ?, 0)");
          if ($insG) { foreach ($grupos_ids as $gid){ $gid=(int)$gid; if($gid>0){ $insG->bind_param('ii',$usuario_id,$gid); $insG->execute(); } } $insG->close(); }
        }
        // Perfis
        $conn->query("DELETE FROM usuarios_perfis WHERE usuario_id=".$usuario_id);
        if (!empty($perfis_ids)) {
          $insP = $conn->prepare("INSERT INTO usuarios_perfis (usuario_id, perfil_id, is_primary) VALUES (?, ?, 0)");
          if ($insP) { foreach ($perfis_ids as $pid){ $pid=(int)$pid; if($pid>0){ $insP->bind_param('ii',$usuario_id,$pid); $insP->execute(); } } $insP->close(); }
        }
        // Papéis (se a tabela existir)
        $existeUP = $conn->query("SHOW TABLES LIKE 'usuarios_papeis'");
        if ($existeUP && $existeUP->num_rows > 0) {
          $conn->query("DELETE FROM usuarios_papeis WHERE usuario_id=".$usuario_id);
          if (!empty($papeis_ids)) {
            $insR = $conn->prepare("INSERT INTO usuarios_papeis (usuario_id, papel_id, is_primary) VALUES (?, ?, 0)");
            if ($insR) { foreach ($papeis_ids as $rid){ $rid=(int)$rid; if($rid>0){ $insR->bind_param('ii',$usuario_id,$rid); $insR->execute(); } } $insR->close(); }
          }
        }

        $sucesso = 'Usuário atualizado com sucesso!';
        // Recarrega dados para refletir tela (senha vazia)
        $_POST['senha'] = $_POST['confirmarSenha'] = '';
      } else {
        $erros[] = 'Erro ao atualizar: ' . $stmt->error;
        $stmt->close();
      }

    } else {
      // INSERT
      $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
      $stmt = fail_stmt(
        $conn,
        $conn->prepare("
          INSERT INTO usuarios
            (username, nome_completo, cpf, cargo, email, senha, telefone, nivel_acesso, observacao, ativo, data_cadastro, tentativas_login, bloqueado)
          VALUES
            (?,        ?,            ?,   ?,     ?,     ?,     ?,        ?,            ?,          ?,     NOW(),        0,               0)
        "),
        "Erro ao preparar INSERT: "
      );
      $stmt->bind_param(
        'sssssssssi',
        $username,
        $nome_completo,
        $cpf,
        $cargo,
        $email,
        $senha_hash,
        $telefone,
        $nivel_acesso,
        $observacao,
        $ativo
      );

      if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        $stmt->close();

        // Vincular grupos
        if (!empty($grupos_ids)) {
          $insG = $conn->prepare("INSERT INTO usuarios_grupos (usuario_id, grupo_id, is_primary) VALUES (?, ?, 0)");
          if ($insG) {
            foreach ($grupos_ids as $gid) {
              $gid = (int)$gid;
              if ($gid > 0) { $insG->bind_param('ii', $userId, $gid); $insG->execute(); }
            }
            $insG->close();
          }
        }
        // Vincular perfis
        if (!empty($perfis_ids)) {
          $insP = $conn->prepare("INSERT INTO usuarios_perfis (usuario_id, perfil_id, is_primary) VALUES (?, ?, 0)");
          if ($insP) {
            foreach ($perfis_ids as $pid) {
              $pid = (int)$pid;
              if ($pid > 0) { $insP->bind_param('ii', $userId, $pid); $insP->execute(); }
            }
            $insP->close();
          }
        }
        // Vincular papéis (se existir)
        $existeUP = $conn->query("SHOW TABLES LIKE 'usuarios_papeis'");
        if ($existeUP && $existeUP->num_rows > 0 && !empty($papeis_ids)) {
          $insR = $conn->prepare("INSERT INTO usuarios_papeis (usuario_id, papel_id, is_primary) VALUES (?, ?, 0)");
          if ($insR) {
            foreach ($papeis_ids as $rid) {
              $rid = (int)$rid;
              if ($rid > 0) { $insR->bind_param('ii', $userId, $rid); $insR->execute(); }
            }
            $insR->close();
          }
        }

        $sucesso = 'Usuário cadastrado com sucesso!';
        // Limpa POST e seleção de múltiplos para a tela ficar zerada
        $_POST = [];
        $sel_grupos = $sel_perfis = $sel_papeis = [];
      } else {
        $erros[] = 'Erro ao inserir: ' . $stmt->error;
        $stmt->close();
      }
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
        <h2><?= $is_edit ? 'Editar Usuário' : 'Cadastrar Usuário' ?> - <?= APP_NAME ?></h2>

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
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <?php if ($is_edit): ?>
            <input type="hidden" name="usuario_id" value="<?= (int)$usuario_id ?>">
          <?php endif; ?>

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
              <option value="colaborador" <?= (($_POST['nivel_acesso'] ?? '')==='colaborador')?'selected':'' ?>>Colaborador</option>
              <option value="prestador" <?= (($_POST['nivel_acesso'] ?? '')==='prestador')?'selected':'' ?>>Prestador</option>
              <option value="visitante" <?= (($_POST['nivel_acesso'] ?? '')==='visitante')?'selected':'' ?>>Visitante</option>
            </select>
          </div>

          <div class="row">
            <!-- PAPÉIS (só mostra se houver opções) -->
            <?php if (!empty($papeis_opts)): ?>
            <div class="col-md-4">
              <div class="form-group">
                <label>Papéis (múltiplos)</label>
                <select class="form-control" name="papeis_ids[]" multiple size="8">
                  <?php foreach($papeis_opts as $r):
                    $sel = in_array((int)$r['id'], $sel_papeis) ? 'selected' : ''; ?>
                    <option value="<?= (int)$r['id'] ?>" <?= $sel ?>><?= htmlspecialchars($r['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="help-block">Segure Ctrl/Cmd para múltiplos. Regra: não selecionar pai e filho ao mesmo tempo.</p>
              </div>
            </div>
            <?php endif; ?>

            <!-- GRUPOS -->
            <div class="col-md-4">
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

            <!-- PERFIS -->
            <div class="col-md-4">
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

          <!-- Senha: obrigatória só no CREATE -->
          <div class="form-group">
            <label for="senha">Senha<?= $is_edit ? ' (preencher apenas se for alterar)' : '' ?>:</label>
            <div class="input-group">
              <input class="form-control" type="password" id="senha" name="senha" oninput="avaliarSenha()" <?= $is_edit ? '' : 'required' ?>>
              <span class="input-group-addon" style="cursor:pointer;" onclick="toggleSenha()">👁️</span>
            </div>
          </div>

          <div class="form-group">
            <label for="confirmarSenha">Confirmar Senha<?= $is_edit ? '' : ' *' ?>:</label>
            <div class="input-group">
              <input class="form-control" type="password" id="confirmarSenha" name="confirmarSenha" <?= $is_edit ? '' : 'required' ?>>
              <span class="input-group-addon" style="cursor:pointer;" onclick="toggleConfSenha()">👁️</span>
            </div>
          </div>

          <div class="form-group">
            <div class="barra"><div id="forcaBarra" class="forca" style="width:0%; background-color:red; height:8px;"></div></div>
            <p class="help-block"><div id="porcentagem">Força da senha: 0%</div></p>
            <?php if (!$is_edit): ?>
            <p class="help-block">Sua senha sugerida é:
              <code id="senhaSugerida"><?= $sugestao_senha ?></code>
              <button class="btn btn-default" type="button" onclick="usarSenha()">Usar senha sugerida</button>
            </p>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label for="observacao" class="form-label">Observações internas</label>
            <textarea name="observacao" id="observacao"
                      class="form-control" rows="3"
                      placeholder="Anotações internas sobre este usuário (não aparece para clientes)"><?=
              htmlspecialchars($_POST['observacao'] ?? '')
            ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Salvar Alterações' : 'Registrar Usuário' ?></button>
          <a href="<?= htmlspecialchars(BASE_URL . '/pages/listar_usuarios.php') ?>" class="btn btn-default">Voltar</a>
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
  // no EDIT, só checa se usuário digitou algo
  const isEdit = <?= $is_edit ? 'true' : 'false' ?>;
  const senha = document.getElementById("senha").value;
  const confirmar = document.getElementById("confirmarSenha").value;
  if (!isEdit || senha || confirmar) {
    if (forcaAtual < 60) {
      alert("❌ A senha é muito fraca. Use letras maiúsculas, números e símbolos para fortalecer.");
      return false;
    }
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
