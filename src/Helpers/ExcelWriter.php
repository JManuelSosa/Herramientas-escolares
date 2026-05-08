<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelWriter {

    public static function RenombrarHojaActiva(Spreadsheet $documento, string $nuevoNombre): void {
        $hoja = $documento->getActiveSheet();
        $hoja->setTitle($nuevoNombre);
    }

    public static function CrearYActivarHoja(Spreadsheet $documento, string $nombreHoja): Worksheet {
        $nuevaHoja = new Worksheet($documento, $nombreHoja);
        $documento->addSheet($nuevaHoja);
        $documento->setActiveSheetIndexByName($nombreHoja);

        return $documento->getActiveSheet();
    }

    public static function CrearCeldaCombinada(Worksheet $hoja, string $rangoCeldas, string $value, array $estilo): void {
        self::CombinarCeldas($hoja, $rangoCeldas);
        $celdaActiva = self::ObtenerCeldaActiva($rangoCeldas);
        self::SetValue($hoja, $celdaActiva, $value);
        self::AplicarEstilo($hoja, $rangoCeldas, $estilo);
    }

    public static function CombinarCeldas(Worksheet $hoja, string $rangoCeldas): void {
        $hoja->mergeCells($rangoCeldas);
        $celdaActiva = self::ObtenerCeldaActiva($rangoCeldas);
        $hoja->getStyle($celdaActiva)->getAlignment()->setWrapText(true);
    }

    public static function SetValue(Worksheet $hoja, string $celda, string $value): void {
        $hoja->setCellValue($celda, $value);
    }

    public static function AplicarEstilo(Worksheet $hoja, string $celda, array $estilo): void {
        $hoja->getStyle($celda)->applyFromArray($estilo);
    }

    private static function ObtenerCeldaActiva(string $rangoCeldas): string {
        $celdas = explode(":", $rangoCeldas);
        return $celdas[0];
    }

    public static function AgregarFilasEnBlanco(int &$fila, int $cantidadEspacios): void {
        $fila = $fila + $cantidadEspacios;
    }

    public static function SetAlturaFilas(Worksheet $hoja, int $startRow, int $endRow, float $heightInPixels): void {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $hoja->getRowDimension($row)->setRowHeight($heightInPixels);
        }
    }

    //* Versión optimizada de CrearYActivarHoja

    public static function CrearHoja(Spreadsheet $documento, string $nombreHoja): Worksheet {
        $nuevaHoja = new Worksheet($documento, $nombreHoja);
        $documento->addSheet($nuevaHoja);
        return $nuevaHoja;
    }

    public static function GetHeighRowInPixels(float $heighInPixels): float {
        return $heighInPixels * 0.775;
    }


}