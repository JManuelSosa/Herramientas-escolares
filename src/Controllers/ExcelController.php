<?php
namespace App\Controllers;

//* Librerias externas
use PhpOffice\PhpSpreadsheet\SpreadSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

//* Utilidades propias
use App\Utilities\ExcelReader;
use App\Utilities\ValidacionesInscripcion;

//* Helpers propios
use App\Helpers\ValidarArchivo;
use App\Helpers\CurpHelper;
use App\Helpers\FechaHelper;
use App\Helpers\ArrayHelper;
use App\Helpers\ExcelWriter;


//* Constantes 
use App\Constants\EstilosSpreadsheet;
use App\Constants\GradosValidos;

//* Clases nativas PHP
use DateTime;

class ExcelController
{
    public function procesarExcel(array $archivo)
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            echo 'Error al subir el archivo: ' . $this->getUploadError($archivo['error']);
            return;
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            echo 'El archivo no fue subido correctamente';
            return;
        }

        $excelValidator = new ValidarArchivo();
        $elArchivoEsUnExcel = $excelValidator->ValidarArchivoExcel($archivo);

        if(!$elArchivoEsUnExcel){
            echo 'Nope, no enviaste un Excel';
            return;
        }

        $rutaTemporal = $archivo['tmp_name'];
        $nombreOriginal = $archivo['name'];

        $uploadDir = __DIR__ . '/../../Uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $rutaDestino = $uploadDir.basename($nombreOriginal);

        move_uploaded_file($rutaTemporal, $rutaDestino);

        return $this->leerExcel($rutaDestino);
    }


    private function leerExcel(string $ExcelValido){

        $excelReader = new ExcelReader();
        $dataExcel = $excelReader->ObtenerDataPreinscritos($ExcelValido);
        
        // En caso de que la data del Excel no regrese nada significa que no se encontró ni los encabezados ni la data necesaria
        if($dataExcel === null){
            echo 'No enviaste el archivo correcto';
            unlink($ExcelValido);
            return;
        }

        unlink($ExcelValido);
        return $this->ProcesarInformacionAlumnosPreinscritos($dataExcel);
    }

    private function ProcesarInformacionAlumnosPreinscritos(array $dataPreinscritos){
        
        $informacionPreinscritos = [];
        $curpHelper = new CurpHelper();
        $fechaHelper = new FechaHelper();
        $hoy = new DateTime('now');

        foreach($dataPreinscritos as $alumno){
            $alumnoActual = [];

            //& Datos del alumno
            $nombreActual = $alumno["Alumno"];
            $curpActual = $alumno["CURP"];

            $curpEsValida = $curpHelper->validarCURP($curpActual);

            if(!$curpEsValida){

                $alumnoActual["Nombre"] = $nombreActual;
                $alumnoActual["Curp"] = $curpActual;
                $alumnoActual["CurpValida"] = false;

                $informacionPreinscritos[] = $alumnoActual;
                continue;
            }

            $fechaNacimiento = $curpHelper->ObtenerFechaDeNacimiento($curpActual);
            $edadActual = $fechaHelper->ObtenerDiferenciaFechas($fechaNacimiento, $hoy);

            //& Procesar la infomación deseada
            $alumnoActual["Nombre"] = $nombreActual;
            $alumnoActual["Curp"] = $curpActual;
            $alumnoActual["FechaNacimiento"] = $fechaNacimiento->format('d/m/Y');
            $alumnoActual["Edad"]["Year"] = $edadActual->y;
            $alumnoActual["Edad"]["Month"] = $edadActual->m;
            $alumnoActual["Sexo"] = $curpHelper->ObtenerSexo($curpActual);
            $alumnoActual["EntidadFederativa"] = $curpHelper->ObtenerEntidadFederativa($curpActual);
            $alumnoActual["CurpValida"] = true;

            //& Guardar Alumno
            $informacionPreinscritos[] = $alumnoActual;
        }

        return $this->CrearExcel($informacionPreinscritos);

    }

    private function CrearExcel(array $dataAlumnos){

        //* Se preparan los datos para crear los apartados del Excel
        $curpsValidas = ArrayHelper::arrayFilter($dataAlumnos, function($alumno){
            return $alumno["CurpValida"] === true;
        });

        $curpsInvalidas = ArrayHelper::arrayFilter($dataAlumnos, function($alumno){
            return $alumno["CurpValida"] === false;
        });

        $alumnosPorSexo = ArrayHelper::groupBy($curpsValidas, "Sexo");
        $alumnosPorEdad = ArrayHelper::groupBy($curpsValidas, function($element){
            return $element["Edad"]["Year"];
        });

        //* Ordenamos por edades de menor a mayor
        ksort($alumnosPorEdad);

        //* Separamos los alumnos por si entran dentro del rango de edad o no
        $alumnosValidosParaEntrar = [];
        $alumnosFueraDelRango = [];

        foreach($curpsValidas as $alumno){

            $alumno["Grado"] = ValidacionesInscripcion::ClasificarAlumnosPorGrado($alumno);

            if($alumno["Grado"] === GradosValidos::GRADOS["FUERA_RANGO_EDAD"]){
                $alumnosFueraDelRango[] = $alumno;
            }else{
                $alumnosValidosParaEntrar[] = $alumno;
            }
        }

        $alumnosPorGrado = ($alumnosValidosParaEntrar !== []) ? ArrayHelper::groupBy($alumnosValidosParaEntrar, "Grado") : null;
        $alumnosInvalidos = ($alumnosFueraDelRango !== []) ? ArrayHelper::groupBy($alumnosFueraDelRango, "Grado") : null;
        
        //& Generamos el Excel
        $excel = new SpreadSheet();
        $excel->removeSheetByIndex(0); //Quitar la hoja "Worksheet" que se genera por defecto
        $nombreArchivo = "Resumen de Preinscritos";

        $excel->getProperties()->setCreator("Vizard LQ4");
        $excel->getProperties()->setTitle($nombreArchivo);

        //& Modificar la fuente y el tamaño de letra
        $excel->getDefaultStyle()->getFont()->setName("Yu Gothic UI");
        $excel->getDefaultStyle()->getFont()->setSize(14);

        //* Crear una nueva hoja de trabajo y llenarla de acuerdo a la lógica necesaria 
        $hojaActiva = ExcelWriter::CrearYActivarHoja($excel, "Resumen");
        $this->CrearHojaResumen($hojaActiva, $curpsInvalidas, $alumnosPorSexo, $alumnosPorEdad);

        //* Para la hoja de detalles de alumnos por grado 
        $grados = [
            "Primer año" => GradosValidos::GRADOS["PRIMER_GRADO"],
            "Segundo año" => GradosValidos::GRADOS["SEGUNDO_GRADO"],
            "Tercer año" => GradosValidos::GRADOS["TERCER_GRADO"],
        ];
        
        foreach($grados as $nombreHoja => $grado){
            $hoja = ExcelWriter::CrearYActivarHoja($excel, $nombreHoja);
            
            if($alumnosPorGrado !== null){
                $this->CrearHojaDetalleGrado($hoja, $alumnosPorGrado[$grado], $grado);
            }
            else{
                $this->CrearHojaAlumnosVacios($hoja, $grado);
            }
        }
        
        //* Para la hoja de invalidos
        if($alumnosInvalidos !== null){
            $hoja = ExcelWriter::CrearYActivarHoja($excel, "Alumnos invalidos");
            $this->CrearHojaDetalleGrado($hoja, $alumnosInvalidos[GradosValidos::GRADOS["FUERA_RANGO_EDAD"]], GradosValidos::GRADOS["FUERA_RANGO_EDAD"]);
        }

        // & Hacer que aparezca el mensaje de descarga cuando se cree el archivo
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$nombreArchivo.xlsx\"");
        header('Cache-Control: max-age=0');

        // ? Con los headers de descarga
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $writer->save('php://output');

        exit;
    }

    private function CrearHojaResumen(Worksheet $hojaActiva, array $curpsInvalidas, array $alumnosPorSexo, array $alumnosPorEdad): void {

        //& Seteamos las columnas con las que vamos a trabajar para que se autoajusten a su contenido mas largo.
        for ($col = 'B'; $col <= 'G'; $col++) {
            $hojaActiva->getColumnDimension($col)->setAutoSize(true);
        }

        //& Establecer el encabezado del documento
        ExcelWriter::CrearCeldaCombinada($hojaActiva, "B2:G2", "Informacion del listado de alumnos", EstilosSpreadsheet::ESTILOS_CELDAS["Titulo"]);

        //& Encabezado de resumen 
        ExcelWriter::CrearCeldaCombinada($hojaActiva, "B4:G4", "Resumen de los datos procesados", EstilosSpreadsheet::ESTILOS_CELDAS["Resumen"]);

        //& Apartado para resumir y poner las CURPS invalidas
        ExcelWriter::CrearCeldaCombinada($hojaActiva, "B5:G5", "Alumnos con CURP inválida (Imposible de procesar datos)", EstilosSpreadsheet::ESTILOS_CELDAS["CURP_Invalida"]);

        $numeroFila = $hojaActiva->getHighestRow() + 1;

        if(count($curpsInvalidas) !== 0){

            foreach($curpsInvalidas as $dataNoProcesada){
                //* Celda alumno
                $celdaAlumno = "B$numeroFila:D$numeroFila";
                ExcelWriter::CrearCeldaCombinada($hojaActiva, $celdaAlumno, $dataNoProcesada["Nombre"], EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);

                //* Curp
                $celdaCurp = "E$numeroFila:G$numeroFila";
                ExcelWriter::CrearCeldaCombinada($hojaActiva, $celdaCurp, $dataNoProcesada["Curp"], EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);

                $numeroFila++;
            }

        }else{

            //* Deja una celda en blanco indicando que no hubo problema
            ExcelWriter::CrearCeldaCombinada($hojaActiva, "B6:G6", "Todas las CURPS enviadas fueron válidas", EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
            $numeroFila++;
        }

        //& Dejamos un nuevo espacio en blanco antes de continuar
        $numeroFila = $hojaActiva->getHighestRow() + 1; //Última fila con contenido
        $numeroFila++;

        //& Apartado para colocar el numero de alumnos por sexo
        $encAlumnPorSexo = "B$numeroFila:G$numeroFila";
        ExcelWriter::CrearCeldaCombinada($hojaActiva, $encAlumnPorSexo, "Numero de alumnos por sexo", EstilosSpreadsheet::ESTILOS_CELDAS["EncabezadoAlumnosSexo"]);

        //* Datos para agrupar alumnos por sexo
        $numeroFila = $hojaActiva->getHighestRow() + 1;

        foreach($alumnosPorSexo as $clave => $alumnos){
            //* Celdas de sexo
            $celdasSexo = "B$numeroFila:D$numeroFila";
            ExcelWriter::CrearCeldaCombinada($hojaActiva, $celdasSexo, $clave, EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);

            //* Celdas del contador de alumnos por sexo
            $celdasCountSexo = "E$numeroFila:G$numeroFila";
            ExcelWriter::CrearCeldaCombinada($hojaActiva, $celdasCountSexo, count($alumnos), EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
            
            $numeroFila++;
        }

        //& Apartado para poner el conteo de alumnos por edad 
        $numeroFila = $hojaActiva->getHighestRow() + 1;
        $numeroFila++;

        $celEncAlumPorEdad = "B$numeroFila:G$numeroFila";
        ExcelWriter::CrearCeldaCombinada($hojaActiva, $celEncAlumPorEdad, "Numero de Alumnos por edad", EstilosSpreadsheet::ESTILOS_CELDAS["EncabezadoAlumnosEdad"]);
        $numeroFila++;

        foreach($alumnosPorEdad as $clave => $alumnos){
            //* Celdas Edad
            $celdasYears = "B$numeroFila:D$numeroFila";
            ExcelWriter::CrearCeldaCombinada($hojaActiva, $celdasYears, "$clave años", EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);

            //* Celdas contador por edad
            $celdasCountEdad = "E$numeroFila:G$numeroFila";
            ExcelWriter::CrearCeldaCombinada($hojaActiva, $celdasCountEdad, count($alumnos), EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
            $numeroFila++;
        }

        //& Detalle de la información detallada de alumnos
        $numeroFila = $hojaActiva->getHighestRow() + 1;
        $numeroFila++;

        $celdasEncabezado = "B$numeroFila:G$numeroFila";
        ExcelWriter::CrearCeldaCombinada($hojaActiva, $celdasEncabezado, "Detalle de Alumnos Preinscritos", EstilosSpreadsheet::ESTILOS_CELDAS["EncabezadoDetalleAlumnos"]);

        $numeroFila++;


        foreach($alumnosPorEdad as $edad => $alumnos){

            $filaInicioDetalle = $numeroFila; //* Variable de control para poner estilo al listado de detalle

            $celdasEncabezadoEdad = "B$numeroFila:G$numeroFila";
            $celdaActivaEncEdad = "B$numeroFila";

            //* Creamos un encabezado por edad
            ExcelWriter::CombinarCeldas($hojaActiva, $celdasEncabezadoEdad);
            $hojaActiva->setCellValue($celdaActivaEncEdad, "Alumnos con $edad años");

            $numeroFila++; //? Añadimos una fila mas para seguir creando el formato

            $hojaActiva->setCellValue("B$numeroFila", "Nombre");
            $hojaActiva->setCellValue("C$numeroFila", "CURP");
            $hojaActiva->setCellValue("D$numeroFila", "Fecha Nacimiento");
            $hojaActiva->setCellValue("E$numeroFila", "Edad");
            $hojaActiva->setCellValue("F$numeroFila", "Sexo");
            $hojaActiva->setCellValue("G$numeroFila", "Estado Nacimiento");

            $numeroFila++;

            foreach($alumnos as $alumno){

                $hojaActiva->setCellValue("B$numeroFila", $alumno["Nombre"]);
                $hojaActiva->setCellValue("C$numeroFila", $alumno["Curp"]);
                $hojaActiva->setCellValue("D$numeroFila", $alumno["FechaNacimiento"]);
                $hojaActiva->setCellValue("E$numeroFila", $alumno["Edad"]["Year"]." años y ".$alumno["Edad"]["Month"]." meses");
                $hojaActiva->setCellValue("F$numeroFila", $alumno["Sexo"]);
                $hojaActiva->setCellValue("G$numeroFila", $alumno["EntidadFederativa"]);

                $numeroFila++;
            }

            //* Aplicamos el estilo a todo el rango de celdas
            $filaFinDetalle = $numeroFila;
            $filaFinDetalle = $filaFinDetalle - 1;
            $rangoDetalle = "B$filaInicioDetalle:G$filaFinDetalle";
            $hojaActiva->getStyle($rangoDetalle)->applyFromArray(EstilosSpreadsheet::ESTILOS_CELDAS["DetalleAlumno"]);
            $hojaActiva->getStyle($celdasEncabezadoEdad)->applyFromArray(EstilosSpreadsheet::ESTILOS_CELDAS["EncabezadoEdadDetalle"]);

            //* Dejamos un espacio en blanco
            $hojaActiva->getStyle("B$numeroFila:G$numeroFila")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
            $numeroFila++;
        }

        $hojaActiva->getPageSetup()->setFitToPage(true);
        $hojaActiva->getPageSetup()->setFitToWidth(1);
        $hojaActiva->getPageSetup()->setFitToHeight(0);
    }

    private function CrearHojaDetalleGrado(Worksheet $hoja, array $alumnos, string $grado): void{

        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $hoja->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        //& Encabezado general
        if($grado !== GradosValidos::GRADOS["FUERA_RANGO_EDAD"]){
            ExcelWriter::CrearCeldaCombinada($hoja, "A2:J2", "Detalle de alumnos preinscritos para ".GradosValidos::TITULOS_GRADO[$grado]. "grado", EstilosSpreadsheet::ESTILOS_CELDAS["Titulo"]);
        }
        else
        {
            ExcelWriter::CrearCeldaCombinada($hoja, "A2:J2", "Detalle de alumnos preinscritos fuera del rango de edad", EstilosSpreadsheet::ESTILOS_CELDAS["Titulo"]);
            $hoja->getStyle("A2")->getFont()->setSize(16);
        }
        
        $currentFila = $hoja->getHighestRow();
        ExcelWriter::AgregarFilasEnBlanco($currentFila, 2);

        //& Conteo de alumnos por sexo
        ExcelWriter::CrearCeldaCombinada($hoja, "D$currentFila:G$currentFila", "Conteo de alumnos por sexo", EstilosSpreadsheet::ESTILOS_CELDAS["EncabezadoDetalleGrado"]);
        ExcelWriter::AgregarFilasEnBlanco($currentFila, 1);

        ExcelWriter::CrearCeldaCombinada($hoja, "D$currentFila:E$currentFila", "Sexo", EstilosSpreadsheet::ESTILOS_CELDAS["SubencabezadoDetalleGrado"]);
        ExcelWriter::CrearCeldaCombinada($hoja, "F$currentFila:G$currentFila", "Total", EstilosSpreadsheet::ESTILOS_CELDAS["SubencabezadoDetalleGrado"]);
        ExcelWriter::AgregarFilasEnBlanco($currentFila, 1);

        $gruposPorSexo = ArrayHelper::groupBy($alumnos, "Sexo");

        foreach($gruposPorSexo as $sexo => $alumnos){
            //* Sexos totales
            ExcelWriter::CrearCeldaCombinada($hoja, "D$currentFila:E$currentFila", $sexo, EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
            //* Conteo
            ExcelWriter::CrearCeldaCombinada($hoja, "F$currentFila:G$currentFila", count($alumnos), EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
            ExcelWriter::AgregarFilasEnBlanco($currentFila, 1);
        } 

        ExcelWriter::AgregarFilasEnBlanco($currentFila, 2);

        //& Detalle de alumnos preinscritos

        //* 1. Ordenar alfabeticamente el arreglo por el nombre (insensible a mayusculas y minusculas por strcasecmp)
        $grupos = [];
        foreach($gruposPorSexo as $sexo => $grupoSexo){
            $alumnosOrdenadosAlfabeticamente = ArrayHelper::OrdenarAlfabeticamente($grupoSexo, "Nombre");
            $grupos[$sexo] = $alumnosOrdenadosAlfabeticamente;
        }

        //* Crear las tablas de detalle alumnos
        $titleDetalleAlumnos = ["Hombre" => "masculinos", "Mujer" => "femeninos", "No binario" => "no binarios"];

        foreach($grupos as $sexo => $alumnos){ 
            //* Encabezado
            if($grado !== GradosValidos::GRADOS["FUERA_RANGO_EDAD"]){
                ExcelWriter::CrearCeldaCombinada($hoja, "A$currentFila:J$currentFila", "Alumnos preinscritos $titleDetalleAlumnos[$sexo]", EstilosSpreadsheet::ESTILOS_CELDAS["EncabezadoDetalleGrado"]);
            }else{
                ExcelWriter::CrearCeldaCombinada($hoja, "A$currentFila:J$currentFila", "Alumnos preinscritos $titleDetalleAlumnos[$sexo] no válidos", EstilosSpreadsheet::ESTILOS_CELDAS["EncabezadoDetalleGrado"]);
            }
            
            ExcelWriter::AgregarFilasEnBlanco($currentFila, 1);

            //* Encabezados de datos
            ExcelWriter::CrearCeldaCombinada($hoja, "A$currentFila:B$currentFila", "Nombre", EstilosSpreadsheet::ESTILOS_CELDAS["SubencabezadoDetalleGrado"]);
            $hoja->getStyle("A$currentFila")->getFont()->setSize(12);

            ExcelWriter::CrearCeldaCombinada($hoja, "C$currentFila:D$currentFila", "CURP", EstilosSpreadsheet::ESTILOS_CELDAS["SubencabezadoDetalleGrado"]);
            $hoja->getStyle("C$currentFila")->getFont()->setSize(12);

            ExcelWriter::CrearCeldaCombinada($hoja, "E$currentFila:F$currentFila", "Fecha de nacimiento", EstilosSpreadsheet::ESTILOS_CELDAS["SubencabezadoDetalleGrado"]);
            $hoja->getStyle("E$currentFila")->getFont()->setSize(12);

            ExcelWriter::CrearCeldaCombinada($hoja, "G$currentFila:H$currentFila", "Edad actual", EstilosSpreadsheet::ESTILOS_CELDAS["SubencabezadoDetalleGrado"]);
            $hoja->getStyle("G$currentFila")->getFont()->setSize(12);

            ExcelWriter::CrearCeldaCombinada($hoja, "I$currentFila:J$currentFila", "Edad al 31 de diciembre", EstilosSpreadsheet::ESTILOS_CELDAS["SubencabezadoDetalleGrado"]);
            $hoja->getStyle("I$currentFila")->getFont()->setSize(12);

            ExcelWriter::AgregarFilasEnBlanco($currentFila, 1);

            foreach($alumnos as $alumno){
                //* Rellenamos la información del alumno
                ExcelWriter::CrearCeldaCombinada($hoja, "A$currentFila:B$currentFila", $alumno["Nombre"], EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
                $hoja->getStyle("A$currentFila")->getFont()->setSize(11);

                ExcelWriter::CrearCeldaCombinada($hoja, "C$currentFila:D$currentFila", $alumno["Curp"], EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
                $hoja->getStyle("C$currentFila")->getFont()->setSize(11);

                ExcelWriter::CrearCeldaCombinada($hoja, "E$currentFila:F$currentFila", $alumno["FechaNacimiento"], EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
                $hoja->getStyle("E$currentFila")->getFont()->setSize(11);

                // Edad actual
                $years = $alumno["Edad"]["Year"];
                $months = $alumno["Edad"]["Month"];
                $edad = "$years años y $months meses";
                ExcelWriter::CrearCeldaCombinada($hoja, "G$currentFila:H$currentFila", $edad, EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
                $hoja->getStyle("G$currentFila")->getFont()->setSize(11);


                //Edad al 31 de diciembre
                $fechaNac = $alumno["FechaNacimiento"];
                $fechaNac = DateTime::createFromFormat("d/m/Y", $fechaNac);
                $currentYear = date("Y");
                $finYear = new DateTime("$currentYear-12-31");

                $fechaHelper = new FechaHelper();
                $edadAl31 = $fechaHelper->ObtenerDiferenciaFechas($fechaNac, $finYear);

                ExcelWriter::CrearCeldaCombinada($hoja, "I$currentFila:J$currentFila", "$edadAl31->y años y $edadAl31->m meses", EstilosSpreadsheet::ESTILOS_CELDAS["Listado"]);
                $hoja->getStyle("I$currentFila")->getFont()->setSize(11);

                ExcelWriter::AgregarFilasEnBlanco($currentFila, 1);
            }

            ExcelWriter::AgregarFilasEnBlanco($currentFila, 2);
        }
    }

    private function CrearHojaAlumnosVacios(Worksheet $hoja, string $grado){

        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $hoja->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        ExcelWriter::CrearCeldaCombinada($hoja, "A2:J2", "Detalle de alumnos preinscritos para ".GradosValidos::TITULOS_GRADO[$grado]. " grado", EstilosSpreadsheet::ESTILOS_CELDAS["Titulo"]);
        
        $currentFila = $hoja->getHighestRow() + 1;
        $currentFila++;

        $rango = "A$currentFila:J".$currentFila+10;

        ExcelWriter::CrearCeldaCombinada($hoja, "$rango", "Sin alumnos válidos preinscritos para este grado", EstilosSpreadsheet::ESTILOS_CELDAS["HojaVacia"]);
        
    }

    private function getUploadError(int $errorCode): string {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo definido en el formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo solo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo',
            default => 'Error desconocido al subir el archivo',
        };
    }

}
