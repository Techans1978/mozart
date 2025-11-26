<?php
// Mostrar erros (opcional, só pra dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

// Agora pode usar ROOT_PATH e BASE_URL para includes e links
require_once ROOT_PATH . '/system/includes/autenticacao.php';
?>

<?php
    include_once ROOT_PATH . '/system/includes/user_head.php';
?>

<body>
    <!-- Navbar -->
    <?php
        include_once ROOT_PATH . '/system/includes/user_navbar.php';
    ?>

    <!-- Feed -->
    <main class="feed" aria-live="polite">
      <section class="card">
        <div class="head">
          <div class="title">📌 <span>Feed</span></div>
          <div class="meta">
            <span class="chip">Minha caixa</span>
            <span class="chip">Equipe Loja 08</span>
            <span class="chip warn">A vencer hoje</span>
          </div>
        </div>
        <div class="body filterbar">
          <span class="view">Visualização:</span>
          <button class="chip">Relevância</button>
          <button class="chip">SLA ↑</button>
          <button class="chip">Recentes</button>
          <span class="chip">Tipo: Todos</span>
          <span class="chip">Status: Pendentes</span>
          <span class="chip">Processo: Onboarding</span>
        </div>
      </section>

      <!-- Card: Tarefa atrasada -->
      <article class="card">
        <div class="head">
          <div class="title">✅ Tarefa · Aprovar cadastro de fornecedor</div>
          <div class="meta">
            <span class="badge danger">Atrasado • SLA -2h</span>
            <span class="chip">Prioridade: Alta</span>
          </div>
        </div>
        <div class="body">
          <div class="muted">Fluxo: Onboarding de Fornecedor · Criado por <strong>Carla</strong> há 3h · Loja 12</div>
          <div class="meta">
            <span class="chip">Fornecedor: Super Peças Ltda</span>
            <span class="chip">CNPJ: 12.345.678/0001-90</span>
            <span class="chip">Valor: R$ 18.200,00</span>
          </div>
          <div class="actions">
            <button class="btn primary">Assumir</button>
            <button class="btn">Aprovar</button>
            <button class="btn">Reprovar</button>
            <button class="btn">Anexar</button>
            <button class="btn">Comentar</button>
            <button class="btn ghost">Abrir</button>
          </div>
          <div class="thread">Último comentário — <strong>João</strong>: "Faltava a certidão; anexei agora." · 2min atrás</div>
        </div>
      </article>

      <!-- Card: Chamado em andamento -->
      <article class="card">
        <div class="head">
          <div class="title">🎫 Chamado · Sem internet — Loja 08</div>
          <div class="meta">
            <span class="badge info">Em andamento</span>
            <span class="chip">SLA: 1h15</span>
          </div>
        </div>
        <div class="body">
          <div class="muted">Categoria: Redes · Aberto por <strong>Caixa 03</strong> há 20min</div>
          <div class="meta">
            <span class="chip">Técnico: Felipe</span>
            <span class="chip">Ticket #48291</span>
          </div>
          <div class="actions">
            <button class="btn primary">Responder</button>
            <button class="btn">Transferir</button>
            <button class="btn">Classificar</button>
            <button class="btn ghost">Abrir</button>
          </div>
        </div>
      </article>

      <!-- Card: Aprovação -->
      <article class="card">
        <div class="head">
          <div class="title">🧾 Aprovação · Pedido de compra #8721</div>
          <div class="meta">
            <span class="badge warn">Aguardando</span>
            <span class="chip">SLA: 4h</span>
          </div>
        </div>
        <div class="body">
          <div class="muted">Solicitado por <strong>Marina</strong> · Itens: 14 · Centro de custo: Marketing</div>
          <div class="meta">
            <span class="chip">Total: R$ 42.670,00</span>
            <span class="chip">Consenso: 1/3</span>
          </div>
          <div class="actions">
            <button class="btn primary">Aprovar</button>
            <button class="btn">Reprovar</button>
            <button class="btn">Comentar</button>
            <button class="btn ghost">Abrir</button>
          </div>
        </div>
      </article>

      <!-- Card: Processo -->
      <article class="card">
        <div class="head">
          <div class="title">🔁 Processo · Onboarding de Colaborador</div>
          <div class="meta">
            <span class="badge ok">No prazo</span>
            <span class="chip">Instância: #7F3C-22</span>
          </div>
        </div>
        <div class="body">
          <div class="muted">Etapa atual: Entrega de Equipamentos · Responsável: <strong>Suporte TI</strong></div>
          <div class="actions">
            <button class="btn primary">Ver progresso</button>
            <button class="btn">Adicionar observador</button>
            <button class="btn ghost">Abrir</button>
          </div>
        </div>
      </article>

    </main>

<?php include_once ROOT_PATH . 'system/includes/user_navbar_right.php'; ?>

<?php include_once ROOT_PATH . 'system/includes/user_code_footer.php'; ?>

<?php include_once ROOT_PATH . 'system/includes/user_footer.php'; ?>
