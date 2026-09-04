<?php
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function ok(array $extra = []): void {
    echo json_encode(array_merge(['ok' => true], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Nur POST erlaubt.', 405);

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;
$action = (string) ($in['action'] ?? '');

try {
    if ($action === 'register_boot') {
        $name = trim((string) ($in['name'] ?? ''));
        $typ = trim((string) ($in['typ'] ?? ''));
        $ys = isset($in['yardstick']) ? (float) $in['yardstick'] : 0.0;

        if ($name === '' || mb_strlen($name) > 80) throw new RegattaError('Bitte einen gültigen Bootsnamen angeben (max. 80 Zeichen).');
        if ($typ === '' || mb_strlen($typ) > 80) throw new RegattaError('Bitte einen gültigen Bootstyp angeben (max. 80 Zeichen).');
        if ($ys <= 0 || $ys > 400) throw new RegattaError('Bitte eine gültige Yardstickzahl angeben (0–400).');

        [, $boot] = regatta_update(function (array $data) use ($name, $typ, $ys) {
            foreach ($data['boote'] as $b) {
                if (mb_strtolower($b['name']) === mb_strtolower($name)) {
                    throw new RegattaError('Ein Boot mit diesem Namen ist schon gemeldet.');
                }
            }
            $boot = ['id' => regatta_new_id(), 'name' => $name, 'typ' => $typ, 'yardstick' => $ys, 'angemeldet_am' => date('c')];
            $data['boote'][] = $boot;
            return [$data, $boot];
        });
        ok(['boot' => $boot]);

    } elseif ($action === 'start' || $action === 'finish') {
        $bootId = trim((string) ($in['boot_id'] ?? ''));
        $durchlauf = (string) ($in['durchlauf'] ?? '');
        $clientTime = isset($in['client_time']) ? (int) $in['client_time'] : null;
        $field = $action === 'start' ? 'start' : 'ziel';

        if ($bootId === '') throw new RegattaError('Kein Boot ausgewählt.');
        if (!in_array($durchlauf, ['1', '2', '3', '4', '5', '6'], true)) throw new RegattaError('Ungültiger Durchlauf.');

        $now = (int) round(microtime(true) * 1000);

        [, $eintrag] = regatta_update(function (array $data) use ($bootId, $durchlauf, $field, $clientTime, $now, $action) {
            $bootExists = false;
            foreach ($data['boote'] as $b) { if ($b['id'] === $bootId) { $bootExists = true; break; } }
            if (!$bootExists) throw new RegattaError('Dieses Boot ist nicht (mehr) gemeldet.');

            $idx = null;
            foreach ($data['durchlaeufe'] as $i => $d) {
                if ($d['boot_id'] === $bootId && $d['durchlauf'] === $durchlauf) { $idx = $i; break; }
            }
            if ($idx === null) {
                $data['durchlaeufe'][] = [
                    'id' => regatta_new_id(), 'boot_id' => $bootId, 'durchlauf' => $durchlauf,
                    'start_client' => null, 'start_server' => null, 'ziel_client' => null, 'ziel_server' => null,
                ];
                $idx = count($data['durchlaeufe']) - 1;
            }
            if ($action === 'finish' && empty($data['durchlaeufe'][$idx]['start_server'])) {
                throw new RegattaError('Für diesen Durchlauf wurde noch kein Start erfasst.');
            }
            if (empty($data['durchlaeufe'][$idx][$field . '_server'])) {
                $data['durchlaeufe'][$idx][$field . '_client'] = $clientTime;
                $data['durchlaeufe'][$idx][$field . '_server'] = $now;
            }
            return [$data, $data['durchlaeufe'][$idx]];
        });
        ok(['eintrag' => $eintrag]);

    } else {
        throw new RegattaError('Unbekannte Aktion.');
    }
} catch (RegattaError $e) {
    fail($e->getMessage());
} catch (Throwable $e) {
    fail('Serverfehler. Bitte in ein paar Sekunden erneut versuchen.', 500);
}
