<?php
/**
 * Builds lang/TEMPLATE.php containing every translatable string in Affreso.
 * Usage: php tools/extract-strings.php
 * Then copy it to lang/<code>.php (e.g. lang/zh-CN.php, lang/ko.php) and translate the values.
 * Keep %s and %d placeholders in the same order.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$strings = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if (preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m)) {
        foreach ($m[1] as $s) {
            $strings[stripcslashes(str_replace("\\'", "'", $s))] = true;
        }
    }
    if (preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $src, $m)) {
        foreach ($m[1] as $s) {
            $strings[stripcslashes($s)] = true;
        }
    }
}
ksort($strings);
$out = "<?php\n// Affreso translation template — " . count($strings) . " strings. Generated " . date('Y-m-d') . ".\n// Copy to lang/<code>.php and replace each value with the translation. Leave a value as '' to fall back to English.\nreturn [\n";
foreach (array_keys($strings) as $s) {
    $out .= '    ' . var_export($s, true) . " => '',\n";
}
$out .= "];\n";
file_put_contents($root . '/lang/TEMPLATE.php', $out);
echo 'Wrote lang/TEMPLATE.php with ' . count($strings) . " strings\n";
