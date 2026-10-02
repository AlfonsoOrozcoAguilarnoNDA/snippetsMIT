<?php
/**
 * contadorClaudeLOC.php
 * Licencia Mit
 * Fecha : 2 de octubre 2026 - Alfonso Orozco Aguilar con apoyo de
 *         Claude Soonet 5.5 Medio
 *
 * Cuenta las líneas totales de uno o dos archivos PHP y las líneas que
 * ocupa cada función (desde la palabra "function" hasta su llave de cierre).
 *
 *  caso de uso : https://vibecodingmexico.com/claude-y-monolitos-de-codigo/
 *
 * Uso: súbelo a la misma carpeta que los archivos a medir y ábrelo en el
 *      navegador. Aparece un formulario para escribir los dos nombres.
 *      (También acepta ?a=archivo1.php&b=archivo2.php en la URL y, si
 *      algún día lo necesitas, consola: php contador_funciones.php a.php b.php)
 *      En web solo lee archivos .php de esta misma carpeta.
 *
 * Si los dos nombres apuntan al mismo archivo, solo se analiza el primero.
 * Usa token_get_all(), así que las llaves dentro de strings, comentarios
 * o heredocs no confunden el conteo.
 */

/**
 * Analiza un archivo y devuelve el total de líneas y sus funciones.
 *
 * @return array ['archivo', 'total', 'funciones' => [[nombre, inicio, fin, lineas], ...]]
 */
function contar_funciones($ruta)
{
    $codigo = @file_get_contents($ruta);
    if ($codigo === false) {
        throw new RuntimeException("No se pudo leer el archivo: $ruta");
    }

    $total = ($codigo === '') ? 0 : substr_count($codigo, "\n") + (substr($codigo, -1) === "\n" ? 0 : 1);

    $tokens = token_get_all($codigo);
    $n = count($tokens);

    // Línea en la que empieza cada token (calculada por nosotros, porque
    // los tokens de un solo carácter no traen número de línea).
    $lineas = array();
    $linea = 1;
    foreach ($tokens as $i => $t) {
        $lineas[$i] = $linea;
        $linea += substr_count(is_array($t) ? $t[1] : $t, "\n");
    }

    $vacios = array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT);
    $funciones = array();

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        // Ignorar "use function Foo\bar;"
        $p = $i - 1;
        while ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], $vacios, true)) {
            $p--;
        }
        if ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_USE) {
            continue;
        }

        // Siguiente token significativo: nombre (o "&" o "(" si es closure)
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], $vacios, true)) {
            $j++;
        }
        // "&" es un string en PHP < 8.1 y un token (array) a partir de 8.1
        if ($j < $n && (is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j]) === '&') {
            $j++;
            while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], $vacios, true)) {
                $j++;
            }
        }
        if ($j >= $n || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            continue; // función anónima (closure): no tiene nombre, se omite
        }
        $nombre = $tokens[$j][1];

        // Buscar la llave que abre el cuerpo (o ";" si es abstracta/interfaz)
        $par = 0;
        $abre = -1;
        for ($k = $j + 1; $k < $n; $k++) {
            $t = $tokens[$k];
            if ($t === '(') {
                $par++;
            } elseif ($t === ')') {
                $par--;
            } elseif ($t === '{' && $par === 0) {
                $abre = $k;
                break;
            } elseif ($t === ';' && $par === 0) {
                break;
            }
        }
        if ($abre < 0) {
            continue;
        }

        // Emparejar llaves hasta cerrar el cuerpo
        $prof = 0;
        $cierre = -1;
        for ($k = $abre; $k < $n; $k++) {
            $t = $tokens[$k];
            if ($t === '{' || (is_array($t) && ($t[0] === T_CURLY_OPEN || $t[0] === T_DOLLAR_OPEN_CURLY_BRACES))) {
                $prof++;
            } elseif ($t === '}') {
                $prof--;
                if ($prof === 0) {
                    $cierre = $k;
                    break;
                }
            }
        }
        if ($cierre < 0) {
            continue; // archivo con llaves desbalanceadas
        }

        $ini = $lineas[$i];
        $fin = $lineas[$cierre];
        $funciones[] = array(
            'nombre' => $nombre,
            'inicio' => $ini,
            'fin'    => $fin,
            'lineas' => $fin - $ini + 1,
        );
    }

    return array('archivo' => $ruta, 'total' => $total, 'funciones' => $funciones);
}

/**
 * Analiza uno o dos archivos. Si ambos son el mismo, usa solo el primero.
 */
function contar_funciones_dos($a, $b = '')
{
    $rutas = array($a);
    if ($b !== '' && $b !== null) {
        $ra = realpath($a);
        $rb = realpath($b);
        $mismo = ($ra !== false && $rb !== false) ? ($ra === $rb) : ($a === $b);
        if (!$mismo) {
            $rutas[] = $b;
        }
    }
    $res = array();
    foreach ($rutas as $r) {
        $res[] = contar_funciones($r);
    }
    return $res;
}

/** Resumen numérico de un resultado. */
function resumen_archivo($r)
{
    $en_funciones = 0;
    $mayor = null;
    foreach ($r['funciones'] as $f) {
        $en_funciones += $f['lineas'];
        if ($mayor === null || $f['lineas'] > $mayor['lineas']) {
            $mayor = $f;
        }
    }
    return array(
        'num_funciones' => count($r['funciones']),
        'en_funciones'  => $en_funciones,
        'fuera'         => $r['total'] - $en_funciones, // código entre funciones, comentarios, etc.
        'mayor'         => $mayor,
    );
}

// ---------------------------------------------------------------------
// Interfaz: página web con formulario (o consola, opcional)
// ---------------------------------------------------------------------

function h_($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Valida un nombre de archivo: solo .php y solo de esta carpeta. */
function ruta_segura($nombre)
{
    $nombre = basename($nombre);
    if (strtolower(substr($nombre, -4)) !== '.php') {
        throw new RuntimeException("Solo se permiten archivos .php: $nombre");
    }
    $ruta = __DIR__ . DIRECTORY_SEPARATOR . $nombre;
    if (!is_file($ruta)) {
        throw new RuntimeException("No existe en esta carpeta: $nombre");
    }
    return $ruta;
}

function pagina_web()
{
    header('Content-Type: text/html; charset=utf-8');

    $archivos = array();
    foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . '*.php') as $f) {
        $archivos[] = basename($f);
    }
    sort($archivos);

    $a = isset($_GET['a']) ? trim($_GET['a']) : '';
    $b = isset($_GET['b']) ? trim($_GET['b']) : '';
    $orden = (isset($_GET['orden']) && $_GET['orden'] === 'tam') ? 'tam' : 'pos';

    echo "<!DOCTYPE html>\n<html lang='es'><head><meta charset='utf-8'>"
       . "<meta name='viewport' content='width=device-width, initial-scale=1'>"
       . "<title>Contador de líneas por función</title>"
       . "<style>"
       . "body{font-family:sans-serif;max-width:900px;margin:20px auto;padding:0 12px}"
       . "label{display:block;margin:8px 0 2px}input[type=text]{width:100%;max-width:420px;padding:5px}"
       . "table{border-collapse:collapse;margin:8px 0 24px}td,th{border:1px solid #999;padding:3px 10px}"
       . "td.n{text-align:right}th{background:#eee}.err{color:#b00;font-weight:bold}.nota{color:#555;font-size:.9em}"
       . "</style></head><body>";
    echo "<h2>Contador de líneas por función</h2>";

    // Formulario
    echo "<form method='get'>";
    echo "<label for='a'>Archivo 1 (.php de esta carpeta):</label>"
       . "<input type='text' id='a' name='a' list='lista' value='" . h_($a) . "' required>";
    echo "<label for='b'>Archivo 2 (opcional):</label>"
       . "<input type='text' id='b' name='b' list='lista' value='" . h_($b) . "'>";
    echo "<datalist id='lista'>";
    foreach ($archivos as $f) {
        echo "<option value='" . h_($f) . "'>";
    }
    echo "</datalist>";
    echo "<label for='orden'>Orden de las funciones:</label>"
       . "<select id='orden' name='orden'>"
       . "<option value='pos'" . ($orden === 'pos' ? ' selected' : '') . ">Como aparecen en el archivo</option>"
       . "<option value='tam'" . ($orden === 'tam' ? ' selected' : '') . ">Más largas primero</option>"
       . "</select><br><br>";
    echo "<button type='submit'>Analizar</button>";
    echo "<p class='nota'>Si el archivo 2 se deja vacío o es el mismo que el 1, solo se analiza el primero.</p>";
    echo "</form>";

    if ($a !== '') {
        try {
            $rutas_b = ($b !== '') ? ruta_segura($b) : '';
            $resultados = contar_funciones_dos(ruta_segura($a), $rutas_b);

            foreach ($resultados as $r) {
                $s = resumen_archivo($r);
                $fs = $r['funciones'];
                if ($orden === 'tam') {
                    usort($fs, function ($x, $y) {
                        return $y['lineas'] - $x['lineas'];
                    });
                }
                echo "<h3>" . h_(basename($r['archivo'])) . "</h3>";
                echo "<p>Líneas totales: <b>{$r['total']}</b> | Funciones: <b>{$s['num_funciones']}</b>"
                   . " | Dentro de funciones: <b>{$s['en_funciones']}</b>"
                   . " | Fuera de funciones: <b>{$s['fuera']}</b>";
                if ($s['mayor']) {
                    echo " | Más larga: <b>" . h_($s['mayor']['nombre']) . "</b> ({$s['mayor']['lineas']})";
                }
                echo "</p>";
                echo "<table><tr><th>Función</th><th>Inicio</th><th>Fin</th><th>Líneas</th></tr>";
                foreach ($fs as $f) {
                    echo "<tr><td>" . h_($f['nombre']) . "</td><td class='n'>{$f['inicio']}</td>"
                       . "<td class='n'>{$f['fin']}</td><td class='n'>{$f['lineas']}</td></tr>";
                }
                echo "</table>";
            }
        } catch (RuntimeException $e) {
            echo "<p class='err'>" . h_($e->getMessage()) . "</p>";
        }
    }
    echo "</body></html>";
}

function salida_consola($argv)
{
    $a = isset($argv[1]) ? $argv[1] : '';
    $b = isset($argv[2]) ? $argv[2] : '';
    if ($a === '') {
        echo "Uso: php contador_funciones.php archivo1.php [archivo2.php]\n";
        return;
    }
    foreach (contar_funciones_dos($a, $b) as $r) {
        $s = resumen_archivo($r);
        echo "\n== " . basename($r['archivo']) . " ==\n";
        echo "Líneas totales: {$r['total']} | Funciones: {$s['num_funciones']} | "
           . "En funciones: {$s['en_funciones']} | Fuera: {$s['fuera']}\n";
        printf("%-40s %8s %8s %8s\n", 'Función', 'Inicio', 'Fin', 'Líneas');
        foreach ($r['funciones'] as $f) {
            printf("%-40s %8d %8d %8d\n", $f['nombre'], $f['inicio'], $f['fin'], $f['lineas']);
        }
    }
}

// Se ejecuta solo si el archivo se abre directamente (no si se incluye con require)
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if (PHP_SAPI === 'cli') {
        salida_consola(isset($argv) ? $argv : array());
    } else {
        pagina_web();
    }
}
