<?php
namespace App\Helpers;

class ValidarArchivo { 

    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls'];
    private const ALLOWED_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ];

    private function ValidarExtension(array $archivoEnviado): bool{
        if (empty($archivoEnviado['name'])) {
            return false;
        }

        $ext = strtolower(pathinfo($archivoEnviado['name'], PATHINFO_EXTENSION));
        return in_array($ext, self::ALLOWED_EXTENSIONS);
    }

    private function ValidarMime(array $archivoEnviado): bool{
        
        if (!isset($archivoEnviado['tmp_name']) || !is_uploaded_file($archivoEnviado['tmp_name'])) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivoEnviado['tmp_name']);
        finfo_close($finfo);

        return in_array($mime, self::ALLOWED_MIMES);

    }

    public function ValidarArchivoExcel(array $supuestoExcel): bool{
        return ($this->ValidarExtension($supuestoExcel) && $this->ValidarMime($supuestoExcel));
    }

}



