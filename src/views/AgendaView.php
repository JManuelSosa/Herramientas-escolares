<?php
$today = new DateTime('now');
$startYear = (int) $today->format('Y'); 
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de agenda</title>
</head>
<body>
    <section>
        <h1>Aqui puedes crear la agenda</h1>
        <form action="generar-agenda" method="post">
            <fieldset>
                <legend>Vista de generación de agenda</legend>
                    <label for="select-month">Seleccione el mes de inicio</label>
                    <select id="select-month" name="startMonth">
                            <option value="1">Enero</option>
                            <option value="2">Febrero</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7">Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                    </select>

                    <br>
                    <br>

                    <label for="select-year">Seleccione el año de inicio</label>
                    <select name="startYear" id="select-mont">
                        <?php 
                            for($i = 0; $i < 10; $i++){
                                $currentYear = $startYear + $i;
                                $selected = ($i === 0) ? 'selected' : '';

                                echo "<option value='$currentYear' $selected>$currentYear</option>";
                            }
                        ?>
                    </select>

                    <button type="submit">Generar Agenda</button>
            </fieldset>
        </form>
    </section>
</body>
</html>