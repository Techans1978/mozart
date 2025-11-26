<?php
// modules/bpm/bpm_designer.php
// Mozart BPM — Modeler com Properties + Element Templates (CDN + fallback local)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';
require_once ROOT_PATH . '/system/config/connect.php';

// Abre <html><head>...<body>
include_once ROOT_PATH . 'system/includes/head.php';
?>

<link href="<?= BASE_URL ?>/modules/gestao_ativos/includes/css/style_gestao_ativos.css?v=1.0.0" rel="stylesheet">

<?php
// (se o seu navbar ficar dentro do head/footer, não precisa incluir aqui)
include_once ROOT_PATH . 'system/includes/navbar.php';
?>

<!-- Page Content -->
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><?= APP_NAME ?></h1></div></div>

    <div class="row">
      <div class="col-lg-12">
<!-- Top Content -->


<session class="bpm">

  <div class="container">
    
<header class="toolbar">
  <h1>Locais / Entidades — Cadastro</h1>
  <div class="actions">
    <a class="btn" href="locais-entidades-listar.html">Listar locais/entidades</a>
  </div>
</header>

<form class="card" autocomplete="off" novalidate>
  <p class="subtitle">Identificação</p>
  <div class="grid cols-3">
    <div><label>Tipo *</label><select><option>Empresa/Entidade</option><option>Filial</option><option>Loja</option><option>Depósito</option><option>Setor</option><option>Sala/Área</option></select></div>
    <div><label>Nome *</label><input type="text" placeholder="Ex.: Matriz / Loja Centro / TI"/></div>
    <div><label>Status *</label><select><option>Ativo</option><option>Inativo</option></select></div>
  </div>

  <div class="grid cols-3">
    <div><label>Vinculado a (pai)</label><select><option>—</option><option>Matriz</option><option>Loja Centro</option></select></div>
    <div><label>Código interno</label><input type="text" placeholder="Sigla/ID"/></div>
    <div><label>Responsável</label><input type="text" placeholder="Nome/usuário"/></div>
  </div>

  <div class="divider"></div>
  <p class="subtitle">Endereço</p>
  <div class="grid cols-4">
    <div><label>CEP</label><input type="text" placeholder="00000-000"/></div>
    <div class="cols-span-3"><label>Logradouro</label><input type="text" placeholder="Rua/Avenida"/></div>
  </div>
  <div class="grid cols-4">
    <div><label>Número</label><input type="text" placeholder="S/N"/></div>
    <div><label>Complemento</label><input type="text" placeholder="Sala, bloco..."/></div>
    <div><label>Bairro</label><input type="text"/></div>
    <div><label>Município</label><input type="text"/></div>
  </div>
  <div class="grid cols-3">
    <div><label>UF</label><input type="text" placeholder="SP, RJ..."/></div>
    <div><label>Telefone</label><input type="tel" placeholder="(11) 4000-0000"/></div>
    <div><label>E-mail</label><input type="email" placeholder="contato@empresa.com.br"/></div>
  </div>

  <div class="divider"></div>
  <p class="subtitle">Observações</p>
  <textarea placeholder="Regras de acesso, horários, observações."></textarea>

  <div class="divider"></div>
  <div style="display:flex;justify-content:flex-end;gap:10px">
    <button class="btn" type="button">Cancelar</button>
    <button class="btn primary" type="button">Salvar (visual)</button>
  </div>
</form>

<div class="card"><p class="hint">Mock visual. Depois mapeamos hierarquia e permissões.</p></div>

</session>

  <!-- Fim Content -->
        </div>
    </div>
  </div>
</div>

<?php
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>






<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>