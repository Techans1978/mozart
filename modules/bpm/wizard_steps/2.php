<div class="card"><h2>2) Acessos</h2>
<?php
/* Acessos – carregar opções do sistema */

// estado atual do wizard para acessos
$acessos = $state['acessos'] ?? [];
$selGrupos = $acessos['grupos'] ?? [];
$selPapeis = $acessos['papeis'] ?? [];
$selPerfis = $acessos['perfis'] ?? [];

// garante que sejam arrays
$selGrupos = is_array($selGrupos) ? $selGrupos : [];
$selPapeis = is_array($selPapeis) ? $selPapeis : [];
$selPerfis = is_array($selPerfis) ? $selPerfis : [];

$grupos = $papeis = $perfis = [];

if (isset($conn) && $conn instanceof mysqli) {

    if ($res = $conn->query("SELECT id, nome FROM grupos ORDER BY nome")) {
        while ($row = $res->fetch_assoc()) {
            $grupos[] = $row;
        }
        $res->free();
    }

    if ($res = $conn->query("SELECT id, nome FROM papeis ORDER BY nome")) {
        while ($row = $res->fetch_assoc()) {
            $papeis[] = $row;
        }
        $res->free();
    }

    if ($res = $conn->query("SELECT id, nome FROM perfis ORDER BY nome")) {
        while ($row = $res->fetch_assoc()) {
            $perfis[] = $row;
        }
        $res->free();
    }
}
?>

  <form method="post" action="/modules/bpm/wizard_steps/save.php?step=2">
    <div class="row g-4">

      <div class="col-12 col-lg-3">
        <div class="mb-2"><strong>Grupos</strong></div>
        <select name="grupos[]" class="form-select w-100" multiple size="8">
          <?php foreach ($grupos as $g): ?>
            <?php
              $id   = (string)$g['id'];
              $nome = $g['nome'];
              $sel  = in_array($id, $selGrupos, true) ? 'selected' : '';
            ?>
            <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $sel; ?>>
              <?php echo htmlspecialchars($nome); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small text-muted mt-2">
          Grupos que poderão iniciar ou participar deste processo.
        </div>
      </div>

      <div class="col-12 col-lg-3">
        <div class="mb-2"><strong>Papéis</strong></div>
        <select name="papeis[]" class="form-select w-100" multiple size="8">
          <?php foreach ($papeis as $p): ?>
            <?php
              $id   = (string)$p['id'];
              $nome = $p['nome'];
              $sel  = in_array($id, $selPapeis, true) ? 'selected' : '';
            ?>
            <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $sel; ?>>
              <?php echo htmlspecialchars($nome); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small text-muted mt-2">
          Papéis vinculados às tarefas deste processo.
        </div>
      </div>

      <div class="col-12 col-lg-3">
        <div class="mb-2"><strong>Perfis</strong></div>
        <select name="perfis[]" class="form-select w-100" multiple size="8">
          <?php foreach ($perfis as $pf): ?>
            <?php
              $id   = (string)$pf['id'];
              $nome = $pf['nome'];
              $sel  = in_array($id, $selPerfis, true) ? 'selected' : '';
            ?>
            <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $sel; ?>>
              <?php echo htmlspecialchars($nome); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small text-muted mt-2">
          Perfis autorizados a visualizar/atuar no processo.
        </div>
      </div>

    </div>

    <button class="btn primary mt-3">Salvar</button>
  </form>

</div>
