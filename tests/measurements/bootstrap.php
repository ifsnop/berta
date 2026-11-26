<?php
/**
 * Arranque común de los scripts de medición.
 *
 * Carga el código de BERTA de un directorio (por defecto, la raíz del repositorio; otro con
 * BERTA_WORK=/ruta), con las constantes tal y como están definidas en su main.php, y la función
 * calculosFL() sin ejecutar programaPrincipal(). Añade utilidades geodésicas de referencia.
 *
 * Los scripts se ejecutan desde la raíz del repositorio, p. ej.:
 *   php tests/measurements/vertex_accuracy.php
 *   BERTA_WORK=/otra/copia php tests/measurements/crossing_parity.php paracuellos1
 * El registro de BERTA va a STDERR; añade 2>/dev/null para ver sólo los resultados.
 */
declare(strict_types=1);
ini_set('memory_limit', '5G');

$BERTA_WORK = getenv('BERTA_WORK') ?: dirname(__DIR__, 2);
if (!is_file("$BERTA_WORK/main.php")) { fwrite(STDERR, "No encuentro main.php en $BERTA_WORK\n"); exit(2); }
if (!is_file("$BERTA_WORK/vendor/autoload.php")) { fwrite(STDERR, "Falta $BERTA_WORK/vendor: ejecuta 'composer install'\n"); exit(2); }
chdir($BERTA_WORK);

// constantes BERTA_* tal y como estén en main.php (el código probado decide sus valores)
$mainSrc = (string) file_get_contents("$BERTA_WORK/main.php");
preg_match_all('/^\s*const\s+BERTA_[A-Z_0-9]+\s*=.*?;/mi', $mainSrc, $m);
eval(implode("\n", $m[0]));
foreach (['BERTA_RADIO_TERRESTRE' => 6371000.0, 'BERTA_MILLA_NAUTICA_EN_METROS' => 1852.0, 'BERTA_MAX_WALL_HEIGHT' => 32714.4,
          'BERTA_FEET_TO_METERS' => 0.3048, 'BERTA_RAMER_DOUGLAS_PEUCKER_PRECISION' => 0.000001] as $k => $v) {
    if (!defined($k)) define($k, $v);
}

foreach (glob("$BERTA_WORK/inc.*.php") as $f) require_once $f;
require_once "$BERTA_WORK/vendor/autoload.php";
// calculosFL() vive en main.php, después de programaPrincipal(); se carga sin ejecutar el programa
eval(substr($mainSrc, strpos($mainSrc, 'function calculosFL')));

/** Radar listo para calcular, como lo prepara main.php (alcance en NM; PSR con su alcance primario). */
function loadRadar(string $name, float $maxRange = -1.0): array
{
    static $info = null;
    if ($info === null) $info = getRadars('spain.tsk/', true);
    if (!isset($info[$name])) { fwrite(STDERR, "Radar desconocido: $name\n"); exit(2); }
    $t = cargarDatosTerreno($info[$name], $maxRange);
    if (false !== strpos($name, '-psr') && $maxRange <= 0) {
        $t['range'] = (float) $info[$name]['primaryMaximumRange'];
        $t['screening']['range'] = $t['range'];
    }
    return $t;
}

/** Problema directo exacto sobre la esfera de radio BERTA_RADIO_TERRESTRE. Devuelve [lat, lon] en grados. */
function dest(float $latd, float $lond, float $dist_m, float $az_rad): array
{
    $d = $dist_m / BERTA_RADIO_TERRESTRE; $p1 = deg2rad($latd); $l1 = deg2rad($lond);
    $p2 = asin(sin($p1) * cos($d) + cos($p1) * sin($d) * cos($az_rad));
    $l2 = $l1 + atan2(sin($az_rad) * sin($d) * cos($p1), cos($d) - sin($p1) * sin($p2));
    return [rad2deg($p2), rad2deg($l2)];
}

/** Distancia de círculo máximo (haversine) en metros entre dos puntos [lat, lon] en grados. */
function hav(array $a, array $b): float
{
    $p1 = deg2rad($a[0]); $p2 = deg2rad($b[0]); $dp = $p2 - $p1; $dl = deg2rad($b[1] - $a[1]);
    $h = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return 2 * BERTA_RADIO_TERRESTRE * asin(min(1, sqrt($h)));
}

/** Problema directo de Vincenty sobre WGS84 (referencia). Latitud/longitud en grados, rumbo en radianes. */
function vincenty(float $lat1d, float $lon1d, float $s, float $az1): array
{
    $a = 6378137.0; $f = 1 / 298.257223563; $b = $a * (1 - $f);
    $U1 = atan((1 - $f) * tan(deg2rad($lat1d))); $s1 = atan2(tan($U1), cos($az1)); $sa = cos($U1) * sin($az1); $c2a = 1 - $sa * $sa;
    $u2 = $c2a * ($a * $a - $b * $b) / ($b * $b);
    $A = 1 + $u2 / 16384 * (4096 + $u2 * (-768 + $u2 * (320 - 175 * $u2))); $B = $u2 / 1024 * (256 + $u2 * (-128 + $u2 * (74 - 47 * $u2)));
    $sig = $s / ($b * $A);
    for ($i = 0; $i < 100; $i++) {
        $c2m = cos(2 * $s1 + $sig);
        $ds = $B * sin($sig) * ($c2m + $B / 4 * (cos($sig) * (-1 + 2 * $c2m * $c2m) - $B / 6 * $c2m * (-3 + 4 * sin($sig) ** 2) * (-3 + 4 * $c2m * $c2m)));
        $old = $sig; $sig = $s / ($b * $A) + $ds; if (abs($sig - $old) < 1e-12) break;
    }
    $tmp = sin($U1) * sin($sig) - cos($U1) * cos($sig) * cos($az1);
    $lat2 = atan2(sin($U1) * cos($sig) + cos($U1) * sin($sig) * cos($az1), (1 - $f) * sqrt($sa * $sa + $tmp * $tmp));
    $lam = atan2(sin($sig) * sin($az1), cos($U1) * cos($sig) - sin($U1) * sin($sig) * cos($az1)); $C = $f / 16 * $c2a * (4 + $f * (4 - 3 * $c2a));
    $L = $lam - (1 - $C) * $f * $sa * ($sig + $C * sin($sig) * (cos(2 * $s1 + $sig) + $C * cos($sig) * (-1 + 2 * cos(2 * $s1 + $sig) ** 2)));
    return [rad2deg($lat2), $lon1d + rad2deg($L)];
}

/** Distancia métrica local sobre el elipsoide WGS84 (válida para separaciones pequeñas), en metros. */
function ellDist(array $p, array $q): float
{
    $a = 6378137.0; $e2 = 0.00669437999014; $lat = deg2rad(($p[0] + $q[0]) / 2); $w = sqrt(1 - $e2 * sin($lat) ** 2);
    $M = $a * (1 - $e2) / $w ** 3; $N = $a / $w;
    return hypot(deg2rad($q[0] - $p[0]) * $M, deg2rad($q[1] - $p[1]) * $N * cos($lat));
}

/** Radio de curvatura de Euler del elipsoide WGS84 en la dirección $az (radianes) a la latitud $latd. */
function eulerR(float $latd, float $az): float
{
    $a = 6378137.0; $e2 = 0.00669437999014; $w = sqrt(1 - $e2 * sin(deg2rad($latd)) ** 2);
    $M = $a * (1 - $e2) / $w ** 3; $N = $a / $w;
    return 1 / (cos($az) ** 2 / $M + sin($az) ** 2 / $N);
}

/** Área en km² de una lista de anillos (exteriores menos agujeros), proyección equirrectangular local. */
function areaKm2(array $contours, ?float $lat0 = null): float
{
    $a = 0;
    foreach (KML_normalizePolygonsForKML($contours) as $poly) {
        foreach ($poly as $i => $ring) {
            $k = cos(deg2rad($lat0 ?? $ring[0][0])); $s = 0; $n = count($ring);
            for ($j = 0; $j < $n - 1; $j++) $s += ($ring[$j][1] * $k) * $ring[$j + 1][0] - ($ring[$j + 1][1] * $k) * $ring[$j][0];
            $a += ($i == 0 ? 1 : -1) * abs($s / 2);
        }
    }
    return $a * (111.195 ** 2);
}

/** ¿Existe en el código probado la función (o constante) que necesita un script? Si no, explica y sale. */
function requires(string $what, string $why): void
{
    $ok = str_starts_with($what, 'BERTA_') ? defined($what) : function_exists($what);
    if (!$ok) { echo "SKIPPED: this measurement needs $what ($why), which this code no longer/does not yet have.\n"; exit(0); }
}
