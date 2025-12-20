<?php
// test_autoload.php v kořeni
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Test autoloaderu</h3>";

// Načti autoloader
require_once 'autoloader.php';

// Test všech handlerů
$testClasses = [
    'Scanner\Handlers\HandlerInterface',
    'Scanner\Handlers\ProjectListHandler', 
    'Scanner\Handlers\ProjectScanHandler',
    'Scanner\Handlers\FileViewHandler',
    'Scanner\Handlers\RulesApiHandler',
    'Scanner\Handlers\ErrorHandler',
    'Scanner\Utilities\FileHelper',
    'Scanner\Core\ScannerEngine'
];

foreach ($testClasses as $className) {
    echo "$className: ";
    if (class_exists($className) || interface_exists($className)) {
        echo "✅ NAČTENO<br>";
    } else {
        echo "❌ NENAČTENO<br>";
        
        // Zkus najít soubor
        $relative = str_replace('Scanner\\', '', $className);
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
        echo "&nbsp;&nbsp;&nbsp;Hledám: $file<br>";
        echo "&nbsp;&nbsp;&nbsp;Existuje: " . (file_exists($file) ? 'ANO' : 'NE') . "<br>";
        
        if (file_exists($file)) {
            echo "&nbsp;&nbsp;&nbsp;Prvních 3 řádky:<br>";
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            for ($i = 0; $i < min(3, count($lines)); $i++) {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . htmlspecialchars($lines[$i]) . "<br>";
            }
        }
    }
}