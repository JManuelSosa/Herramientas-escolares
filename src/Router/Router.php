<?php
namespace App\Router;

use App\Controllers\ExcelController;
use App\Controllers\AgendaController;

class Router
{
    public function handleRequest($uri, $method)
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if ($path === "/Alumnos/") {
            require_once __DIR__ . '/../views/formularioExcel.php';

        } elseif ($path === '/Alumnos/procesar-excel' && $method === 'POST') {
            $controller = new ExcelController();
            $controller->procesarExcel($_FILES['Excel'] ?? []);

        } elseif ($path === "/Alumnos/Agenda/") {
            require_once __DIR__ . '/../views/AgendaView.php';

        } elseif ($path === '/Alumnos/Agenda/generar-agenda' && $method === 'POST') {
            $controller = new AgendaController();
            $controller->CrearAgenda($_POST);
        }
        else {
            http_response_code(404);
            echo "404 - Página no encontrada";
        }
    }
}
