<?php

namespace App\Utilities;

use DateTime;
use App\Helpers\FechaHelper;
use App\Constants\GradosValidos;

class ValidacionesInscripcion {

    // Reglas de negocio 
    /* 
        Los niños que van a primero como mínimo deben cumplir 3 años en el año en curso para entrar a primero, y no pueden cumplir 4 en el presente año
        Los niños que van a segundo tienen que tener 4 o como mínimo cumplir los 4 en el año en curso
        Los niños que van a tercero tienen que tener 5 o como mímino cumplirlos en el año en curso. Si llegan a cumplir 6 no pueden entrar al kinder.
    */

    public static function ClasificarAlumnosPorGrado(array $alumnoValido){

        $fechaNac = $alumnoValido["FechaNacimiento"];
        $fechaNac = DateTime::createFromFormat("d/m/Y", $fechaNac);
        $hoy = new DateTime("now");
        $currentYear = date("Y");
        $finYear = new DateTime("$currentYear-12-31");

        $fechaHelper = new FechaHelper();

        $edadActual = $fechaHelper->ObtenerDiferenciaFechas($fechaNac, $hoy);
        $edadAlFinalAnio = $fechaHelper->ObtenerDiferenciaFechas($fechaNac, $finYear);

        switch(true){

            case ($edadActual->y >= 2 && $edadAlFinalAnio->y >= 3 && $edadAlFinalAnio->y < 4):
                return GradosValidos::GRADOS["PRIMER_GRADO"];
            

            case ($edadActual->y >= 3 && $edadAlFinalAnio->y >= 4 && $edadAlFinalAnio->y < 5):
                return GradosValidos::GRADOS["SEGUNDO_GRADO"];

            case ($edadActual->y >= 4 && $edadAlFinalAnio->y >= 5 && $edadAlFinalAnio->y < 6):
                return GradosValidos::GRADOS["TERCER_GRADO"];

            default:
                return GradosValidos::GRADOS["FUERA_RANGO_EDAD"];

        }

    }


}