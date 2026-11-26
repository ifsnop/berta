<?php
/**
 * Implementación de referencia (prototipos) de los arreglos de la revisión matemática.
 *
 * Son las funciones con las que se midió todo lo que aparece en docs/review-and-proposed-fixes.html,
 * escritas de forma independiente al código de BERTA para poder compararlo con ellas. El código
 * propuesto para BERTA (Cambios 6-8, tests/prototype/proposed-fixes.patch) se derivó de aquí y se
 * comprobó contra estas funciones (tests/measurements/patched_vs_prototype.php).
 *
 *   fx_anguloMaximaCobertura   ángulo de máxima cobertura con acos limitado (E2)
 *   fx_perfil                  perfil cartesiano por acimut, independiente del FL; con $staircase el suelo
 *                              sigue la línea de visión más alta (E4)
 *   fx_obstaculos              recorte al alcance + muro final, con el punto tope sobre la recta (E7)
 *   fx_intersecciones          cruces robustos: un signo por vértice, sin W += 0.01 (E6)
 *   fx_destino                 problema directo exacto sobre la esfera (P1)
 *   fx_bandas / fx_intervalos  contorno trazado desde los intervalos por acimut, sin unión de polígonos (E3, S1)
 *   fx_multi_refine            multiradar por refinamiento incremental (S3)
 *   fx_prep_segmentos / fx_intersecciones_rapido   descarte rápido de segmentos (S2)
 *
 * Requiere las constantes BERTA_* y el autoload de Martinez-Rueda (tests/measurements/bootstrap.php).
 */
declare(strict_types=1);
// ---- Proposed fixes (prefixed fx_) ----

/** Max coverage angle, clamped (no NaN). Range in NM. */
function fx_anguloMaximaCobertura(array $radar, float $flm): float {
    $Rk = $radar['screening']['radioTerrestreAumentado'];
    $a = $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'] + $Rk;
    $b = $Rk + $flm;
    $c = $radar['range'] * BERTA_MILLA_NAUTICA_EN_METROS;
    $cos = ($a*$a + $b*$b - $c*$c) / (2*$a*$b);
    return acos(max(-1.0, min(1.0, $cos)));
}

/** Per-radar, FL-independent Cartesian profile. $staircase: floor between horizon points follows the ray (running max elevation). */
function fx_perfil(array $radar, bool $staircase): array {
    $Rk = $radar['screening']['radioTerrestreAumentado'];
    $ya = $Rk + $radar['screening']['towerHeight'] + $radar['screening']['terrainHeight'];
    $out = [];
    foreach ($radar['screening']['listaAzimuths'] as $az => $obs) {
        $pts = [[0.0, $ya, 0.0]]; // x, y, central angle
        $hx = null; $hy = null; $emax = -INF;
        foreach ($obs as $k => $o) {
            if ($k == 0) continue; // radar itself
            $r = $Rk + $o['altura']; $ang = $o['angulo'];
            $x = $r * sin($ang); $y = $r * cos($ang);
            if (!$staircase) { $pts[] = [$x, $y, $ang]; continue; }
            $e = atan2($y - $ya, $x);
            if ($e <= $emax) continue;               // hidden behind current horizon: floor is the ray
            if ($hx !== null) {                       // corner: current horizon ray evaluated at this point's central angle
                // ray: (0,ya) + s*(hx, hy-ya); radial line at angle ang: x*cos(ang) - y*sin(ang) = 0
                $den = $hx*cos($ang) - ($hy-$ya)*sin($ang);
                if ($den != 0.0) { $s = ($ya*sin($ang)) / $den; $pts[] = [$s*$hx, $ya + $s*($hy-$ya), $ang]; }
            }
            $pts[] = [$x, $y, $ang]; $hx = $x; $hy = $y; $emax = $e;
        }
        $out[$az] = $pts;
    }
    return $out;
}

/** Truncate the profile at alpha_max and add the wall (same model as create_matriz_obstaculos, with the yil fix). */
function fx_obstaculos(array $radar, array $perfil, float $flm): array {
    $Rk = $radar['screening']['radioTerrestreAumentado'];
    $alpha = fx_anguloMaximaCobertura($radar, $flm);
    $m = tan(M_PI_2 - $alpha);
    $out = [];
    foreach ($perfil as $az => $pts) {
        $ya = $pts[0][1]; $res = [];
        $last = null;
        foreach ($pts as $p) { if ($p[2] > $alpha) break; $res[] = [$p[0], $p[1]]; $last = $p; }
        [$xb, $yb] = [$last[0], $last[1]];
        $altLast = sqrt($xb*$xb + $yb*$yb) - $Rk;
        if ($altLast <= 30000 && $xb != 0.0) {
            $dx = $xb; $dy = $yb - $ya;
            $den = $dy - $m*$dx;
            if ($den != 0.0) {
                $t = (-$ya) / $den; // m*xa - ya with xa = 0
                if ($t >= 1) {
                    $xi = $t*$dx; $yi = $ya + $t*$dy;
                    $rTop = BERTA_MAX_WALL_HEIGHT + $Rk;
                    $res[] = [$xi, $yi];
                    if (hypot($xi, $yi) < $rTop) $res[] = [$rTop*cos(atan($m)), $rTop*sin(atan($m))]; // stay on the ray (fix)
                }
            }
        }
        $out[$az] = $res;
    }
    return $out;
}

/** Robust segment/circle crossings: classify endpoints (inside = f<0), no epsilon, no W+0.01, parity guaranteed. */
function fx_intersecciones(array $mo, float $W, float $Rk): array {
    $W2 = $W*$W; $out = [];
    foreach ($mo as $az => $pts) {
        $res = []; $n = count($pts);
        $f = []; foreach ($pts as $k => $p) $f[$k] = $p[0]*$p[0] + $p[1]*$p[1] - $W2; // one sign per vertex
        for ($k = 0; $k < $n-1; $k++) {
            $x1 = $pts[$k][0]; $y1 = $pts[$k][1]; $x2 = $pts[$k+1][0]; $y2 = $pts[$k+1][1];
            $dx = $x2-$x1; $dy = $y2-$y1;
            $A = $dx*$dx + $dy*$dy; if ($A == 0.0) continue;
            $B = 2*($x1*$dx + $y1*$dy); $C = $f[$k];
            $in1 = $f[$k] < 0; $in2 = $f[$k+1] < 0;
            $ts = [];
            if ($in1 !== $in2) {
                $s = sqrt(max(0.0, $B*$B - 4*$A*$C));
                $q = -0.5*($B + ($B >= 0 ? $s : -$s));
                $cand = [$q/$A]; if ($q != 0.0) $cand[] = $C/$q;
                $best = null; foreach ($cand as $t) { $tc = max(0.0, min(1.0, $t)); if ($best === null || abs($tc-$t) < abs($best[0]-$best[1])) $best = [$tc, $t]; }
                $ts[] = $best[0];
            } elseif (!$in1) {
                $tv = -$B/(2*$A);
                if ($tv > 0 && $tv < 1 && ($C - $B*$B/(4*$A)) < 0) {
                    $s = sqrt($B*$B - 4*$A*$C);
                    $ts[] = (-$B - $s)/(2*$A); $ts[] = (-$B + $s)/(2*$A);
                }
            }
            foreach ($ts as $t) { $xi = $x1+$t*$dx; $yi = $y1+$t*$dy; $res[] = $Rk*atan2($xi, $yi)/BERTA_MILLA_NAUTICA_EN_METROS; }
        }
        $out[$az] = $res;
    }
    return $out;
}

/** Exact spherical direct problem. Returns [lat, lon] in degrees. */
function fx_destino(float $lat_rad, float $lon_rad, float $dist_m, float $az_rad, float $R = BERTA_RADIO_TERRESTRE): array {
    $d = $dist_m / $R; $sl = sin($lat_rad); $cl = cos($lat_rad); $cd = cos($d); $sd = sin($d);
    $sl2 = $sl*$cd + $cl*$sd*cos($az_rad);
    $lat2 = asin(max(-1.0, min(1.0, $sl2)));
    $lon2 = $lon_rad + atan2(sin($az_rad)*$sd*$cl, $cd - $sl*$sl2);
    return [rad2deg($lat2), rad2deg($lon2)];
}

/**
 * Coverage outline traced directly from per-azimuth radial intervals (no polygon union).
 * $intervals[j] = list of [start_nm, end_nm] for azimuth bin j (bin j spans j*step ± step/2).
 * Returns rings [[lat,lon],...] (closed implicitly), same shape as MR getArray().
 */
function fx_bandas(array $radar, array $intervals, float $maxRadialStepNm = 10.0): array {
    $N = count($intervals); $step = 360.0 / $N;
    // normalise: sort + merge touching/overlapping intervals per bin
    $bins = [];
    for ($j = 0; $j < $N; $j++) {
        $iv = $intervals[$j] ?? []; usort($iv, fn($a, $b) => $a[0] <=> $b[0]); $m = [];
        foreach ($iv as [$s, $e]) { if ($e <= $s) continue; if ($m && $s <= $m[count($m)-1][1]) { $m[count($m)-1][1] = max($m[count($m)-1][1], $e); } else $m[] = [$s, $e]; }
        $bins[$j] = $m;
    }
    // directed edges in (k = boundary index mod N, r) space; region on the left with u = azimuth, v = r
    $out = []; // key "k|r" => list of [toKey, dirCode]  dir: 0=+u,1=+v,2=-u,3=-v
    $pt = [];  // key => [k, r]
    $add = function (int $k1, float $r1, int $k2, float $r2, int $dir) use (&$out, &$pt, $N) {
        $k1 %= $N; $k2 %= $N; $a = $k1 . '|' . $r1; $b = $k2 . '|' . $r2;
        $pt[$a] = [$k1, $r1]; $pt[$b] = [$k2, $r2]; $out[$a][] = [$b, $dir];
    };
    for ($j = 0; $j < $N; $j++) foreach ($bins[$j] as [$s, $e]) {
        $add($j, $s, $j + 1, $s, 0);   // bottom arc, +u
        $add($j + 1, $e, $j, $e, 2);   // top arc, -u
    }
    for ($k = 0; $k < $N; $k++) { // boundary k between bin k-1 and bin k
        $L = $bins[($k - 1 + $N) % $N]; $R = $bins[$k];
        $cuts = []; foreach ([$L, $R] as $set) foreach ($set as [$s, $e]) { $cuts[(string)$s] = $s; $cuts[(string)$e] = $e; }
        $cuts = array_values($cuts); sort($cuts);
        $cov = function (array $set, float $x): bool { foreach ($set as [$s, $e]) if ($x > $s && $x < $e) return true; return false; };
        for ($i = 0; $i < count($cuts) - 1; $i++) {
            $a = $cuts[$i]; $b = $cuts[$i + 1]; $mid = 0.5 * ($a + $b);
            $l = $cov($L, $mid); $r = $cov($R, $mid);
            if ($l && !$r) $add($k, $a, $k, $b, 1);       // region at u<k: +v
            elseif ($r && !$l) $add($k, $b, $k, $a, 3);   // region at u>k: -v
        }
    }
    // trace rings: at each vertex prefer left turn, then straight, then right
    $pref = [0 => [1, 0, 3], 1 => [2, 1, 0], 2 => [3, 2, 1], 3 => [0, 3, 2]];
    $rings = [];
    foreach (array_keys($out) as $start) {
        while (!empty($out[$start])) {
            $ring = [$start]; [$cur, $dir] = array_pop($out[$start]);
            $guard = 0;
            while ($cur !== $start || !empty($out[$cur]) && false) {
                $ring[] = $cur;
                $opts = $out[$cur] ?? []; if (!$opts) break;
                $pick = null; foreach ($pref[$dir] as $want) { foreach ($opts as $i => $o) if ($o[1] === $want) { $pick = $i; break 2; } }
                if ($pick === null) $pick = array_key_first($opts);
                [$nxt, $dir] = $opts[$pick]; unset($out[$cur][$pick]); $out[$cur] = array_values($out[$cur]);
                $cur = $nxt; if (++$guard > 10000000) break;
            }
            $rings[] = $ring;
        }
    }
    // to lat/lon, subdividing radial edges
    $lat = $radar['lat_rad']; $lon = $radar['lon_rad']; $res = [];
    $cache = [];
    $toLL = function (int $k, float $r) use ($lat, $lon, $step, &$cache) {
        $key = $k . '|' . $r; if (isset($cache[$key])) return $cache[$key];
        return $cache[$key] = ($r <= 0.0) ? [rad2deg($lat), rad2deg($lon)] : fx_destino($lat, $lon, $r * BERTA_MILLA_NAUTICA_EN_METROS, deg2rad($k * $step - $step / 2));
    };
    foreach ($rings as $ring) {
        $poly = []; $n = count($ring);
        for ($i = 0; $i < $n; $i++) {
            [$k1, $r1] = $pt[$ring[$i]]; [$k2, $r2] = $pt[$ring[($i + 1) % $n]];
            $p = $toLL($k1, $r1); if (!$poly || $poly[count($poly)-1] != $p) $poly[] = $p;
            if ($k1 === $k2 && abs($r2 - $r1) > $maxRadialStepNm) {
                $ns = (int)ceil(abs($r2 - $r1) / $maxRadialStepNm);
                for ($s = 1; $s < $ns; $s++) $poly[] = $toLL($k1, $r1 + ($r2 - $r1) * $s / $ns);
            }
        }
        if (count($poly) > 1 && $poly[0] == $poly[count($poly)-1]) array_pop($poly);
        if (count($poly) >= 3) $res[] = $poly;
    }
    return $res;
}

/** Intervals per azimuth from a crossing list. Below radar: pairs (r0,r1),(r2,r3)...; above radar: [0 or cone, r0], (r1,r2)... */
function fx_intervalos(array $crossings, bool $above, float $cone = 0.0): array {
    $out = [];
    foreach ($crossings as $j => $l) {
        $l = $above ? array_merge([0.0], $l) : $l; $iv = [];
        for ($i = 0; $i + 1 < count($l); $i += 2) { $s = max($l[$i], $above ? $cone : 0.0); if ($l[$i+1] > $s) $iv[] = [$s, $l[$i+1]]; }
        $out[$j] = $iv;
    }
    return $out;
}

/**
 * Multi-radar by incremental refinement: cells keyed by the exact set of radars covering them.
 * Adding radar S splits every existing cell P into P∩S and P−S, plus S−(union so far). Empty cells are dropped,
 * so work follows the number of non-empty overlap regions instead of 2^N.
 * @param array<string,\Ifsnop\MartinezRueda\Polygon> $mr sensor => polygon (sorted keys)
 * @return array<string,\Ifsnop\MartinezRueda\Polygon> "a,b,c" => region covered by exactly those radars
 */
function fx_multi_refine(array $mr, ?int &$ops = null): array {
    $ops = 0; $cells = []; $union = null;
    ksort($mr);
    foreach ($mr as $s => $S) {
        $new = [];
        foreach ($cells as $key => $P) {
            $I = \Ifsnop\MartinezRueda\Algorithm::intersect($P, $S); $ops++;
            if ($I->numPoints > 0) {
                $new[$key . ',' . $s] = $I;
                $D = \Ifsnop\MartinezRueda\Algorithm::difference($P, $S); $ops++;
                if ($D->numPoints > 0) $new[$key] = $D;
            } else {
                $new[$key] = $P; // untouched
            }
        }
        $only = $union === null ? $S : \Ifsnop\MartinezRueda\Algorithm::difference($S, $union); if ($union !== null) $ops++;
        if ($only->numPoints > 0) $new[$s] = $only;
        $union = $union === null ? $S : \Ifsnop\MartinezRueda\Algorithm::union($union, $S); $ops++;
        $cells = $new;
    }
    return $cells;
}

/** FL-independent per-segment data: endpoint r^2 and the segment's minimum r^2 (closest approach to Earth centre). */
function fx_prep_segmentos(array $perfil): array {
    $out = [];
    foreach ($perfil as $az => $pts) {
        $n = count($pts); $r2 = []; $min2 = [];
        foreach ($pts as $k => $p) $r2[$k] = $p[0]*$p[0] + $p[1]*$p[1];
        for ($k = 0; $k < $n-1; $k++) {
            $dx = $pts[$k+1][0]-$pts[$k][0]; $dy = $pts[$k+1][1]-$pts[$k][1]; $A = $dx*$dx+$dy*$dy;
            $t = $A > 0 ? -($pts[$k][0]*$dx + $pts[$k][1]*$dy)/$A : 0.0;
            $min2[$k] = ($t > 0 && $t < 1) ? (($pts[$k][0]+$t*$dx)**2 + ($pts[$k][1]+$t*$dy)**2) : min($r2[$k], $r2[$k+1]);
        }
        $out[$az] = [$r2, $min2];
    }
    return $out;
}

/** Same result as fx_intersecciones(fx_obstaculos(...)) but skips profile segments that cannot touch the FL circle. */
function fx_intersecciones_rapido(array $radar, array $perfil, array $prep, float $flm): array {
    $Rk = $radar['screening']['radioTerrestreAumentado']; $W = $Rk + $flm; $W2 = $W*$W;
    $alpha = fx_anguloMaximaCobertura($radar, $flm);
    $res = [];
    foreach ($perfil as $az => $pts) {
        [$r2, $min2] = $prep[$az];
        // truncation index (profile points within alpha)
        $n = 0; foreach ($pts as $p) { if ($p[2] > $alpha) break; $n++; }
        $cand = [];
        $lastK = $n - 1;
        for ($k = 0; $k < $lastK; $k++) {
            $in1 = $r2[$k] < $W2; $in2 = $r2[$k+1] < $W2;
            if ($in1 === $in2 && ($in1 || $min2[$k] >= $W2)) continue; // no crossing possible
            $cand[] = $k;
        }
        // crossings of the candidate segments, then the wall tail from the generic routine
        $list = [];
        foreach ($cand as $k) { $seg = fx_intersecciones([0 => [[$pts[$k][0],$pts[$k][1]],[$pts[$k+1][0],$pts[$k+1][1]]]], $W, $Rk); foreach ($seg[0] as $d) $list[] = $d; }
        // tail: last kept point + wall (same construction as fx_obstaculos)
        $tail = fx_obstaculos($radar, [$az => array_slice($pts, 0, $n)], $flm)[$az];
        $tail = array_slice($tail, $n - 1);
        if (count($tail) > 1) { $seg = fx_intersecciones([0 => $tail], $W, $Rk); foreach ($seg[0] as $d) $list[] = $d; }
        $res[$az] = $list;
    }
    return $res;
}
