<?php
/*    
    Copyright (C) 2026 Alfonso Orozco Aguilar

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation; version 2.1 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program; if not, write to the Free Software Foundation,
    Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/
/*
Programdor: Alfonso Orozco
Sistema: xxx (7.4 PHP)
Objetivo: Programador, para mostrar errores
Fecha: 07/oct/2022
Status o comentario: PHP no muestra errores em 7.x/8.x a menos que hay un include
                     se usa por ejemplo pru.php para depurar. Es depurador
*/

// para mostrar errores en php7
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//require_once('index.php');
require_once('import.php');
?>
