<?php
namespace App\Helpers;

use DateInterval;
use DateTimeInterface;

class FechaHelper {

    public function ObtenerDiferenciaFechas(DateTimeInterface $fechaInicio, DateTimeInterface $fechaFin): ?DateInterval{

        $diferencia = $fechaInicio->diff($fechaFin);

        if($diferencia->invert){
            return null;
        }

        return $diferencia;
    }


}