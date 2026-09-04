<?php
/**
 * Lese-/Schreibfunktionen für die Regatta-JSON-Datei mit Datei-Sperre für
 * atomare Read-Modify-Write-Zyklen (mehrere Skipper schreiben gleichzeitig).
 */

require_once __DIR__ . '/config.php';

class RegattaError extends Exception {}

function regatta_default_data(): array {
    return [
        'event' => ['name' => 'SKB Vereinsregatta', 'datum' => date('Y-m-d'), 'kurse' => 3],
        'boote' => [],
        'durchlaeufe' => [],
    ];
}

function regatta_normalize(array $data): array {
    $data += regatta_default_data();
    $data['event'] = is_array($data['event'] ?? null) ? ($data['event'] + regatta_default_data()['event']) : regatta_default_data()['event'];
    $data['boote'] = is_array($data['boote'] ?? null) ? $data['boote'] : [];
    $data['durchlaeufe'] = is_array($data['durchlaeufe'] ?? null) ? $data['durchlaeufe'] : [];
    return $data;
}

function regatta_load(): array {
    $path = REGATTA_JSON_PATH;
    if (!file_exists($path)) return regatta_default_data();
    $fh = fopen($path, 'r');
    if (!$fh) return regatta_default_data();
    flock($fh, LOCK_SH);
    $content = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode($content, true);
    return regatta_normalize(is_array($data) ? $data : []);
}

function regatta_update(callable $mutator): array {
    $path = REGATTA_JSON_PATH;
    $fh = fopen($path, 'c+');
    if (!$fh) throw new RegattaError('Konnte Datendatei nicht öffnen.');
    if (!flock($fh, LOCK_EX)) { fclose($fh); throw new RegattaError('Datendatei ist gerade gesperrt, bitte kurz erneut versuchen.'); }
    try {
        $content = stream_get_contents($fh);
        $data = json_decode($content, true);
        $data = regatta_normalize(is_array($data) ? $data : []);
        [$data, $result] = $mutator($data);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fh);
        return [$data, $result];
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function regatta_new_id(): string {
    return date('YmdHis') . '-' . bin2hex(random_bytes(3));
}

function regatta_corrected_time(float $elapsedSec, float $yardstick): float {
    return $elapsedSec * $yardstick / 1000;
}

function regatta_zeiten(array $eintrag, array $boote): ?array {
    if (empty($eintrag['start_server']) || empty($eintrag['ziel_server'])) return null;
    $boot = null;
    foreach ($boote as $b) { if ($b['id'] === $eintrag['boot_id']) { $boot = $b; break; } }
    if (!$boot) return null;
    $elapsed = ($eintrag['ziel_server'] - $eintrag['start_server']) / 1000;
    if ($elapsed < 0) return null;
    return [$elapsed, regatta_corrected_time($elapsed, (float) $boot['yardstick'])];
}

function fmt_dauer(float $sec): string {
    $m = floor($sec / 60);
    $s = $sec - $m * 60;
    return sprintf('%d:%04.1f', $m, $s);
}
