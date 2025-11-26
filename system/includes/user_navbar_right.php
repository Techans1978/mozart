    <!-- Sidebar Right -->
    <aside class="sidebar-right">
      <div class="panel">
        <?php require_once ROOT_PATH . 'components/menu_eventos.php'; ?>
      </div>
      <div class="panel" style="margin-top:16px">
        <h4>Meus KPIs</h4>
        <div class="kpi">
          <span>Pendências (12)</span>
          <span class="chip">Meta: 8</span>
        </div>
        <div class="bar" aria-label="Progresso pendências"><i style="width:65%"></i></div>
        <div class="kpi" style="margin-top:10px">
          <span>SLA cumprido</span>
          <span class="chip ok">92%</span>
        </div>
        <div class="bar"><i style="width:92%"></i></div>
      </div>
      <div class="panel" style="margin-top:16px">
        <h4>Atalhos</h4>
        <div class="list">
          <div class="btn"><a href="https://csc.superabconline.com.br/" target="_blank">Abrir Chamado</a></div>
          <div class="btn">Iniciar Processo</div>
          <div class="btn">Ver Tarefas</div>
        </div>
      </div>
    </aside>
  </div>

  <!-- Composer (mock) -->
  <div class="modal" id="modalNovo" aria-modal="true" role="dialog">
    <div class="box">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <h3 style="margin:0">Criar novo</h3>
        <button class="btn" onclick="toggleModal(false)">Fechar</button>
      </div>
      <div class="grid">
        <div class="tile">
          <strong>Iniciar Processo</strong>
          <span class="muted">Fluxos publicados</span>
        </div>
        <div class="tile">
          <strong>Abrir Chamado</strong>
          <a href="https://csc.superabconline.com.br/" target="_blank"><span class="muted">Helpdesk</span></a>
        </div>
        <div class="tile">
          <strong>Criar Tarefa</strong>
          <span class="muted">Atribuir a alguém</span>
        </div>
        <div class="tile">
          <strong>Criar Evento</strong>
          <span class="muted">Calendário</span>
        </div>
      </div>
    </div>
  </div>