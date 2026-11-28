<?php
/**
 * BERTA — verificación de los problemas encontrados en la revisión (docs/review-and-proposed-fixes.html).
 *
 * Cada elemento de la lista tiene una comprobación. Casi todas ejecutan BERTA (main.php) en un directorio
 * temporal y comparan su salida con un modelo de referencia independiente incluido en este fichero, de modo
 * que funcionan aunque el arreglo se haga de forma distinta a la propuesta.
 *
 * Uso:
 *   php tests/verify_fixes.php                 todas las comprobaciones
 *   php tests/verify_fixes.php --quick         sin las pruebas lentas de rendimiento
 *   php tests/verify_fixes.php --only=M1,X3    sólo algunas
 *   php tests/verify_fixes.php --list          muestra la lista sin ejecutar nada
 *   opciones: --root=DIR (código a probar, por defecto el proyecto)  --tmp=DIR  --keep  --no-write
 *
 * Estados: FIXED (arreglado), OPEN (pendiente), MANUAL (requiere decisión o revisión humana),
 *          BLOCKED (depende de otro elemento), SKIPPED (falta algo en el entorno), ERROR (la prueba falló).
 * El resultado se guarda en tests/fix-status.md. Las decisiones manuales se registran en
 * tests/fix-decisions.json, p. ej. {"Q1": "PREDICT da puntos de terreno; BERTA_PERFIL_ESCALONADO activado"}.
 */
declare(strict_types=1);
ini_set('memory_limit', '2G');

const R_TIERRA = 6371000.0;
const NM = 1852.0;
const PIE = 0.3048;
const MURO = 32714.4;

$opts = getopt('', ['root:', 'tmp:', 'only:', 'quick', 'list', 'keep', 'no-write']);
$ROOT = realpath($opts['root'] ?? dirname(__DIR__));
if (false === $ROOT || !is_file("$ROOT/main.php")) { fwrite(STDERR, "No encuentro main.php en --root\n"); exit(2); }
$TMP = $opts['tmp'] ?? (dirname(__DIR__) . '/tests/.tmp');
$ONLY = isset($opts['only']) ? array_map('trim', explode(',', strtoupper((string) $opts['only']))) : null;
$QUICK = isset($opts['quick']);
$DECISIONS_FILE = __DIR__ . '/fix-decisions.json';
$STATUS_FILE = __DIR__ . '/fix-status.md';

// ---------------------------------------------------------------- lista de elementos
// id, grupo, título, dónde está el arreglo propuesto, función de comprobación, lenta
$ITEMS = [
    ['M1', 'Mono-radar', 'PSR range converted to metres twice: PSR coverage always empty (= E1)', 'Change 2', 'check_M1', false],
    ['M2', 'Mono-radar', 'Mono cache key ignores the cone flag (-c)', 'Change 11', 'check_M2', false],
    ['M3', 'Mono-radar', 'Mono cache not invalidated when the .scr screening changes', 'Change 11', 'check_M3', false],
    ['M4', 'Mono-radar', 'Tolerance constant mixes NM and metres (20 NM / R in m)', 'Change 5a', 'check_M4', false],
    ['M5', 'Mono-radar', 'Range too short for the level gives NaN (acos without clamp) (= E2)', 'Change 5b', 'check_M5', false],
    ['M6a', 'Mono-radar', 'Malformed screening silently accepted or PHP crash (bad line, non-numeric, count, azimuth order, truncated)', 'Change 10', 'check_M6a', false],
    ['M6b', 'Mono-radar', 'Forced-range log divides NM by 1852', 'Change 4', 'check_M6b', false],
    ['L1', 'Mono-radar', 'Above radar: several crossings per azimuth merged into one ring (latent)', 'Change 6A', 'check_L1', false],
    ['X1', 'Multi-radar', 'Polygon cache returns results from another radar set', 'Change 9', 'check_X1', false],
    ['X2', 'Multi-radar', 'Polygon cache ignores -m / cone / input changes', 'Change 9', 'check_X2', false],
    ['X3', 'Multi-radar', '"unica" layer missing from -2 output', 'Change 7c', 'check_X3', false],
    ['X4', 'Multi-radar', 'Empty single-radar result crashes the multi-radar run', 'not in patch (Change 5b avoids one cause)', 'check_X4', false],
    ['X5', 'Multi-radar', '"Inversa" view matches radar names by substring', 'not in patch', 'check_X5', false],
    ['X6', 'Multi-radar', 'Multi-radar cost grows as 2^N (= S3)', 'Change 8', 'check_X6', true],
    ['O1', 'Output/options', 'KMZ file name without flight level (overwritten every level)', 'not in patch', 'check_O1', false],
    ['O2', 'Output/options', 'Multi output name without the radar set (overwritten every run)', 'not in patch', 'check_O2', false],
    ['O3', 'Output/options', '--screening-file long option unusable (missing ":")', 'commit 161aa2b', 'check_O3', false],
    ['O4', 'Output/options', 'Help lists options that do not work (--rascal, --no-rascal, -p)', 'commit f2f3bb1', 'check_O4', false],
    ['O5', 'Output/options', 'Dead code (functions never called, EtaEstimator)', 'partly (Change 6 removes old functions)', 'check_O5', false],
    ['O6', 'Output/options', 'Radar data: .rdb pointing to a missing .scr (sesolles)', 'data, not code', 'check_O6', false],
    ['O7', 'Output/options', 'A level without coverage must not produce a KML (above or below the radar, or from an old cache)', 'Change 5c', 'check_O7', false],
    ['E3', 'Maths', 'Fragile polygon union (20 m overlap, rounding): wrong points below the radar (= S1)', 'Change 6D', 'check_E3', false],
    ['E4', 'Maths', 'Terrain-shadow floor (staircase) — flag BERTA_PERFIL_ESCALONADO', 'Change 6B (flag)', 'check_E4', false],
    ['E5', 'Maths', 'Feet constant 1/3.2808 instead of 0.3048', 'Change 1', 'check_E5', false],
    ['E6', 'Maths', 'W += 0.01 workaround instead of robust crossing classification', 'Change 6C', 'check_E6', false],
    ['E7', 'Maths', 'Capped wall point moved off the range line', 'Change 6A', 'check_E7', false],
    ['E8', 'Maths', 'Cone radius uses a fixed 45° instead of the .rdb cone angle', 'Change 3 (done in af810d4)', 'check_E8', false],
    ['P1', 'Precision', 'Approximate vertex formula (up to 80 m at 250 NM) + 20 m offset', 'Change 6E', 'check_P1', false],
    ['P2', 'Precision', 'Sphere instead of WGS84 — flag BERTA_ELIPSOIDE_WGS84', 'Change 6E (flag)', 'check_P2', false],
    ['S2', 'Speed', 'Full FL1-400 single-radar sweep (profile once per radar, segment rejection)', 'Change 6B-C', 'check_S2', true],
    ['Q1', 'Open question', 'Confirm what PREDICT screening points are (decides E4)', 'manual', 'check_Q', false],
    ['Q2', 'Open question', 'Compare one level with a PREDICT export (decides P2, Earth radius)', 'manual', 'check_Q', false],
    ['Q3', 'Open question', 'Is SASS-C ConeOfSilence an elevation angle or a half-angle from vertical? (E8) — answered', 'manual', 'check_Q', false],
];
$ALIASES = ['E1' => 'M1', 'E2' => 'M5', 'S1' => 'E3', 'S3' => 'X6'];

if (isset($opts['list'])) {
    foreach ($ITEMS as [$id, $g, $t, $w]) printf("%-4s %-15s %s  [%s]\n", $id, $g, $t, $w);
    echo "Aliases: " . implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($ALIASES), $ALIASES)) . "\n";
    exit(0);
}
if (!is_file("$ROOT/vendor/autoload.php")) { fwrite(STDERR, "Falta $ROOT/vendor: ejecuta 'composer install'\n"); exit(2); }
if (!is_dir("$ROOT/spain.tsk/radar_data.rbk")) { fwrite(STDERR, "Falta $ROOT/spain.tsk/radar_data.rbk\n"); exit(2); }

// ---------------------------------------------------------------- utilidades de ejecución
function rmtree(string $d): void {
    if (!file_exists($d) && !is_link($d)) return;
    if (is_link($d) || is_file($d)) { unlink($d); return; }
    foreach (scandir($d) as $f) if ($f !== '.' && $f !== '..') rmtree("$d/$f");
    rmdir($d);
}

/** Directorio de trabajo nuevo: enlaces a los ficheros del proyecto; spain.tsk enlazado o propio ($datos). */
function workspace(string $name, bool $datosPropios = false): string {
    global $ROOT, $TMP;
    $ws = "$TMP/$name";
    rmtree($ws); mkdir($ws, 0775, true);
    foreach (scandir($ROOT) as $f) {
        if (in_array($f, ['.', '..', '.git', 'cache', 'RESULTADOS', 'resultados', 'tests', 'docs', 'spain.tsk'], true)) continue;
        symlink("$ROOT/$f", "$ws/$f");
    }
    if ($datosPropios) {
        mkdir("$ws/spain.tsk/radar_data.rbk", 0775, true);
        foreach (glob("$ROOT/spain.tsk/radar_data.rbk/*") as $f) symlink(realpath($f), "$ws/spain.tsk/radar_data.rbk/" . basename($f));
    } else {
        symlink(realpath("$ROOT/spain.tsk"), "$ws/spain.tsk");
    }
    return $ws;
}

/** Ejecuta main.php con los argumentos dados en $ws. Devuelve [código, stdout, stderr, segundos]. */
function berta(string $ws, array $args, int $timeout = 900): array {
    $cmd = array_merge([PHP_BINARY, 'main.php'], $args);
    $t = microtime(true);
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $ws);
    if (!is_resource($p)) return [-1, '', 'proc_open failed', 0.0];
    stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    $out = ''; $err = '';
    while (true) {
        $st = proc_get_status($p);
        $out .= stream_get_contents($pipes[1]); $err .= stream_get_contents($pipes[2]);
        if (!$st['running']) { $code = $st['exitcode']; break; }
        if (microtime(true) - $t > $timeout) { proc_terminate($p); $code = -2; break; }
        usleep(20000);
    }
    $out .= stream_get_contents($pipes[1]); $err .= stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    return [$code, $out, $err, microtime(true) - $t];
}

function crashed(array $r): bool { return $r[0] !== 0 || preg_match('/PHP Fatal|Uncaught|Fatal error/', $r[2] . $r[1]) === 1; }

function files_under(string $dir, string $pattern): array {
    if (!is_dir($dir)) return [];
    $res = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if (preg_match($pattern, $f->getFilename())) $res[] = $f->getPathname();
    sort($res); return $res;
}

/** ¿Está el fichero bajo RESULTADOS/MULTI? (sólo se mira la parte posterior a RESULTADOS) */
function is_multi_path(string $f): bool {
    $p = strrpos($f, '/RESULTADOS/'); return $p !== false && preg_match('#^/RESULTADOS/MULTI/#i', substr($f, $p)) === 1;
}

/** KML monoradar de un sensor y nivel (busca por nombre, tolera cambios de carpeta). */
function mono_kml(string $ws, string $sensor, int $fl): ?string {
    $fl3 = sprintf('%03d', $fl);
    $c = array_values(array_filter(files_under("$ws/RESULTADOS", '/\.kml$/i'), function ($f) use ($sensor, $fl3) {
        $b = basename($f);
        if (is_multi_path($f) || !str_starts_with($b, $sensor . '-') || !str_contains($b, $fl3)) return false;
        return str_ends_with($sensor, '-psr') || !str_starts_with($b, $sensor . '-psr');
    }));
    return $c ? file_get_contents($c[0]) : null;
}

/** KML multiradar más reciente de un nivel. */
function multi_kml(string $ws, int $fl): ?string {
    $c = array_filter(files_under("$ws/RESULTADOS", '/\.kml$/i'), fn($f) => is_multi_path($f) && str_contains(basename($f), sprintf('%03d', $fl)));
    if (!$c) return null;
    usort($c, fn($a, $b) => filemtime($b) <=> filemtime($a));
    return file_get_contents(array_values($c)[0]);
}

/** Polígonos de un KML: lista de [exterior, agujeros...], cada anillo [[lat, lon], ...]. */
function kml_polygons(string $kml): array {
    $res = [];
    preg_match_all('#<Polygon>(.*?)</Polygon>#s', $kml, $pm);
    foreach ($pm[1] as $pg) {
        $poly = [];
        preg_match_all('#<(outer|inner)BoundaryIs>.*?<coordinates>(.*?)</coordinates>#s', $pg, $cm, PREG_SET_ORDER);
        foreach ($cm as $c) {
            $ring = [];
            foreach (preg_split('/\s+/', trim($c[2])) as $t) { if ($t === '') continue; $v = explode(',', $t); $ring[] = [(float) $v[1], (float) $v[0]]; }
            if ($c[1] === 'outer') array_unshift($poly, $ring); else $poly[] = $ring;
        }
        if ($poly) $res[] = $poly;
    }
    return $res;
}

function pip(float $lat, float $lon, array $ring): bool {
    $in = false; $n = count($ring);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        [$yi, $xi] = $ring[$i]; [$yj, $xj] = $ring[$j];
        if ((($yi > $lat) !== ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) $in = !$in;
    }
    return $in;
}
function inside_polys(array $polys, float $lat, float $lon): bool {
    foreach ($polys as $poly) {
        if (!pip($lat, $lon, $poly[0])) continue;
        $hole = false; for ($k = 1; $k < count($poly); $k++) if (pip($lat, $lon, $poly[$k])) { $hole = true; break; }
        if (!$hole) return true;
    }
    return false;
}

function folder_body(string $txt, string $name): ?string {
    $i = strpos($txt, "<name>$name</name>"); if (false === $i) return null;
    $depth = 1; $pos = $i;
    while ($depth > 0) {
        $o = strpos($txt, '<Folder>', $pos); $c = strpos($txt, '</Folder>', $pos);
        if (false === $c) return substr($txt, $i);
        if (false !== $o && $o < $c) { $depth++; $pos = $o + 8; } else { $depth--; $pos = $c + 9; }
    }
    return substr($txt, $i, $pos - $i);
}

/** Lee una constante "const NOMBRE = valor;" del código probado. */
function code_const(string $name): ?string {
    global $ROOT;
    foreach (glob("$ROOT/*.php") as $f) if (preg_match('/^\s*const\s+' . $name . '\s*=\s*([^;]+);/mi', (string) file_get_contents($f), $m)) return trim($m[1]);
    return null;
}
function flag(string $name): bool { $v = code_const($name); return $v !== null && strtolower($v) === 'true'; }
/** ¿Se usa la constante en algún sitio aparte de su definición? */
function const_used(string $name): bool {
    global $ROOT;
    foreach (glob("$ROOT/*.php") as $f) foreach (file($f) as $l) if (str_contains($l, $name) && !preg_match('/^\s*const\s+' . $name . '\b/i', $l)) return true;
    return false;
}

function rdb_variant(string $src, string $dst, array $changes): void {
    $t = (string) file_get_contents($src);
    foreach ($changes as $k => $v) $t = preg_replace('/^' . $k . ': ".*"$/m', $k . ': "' . $v . '"', $t);
    file_put_contents($dst, $t);
}

// ---------------------------------------------------------------- modelo de referencia (independiente de BERTA)
function ref_radar(string $ws, string $name, float $forced = -1.0): array {
    $base = preg_replace('/-psr$/', '', $name);
    $v = [];
    foreach (file("$ws/spain.tsk/radar_data.rbk/$base.rdb", FILE_IGNORE_NEW_LINES) as $line) if (preg_match('/^(\w+): "(.*)"/', trim($line), $m)) $v[$m[1]] = $m[2];
    $dms = function (string $s): float { preg_match('/(\d+):(\d+):([\d.]+)([NSEW])/', $s, $m); $d = $m[1] + $m[2] / 60 + (float) $m[3] / 3600; return in_array($m[4], ['S', 'W']) ? -$d : $d; };
    $lines = file("$ws/" . preg_replace('#^\./#', '', $v['screening']), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $hdr = implode("\n", array_slice($lines, 0, 12));
    preg_match('/k_factor\s+(\S+)/', $hdr, $k); preg_match('/TowerHeight\s+(\S+)/', $hdr, $tw); preg_match('/TerrainHeight\s+(\S+)/', $hdr, $te);
    $i = 0; while (!str_starts_with($lines[$i], 'AZIMUTH')) $i++;
    $az = [];
    while ($i < count($lines)) {
        if (!str_starts_with($lines[$i], 'AZIMUTH')) { $i++; continue; }
        $n = (int) trim($lines[$i + 1]); $obs = [];
        for ($j = 0; $j < $n; $j++) if (preg_match('/\(\s*(\S+)\s*\|\s*(\S+)\s*\)/', $lines[$i + 2 + $j], $m)) $obs[] = [(float) $m[1], (float) $m[2]];
        $az[] = $obs; $i += 2 + $n;
    }
    $psr = $name !== $base;
    $range = $forced > 0 ? $forced : (float) ($psr ? $v['primaryMaximumRange'] : $v['secondaryMaximumRange']);
    $lat = $dms($v['radarLatitude']); $lon = $dms($v['radarLongitude']);
    return ['lat' => deg2rad($lat), 'lon' => deg2rad($lon), 'Rk' => (float) $k[1] * R_TIERRA, 'hA' => (float) $tw[1] + (float) $te[1],
        'range' => $range, 'az' => $az, 'rdb' => $v];
}

function ref_cruces(array $r, float $flm, bool $escalonado): array {
    $Rk = $r['Rk']; $ya = $Rk + $r['hA']; $W2 = ($Rk + $flm) ** 2;
    $c = (($ya) ** 2 + ($Rk + $flm) ** 2 - ($r['range'] * NM) ** 2) / (2 * $ya * ($Rk + $flm));
    $alpha = acos(max(-1.0, min(1.0, $c))); $m = tan(M_PI_2 - $alpha);
    $out = [];
    foreach ($r['az'] as $obs) {
        $pts = [[0.0, $ya]]; $emax = -INF; $h = null;
        foreach ($obs as [$ang, $alt]) {
            if ($ang > $alpha) break;
            $rr = $Rk + $alt; $x = $rr * sin($ang); $y = $rr * cos($ang);
            if ($escalonado) {
                $e = atan2($y - $ya, $x); if ($e <= $emax) continue;
                if ($h) { $den = $h[0] * cos($ang) - ($h[1] - $ya) * sin($ang); if ($den != 0.0) { $s = $ya * sin($ang) / $den; $pts[] = [$s * $h[0], $ya + $s * ($h[1] - $ya)]; } }
                $h = [$x, $y]; $emax = $e;
            }
            $pts[] = [$x, $y];
        }
        [$xb, $yb] = end($pts);
        if (count($pts) >= 2 && sqrt($xb * $xb + $yb * $yb) - $Rk <= 30000) {
            $den = ($yb - $ya) - $m * $xb;
            if ($den != 0.0) { $t = -$ya / $den;
                if ($t >= 1) { $xi = $t * $xb; $yi = $ya + $t * ($yb - $ya); $pts[] = [$xi, $yi]; $top = MURO + $Rk;
                    if (hypot($xi, $yi) < $top) $pts[] = [$top * cos(atan($m)), $top * sin(atan($m))]; } }
        }
        $f = array_map(fn($p) => $p[0] * $p[0] + $p[1] * $p[1] - $W2, $pts); $l = [];
        for ($k = 0; $k < count($pts) - 1; $k++) {
            [$x1, $y1] = $pts[$k]; $dx = $pts[$k + 1][0] - $x1; $dy = $pts[$k + 1][1] - $y1;
            $A = $dx * $dx + $dy * $dy; if ($A == 0.0) continue; $B = 2 * ($x1 * $dx + $y1 * $dy); $C = $f[$k]; $ts = [];
            if (($f[$k] < 0) !== ($f[$k + 1] < 0)) {
                $s = sqrt(max(0.0, $B * $B - 4 * $A * $C)); $q = -0.5 * ($B + ($B >= 0 ? $s : -$s));
                $best = null; foreach ([$q / $A, $q != 0.0 ? $C / $q : $q / $A] as $t) { $tc = max(0.0, min(1.0, $t)); if ($best === null || abs($tc - $t) < abs($best[0] - $best[1])) $best = [$tc, $t]; }
                $ts[] = $best[0];
            } elseif ($f[$k] >= 0) {
                $tv = -$B / (2 * $A);
                if ($tv > 0 && $tv < 1 && ($C - $B * $B / (4 * $A)) < 0) { $s = sqrt($B * $B - 4 * $A * $C); $ts[] = (-$B - $s) / (2 * $A); $ts[] = (-$B + $s) / (2 * $A); }
            }
            foreach ($ts as $t) $l[] = $Rk * atan2($x1 + $t * $dx, $y1 + $t * $dy) / NM;
        }
        $out[] = $l;
    }
    return $out;
}

function ref_intervalos(array $cruces, bool $encima, float $cono = 0.0): array {
    $res = [];
    foreach ($cruces as $l) { if ($encima) array_unshift($l, 0.0); $iv = [];
        for ($i = 0; $i + 1 < count($l); $i += 2) { $s = max($l[$i], $cono); if ($l[$i + 1] > $s) $iv[] = [$s, $l[$i + 1]]; }
        $res[] = $iv; }
    return $res;
}

function ref_destino(float $lat, float $lon, float $d, float $az): array {
    if (flag('BERTA_ELIPSOIDE_WGS84')) return vincenty($lat, $lon, $d, $az);
    $dd = $d / R_TIERRA; $s2 = sin($lat) * cos($dd) + cos($lat) * sin($dd) * cos($az); $lat2 = asin(max(-1.0, min(1.0, $s2)));
    return [rad2deg($lat2), rad2deg($lon + atan2(sin($az) * sin($dd) * cos($lat), cos($dd) - sin($lat) * $s2))];
}
function vincenty(float $lat, float $lon, float $s, float $az): array {
    $a = 6378137.0; $f = 1 / 298.257223563; $b = $a * (1 - $f); $U1 = atan((1 - $f) * tan($lat)); $s1 = atan2(tan($U1), cos($az));
    $sa = cos($U1) * sin($az); $c2a = 1 - $sa * $sa; $u2 = $c2a * ($a * $a - $b * $b) / ($b * $b);
    $A = 1 + $u2 / 16384 * (4096 + $u2 * (-768 + $u2 * (320 - 175 * $u2))); $B = $u2 / 1024 * (256 + $u2 * (-128 + $u2 * (74 - 47 * $u2)));
    $sig = $s / ($b * $A);
    for ($i = 0; $i < 100; $i++) { $c2m = cos(2 * $s1 + $sig); $ds = $B * sin($sig) * ($c2m + $B / 4 * (cos($sig) * (-1 + 2 * $c2m * $c2m) - $B / 6 * $c2m * (-3 + 4 * sin($sig) ** 2) * (-3 + 4 * $c2m * $c2m))); $o = $sig; $sig = $s / ($b * $A) + $ds; if (abs($sig - $o) < 1e-12) break; }
    $c2m = cos(2 * $s1 + $sig); $tmp = sin($U1) * sin($sig) - cos($U1) * cos($sig) * cos($az);
    $lat2 = atan2(sin($U1) * cos($sig) + cos($U1) * sin($sig) * cos($az), (1 - $f) * sqrt($sa * $sa + $tmp * $tmp));
    $lam = atan2(sin($sig) * sin($az), cos($U1) * cos($sig) - sin($U1) * sin($sig) * cos($az)); $C = $f / 16 * $c2a * (4 + $f * (4 - 3 * $c2a));
    $L = $lam - (1 - $C) * $f * $sa * ($sig + $C * sin($sig) * ($c2m + $C * cos($sig) * (-1 + 2 * $c2m * $c2m)));
    return [rad2deg($lat2), rad2deg($lon + $L)];
}
function hav(array $p, array $q): float {
    $a = sin(deg2rad($q[0] - $p[0]) / 2) ** 2 + cos(deg2rad($p[0])) * cos(deg2rad($q[0])) * sin(deg2rad($q[1] - $p[1]) / 2) ** 2;
    return 2 * R_TIERRA * asin(min(1.0, sqrt($a)));
}

/** Muestreo: número de puntos donde el KML de BERTA no coincide con los intervalos de referencia (por debajo del radar). */
function mismatch_below(string $ws, string $sensor, int $fl, int $n, int $seed): array {
    $r = berta($ws, ['-r', $sensor, '-s', (string) $fl, '-z', '-f']);
    if (crashed($r)) return ['error' => 'BERTA crashed: ' . trim(substr($r[2], -300))];
    $kml = mono_kml($ws, $sensor, $fl);
    if ($kml === null) return ['error' => "no KML for $sensor FL$fl"];
    $polys = kml_polygons($kml);
    $rad = ref_radar($ws, $sensor);
    $flm = $fl * 100 * PIE;
    $iv = ref_intervalos(ref_cruces($rad, $flm, flag('BERTA_PERFIL_ESCALONADO')), $flm >= $rad['hA']);
    $N = count($iv); $step = 360.0 / $N; $maxd = 1.0; foreach ($iv as $l) foreach ($l as $x) $maxd = max($maxd, $x[1]);
    mt_srand($seed); $bad = 0;
    for ($i = 0; $i < $n; $i++) {
        $d = $maxd * 1.05 * sqrt(mt_rand() / mt_getrandmax()); $az = 360 * mt_rand() / mt_getrandmax();
        $p = ref_destino($rad['lat'], $rad['lon'], $d * NM, deg2rad($az));
        $j = ((int) floor(($az + $step / 2) / $step)) % $N; $exp = false;
        foreach ($iv[$j] as [$s, $e]) if ($d >= $s && $d <= $e) { $exp = true; break; }
        if (inside_polys($polys, $p[0], $p[1]) !== $exp) $bad++;
    }
    return ['bad' => $bad, 'n' => $n];
}

function res(string $status, string $detail): array { return [$status, $detail]; }

// ---------------------------------------------------------------- comprobaciones
function check_M1(): array {
    $ws = workspace('M1');
    $r = berta($ws, ['-r', 'paracuellos1-psr', '-s', '100', '-z', '-f']);
    if (crashed($r)) return res('OPEN', 'BERTA crashed on paracuellos1-psr');
    $kml = mono_kml($ws, 'paracuellos1-psr', 100);
    $polys = $kml ? kml_polygons($kml) : [];
    if (!$polys) return res('OPEN', 'paracuellos1-psr at FL100 still has no coverage polygon');
    $rad = ref_radar($ws, 'paracuellos1-psr'); $c = [rad2deg($rad['lat']), rad2deg($rad['lon'])]; $max = 0;
    foreach ($polys as $p) foreach ($p[0] as $v) $max = max($max, hav($c, $v) / NM);
    $exp = (float) $rad['rdb']['primaryMaximumRange'];
    return $max <= $exp * 1.02 ? res('FIXED', sprintf('PSR coverage present, farthest point %.1f NM (primary range %.0f NM)', $max, $exp))
        : res('OPEN', sprintf('PSR coverage reaches %.1f NM, beyond its %.0f NM primary range', $max, $exp));
}

function check_M2(): array {
    $ws = workspace('M2');
    berta($ws, ['-r', 'paracuellos1', '-s', '150', '-z']);
    berta($ws, ['-r', 'paracuellos1', '-s', '150', '-z', '-c']);
    $withCone = (int) substr_count((string) mono_kml($ws, 'paracuellos1', 150), '<innerBoundaryIs>');
    berta($ws, ['-r', 'paracuellos1', '-s', '150', '-z']);
    $without = (int) substr_count((string) mono_kml($ws, 'paracuellos1', 150), '<innerBoundaryIs>');
    if ($withCone > 0 && $without == 0) return res('FIXED', 'warm cache: -c adds the cone hole and a later run without -c removes it');
    return res('OPEN', "warm cache: cone holes with -c = $withCone (expected ≥1), without -c = $without (expected 0)");
}

function check_M3(): array {
    // dos casos: el .scr cambia con fecha más reciente que la caché, y el .scr cambia pero conserva una
    // fecha anterior a la caché (p. ej. restaurado de una copia con cp -p); en los dos hay que recalcular
    $mk = function (string $name): string {
        $ws = workspace($name, true);
        $d = "$ws/spain.tsk/radar_data.rbk";
        foreach (['barajas.rdb', 'barajas.scr'] as $f) { unlink("$d/$f"); copy(realpath($GLOBALS['ROOT'] . "/spain.tsk/radar_data.rbk/$f"), "$d/$f"); }
        return $ws;
    };
    $edit = function (string $ws, int $mtime): void {
        $f = "$ws/spain.tsk/radar_data.rbk/barajas.scr";
        file_put_contents($f, preg_replace('/^TowerHeight\s+\S+/m', 'TowerHeight 300', (string) file_get_contents($f), 1));
        touch($f, $mtime); clearstatcache();
    };
    $ws2 = $mk('M3-fresh'); $edit($ws2, time());
    berta($ws2, ['-r', 'barajas', '-s', '30', '-z', '-f']);
    $fresh = mono_kml($ws2, 'barajas', 30);
    $fallos = [];
    foreach (['newer' => +10, 'older' => -86400] as $caso => $delta) {
        $ws = $mk("M3-$caso");
        berta($ws, ['-r', 'barajas', '-s', '30', '-z']);
        $before = mono_kml($ws, 'barajas', 30);
        if ($fresh === null || $before === null) return res('ERROR', 'no KML produced for barajas FL30');
        if ($fresh === $before) return res('ERROR', 'test edit (tower height) did not change coverage');
        $edit($ws, time() + $delta);
        berta($ws, ['-r', 'barajas', '-s', '30', '-z']);
        if (mono_kml($ws, 'barajas', 30) !== $fresh) $fallos[] = $caso === 'newer' ? 'edited .scr with a newer date' : 'edited .scr keeping an older date than the cache';
    }
    return $fallos ? res('OPEN', 'BERTA served the old cached coverage after: ' . implode('; ', $fallos))
        : res('FIXED', 'after editing the .scr (newer or older date than the cache), a cached run recomputes the coverage');
}

function check_M4(): array {
    $v = code_const('BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD');
    if ($v === null) return res('FIXED', 'constant BERTA_INTERSECTION_TOLERANCE_LIMIT_RAD no longer defined');
    return str_contains($v, '_NM') && str_contains($v, 'RADIO_TERRESTRE') ? res('OPEN', "still defined as $v (NM divided by metres)") : res('MANUAL', "redefined as $v — check units");
}

function check_M5(): array {
    $ws = workspace('M5');
    $r = berta($ws, ['-r', 'paracuellos1', '-s', '400', '-m', '5', '-z', '-f']);
    if (crashed($r)) return res('OPEN', 'BERTA crashed with -m 5 at FL400');
    return preg_match('/\bNAN\b|NANrad/i', $r[2] . $r[1]) ? res('OPEN', 'NaN still appears in the log with -m 5 at FL400') : res('FIXED', 'no NaN with a range that cannot reach the level');
}

function check_M6a(): array {
    // cinco screenings defectuosos derivados de barajas.scr; cada uno debe terminar con error (no seguir en silencio)
    $ws = workspace('M6a', true);
    $d = "$ws/spain.tsk/radar_data.rbk";
    $orig = file("$d/barajas.scr", FILE_IGNORE_NEW_LINES);
    $az5 = null; foreach ($orig as $i => $l) if (str_starts_with($l, 'AZIMUTH 5')) { $az5 = $i; break; }
    if ($az5 === null) return res('ERROR', 'AZIMUTH 5 not found in barajas.scr');
    $casos = [
        'nopipe'    => fn($L) => array_replace($L, [$az5 + 4 => ' ( abc def )']),        // sin "|"
        'nonnum'    => fn($L) => array_replace($L, [$az5 + 4 => ' ( abc | def )']),      // valores no numéricos
        'badcount'  => fn($L) => array_replace($L, [$az5 + 1 => 'abc']),                 // número de obstáculos
        'dupaz'     => fn($L) => array_replace($L, [$az5 => 'AZIMUTH 4']),               // acimut repetido
        'truncated' => fn($L) => array_slice($L, 0, $az5 + 5),                           // fichero cortado
    ];
    $silent = []; $crash = [];
    foreach ($casos as $k => $f) {
        file_put_contents("$d/zbad$k.scr", implode("\n", $f($orig)) . "\n");
        rdb_variant("$d/barajas.rdb", "$d/zbad$k.rdb", ['radarIdentifier' => "zbad$k", 'screening' => "./spain.tsk/radar_data.rbk/zbad$k.scr"]);
        $r = berta($ws, ['-r', "zbad$k", '-s', '30', '-z', '-f'], 60);
        $msg = preg_match('/\sE>/', $r[2]) === 1;
        if ($r[0] === 0 && !$msg) $silent[] = $k;
        elseif (preg_match('/PHP Fatal|Uncaught/', $r[2]) || $r[0] === -2) $crash[] = $k . ($r[0] === -2 ? ' (hangs)' : '');
    }
    if (!$silent && !$crash) return res('FIXED', 'all 5 malformed screenings (no "|", non-numeric, bad count, repeated azimuth, truncated) stop with an E> message');
    $parts = [];
    if ($silent) $parts[] = 'accepted silently: ' . implode(', ', $silent);
    if ($crash) $parts[] = 'PHP crash instead of a message: ' . implode(', ', $crash);
    return res('OPEN', implode('; ', $parts));
}

function check_M6b(): array {
    $ws = workspace('M6b');
    $r = berta($ws, ['-r', 'paracuellos1', '-s', '100', '-m', '60', '-z']);
    if (preg_match('/forzado un alcance de\s*([\d.]+)\s*NM/i', $r[2], $m)) return abs((float) $m[1] - 60) < 0.01 ? res('FIXED', 'log shows 60NM') : res('OPEN', "log shows {$m[1]}NM for -m 60");
    return res('MANUAL', 'forced-range log line not found (message changed?)');
}

function check_L1(): array {
    // radar sintético: sierra de 2000 m a 18-22 NM y valle a 0 m después; FL a 1000 m por encima del radar (100 m)
    $ws = workspace('L1', true);
    $d = "$ws/spain.tsk/radar_data.rbk";
    $k = 1.33; $Rk = $k * R_TIERRA; $prof = [];
    for ($nm = 0.5; $nm <= 60; $nm += 0.5) { $alt = ($nm >= 18 && $nm <= 22) ? 2000.0 : 0.0; $prof[] = sprintf(' ( %.12E | %.1f )', $nm * NM / $Rk, $alt); }
    $txt = "Site LE_ZRIDGE\nk_factor 1.33\nMethod 136\nTowerHeight 20.0\nRange 250.0\nTerrainHeight 80.0\n360\n";
    for ($a = 0; $a < 360; $a++) $txt .= "AZIMUTH $a\n" . count($prof) . "\n" . implode("\n", $prof) . "\n";
    file_put_contents("$d/zridge.scr", $txt);
    rdb_variant("$d/barajas.rdb", "$d/zridge.rdb", ['radarIdentifier' => 'zridge', 'screening' => './spain.tsk/radar_data.rbk/zridge.scr', 'secondaryMaximumRange' => '60']);
    $r = berta($ws, ['-r', 'zridge', '-s', '33', '-z', '-f']); // FL33 ≈ 1006 m
    if (crashed($r)) return res('OPEN', 'BERTA crashed on a profile with several crossings above the radar');
    $kml = mono_kml($ws, 'zridge', 33); $polys = $kml ? kml_polygons($kml) : [];
    if (!$polys) return res('OPEN', 'no coverage produced for the ridge profile');
    $rad = ref_radar($ws, 'zridge');
    $p20 = ref_destino($rad['lat'], $rad['lon'], 20 * NM, deg2rad(45.3)); // dentro de la sierra: sin cobertura
    $p10 = ref_destino($rad['lat'], $rad['lon'], 10 * NM, deg2rad(45.3)); // antes de la sierra: cobertura
    $in20 = inside_polys($polys, $p20[0], $p20[1]); $in10 = inside_polys($polys, $p10[0], $p10[1]);
    if ($in10 && !$in20) return res('FIXED', 'terrain above the level (2000 m ridge at 20 NM) is excluded; 10 NM is covered');
    return res('OPEN', sprintf('ridge profile: 10 NM covered=%s (expected yes), 20 NM inside the ridge covered=%s (expected no)', $in10 ? 'yes' : 'no', $in20 ? 'yes' : 'no'));
}

/** Ejecuciones multiradar compartidas por X1, X2, X3 y O2. */
function multi_runs(): array {
    static $cache = null; if ($cache !== null) return $cache;
    $A = ['barajas paracuellos1 valladolid']; $B = ['barajas paracuellos1 alcolea'];
    $ws = workspace('multi');
    $ra = berta($ws, ['-2', '-r', $A[0], '-s', '100', '-z']); $kA = multi_kml($ws, 100);
    $rb = berta($ws, ['-2', '-r', $B[0], '-s', '100', '-z']); $kBwarm = multi_kml($ws, 100);
    $outs = array_filter(files_under("$ws/RESULTADOS", '/\.km[lz]$/i'), fn($f) => is_multi_path($f));
    $rm = berta($ws, ['-2', '-r', $A[0], '-s', '100', '-z', '-m', '60']); $kAm60warm = multi_kml($ws, 100);
    $ws2 = workspace('multi-freshB'); $rb2 = berta($ws2, ['-2', '-r', $B[0], '-s', '100', '-z']); $kBfresh = multi_kml($ws2, 100);
    $ws3 = workspace('multi-freshA60'); $rm3 = berta($ws3, ['-2', '-r', $A[0], '-s', '100', '-z', '-m', '60']); $kAm60fresh = multi_kml($ws3, 100);
    $crash = crashed($ra) || crashed($rb) || crashed($rm) || crashed($rb2) || crashed($rm3);
    return $cache = compact('kA', 'kBwarm', 'kBfresh', 'kAm60warm', 'kAm60fresh', 'outs', 'crash');
}
function union_folder(?string $k): ?string { return $k === null ? null : folder_body($k, 'multiradar-union'); }

function check_X1(): array {
    $m = multi_runs(); if ($m['crash']) return res('ERROR', 'a multi-radar run crashed');
    $w = union_folder($m['kBwarm']); $f = union_folder($m['kBfresh']);
    if ($w === null || $f === null) return res('MANUAL', 'multiradar-union folder not found in the output');
    return $w === $f ? res('FIXED', 'warm cache from another radar set gives the same result as a fresh run')
        : res('OPEN', 'radar set B after set A (warm cache) differs from a fresh run of set B');
}
function check_X2(): array {
    $m = multi_runs(); if ($m['crash']) return res('ERROR', 'a multi-radar run crashed');
    $w = $m['kAm60warm']; $f = $m['kAm60fresh'];
    if ($w === null || $f === null) return res('ERROR', 'no multi output for -m 60');
    $strip = fn($k) => preg_replace('#<Folder>\s*<name>monoradar</name>.*?</Folder>#s', '', $k);
    return $strip($w) === $strip($f) ? res('FIXED', '-m 60 with a warm cache matches a fresh -m 60 run')
        : res('OPEN', '-m 60 after a full-range run (warm cache) differs from a fresh -m 60 run');
}
function check_X3(): array {
    $m = multi_runs(); if ($m['kA'] === null) return res('ERROR', 'no multi output');
    return str_contains($m['kA'], '<name>unica</name>') ? res('FIXED', '"unica" folder present in -2 output') : res('OPEN', '"unica" folder missing from -2 output');
}
function check_O2(): array {
    $m = multi_runs();
    $n = count(array_unique(array_map('basename', $m['outs'])));
    return $n >= 2 ? res('FIXED', "two radar sets at FL100 produced $n distinct multi output files")
        : res('OPEN', 'two different radar sets at FL100 wrote the same multi output file');
}

function check_X4(): array {
    $ws = workspace('X4', true);
    $d = "$ws/spain.tsk/radar_data.rbk";
    rdb_variant("$d/barajas.rdb", "$d/zshort.rdb", ['radarIdentifier' => 'zshort', 'secondaryMaximumRange' => '1']);
    $r = berta($ws, ['-2', '-r', 'barajas paracuellos1 zshort', '-s', '300', '-z']);
    if (crashed($r)) return res('OPEN', 'multi-radar run crashes when one radar has no coverage (' . (preg_match('/Uncaught (\w+)/', $r[2], $mm) ? $mm[1] : 'exit ' . $r[0]) . ')');
    return multi_kml($ws, 300) !== null ? res('FIXED', 'radar without coverage is skipped; multi output produced') : res('OPEN', 'no multi output produced');
}

function check_X5(): array {
    // radar sintético "barajasnorte" (datos de valladolid): su nombre contiene "barajas"
    $ws = workspace('X5', true);
    $d = "$ws/spain.tsk/radar_data.rbk";
    rdb_variant("$d/valladolid.rdb", "$d/barajasnorte.rdb", ['radarIdentifier' => 'barajasnorte']);
    $r = berta($ws, ['-2', '-r', 'barajas barajasnorte paracuellos1', '-s', '100', '-z']);
    if (crashed($r)) return res('ERROR', 'multi run crashed');
    $k = multi_kml($ws, 100); $inv = $k ? folder_body($k, 'multiradar-inversa') : null;
    if ($inv === null) return res('MANUAL', 'multiradar-inversa folder not found');
    $body = folder_body($inv, 'barajas');
    if ($body === null) return res('MANUAL', 'no barajas folder in multiradar-inversa');
    preg_match_all('#<Placemark>\s*<name>(.*?)</name>#s', $body, $pm); $wrong = [];
    foreach ($pm[1] as $name) { $grp = explode(',', preg_replace('/-\d{3}$/', '', $name)); if (!in_array('barajas', $grp, true)) $wrong[] = $name; }
    if (!$pm[1]) return res('MANUAL', 'barajas folder is empty');
    return $wrong ? res('OPEN', 'barajas folder lists groups without barajas: ' . implode('; ', array_slice($wrong, 0, 3)))
        : res('FIXED', 'barajas folder only lists groups that contain barajas (tested with a radar named barajasnorte)');
}

function check_X6(): array {
    $ws = workspace('X6');
    $r = berta($ws, ['-2', '-r', 'barajas paracuellos1 valladolid alcolea canchoblanco erillas monflorite valdespina paracuellos2', '-s', '150', '-z', '-f']);
    if (crashed($r)) return res('ERROR', '9-radar run crashed');
    return $r[3] < 30 ? res('FIXED', sprintf('9 radars at FL150 in %.1f s (before: about 70 s)', $r[3]))
        : res('OPEN', sprintf('9 radars at FL150 took %.1f s (target < 30 s; patched code ≈ 11 s)', $r[3]));
}

function check_O1(): array {
    if (!class_exists('ZipArchive')) return res('SKIPPED', 'php-zip not installed: KMZ output cannot be exercised here');
    $ws = workspace('O1');
    berta($ws, ['-r', 'barajas', '-s', '30,31,1', '-f']);
    $k = files_under("$ws/RESULTADOS", '/\.kmz$/i'); $names = array_unique(array_map('basename', $k));
    $withFl = array_filter($names, fn($n) => preg_match('/03[01]/', $n));
    return count($withFl) >= 2 ? res('FIXED', 'one KMZ per flight level: ' . implode(', ', array_slice($names, 0, 4)))
        : res('OPEN', 'KMZ files: ' . (implode(', ', $names) ?: 'none') . ' (expected one per level with the FL in the name)');
}

function check_O3(): array {
    $ws = workspace('O3');
    $scr = "$ws/zv2.scr";
    $txt = "Site LE_ZTESTV2\nVersion 2\nLongitude -3.5\nLatitude 40.5\nCentralAngle k_factor\nHeightReference EGM96\nk_factor 1.333333\nTowerHeight 20.0\nRange 250.0\nTerrainHeight 700.0\n2\n";
    for ($a = 0; $a < 2; $a++) $txt .= "AZIMUTH $a\n2\n ( 1.0E-4 | 700.0 )\n ( 2.0E-4 | 705.0 )\n";
    file_put_contents($scr, $txt);
    $before = glob("$ws/*.rdb") ?: [];
    $r = berta($ws, ['--screening-file', $scr]);
    $new = array_diff(glob("$ws/*.rdb") ?: [], $before);
    if ($r[0] === 0 && $new) return res('FIXED', '--screening-file generated ' . basename((string) reset($new)));
    $r2 = berta($ws, ['--screening-file=' . $scr]); $new2 = array_diff(glob("$ws/*.rdb") ?: [], $before);
    if ($r2[0] === 0 && $new2) return res('FIXED', '--screening-file=FILE generated ' . basename((string) reset($new2)));
    return res('OPEN', '--screening-file did not generate an .rdb (exit ' . $r[0] . ')');
}

function check_O4(): array {
    $ws = workspace('O4');
    $help = berta($ws, ['-h']); $bad = [];
    foreach (['--rascal', '--no-rascal', '-p'] as $o) {
        if (!preg_match('/(^|\s)' . preg_quote($o, '/') . '(\s|$)/m', $help[1] . $help[2])) continue;
        $r = berta($ws, [$o, '-r', 'barajas', '-s', '30', '-z']);
        if ($r[0] !== 0 || stripos($r[2] . $r[1], 'no esperado') !== false) $bad[] = $o;
    }
    return $bad ? res('OPEN', 'listed in help but rejected: ' . implode(', ', $bad)) : res('FIXED', 'every option in the help is accepted (or removed from the help)');
}

function check_O5(): array {
    global $ROOT;
    // grafo de llamadas: funciones definidas, llamadas desde cada función y desde el código de nivel superior
    $defined = []; $edges = []; $top = [];
    foreach (glob("$ROOT/*.php") as $f) {
        $tok = token_get_all((string) file_get_contents($f)); $n = count($tok);
        $depth = 0; $fn = null; $fnDepth = -1; $classDepth = -1; $pendingFn = null; $pendingClass = false;
        for ($i = 0; $i < $n; $i++) {
            $t = $tok[$i];
            if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                if ($pendingClass) { $classDepth = $depth; $pendingClass = false; }
                if ($pendingFn !== null) { $fn = $pendingFn; $fnDepth = $depth; $pendingFn = null; }
                continue;
            }
            if ($t === '}') { if ($depth === $fnDepth) { $fn = null; $fnDepth = -1; } if ($depth === $classDepth) $classDepth = -1; $depth--; continue; }
            if (!is_array($t)) continue;
            if ($t[0] === T_CLASS) { $pendingClass = true; continue; }
            $src = $fn ?? '#top';
            if ($t[0] === T_CONSTANT_ENCAPSED_STRING) { $name = strtolower(trim($t[1], '\'"')); if (preg_match('/^\w+$/', $name)) $edges[$src][$name] = true; continue; }
            if ($t[0] !== T_STRING) continue;
            $j = $i - 1; while ($j >= 0 && is_array($tok[$j]) && $tok[$j][0] === T_WHITESPACE) $j--;
            $k = $i + 1; while ($k < $n && is_array($tok[$k]) && $tok[$k][0] === T_WHITESPACE) $k++;
            $name = strtolower($t[1]);
            if ($j >= 0 && is_array($tok[$j]) && $tok[$j][0] === T_FUNCTION) {
                if ($classDepth < 0) { $defined[$name] = basename($f); $pendingFn = $name; } else { $pendingFn = '#method'; }
            } elseif ($k < $n && $tok[$k] === '(') {
                $edges[$src][$name] = true;
            }
        }
    }
    $reach = []; $queue = array_keys($edges['#top'] ?? []); $queue = array_merge($queue, array_keys($edges['#method'] ?? []));
    while ($queue) { $x = array_pop($queue); if (isset($reach[$x])) continue; $reach[$x] = true; foreach (array_keys($edges[$x] ?? []) as $y) if (!isset($reach[$y])) $queue[] = $y; }
    $dead = array_keys(array_diff_key($defined, $reach)); sort($dead);
    $eta = (bool) preg_match('/require(_once)?\s*\(?\s*[\'"]EtaEstimator\.php/', (string) file_get_contents("$ROOT/main.php"));
    $etaUsed = false; foreach (glob("$ROOT/*.php") as $f) if (basename($f) !== 'EtaEstimator.php' && preg_match('/new\s+EtaEstimator/', (string) file_get_contents($f))) $etaUsed = true;
    $parts = [];
    if ($dead) $parts[] = count($dead) . ' functions unreachable from the program: ' . implode(', ', array_slice($dead, 0, 14)) . (count($dead) > 14 ? '…' : '');
    if ($eta && !$etaUsed) $parts[] = 'EtaEstimator.php loaded but unused';
    return $parts ? res('OPEN', implode('; ', $parts)) : res('FIXED', 'no unreachable functions found');
}

function check_O6(): array {
    global $ROOT; $missing = [];
    foreach (glob("$ROOT/spain.tsk/radar_data.rbk/*.rdb") as $f) {
        if (preg_match('/^screening: "(.*)"/m', (string) file_get_contents($f), $m) && !is_file("$ROOT/" . preg_replace('#^\./#', '', $m[1]))) $missing[] = basename($f) . ' → ' . basename($m[1]);
    }
    return $missing ? res('OPEN', 'missing screening files: ' . implode(', ', $missing)) : res('FIXED', 'every .rdb points to an existing screening file');
}

function check_O7(): array {
    $ws = workspace('O7', true);
    $d = "$ws/spain.tsk/radar_data.rbk";
    rdb_variant("$d/barajas.rdb", "$d/zshort.rdb", ['radarIdentifier' => 'zshort', 'secondaryMaximumRange' => '1']);
    $bad = [];
    // por encima del radar: alcance de 1 NM a FL300
    $r = berta($ws, ['-r', 'zshort', '-s', '300', '-z', '-f']);
    if (crashed($r)) $bad[] = 'crash above the radar (zshort FL300)';
    elseif (mono_kml($ws, 'zshort', 300) !== null) $bad[] = 'KML written above the radar (zshort FL300)';
    // por debajo del radar: barajas a FL5 no tiene cobertura
    $r = berta($ws, ['-r', 'barajas', '-s', '5', '-z', '-f']);
    if (crashed($r)) $bad[] = 'crash below the radar (barajas FL5)';
    elseif (mono_kml($ws, 'barajas', 5) !== null) $bad[] = 'KML written below the radar (barajas FL5)';
    // caché antigua con un anillo vacío
    rmtree("$ws/RESULTADOS");
    @mkdir("$ws/cache/zshort", 0775, true);
    file_put_contents("$ws/cache/zshort/zshort-FL300.json", '[[]]'); touch("$ws/cache/zshort/zshort-FL300.json", time() + 60); clearstatcache();
    $r = berta($ws, ['-r', 'zshort', '-s', '300', '-z']);
    if (crashed($r)) $bad[] = 'crash with an old [[]] cache';
    elseif (mono_kml($ws, 'zshort', 300) !== null) $bad[] = 'KML written from an old [[]] cache';
    return $bad ? res('OPEN', implode('; ', $bad)) : res('FIXED', 'no KML for levels without coverage (above, below, old [[]] cache)');
}

function check_E3(): array {
    $ws = workspace('E3'); $bad = 0; $n = 0; $parts = [];
    foreach ([['paracuellos1', 22], ['paracuellos1', 23], ['barajas', 19], ['canchoblanco', 20], ['valdespina', 25]] as $i => [$s, $fl]) {
        $m = mismatch_below($ws, $s, $fl, 5000, 100 + $i);
        if (isset($m['error'])) return res('ERROR', $m['error']);
        $bad += $m['bad']; $n += $m['n']; $parts[] = "$s FL$fl {$m['bad']}";
    }
    $pct = 100 * $bad / $n;
    return $bad <= 5 ? res('FIXED', sprintf('%d of %d sampled points wrong (%.3f%%) below the radar', $bad, $n, $pct))
        : res('OPEN', sprintf('%d of %d sampled points wrong (%.3f%%; target ≤ 5) — %s', $bad, $n, $pct, implode(', ', $parts)));
}

function check_E4(): array {
    $v = code_const('BERTA_PERFIL_ESCALONADO');
    if ($v === null) return res('OPEN', 'flag BERTA_PERFIL_ESCALONADO not defined');
    if (!const_used('BERTA_PERFIL_ESCALONADO')) return res('OPEN', "flag defined ($v) but no code reads it yet (needs Change 6B)");
    return res('MANUAL', "flag implemented, currently $v — turn on/off after Q1 (E3 test honours the flag)");
}

function check_E5(): array {
    $v = code_const('BERTA_FEET_TO_METERS');
    if ($v === null) return res('MANUAL', 'BERTA_FEET_TO_METERS not found');
    return abs((float) $v - 0.3048) < 1e-12 ? res('FIXED', 'BERTA_FEET_TO_METERS = 0.3048') : res('OPEN', "BERTA_FEET_TO_METERS = $v");
}

function check_E6(): array {
    global $ROOT; $hack = [];
    foreach (glob("$ROOT/*.php") as $f) foreach (file($f) as $i => $l) if (preg_match('/\$W\s*\+=\s*0\.01\b/', $l)) $hack[] = basename($f) . ':' . ($i + 1);
    $ws = workspace('E6'); $bad = 0; $n = 0;
    foreach ([['valdespina', 25], ['canchoblanco', 25], ['erillas', 25]] as $i => [$s, $fl]) {
        $flm = $fl * 100 * PIE; $rad = ref_radar($ws, $s); if ($flm >= $rad['hA']) continue;
        $m = mismatch_below($ws, $s, $fl, 3000, 200 + $i); if (isset($m['error'])) return res('ERROR', $m['error']);
        $bad += $m['bad']; $n += $m['n'];
    }
    if ($hack) return res('OPEN', 'W += 0.01 workaround still present (' . implode(', ', $hack) . ')' . ($n ? ", exact-level accuracy $bad/$n wrong" : ''));
    return $bad <= 3 ? res('FIXED', "no 1 cm workaround; levels where terrain sits exactly on the circle: $bad of $n points wrong")
        : res('OPEN', "workaround removed but $bad of $n points wrong at levels exactly on terrain points");
}

function check_E7(): array {
    return res('MANUAL', 'no observable effect up to FL400 (both points stay above the level); verify in code review');
}

function check_E8(): array {
    // Q3 (respondida): ConeOfSilence es el semiángulo medido desde la vertical del radar; 0º = sin cono.
    // radio = (altura sobre el radar) · tan(ángulo)
    $ws = workspace('E8', true);
    $d = "$ws/spain.tsk/radar_data.rbk";
    rdb_variant("$d/paracuellos1.rdb", "$d/zcone.rdb", ['radarIdentifier' => 'zcone', 'secondaryConeOfSilence' => '30.000000']);
    rdb_variant("$d/paracuellos1.rdb", "$d/zcone0.rdb", ['radarIdentifier' => 'zcone0', 'secondaryConeOfSilence' => '0.000000']);
    $r = berta($ws, ['-r', 'zcone', '-s', '150', '-z', '-f', '-c']);
    if (crashed($r)) return res('OPEN', 'BERTA crashed with -c (30° cone)');
    $kml = (string) mono_kml($ws, 'zcone', 150); $rad = ref_radar($ws, 'zcone');
    if (!preg_match('#<innerBoundaryIs>.*?<coordinates>(.*?)</coordinates>#s', $kml, $m)) return res('OPEN', 'no cone hole in the output for a 30° cone');
    $c = [rad2deg($rad['lat']), rad2deg($rad['lon'])]; $ds = [];
    foreach (preg_split('/\s+/', trim($m[1])) as $t) { $v = explode(',', $t); if (count($v) >= 2) $ds[] = hav($c, [(float) $v[1], (float) $v[0]]); }
    $rr = array_sum($ds) / count($ds); $h = 150 * 100 * PIE - $rad['hA'];
    $cand = ['half-angle from vertical (h·tan)' => $h * tan(deg2rad(30)), 'elevation angle (h/tan)' => $h / tan(deg2rad(30)), 'fixed 45°' => $h];
    $best = ''; $err = INF; foreach ($cand as $k => $v) if (abs($rr - $v) / $v < $err) { $err = abs($rr - $v) / $v; $best = $k; }
    if ($err > 0.03) return res('OPEN', sprintf('30° cone radius %.0f m matches no model (expected %.0f m = h·tan30°)', $rr, $h * tan(deg2rad(30))));
    if ($best !== 'half-angle from vertical (h·tan)') return res('OPEN', sprintf('30° cone radius %.0f m follows "%s"; expected %.0f m (half-angle from vertical)', $rr, $best, $h * tan(deg2rad(30))));

    // 0º = sin cono: no debe fallar ni dejar un agujero
    $r0 = berta($ws, ['-r', 'zcone0', '-s', '150', '-z', '-f', '-c']);
    if (crashed($r0)) return res('OPEN', sprintf('30° cone correct (%.0f m), but a 0° cone (= no cone) crashes BERTA', $rr));
    $k0 = (string) mono_kml($ws, 'zcone0', 150);
    if (!kml_polygons($k0)) return res('OPEN', sprintf('30° cone correct (%.0f m), but a 0° cone removes the whole coverage', $rr));
    if (str_contains($k0, '<innerBoundaryIs>')) return res('OPEN', sprintf('30° cone correct (%.0f m), but a 0° cone (= no cone) still leaves a hole', $rr));
    return res('FIXED', sprintf('30° cone radius %.0f m = h·tan30° (half-angle from vertical); 0° gives no cone', $rr));
}

function check_P1(): array {
    $ws = workspace('P1');
    $r = berta($ws, ['-r', 'paracuellos1', '-s', '100', '-z', '-f']);
    if (crashed($r)) return res('ERROR', 'BERTA crashed');
    $polys = kml_polygons((string) mono_kml($ws, 'paracuellos1', 100));
    if (count($polys) !== 1) return res('MANUAL', 'above-radar output is not a single ring; use E3 instead');
    $rad = ref_radar($ws, 'paracuellos1'); $cr = ref_cruces($rad, 100 * 100 * PIE, flag('BERTA_PERFIL_ESCALONADO'));
    $N = count($cr); $ref = [];
    foreach ($cr as $j => $l) if (count($l) == 1) $ref[] = ref_destino($rad['lat'], $rad['lon'], $l[0] * NM, deg2rad($j * 360.0 / $N));
    $max = 0.0; $ring = $polys[0][0];
    foreach ($ring as $v) { $best = INF; foreach ($ref as $q) { $dd = hav($v, $q); if ($dd < $best) $best = $dd; } $max = max($max, $best); }
    return $max < 1.0 ? res('FIXED', sprintf('above-radar vertices within %.2f m of the exact position', $max))
        : res('OPEN', sprintf('above-radar vertices up to %.0f m from the exact position', $max));
}

function check_P2(): array {
    $v = code_const('BERTA_ELIPSOIDE_WGS84');
    if ($v === null) return res('OPEN', 'flag BERTA_ELIPSOIDE_WGS84 not defined');
    if (!const_used('BERTA_ELIPSOIDE_WGS84')) return res('OPEN', "flag defined ($v) but no code reads it yet (needs Change 6E)");
    return res('MANUAL', "flag implemented, currently $v — decide after Q2 (E3/P1 tests honour the flag)");
}

function check_S2(): array {
    $ws = workspace('S2');
    $r = berta($ws, ['-r', 'paracuellos1', '-s', '1,400,1', '-z', '-f']);
    if (crashed($r)) return res('ERROR', 'sweep crashed');
    rmtree("$ws/RESULTADOS");
    return $r[3] < 12 ? res('FIXED', sprintf('paracuellos1 FL1-400 in %.1f s including KML (before: about 22 s)', $r[3]))
        : res('OPEN', sprintf('paracuellos1 FL1-400 took %.1f s including KML (target < 12 s)', $r[3]));
}

function check_Q(string $id): array {
    global $DECISIONS_FILE;
    $dec = is_file($DECISIONS_FILE) ? (json_decode((string) file_get_contents($DECISIONS_FILE), true) ?: []) : [];
    return isset($dec[$id]) && trim((string) $dec[$id]) !== '' ? res('FIXED', 'decision recorded: ' . $dec[$id]) : res('MANUAL', "record the answer in tests/fix-decisions.json as \"$id\"");
}

// ---------------------------------------------------------------- ejecución
@mkdir($TMP, 0775, true);
$results = []; $t0 = microtime(true);
foreach ($ITEMS as [$id, $group, $title, $where, $fn, $slow]) {
    if ($ONLY !== null && !in_array(strtoupper($id), $ONLY, true)) continue;
    if ($QUICK && $slow) { $results[$id] = ['SKIPPED', 'slow test skipped (--quick)', $group, $title, $where]; continue; }
    $t = microtime(true);
    fwrite(STDERR, sprintf("%-4s %s ... ", $id, $title));
    try { [$st, $detail] = $fn === 'check_Q' ? check_Q($id) : $fn(); }
    catch (Throwable $e) { $st = 'ERROR'; $detail = get_class($e) . ': ' . $e->getMessage(); }
    fwrite(STDERR, sprintf("%s (%.1fs)\n", $st, microtime(true) - $t));
    $results[$id] = [$st, $detail, $group, $title, $where];
}
if (!isset($opts['keep'])) rmtree($TMP);

$count = array_count_values(array_column($results, 0));
$open = array_keys(array_filter($results, fn($r) => in_array($r[0], ['OPEN', 'ERROR', 'BLOCKED'], true)));
$manual = array_keys(array_filter($results, fn($r) => $r[0] === 'MANUAL'));

echo "\n";
foreach ($results as $id => [$st, $detail, $group, $title]) printf("%-4s %-8s %s\n     %s\n", $id, $st, $title, $detail);
echo "\nSummary: " . implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($count), $count)) . sprintf(" (%.0f s)\n", microtime(true) - $t0);
echo $open ? 'Remaining: ' . implode(', ', $open) . "\n" : "All automated items fixed.\n";
if ($manual) echo 'Needs a human decision/review: ' . implode(', ', $manual) . "\n";

if (!isset($opts['no-write']) && $ONLY === null) {
    $commit = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD 2>/dev/null'));
    $dirty = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' status --porcelain -- ":(glob)*.php" 2>/dev/null')) !== '' ? ' + uncommitted changes' : '';
    $md = "# BERTA review items — status\n\nGenerated by `php tests/verify_fixes.php` on " . date('Y-m-d H:i') . " for `" . ($commit ?: $ROOT) . "`$dirty" . ($QUICK ? ' (quick run)' : '') . ".\n\n";
    $md .= "Summary: " . implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($count), $count)) . ".\n\n";
    $md .= $open ? "**Remaining:** " . implode(', ', $open) . "\n\n" : "**All automated items fixed.**\n\n";
    $row = fn($id, $r) => "| $id | {$r[0]} | " . str_replace('|', '\\|', $r[3]) . " | {$r[4]} | " . str_replace('|', '\\|', $r[1]) . " |\n";
    $hdr = "| Item | Status | Problem | Proposed fix | Result |\n| --- | --- | --- | --- | --- |\n";
    $pend = array_filter($results, fn($r) => $r[0] !== 'FIXED'); $done = array_filter($results, fn($r) => $r[0] === 'FIXED');
    $md .= "## Pending (" . count($pend) . ")\n\nOPEN = still to fix · MANUAL = needs a decision or review · BLOCKED = depends on another item · SKIPPED = cannot be tested here.\n\n";
    $md .= $pend ? $hdr . implode('', array_map($row, array_keys($pend), $pend)) : "Nothing pending.\n";
    $md .= "\n## Resolved (" . count($done) . ")\n\n";
    $md .= $done ? $hdr . implode('', array_map($row, array_keys($done), $done)) : "None yet.\n";
    $md .= "\nAliases from the maths review: " . implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($ALIASES), $ALIASES)) . ". P3 needs no change.\n";
    $md .= "Details of every item and fix: `docs/review-and-proposed-fixes.html`.\n";
    file_put_contents($STATUS_FILE, $md);
    echo "Status written to tests/fix-status.md\n";
}
exit($open ? 1 : 0);
