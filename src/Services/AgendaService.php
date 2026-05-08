<?php
namespace App\Services;

//* Librerias externas
use PhpOffice\PhpSpreadsheet\SpreadSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

//* Helpers
use App\Helpers\ExcelWriter;

//* Constantes 
use App\Constants\EstilosSpreadsheet;


class AgendaService {

    public function CreateExcelAgend(array $dataDates, string $cycle): void{

        //* Creamos el Excel y seteamos sus propiedades
        $excel = new SpreadSheet();
        $excel->removeSheetByIndex(0);
        $nombreArchivo = "Agenda Ciclo Escolar $cycle";

        $excel->getProperties()->setCreator("Vizard LQ4");
        $excel->getProperties()->setTitle($nombreArchivo);

        //* Estilos por defecto
        $excel->getDefaultStyle()->getFont()->setName("Yu Gothic UI");

        foreach($dataDates as $month => $weeks){
            $this->CreateMonthSheet($excel, $month, $weeks);
        }

        $this->CreateNotesSheet($excel);

        // & Hacer que aparezca el mensaje de descarga cuando se cree el archivo
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$nombreArchivo.xlsx\"");
        header('Cache-Control: max-age=0');

        // ? Con los headers de descarga
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $writer->save('php://output');

        exit;


    }

    private function CreateMonthSheet(SpreadSheet $documento, string $nombreHoja, array $data): void {
        $hoja = ExcelWriter::CrearHoja($documento, $nombreHoja);
        
        //* Ajustar el tamaño y orientación del papel así como el ancho de las columnas
        $hoja->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LEGAL)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $hoja->getDefaultColumnDimension()->setWidth(30.1);
        $hoja->getPageMargins()->setTop(0)->setBottom(0);

        $filaInicio = 3; 

        //* Ajustar el alto de los encabezados
        $hoja->getRowDimension($filaInicio)->setRowHeight(ExcelWriter::GetHeighRowInPixels(27)); //Encabezado del mes
        ExcelWriter::CombinarCeldas($hoja, "A$filaInicio:E$filaInicio");
        $hoja->getRowDimension($filaInicio + 1)->setRowHeight(ExcelWriter::GetHeighRowInPixels(20)); //Encabezado de los dias

        //* Aplicar estilos a los headers
        $hoja->getStyle("A$filaInicio")->applyFromArray(EstilosSpreadsheet::ESTILOS_CELDAS["HeaderMesCalendario"]);
        $hoja->getStyle("A". $filaInicio + 1 .":"."E". $filaInicio + 1)->applyFromArray(EstilosSpreadsheet::ESTILOS_CELDAS["HeaderDaysCalendario"]);

        //* Obtener la información de los días en el calendario
        $dataExcelFormat = $this->FormatDataForExcel($data, $nombreHoja);
        $hoja->fromArray($dataExcelFormat, null, "A$filaInicio");

        //* Ajustar el tamaño de las demas celdas
        $conteoFilasCalendario = count($dataExcelFormat);

        $inicioCalendario = $filaInicio + 2;

        for ($row = $inicioCalendario; $row <= $conteoFilasCalendario + 2; $row++) {
            if(($conteoFilasCalendario - 2) === 5){
                $hoja->getRowDimension($row)->setRowHeight(ExcelWriter::GetHeighRowInPixels(122));
            }
            elseif(($conteoFilasCalendario - 2) === 4){
                $hoja->getRowDimension($row)->setRowHeight(ExcelWriter::GetHeighRowInPixels(150));
            }
            
        }

        //* Aplicar estilos a las celdas de los días.
        $rowFinal = $conteoFilasCalendario + 2;        
        $hoja->getStyle("A". $filaInicio + 2 .":E$rowFinal")->applyFromArray(EstilosSpreadsheet::ESTILOS_CELDAS["DiasCalendario"]);
    }

    private function CreateNotesSheet(SpreadSheet $documento): void {

        $hoja = ExcelWriter::CrearHoja($documento, 'Notas');

        //* Ajustar el tamaño y orientación del papel así como el ancho de las columnas
        $hoja->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LEGAL)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $hoja->getPageMargins()->setTop(0);
        $hoja->getDefaultColumnDimension()->setWidth(30.5);

        //* Creamos el titulo de notas
        $rangoEncabezadoNotes = "B2:D2";
        ExcelWriter::CrearCeldaCombinada($hoja, $rangoEncabezadoNotes, 'Notas', EstilosSpreadsheet::ESTILOS_CELDAS["NotasTitle"]);
        
        //* Ajustamos la altura de todas las filas de las notas
        ExcelWriter::SetAlturaFilas($hoja, 4, 22, ExcelWriter::GetHeighRowInPixels(28));

        //* Creamos los subtitulos de cada sección 
        $subtitle1Range = "A3:B4";
        $subtitle2Range = "D3:E4";

        ExcelWriter::CrearCeldaCombinada($hoja, $subtitle1Range, 'Actividades realizadas en la semana', EstilosSpreadsheet::ESTILOS_CELDAS["NotasSubtitle"]);
        ExcelWriter::CrearCeldaCombinada($hoja, $subtitle2Range, 'Actividades pendientes', EstilosSpreadsheet::ESTILOS_CELDAS["NotasSubtitle"]);

        //* Creamos las secciones de fechas 
        // ~ Sección 1
        $filaSeccion1 = 6;
        $columnaSeccion1 = 1;
        $this->CreateSectionNotes($hoja, $filaSeccion1, $columnaSeccion1, 6);

        //~ Sección 2
        $columnaSeccion2 = $columnaSeccion1 + 3;
        $this->CreateSectionNotes($hoja, $filaSeccion1, $columnaSeccion2, 6);
        
        //~ Sección 3 
        $filaSeccion3 = $filaSeccion1 + 9;
        $this->CreateSectionNotes($hoja, $filaSeccion3, $columnaSeccion1, 6);

        //~ Sección 4
        $this->CreateSectionNotes($hoja, $filaSeccion3, $columnaSeccion2, 6);

    }

    private function FormatDataForExcel(array $monthData, string $nameMonth): array {
        $excelData = [];

        //* Crear encabezado 
        $excelData[] = ["$nameMonth"];

        //* Crear columnas 
        $excelData[] = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

        foreach($monthData as $week){
            $excelData[] = [
                    $week['Monday'] ?? '',
                    $week['Tuesday'] ?? '',
                    $week['Wednesday'] ?? '',
                    $week['Thursday'] ?? '',
                    $week['Friday'] ?? ''
            ];
        }

        return $excelData;
    }

    private function CreateSectionNotes(Worksheet $hoja, int $filaInicial, int $columnaInicial, int $sizeSection){

        //* Creamos y seteamos el valor de fecha
        $columnaInicial++;
        $letraCol = Coordinate::stringFromColumnIndex($columnaInicial);

        $celdaFecha = "$letraCol"."$filaInicial";

        ExcelWriter::SetValue($hoja, $celdaFecha, 'Fecha:_______________________');
        $hoja->getStyle($celdaFecha)->getFont()->setSize(14);

        $startCol = Coordinate::stringFromColumnIndex($columnaInicial - 1);
        $endCol = Coordinate::stringFromColumnIndex($columnaInicial);

        $endRow = $filaInicial + $sizeSection;

        $sectionBorders = [
            'borders' => [
                'bottom' => ['borderStyle' => 'thin', 'color' => ['rgb' => '000000']],
            ],
        ];

        for($fila = $filaInicial + 1; $fila <= $endRow; $fila++){
            $rango = "$startCol"."$fila".":"."$endCol"."$fila";
            $hoja->getStyle($rango)->applyFromArray($sectionBorders);
        }

    }



}