<?php
/**
 * Por encima del radar: área de la cobertura de BERTA frente a una referencia que respeta las sombras
 * ([0,r0] ∪ [r1,r2]...) y frente a la envolvente; cuenta acimuts con más de un cruce (problema latente L1).
 * Uso: php tests/measurements/above_radar_area.php [radar1,radar2,...] 2>/dev/null
 * Resultado en la revisión: diferencias de 0,0–0,5 % y 0 acimuts con varios cruces en los datos incluidos.
 */
require __DIR__.'/bootstrap.php';
requires('create_poligonos_cobertura', 'the sector builder replaced by Change 6D');
use Ifsnop\MartinezRueda as MR;
$radars = explode(',', $argv[1] ?? 'paracuellos1,valdespina,erillas,alcolea');
$fls = [30, 60, 100, 150, 250];
foreach ($radars as $name) {
  $r = loadRadar($name);
  $hA = $r['screening']['towerHeight'] + $r['screening']['terrainHeight'];
  foreach ($fls as $fl) {
    $flm = $fl*100*BERTA_FEET_TO_METERS; if ($flm < $hA) continue;
    $mo = create_matriz_obstaculos($r, $flm); $mx=0;
    $W = $flm + $r['screening']['radioTerrestreAumentado'] + 0.01;
    $inter = create_matriz_intersecciones($r, $mo, $W, $mx);
    $multi=0; $even=0; $zero=0;
    foreach ($inter as $l) { $c=count($l); if($c>1)$multi++; if($c%2==0)$even++; if($c==0)$zero++; }
    // BERTA polygon (as main.php would compute it)
    $berta = calculosFL($r, (float)$fl, sprintf('%03d',$fl), false);
    $aB = areaKm2(MR\Polygon::create()->fillFromArray($berta)->getArray(), $r['lat']);
    // reference: ring sectors [0,r0] U [r1,r2] ...  via the same sector builder used below radar
    $inter0 = array_map(fn($l)=>array_merge([0.01], $l), $inter);
    $ref = create_poligonos_cobertura($r, $inter0);
    $aR = areaKm2($ref, $r['lat']);
    // envelope only: disk out to the farthest intersection (what the comment claims)
    $env = array_map(fn($l)=>[0.01, $l ? max($l) : 0.02], $inter);
    $aE = areaKm2(create_poligonos_cobertura($r, $env), $r['lat']);
    printf("%-13s FL%03d az=%d  az_with_>1_crossing=%3d even_count=%d empty=%d | area BERTA %8.0f km2  ref(shadows) %8.0f  envelope %8.0f  diff vs ref %+5.1f%%\n",
      $name,$fl,count($inter),$multi,$even,$zero,$aB,$aR,$aE,100*($aB-$aR)/$aR);
  }
}
