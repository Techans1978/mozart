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
  <h1>Tempos por Serviços (SLA/OLAs) — Cadastro</h1>
  <div class="actions">
    <a class="btn" href="tempos-servicos-listar.html">Listar SLAs</a>
  </div>
</header>

<form class="card" autocomplete="off" novalidate>
  <p class="subtitle">Definição</p>
  <div class="grid cols-3">
    <div><label>Nome do SLA *</label><input type="text" placeholder="Ex.: Suporte Loja — Padrão"/></div>
    <div><label>Escopo</label><select><option>Global</option><option>Por entidade</option><option>Por local</option></select></div>
    <div><label>Status *</label><select><option>Ativo</option><option>Inativo</option></select></div>
  </div>

  <div class="grid cols-3">
    <div><label>Calendário de trabalho</label><select><option>8x5 (seg–sex 08:00–17:00)</option><option>24x7</option><option>12x6</option></select></div>
    <div><label>Considerar feriados?</label><select><option>Sim</option><option>Não</option></select></div>
    <div><label>Zona de tempo</label><input type="text" value="America/Sao_Paulo"/></div>
  </div>

  <div class="divider"></div>
  <p class="subtitle">Matriz de metas</p>
  <div class="hint" style="margin-bottom:6px">Defina tempos de resposta e solução por tipo/impacto/prioridade.</div>
  <div id="metas" class="stack"></div>
  <button type="button" class="btn small" id="add-meta">+ Adicionar linha</button>

  <div class="divider"></div>
  <p class="subtitle">Escalonamento</p>
  <div id="escalas" class="stack"></div>
  <button type="button" class="btn small" id="add-escala">+ Adicionar regra</button>

  <div class="divider"></div>
  <p class="subtitle">Aplicação</p>
  <div class="grid cols-3">
    <div><label>Aplica em</label><select multiple>
      <option>Reservas</option><option>Transferências</option><option>Sub-locação</option><option>Manutenção preventiva</option></select>
      <span class="hint">RBAC pode limitar por perfil.</span>
    </div>
    <div><label>Categorias/Modelos alvos</label><input type="text" placeholder="Ex.: Notebooks; Switches"/></div>
    <div><label>Valor máximo por item (para aprovação)</label><input type="number" min="0" step="0.01"/></div>
  </div>

  <div class="divider"></div>
  <div style="display:flex;justify-content:flex-end;gap:10px">
    <button class="btn" type="button">Cancelar</button>
    <button class="btn primary" type="button">Salvar (visual)</button>
  </div>
</form>

<div class="card"><p class="hint">Mock visual. Depois definimos a engine de SLA e integrações com agenda.</p></div>

</session>

  <!-- Fim Content -->
        </div>
    </div>
  </div>
</div>

<?php
include_once ROOT_PATH . 'system/includes/code_footer.php';
?>
  
<script>
function metaRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Tipo</label><select><option>Incidente</option><option>Solicitação</option><option>Manutenção</option></select></div>
    <div><label>Prioridade</label><select><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
    <div><label>Resp. (h)</label><input type="number" min="0" step="0.1" placeholder="4"/></div>
    <div><label>Solução (h)</label><input type="number" min="0" step="0.1" placeholder="24"/></div>
    <div class="cols-span-4"><label>Observações</label><input type="text" placeholder="Ex.: fora do horário considerar próximo dia útil"/></div>
  `;
  return el;
}
function escalaRow(){
  const el=document.createElement('div'); el.className='grid cols-4'; el.style.alignItems='end';
  el.innerHTML=`
    <div><label>Após (h)</label><input type="number" min="0" step="0.1" placeholder="2"/></div>
    <div><label>Escalonar para</label><select><option>Gestor</option><option>Coordenador</option><option>Diretoria</option></select></div>
    <div><label>Canal</label><select><option>E-mail</option><option>WhatsApp</option><option>SMS</option></select></div>
    <div><label>Repetir?</label><select><option>Não</option><option>Sim</option></select></div>
  `;
  return el;
}
const metas=document.getElementById('metas');
const escalas=document.getElementById('escalas');
document.getElementById('add-meta').addEventListener('click',()=>metas.appendChild(metaRow()));
document.getElementById('add-escala').addEventListener('click',()=>escalas.appendChild(escalaRow()));
metas.appendChild(metaRow()); metas.appendChild(metaRow());
escalas.appendChild(escalaRow());
</script>








<?php
include_once ROOT_PATH . 'system/includes/footer.php';
?>