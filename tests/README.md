# Pruebas y mediciones de BERTA

Todo lo que respalda `docs/review-and-proposed-fixes.html`: la lista de problemas con su verificación
automática, la implementación de referencia con la que se midió y los scripts que dieron cada número.
Se ejecuta desde la raíz del repositorio y necesita `composer install` y los datos de `spain.tsk/`.

## 1. Lista de problemas: `verify_fixes.php`

Una comprobación por problema de la revisión (M, X, O, E, P, S y las preguntas Q). Ejecuta BERTA en
`tests/.tmp/` y compara el resultado con un modelo de referencia propio, así que da por bueno un arreglo
aunque se haga de otra forma que la propuesta.

```
php tests/verify_fixes.php            # todo (unos 2 minutos)
php tests/verify_fixes.php --quick    # sin las pruebas lentas de rendimiento
php tests/verify_fixes.php --only=X1,X2
php tests/verify_fixes.php --list     # sólo la lista
```

- `fix-status.md`: estado actual, pendientes y resueltos por separado. Lo regenera cada ejecución completa.
- `fix-decisions.json`: respuestas a las preguntas abiertas (Q1–Q3); una pregunta con respuesta cuenta como resuelta.

## 2. Implementación de referencia: `prototype/`

- `reference.php`: las funciones `fx_*` con las que se midió todo (cruces robustos, perfil escalonado,
  contorno por intervalos, problema directo exacto, multiradar por refinamiento…). Son independientes del
  código de BERTA, para poder compararlo con ellas.
- `proposed-fixes.patch`: los Cambios 1–8 completos contra el commit `ba079d8`. Algunos ya están aplicados
  (ver el índice al principio de la Parte II del documento); sirve de referencia para los pendientes.

## 3. Mediciones: `measurements/`

Cada script lleva en la cabecera qué mide, cómo se usa y qué resultado dio en la revisión. Todos aceptan
argumentos opcionales; sin argumentos usan los mismos radares y niveles que la revisión. El registro de
BERTA sale por STDERR, así que conviene añadir `2>/dev/null`. Con `BERTA_WORK=/otra/copia` miden otra copia del código.

| Script | Qué mide | Problemas |
| --- | --- | --- |
| `vertex_accuracy.php` | Error de la fórmula de vértices actual frente a la esfera exacta | P1, M4 |
| `precision_wgs84.php` | Posiciones frente a WGS84 (Vincenty), radio de Euler, coste por vértice | P1, P2 |
| `vertex_cost.php` | Coste por vértice de cada fórmula; tamaño del perfil escalonado | P1, P2, E4 |
| `range_psr_nan.php` | Alcance del PSR, `-m`, ángulo cuando el alcance no llega al nivel | M1, M5 |
| `above_radar_area.php` | Área por encima del radar frente a una referencia con sombras | L1 |
| `screening_profile.php` | Si el screening es un perfil de horizonte y si el último punto es el más alto | E4, muro |
| `staircase_area.php` | Cambio de área con el suelo escalonado | E4 |
| `wall_cap.php` | Veces que el tope del muro saca el punto de la recta | E7 |
| `crossing_parity.php` | Paridad de cruces en FL1–400, código actual frente a cruces robustos | E5, E6 |
| `stage_timings.php` | Tiempo de cada etapa del cálculo monoradar | S1 |
| `outline_tracing.php` | Contorno por intervalos frente a la unión actual: tiempo y precisión | E3, S1 |
| `sweep_speed.php` | Barrido FL1–400: código actual frente al prototipo | S2 |
| `sweep_calculosfl.php` | Tiempo de `calculosFL()` en FL1–400 con el código que haya | S1, S2 |
| `multi_refinement.php` | Multiradar: todos los grupos frente al refinamiento incremental | X6, S3 |
| `multi_levels_sampling.php` | Por muestreo, que cada punto caiga en la capa multiradar correcta del KML | X1, X2 |
| `patched_vs_prototype.php` | Código de los Cambios 6–8 frente a la referencia | Cambio 6 |

Algunos scripts comparan con funciones del código actual que el Cambio 6 sustituirá
(`calcula_vertices_interseccion`, `create_matriz_obstaculos`, `create_poligonos_cobertura`…).
Cuando esas funciones ya no existan, el script lo indica con `SKIPPED` en lugar de fallar.
`patched_vs_prototype.php` hace lo contrario: sólo funciona cuando el Cambio 6 está aplicado.
