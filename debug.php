<?php
// debug.php v kořeni scanneru
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'autoloader.php';

echo "<h3>Test autoloaderu</h3>";

// 1. Test existence handleru
$handlerClass = 'Scanner\Handlers\ProjectListHandler';
echo "1. Testuji třídu: $handlerClass<br>";

if (class_exists($handlerClass)) {
    echo "✅ Třída existuje<br>";
    
    // 2. Test vytvoření instance
    try {
        $handler = new $handlerClass();
        echo "✅ Instance vytvořena<br>";
        
        // 3. Test metody handle()
        echo "3. Testuji metodu handle()...<br>";
        $result = $handler->handle([]);
        echo "✅ Handler vrátil: " . substr($result, 0, 50) . "...<br>";
    } catch (Exception $e) {
        echo "❌ Chyba při vytváření/volání: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Třída NEEXISTUJE<br>";
    
    // Debug: kam autoloader hledá
    echo "<h4>Debug cesty:</h4>";
    $expectedFile = __DIR__ . '/src/Handlers/ProjectListHandler.php';
    echo "Očekávaný soubor: $expectedFile<br>";
    echo "Soubor existuje: " . (file_exists($expectedFile) ? 'ANO' : 'NE') . "<br>";
    
    if (file_exists($expectedFile)) {
        echo "Obsah souboru (prvních 500 znaků):<br>";
        echo "<pre>" . htmlspecialchars(substr(file_get_contents($expectedFile), 0, 500)) . "</pre>";
    }
}

echo "<hr><h3>Debug konfigurace</h3>";
echo "config/actions.php:<br>";
$actions = require 'config/actions.php';
echo "<pre>" . print_r($actions, true) . "</pre>";