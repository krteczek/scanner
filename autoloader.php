<?php
/**
 * Jednoduchý autoloader pro Scanner aplikaci
 * 
 * Načítá třídy podle PSR-4-like konvence:
 * Scanner\Namespace\ClassName → src/Namespace/ClassName.php
 */

declare(strict_types=1);

spl_autoload_register(function (string $className): void {
    // Mapování namespace na adresář
    $namespaceMap = [
        'Scanner\\' => __DIR__ . '/src/',
    ];
    
    foreach ($namespaceMap as $prefix => $baseDir) {
        // Kontrola zda třída patří do tohoto namespace
        $len = strlen($prefix);
        if (strncmp($prefix, $className, $len) !== 0) {
            continue;
        }
        
        // Získání relativní cesty třídy
        $relativeClass = substr($className, $len);
        
        // Nahrazení namespace separátoru za adresářový separátor
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        // Načtení souboru pokud existuje
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
    
    // Debug informace (pouze v dev módu)
    if (defined('SCANNER_DEBUG') && SCANNER_DEBUG) {
        error_log("Autoloader: Třída '$className' nebyla nalezena.");
    }
});

// Funkce pro manuální načtení helperů (volitelné)
function require_helper(string $helperName): void {
    $helperFile = __DIR__ . '/src/Utilities/' . $helperName . '.php';
    if (file_exists($helperFile)) {
        require $helperFile;
    }
}