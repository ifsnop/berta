<?php
declare(strict_types=1);

/**
 * Estimador de tiempo restante (ETA) anti "minuto Microsoft"
 *
 * Combina tres técnicas para lograr estimaciones estables y realistas:
 *  1. EWMA (Exponentially Weighted Moving Average) sobre la tasa de progreso,
 *     para reaccionar a cambios recientes sin sobrerreaccionar a ruido puntual.
 *  2. Proyección cuadrática (mínimos cuadrados sobre las últimas N muestras)
 *     para detectar si el proceso se está acelerando o frenando.
 *  3. Suavizado del ETA final con EWMA sobre el propio resultado, para evitar
 *     saltos bruscos en la UI aunque la tasa cambie de golpe.
 *
 * Uso:
 *   $eta = new EtaEstimator();
 *
 *   while ($working) {
 *       $progress = getProgress(); // float 0.0 - 1.0
 *       $seconds  = $eta->update($progress);
 *       echo $seconds !== null ? "{$seconds}s restantes" : "Calculando...";
 *   }
 */
class EtaEstimator
{
    // ── Configuración ──────────────────────────────────────────────────────

    /** Factor de suavizado EWMA para la tasa (0=ignora recientes, 1=ignora histórico) */
    private float $rateAlpha = 0.3;

    /** Factor de suavizado EWMA para el ETA final (estabiliza la UI) */
    private float $etaAlpha = 0.2;

    /** Muestras mínimas antes de devolver una estimación */
    private int $minSamples = 3;

    /** Máximo de muestras almacenadas para la regresión cuadrática */
    private int $windowSize = 20;

    /** Peso de la proyección cuadrática vs EWMA (0 = solo EWMA, 1 = solo cuadrática) */
    private float $quadraticWeight = 0.3;

    /** Multiplica el tiempo transcurrido para acotar el ETA máximo (anti-congelado) */
    private float $maxEtaMultiplier = 10.0;

    // ── Estado interno ─────────────────────────────────────────────────────

    /** @var array<int, array{t: float, p: float}> Historial de muestras {timestamp, progreso} */
    private array $samples = [];

    /** Tasa de progreso por segundo suavizada con EWMA */
    private ?float $ewmaRate = null;

    /** ETA anterior (para suavizado del resultado final) */
    private ?float $smoothedEta = null;

    /** Timestamp de la primera muestra */
    private ?float $startTime = null;

    // ── API pública ────────────────────────────────────────────────────────

    /**
     * Registra el progreso actual y devuelve los segundos estimados restantes.
     *
     * @param float $progress Valor entre 0.0 (inicio) y 1.0 (fin)
     * @return int|null Segundos restantes, o null si no hay datos suficientes
     */
    public function update(float $progress): ?int
    {
        $progress = max(0.0, min(1.0, $progress));
        $now      = microtime(true);

        // Progreso completado: no hay tiempo restante
        if ($progress >= 1.0) {
            return 0;
        }

        // Registrar muestra
        if ($this->startTime === null) {
            $this->startTime = $now;
        }

        $this->samples[] = ['t' => $now, 'p' => $progress];

        // Mantener ventana deslizante
        if (count($this->samples) > $this->windowSize) {
            array_shift($this->samples);
        }

        $n = count($this->samples);

        // Necesitamos al menos minSamples para estimar
        if ($n < $this->minSamples) {
            return null;
        }

        // ── 1. Tasa EWMA ───────────────────────────────────────────────────
        //
        // Calculamos la tasa instantánea entre la última y la antepenúltima
        // muestra y la integramos en la media ponderada exponencialmente.
        // Usar la penúltima (no la primera) nos da la velocidad *reciente*.
        $last = $this->samples[$n - 1];
        $prev = $this->samples[$n - 2];

        $dt = $last['t'] - $prev['t'];
        $dp = $last['p'] - $prev['p'];

        if ($dt > 0 && $dp > 0) {
            $instantRate = $dp / $dt; // progreso/segundo

            $this->ewmaRate = $this->ewmaRate === null
                ? $instantRate
                : ($this->rateAlpha * $instantRate + (1 - $this->rateAlpha) * $this->ewmaRate);
        }

        if ($this->ewmaRate === null || $this->ewmaRate <= 0) {
            return null;
        }

        $remaining    = 1.0 - $progress;
        $etaFromEwma  = $remaining / $this->ewmaRate;

        // ── 2. Proyección cuadrática (mínimos cuadrados) ───────────────────
        //
        // Ajusta p(t) = a·t² + b·t + c sobre las últimas N muestras
        // (usando tiempo relativo al inicio para evitar overflow numérico).
        // El coeficiente 'a' indica aceleración: positivo = acelerando,
        // negativo = frenando. Esto corrige la estimación lineal pura.
        $etaFromQuad = $this->quadraticEta($progress, $now);

        // ── 3. Blend ───────────────────────────────────────────────────────
        //
        // EWMA es el ancla estable; la cuadrática aporta reactividad a
        // cambios de tendencia. Si la cuadrática da negativo (proceso
        // aparentemente invertido) confiamos solo en EWMA.
        if ($etaFromQuad !== null && $etaFromQuad > 0) {
            $rawEta = (1 - $this->quadraticWeight) * $etaFromEwma
                    +       $this->quadraticWeight  * $etaFromQuad;
        } else {
            $rawEta = $etaFromEwma;
        }

        // ── 4. Clamp de seguridad ──────────────────────────────────────────
        //
        // El ETA no puede ser mayor que X veces el tiempo ya transcurrido
        // (evita que un proceso que arranca muy lento proyecte días enteros).
        $elapsed = $now - $this->startTime;
        if ($elapsed > 0) {
            $maxEta = $elapsed * $this->maxEtaMultiplier;
            $rawEta = min($rawEta, $maxEta);
        }

        $rawEta = max(0.0, $rawEta);

        // ── 5. Suavizado del ETA final ─────────────────────────────────────
        //
        // Aplica EWMA sobre el propio ETA para que la UI no salte de 120s
        // a 45s de un tick para otro. El alpha bajo (0.2) lo hace conservador.
        $this->smoothedEta = $this->smoothedEta === null
            ? $rawEta
            : ($this->etaAlpha * $rawEta + (1 - $this->etaAlpha) * $this->smoothedEta);

        return (int) round($this->smoothedEta);
    }

    /**
     * Reinicia el estimador para un nuevo proceso.
     */
    public function reset(): void
    {
        $this->samples     = [];
        $this->ewmaRate    = null;
        $this->smoothedEta = null;
        $this->startTime   = null;
    }

    // ── Internos ───────────────────────────────────────────────────────────

    /**
     * Calcula el ETA mediante regresión cuadrática sobre las muestras actuales.
     *
     * Ajusta p(t) = a·τ² + b·τ + c (τ = tiempo relativo al primer sample)
     * y usa la derivada dp/dτ = 2a·τ + b en τ_actual para estimar la tasa
     * de progreso instantánea con corrección de aceleración.
     *
     * @return float|null ETA en segundos, o null si la regresión es inválida
     */
    private function quadraticEta(float $currentProgress, float $now): ?float
    {
        $n = count($this->samples);
        if ($n < 3) {
            return null;
        }

        $t0 = $this->samples[0]['t'];

        // Construir sistema de ecuaciones normales para mínimos cuadrados
        // con polinomio grado 2: [Σt⁴ Σt³ Σt²] [a]   [Σt²p]
        //                        [Σt³ Σt² Σt ] [b] = [Σtp ]
        //                        [Σt² Σt  Σ1 ] [c]   [Σp  ]
        $s = array_fill_keys(
            ['t1','t2','t3','t4','pt0','pt1','pt2'],
            0.0
        );

        foreach ($this->samples as $sample) {
            $tau = $sample['t'] - $t0;
            $p   = $sample['p'];
            $s['t1']  += $tau;
            $s['t2']  += $tau ** 2;
            $s['t3']  += $tau ** 3;
            $s['t4']  += $tau ** 4;
            $s['pt0'] += $p;
            $s['pt1'] += $p * $tau;
            $s['pt2'] += $p * $tau ** 2;
        }

        // Sistema 3×3: A·x = b
        $A = [
            [$s['t4'], $s['t3'], $s['t2']],
            [$s['t3'], $s['t2'], $s['t1']],
            [$s['t2'], $s['t1'], $n],
        ];
        $b = [$s['pt2'], $s['pt1'], $s['pt0']];

        $coeffs = $this->solveLinear3($A, $b);
        if ($coeffs === null) {
            return null;
        }

        [$a, $bCoef, $c] = $coeffs;

        // Tasa de progreso en el instante actual: dp/dτ = 2a·τ + b
        $tauNow      = $now - $t0;
        $rateAtNow   = 2 * $a * $tauNow + $bCoef;

        if ($rateAtNow <= 0) {
            return null; // proceso detenido o regresando; no fiable
        }

        $remaining = 1.0 - $currentProgress;
        return $remaining / $rateAtNow;
    }

    /**
     * Resuelve un sistema lineal 3×3 mediante eliminación de Gauss con
     * pivoteo parcial. Devuelve [x0, x1, x2] o null si es singular.
     *
     * @param float[][] $A Matriz 3×3
     * @param float[]   $b Vector independiente
     * @return float[]|null
     */
    private function solveLinear3(array $A, array $b): ?array
    {
        $n = 3;

        // Aumentada [A|b]
        for ($i = 0; $i < $n; $i++) {
            $A[$i][] = $b[$i];
        }

        for ($col = 0; $col < $n; $col++) {
            // Pivoteo parcial
            $maxRow = $col;
            $maxVal = abs($A[$col][$col]);
            for ($row = $col + 1; $row < $n; $row++) {
                if (abs($A[$row][$col]) > $maxVal) {
                    $maxVal = abs($A[$row][$col]);
                    $maxRow = $row;
                }
            }
            [$A[$col], $A[$maxRow]] = [$A[$maxRow], $A[$col]];

            if (abs($A[$col][$col]) < 1e-12) {
                return null; // sistema singular
            }

            // Eliminación
            for ($row = $col + 1; $row < $n; $row++) {
                $factor = $A[$row][$col] / $A[$col][$col];
                for ($k = $col; $k <= $n; $k++) {
                    $A[$row][$k] -= $factor * $A[$col][$k];
                }
            }
        }

        // Sustitución hacia atrás
        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $x[$i] = $A[$i][$n];
            for ($j = $i + 1; $j < $n; $j++) {
                $x[$i] -= $A[$i][$j] * $x[$j];
            }
            $x[$i] /= $A[$i][$i];
        }

        return $x;
    }
}


// ── Ejemplo de uso ─────────────────────────────────────────────────────────

/*
$eta = new EtaEstimator();

$total = 1000;
for ($i = 0; $i <= $total; $i++) {
    // Simula procesamiento con velocidad variable
    usleep(rand(500, 3000));

    $progress = $i / $total;
    $seconds  = $eta->update($progress);

    if ($seconds !== null) {
        printf("Progreso: %5.1f%%  |  ETA: %ds\n", $progress * 100, $seconds);
    } else {
        printf("Progreso: %5.1f%%  |  ETA: calculando...\n", $progress * 100);
    }
}
*/
