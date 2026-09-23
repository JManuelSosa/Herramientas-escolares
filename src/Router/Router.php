<?php
namespace App\Router;

use App\Controllers\ExcelController;
use App\Controllers\AgendaController;

class Router
{
    private string $basePath;

    public function __construct() {
        $rawDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $this->basePath = preg_replace('#/public$#', '', $rawDir);
    }

    public function handleRequest(string $uri, string $method)
    {
        $path = $this->getPath($uri);
        $method = strtoupper($method);
        $match = strtolower($path);

        if ($match === "alumnos" || $match === '') {
            require_once __DIR__ . '/../views/formularioExcel.php';

        } elseif ($match === 'alumnos/procesar-excel' && $method === 'POST') {
            $controller = new ExcelController();
            $controller->procesarExcel($_FILES['Excel'] ?? []);

        } elseif ($match === "alumnos/agenda") {
            require_once __DIR__ . '/../views/AgendaView.php';

        } elseif ($match === 'alumnos/agenda/generar-agenda' && $method === 'POST') {
            $controller = new AgendaController();
            $controller->CrearAgenda($_POST);
        }
        else {
            http_response_code(404);
            echo $path;
            echo "<br>";
            echo "404 - Página no encontrada";
        }
    }

    private function getPath(string $uri):string {

        $parsedPath = parse_url($uri, PHP_URL_PATH);

        if(!empty($this->basePath) && $this->basePath !== '/' && str_starts_with($parsedPath, $this->basePath)){
            $parsedPath = substr($parsedPath, strlen($this->basePath));
        }

        if(str_starts_with($parsedPath, '/public')) $parsedPath = substr($parsedPath, strlen('/public'));
        
        $path = trim($parsedPath, '/');
        return $path;
    }   
}
