<?php
/*
 * ============================================================================
 *  AIRGAPPED  -  Kit base de funciones PHP para entornos sin internet
 * ============================================================================
 *  Fecha:        6 de noviembre de 2026
 *  Compatible:   PHP 5.6 a 8.4
 *  Licencia:     MIT (texto completo al final de este comentario)
 *  Autor:        Alfonso Orozco Aguilar  
 *
 *  QUE ES
 *  ------
 *  Un solo archivo, sin Composer, sin CDNs y sin librerias externas, pensado
 *  para llevarse en USB o impreso a un entorno "air-gapped" (aislado, sin
 *  acceso a internet) donde no se puede descargar nada si algo falla.
 *
 *  POR QUE ESTAS FUNCIONES SUELEN SER UTILES EN UN ENTORNO AISLADO
 *  ---------------------------------------------------------------
 *  1. Reconocimiento de una base de codigo legacy desconocida:
 *       listaArchivosPHP()  -> inventario de archivos .php (y similares).
 *       infoArchivoPHP()    -> radiografia por archivo: UTF-8, BOM, clases,
 *                              numero de lineas.
 *  2. Acceso a MySQL tolerante a fallos:
 *       aValues319()        -> regresa siempre un arreglo; si no hay datos
 *                              regresa array("") en vez de un error.
 *  3. Importes financieros exactos (sin errores de flotante):
 *       CFDINumbers()       -> trunca con bcmath (requiere extension).
 *       CFDINumbers2()      -> trunca con texto puro (sin extensiones).
 *  4. Utilidades basicas:
 *       randomstring()      -> cadena alfanumerica para nombres/ids.
 *       Headers y charset   -> sin cache y todo en UTF-8.
 *
 *  REQUISITOS (verificar en el servidor antes de usar)
 *  ---------------------------------------------------
 *    mbstring  : function_exists('mb_internal_encoding')
 *    bcmath    : function_exists('bcdiv')            (solo CFDINumbers)
 *    tokenizer : function_exists('token_get_all')    (infoArchivoPHP; si falta
 *                usa un respaldo con regex, menos preciso)
 *    mysqli    : function_exists('mysqli_connect')   (aValues319)
 *    SPL / PCRE: incluidas por defecto.
 *
 *  USO
 *  ---
 *    require_once 'airgapped.php';           // como libreria
 *    airgapped.php?test=1                    // casos de prueba en navegador
 *    php airgapped.php test                  // casos de prueba en consola
 *
 *  NOTA SOBRE ESTE ARCHIVO
 *  -----------------------
 *  Guardar en UTF-8 SIN BOM. Un BOM o un espacio antes de "<?php" provoca el
 *  error "headers already sent".
 *
 * ----------------------------------------------------------------------------
 *  MIT License
 *
 *  Copyright (c) 2026 Alfonso Orozco Aguilar
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a
 *  copy of this software and associated documentation files (the "Software"),
 *  to deal in the Software without restriction, including without limitation
 *  the rights to use, copy, modify, merge, publish, distribute, sublicense,
 *  and/or sell copies of the Software, and to permit persons to whom the
 *  Software is furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in
 *  all copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 *  FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 *  DEALINGS IN THE SOFTWARE.
 * ============================================================================
 */

/* ============================================================================
   1. HEADERS Y CHARSET
   Deben ir ANTES de cualquier salida.
   ============================================================================ */
if (!headers_sent()) {
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header('Content-Type: text/html; charset=UTF-8');
}

//  this is a dummy char for pspad, no remove é

ini_set('default_charset', 'UTF-8');

// REQUIERE extension mbstring. En PHP 8.x alguna de estas puede avisar
// "Deprecated"; por eso se protegen. default_charset ya cubre lo importante.
if (function_exists('mb_internal_encoding')) @mb_internal_encoding('UTF-8');
if (function_exists('mb_http_output'))       @mb_http_output('UTF-8');

//require_once 'sdk2.php'; // libreria de timbrado

// Desde PHP 8.1 mysqli lanza excepciones por defecto. Se apaga para que
// aValues319() maneje los errores a su manera (como en PHP 5.6 a 8.0).
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);

/* ============================================================================
   2. ACCESO A MYSQL
   ============================================================================ */

// Respaldo: si el sistema ya define cssalert() se usa la suya.
if (!function_exists('cssalert')) {
    function cssalert($color, $html) {
        return '<div style="color:' . $color . ';font-family:monospace">' . $html . '</div>';
    }
}

/* ----------------------------------------------------------------------------
   aValues319: ejecuta un query y regresa SIEMPRE un arreglo plano.
   REQUIERE extension mysqli y que $link ya sea una conexion establecida.
   - Sin datos, o query que no es SELECT (insert/update/delete): array("").
   - Con datos: orden plano (fila1 col1, fila1 col2, fila2 col1, ...).
     El llamador debe conocer el numero de columnas.
   - NULL se convierte a "".
   - Usa fetch_row (por posicion), asi que no falla con columnas repetidas
     en un JOIN.
   ---------------------------------------------------------------------------- */
function aValues319($Qx) {
    global $link;

    $rsX = mysqli_query($link, $Qx);

    if ($link->error <> '') {
        echo cssalert("red", "<br /><br /><br /><br /><li>" . htmlspecialchars($link->error) . "<hr>" . htmlspecialchars($Qx));
        return array("");
    }

    // insert/update/delete devuelven true, no un resultset
    if (!($rsX instanceof mysqli_result)) return array("");

    if (mysqli_num_rows($rsX) == 0) {
        mysqli_free_result($rsX);
        return array("");
    }

    $aDataX = array();
    while ($regX = mysqli_fetch_row($rsX)) {
        foreach ($regX as $v) {
            $aDataX[] = ($v === null) ? "" : $v;
        }
    }
    mysqli_free_result($rsX);
    return $aDataX;
}

/* ============================================================================
   3. ANALISIS DE CODIGO LEGACY
   ============================================================================ */

/* ----------------------------------------------------------------------------
   listaArchivosPHP: ruta completa de todos los archivos .php de forma
   recursiva, ordenados alfabeticamente.
   Usa solo clases SPL. Salta carpetas sin permiso.
   Si el sistema usa otras extensiones, ampliar el arreglo $ext.
   ---------------------------------------------------------------------------- */
function listaArchivosPHP($ruta) {
    $resultado = array();
    $ext = array('php'); // ej: array('php','inc','phtml','php3','php4','php5')

    $ruta = realpath($ruta);
    if ($ruta === false || !is_dir($ruta)) return $resultado;

    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($it as $archivo) {
            if ($archivo->isFile() && in_array(strtolower($archivo->getExtension()), $ext, true)) {
                $resultado[] = $archivo->getPathname();
            }
        }
    } catch (Exception $e) {
        // regresa lo que alcanzo a recolectar
    }

    sort($resultado);
    return $resultado;
}

/* ----------------------------------------------------------------------------
   infoArchivoPHP: regresa un texto como "YNY|1680".
     Posicion 1: Y si es UTF-8 valido (un archivo solo ASCII cuenta como Y).
     Posicion 2: Y si tiene BOM UTF-8 (EF BB BF) al inicio.
     Posicion 3: Y si declara clases (no cuenta Foo::class ni "new class").
     Despues del "|": numero de lineas.
   Archivo ilegible: "???|0".
   REQUIERE tokenizer (si falta, usa un respaldo con regex, menos preciso).
   Un N en la posicion 1 casi siempre indica ISO-8859-1 / Windows-1252.
   Lee el archivo completo en memoria (correcto para codigo fuente).
   ---------------------------------------------------------------------------- */
function infoArchivoPHP($archivo) {
    if (!is_file($archivo) || !is_readable($archivo)) return "???|0";

    $c = file_get_contents($archivo);
    if ($c === false) return "???|0";

    // 1) UTF-8 valido
    $utf8 = ($c === '' || preg_match('//u', $c) === 1) ? 'Y' : 'N';

    // 2) BOM UTF-8
    $bom = (substr($c, 0, 3) === "\xEF\xBB\xBF") ? 'Y' : 'N';

    // 3) Clases declaradas
    $clases = 'N';
    if (function_exists('token_get_all')) {
        $tokens = @token_get_all($c);
        $prev = null;
        foreach ($tokens as $t) {
            if (is_array($t)) {
                $id = $t[0];
                if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) continue;
                if ($id === T_CLASS && $prev !== T_DOUBLE_COLON && $prev !== T_NEW) {
                    $clases = 'Y';
                    break;
                }
                $prev = $id;
            } else {
                $prev = $t;
            }
        }
    } else {
        if (preg_match('/^\s*(?:abstract\s+|final\s+)*class\s+[A-Za-z_]\w*/mi', $c)) $clases = 'Y';
    }

    // 4) Numero de lineas
    if ($c === '') {
        $lineas = 0;
    } else {
        $lineas = substr_count($c, "\n");
        if (substr($c, -1) !== "\n") $lineas++;
    }

    return $utf8 . $bom . $clases . '|' . $lineas;
}

/* ============================================================================
   4. IMPORTES (TRUNCADO PARA CFDI)
   ============================================================================ */

/* ----------------------------------------------------------------------------
   CFDINumbers: trunca (sin redondear) a 2 decimales.
   REQUIERE extension bcmath (verificar con function_exists('bcdiv')).
   Limitaciones: ignora $p; con valores no numericos o floats puede variar
   segun la version de PHP (en PHP 8 un valor no numerico lanza ValueError).
   Para mayor portabilidad ver CFDINumbers2.
   ---------------------------------------------------------------------------- */
function CFDINumbers($val, $p = 2) {
    // https://stackoverflow.com/questions/9944001/delete-digits-after-two-decimal-points-without-rounding-the-value
    $res = bcdiv($val, 1, 2);
    if ($val == 0) return "0.00";
    return $res;
}

/* ----------------------------------------------------------------------------
   CFDINumbers2: trunca a $p decimales con manipulacion de texto.
   NO requiere bcmath ni ninguna extension (solo PCRE, incluido por defecto).
   Respeta $p, evita "-0.00" y se comporta igual de PHP 5.6 a 8.4.
   Recomendado: pasar el valor como string (DECIMAL de MySQL ya llega asi).
   ---------------------------------------------------------------------------- */
function CFDINumbers2($val, $p = 2) {
    $p = max(0, (int)$p);

    // floats: formato fijo, sin notacion cientifica
    if (is_float($val)) $val = sprintf('%.8F', $val);

    // limpia separadores de miles, signo de pesos y espacios
    $val = str_replace(array(',', '$', ' '), '', trim((string)$val));

    // valida; si no es numerico se trata como 0
    if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $val, $m)
        || ($m[2] === '' && (!isset($m[3]) || $m[3] === ''))) {
        $m = array('', '', '0', '');
    }

    $neg = ($m[1] === '-');
    $ent = ltrim($m[2], '0');
    if ($ent === '') $ent = '0';
    $dec = isset($m[3]) ? $m[3] : '';

    if ($p > 0) {
        $dec = substr(str_pad($dec, $p, '0'), 0, $p);
        $res = $ent . '.' . $dec;
    } else {
        $res = $ent;
    }

    // evita "-0.00"
    if ($neg && trim($res, '0.') !== '') $res = '-' . $res;
    return $res;
}

/* ============================================================================
   5. UTILIDADES
   ============================================================================ */

/* ----------------------------------------------------------------------------
   randomstring: cadena alfanumerica de $leng caracteres.
   Uso basico (nombres de archivo, ids). NO usar para tokens ni claves.
   random_int existe desde PHP 7; en 5.6 usa mt_rand.
   ---------------------------------------------------------------------------- */
function randomstring($leng = 10) {
    $leng = (int)$leng;
    if ($leng <= 0) $leng = 10;
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($abc) - 1;
    $res = '';
    for ($i = 0; $i < $leng; $i++) {
        $res .= $abc[function_exists('random_int') ? random_int(0, $max) : mt_rand(0, $max)];
    }
    return $res;
}

/* ============================================================================
   6. CASOS DE PRUEBA
   Solo corren si este archivo se ejecuta directamente con ?test=1 (web)
   o "php airgapped.php test" (consola). Como libreria no hacen nada.
   ============================================================================ */
function airgapped_selftest() {
    $ok = 0; $fail = 0;
    $chk = function ($nombre, $obtenido, $esperado) use (&$ok, &$fail) {
        $bien = ($obtenido === $esperado);
        if ($bien) $ok++; else $fail++;
        echo ($bien ? "[OK]   " : "[FALLA]") . " $nombre => '$obtenido'" . ($bien ? "" : " (esperado '$esperado')") . "\n";
    };

    echo "PHP " . PHP_VERSION . "\n";
    echo "mbstring:  " . (function_exists('mb_internal_encoding') ? 'si' : 'NO') . "\n";
    echo "bcmath:    " . (function_exists('bcdiv') ? 'si' : 'NO (solo afecta CFDINumbers)') . "\n";
    echo "tokenizer: " . (function_exists('token_get_all') ? 'si' : 'NO (usa respaldo regex)') . "\n";
    echo "mysqli:    " . (function_exists('mysqli_connect') ? 'si' : 'NO') . "\n\n";

    // CFDINumbers2
    $chk('CFDINumbers2("12.349")',    CFDINumbers2("12.349"),    "12.34");
    $chk('CFDINumbers2("5")',         CFDINumbers2("5"),         "5.00");
    $chk('CFDINumbers2(".5")',        CFDINumbers2(".5"),        "0.50");
    $chk('CFDINumbers2("-0.004")',    CFDINumbers2("-0.004"),    "0.00");
    $chk('CFDINumbers2("1,234.999")', CFDINumbers2("1,234.999"), "1234.99");
    $chk('CFDINumbers2(0.1+0.2)',     CFDINumbers2(0.1 + 0.2),   "0.30");
    $chk('CFDINumbers2("12.349", 6)', CFDINumbers2("12.349", 6), "12.349000");
    $chk('CFDINumbers2("")',          CFDINumbers2(""),          "0.00");

    // CFDINumbers (solo si hay bcmath)
    if (function_exists('bcdiv')) {
        $chk('CFDINumbers("12.349")', CFDINumbers("12.349"), "12.34");
        $chk('CFDINumbers("0")',      CFDINumbers("0"),      "0.00");
    }

    // randomstring
    $chk('strlen(randomstring())',   (string)strlen(randomstring()),   "10");
    $chk('strlen(randomstring(25))', (string)strlen(randomstring(25)), "25");
    $chk('strlen(randomstring(0))',  (string)strlen(randomstring(0)),  "10");

    // listaArchivosPHP / infoArchivoPHP sobre este mismo archivo
    $lista = listaArchivosPHP(dirname(__FILE__));
    $chk('listaArchivosPHP incluye este archivo',
         in_array(realpath(__FILE__), $lista, true) ? 'si' : 'no', 'si');
    $info = infoArchivoPHP(__FILE__);
    echo "infoArchivoPHP(este archivo) => $info  (esperado YNN|n, con n = lineas)\n";
    $chk('infoArchivoPHP archivo inexistente', infoArchivoPHP('/ruta/que/no/existe.php'), "???|0");

    echo "\nResultado: $ok bien, $fail con falla\n";
}

$__ag_directo = isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
if ($__ag_directo) {
    $__ag_pedir = isset($_GET['test']) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] === 'test');
    if ($__ag_pedir) {
        echo (PHP_SAPI === 'cli') ? '' : '<pre>';
        airgapped_selftest();
        echo (PHP_SAPI === 'cli') ? '' : '</pre>';
    }
}
unset($__ag_directo, $__ag_pedir);
