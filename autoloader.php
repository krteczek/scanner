<?php
// /scanner/autoloader.php

spl_autoload_register(function ($className) {
    echo "🔧 AUTOLOADER: Hledám třídu: <strong>$className</strong><br>";

    $prefix = 'Scanner\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $className, $len) !== 0) {
        echo "🔧 AUTOLOADER: Přeskočeno - nesedí prefix '$prefix'<br>";
        return;
    }

    $relativeClass = substr($className, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    echo "🔧 AUTOLOADER: Převádím na soubor: <strong>$file</strong><br>";
    echo "🔧 AUTOLOADER: Soubor existuje: " . (file_exists($file) ? '✅ ANO' : '❌ NE') . "<br>";

    if (file_exists($file)) {
        require $file;
        echo "🔧 AUTOLOADER: ✅ Soubor načten!<br><br>";
    } else {
        echo "🔧 AUTOLOADER: ❌ Soubor nenalezen!<br><br>";
    }
});

echo "🔧 AUTOLOADER: Registrován<br><br>";