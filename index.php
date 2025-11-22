<?php
// /scanner/index.php
/**
 * Project Scanner - Entry Point
 *
 * @package Scanner
 * @author KRS3
 * @version 2.0
 */

declare(strict_types=1);

// ✅ NAČTENÍ AUTOLOADERU
require_once __DIR__ . '/autoloader.php';

// Načtení konfigurace
$config = require __DIR__ . '/config/app.php';


// Spuštění scanneru
try {
    $scanner = new Scanner\Core\ScannerEngine($config);
    $scanner->run();
} catch (Exception $e) {
    echo "❌ Chyba scanneru: " . $e->getMessage();
    error_log("Scanner Error: " . $e->getMessage());
}
?>