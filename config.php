<?php
/*
    Copyright (C) 2026 Alfonso Orozco Aguilar
    Licencia MIT
    Configuración de Base de Datos - Proyecto Generico 
 */

// Datos de conexión (Edita estos valores)
$host     = 'localhost';
$dbname   = 'tu_base_de_datos'; // Nombre de tu base de datos
$username = 'tu_usuario';
$password = 'tu_password';

// Crear la conexión mysqli
$link = mysqli_connect($host, $username, $password, $dbname);

// Verificar la conexión
if (!$link) {
    die("Error de conexión (" . mysqli_connect_errno() . "): " . mysqli_connect_error());
}

// Establecer el juego de caracteres a utf8mb4 (vital para nombres y descripciones)
if (!mysqli_set_charset($link, "utf8mb4")) {
    die("Error cargando el conjunto de caracteres utf8mb4: " . mysqli_error($link));
}

// Configurar la zona horaria (opcional, pero recomendado para logs)
date_default_timezone_set('America/Mexico_City');
?>
