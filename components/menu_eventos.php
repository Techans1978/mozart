<?php
// components/menu_eventos.php — Widget compacto, sem funções globais

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Sao_Paulo');

/* ===== helpers locais (sem declarar function global) ===== */
$esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$labelDay = static function(DateTime $d){
  $h = new DateTime('today'); $a = (clone $h)->modify('+1 day');
  if ($d->format('Y-m-d') === $h->format('Y-m-d')) return 'Hoje';
  if ($d->format('Y-m-d') === $a->format('Y-m-d')) return 'Amanhã';
  $dias = ['dom','seg','ter','qua','qui','sex','sáb'];
  $meses = [1=>'jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
  return $dias[(int)$d->format('w')] . ', ' . $d->format('d') . ' ' . $meses[(int)$d->format('n')];
};
$horaFmt = static fn(DateTime $d) => $d->format('H:i');

/* ===== dados: 1) injetado  2) banco (se existir)  3) demo ===== */
$items = isset($moz_evt_items) && is_array($moz_evt_items) ? $moz_evt_items : null;

if (!$items) {
  // tenta banco se já houver conexões globais ($pdo ou $mysqli/$conn)
  $pdo = $pdo ?? null;
  $mysqli = $mysqli ?? ($conn ?? null);

  $ini = (new DateTime('today 00:00:00'))->format('Y-m-d H:i:s');
  $fim = (new DateTime('+7 days 23:59:59'))->format('Y-m-d H:i:s');
  $sql = "SELECT id, titulo, local, inicio, fim
          FROM moz_agenda_eventos
          WHERE inicio BETWEEN ? AND ?
          ORDER BY inicio ASC";
  try {
    if ($pdo instanceof PDO) {
      $st = $pdo->prepare($sql);
      $st->execute([$ini,$fim]);
      $items = $st->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($mysqli instanceof mysqli) {
      $st = $mysqli->prepare($sql);
      $st->bind_param('ss',$ini,$fim);
      $st->execute();
      $items = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    }
  } catch (Throwable $e) {
    // ignora e cai no demo
  }
}

if (!$items) {
  // demo de hoje
  $hoje = date('Y-m-d');
  $items = [
    ['titulo'=>'Reunião Equipe TI','local'=>null,'inicio'=>"$hoje 09:30:00",'fim'=>null],
    ['titulo'=>'Aprovação de compras','local'=>null,'inicio'=>"$hoje 11:00:00",'fim'=>null],
    ['titulo'=>'Visita técnica — Loja 12','local'=>'Loja 12','inicio'=>"$hoje 14:00:00",'fim'=>null],
    ['titulo'=>'Fechamento de chamados','local'=>null,'inicio'=>"$hoje 16:00:00",'fim'=>null],
  ];
}

/* ===== agrupar por dia ===== */
$byDay = [];
foreach ($items as $ev) {
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', substr((string)$ev['inicio'],0,19)) ?: new DateTime((string)$ev['inicio']);
  $key = $dt->format('Y-m-d');
  $ev['__dt'] = $dt;
  $byDay[$key][] = $ev;
}
ksort($byDay);
?>
<style>
.moz-evt-card{border-radius:14px;border:0}
.moz-evt-sec + .moz-evt-sec{margin-top:.5rem}
.moz-evt-day{font-weight:600;margin-bottom:.5rem}
.moz-evt-row{display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.5rem}
.moz-evt-hour{font-weight:700;min-width:56px}
.moz-evt-title{line-height:1.2}
.moz-evt-local{font-size:.875rem;color:#6c757d}
</style>

<div class="card shadow-sm moz-evt-card">
  <div class="card-body p-3">
    <?php if (empty($byDay)): ?>
      <div class="text-muted small">Sem eventos nos próximos dias.</div>
    <?php else: ?>
      <?php foreach ($byDay as $ymd => $list): ?>
        <div class="moz-evt-sec">
          <div class="moz-evt-day"><?= $esc($labelDay($list[0]['__dt'])) ?></div>
          <?php foreach ($list as $ev): ?>
            <div class="moz-evt-row">
              <div class="moz-evt-hour"><?= $esc($horaFmt($ev['__dt'])) ?></div>
              <div class="flex-grow-1">
                <div class="moz-evt-title"><?= $esc($ev['titulo'] ?? '(Sem título)') ?></div>
                <?php if (!empty($ev['local'])): ?>
                  <div class="moz-evt-local">— <?= $esc($ev['local']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
