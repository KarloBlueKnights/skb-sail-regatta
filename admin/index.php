<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib.php';
require_login();

$flash = '';
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    try {
        if ($do === 'update_event') {
            $name = trim($_POST['name'] ?? '');
            $datum = trim($_POST['datum'] ?? '');
            $kurse = max(1, min(6, (int) ($_POST['kurse'] ?? 3)));
            if ($name === '' || mb_strlen($name) > 120) throw new RegattaError('Bitte einen gültigen Regatta-Namen angeben.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || !strtotime($datum)) throw new RegattaError('Bitte ein gültiges Datum angeben.');

            regatta_update(function (array $data) use ($name, $datum, $kurse) {
                $data['event'] = ['name' => $name, 'datum' => $datum, 'kurse' => $kurse];
                return [$data, null];
            });
            header('Location: index.php?saved=1'); exit;
        }

        if ($do === 'delete_boot') {
            $id = trim($_POST['id'] ?? '');
            regatta_update(function (array $data) use ($id) {
                $data['boote'] = array_values(array_filter($data['boote'], fn($b) => $b['id'] !== $id));
                $data['durchlaeufe'] = array_values(array_filter($data['durchlaeufe'], fn($d) => $d['boot_id'] !== $id));
                return [$data, null];
            });
            header('Location: index.php?deleted=1'); exit;
        }

        if ($do === 'delete_durchlauf') {
            $id = trim($_POST['id'] ?? '');
            regatta_update(function (array $data) use ($id) {
                $data['durchlaeufe'] = array_values(array_filter($data['durchlaeufe'], fn($d) => $d['id'] !== $id));
                return [$data, null];
            });
            header('Location: index.php?deleted=1'); exit;
        }

        if ($do === 'reset_regatta') {
            $archivName = 'regatta_archiv_' . date('Y-m-d_His') . '.json';
            regatta_update(function (array $data) use ($archivName) {
                file_put_contents(__DIR__ . '/../data/' . $archivName, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $data['boote'] = [];
                $data['durchlaeufe'] = [];
                return [$data, null];
            });
            header('Location: index.php?archived=1'); exit;
        }

        throw new RegattaError('Unbekannte Aktion.');
    } catch (RegattaError $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

if (isset($_GET['saved'])) $flash = 'Gespeichert.';
if (isset($_GET['deleted'])) $flash = 'Eintrag gelöscht.';
if (isset($_GET['archived'])) $flash = 'Regatta archiviert und zurückgesetzt — bereit für den nächsten Regattatag.';
if (isset($_GET['error'])) { $flash = 'Es ist ein Fehler aufgetreten.'; $flashType = 'error'; }

$data = regatta_load();
$boote = $data['boote'];
$byId = [];
foreach ($boote as $b) { $byId[$b['id']] = $b; }

usort($data['durchlaeufe'], fn($a, $b) => $a['durchlauf'] <=> $b['durchlauf'] ?: strcmp($a['id'], $b['id']));

$gesamt = [];
foreach ($data['durchlaeufe'] as $d) {
    $zeiten = regatta_zeiten($d, $boote);
    if (!$zeiten) continue;
    [$elapsed, $corr] = $zeiten;
    $bid = $d['boot_id'];
    if (!isset($gesamt[$bid])) $gesamt[$bid] = ['boot' => $byId[$bid], 'runs' => 0, 'sum' => 0.0];
    $gesamt[$bid]['runs']++;
    $gesamt[$bid]['sum'] += $corr;
}
usort($gesamt, fn($a, $b) => $a['sum'] <=> $b['sum']);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Regatta verwalten — Admin — Segelkameradschaft Buchholz e.V.</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,650&family=Figtree:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{ --navy:#0a1e3f; --brass:#c6a15b; --brass-deep:#a5823f; --paper:#f4f6fa; --white:#fff; --ink:#14243d; --slate:#57687f; --line:rgba(20,36,61,.12); }
  *{box-sizing:border-box}
  body{ margin:0; background:var(--paper); font-family:'Figtree',sans-serif; color:var(--ink); }
  .bar{ background:var(--navy); color:#fff; padding:.9rem 1.4rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.6rem; }
  .bar strong{ font-family:'Fraunces',serif; }
  .bar a{ color:#c9d4e6; text-decoration:none; font-size:.82rem; margin-left:1rem; }
  .bar a:hover{ color:var(--brass); }
  main{ max-width:900px; margin:0 auto; padding:2rem 1.2rem 4rem; }
  .card{ background:var(--white); border:1px solid var(--line); border-radius:10px; padding:1.4rem 1.5rem; margin-bottom:1.4rem; box-shadow:0 10px 26px -18px rgba(10,30,63,.25); }
  .card h1, .card h2{ font-family:'Fraunces',serif; margin:0 0 1rem; color:var(--navy); }
  .card h1{ font-size:1.4rem; } .card h2{ font-size:1.15rem; }
  .flash{ padding:.7rem 1rem; border-radius:8px; font-size:.88rem; margin-bottom:1.2rem; }
  .flash.ok{ background:#e6f4ea; color:#1e5e34; } .flash.error{ background:#fdecea; color:#8a2a20; }
  label{ display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--slate); margin:.8rem 0 .3rem; }
  input{ width:100%; padding:.6rem .7rem; border:1px solid var(--line); border-radius:7px; font-size:.95rem; }
  .grid2{ display:grid; grid-template-columns:1fr 1fr; gap:0 1.2rem; }
  .btn{ display:inline-block; padding:.6rem 1.1rem; border:none; border-radius:7px; font-weight:700; font-size:.85rem; cursor:pointer; margin-top:1rem; }
  .btn-ink{ background:var(--navy); color:#fff; }
  table{ width:100%; border-collapse:collapse; font-size:.88rem; }
  th,td{ text-align:left; padding:.6rem .5rem; border-bottom:1px solid var(--line); }
  th{ font-family:'Space Mono',monospace; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:var(--slate); }
  .danger{ background:none; border:1px solid rgba(138,42,32,.35); color:#8a2a20; border-radius:6px; padding:.3rem .7rem; font-size:.76rem; cursor:pointer; }
  .danger:hover{ background:#fdecea; }
  .muted{ color:var(--slate); font-size:.88rem; }
  code{ background:var(--paper); padding:.1rem .35rem; border-radius:4px; }
</style>
</head>
<body>
  <div class="bar">
    <strong>⛵ SKB &middot; Regatta-Verwaltung</strong>
    <div>
      <a href="../index.html" target="_blank" rel="noopener">Öffentliche Seite ↗</a>
      <a href="logout.php">Abmelden</a>
    </div>
  </div>

  <main>
    <?php if ($flash): ?><p class="flash <?= $flashType ?>"><?= e($flash) ?></p><?php endif; ?>

    <div class="card">
      <h1>Event-Einstellungen</h1>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="update_event">
        <div class="grid2">
          <div><label for="f-name">Name der Regatta</label>
            <input id="f-name" name="name" type="text" required maxlength="120" value="<?= e($data['event']['name']) ?>"></div>
          <div><label for="f-datum">Datum</label>
            <input id="f-datum" name="datum" type="date" required value="<?= e($data['event']['datum']) ?>"></div>
        </div>
        <div style="max-width:200px"><label for="f-kurse">Anzahl Durchläufe (1–6)</label>
          <input id="f-kurse" name="kurse" type="number" min="1" max="6" value="<?= (int) $data['event']['kurse'] ?>"></div>
        <button class="btn btn-ink" type="submit">Speichern</button>
      </form>
    </div>

    <div class="card">
      <h2>Gemeldete Boote (<?= count($boote) ?>)</h2>
      <?php if (!$boote): ?><p class="muted">Noch keine Boote gemeldet.</p><?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>Boot</th><th>Typ</th><th>Yardstick</th><th>Gemeldet</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($boote as $b): ?>
          <tr>
            <td><?= e($b['name']) ?></td><td><?= e($b['typ']) ?></td><td><?= e((string) $b['yardstick']) ?></td>
            <td><?= e(date('d.m. H:i', strtotime($b['angemeldet_am'] ?? 'now'))) ?></td>
            <td><form method="POST" onsubmit="return confirm('Boot samt aller erfassten Zeiten wirklich entfernen?');">
              <?= csrf_field() ?><input type="hidden" name="do" value="delete_boot"><input type="hidden" name="id" value="<?= e($b['id']) ?>">
              <button type="submit" class="danger">Entfernen</button></form></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Erfasste Durchläufe</h2>
      <?php if (!$data['durchlaeufe']): ?><p class="muted">Noch keine Start-/Zielzeiten erfasst.</p><?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>Durchlauf</th><th>Boot</th><th>Start</th><th>Ziel</th><th>Fahrzeit</th><th>Wertungszeit</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($data['durchlaeufe'] as $d): $boot = $byId[$d['boot_id']] ?? null; $z = $boot ? regatta_zeiten($d, $boote) : null; ?>
          <tr>
            <td><?= e($d['durchlauf']) ?></td>
            <td><?= $boot ? e($boot['name']) : '<em>gelöschtes Boot</em>' ?></td>
            <td><?= $d['start_server'] ? e(date('H:i:s', intdiv($d['start_server'], 1000))) : '—' ?></td>
            <td><?= $d['ziel_server'] ? e(date('H:i:s', intdiv($d['ziel_server'], 1000))) : '—' ?></td>
            <td><?= $z ? e(fmt_dauer($z[0])) : '—' ?></td>
            <td><?= $z ? '<b>' . e(fmt_dauer($z[1])) . '</b>' : '—' ?></td>
            <td><form method="POST" onsubmit="return confirm('Diesen Eintrag löschen? Der Skipper kann Start/Ziel danach erneut erfassen.');">
              <?= csrf_field() ?><input type="hidden" name="do" value="delete_durchlauf"><input type="hidden" name="id" value="<?= e($d['id']) ?>">
              <button type="submit" class="danger">Löschen</button></form></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Gesamtwertung</h2>
      <?php if (!$gesamt): ?><p class="muted">Noch keine abgeschlossenen Durchläufe.</p><?php else: ?>
      <div style="overflow-x:auto"><table>
        <thead><tr><th>Platz</th><th>Boot</th><th>Durchläufe</th><th>Summe Wertungszeit</th></tr></thead>
        <tbody>
          <?php foreach ($gesamt as $i => $g): ?>
          <tr><td><?= $i + 1 ?></td><td><?= e($g['boot']['name']) ?></td><td><?= (int) $g['runs'] ?></td><td><b><?= e(fmt_dauer($g['sum'])) ?></b></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
      <p class="muted" style="margin-top:1rem">Wertungszeit = Fahrzeit × Yardstickzahl ÷ 1000. Formel bei Bedarf in <code>lib.php</code> anpassen.</p>
    </div>

    <div class="card">
      <h2>Neuer Regattatag</h2>
      <p class="muted">Sichert alle Boote und Zeiten in eine Archivdatei unter <code>data/</code> und leert die aktuelle Liste. Datum oben vorher anpassen.</p>
      <form method="POST" onsubmit="return confirm('Aktuelle Boote und Zeiten wirklich archivieren und leeren?');">
        <?= csrf_field() ?><input type="hidden" name="do" value="reset_regatta">
        <button type="submit" class="btn btn-ink">Archivieren &amp; zurücksetzen</button>
      </form>
    </div>
  </main>
</body>
</html>
