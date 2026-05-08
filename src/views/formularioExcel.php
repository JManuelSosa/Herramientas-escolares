<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de nuevos alumnos</title>
</head>
<body>
    <form action="procesar-excel" method="post" enctype="multipart/form-data">
        <fieldset>
            <legend>Excel de alumnos</legend>
            <label for="Excel-File">Archivo de Excel</label>
            <input type="file" id="Excel-File" accept=".xlsx" name="Excel">
        </fieldset>
        <input type="submit" value="Procesar Alumnos">
    </form>
</body>
</html>