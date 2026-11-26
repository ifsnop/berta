<?php
/**
 * Multiradar: comprueba por muestreo que cada punto cae en la capa "exactamente k radares" correcta del KML
 * (carpeta multiradar-union), contando cuántas coberturas monoradar lo contienen.
 * Requiere ejecutar antes BERTA en el directorio de trabajo, p. ej.:
 *   php main.php -2 -r "barajas paracuellos1 valladolid" -s 100 -z
 *   php tests/measurements/multi_levels_sampling.php RESULTADOS/MULTI/100.kml 100 barajas paracuellos1 valladolid 2>/dev/null
 * Variable N = número de puntos (20000 por defecto).
 * Resultado en la revisión: 0 discrepancias con caché fría; 53 % con caché caliente de otro conjunto (X1).
 */
// usage: t4_multi_verify.php <kml> <FL3> sensor1 sensor2 ...
require __DIR__.'/bootstrap.php';
if ($argc < 4) { fwrite(STDERR, "Uso: multi_levels_sampling.php KML FL radar1 radar2 ...\n"); exit(2); }
[$_, $kmlFile, $fl] = $argv; $sensors = array_slice($argv, 3);
$txt = file_get_contents($kmlFile);
function folderBody(string $txt, string $name): string { $i=strpos($txt, "<name>$name</name>"); $depth=1; $pos=$i;
  while ($depth>0) { $o=strpos($txt,'<Folder>',$pos); $c=strpos($txt,'</Folder>',$pos); if ($o!==false && $o<$c) { $depth++; $pos=$o+8; } else { $depth--; $pos=$c+9; } }
  return substr($txt,$i,$pos-$i); }
function placemarks(string $body): array { preg_match_all('#<Placemark>.*?<name>(.*?)</name>(.*?)</Placemark>#s',$body,$m,PREG_SET_ORDER); $out=[];
  foreach ($m as [$_, $name, $pb]) { $polys=[]; preg_match_all('#<Polygon>(.*?)</Polygon>#s',$pb,$pm);
    foreach ($pm[1] as $pg) { $poly=[]; preg_match_all('#<coordinates>(.*?)</coordinates>#s',$pg,$cm);
      foreach ($cm[1] as $c) { $ring=[]; foreach (preg_split('/\s+/', trim($c)) as $t) { [$lo,$la]=explode(',',$t); $ring[]=[(float)$la,(float)$lo]; } $poly[]=$ring; } $polys[]=$poly; }
    $out[]=[$name,$polys]; } return $out; }
function inside(array $polys, float $la, float $lo): bool { foreach ($polys as $poly) { $in=false; foreach ($poly as $ring) if (pointInPolygon($la,$lo,$ring,count($ring))) $in=!$in; if ($in) return true; } return false; }
$level=[]; foreach (placemarks(folderBody($txt,'multiradar-union')) as [$n,$pl]) $level[(int)explode('-',$n)[0]]=$pl;
$unica = strpos($txt,"<name>unica</name>")!==false ? placemarks(folderBody($txt,"unica"))[0][1] : array_merge(...array_values($level)); if (strpos($txt,"<name>unica</name>")===false) echo "NOTE: no unica folder in KML\n";
$mono = [];
foreach ($sensors as $s) { $mono[$s] = json_decode(file_get_contents("cache/$s/$s-FL$fl.json"), true); }
$la0=90;$la1=-90;$lo0=180;$lo1=-180; foreach ($mono as $p) foreach ($p as $ring) foreach ($ring as [$a,$b]) { $la0=min($la0,$a);$la1=max($la1,$a);$lo0=min($lo0,$b);$lo1=max($lo1,$b); }
mt_srand(42); $N=(int)(getenv('N')?:20000); $bad=0; $badU=0; $multiHit=0; $hist=[];
for ($i=0;$i<$N;$i++) {
  $la=$la0+($la1-$la0)*mt_rand()/mt_getrandmax(); $lo=$lo0+($lo1-$lo0)*mt_rand()/mt_getrandmax();
  $cnt=0; foreach ($mono as $p) { $in=false; foreach ($p as $ring) if (pointInPolygon($la,$lo,$ring,count($ring))) $in=!$in; if($in)$cnt++; }
  $got=[]; foreach ($level as $k=>$pl) if (inside($pl,$la,$lo)) $got[]=$k;
  if (count($got)>1) $multiHit++;
  $g = $got ? $got[0] : 0; @$hist[$cnt]++;
  if ($g !== $cnt) $bad++;
  if (inside($unica,$la,$lo) !== ($cnt>0)) $badU++;
}
ksort($hist);
printf("levels present: %s | samples %d | expected-count histogram %s\n", implode(',',array_keys($level)), $N, json_encode($hist));
printf("level mismatches: %d (%.3f%%) | points in >1 level: %d | unica mismatches: %d (%.3f%%)\n", $bad, 100*$bad/$N, $multiHit, $badU, 100*$badU/$N);
