<?php

namespace App\Helpers;
use DateTimeImmutable;

class CurpHelper {

    private const SEXO_CURP = [ "X" => "No binario", "H" => "Hombre", "M" => "Mujer"];
    private const CODIGOS_ENTIDADES_FEDERATIVAS = [
        "AS" => "Aguascalientes",
        "BC" => "Baja California",
        "BS" => "Baja California Sur",
        "CC" => "Campeche",
        "CL" => "Coahuila",
        "CM" => "Colima",
        "CS" => "Chiapas",
        "CH" => "Chihuahua",
        "DF" => "Ciudad de México",
        "DG" => "Durango",
        "GT" => "Guanajato",
        "GR" => "Guerrero",
        "HG" => "Hidalgo",
        "JC" => "Jalisco",
        "MC" => "Estado de México",
        "MN" => "Michoacán",
        "MS" => "Morelos",
        "NT" => "Nayarit",
        "NL" => "Nuevo León",
        "OC" => "Oaxaca",
        "PL" => "Puebla",
        "QT" => "Querétaro",
        "QR" => "Quintana Roo",
        "SP" => "San Luis Potosí",
        "SL" => "Sinaloa",
        "SR" => "Sonora",
        "TC" => "Tabasco",
        "TS" => "Tamaulipas",
        "TL" => "Tlaxcala",
        "VZ" => "Veracruz",
        "YN" => "Yucatán",
        "ZS" => "Zacatecas",
        "NE" => "Nacido en el extranjero"
    ];

    public function validarCURP(string $Curp): bool {
        $curp = strtoupper(trim($Curp)); // Limpieza
    
        $regex = '/^[A-Z]{4}[0-9]{6}[HM]{1}[A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[0-9A-Z]{1}[0-9]{1}$/';
    
        return preg_match($regex, $curp);
    }

    public function ObtenerFechaDeNacimiento(string $Curp): ?DateTimeImmutable {
        
        $curp = strtoupper(trim($Curp)); // Limpieza
        
        if(strlen($curp) !== 18){
            return null;
        }

        $year = substr($curp, 4, 2);
        $month = substr($curp, 6, 2);
        $day = substr($curp, 8, 2);

        $siglo = ($year <= date('y')) ? '20' : '19';

        $stringFechaNac = "$siglo"."$year-$month-$day";

        $fechaNacimiento = DateTimeImmutable::createFromFormat('Y-m-d', $stringFechaNac);

        if($fechaNacimiento === false){
            return null;
        }

        return $fechaNacimiento;
    }

    public function ObtenerSexo(string $Curp): ?string{

        $curp = strtoupper(trim($Curp)); // Limpieza

        if(strlen($curp) !== 18){
            return null;
        }

        $sexo = substr($curp, 10, 1);
        $sexo = strtoupper($sexo);

        return self::SEXO_CURP[$sexo] ?? null;
    }

    public function ObtenerEntidadFederativa(string $Curp): ?string{

        $curp = strtoupper(trim($Curp)); // Limpieza

        if(strlen($curp) !== 18){
            return null;
        }

        $entidadFederativa = substr($curp, 11, 2);
        $entidadFederativa = strtoupper($entidadFederativa);

        return self::CODIGOS_ENTIDADES_FEDERATIVAS[$entidadFederativa] ?? null;
    }







}
