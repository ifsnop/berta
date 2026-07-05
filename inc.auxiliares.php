<?php
declare(strict_types=1);

/**
 * Obtiene un valor cacheado o lo calcula si no existe.
 *
 * @param array  &$cache     Array de caché (pasado por referencia)
 * @param string  $key       Clave de caché
 * @param callable $compute  Función que calcula y devuelve el valor
 * @return mixed             Valor cacheado o recién calculado
 */
function getCached(array &$cache, string $key, callable $compute)
{
    if (!isset($cache[$key])) {
        $cache[$key] = $compute();
    }
    return $cache[$key];
}

/**
 * Express number with a byte prefix (byte, Kb, Mb, Gb...)
 * @param float $size number of bytes
 * @return string rounded number with prefix
*/
function convertBytes(float $size) {
        $unit = array('b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb');
        return @round($size / pow(1024, ($i = (int)floor(log($size, 1024)))), 1) . '' . $unit[$i];
}

/*
 * Count set bits by pre-storing count set bits in nibbles.
 * @param int number to count bits set to '1'
 * @return int number of bits set to '1'
 */
function countSetBits(int $num) {
    $num_to_bits = array(0, 1, 1, 2, 1, 2, 2, 3,
                     1, 2, 2, 3, 2, 3, 3, 4);

    global $num_to_bits;
    $nibble = 0;
    if (0 == $num)
        return $num_to_bits[0];

    // Find last nibble
    $nibble = $num & 0xf;

    // Use pre-stored values to find count
    // in last nibble plus recursively add
    // remaining nibbles.
    return $num_to_bits[$nibble] +
           countSetBits($num >> 4);
}

/*
 * escribe la cadena enviada a stderr, añadiendo fecha
 * si insert_EOL, añade fecha y salto de línea
 * @param string $str cadena a imprimir
 * @param bool $insert_EOL por defecto pone fecha y salgo de línea, si se pasa false, imprime el texto sin modificar
 * 
 * @return bool siempre verdadero
 */
function logger(string $str, bool $insert_EOL = true) {

    $d = new DateTime();
    if ( $insert_EOL )
        $ret = $d->format("Y-m-d H:i:s.v") . $str . PHP_EOL;
    else
        $ret = $str;
    fwrite(STDERR, $ret);
    return true;
}

/**
 * @author Ovunc Tukenmez <ovunct@live.com>
 * version 1.0.1 - 10/26/2017
 *
 * @url https://github.com/ovunctukenmez/Combinations/blob/master/Combinations.php
 * This class is used to generate combinations with or without repetition allowed
 * as well as permutations with or without repetition allowed
 */
class Combinations
{
    private $_elements = array();

    public function __construct(array $elements)
    {
        $this->setElements($elements);
    }

    public function setElements(array $elements)
    {
        $this->_elements = array_values($elements);
    }

    public function getCombinations(int $length, bool $with_repetition = false)
    {
        $combinations = array();

        foreach ($this->x_calculateCombinations($length, $with_repetition) as $value) {
            $combinations[] = $value;
        }

        return $combinations;
    }

    public function getPermutations(int $length, bool $with_repetition = false)
    {
        $permutations = array();

        foreach ($this->x_calculatePermutations($length, $with_repetition) as $value) {
            $permutations[] = $value;
        }

        return $permutations;
    }

    private function x_calculateCombinations(int $length, bool $with_repetition = false, int $position = 0, array $elements = array())
    {

        $items_count = count($this->_elements);

        for ($i = $position; $i < $items_count; $i++) {

            $elements[] = $this->_elements[$i];

            if (count($elements) == $length) {
                yield $elements;
            } else {
                foreach ($this->x_calculateCombinations($length, $with_repetition, ($with_repetition == true ? $i : $i + 1), $elements) as $value2) {
                    yield $value2;
                }
            }

            array_pop($elements);
        }
    }

    private function x_calculatePermutations(int $length, bool $with_repetition = false, array $elements = array(), array $keys = array())
    {

        foreach ($this->_elements as $key => $value) {

            if ($with_repetition == false) {
                if (in_array($key, $keys)) {
                    continue;
                }
            }

            $keys[] = $key;
            $elements[] = $value;

            if (count($elements) == $length) {
                yield $elements;
            } else {
                foreach ($this->x_calculatePermutations($length, $with_repetition, $elements, $keys) as $value2) {
                    yield $value2;
                }
            }

            array_pop($keys);
            array_pop($elements);
        }
    }
}

/*
 * Convierte un número de segundos en una cadena legible para humanos con unidades
 *
 */
function timer_unidades(float $t)
{

    $t = floor($t);
    $unidad = "";
    if ($t > 24 * 60 * 60) {
        $format = "d H:i:s";
    } else if ($t > 60 * 60) {
        $format = "H:i:s";
    } else if ($t > 120) {
        $format = "i:s";
    } else {
        $format = "U";
        if ($t > 1) {
            $unidad = "segundos";
        } else {
            $unidad = "segundo";
        }
    }

    $timer_string = date($format, (int)floor($t));

    return $timer_string . " " . $unidad;
}