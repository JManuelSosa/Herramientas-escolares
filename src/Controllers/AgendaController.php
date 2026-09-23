<?php
namespace App\Controllers;

//* Servicios
use App\Services\AgendaDatesService;
use App\Services\AgendaService;

class AgendaController {

    public function CrearAgenda(array $post){

        $cycle = AgendaDatesService::GetSchoolarYear($post);
        $monthsWithDays = AgendaDatesService::GetAgendData($post);

        $Service = new AgendaService();
        $Service->CreateExcelAgend($monthsWithDays, $cycle);
    }

}