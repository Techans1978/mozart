<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . 'system/includes/autenticacao.php';
proteger_pagina();
require_once ROOT_PATH . 'system/config/connect.php';

// ⚠️ SOMENTE O PRÓPRIO USUÁRIO (usa a sessão)
if (empty($_SESSION['user_id'])) {
    die('Erro: Usuário não autenticado.');
}
$user_id = (int) $_SESSION['user_id'];

// Busca usuário logado (username e hash)
$stmt = $conn->prepare("SELECT username, senha FROM usuarios WHERE id = ? LIMIT 1");
if (!$stmt) { die("Erro ao preparar consulta: " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$usuario = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$usuario) {
    die('Erro: Usuário não encontrado.');
}

$erros = [];
$sucesso = null;

// Processamento do POST (self-service)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ignora qualquer tentativa de trocar o alvo: NÃO lemos id de GET/POST
    $senha_atual     = $_POST['senha_atual']     ?? '';
    $nova_senha      = $_POST['nova_senha']      ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    // Validações
    if ($nova_senha === '' || $confirmar_senha === '' || $nova_senha !== $confirmar_senha) {
        $erros[] = 'Nova senha e confirmação não conferem.';
    }
    if (!(strlen($nova_senha) >= 8 && preg_match('/[A-Z]/', $nova_senha) && preg_match('/[\d\W_]/', $nova_senha))) {
        $erros[] = 'Senha muito fraca. Use 8+ caracteres, com letra maiúscula e número/símbolo.';
    }

    // Confere senha atual e impede reutilizar
    if (!$erros) {
        $hash_atual = $usuario['senha'];
        if (!password_verify($senha_atual, $hash_atual)) {
            $erros[] = 'Senha atual incorreta.';
        }
        if (password_verify($nova_senha, $hash_atual)) {
            $erros[] = 'A nova senha não pode ser igual à senha atual.';
        }
    }

    // Atualiza
    if (!$erros) {
        $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE usuarios SET senha = ?, tentativas_login = 0, bloqueado = 0 WHERE id = ?");
        if (!$upd) {
            $erros[] = 'Erro ao preparar atualização: ' . $conn->error;
        } else {
            $upd->bind_param("si", $novo_hash, $user_id);
            if ($upd->execute()) {
                $sucesso = 'Senha alterada com sucesso!';
                $_POST = []; // limpa inputs
            } else {
                $erros[] = 'Erro ao atualizar senha: ' . $upd->error;
            }
            $upd->close();
        }
    }
}

include_once ROOT_PATH . 'system/includes/head.php';
include_once ROOT_PATH . 'system/includes/navbar.php';
?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header">Alterar Senha</h1></div></div>

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
      <div class="col-md-4 col-md-offset-4">
        <div class="login-panel panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">
              Alterar senha de <strong><?= htmlspecialchars($usuario['username']) ?></strong>
              <small>(mín. 8, 1 maiúscula e número/símbolo)</small>
            </h3>
          </div>
          <div class="panel-body">
            <form method="POST" action="" onsubmit="return validarSenha();">
              <div class="form-group">
                <label for="senha_atual">Senha Atual:</label>
                <div class="input-group">
                  <input class="form-control" type="password" name="senha_atual" id="senha_atual" required>
                  <span class="input-group-addon" style="cursor:pointer" onclick="toggle('senha_atual')">👁️</span>
                </div>
              </div>

              <div class="form-group">
                <label for="nova_senha">Nova Senha:</label>
                <div class="input-group">
                  <input class="form-control" type="password" name="nova_senha" id="nova_senha" oninput="avaliarSenha()" required>
                  <span class="input-group-addon" style="cursor:pointer" onclick="toggle('nova_senha')">👁️</span>
                </div>
                <div class="barra" style="margin-top:8px;background:#eee;height:8px;border-radius:4px;">
                  <div id="forcaBarra" class="forca" style="height:8px;width:0%;background:red;"></div>
                </div>
                <small id="porcentagem">Força da senha: 0%</small>
              </div>

              <div class="form-group">
                <label for="confirmar_senha">Confirmar Nova Senha:</label>
                <div class="input-group">
                  <input class="form-control" type="password" name="confirmar_senha" id="confirmar_senha" required>
                  <span class="input-group-addon" style="cursor:pointer" onclick="toggle('confirmar_senha')">👁️</span>
                </div>
              </div>

              <button type="submit" class="btn btn-primary">Alterar Senha</button>
            </form>
          </div>
        </div>
      </div>
    </div>  
  </div>
</div>

<script>
let forcaAtual = 0;
function toggle(id){ const i=document.getElementById(id); i.type = i.type==='password'?'text':'password'; }
function avaliarSenha(){
  const senha = document.getElementById("nova_senha").value;
  const barra = document.getElementById("forcaBarra");
  const texto = document.getElementById("porcentagem");
  let forca=0;
  if (senha.length>=8) forca+=25;
  if (/[A-Z]/.test(senha)) forca+=20;
  if (/[0-9]/.test(senha)) forca+=20;
  if (/[\W_]/.test(senha)) forca+=20;
  if (senha.length>=12) forca+=15;
  forca=Math.min(forca,100); forcaAtual=forca;
  barra.style.width=forca+"%";
  texto.textContent="Força da senha: "+forca+"%";
  barra.style.backgroundColor = forca<40?'red':(forca<70?'orange':(forca<90?'gold':'green'));
}
function validarSenha(){
  if (forcaAtual<60){ alert("❌ A senha é muito fraca. Use letras maiúsculas, números e símbolos para fortalecer."); return false; }
  const n=document.getElementById('nova_senha').value;
  const c=document.getElementById('confirmar_senha').value;
  if(n!==c){ alert("❌ Nova senha e confirmação não conferem."); return false; }
  return true;
}
</script>
<?php include_once ROOT_PATH . 'system/includes/code_footer.php'; ?>
<?php include_once ROOT_PATH . 'system/includes/footer.php'; ?>
<?php
$conn->close();
?>
