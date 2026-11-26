<?php
/**
 * Screening: ¿es un perfil de horizonte (ángulo de elevación no decreciente)? ¿El último punto es el de
 * mayor elevación (lo que justifica el muro final)?
 * Uso: php tests/measurements/screening_profile.php [radar1,radar2,...] 2>/dev/null
 * Resultado en la revisión: método 135 sin descensos; método 136 con unos pocos; el último punto es el más
 * alto en el 99,9 % de los acimuts.
 */
require __DIR__.'/bootstrap.php';
// Is the screening a horizon profile (elevation angle non-decreasing)? Is the last point the max-elevation one?
foreach (explode(',', $argv[1] ?? 'paracuellos1,paracuellos2,alcolea,barajas,canchoblanco,erillas,monflorite,valdespina,valladolid') as $name) {
  $r = loadRadar($name);
  $Rk = $r['screening']['radioTerrestreAumentado']; $ya = $Rk + $r['screening']['towerHeight'] + $r['screening']['terrainHeight'];
  $nonmono=0; $lastNotMax=0; $maxGapDeg=0; $sumpts=0; $worstWallErr=0;
  foreach ($r['screening']['listaAzimuths'] as $az => $obs) {
    $prev=-INF; $emax=-INF; $elast=null; $sumpts += count($obs);
    foreach ($obs as $k => $o) { if ($k==0) continue;
      $rr = $Rk + $o['altura']; $x = $rr*sin($o['angulo']); $y = $rr*cos($o['angulo']);
      $e = atan2($y-$ya, $x); if ($e < $prev - 1e-12) $nonmono++; $prev=$e; $emax=max($emax,$e); $elast=$e; }
    if ($elast < $emax - 1e-9) { $lastNotMax++; $worstWallErr=max($worstWallErr, rad2deg($emax-$elast)); }
  }
  printf("%-13s azimuths=%d avg pts/az=%.0f  elevation decreases=%d  azimuths where last point is NOT the highest-elevation one=%d (worst %.3f deg)\n",
    $name, count($r['screening']['listaAzimuths']), $sumpts/count($r['screening']['listaAzimuths']), $nonmono, $lastNotMax, $worstWallErr);
}
