<?php
namespace App\Services;
use DateInterval;
use DatePeriod;

//* Clases nativas PHP
use DateTime;

//* Constantes
use App\Constants\Meses;

class AgendaDatesService{

    private static $days = Meses::SPANISH_DAYS;
    private static $months = Meses::SPANISH_MONTHS;

    public static function GetAgendData(array $request): array{

        $period = self::GetAnualPeriod(self::GetStartDate($request));
        $data = [];

        $plantillaWeek = ["Monday" => null, "Tuesday" => null, "Wednesday" => null, "Thursday" => null, "Friday" => null];
        $week = [...$plantillaWeek];

        $currentMonth = null;

        foreach($period as $date){
            
            $currentDayData = [
                'date' => $date->format('Y-m-d'),
                'dayNumber' => $date->format('d'),
                'monthNumber' => $date->format('m'),
                'year' => $date->format('Y'),
                'dayName' => $date->format('l'),
                'monthName' => self::$months[$date->format('F')],
                'monthWithName' => self::$months[$date->format('F')] . ' ' . $date->format('Y'),
            ];

            $esFinDeSemana = ($currentDayData['dayName'] === 'Saturday' || $currentDayData['dayName'] === 'Sunday');

            if($esFinDeSemana) continue;


            //? Para manejar semanas cortadas entre meses
            $existeCambioDeMes = ($currentMonth !== $currentDayData['monthWithName']);
            if ($existeCambioDeMes) {
    
                if ($currentMonth !== null && !empty(array_filter($week))) {
                    // Guardar semana incompleta del mes anterior
                    $data[$currentMonth][] = array_filter($week);
                    // Con array_filter eliminamos los días de la semana restantes con null, ya que esos pertenecen al sig. mes
                }

                //? Cambiamos el mes actual y reiniciamos la plantilla de días
                $currentMonth = $currentDayData['monthWithName'];
                $week = [...$plantillaWeek];
                
                if (!isset($data[$currentMonth])) {
                    $data[$currentMonth] = [];
                }
            }

            //? Asignar el dia a su posición semanal
            $dayName = $currentDayData['dayName'];
            $week[$dayName] = $currentDayData['dayNumber'];

            //? Para meter el arreglo de la semana dentro de su mes correspondiente siempre que exista el viernes dentro de la semana
            if($week["Friday"] !== null){
                $data[$currentMonth][] = $week;
                $week = [...$plantillaWeek];
            }
            
        }

        // Guardar última semana incompleta
        if (!empty(array_filter($week))) {
            $data[$currentMonth][] = array_filter($week);
        }

        return $data;
    }

    public static function GetSchoolarYear(array $post): string{

        $startDate = (int) $post['startYear'];
        $endDate = $startDate + 1;

        return "$startDate-$endDate";
    }


    private static function GetStartDate(array $post): string {

        $year = $post['startYear'];
        $month = $post['startMonth'];
        $day = '01';

        //? Damos el formato correcto al mes "mm"
        if(strlen($month) !== 2){
            $month = '0'.$month;
        }

        return "$year-$month-$day";
    }

    private static function GetAnualPeriod(string $fecha): DatePeriod {
        $startDate = new DateTime($fecha);
        $endDate = (new DateTime($fecha))->modify('+1 year');

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($startDate, $interval, $endDate);
        return $period;
    }

}