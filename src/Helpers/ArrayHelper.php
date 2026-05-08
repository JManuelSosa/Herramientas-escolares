<?php
namespace App\Helpers;
use InvalidArgumentException;

class ArrayHelper{

    /**
     * Filtra un array usando una función callback
     * 
     * @param array $arrayAFiltrar Array a filtrar
     * @param callable $callbackFiltro Función que devuelve true para mantener el elemento
     * @return array Nuevo array filtrado y reindexado
     */

    public static function arrayFilter(array $arrayAFiltrar, callable $callbackFiltro): array{

        if (!is_callable($callbackFiltro)) {
            throw new InvalidArgumentException('El callback debe ser invocable');
        }

        return array_values(array_filter($arrayAFiltrar, $callbackFiltro));
    }


    public static function groupBy(array $array, callable|string $criterio): array
    {
        return array_reduce($array, function($resultado, $elemento) use ($criterio) {
            $clave = is_string($criterio)
                ? ( (is_object($elemento)) ? $elemento->$criterio : $elemento[$criterio] )
                : $criterio($elemento);
            
            $resultado[$clave][] = $elemento;
            return $resultado;
        }, []);
    }

    public static function OrdenarAlfabeticamente(array $array, string $parametro): array{
        usort($array, function($a, $b) use($parametro) {
            return strcasecmp($a[$parametro], $b[$parametro]);
        });

        return $array;
    }




}
