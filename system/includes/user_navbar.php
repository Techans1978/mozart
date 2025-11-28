<?php
// NUNCA deixe espaços/linhas acima deste <?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config.php'; // ajuste se necessário
?>
<!-- Topbar -->
<header class="topbar">
  <div class="wrap">
    <div class="brand">
      <div class="logo" aria-hidden="true"></div>
      MOZART
    </div>

    <div class="search" role="search">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="7"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input placeholder="Buscar tarefas, chamados, processos…" aria-label="Buscar" />
    </div>

    <div class="top-actions">
      <button class="btn primary" id="btnNovo">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Criar
      </button>

      <button class="btn ghost" id="btnTheme" title="Alternar tema">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 3a9 9 0 108.94 6.33A7 7 0 0112 3z"/>
        </svg>
      </button>

      <button class="btn ghost" title="Notificações">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
      </button>

      <div class="round" title="Minha caixa" aria-label="Minha caixa">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
      </div>

      <!-- Perfil com dropdown -->
      <div class="dropdown" id="profileDropdown">
        <button class="round profile" id="btnPerfil" title="Perfil" aria-haspopup="menu" aria-expanded="false">
          <svg class="ico"><use href="#i-user"/></svg>
        </button>

        <div class="dropdown-menu" role="menu" aria-label="Menu do perfil">
          <a class="item" href="<?= BASE_URL ?>/pages/user_atualizar_usuario.php">
            <span class="mini-round c-teal">
              <svg><use href="#i-edit"/></svg>
            </span>
            <span>Meu Perfil</span>
          </a>

          <a class="item" href="<?= BASE_URL >/pages/user_senha_usuario.php">
            <span class="mini-round c-indigo">
              <svg><use href="#i-key"/></svg>
            </span>
            <span>Alterar Senha</span>
          </a>

          <a class="item" href="<?= BASE_URL ?>/pages/view_calendar.php">
            <span class="mini-round c-amber">
              <svg><use href="#i-calendar"/></svg>
            </span>
            <span>Calendário</span>
          </a>

          <a class="item" href="<?= BASE_URL ?>/system/actions/logout.php">
            <span class="mini-round c-red">
              <svg><use href="#i-logout"/></svg>
            </span>
            <span>Sair</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="layout">
  <!-- Sidebar Left -->
  <aside class="sidebar-left">
    <nav>
      <div class="sidebar-head">
        <span class="sidebar-title">Menu</span>
        <button class="collapse-btn" id="btnCollapse" title="Expandir/Recolher menu">
          <svg class="ico"><use href="#i-chevron-left"/></svg>
        </button>
      </div>

      <a class="nav-item active" href="<?= BASE_URL ?>/pages/user_page.php">
        <span class="round c-blue">
          <svg class="ico"><use href="#i-home"/></svg>
        </span>
        <span class="label">Início</span>
      </a>

      <a class="nav-item" href="<?= BASE_URL ?>/pages/feed_content.php">
        <span class="round c-purple">
          <svg class="ico"><use href="#i-bullhorn"/></svg>
        </span>
        <span class="label">Comunicação</span>
      </a>

      <a class="nav-item" href="<?= BASE_URL ?>/pages/view_calendar.php">
        <span class="round c-amber">
          <svg class="ico"><use href="#i-calendar"/></svg>
        </span>
        <span class="label">Calendário</span>
      </a>

      <a class="nav-item" href="<?= BASE_URL ?>/pages/feed_dicas.php">
        <span class="round c-lime">
          <svg class="ico"><use href="#i-lightbulb"/></svg>
        </span>
        <span class="label">Dicas</span>
      </a>

      <a class="nav-item" href="#">
        <span class="round c-indigo">
          <svg class="ico"><use href="#i-file"/></svg>
        </span>
        <span class="label">Documentos</span>
      </a>

      <a class="nav-item" href="#">
        <span class="round c-slate">
          <svg class="ico"><use href="#i-help"/></svg>
        </span>
        <span class="label">Perguntas Frequentes</span>
      </a>

      <a class="nav-item" href="#">
        <span class="round c-teal">
          <svg class="ico"><use href="#i-checklist"/></svg>
        </span>
        <span class="label">Minhas Tarefas</span>
      </a>

      <a class="nav-item" href="#">
        <span class="round c-amber">
          <svg class="ico"><use href="#i-ticket"/></svg>
        </span>
        <span class="label">Chamados</span>
      </a>

      <a class="nav-item" href="#">
        <span class="round c-blue">
          <svg class="ico"><use href="#i-rotate"/></svg>
        </span>
        <span class="label">Processos</span>
      </a>

      <a class="nav-item" href="#">
        <span class="round c-cyan">
          <svg class="ico"><use href="#i-files"/></svg>
        </span>
        <span class="label">Documentos</span>
      </a>

      <a class="nav-item" href="#">
        <span class="round c-green">
          <svg class="ico"><use href="#i-users"/></svg>
        </span>
        <span class="label">Contatos</span>
      </a>

      <div style="height:8px"></div>

      <a class="nav-item" href="#">
        <span class="round c-slate">
          <svg class="ico"><use href="#i-settings"/></svg>
        </span>
        <span class="label">Administração</span>
      </a>
    </nav>
  </aside>

  <!-- daqui pra baixo entra o conteúdo da página (main, cards, etc.) -->
