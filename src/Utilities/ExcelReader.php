<?php
namespace App\Utilities;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelReader
{
    public function ObtenerDataPreinscritos(string $ruta)
    {
        $spreadsheet = IOFactory::load($ruta);
        $hoja = $spreadsheet->getActiveSheet();
        
        //& Obtenemos los encabezados que nos interesa buscar.
        $encabezadosEsperados = ['Alumno', 'CURP'];

        $encabezadosData = $this->ObtenerEncabezadosYFilaInicioTabla($hoja, $encabezadosEsperados);
        
        if($encabezadosData === []) return null;

        $filaInicioTabla = $encabezadosData['filaInicioTabla'];
        $encabezados = $encabezadosData['encabezados'];

        $camposData = $hoja->getRowIterator($filaInicioTabla + 1);

        $informacionAlumnos = $this->ObtenerInformacionTabla($camposData, $encabezados);
        
        return $informacionAlumnos;
    }

    private function ObtenerEncabezadosYFilaInicioTabla(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $hojaCalculo, array $encabezadosABuscar): array{

        $filaInicioTabla = null;
        $encabezados = [];

        //& Buscar la fila con los encabezados esperados.
        foreach($hojaCalculo->getRowIterator() as $fila){

            $celdaIterator = $fila->getCellIterator();
            $celdaIterator->setIterateOnlyExistingCells(true);

            $valoresFila = [];

            //? Guardamos los valores de todas las celdas disponibles en la fila
            foreach($celdaIterator as $celda){
                $valorCelda = trim((string)$celda->getValue());
                $valoresFila[] = $valorCelda;
            }

            $interseccion = array_intersect($encabezadosABuscar, $valoresFila);

            if(count($interseccion) === count($encabezadosABuscar)){
                
                //& Guardamos la posicion de la fila donde esta la información que queremos
                $filaInicioTabla = $fila->getRowIndex();

                //& Obtenemos las columnas donde se encuentran los encabezados.
                foreach($celdaIterator as $celda){

                    $columna = $celda->getColumn();
                    $valorCelda = trim((string)$celda->getValue());

                    if(in_array($valorCelda, $encabezadosABuscar)){
                        $encabezados[$columna] = $valorCelda;
                    }
                }

                break;
            }
        }

        if($filaInicioTabla === null){
            return [];
        }

        return [
            "filaInicioTabla" => $filaInicioTabla,
            "encabezados" => $encabezados 
        ];
    }

    private function ObtenerInformacionTabla(\PhpOffice\PhpSpreadsheet\Worksheet\RowIterator $seccionABuscar, array $encabezados): array{

        $datos = [];

        foreach($seccionABuscar as $fila){

            $filaDatos = [];

            foreach($fila->getCellIterator() as $celda){

                $columna = $celda->getColumn();
                if(!ISSET($encabezados[$columna])){
                    continue;
                }

                $clave = $encabezados[$columna];
                $valor = $celda->getValue();
                $filaDatos[$clave] = $valor;
            }

            // Evitamos filas completamente vacías
            if (array_filter($filaDatos)) {
                $datos[] = $filaDatos;
            }
        }


        return $datos;
    }



}
